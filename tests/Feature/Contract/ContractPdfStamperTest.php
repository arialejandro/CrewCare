<?php

namespace Tests\Feature\Contract;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\ContractTemplate;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Support\ContractPdfStamper;
use App\Support\ContractSignedRenderer;
use App\Support\CurrentProduction;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\QaTestCase;

/**
 * PDF FILLABLE · F2 — el MOTOR DE ESTAMPADO. Verifica que FPDI abre el PDF original y sobrepone datos
 * + firma PNG conservando la estructura (sale un PDF válido, más pesado que el fuente), que las firmas
 * pendientes no rompen, y que un archivo ilegible (no-PDF / cifrado) da un error claro y accionable.
 */
class ContractPdfStamperTest extends QaTestCase
{
    /** Autógrafa de prueba: PNG real generado con GD (data URI), como el canvas de firma. */
    private function pngDataUri(): string
    {
        $im = imagecreatetruecolor(120, 40);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imageline($im, 4, 30, 116, 12, imagecolorallocate($im, 15, 17, 21));
        ob_start();
        imagepng($im);
        $bin = ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,' . base64_encode($bin);
    }

    /** Genera un PDF real de $pages y lo deja en el disco local (fake) en $path. */
    private function makeSourcePdf(string $path, int $pages = 2): int
    {
        $pdf = new Fpdi('P', 'pt');
        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 16);
            $pdf->Cell(0, 20, 'Contrato de prueba - pagina ' . $i, 0, 1);
        }
        $bytes = (string) $pdf->Output('S');
        Storage::disk('local')->put($path, $bytes);

        return strlen($bytes);
    }

    private function pdfTemplate(string $path, array $fieldMap): ContractTemplate
    {
        return ContractTemplate::create([
            'production_id'     => CurrentProduction::id(),
            'name'              => 'PDF estampable',
            'applies_to'        => ['crew_work'],
            'source_kind'       => ContractTemplate::SOURCE_PDF,
            'pdf_path'          => $path,
            'pdf_original_name' => 'legal.pdf',
            'field_map'         => $fieldMap,
            'is_active'         => true,
        ]);
    }

    public function test_stamps_data_and_signature_over_the_pdf(): void
    {
        Storage::fake('local');
        $srcLen = $this->makeSourcePdf('contract-templates/src.pdf', 2);

        $tpl = $this->pdfTemplate('contract-templates/src.pdf', [
            ['page' => 1, 'x_pct' => 15, 'y_pct' => 40, 'w_pct' => 40, 'type' => 'data', 'key' => 'payee_nombre'],
            ['page' => 2, 'x_pct' => 15, 'y_pct' => 70, 'w_pct' => 24, 'type' => 'sign', 'key' => 'contratado'],
        ]);

        $out = ContractPdfStamper::stamp(
            $tpl,
            ['payee_nombre' => 'Juan Pérez López'],   // con acento: debe convertir a WinAnsi sin romper
            ['contratado' => ['image' => $this->pngDataUri(), 'signer' => 'Juan Pérez López']]
        );

        $this->assertStringStartsWith('%PDF', $out);
        $this->assertGreaterThan($srcLen, strlen($out));   // el overlay añade contenido
    }

    public function test_pending_signature_is_skipped_without_error(): void
    {
        Storage::fake('local');
        $this->makeSourcePdf('contract-templates/src.pdf', 1);

        $tpl = $this->pdfTemplate('contract-templates/src.pdf', [
            ['page' => 1, 'x_pct' => 10, 'y_pct' => 60, 'w_pct' => 24, 'type' => 'sign', 'key' => 'contratado'],
        ]);

        // Firma pendiente (null) → no se dibuja, pero sigue saliendo un PDF válido.
        $out = ContractPdfStamper::stamp($tpl, [], ['contratado' => null]);

        $this->assertStringStartsWith('%PDF', $out);
    }

    public function test_unreadable_source_throws_clear_error(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('contract-templates/bad.pdf', 'esto no es un pdf');
        $tpl = $this->pdfTemplate('contract-templates/bad.pdf', []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/protegido|no se pudo leer/i');

        ContractPdfStamper::stamp($tpl, [], []);
    }

    /** F3 — al congelar un sobre cuya plantilla es PDF, el documento firmado sale del estampador. */
    public function test_store_uses_the_stamper_for_pdf_templates(): void
    {
        Storage::fake('local');
        $this->makeSourcePdf('contract-templates/src.pdf', 1);
        $this->pdfTemplate('contract-templates/src.pdf', [
            ['page' => 1, 'x_pct' => 15, 'y_pct' => 50, 'w_pct' => 40, 'type' => 'data', 'key' => 'payee_nombre'],
            ['page' => 1, 'x_pct' => 15, 'y_pct' => 70, 'w_pct' => 24, 'type' => 'sign', 'key' => 'contratado'],
        ]);

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
            'signature_image' => $this->pngDataUri(),
        ]);

        $meta = ContractSignedRenderer::store($env->fresh());

        $this->assertNotNull($meta);
        $this->assertSame('fpdi-overlay', $meta['engine']);          // vino del estampador, no de Chrome
        Storage::disk('local')->assertExists($meta['path']);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($meta['path']));
        $this->assertDatabaseHas('contract_envelope_events', ['envelope_id' => $env->id, 'event' => 'sealed']);
    }
}
