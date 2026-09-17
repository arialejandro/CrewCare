<?php

namespace Tests\Feature\Contract;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\ContractTemplate;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Support\ContractSignedRenderer;
use App\Support\ContractSigning;
use App\Support\CurrentProduction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO C · FASE 3 — DOCUMENTO FIRMADO REAL. Al completarse el sobre se congela el
 * contrato con las autógrafas estampadas (PDF), se guarda y su hash queda en la bitácora (evento
 * 'sealed'). El motor PDF está sustituido por la costura de QaTestCase (sin Chrome).
 */
class RenderSignedContractTest extends QaTestCase
{
    private function activeTemplate(): ContractTemplate
    {
        return ContractTemplate::create([
            'production_id' => CurrentProduction::id(),
            'name'          => 'Plantilla QA',
            'applies_to'    => [PayeeContract::CONCEPT_CREW],
            'body'          => '<h1>Contrato</h1><p>Firma del contratado: [[firma:contratado]]</p>',
            'language'      => 'es',
            'is_active'     => 1,
            'page_size'     => 'carta',
        ]);
    }

    private function completedEnvelope(): ContractEnvelope
    {
        $payee    = Payee::create(['legal_nature' => 'fisica', 'name' => 'Juan Pérez López']);
        $contract = $payee->contracts()->create([
            'concept' => PayeeContract::CONCEPT_CREW, 'is_active' => 1, 'production_id' => CurrentProduction::id(),
        ]);
        $env = ContractEnvelope::create([
            'payee_contract_id' => $contract->id, 'production_id' => $contract->production_id,
            'status' => ContractEnvelope::STATUS_COMPLETED, 'completed_at' => now(),
        ]);
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_CONTRACTED, 'sort_order' => 0, 'name' => 'Juan Pérez López',
            'anchor_key' => 'contratado', 'payee_id' => $payee->id,
            'status' => ContractEnvelopeRecipient::STATUS_SIGNED, 'signed_at' => now(),
            'signature_image' => 'data:image/png;base64,' . base64_encode('firma'),
        ]);

        return $env->fresh();
    }

    public function test_renderhtml_stamps_the_frozen_signature(): void
    {
        $this->activeTemplate();
        $html = ContractSignedRenderer::renderHtml($this->completedEnvelope());

        $this->assertNotNull($html);
        $this->assertStringContainsString('<!doctype html>', strtolower($html));
        $this->assertStringContainsString('data:image/png;base64,', $html, 'la autógrafa congelada se estampa');
    }

    public function test_renderhtml_is_null_without_active_template(): void
    {
        $this->assertNull(ContractSignedRenderer::renderHtml($this->completedEnvelope()));
    }

    public function test_store_freezes_pdf_writes_column_and_logs_sealed(): void
    {
        Storage::fake('local');
        $this->activeTemplate();
        $env  = $this->completedEnvelope();

        $meta = ContractSignedRenderer::store($env);

        $this->assertNotNull($meta);
        Storage::disk('local')->assertExists($meta['path']);
        $bytes = Storage::disk('local')->get($meta['path']);
        $this->assertStringStartsWith('TESTPDF:', $bytes);
        $this->assertSame(hash('sha256', $bytes), $meta['hash']);

        $env = $env->fresh();
        $this->assertTrue($env->hasSignedDocument());
        $this->assertSame($meta['path'], $env->signed_document['path']);
        $this->assertDatabaseHas('contract_envelope_events', ['envelope_id' => $env->id, 'event' => 'sealed']);
    }

    public function test_store_is_a_noop_without_template(): void
    {
        Storage::fake('local');
        $env = $this->completedEnvelope();

        $this->assertNull(ContractSignedRenderer::store($env));
        $this->assertFalse($env->fresh()->hasSignedDocument());
        $this->assertDatabaseMissing('contract_envelope_events', ['envelope_id' => $env->id, 'event' => 'sealed']);
    }

    public function test_signed_document_is_excluded_from_the_seal(): void
    {
        $env = $this->completedEnvelope();
        $env = $env->fresh();
        $env->signDocumentAsSystem('test:fase3');
        $this->assertTrue($env->fresh()->verifyLatestSignature());

        // Escribir el documento firmado NO debe romper el sello del paquete.
        $env->update(['signed_document' => ['path' => 'x.pdf', 'hash' => str_repeat('a', 64)]]);
        $this->assertTrue($env->fresh()->verifyLatestSignature(), 'signed_document no entra al hash');
    }

    public function test_completing_an_envelope_freezes_the_signed_document(): void
    {
        Storage::fake('local');
        $this->activeTemplate();
        $this->actingAsRole('super-admin');

        $signer   = $this->makeUser('crew');
        $payee    = Payee::create(['legal_nature' => 'fisica', 'name' => 'Demo ' . Str::random(4)]);
        $contract = $payee->contracts()->create([
            'concept' => PayeeContract::CONCEPT_CREW, 'is_active' => 1, 'production_id' => CurrentProduction::id(),
        ]);
        $env = ContractEnvelope::create([
            'payee_contract_id' => $contract->id, 'production_id' => $contract->production_id,
            'status' => ContractEnvelope::STATUS_DRAFT,
        ]);
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_CONTRACTED, 'sort_order' => 0, 'name' => 'Juan',
            'anchor_key' => 'contratado', 'payee_id' => $payee->id, 'status' => ContractEnvelopeRecipient::STATUS_PENDING,
        ]);
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_SIGNER, 'sort_order' => 1, 'name' => 'Ana',
            'user_id' => $signer->id, 'status' => ContractEnvelopeRecipient::STATUS_PENDING,
        ]);

        ContractSigning::send($env->fresh());
        $sig = 'data:image/png;base64,' . base64_encode('x');
        ContractSigning::sign($env->fresh()->currentRecipient->fresh(), 'authenticated', '1.1.1.1', $sig);
        ContractSigning::sign($env->fresh()->currentRecipient->fresh(), 'authenticated', '1.1.1.1', $sig);

        $env = $env->fresh();
        $this->assertTrue($env->isCompleted());
        $this->assertTrue($env->hasSignedDocument(), 'al completar se congela el contrato firmado');
        $this->assertDatabaseHas('contract_envelope_events', ['envelope_id' => $env->id, 'event' => 'sealed']);
    }
}
