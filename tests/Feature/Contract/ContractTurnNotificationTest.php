<?php

namespace Tests\Feature\Contract;

use App\Jobs\NotifyRecipientTurn;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Support\ContractSigning;
use App\Support\CurrentProduction;
use App\Support\Features;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO C · FASE 4 — AVISO "TE TOCA". Cuando la ruta avanza (enviar / firmar / reenviar),
 * el destinatario en turno recibe el aviso con el enlace. Antes no existía ningún aviso y la página de
 * firma prometía uno que nunca llegaba. Se prueba el DESPACHO (el correo va por Job, como el de cierre).
 */
class ContractTurnNotificationTest extends QaTestCase
{
    private function draftEnvelope(): ContractEnvelope
    {
        $signer = $this->makeUser('crew');
        $payee  = Payee::create(['legal_nature' => 'fisica', 'name' => 'Demo ' . Str::random(5)]);
        $contract = $payee->contracts()->create([
            'concept' => PayeeContract::CONCEPT_CREW, 'is_active' => 1, 'production_id' => CurrentProduction::id(),
        ]);
        $env = ContractEnvelope::create([
            'payee_contract_id' => $contract->id, 'production_id' => $contract->production_id,
            'status' => ContractEnvelope::STATUS_DRAFT,
        ]);
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_CONTRACTED, 'sort_order' => 0, 'name' => 'Juan Pérez López',
            'email' => 'juan@demo.test', 'payee_id' => $payee->id, 'status' => ContractEnvelopeRecipient::STATUS_PENDING,
        ]);
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_SIGNER, 'sort_order' => 1, 'name' => 'Ana García',
            'cargo' => 'Gerente de Producción', 'email' => 'ana@demo.test', 'user_id' => $signer->id,
            'status' => ContractEnvelopeRecipient::STATUS_PENDING,
        ]);

        return $env->fresh();
    }

    public function test_sending_notifies_the_first_recipient(): void
    {
        Bus::fake();
        $env   = $this->draftEnvelope();
        $first = $env->orderedRecipients()->first();

        ContractSigning::send($env);

        Bus::assertDispatchedSync(NotifyRecipientTurn::class, fn ($j) => $j->recipientId === $first->id);
    }

    public function test_advancing_notifies_the_next_recipient(): void
    {
        Bus::fake();
        $env = $this->draftEnvelope();
        ContractSigning::send($env);
        $next = $env->fresh()->orderedRecipients()->where('sort_order', 1)->first();

        ContractSigning::sign($env->fresh()->currentRecipient->fresh(), 'authenticated', '1.1.1.1', 'data:image/png;base64,x');

        Bus::assertDispatchedSync(NotifyRecipientTurn::class, fn ($j) => $j->recipientId === $next->id);
    }

    public function test_completion_does_not_notify_a_further_turn(): void
    {
        Bus::fake();
        $env = $this->draftEnvelope();
        ContractSigning::send($env);   // aviso #0
        ContractSigning::sign($env->fresh()->currentRecipient->fresh(), 'authenticated', '1.1.1.1', 'data:image/png;base64,x'); // aviso #1
        ContractSigning::sign($env->fresh()->currentRecipient->fresh(), 'authenticated', '1.1.1.1', 'data:image/png;base64,y'); // completa → sin aviso

        // Dos avisos en total: el contratado y el firmante interno; ninguno tras completarse.
        Bus::assertDispatchedSyncTimes(NotifyRecipientTurn::class, 2);
    }

    public function test_resend_dispatches_the_turn_notification(): void
    {
        $env = $this->draftEnvelope();
        ContractSigning::send($env);

        Bus::fake();   // a partir de aquí
        ContractSigning::resend($env->fresh(), null);

        $current = $env->fresh()->currentRecipient;
        Bus::assertDispatchedSync(NotifyRecipientTurn::class, fn ($j) => $j->recipientId === $current->id);
    }

    public function test_flag_routes_the_notification_to_the_queue(): void
    {
        DB::table('feature_flags')->updateOrInsert(['key' => 'contracts_queue_email'], ['enabled' => 1]);
        Features::flush();
        Bus::fake();
        $env = $this->draftEnvelope();

        ContractSigning::send($env);

        Bus::assertDispatched(NotifyRecipientTurn::class);   // a la cola, no sync
    }

    public function test_signlink_builds_a_signed_signing_url(): void
    {
        $env = $this->draftEnvelope();
        $r   = $env->orderedRecipients()->first();

        $url = NotifyRecipientTurn::signLink($r);
        $this->assertStringContainsString('/contratos/firma/' . $r->id, $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_job_is_a_noop_when_it_is_no_longer_the_turn(): void
    {
        $env = $this->draftEnvelope();
        ContractSigning::send($env);
        // El firmante interno (#1) NO está en turno todavía → el aviso para él no debe hacer nada.
        $notCurrent = $env->fresh()->orderedRecipients()->where('sort_order', 1)->first();

        (new NotifyRecipientTurn($notCurrent->id))->handle();   // no debe lanzar
        $this->assertTrue(true);
    }
}
