<?php

namespace Tests\Feature\Unidades;

use App\Models\Production;
use App\Models\ShootDay;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;
use Tests\QaTestCase;

/**
 * UNIDADES · §5 — CADA UNIDAD TIENE SU PROPIO RITMO. Una 2ª unidad puede filmar UN día por semana (ritmo
 * irregular, no secuencial). El contador (N) y la M salen de SUS días marcados, no de un ritmo semanal
 * supuesto: no depende del asistente ni de shoot_days_per_week. Confirma el requisito "una unidad que filma
 * un día a la semana cuenta bien sus días".
 */
class UnitIrregularRhythmTest extends QaTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1])->save();
        CurrentProduction::forget();

        return $prod;
    }

    public function test_unidad_de_un_dia_por_semana_cuenta_1_2_3(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        // Un lunes por semana: ritmo irregular, marcado a mano (sin asistente).
        $dates = ['2026-10-05', '2026-10-12', '2026-10-19'];
        foreach ($dates as $i => $d) {
            ShootDay::create([
                'production_id' => $prod->id,
                'unit_id'       => $u2->id,
                'shoot_date'    => $d,
                'is_shoot_day'  => true,
                'is_manual'     => true,
                'week_no'       => $i + 1,
            ]);
        }
        ProductionCalendar::forget();

        // El total y la M de la unidad = sus días marcados (no semanas × días/semana).
        $this->assertSame(3, ProductionCalendar::shootDaysCount($u2->id), 'Cuenta sus días marcados.');
        $this->assertSame(3, ProductionCalendar::plannedShootDays($u2->id), 'La M de una unidad adicional es su propio calendario.');

        // El contador es la POSICIÓN en sus días marcados: 1, 2, 3 — aunque haya una semana entre cada uno.
        $this->assertSame(1, ProductionCalendar::dayNumber('2026-10-05', $u2->id));
        $this->assertSame(2, ProductionCalendar::dayNumber('2026-10-12', $u2->id));
        $this->assertSame(3, ProductionCalendar::dayNumber('2026-10-19', $u2->id));

        // Y la unidad arranca SIEMPRE en su día 1 (su primer día marcado es el ancla).
        $this->assertSame('2026-10-05', ProductionCalendar::anchorDate($u2->id)->toDateString());
    }
}
