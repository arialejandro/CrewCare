<?php

namespace Tests\Feature\Pae;

use App\Models\EmergencyActionPlan;
use App\Models\Production;
use App\Models\ShootDay;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;

/**
 * UNIDADES · 2b §1 — el PAE de la 2ª unidad sella SU día y ESTAMPA el nombre de la unidad elegida.
 *
 * El PAE es el ÚNICO documento que NOMBRA su unidad por documento. Antes era texto libre (`unit_name`) sin
 * vínculo, así que un PAE "de la 2ª unidad" sellaba igual el día de la PRINCIPAL — irreversible. Ahora elige
 * la unidad (unit_id): su nombre se congela en unit_name y el día se deriva contra SU calendario.
 *
 * Escenario idéntico al del DSR: 16-nov es "día 4" en la principal y "día 1" en la 2ª unidad.
 */
class PaeUnitDaySealTest extends PaeVerticalTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1, 'start_date' => '2026-10-05'])->save();
        CurrentProduction::forget();

        return $prod;
    }

    private function seedCalendars(Production $prod): Unit
    {
        foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $d) {
            ShootDay::create(['production_id' => $prod->id, 'unit_id' => null, 'shoot_date' => $d, 'is_shoot_day' => true]);
        }
        $u2 = Unit::create(['production_id' => $prod->id, 'name' => 'Unidad marina', 'sort_order' => 1, 'is_active' => true]);
        foreach (['2026-11-16', '2026-11-17'] as $d) {
            ShootDay::create(['production_id' => $prod->id, 'unit_id' => $u2->id, 'shoot_date' => $d, 'is_shoot_day' => true]);
        }
        ProductionCalendar::forget();

        return $u2;
    }

    public function test_el_pae_de_la_segunda_unidad_sella_su_dia_y_estampa_su_nombre(): void
    {
        $prod = $this->prod();
        $u2   = $this->seedCalendars($prod);
        $sc   = $this->makeScouting();

        $this->assertSame(4, ProductionCalendar::dayNumber('2026-11-16', null));
        $this->assertSame(1, ProductionCalendar::dayNumber('2026-11-16', $u2->id));

        $this->actingAsRole('safety-officer');
        // Sin shoot_day tecleado (null) → se deriva; unit_id = 2ª unidad; plan_date = su día 1.
        $payload = $this->storePayload([$sc->id], [
            'shoot_day' => null,
            'plan_date' => '2026-11-16',
            'unit_id'   => $u2->id,
            'unit_name' => 'lo que sea, lo pisa la unidad elegida',
        ]);

        ProductionCalendar::forget();
        $this->post(route('pae.store'), $payload)->assertSessionHasNoErrors();

        $plan = EmergencyActionPlan::latest('id')->first();
        $this->assertNotNull($plan);
        $this->assertSame((int) $u2->id, (int) $plan->unit_id, 'El PAE quedó atado a la 2ª unidad.');
        $this->assertSame(1, (int) $plan->shoot_day, '🔴 Debe sellar el día 1 de SU unidad, no el 4 de la principal.');
        $this->assertSame('Unidad marina', $plan->unit_name, 'El nombre de la unidad elegida se estampa (snapshot), pisando el texto libre.');

        $this->assertTrue($plan->verifyLatestSignature(), 'El PAE de la 2ª unidad debe nacer sellado e íntegro.');
    }

    public function test_el_formulario_pinta_el_selector_de_unidad_cuando_hay_adicionales(): void
    {
        $prod = $this->prod();
        $u2   = $this->seedCalendars($prod);

        $this->actingAsRole('safety-officer');
        $this->get(route('pae.create'))
            ->assertOk()
            ->assertSee(\App\Models\Unit::PRINCIPAL_LABEL)   // opción principal (unit_id vacío)
            ->assertSee('Unidad marina');                    // la unidad adicional como opción
    }

    public function test_el_pae_sin_unidad_sigue_siendo_de_la_principal(): void
    {
        $prod = $this->prod();
        $this->seedCalendars($prod);
        $sc = $this->makeScouting();

        $this->actingAsRole('safety-officer');
        // Sin unit_id → principal → el 16-nov es su día 4, como hoy.
        $payload = $this->storePayload([$sc->id], [
            'shoot_day' => null,
            'plan_date' => '2026-11-16',
            'unit_name' => 'Unidad principal',
        ]);
        unset($payload['unit_id']);

        ProductionCalendar::forget();
        $this->post(route('pae.store'), $payload)->assertSessionHasNoErrors();

        $plan = EmergencyActionPlan::latest('id')->first();
        $this->assertNull($plan->unit_id, 'Sin elección de unidad, el PAE es de la principal (unit_id NULL).');
        $this->assertSame(4, (int) $plan->shoot_day, 'La principal sigue sellando su propio número (4).');
    }
}
