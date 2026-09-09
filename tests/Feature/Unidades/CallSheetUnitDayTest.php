<?php

namespace Tests\Feature\Unidades;

use App\Models\CallDay;
use App\Models\Production;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * UNIDADES · 2b · dominio LLAMADO — el call_day es POR UNIDAD: (producción, fecha) → (producción, fecha,
 * unidad). La hora general y las comidas (hijas del call_day) son de cada unidad. Con una sola unidad, la
 * llave del firstOrCreate es idéntica a la de hoy → mismo comportamiento.
 *
 * OJO (reportado, no cableado aquí): los offsets de departamento (CallDeptOffset) y los horarios por
 * persona (CallPersonSchedule) NO cuelgan del call_day —están llaveados por (producción, …)— y no tienen
 * unit_id: siguen siendo GLOBALES de la producción. Hacerlos por unidad es un cambio de esquema fuera de 2b.
 */
class CallSheetUnitDayTest extends QaTestCase
{
    private string $ds = '2026-08-22';

    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1])->save();
        CurrentProduction::forget();

        return $prod;
    }

    public function test_el_call_day_es_por_unidad(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAsRole('coordinator');   // callsheet.manage

        // General de la 2ª unidad = 18:00.
        $this->withSession([CurrentUnit::SESSION_KEY => $u2->id]);
        CurrentUnit::forget();
        $this->post(route('callsheet.config.save', ['date' => $this->ds]), ['general_call' => '18:00'])->assertRedirect();

        // General de la principal = 07:00 (mismo día, unidad distinta).
        $this->withSession([CurrentUnit::SESSION_KEY => null]);
        CurrentUnit::forget();
        $this->post(route('callsheet.config.save', ['date' => $this->ds]), ['general_call' => '07:00'])->assertRedirect();

        // Dos call_days INDEPENDIENTES ese día, uno por unidad.
        $cdU2  = CallDay::where('production_id', $prod->id)->whereDate('call_date', $this->ds)->where('unit_id', $u2->id)->first();
        $cdPri = CallDay::where('production_id', $prod->id)->whereDate('call_date', $this->ds)->whereNull('unit_id')->first();

        $this->assertNotNull($cdU2, 'Existe el call_day de la 2ª unidad.');
        $this->assertNotNull($cdPri, 'Existe el call_day de la principal.');
        $this->assertNotSame((int) $cdU2->id, (int) $cdPri->id, 'Son filas distintas.');
        $this->assertSame('18:00', $cdU2->generalHHMM(), 'La 2ª unidad guarda SU general.');
        $this->assertSame('07:00', $cdPri->generalHHMM(), 'La principal guarda el suyo.');
    }

    public function test_con_una_sola_unidad_el_call_day_es_como_hoy(): void
    {
        $prod = $this->prod();
        $this->actingAsRole('coordinator');

        // Sin unidades adicionales: la llave del call_day no incluye unit_id → un solo call_day, unit_id NULL.
        $this->post(route('callsheet.config.save', ['date' => $this->ds]), ['general_call' => '08:00'])->assertRedirect();

        $this->assertSame(1, CallDay::where('production_id', $prod->id)->whereDate('call_date', $this->ds)->count());
        $cd = CallDay::where('production_id', $prod->id)->whereDate('call_date', $this->ds)->first();
        $this->assertNull($cd->unit_id, 'Con una sola unidad, el call_day es de la principal (unit_id NULL).');
    }
}
