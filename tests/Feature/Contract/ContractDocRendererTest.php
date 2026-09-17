<?php

namespace Tests\Feature\Contract;

use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use App\Support\ContractDocRenderer;
use App\Support\CurrentProduction;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\QaTestCase;

/**
 * MOTOR UNIFICADO — el PDF final = hoja base (PDF subido / HTML por Chrome) + etiquetas por coordenadas.
 * El pipeline HTML→Chrome usa el doble de prueba de QaTestCase (sin Chrome); el estampado FPDI del PDF
 * subido corre real. (La rama HTML+field_map sí requiere Chrome real → se verifica en el laragon del owner.)
 */
class ContractDocRendererTest extends QaTestCase
{
    public function test_html_without_field_map_returns_chrome_base(): void
    {
        $tpl = ContractTemplate::create([
            'production_id' => CurrentProduction::id(), 'name' => 'HTML', 'applies_to' => [PayeeContract::CONCEPT_CREW],
            'source_kind' => ContractTemplate::SOURCE_HTML, 'body' => '<p>{{payee_nombre}}</p>',
            'is_active' => 1, 'page_size' => 'carta',
        ]);

        $bytes = ContractDocRenderer::renderPdf($tpl, ['payee_nombre' => 'Juan'], []);

        // Sin field_map → la hoja base (HTML por Chrome) ES el documento. En pruebas, el doble de Chrome.
        $this->assertStringStartsWith('TESTPDF:', $bytes);
    }

    public function test_pdf_with_field_map_stamps_data_and_signature(): void
    {
        Storage::fake('local');
        $pdf = new Fpdi('P', 'pt');
        $pdf->AddPage();
        Storage::disk('local')->put('ct/deal.pdf', (string) $pdf->Output('S'));

        $tpl = ContractTemplate::create([
            'production_id' => CurrentProduction::id(), 'name' => 'PDF', 'applies_to' => [PayeeContract::CONCEPT_CREW],
            'source_kind' => ContractTemplate::SOURCE_PDF, 'pdf_path' => 'ct/deal.pdf', 'pdf_original_name' => 'deal.pdf',
            'field_map' => [
                ['page' => 1, 'x_pct' => 20, 'y_pct' => 10, 'w_pct' => 28, 'type' => 'data', 'key' => 'payee_nombre'],
                ['page' => 1, 'x_pct' => 20, 'y_pct' => 60, 'w_pct' => 24, 'type' => 'sign', 'key' => 'contratado'],
            ],
            'is_active' => 1,
        ]);

        $sig = ['image' => 'data:image/png;base64,' . base64_encode('x'), 'signer' => 'Juan', 'hash' => str_repeat('a', 64), 'verified' => true];
        $bytes = ContractDocRenderer::renderPdf($tpl, ['payee_nombre' => 'Juan'], ['contratado' => $sig]);

        // El PDF subido se estampa por coordenadas (datos + firma+sello) → sigue siendo un PDF válido.
        $this->assertStringStartsWith('%PDF', $bytes);
    }
}
