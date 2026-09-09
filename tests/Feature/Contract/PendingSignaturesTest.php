<?php

namespace Tests\Feature\Contract;

use App\Events\ContractEnvelopeCompleted;
use App\Jobs\DeliverSignedContractEmail;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\Features;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO C — FIRMAS PENDIENTES (la "cola" de firmas a escala).
 * Verifica: la bandeja se limita al TURNO del propio usuario; el tablero agrupa por FIGURA; el LOTE
 * está detrás del flag (403 apagado); y el flag de COLA gobierna si el correo se encola.
 */
class PendingSignaturesTest extends QaTestCase
{
    /** Un sobre 'sent' cuyo turno ACTUAL es de $signer (el contratado ya firmó antes que él). */
    private function awaiting(User $signer, string $cargo): ContractEnvelope
    {
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Payee ' . Str::random(5)]);
        $contract = $payee->contracts()->create([
            'concept'            => PayeeContract::CONCEPT_CREW,
            'is_active'          => 1,
            'production_id'      => CurrentProduction::id(),
            'fee_amount'         => 50000,
            'fee_currency'       => 'MXN',
            'effective_date'     => '2026-01-01',
            'definitive_end_date' => '2026-12-31',
        ]);
        $env = ContractEnvelope::create([
            'payee_contract_id' => $contract->id,
            'production_id'     => $contract->production_id,
            'status'            => ContractEnvelope::STATUS_SENT,
            'sent_at'           => now(),
        ]);
        // #0 contratado — ya firmó, así el turno pasa al firmante interno.
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_CONTRACTED, 'sort_order' => 0,
            'name' => 'Contratado', 'status' => ContractEnvelopeRecipient::STATUS_SIGNED, 'signed_at' => now(),
        ]);
        // #1 el firmante interno — su TURNO ahora.
        $rec = $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_SIGNER, 'sort_order' => 1,
            'name' => trim($signer->name . ' ' . $signer->lname), 'cargo' => $cargo,
            'user_id' => $signer->id, 'status' => ContractEnvelopeRecipient::STATUS_SENT, 'sent_at' => now(),
        ]);
        $env->update(['current_recipient_id' => $rec->id]);

        return $env->fresh();
    }

    public function test_inbox_shows_only_my_current_turn(): void
    {
        $me = $this->actingAsRole('super-admin');           // el firmante logueado
        $other = $this->makeUser('crew');

        $mine  = $this->awaiting($me, 'Gerente de Producción');
        $their = $this->awaiting($other, 'Contador Fiscal');

        // Un tercer sobre donde YO soy firmante pero NO es mi turno (va otro antes).
        $notYet = $this->awaiting($other, 'Line Producer');
        $notYet->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_SIGNER, 'sort_order' => 2,
            'name' => $me->name, 'cargo' => 'Fiscal', 'user_id' => $me->id,
            'status' => ContractEnvelopeRecipient::STATUS_PENDING,
        ]);

        $res = $this->get(route('contracts.pending.index'));
        $res->assertOk();
        $res->assertSee(optional($mine->contract->payee)->name);     // mi turno → aparece
        $res->assertDontSee(optional($their->contract->payee)->name); // turno de otro → no
        $res->assertDontSee(optional($notYet->contract->payee)->name);// aún no me toca → no
        $res->assertSee('Gerente de Producción');                    // la figura con la que firmo
        $res->assertSee('$50,000.00 MXN');                           // contraprestación visible
    }

    public function test_board_groups_by_figure(): void
    {
        $this->actingAsRole('super-admin');
        $u1 = $this->makeUser('crew');
        $u2 = $this->makeUser('crew');
        $this->awaiting($u1, 'Contador Fiscal');
        $this->awaiting($u2, 'Representante Legal');

        $res = $this->get(route('contracts.pending.board'));
        $res->assertOk();
        $res->assertSee('Contador Fiscal');
        $res->assertSee('Representante Legal');
        $res->assertSee(__('Seguimiento de firmas'));
    }

    public function test_batch_is_forbidden_when_flag_off(): void
    {
        $this->actingAsRole('super-admin');
        // Flag apagado por defecto → 403.
        $res = $this->post(route('contracts.pending.batch'), ['recipient_ids' => [1]]);
        $res->assertForbidden();
    }

    public function test_batch_signs_selected_when_enabled(): void
    {
        $me = $this->actingAsRole('super-admin');
        $me->adopted_signature = 'data:image/png;base64,' . base64_encode('firma');
        $me->save();

        // Enciende el flag por override de BD (como el panel de admin).
        DB::table('feature_flags')->updateOrInsert(['key' => 'contracts_batch_signing'], ['enabled' => 1]);
        Features::flush();

        $a = $this->awaiting($me, 'Gerente de Producción');
        $b = $this->awaiting($me, 'Contador Fiscal');
        $ids = [$a->current_recipient_id, $b->current_recipient_id];

        $res = $this->post(route('contracts.pending.batch'), ['recipient_ids' => $ids]);
        $res->assertRedirect();

        foreach ($ids as $rid) {
            $this->assertSame(ContractEnvelopeRecipient::STATUS_SIGNED, ContractEnvelopeRecipient::find($rid)->status);
        }
        // Eran el último de cada ruta → los sobres quedan completados.
        $this->assertTrue($a->fresh()->isCompleted());
        $this->assertTrue($b->fresh()->isCompleted());
    }

    public function test_batch_ignores_foreign_recipients(): void
    {
        $me = $this->actingAsRole('super-admin');
        $me->adopted_signature = 'data:image/png;base64,' . base64_encode('firma');
        $me->save();
        DB::table('feature_flags')->updateOrInsert(['key' => 'contracts_batch_signing'], ['enabled' => 1]);
        Features::flush();

        $other = $this->makeUser('crew');
        $foreign = $this->awaiting($other, 'Contador Fiscal');   // turno de OTRO

        $res = $this->post(route('contracts.pending.batch'), ['recipient_ids' => [$foreign->current_recipient_id]]);
        $res->assertRedirect();
        // No se firmó nada ajeno.
        $this->assertNotSame(ContractEnvelopeRecipient::STATUS_SIGNED, ContractEnvelopeRecipient::find($foreign->current_recipient_id)->status);
    }

    public function test_queue_flag_routes_email_job(): void
    {
        Bus::fake();
        $env = $this->awaiting($this->makeUser('crew'), 'Contador Fiscal');

        // Apagado (default) → el correo se manda INMEDIATO (dispatchSync).
        event(new ContractEnvelopeCompleted($env));
        Bus::assertDispatchedSync(DeliverSignedContractEmail::class);

        // Encendido → el correo se va a la COLA (dispatch).
        DB::table('feature_flags')->updateOrInsert(['key' => 'contracts_queue_email'], ['enabled' => 1]);
        Features::flush();
        event(new ContractEnvelopeCompleted($env));
        Bus::assertDispatched(DeliverSignedContractEmail::class);
    }
}
