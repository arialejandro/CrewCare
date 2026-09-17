<?php

namespace Tests\Feature\Unidades;

use App\Models\Production;
use App\Models\Unit;
use App\Models\UnitMember;
use App\Models\User;
use App\Support\CrewRosterBuilder;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use App\Support\UnitMembership;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * UNIDADES · 2c — la PERTENENCIA persona↔unidad es PIVOTE (unit_members); users/production_user siguen sin
 * unidad. Una persona puede estar en las dos; marcarla no la duplica ni le pide nada; el CrewList de una
 * unidad muestra sólo a su gente; con una sola unidad, todo idéntico.
 */
class UnitMembershipTest extends QaTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1])->save();
        CurrentProduction::forget();

        return $prod;
    }

    private function crew(string $tag): User
    {
        return $this->makeUser('crew', ['name' => $tag, 'crewlist_visible' => 1, 'activo' => 1]);
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

    private function crewIds(User $viewer): \Illuminate\Support\Collection
    {
        $roster = CrewRosterBuilder::build($viewer);

        return collect($roster['groups'] ?? [])->flatMap(fn ($g) => collect($g['people'] ?? [])->pluck('id'))->map(fn ($x) => (int) $x);
    }

    public function test_users_y_production_user_no_llevan_unidad(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'unit_id'), 'users NUNCA lleva unidad.');
        $this->assertFalse(Schema::hasColumn('production_user', 'unit_id'), 'production_user NUNCA lleva unidad.');
    }

    public function test_una_persona_puede_estar_en_las_dos_y_marcarla_no_duplica(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);
        $hod  = $this->crew('HOD Arte ' . Str::random(4));

        UnitMembership::setState($u2->id, $hod->id, 'ambas');
        $this->assertSame('ambas', UnitMembership::stateFor($u2->id, $hod->id));

        // Está en la 2ª unidad Y en la principal (compartida).
        $this->assertContains($hod->id, UnitMembership::userIdsIn($u2->id));
        $this->assertNotContains($hod->id, UnitMembership::exclusiveUserIds(), 'Compartida → sigue en la principal.');

        // Marcarla otra vez NO duplica: una sola fila en la pivote.
        UnitMembership::setState($u2->id, $hod->id, 'ambas');
        $this->assertSame(1, UnitMember::where('unit_id', $u2->id)->where('user_id', $hod->id)->count());
    }

    public function test_el_crewlist_de_una_unidad_muestra_solo_a_su_gente(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        $solo  = $this->crew('Gaffer 2U ' . Str::random(4));   // exclusivo de la 2ª unidad
        $ambas = $this->crew('HOD Arte '  . Str::random(4));   // compartido
        $prin  = $this->crew('Gaffer 1U ' . Str::random(4));   // principal

        UnitMembership::setState($u2->id, $solo->id, 'solo');
        UnitMembership::setState($u2->id, $ambas->id, 'ambas');

        // Vigente = 2ª unidad → sólo su gente (solo + ambas), NO el de la principal.
        session()->put(CurrentUnit::SESSION_KEY, $u2->id);
        CurrentUnit::forget();
        $ids = $this->crewIds($this->admin());
        $this->assertContains($solo->id, $ids);
        $this->assertContains($ambas->id, $ids);
        $this->assertNotContains($prin->id, $ids, 'El de la principal no aparece en la 2ª unidad.');

        // Vigente = principal → todos MENOS el exclusivo de la 2ª unidad.
        session()->forget(CurrentUnit::SESSION_KEY);
        CurrentUnit::forget();
        $ids = $this->crewIds($this->admin());
        $this->assertContains($prin->id, $ids);
        $this->assertContains($ambas->id, $ids, 'El compartido sí aparece en la principal.');
        $this->assertNotContains($solo->id, $ids, 'El exclusivo de la 2ª unidad NO aparece en la principal.');
    }

    public function test_con_una_sola_unidad_se_ve_todo_el_crew(): void
    {
        $this->prod();   // sin unidades adicionales
        $a = $this->crew('A ' . Str::random(4));
        $b = $this->crew('B ' . Str::random(4));

        CurrentUnit::forget();
        $ids = $this->crewIds($this->admin());
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids, 'Sin pivote poblada, se ve todo el crew (idéntico a hoy).');
    }

    public function test_el_constructor_renderiza_y_guarda(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);
        $hod  = $this->crew('HOD Construcción ' . Str::random(4));

        $this->actingAs($this->admin());
        $this->get(route('production.units.builder', $u2->id))
            ->assertOk()
            ->assertSee('Constructor')
            ->assertSee('Ambas');

        $this->post(route('production.units.builder.save', $u2->id), ['m' => [$hod->id => 'ambas']])
            ->assertRedirect(route('production.units.builder', $u2->id));

        $this->assertSame('ambas', UnitMembership::stateFor($u2->id, $hod->id));
    }
}
