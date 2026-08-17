<?php

namespace Tests\Feature\Quotation;

use App\Models\Quotation;
use Tests\QaTestCase;

/** VERIFICACIÓN F2 — negociar es versionar (append-only; la anterior no se altera). */
class QuotationVersionTest extends QaTestCase
{
    private function makeQuotation(): Quotation
    {
        $this->actingAsRole('line-producer');
        $this->post(route('quotations.store'), [
            'emitter_name' => 'Proveedor', 'source_kind' => 'items', 'iva_rate' => 16,
            'items' => [0 => ['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertRedirect();

        return Quotation::latest('id')->first();
    }

    /** Una versión nueva referencia a la anterior; la anterior queda intacta; pasa a negociación. */
    public function test_new_version_supersedes_and_preserves_previous(): void
    {
        $q = $this->makeQuotation();
        $v1 = $q->currentVersion;
        $this->assertSame('1160.00', (string) $v1->total);

        $this->post(route('quotations.store_version', $q), [
            'emitter_name' => 'Proveedor', 'source_kind' => 'items', 'iva_rate' => 16,
            'change_note'  => 'Se bajó el precio tras negociar',
            'items' => [0 => ['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 800]],
        ])->assertRedirect();

        $q->refresh();
        $this->assertSame(Quotation::STATUS_NEGOTIATING, $q->status);
        $this->assertSame(2, $q->versions()->count());

        $v2 = $q->currentVersion;
        $this->assertSame(2, $v2->version_no);
        $this->assertSame($v1->id, $v2->supersedes_id, 'la v2 referencia a la v1');
        $this->assertSame('928.00', (string) $v2->total, '800 + 16% IVA');
        $this->assertSame('Se bajó el precio tras negociar', $v2->change_note);

        // La versión anterior NO se alteró.
        $this->assertSame('1160.00', (string) $v1->fresh()->total, 'la v1 queda intacta');
    }
}
