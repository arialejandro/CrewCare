<?php

namespace Tests\Feature\Unidades;

use App\Models\DailyReport;
use App\Models\Production;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * UNIDADES · 2b — dominio SAFETY cableado: la creación estampa la unidad VIGENTE y el listado la respeta.
 * Un DSR de la 2ª unidad NO aparece en el listado de la principal (y viceversa). Con una sola unidad, todo
 * idéntico a hoy (probado en CurrentUnitContextTest).
 */
class SafetyUnitFilterTest extends QaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CurrentUnit::forget();
        CurrentProduction::forget();
    }

    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1, 'start_date' => '2026-10-05'])->save();
        CurrentProduction::forget();

        return $prod;
    }

    private function dsrPayload(string $loc): array
    {
        return [
            'report_date'       => '2026-10-05',
            'location_name'     => $loc,
            'slug_setting'      => 'INT.',
            'slug_time'         => 'DÍA',
            'weather_condition' => 'sunny',
            'nearest_hospital'  => 'Hospital QA',
            'crew_count'        => 12,
        ];
    }

    public function test_creacion_estampa_la_unidad_vigente_y_el_listado_la_respeta(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        $author = $this->makeUser('safety-officer');
        $this->actingAs($author);

        // DSR creado con la 2ª unidad vigente → se estampa unit_id de la 2ª unidad.
        $this->withSession([CurrentUnit::SESSION_KEY => $u2->id]);
        CurrentUnit::forget();
        $this->post(route('daily_reports.store'), $this->dsrPayload('LOCSEGUNDA'))->assertSessionHasNoErrors();

        // DSR creado en la principal → unit_id null.
        $this->withSession([CurrentUnit::SESSION_KEY => null]);
        CurrentUnit::forget();
        $this->post(route('daily_reports.store'), $this->dsrPayload('LOCPRINCIPAL'))->assertSessionHasNoErrors();

        $this->assertSame((int) $u2->id, (int) DailyReport::where('location_name', 'LOCSEGUNDA')->value('unit_id'));
        $this->assertNull(DailyReport::where('location_name', 'LOCPRINCIPAL')->value('unit_id'));

        // Listado con la PRINCIPAL vigente → ve la principal, NO la 2ª unidad.
        $this->withSession([CurrentUnit::SESSION_KEY => null]);
        CurrentUnit::forget();
        $this->actingAs($author)->get(route('daily_reports.index'))
            ->assertOk()
            ->assertSee('LOCPRINCIPAL')
            ->assertDontSee('LOCSEGUNDA');

        // Listado con la 2ª unidad vigente → ve la 2ª, NO la principal.
        $this->withSession([CurrentUnit::SESSION_KEY => $u2->id]);
        CurrentUnit::forget();
        $this->actingAs($author)->get(route('daily_reports.index'))
            ->assertOk()
            ->assertSee('LOCSEGUNDA')
            ->assertDontSee('LOCPRINCIPAL');
    }
}
