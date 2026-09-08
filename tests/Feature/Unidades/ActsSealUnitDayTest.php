<?php

namespace Tests\Feature\Unidades;

use App\Models\AmbulanceInspection;
use App\Models\Production;
use App\Models\ShootDay;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use App\Support\ProductionCalendar;
use Carbon\Carbon;
use Tests\Feature\Ambulance\AmbulanceVerticalTestCase;

/**
 * UNIDADES · 2b §1 — las actas de recurso (ambulancia/vehículo/herramienta/permiso) cuentan en el
 * contador de SU unidad. Todas usan el mismo `currentShootDay()` → `shootDayFor(now(), CurrentUnit::id())`
 * + estampan `unit_id = CurrentUnit::id()`. Se ejercita el acta de AMBULANCIA como representante (las otras
 * tres son el mismo cambio); con una sola unidad, `CurrentUnit::id()` es null → principal → idéntico a hoy.
 *
 * Escenario: hoy (now congelado al 16-nov) es "día 4" en la principal y "día 1" en la 2ª unidad. Con la 2ª
 * unidad VIGENTE, el acta debe sellar día 1 (el suyo), no día 4.
 */
class ActsSealUnitDayTest extends AmbulanceVerticalTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CurrentUnit::forget();
        ProductionCalendar::forget();
        parent::tearDown();
    }

    public function test_el_acta_de_ambulancia_sella_el_dia_de_su_unidad(): void
    {
        Carbon::setTestNow('2026-11-16');

        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1, 'start_date' => '2026-10-05'])->save();
        CurrentProduction::forget();

        foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $d) {
            ShootDay::create(['production_id' => $prod->id, 'unit_id' => null, 'shoot_date' => $d, 'is_shoot_day' => true]);
        }
        $u2 = Unit::create(['production_id' => $prod->id, 'name' => 'Unidad marina', 'sort_order' => 1, 'is_active' => true]);
        foreach (['2026-11-16', '2026-11-17'] as $d) {
            ShootDay::create(['production_id' => $prod->id, 'unit_id' => $u2->id, 'shoot_date' => $d, 'is_shoot_day' => true]);
        }
        ProductionCalendar::forget();
        CurrentUnit::forget();

        $this->assertSame(4, ProductionCalendar::dayNumber('2026-11-16', null));
        $this->assertSame(1, ProductionCalendar::dayNumber('2026-11-16', $u2->id));

        $this->actingAs($this->makeUser('safety-officer'));   // tiene ambulance.manage

        // Fija la unidad vigente = 2ª unidad (por el selector del topbar → ruta unit.switch).
        $this->post(route('unit.switch'), ['unit_id' => $u2->id])->assertRedirect();
        CurrentUnit::forget();

        $type = $this->aTerrestrialType();
        ProductionCalendar::forget();
        $this->post(route('ambulance.inspect.store'), $this->inspectStorePayload($type))->assertSessionHasNoErrors();

        $insp = AmbulanceInspection::latest('id')->first();
        $this->assertNotNull($insp);
        $this->assertSame((int) $u2->id, (int) $insp->unit_id, 'El acta quedó atada a la 2ª unidad.');
        $this->assertSame(1, (int) $insp->shoot_day, '🔴 Sella el día 1 de SU unidad, no el 4 de la principal.');
        $this->assertTrue($insp->fresh()->verifyLatestSignature(), 'El acta de la 2ª unidad debe verificar íntegra.');
    }

    public function test_con_una_sola_unidad_el_acta_sella_la_principal(): void
    {
        Carbon::setTestNow('2026-11-16');

        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1, 'start_date' => '2026-10-05'])->save();
        CurrentProduction::forget();
        foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $d) {
            ShootDay::create(['production_id' => $prod->id, 'unit_id' => null, 'shoot_date' => $d, 'is_shoot_day' => true]);
        }
        ProductionCalendar::forget();
        CurrentUnit::forget();

        $this->actingAs($this->makeUser('safety-officer'));
        $type = $this->aTerrestrialType();
        ProductionCalendar::forget();
        $this->post(route('ambulance.inspect.store'), $this->inspectStorePayload($type))->assertSessionHasNoErrors();

        $insp = AmbulanceInspection::latest('id')->first();
        $this->assertNull($insp->unit_id, 'Sin unidades adicionales, el acta es de la principal (unit_id NULL).');
        $this->assertSame(4, (int) $insp->shoot_day, 'Con una sola unidad, el día es el de la principal (idéntico a hoy).');
    }
}
