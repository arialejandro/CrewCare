<?php

namespace Tests\Feature\Contract;

use App\Models\ContractEnvelope;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Support\CurrentProduction;
use App\Support\SealVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO C · FASE 3 — SOBRE VERIFICABLE. Un sobre sellado se puede comprobar en la
 * superficie pública (/verificar/cenv/{uuid}, la misma que los docs de seguridad): íntegro / alterado
 * / retirado (anulado, rechazado, vencido). El uuid nace con el sobre y NO entra al hash del sello.
 */
class ContractVerifierTest extends QaTestCase
{
    private function envelope(array $overrides = []): ContractEnvelope
    {
        $payee    = Payee::create(['legal_nature' => 'fisica', 'name' => 'Demo ' . Str::random(5)]);
        $contract = $payee->contracts()->create([
            'concept' => PayeeContract::CONCEPT_CREW, 'is_active' => 1, 'production_id' => CurrentProduction::id(),
        ]);

        return ContractEnvelope::create(array_merge([
            'payee_contract_id' => $contract->id,
            'production_id'      => $contract->production_id,
            'status'            => ContractEnvelope::STATUS_SENT,
            'documents'         => [['name' => 'Carátula', 'kind' => 'caratula', 'hash' => str_repeat('a', 64)]],
        ], $overrides));
    }

    public function test_new_envelope_gets_a_uuid(): void
    {
        $env = $this->envelope();
        $this->assertNotEmpty($env->uuid, 'el sobre nace con uuid');
        $this->assertSame(36, strlen($env->uuid));
    }

    public function test_sealed_envelope_verifies_as_ok(): void
    {
        $env = $this->envelope();
        $env = $env->fresh();   // sella sobre tipos de BD (store→refresh→sellar), como el builder
        $env->signDocumentAsSystem('test:fase3');

        $acuse = SealVerifier::resolve('cenv', $env->uuid);
        $this->assertNotNull($acuse, 'el verificador resuelve el sobre');
        $this->assertSame('ok', $acuse['verdict']);
        $this->assertSame('Sobre de contrato', $acuse['type_label']);
        $this->assertSame('CENV-' . str_pad((string) $env->id, 4, '0', STR_PAD_LEFT), $acuse['folio']);
        $this->assertFalse($acuse['retired']);
        $this->assertNotNull($acuse['sealed_at']);
    }

    public function test_tampering_the_package_marks_altered(): void
    {
        $env = $this->envelope();
        $env = $env->fresh();   // sella sobre tipos de BD (store→refresh→sellar), como el builder
        $env->signDocumentAsSystem('test:fase3');
        $this->assertSame('ok', SealVerifier::resolve('cenv', $env->uuid)['verdict']);

        // Cambiar el paquete sellado (documents SÍ entra al hash) → alterado.
        $env->update(['documents' => [['name' => 'Otro', 'kind' => 'caratula', 'hash' => str_repeat('b', 64)]]]);
        $this->assertSame('altered', SealVerifier::resolve('cenv', $env->uuid)['verdict']);
    }

    public function test_unsealed_envelope_reports_unsealed(): void
    {
        $env = $this->envelope();   // sin sellar
        $this->assertSame('unsealed', SealVerifier::resolve('cenv', $env->uuid)['verdict']);
    }

    public function test_escape_paths_retire_without_altering(): void
    {
        $env = $this->envelope();
        $env = $env->fresh();   // sella sobre tipos de BD (store→refresh→sellar), como el builder
        $env->signDocumentAsSystem('test:fase3');

        // Rechazar (columna excluida del hash) → sigue íntegro, pero RETIRADO.
        $env->update(['status' => ContractEnvelope::STATUS_DECLINED, 'declined_at' => now()]);
        $acuse = SealVerifier::resolve('cenv', $env->uuid);
        $this->assertSame('ok', $acuse['verdict'], 'un camino de escape no altera el sello');
        $this->assertTrue($acuse['retired']);
        $this->assertSame('Rechazado', $acuse['retired_label']);
    }

    public function test_seal_retirement_labels_each_escape_state(): void
    {
        $this->assertNull($this->envelope()->sealRetirement(), 'un sobre vivo no está retirado');

        $cancel = $this->envelope(['status' => ContractEnvelope::STATUS_CANCELLED, 'cancelled_at' => now()]);
        $this->assertSame('Anulado', $cancel->sealRetirement()['retired_label']);

        $expire = $this->envelope(['status' => ContractEnvelope::STATUS_EXPIRED, 'expired_at' => now()]);
        $this->assertSame('Vencido', $expire->sealRetirement()['retired_label']);
    }

    public function test_urlfor_builds_the_public_verify_url(): void
    {
        $env = $this->envelope();
        $url = SealVerifier::urlFor($env);
        $this->assertStringContainsString('/verificar/cenv/' . $env->uuid, (string) $url);
    }
}
