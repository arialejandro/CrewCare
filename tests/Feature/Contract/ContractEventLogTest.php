<?php

namespace Tests\Feature\Contract;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeEvent;
use App\Models\ContractEnvelopeRecipient;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Support\ContractEventLog;
use App\Support\ContractSigning;
use App\Support\CurrentProduction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO C — BITÁCORA DE EVENTOS (Fase 1 arquitectura tipo DocuSign). El estado del sobre
 * se DERIVA de una bitácora append-only encadenada. Verifica: cada transición escribe su evento; la
 * cadena de hashes verifica; alterar una fila la rompe; y la bitácora no se edita ni se borra.
 */
class ContractEventLogTest extends QaTestCase
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
            'role' => ContractEnvelopeRecipient::ROLE_CONTRACTED, 'sort_order' => 0,
            'name' => 'Juan Pérez López', 'payee_id' => $payee->id, 'status' => ContractEnvelopeRecipient::STATUS_PENDING,
        ]);
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_SIGNER, 'sort_order' => 1,
            'name' => 'Ana García', 'cargo' => 'Gerente de Producción', 'user_id' => $signer->id,
            'status' => ContractEnvelopeRecipient::STATUS_PENDING,
        ]);

        return $env->fresh();
    }

    public function test_signing_flow_writes_chained_events(): void
    {
        $this->actingAsRole('super-admin');       // actor de los eventos de envío
        $env = $this->draftEnvelope();
        $sig = 'data:image/png;base64,' . base64_encode('firma');

        ContractSigning::send($env->fresh());
        $this->assertDatabaseHas('contract_envelope_events', ['envelope_id' => $env->id, 'event' => 'sent']);

        $contratado = $env->orderedRecipients()->first();
        ContractSigning::markViewed($contratado->fresh());
        ContractSigning::recordConsent($contratado->fresh(), '187.190.0.1');
        ContractSigning::sign($contratado->fresh(), 'authenticated', '187.190.0.1', $sig);

        $signer = $env->fresh()->currentRecipient;
        ContractSigning::sign($signer->fresh(), 'authenticated', '187.190.0.1', $sig);

        // Todos los eventos del ciclo quedaron en la bitácora.
        foreach (['sent', 'viewed', 'consented', 'signed', 'completed'] as $ev) {
            $this->assertDatabaseHas('contract_envelope_events', ['envelope_id' => $env->id, 'event' => $ev]);
        }
        $this->assertSame(2, ContractEnvelopeEvent::where('envelope_id', $env->id)->where('event', 'signed')->count());
        $this->assertTrue($env->fresh()->isCompleted());

        // La cadena verifica de punta a punta.
        $chain = ContractEventLog::verifyChain($env->fresh());
        $this->assertTrue($chain['ok'], 'la cadena debe verificar');
        $this->assertGreaterThanOrEqual(5, $chain['count']);
    }

    public function test_tampering_a_row_breaks_the_chain(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();
        ContractSigning::send($env->fresh());
        ContractSigning::sign($env->orderedRecipients()->first()->fresh(), 'authenticated', '1.1.1.1', 'data:image/png;base64,' . base64_encode('x'));

        $this->assertTrue(ContractEventLog::verifyChain($env->fresh())['ok']);

        // Alterar una fila POR FUERA del modelo (como quien edita la BD directo).
        $ev = ContractEnvelopeEvent::where('envelope_id', $env->id)->orderBy('id')->first();
        DB::table('contract_envelope_events')->where('id', $ev->id)->update(['ip_address' => '9.9.9.9']);

        $broken = ContractEventLog::verifyChain($env->fresh());
        $this->assertFalse($broken['ok'], 'alterar una fila debe romper la cadena');
        $this->assertSame($ev->id, $broken['brokenAt']);
    }

    public function test_events_are_append_only(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();
        ContractSigning::send($env->fresh());
        $ev = ContractEnvelopeEvent::where('envelope_id', $env->id)->first();

        try {
            $ev->update(['event' => 'hackeado']);
            $this->fail('un evento no debe poder editarse');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $ev->delete();
            $this->fail('un evento no debe poder borrarse');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_cancel_route_logs_cancelled_event(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();
        ContractSigning::send($env->fresh());

        $res = $this->post(route('contracts.envelope.cancel', $env->id), ['reason' => 'prueba']);
        $res->assertRedirect();

        $this->assertDatabaseHas('contract_envelope_events', ['envelope_id' => $env->id, 'event' => 'cancelled']);
        $this->assertTrue($env->fresh()->isCancelled());
        $this->assertTrue(ContractEventLog::verifyChain($env->fresh())['ok']);
    }
}
