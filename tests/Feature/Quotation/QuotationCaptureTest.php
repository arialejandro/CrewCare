<?php

namespace Tests\Feature\Quotation;

use App\Models\Quotation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\QaTestCase;

/** VERIFICACIÓN F1 — captura adentro de la cotización (partidas y PDF byte-intact). */
class QuotationCaptureTest extends QaTestCase
{
    /** Sin permiso quotations.manage → 403 en el listado. */
    public function test_gate_blocks_without_permission(): void
    {
        $this->actingAsRole('crew');
        $this->get(route('quotations.index'))->assertForbidden();
    }

    /** Capturar por PARTIDAS calcula subtotal/IVA/total (con y sin multiplicador de días). */
    public function test_capture_items_computes_totals(): void
    {
        $this->actingAsRole('line-producer');

        $this->post(route('quotations.store'), [
            'emitter_name' => 'Proveedor Demo', 'emitter_email' => 'Ventas@Proveedor.MX ',
            'source_kind'  => 'items', 'iva_rate' => 16, 'quotation_number' => 'DB-2002',
            'items' => [
                0 => ['description' => 'Renta cámara', 'quantity' => 2, 'unit_price' => 100],           // 200
                1 => ['description' => 'Operador x día', 'quantity' => 1, 'days' => 5, 'unit_price' => 50], // 250
            ],
        ])->assertRedirect();

        $q = Quotation::latest('id')->first();
        $this->assertSame('ventas@proveedor.mx', $q->emitter_email, 'correo normalizado');
        $v = $q->currentVersion;
        $this->assertSame('450.00', (string) $v->subtotal);
        $this->assertSame('72.00', (string) $v->iva_amount);
        $this->assertSame('522.00', (string) $v->total);
        $this->assertCount(2, $v->items);
    }

    /** IVA INCLUIDO: no se vuelve a sumar IVA (total = suma de líneas). */
    public function test_iva_included_does_not_add_iva(): void
    {
        $this->actingAsRole('line-producer');
        $this->post(route('quotations.store'), [
            'emitter_name' => 'X', 'source_kind' => 'items', 'iva_rate' => 16, 'iva_included' => 1,
            'items' => [0 => ['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 1160]],
        ])->assertRedirect();

        $v = Quotation::latest('id')->first()->currentVersion;
        $this->assertSame('1160.00', (string) $v->total, 'el total es la suma, sin re-sumar IVA');
        $this->assertSame('1000.00', (string) $v->subtotal, 'subtotal despejado');
        $this->assertSame('160.00', (string) $v->iva_amount);
    }

    /** PDF subido: se guarda byte-intact (sha256) y se descarga IDÉNTICO. */
    public function test_pdf_upload_is_byte_intact_and_downloads_identical(): void
    {
        Storage::fake('local');
        $this->actingAsRole('line-producer');

        $bytes = "%PDF-1.4\n1 0 obj<<>>endobj\ncotización de prueba áéí\n%%EOF";
        $this->post(route('quotations.store'), [
            'emitter_name' => 'Proveedor PDF', 'source_kind' => 'pdf', 'total' => 5000,
            'pdf' => UploadedFile::fake()->createWithContent('cotiza.pdf', $bytes),
        ])->assertRedirect();

        $q = Quotation::latest('id')->first();
        $v = $q->currentVersion;
        $this->assertSame('pdf', $v->source_kind);
        $this->assertSame(hash('sha256', $bytes), $v->pdf_sha256, 'el hash pina el contenido recibido');
        $this->assertSame('5000.00', (string) $v->total);

        $res = $this->get(route('quotations.version_pdf', [$q, $v]));
        $res->assertOk();
        $this->assertSame($bytes, $res->getContent(), 'descarga byte-idéntica');
    }
}
