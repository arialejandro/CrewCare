<?php

namespace Tests\Feature\Calendario;

use App\Models\DailyReport;
use App\Models\Production;
use App\Models\ShootDay;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;
use Tests\QaTestCase;

/**
 * ProductionCalendar UNIT-AWARE: cada unidad su propio contador; una 2ª unidad SIEMPRE arranca en día 1;
 * su M es la suya; y documentDayLabel compara contra el calendario DE LA UNIDAD DEL DOCUMENTO (no inventa
 * divergencias falsas en la 2ª unidad). NULL = principal, se comporta como hoy.
 */
class UnitCalendarTest extends QaTestCase
{
    private function producciónVigente(string $start): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1, 'start_date' => $start, 'end_date' => null,
            'shoot_weeks' => 1, 'shoot_days_per_week' => 6])->save();   // M principal = 6
        CurrentProduction::forget();
        ProductionCalendar::forget();

        return $prod;
    }

    private function marcar(int $pid, ?int $unit, string $fecha): void
    {
        ShootDay::create(['production_id' => $pid, 'unit_id' => $unit, 'shoot_date' => $fecha,
            'is_shoot_day' => true, 'week_no' => 1]);
    }

    private function escenario(): array
    {
        $prod = $this->producciónVigente('2026-10-05');
        $u2 = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        // PRINCIPAL (unit_id NULL): 3 días desde el inicio.
        $this->marcar($prod->id, null, '2026-10-05');
        $this->marcar($prod->id, null, '2026-10-06');
        $this->marcar($prod->id, null, '2026-10-07');
        // 2ª UNIDAD: nace mucho después (semana 6), 2 días.
        $this->marcar($prod->id, $u2->id, '2026-11-16');
        $this->marcar($prod->id, $u2->id, '2026-11-17');
        ProductionCalendar::forget();

        return [$prod, $u2];
    }

    public function test_cada_unidad_su_contador_y_la_segunda_arranca_en_dia_1(): void
    {
        [$prod, $u2] = $this->escenario();

        $this->assertSame(3, ProductionCalendar::shootDaysCount(null), 'La principal cuenta sus 3 días.');
        $this->assertSame(2, ProductionCalendar::shootDaysCount($u2->id), 'La 2ª unidad cuenta SOLO los suyos.');

        // La 2ª unidad, aunque nace en noviembre, arranca en DÍA 1.
        $this->assertSame(1, ProductionCalendar::dayNumber('2026-11-16', $u2->id));
        $this->assertSame(2, ProductionCalendar::dayNumber('2026-11-17', $u2->id));
        $this->assertSame('2026-11-16', ProductionCalendar::anchorDate($u2->id)->toDateString(), 'Su día 1 es su primer día marcado, no el start de producción.');

        // La principal, intacta.
        $this->assertSame(3, ProductionCalendar::dayNumber('2026-10-07', null));
        $this->assertSame('2026-10-05', ProductionCalendar::anchorDate(null)->toDateString());
    }

    public function test_la_M_es_por_unidad(): void
    {
        [$prod, $u2] = $this->escenario();

        $this->assertSame(6, ProductionCalendar::plannedShootDays(null), 'La M principal = semanas×días de producción (1×6).');
        $this->assertSame(2, ProductionCalendar::plannedShootDays($u2->id), 'La M de la 2ª unidad = SUS días marcados, no la de producción.');
    }

    public function test_documentDayLabel_compara_contra_la_unidad_del_documento(): void
    {
        [$prod, $u2] = $this->escenario();

        // Documento de la 2ª unidad, día sellado 99 ≠ lo que dice SU calendario (día 2).
        $doc = new DailyReport(['report_date' => '2026-11-17', 'production_id' => $prod->id, 'shoot_day' => 99]);
        $doc->unit_id = $u2->id;
        $label = ProductionCalendar::documentDayLabel($doc);
        $this->assertStringContainsString('Día 99', $label);
        $this->assertStringContainsString('el calendario dice 2', $label, 'Compara contra la 2ª unidad (día 2), no contra la principal.');

        // Documento de la 2ª unidad cuyo sello COINCIDE con su calendario → sin divergencia falsa.
        $ok = new DailyReport(['report_date' => '2026-11-17', 'production_id' => $prod->id, 'shoot_day' => 2]);
        $ok->unit_id = $u2->id;
        $this->assertSame('Día 2', ProductionCalendar::documentDayLabel($ok), 'Un documento de la 2ª unidad no debe inventar divergencia.');

        // Documento de la principal, coincide → "Día 3".
        $prin = new DailyReport(['report_date' => '2026-10-07', 'production_id' => $prod->id, 'shoot_day' => 3]);
        $this->assertSame('Día 3', ProductionCalendar::documentDayLabel($prin));
    }
}
