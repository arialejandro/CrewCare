<?php

namespace Tests\Feature\Contract;

use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use App\Support\CurrentProduction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\QaTestCase;

/**
 * PDF FILLABLE · segundo modo de autoría — subir el PDF ya redactado y colocar etiquetas.
 *
 * Cubre F0/F1: subida (source_kind=pdf + archivo en disco privado), que el editor bifurque por
 * source_kind, que el PDF se sirva SOLO autenticado, y — lo crítico — que el field_map se SANEE en el
 * servidor (jamás se confía en el cliente: solo claves del catálogo, coordenadas acotadas 0–100%).
 */
class ContractPdfTemplateTest extends QaTestCase
{
    /** PDF REAL (no bytes basura): storePdf ahora exige que FPDI pueda leerlo. */
    private function samplePdf(): UploadedFile
    {
        $pdf = new Fpdi('P', 'pt');
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 20, 'Contrato de prueba', 0, 1);

        return UploadedFile::fake()->createWithContent('contrato-legal.pdf', (string) $pdf->Output('S'));
    }

    /** Genera un PDF real en el disco (fake) y devuelve su ruta relativa. */
    private function seedSourcePdf(string $path): void
    {
        $pdf = new Fpdi('P', 'pt');
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 20, 'CONTRATO ORIGINAL', 0, 1);
        Storage::disk('local')->put($path, (string) $pdf->Output('S'));
    }

    public function test_store_pdf_creates_pdf_template_and_stores_file(): void
    {
        Storage::fake('local');
        $this->actingAsRole('super-admin');

        $res = $this->post(route('contracts.templates.store_pdf'), [
            'name'       => 'Contrato de servicios',
            'applies_to' => ['crew_work'],
            'pdf'        => $this->samplePdf(),
        ]);

        $tpl = ContractTemplate::latest('id')->first();
        $this->assertNotNull($tpl);
        $res->assertRedirect(route('contracts.templates.edit', $tpl));

        $this->assertSame(ContractTemplate::SOURCE_PDF, $tpl->source_kind);
        $this->assertTrue($tpl->isPdfSource());
        $this->assertSame('contrato-legal.pdf', $tpl->pdf_original_name);
        $this->assertSame([], $tpl->field_map);
        $this->assertFalse((bool) $tpl->is_active);
        Storage::disk('local')->assertExists($tpl->pdf_path);
    }

    public function test_edit_renders_the_pdf_editor_for_pdf_templates(): void
    {
        Storage::fake('local');
        $this->actingAsRole('super-admin');

        $tpl = ContractTemplate::create([
            'production_id' => CurrentProduction::id(), 'name' => 'PDF X', 'applies_to' => ['crew_work'],
            'source_kind' => ContractTemplate::SOURCE_PDF, 'pdf_path' => 'contract-templates/x.pdf',
            'pdf_original_name' => 'legal.pdf', 'field_map' => [], 'is_active' => false,
        ]);

        $this->get(route('contracts.templates.edit', $tpl))
            ->assertOk()
            ->assertSee('Colocar etiquetas')
            ->assertSee('legal.pdf')
            ->assertSee('pdf.worker.min.js', false);   // el editor pdf.js se montó
    }

    public function test_update_sanitizes_field_map_dropping_unknown_keys_and_clamping(): void
    {
        Storage::fake('local');
        $this->actingAsRole('super-admin');

        $tpl = ContractTemplate::create([
            'production_id' => CurrentProduction::id(), 'name' => 'PDF Y', 'applies_to' => ['crew_work'],
            'source_kind' => ContractTemplate::SOURCE_PDF, 'pdf_path' => 'contract-templates/y.pdf',
            'field_map' => [], 'is_active' => false,
        ]);

        $map = [
            ['page' => 1, 'x_pct' => 10, 'y_pct' => 20, 'w_pct' => 24, 'type' => 'sign', 'key' => 'contratado'],
            ['page' => 2, 'x_pct' => 250, 'y_pct' => -5, 'w_pct' => 30, 'type' => 'data', 'key' => 'payee_nombre'],
            ['page' => 1, 'x_pct' => 5, 'y_pct' => 5, 'w_pct' => 20, 'type' => 'data', 'key' => 'no_existe'],
            ['page' => 1, 'x_pct' => 5, 'y_pct' => 5, 'w_pct' => 20, 'type' => 'sign', 'key' => 'puesto:99999'],
        ];

        $this->put(route('contracts.templates.update', $tpl), [
            'name'       => 'PDF Y v2',
            'applies_to' => ['crew_work', 'service'],
            'is_active'  => 1,
            'field_map'  => json_encode($map),
        ])->assertRedirect();

        $tpl->refresh();
        $fm = $tpl->field_map;

        $this->assertCount(2, $fm);                       // las 2 claves desconocidas se descartan
        $this->assertSame('PDF Y v2', $tpl->name);
        $this->assertEqualsCanonicalizing(['crew_work', 'service'], $tpl->applies_to);
        $this->assertTrue((bool) $tpl->is_active);

        $keys = array_column($fm, 'key');
        $this->assertContains('contratado', $keys);
        $this->assertContains('payee_nombre', $keys);
        $this->assertNotContains('no_existe', $keys);
        $this->assertNotContains('puesto:99999', $keys);

        $data = collect($fm)->firstWhere('key', 'payee_nombre');
        $this->assertSame(100.0, (float) $data['x_pct']);   // 250 → acotado a 100
        $this->assertSame(0.0, (float) $data['y_pct']);      // -5  → acotado a 0
    }

    public function test_pdf_file_streams_only_for_pdf_templates(): void
    {
        Storage::fake('local');
        $this->actingAsRole('super-admin');

        $path = 'contract-templates/served.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 fake');
        $tpl = ContractTemplate::create([
            'production_id' => CurrentProduction::id(), 'name' => 'PDF Z', 'applies_to' => ['crew_work'],
            'source_kind' => ContractTemplate::SOURCE_PDF, 'pdf_path' => $path,
            'pdf_original_name' => 'z.pdf', 'field_map' => [], 'is_active' => false,
        ]);

        $this->get(route('contracts.templates.pdf_file', $tpl))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Una plantilla HTML (sin PDF) no expone archivo.
        $html = ContractTemplate::create([
            'production_id' => CurrentProduction::id(), 'name' => 'HTML', 'applies_to' => ['crew_work'],
            'body' => '<p>x</p>', 'is_active' => false,
        ]);
        $this->get(route('contracts.templates.pdf_file', $html))->assertNotFound();
    }

    public function test_pdf_preview_stamps_sample_data_from_posted_field_map(): void
    {
        Storage::fake('local');
        $this->actingAsRole('super-admin');
        $this->seedSourcePdf('contract-templates/prev.pdf');

        $tpl = ContractTemplate::create([
            'production_id' => CurrentProduction::id(), 'name' => 'PDF prev', 'applies_to' => ['crew_work'],
            'source_kind' => ContractTemplate::SOURCE_PDF, 'pdf_path' => 'contract-templates/prev.pdf',
            'field_map' => [], 'is_active' => false,
        ]);

        // El editor manda las etiquetas ACTUALES (sin guardar) → sale un PDF estampado.
        $res = $this->post(route('contracts.templates.pdf_preview', $tpl), [
            'field_map' => json_encode([
                ['page' => 1, 'x_pct' => 15, 'y_pct' => 50, 'w_pct' => 40, 'type' => 'data', 'key' => 'payee_nombre'],
                ['page' => 1, 'x_pct' => 15, 'y_pct' => 70, 'w_pct' => 24, 'type' => 'sign', 'key' => 'contratado'],
            ]),
        ]);

        $res->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());

        // HTML → sin preview de PDF.
        $htmlTpl = ContractTemplate::create([
            'production_id' => CurrentProduction::id(), 'name' => 'HTML', 'applies_to' => ['crew_work'],
            'body' => '<p>x</p>', 'is_active' => false,
        ]);
        $this->post(route('contracts.templates.pdf_preview', $htmlTpl), ['field_map' => '[]'])->assertNotFound();
    }
}
