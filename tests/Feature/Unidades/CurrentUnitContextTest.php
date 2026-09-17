<?php

namespace Tests\Feature\Unidades;

use App\Models\DailyReport;
use App\Models\Production;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * UNIDADES · 2b — la UNIDAD VIGENTE (opción (a): selector en topbar, por sesión, default principal).
 *
 * Cubre CurrentUnit (fuente única) + la ruta de cambio + que el selector y la franja aparecen SÓLO cuando
 * hay más de una unidad / se trabaja fuera de la principal, y que con una sola unidad todo es idéntico.
 */
class CurrentUnitContextTest extends QaTestCase
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

    private function admin(): User
    {
        $u = $this->makeUser('super-admin');
        try {
            $u->givePermissionTo('settings.manage');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }

        return $u;
    }

    public function test_sin_unidades_no_hay_multiple_y_no_filtra(): void
    {
        $this->prod();
        CurrentUnit::forget();

        $this->assertFalse(CurrentUnit::hasMultiple(), 'Con sólo la principal no hay "más de una unidad".');
        $this->assertNull(CurrentUnit::id(), 'La vigente por default es la principal.');

        // applyTo NO toca la consulta: mismo count que sin filtro.
        $author = $this->makeUser('safety-officer');
        $this->makeDsr($author, null);
        $this->makeDsr($author, null);
        $this->assertSame(2, CurrentUnit::applyTo(DailyReport::query())->count());
    }

    public function test_applyTo_filtra_por_la_unidad_vigente(): void
    {
        $prod = $this->prod();
        $u    = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);
        CurrentUnit::forget();
        $this->assertTrue(CurrentUnit::hasMultiple());

        $author = $this->makeUser('safety-officer');
        $this->makeDsr($author, null);      // principal
        $this->makeDsr($author, $u->id);    // 2ª unidad

        // Vigente = principal (sin sesión) → sólo el principal.
        session()->forget(CurrentUnit::SESSION_KEY);
        CurrentUnit::forget();
        $this->assertSame(1, CurrentUnit::applyTo(DailyReport::query())->count(), 'Principal ve sólo lo suyo.');

        // Vigente = 2ª unidad → sólo el de la 2ª unidad.
        session()->put(CurrentUnit::SESSION_KEY, $u->id);
        CurrentUnit::forget();
        $this->assertSame(1, CurrentUnit::applyTo(DailyReport::query())->count(), 'La 2ª unidad ve sólo lo suyo.');
    }

    public function test_la_ruta_de_cambio_persiste_en_sesion_y_valida(): void
    {
        $prod = $this->prod();
        $u    = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);
        CurrentUnit::forget();

        $this->actingAs($this->makeUser('crew'));

        // Cambiar a la 2ª unidad → queda en sesión.
        $this->post(route('unit.switch'), ['unit_id' => $u->id])->assertRedirect();
        $this->assertSame($u->id, (int) session(CurrentUnit::SESSION_KEY));

        // Volver a la principal ("" ) → se limpia.
        $this->post(route('unit.switch'), ['unit_id' => ''])->assertRedirect();
        $this->assertNull(session(CurrentUnit::SESSION_KEY));

        // Un id inexistente NO se pega (falla seguro → principal).
        $this->post(route('unit.switch'), ['unit_id' => 999999])->assertRedirect();
        $this->assertNull(session(CurrentUnit::SESSION_KEY));
    }

    public function test_una_unidad_desactivada_cae_a_principal(): void
    {
        $prod = $this->prod();
        $u    = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);
        session()->put(CurrentUnit::SESSION_KEY, $u->id);
        CurrentUnit::forget();
        $this->assertSame($u->id, CurrentUnit::id());

        // Se desactiva → la vigente cae a principal (falla seguro).
        $u->update(['is_active' => false]);
        CurrentUnit::forget();
        $this->assertNull(CurrentUnit::id(), 'Una unidad desactivada no puede seguir siendo la vigente.');
    }

    public function test_el_selector_y_la_franja_solo_aparecen_con_mas_de_una_unidad(): void
    {
        $prod  = $this->prod();
        $admin = $this->admin();

        // Con una sola unidad: ni selector ni franja → idéntico a hoy.
        CurrentUnit::forget();
        $this->actingAs($admin)->get(route('production.units.index'))
            ->assertOk()
            ->assertDontSee('cc-unit-switch')
            ->assertDontSee('cc-unit-banner');

        // Con una 2ª unidad y estando en ella: selector + franja gritan.
        $u = Unit::create(['production_id' => $prod->id, 'name' => 'Unidad marina', 'sort_order' => 1, 'is_active' => true]);
        session()->put(CurrentUnit::SESSION_KEY, $u->id);
        CurrentUnit::forget();

        $html = $this->actingAs($admin)->get(route('production.units.index'));
        $html->assertOk()
            ->assertSee('cc-unit-switch')     // el selector aparece
            ->assertSee('cc-unit-banner')     // la franja aparece
            ->assertSee('Unidad marina');     // con el nombre de la unidad vigente
    }

    private function makeDsr(User $author, ?int $unitId): DailyReport
    {
        return DailyReport::create([
            'report_date'   => '2026-10-05',
            'location_name' => 'Set ' . Str::random(6),
            'slug_setting'  => 'INT.',
            'slug_time'     => 'DÍA',
            'weather_condition' => 'sunny',
            'nearest_hospital'  => 'Hospital QA',
            'crew_count'    => 10,
            'shoot_day'     => 1,
            'author_name'   => $author->name,
            'created_by_id' => $author->id,
            'unit_id'       => $unitId,
            'production_id' => CurrentProduction::id(),
        ]);
    }
}
