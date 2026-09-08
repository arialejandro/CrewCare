<?php

namespace Tests\Feature\Calendario;

use App\Models\DailyReport;
use App\Models\Production;
use App\Models\ShootDay;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * UNIDADES · 2b §1 — el DSR de la 2ª unidad sella SU número de día, NO el de la principal.
 *
 * 🔴 Es el único error IRREVERSIBLE del arco: `shoot_day` se sella y no se reescribe, así que sellar el
 * número de la principal en un documento de la 2ª unidad quedaría mal PARA SIEMPRE. resolveShootDay ahora
 * deriva contra la UNIDAD del documento. Con una sola unidad (unit_id NULL) todo se sella igual que hoy.
 *
 * Calendario del escenario:
 *   · Principal (unit_id NULL): días marcados 05, 06, 07-oct → el 16-nov (no marcado) sería su "día 4".
 *   · 2ª unidad: días marcados 16, 17-nov → su día 1 es el 16-nov (arranca en 1, no hereda a la principal).
 * Un DSR del 16-nov: principal ⇒ 4; 2ª unidad ⇒ 1. El sello debe decir 1 para el de la 2ª unidad.
 */
class DsrUnitDaySealTest extends QaTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1, 'start_date' => '2026-10-05'])->save();
        CurrentProduction::forget();

        return $prod;
    }

    /** Siembra el calendario de las dos unidades y devuelve la 2ª. */
    private function seedCalendars(Production $prod): Unit
    {
        foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $d) {
            ShootDay::create(['production_id' => $prod->id, 'unit_id' => null, 'shoot_date' => $d, 'is_shoot_day' => true]);
        }
        $u2 = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);
        foreach (['2026-11-16', '2026-11-17'] as $d) {
            ShootDay::create(['production_id' => $prod->id, 'unit_id' => $u2->id, 'shoot_date' => $d, 'is_shoot_day' => true]);
        }
        ProductionCalendar::forget();

        return $u2;
    }

    private function dsrPayload(array $overrides = []): array
    {
        return array_merge([
            'report_date'       => '2026-11-16',
            'location_name'     => 'Set QA ' . Str::random(6),
            'slug_setting'      => 'INT.',
            'slug_time'         => 'DÍA',
            'weather_condition' => 'sunny',
            'nearest_hospital'  => 'Hospital QA Central',
            'crew_count'        => 30,
        ], $overrides);
    }

    public function test_el_dsr_de_la_segunda_unidad_sella_su_dia_no_el_de_la_principal(): void
    {
        $prod = $this->prod();
        $u2   = $this->seedCalendars($prod);

        // Sanidad del escenario: el 16-nov es día 4 en la principal pero día 1 en la 2ª unidad.
        $this->assertSame(4, ProductionCalendar::dayNumber('2026-11-16', null), 'La principal ve el 16-nov como su día 4.');
        $this->assertSame(1, ProductionCalendar::dayNumber('2026-11-16', $u2->id), 'La 2ª unidad ve el 16-nov como su día 1.');

        $this->actingAsRole('safety-officer');
        $payload = $this->dsrPayload(['unit_id' => $u2->id]);

        ProductionCalendar::forget();
        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();

        $dsr = DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertNotNull($dsr);
        $this->assertSame((int) $u2->id, (int) $dsr->unit_id, 'El DSR quedó atado a la 2ª unidad.');
        $this->assertSame(1, (int) $dsr->shoot_day, '🔴 Debe sellar el día 1 de SU unidad, no el 4 de la principal.');

        // Con unit_id no nulo, la columna entra al hash: el DSR intacto debe verificar íntegro.
        $this->assertTrue($dsr->fresh()->verifyLatestSignature(), 'El DSR de la 2ª unidad debe nacer sellado e íntegro.');
    }

    public function test_el_dsr_de_la_principal_sigue_sellando_el_dia_de_la_principal(): void
    {
        $prod = $this->prod();
        $this->seedCalendars($prod);

        $this->actingAsRole('safety-officer');
        // Sin unit_id → principal → idéntico a hoy: el 16-nov es su día 4.
        $payload = $this->dsrPayload();

        ProductionCalendar::forget();
        $this->post(route('daily_reports.store'), $payload)->assertSessionHasNoErrors();

        $dsr = DailyReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertNotNull($dsr);
        $this->assertNull($dsr->unit_id, 'Sin contexto de unidad, el DSR es de la principal (unit_id NULL).');
        $this->assertSame(4, (int) $dsr->shoot_day, 'La principal sigue sellando su propio número (4).');
    }
}
