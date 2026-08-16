<?php

namespace Tests\Feature\Contract;

use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use App\Support\CurrentProduction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    private function samplePdf(): UploadedFile
    {
        return UploadedFile::fake()->create('contrato-legal.pdf', 120, 'application/pdf');
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
}
