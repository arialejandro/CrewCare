<?php

namespace Tests\Feature\Contract;

use App\Jobs\DeliverSignedContractEmail;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Support\ContractCompletionCertificate;
use App\Support\CurrentProduction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO C · FASE 3c/3d — CERTIFICADO DE CIERRE + ENTREGA. El certificado es una vista de
 * datos ya sellados (partes, tiempos, hashes, cadena, QR al verificador). El correo de cierre adjunta
 * el contrato firmado + el certificado. El motor PDF es la costura de QaTestCase (sin Chrome).
 */
class ContractCertificateTest extends QaTestCase
{
    private function completedEnvelope(array $overrides = []): ContractEnvelope
    {
        $payee    = Payee::create(['legal_nature' => 'fisica', 'name' => 'Juan Pérez López']);
        $contract = $payee->contracts()->create([
            'concept' => PayeeContract::CONCEPT_CREW, 'is_active' => 1, 'production_id' => CurrentProduction::id(),
        ]);
        $env = ContractEnvelope::create(array_merge([
            'payee_contract_id' => $contract->id, 'production_id' => $contract->production_id,
            'status' => ContractEnvelope::STATUS_COMPLETED, 'completed_at' => now(),
        ], $overrides));
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_CONTRACTED, 'sort_order' => 0, 'name' => 'Juan Pérez López',
            'email' => 'juan@demo.test', 'payee_id' => $payee->id,
            'status' => ContractEnvelopeRecipient::STATUS_SIGNED, 'signed_at' => now(), 'ip_address' => '187.190.0.1',
        ]);
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_SIGNER, 'sort_order' => 1, 'name' => 'Ana García',
            'cargo' => 'Gerente de Producción', 'status' => ContractEnvelopeRecipient::STATUS_SIGNED, 'signed_at' => now(),
        ]);

        return $env->fresh();
    }

    public function test_certificate_html_lists_parties_folio_and_verifier(): void
    {
        $env  = $this->completedEnvelope();
        $html = ContractCompletionCertificate::html($env);

        $this->assertStringContainsString('CENV-' . str_pad((string) $env->id, 4, '0', STR_PAD_LEFT), $html);
        $this->assertStringContainsString('Juan Pérez López', $html);
        $this->assertStringContainsString('Gerente de Producción', $html);
        $this->assertStringContainsString('/verificar/cenv/' . $env->uuid, $html);
    }

    public function test_certificate_route_renders_html(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->completedEnvelope();

        $this->get(route('contracts.envelope.certificate', $env))
            ->assertOk()
            ->assertSee('CENV-' . str_pad((string) $env->id, 4, '0', STR_PAD_LEFT))
            ->assertSee('Juan Pérez López');
    }

    public function test_certificate_pdf_branch_returns_pdf(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->completedEnvelope();

        $res = $this->get(route('contracts.envelope.certificate', ['envelope' => $env->id, 'pdf' => 1]));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('TESTPDF:', $res->getContent());
    }

    public function test_email_attaches_signed_contract_and_certificate(): void
    {
        Storage::fake('local');
        $env = $this->completedEnvelope(['signed_document' => ['path' => 'contracts/signed/env-x.pdf', 'hash' => str_repeat('a', 64)]]);
        Storage::disk('local')->put('contracts/signed/env-x.pdf', 'REALSIGNEDPDF');

        $names = collect(DeliverSignedContractEmail::buildAttachments($env))->pluck('name');

        $this->assertTrue($names->contains(fn ($n) => str_starts_with($n, 'Contrato-firmado-')), 'adjunta el contrato firmado');
        $this->assertTrue($names->contains(fn ($n) => str_starts_with($n, 'Certificado-')), 'adjunta el certificado de cierre');
    }

    public function test_email_falls_back_to_package_when_no_signed_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('contracts/pkg/caratula.pdf', 'PKG');
        $env = $this->completedEnvelope(['documents' => [['name' => 'Carátula', 'kind' => 'caratula', 'path' => 'contracts/pkg/caratula.pdf']]]);

        $names = collect(DeliverSignedContractEmail::buildAttachments($env))->pluck('name');

        // Sin signed_document → cae al paquete byte-intact + el certificado.
        $this->assertTrue($names->contains('Carátula.pdf'), 'respaldo: adjunta el paquete');
        $this->assertTrue($names->contains(fn ($n) => str_starts_with($n, 'Certificado-')));
        $this->assertFalse($names->contains(fn ($n) => str_starts_with($n, 'Contrato-firmado-')));
    }
}
