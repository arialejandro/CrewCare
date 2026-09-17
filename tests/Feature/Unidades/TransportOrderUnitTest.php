<?php

namespace Tests\Feature\Unidades;

use App\Models\Production;
use App\Models\TransportOrder;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use Tests\QaTestCase;

/**
 * UNIDADES · 2b · dominio TRANSPORTACIÓN — la orden nace en la unidad VIGENTE y el listado la respeta.
 * La orden lleva unit_id; sus hijos (corridas/ocupantes/direcciones) heredan de la orden. El versionado y
 * el borrador del día son POR UNIDAD: la 2ª unidad lleva su propia serie, independiente de la principal.
 */
class TransportOrderUnitTest extends QaTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1])->save();
        CurrentProduction::forget();

        return $prod;
    }

    /** Lo que el índice listaría para la unidad vigente (misma consulta que TransportOrderController::index). */
    private function indexOrderIds(int $pid): \Illuminate\Support\Collection
    {
        return CurrentUnit::applyTo(
            TransportOrder::query()->where('production_id', $pid)->where('is_active', 1)
        )->pluck('id');
    }

    public function test_la_orden_nace_en_la_unidad_vigente_y_el_listado_la_respeta(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAsRole('safety-officer');   // canFull en transportación

        // Orden con la 2ª unidad vigente.
        $this->withSession([CurrentUnit::SESSION_KEY => $u2->id]);
        CurrentUnit::forget();
        $this->post(route('transport.order.create'), ['order_date' => '2026-10-10'])->assertStatus(302);
        $o2 = TransportOrder::where('unit_id', $u2->id)->latest('id')->first();
        $this->assertNotNull($o2, 'La orden nació en la 2ª unidad.');

        // Orden en la principal el MISMO día → borrador INDEPENDIENTE (no retoma el de la 2ª unidad).
        $this->withSession([CurrentUnit::SESSION_KEY => null]);
        CurrentUnit::forget();
        $this->post(route('transport.order.create'), ['order_date' => '2026-10-10'])->assertStatus(302);
        $o1 = TransportOrder::whereNull('unit_id')->latest('id')->first();
        $this->assertNotNull($o1);
        $this->assertNotSame((int) $o1->id, (int) $o2->id, 'Dos borradores del mismo día, uno por unidad.');

        // Listado (misma consulta que el índice) con la PRINCIPAL vigente → ve la principal, NO la 2ª.
        $this->withSession([CurrentUnit::SESSION_KEY => null]);
        CurrentUnit::forget();
        $ids = $this->indexOrderIds($prod->id);
        $this->assertTrue($ids->contains($o1->id));
        $this->assertFalse($ids->contains($o2->id), 'La orden de la 2ª unidad no aparece en el listado de la principal.');

        // Con la 2ª unidad vigente → ve la 2ª, NO la principal.
        $this->withSession([CurrentUnit::SESSION_KEY => $u2->id]);
        CurrentUnit::forget();
        $ids2 = $this->indexOrderIds($prod->id);
        $this->assertTrue($ids2->contains($o2->id));
        $this->assertFalse($ids2->contains($o1->id));
    }
}
