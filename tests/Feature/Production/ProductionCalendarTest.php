<?php

namespace Tests\Feature\Production;

use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * PARTE A · CALENDARIO DE RODAJE (2026-08-23). Deriva el TOTAL (la M de "Día N de M") y el wrap
 * estimado de start_date + shoot_weeks × shoot_days_per_week. NO escribe un calendario paralelo:
 * extiende ProductionCalendar. Verifica la M, el wrap para semana de 6 y de 5 días, la etiqueta
 * "Día N de M", y que la pantalla de configuración (settings.manage) responde y persiste.
 */
class ProductionCalendarTest extends QaTestCase
{
    private int $prodId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = (int) DB::table('productions')->min('id');
        // Lunes 5-ene-2026 como ancla → wrap predecible.
        DB::table('productions')->where('id', $this->prodId)->update([
            'active' => 1, 'start_date' => '2026-01-05', 'end_date' => null,
            'shoot_weeks' => null, 'shoot_days_per_week' => null,
        ]);
        // Sin DSRs propios en el test → el contador cae al ancla = start_date.
        DB::table('daily_reports')->delete();
        ProductionCalendar::forget();
        CurrentProduction::forget();
    }

    private function setSchedule(?int $weeks, ?int $dpw): void
    {
        DB::table('productions')->where('id', $this->prodId)
            ->update(['shoot_weeks' => $weeks, 'shoot_days_per_week' => $dpw]);
        ProductionCalendar::forget();
        CurrentProduction::forget();
    }

    public function test_total_planeado_es_semanas_por_dias(): void
    {
        $this->setSchedule(12, 6);
        $this->assertSame(72, ProductionCalendar::plannedShootDays());

        $this->setSchedule(10, 5);
        $this->assertSame(50, ProductionCalendar::plannedShootDays());
    }

    public function test_sin_configurar_no_hay_total(): void
    {
        $this->setSchedule(null, null);
        $this->assertNull(ProductionCalendar::plannedShootDays());

        // Semana inválida (7) tampoco cuenta.
        $this->setSchedule(4, 7);
        $this->assertNull(ProductionCalendar::plannedShootDays());
    }

    public function test_wrap_estimado_semana_de_6_salta_domingo(): void
    {
        // 2 sem × 6 = 12 días desde lun 5-ene. 12º laborable (salta domingos) = sáb 17-ene.
        $this->setSchedule(2, 6);
        $this->assertSame('2026-01-17', ProductionCalendar::plannedWrapDate()->toDateString());
    }

    public function test_wrap_estimado_semana_de_5_salta_sabado_y_domingo(): void
    {
        // 2 sem × 5 = 10 días desde lun 5-ene. 10º laborable (salta sáb+dom) = vie 16-ene.
        $this->setSchedule(2, 5);
        $this->assertSame('2026-01-16', ProductionCalendar::plannedWrapDate()->toDateString());
    }

    public function test_etiqueta_dia_n_de_m(): void
    {
        $this->setSchedule(12, 6);
        // El ancla (start_date) es el día 1 aunque no haya DSR.
        $this->assertSame('Día 1 de 72', ProductionCalendar::dayLabelWithTotal('2026-01-05'));

        // Sin total configurado → sólo "Día N".
        $this->setSchedule(null, null);
        $this->assertSame('Día 1', ProductionCalendar::dayLabelWithTotal('2026-01-05'));
    }

    public function test_pantalla_config_responde_y_persiste(): void
    {
        $this->actingAsRole('super-admin');
        $this->get(route('production.calendar.edit'))->assertOk();

        $this->post(route('production.calendar.update'), [
            'start_date' => '2026-01-05',
            'shoot_weeks' => 2,
            'shoot_days_per_week' => 6,
            'end_date' => '',   // vacío → toma el derivado
        ])->assertRedirect(route('production.calendar.edit'));

        $this->assertDatabaseHas('productions', [
            'id' => $this->prodId, 'shoot_weeks' => 2, 'shoot_days_per_week' => 6,
            'end_date' => '2026-01-17',   // wrap derivado guardado en end_date
        ]);
    }

    public function test_config_es_solo_super_admin_o_settings_manage(): void
    {
        $this->actingAsRole('crew');
        $this->get(route('production.calendar.edit'))->assertForbidden();
    }
}
