<?php

namespace Tests\Feature\Calendario;

use App\Models\DailyReport;
use App\Models\Production;
use App\Models\ShootDay;
use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;
use Tests\QaTestCase;

/**
 * El CALENDARIO manda: shootDates() sale de shoot_days. El contador avanza SIN DSR. Sin calendario
 * poblado se cae al DSR (comportamiento anterior). Y un documento sellado conserva su shoot_day: la
 * divergencia con el calendario se MUESTRA, no se resuelve.
 */
class ProductionCalendarShootDaysTest extends QaTestCase
{
    /** Deja UNA producción vigente con el start_date dado y limpia las cachés. */
    private function producciónVigente(string $start): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        $this->assertNotNull($prod, 'El seeder base debe dejar una producción.');
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill([
            'active' => 1, 'start_date' => $start, 'end_date' => null,
            'shoot_weeks' => null, 'shoot_days_per_week' => null,
        ])->save();

        CurrentProduction::forget();
        ProductionCalendar::forget();

        return $prod;
    }

    private function marcarDia(int $pid, string $fecha, bool $rodaje = true, ?string $slug = null): void
    {
        ShootDay::create([
            'production_id' => $pid, 'shoot_date' => $fecha,
            'is_shoot_day' => $rodaje, 'slug_time' => $slug, 'week_no' => 1,
        ]);
    }

    public function test_el_contador_avanza_sin_que_exista_un_dsr(): void
    {
        $prod = $this->producciónVigente('2026-10-05');
        $this->marcarDia($prod->id, '2026-10-05');
        $this->marcarDia($prod->id, '2026-10-06');
        $this->marcarDia($prod->id, '2026-10-07');
        ProductionCalendar::forget();

        // NO existe ningún DSR y aun así el calendario cuenta 3 días.
        $this->assertSame(0, DailyReport::count(), 'La prueba parte de CERO DSR.');
        $this->assertSame(3, ProductionCalendar::shootDaysCount(), 'El contador sale del calendario, no del DSR.');
        $this->assertSame(1, ProductionCalendar::dayNumber('2026-10-05'));
        $this->assertSame(3, ProductionCalendar::dayNumber('2026-10-07'));
    }

    public function test_un_dia_marcado_como_descanso_no_cuenta(): void
    {
        $prod = $this->producciónVigente('2026-10-05');
        $this->marcarDia($prod->id, '2026-10-05');
        $this->marcarDia($prod->id, '2026-10-06');
        $this->marcarDia($prod->id, '2026-10-07', false);  // descanso/festivo explícito
        ProductionCalendar::forget();

        $this->assertSame(2, ProductionCalendar::shootDaysCount(), 'Un día is_shoot_day=0 no suma.');
    }

    public function test_sin_calendario_cae_al_dsr(): void
    {
        $prod = $this->producciónVigente('2026-10-05');
        // Sin filas en shoot_days → fallback: el contador sale de daily_reports (comportamiento anterior).
        DailyReport::create(['report_date' => '2026-10-05', 'location_name' => 'Set', 'production_id' => $prod->id]);
        ProductionCalendar::forget();

        $this->assertSame(0, ShootDay::count());
        $this->assertSame(1, ProductionCalendar::shootDaysCount(), 'Sin calendario, el DSR sigue mandando.');
    }

    public function test_documento_sellado_conserva_su_dia_y_la_divergencia_se_muestra(): void
    {
        $prod = $this->producciónVigente('2026-10-05');
        $this->marcarDia($prod->id, '2026-10-05');
        $this->marcarDia($prod->id, '2026-10-06');
        $this->marcarDia($prod->id, '2026-10-07');   // el calendario asigna día 3 a esta fecha
        ProductionCalendar::forget();

        // Documento con shoot_day CONGELADO distinto (99) al que dice el calendario (3).
        $diverge = new DailyReport(['report_date' => '2026-10-07', 'production_id' => $prod->id, 'shoot_day' => 99]);
        $label = ProductionCalendar::documentDayLabel($diverge);
        $this->assertStringContainsString('Día 99', $label, 'Muestra LO SUYO (el sellado).');
        $this->assertStringContainsString('el calendario dice 3', $label, 'Y anexa lo que dice el calendario.');

        // Cuando coinciden, se ve UN solo número, sin decoración.
        $igual = new DailyReport(['report_date' => '2026-10-07', 'production_id' => $prod->id, 'shoot_day' => 3]);
        $this->assertSame('Día 3', ProductionCalendar::documentDayLabel($igual));
    }
}
