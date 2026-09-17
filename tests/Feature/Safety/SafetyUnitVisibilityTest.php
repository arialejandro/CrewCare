<?php

namespace Tests\Feature\Safety;

use App\Models\DailyReport;
use App\Models\Production;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\ReportVisibility;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * UNIDADES · 2b §3 — el eje de UNIDAD se SUMA (AND) al de autor en ReportVisibility, no lo sustituye.
 *
 * Hoy es REDUNDANTE: cada unidad tiene su propio safety, así que el eje de autor ya aísla la unidad. El
 * cambio importa el día que UN MISMO safety cubra dos unidades. `safety.consolidate` sigue viendo todo.
 * Con $unitScope por defecto (UNIT_UNSCOPED) el listado es idéntico a hoy.
 */
class SafetyUnitVisibilityTest extends QaTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        $prod->forceFill(['active' => 1])->save();
        CurrentProduction::forget();

        return $prod;
    }

    private function makeDsr(User $author, ?int $unitId): DailyReport
    {
        return DailyReport::create([
            'report_date'       => '2026-10-05',
            'location_name'     => 'Set ' . Str::random(6),
            'slug_setting'      => 'INT.',
            'slug_time'         => 'DÍA',
            'weather_condition' => 'sunny',
            'nearest_hospital'  => 'Hospital QA',
            'crew_count'        => 20,
            'shoot_day'         => 1,
            'author_name'       => $author->name,
            'created_by_id'     => $author->id,
            'unit_id'           => $unitId,
            'production_id'     => CurrentProduction::id(),
        ]);
    }

    public function test_el_eje_de_unidad_se_suma_al_de_autor(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        // UN MISMO safety con documentos en las dos unidades (el caso que hace no-redundante el eje).
        $safety = $this->makeUser('safety-officer');
        $this->makeDsr($safety, null);       // principal
        $this->makeDsr($safety, $u2->id);    // 2ª unidad

        // Sin eje de unidad (default) → ve LOS DOS suyos: idéntico a hoy.
        $this->assertSame(2, ReportVisibility::apply(DailyReport::query(), $safety)->count());

        // Eje de unidad = principal (null) → sólo el principal (AND autor∧unidad).
        $this->assertSame(1, ReportVisibility::apply(DailyReport::query(), $safety, 'created_by_id', null)->count());

        // Eje de unidad = 2ª unidad → sólo el de la 2ª unidad.
        $this->assertSame(1, ReportVisibility::apply(DailyReport::query(), $safety, 'created_by_id', $u2->id)->count());
    }

    public function test_consolidate_ve_todo_con_o_sin_eje_de_unidad(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        $safety = $this->makeUser('safety-officer');
        $this->makeDsr($safety, null);
        $this->makeDsr($safety, $u2->id);

        // line-producer tiene safety.consolidate → ve TODO, aunque se le pase un eje de unidad.
        $consol = $this->makeUser('line-producer');
        $this->assertTrue($consol->can('safety.consolidate'), 'Precondición: la consolidación existe.');
        $this->assertSame(2, ReportVisibility::apply(DailyReport::query(), $consol, 'created_by_id', $u2->id)->count(),
            'La consolidación bypassa autor Y unidad: ve todo.');
    }
}
