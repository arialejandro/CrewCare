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
use App\Exceptions\ContractEnvelopeException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO C · FASE 2 — CAMINOS DE ESCAPE. Las salidas que "definen si el sistema se usa":
 * rechazar (con motivo), anular (con motivo), reenviar (recordatorio) y vencer (barrido). Cada una
 * detiene o mueve el sobre y deja su evento en la cadena inviolable de la Fase 1.
 */
class ContractEscapePathsTest extends QaTestCase
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

    public function test_send_sets_expiry_deadline(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();

        ContractSigning::send($env);
        $this->assertNotNull($env->fresh()->expires_at, 'el envío debe fijar la fecha límite');
        $this->assertTrue($env->fresh()->expires_at->isFuture());
    }

    public function test_decline_stops_the_envelope_and_logs_event(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();
        ContractSigning::send($env);

        $contratado = $env->fresh()->currentRecipient;
        ContractSigning::decline($contratado->fresh(), '187.190.0.1', 'El monto no coincide con lo acordado.');

        $env = $env->fresh();
        $this->assertTrue($env->isDeclined());
        $this->assertNotNull($env->declined_at);
        $this->assertNull($env->current_recipient_id, 'el turno se libera');
        $this->assertSame('El monto no coincide con lo acordado.', $env->resolution_reason);
        $this->assertTrue($contratado->fresh()->isDeclined());

        $this->assertDatabaseHas('contract_envelope_events', ['envelope_id' => $env->id, 'event' => 'declined']);
        $this->assertTrue(ContractEventLog::verifyChain($env)['ok']);
    }

    public function test_decline_requires_a_reason(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();
        ContractSigning::send($env);
        $contratado = $env->fresh()->currentRecipient;

        $this->expectException(ContractEnvelopeException::class);
        ContractSigning::decline($contratado->fresh(), '1.1.1.1', '   ');
    }

    public function test_decline_only_on_current_turn(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();
        ContractSigning::send($env);

        // El firmante interno (#1) NO está en turno todavía (va el contratado #0).
        $signer = $env->fresh()->orderedRecipients()->where('sort_order', 1)->first();

        $this->expectException(ContractEnvelopeException::class);
        ContractSigning::decline($signer->fresh(), '1.1.1.1', 'no debería poder');
    }

    public function test_signed_envelope_cannot_be_declined(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();
        ContractSigning::send($env);

        // Firma completa la ruta (2 firmantes) → completado.
        ContractSigning::sign($env->fresh()->currentRecipient->fresh(), 'authenticated', '1.1.1.1', 'data:image/png;base64,' . base64_encode('a'));
        ContractSigning::sign($env->fresh()->currentRecipient->fresh(), 'authenticated', '1.1.1.1', 'data:image/png;base64,' . base64_encode('b'));
        $this->assertTrue($env->fresh()->isCompleted());

        $this->expectException(ContractEnvelopeException::class);
        ContractSigning::decline($env->fresh()->orderedRecipients()->first()->fresh(), '1.1.1.1', 'ya no');
    }

    public function test_resend_bumps_resent_at_and_logs(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();
        ContractSigning::send($env);

        $this->assertNull($env->fresh()->currentRecipient->resent_at);
        ContractSigning::resend($env->fresh(), null);

        $this->assertNotNull($env->fresh()->currentRecipient->resent_at, 'reenviar sella resent_at');
        $this->assertDatabaseHas('contract_envelope_events', ['envelope_id' => $env->id, 'event' => 'resent']);
        $this->assertTrue(ContractEventLog::verifyChain($env->fresh())['ok']);
    }

    public function test_resend_rejects_a_draft(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();

        $this->expectException(ContractEnvelopeException::class);
        ContractSigning::resend($env, null);
    }

    public function test_cancel_route_requires_reason(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();
        ContractSigning::send($env);

        // Sin motivo → error de validación, el sobre NO se anula.
        $this->post(route('contracts.envelope.cancel', $env->id), [])->assertSessionHasErrors('reason');
        $this->assertFalse($env->fresh()->isCancelled());

        // Con motivo → anulado + motivo guardado + evento.
        $this->post(route('contracts.envelope.cancel', $env->id), ['reason' => 'Se reemitirá corregido.'])->assertRedirect();
        $env = $env->fresh();
        $this->assertTrue($env->isCancelled());
        $this->assertSame('Se reemitirá corregido.', $env->resolution_reason);
        $this->assertNull($env->current_recipient_id);
        $this->assertDatabaseHas('contract_envelope_events', ['envelope_id' => $env->id, 'event' => 'cancelled']);
    }

    public function test_expire_command_marks_stale_sent_expired(): void
    {
        $this->actingAsRole('super-admin');

        // Sobre vencido: fecha límite en el pasado.
        $stale = $this->draftEnvelope();
        ContractSigning::send($stale);
        $stale->update(['expires_at' => now()->subDay()]);

        // Sobre fresco: recién enviado (no debe vencerse).
        $fresh = $this->draftEnvelope();
        ContractSigning::send($fresh);

        Artisan::call('contracts:expire-stale');

        $stale = $stale->fresh();
        $this->assertTrue($stale->isExpired());
        $this->assertNotNull($stale->expired_at);
        $this->assertNull($stale->current_recipient_id);
        $this->assertDatabaseHas('contract_envelope_events', ['envelope_id' => $stale->id, 'event' => 'expired']);
        $this->assertTrue(ContractEventLog::verifyChain($stale)['ok']);

        $this->assertTrue($fresh->fresh()->isSent(), 'un sobre fresco no se vence');
    }

    public function test_expire_command_dry_run_changes_nothing(): void
    {
        $this->actingAsRole('super-admin');
        $stale = $this->draftEnvelope();
        ContractSigning::send($stale);
        $stale->update(['expires_at' => now()->subDay()]);

        Artisan::call('contracts:expire-stale', ['--dry' => true]);

        $this->assertTrue($stale->fresh()->isSent(), 'dry-run no vence nada');
        $this->assertDatabaseMissing('contract_envelope_events', ['envelope_id' => $stale->id, 'event' => 'expired']);
    }

    public function test_new_escape_columns_are_excluded_from_seal(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->draftEnvelope();
        $env->update(['documents' => [['name' => 'Carátula', 'kind' => 'cover']]]);

        // Sella el paquete como sistema (igual que el builder).
        $env->signDocumentAsSystem('test:fase2');
        $this->assertTrue($env->fresh()->verifyLatestSignature());

        // Tocar las columnas de Fase 2 NO debe romper el sello (están en $signatureExcludes).
        $env->update([
            'declined_at'       => now(),
            'resolution_reason' => 'motivo cualquiera',
            'expires_at'        => now()->addDays(10),
        ]);
        $this->assertTrue($env->fresh()->verifyLatestSignature(), 'las columnas de escape no entran al hash');
    }
}
