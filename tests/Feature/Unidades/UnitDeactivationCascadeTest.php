<?php

namespace Tests\Feature\Unidades;

use App\Models\Production;
use App\Models\Unit;
use App\Models\UnitMember;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\UnitMembership;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * UNIDADES · §1 — DESACTIVACIÓN EN CASCADA. Las unidades NO se combinan: apagar una unidad apaga a sus
 * EXCLUSIVOS de verdad (users.activo=0, el mismo mecanismo del crew), no los devuelve a la principal, y no
 * toca a los COMPARTIDOS. Reactivar la unidad devuelve EXACTAMENTE a los que ella apagó, sin resucitar a
 * quien ya estaba de baja por su cuenta ni a quien otra unidad activa todavía necesita.
 */
class UnitDeactivationCascadeTest extends QaTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1])->save();
        CurrentProduction::forget();

        return $prod;
    }

    private function unit(Production $prod, string $name = 'Segunda unidad'): Unit
    {
        return Unit::create(['production_id' => $prod->id, 'name' => $name, 'sort_order' => 1, 'is_active' => true]);
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

    public function test_desactivar_apaga_exclusivos_no_toca_compartidos_y_reactivar_los_devuelve(): void
    {
        $prod  = $this->prod();
        $u2    = $this->unit($prod);
        $solo  = $this->crew('Solo ' . Str::random(4));    // exclusivo de la 2ª unidad
        $ambas = $this->crew('Ambas ' . Str::random(4));   // compartido

        UnitMembership::setState($u2->id, $solo->id, 'solo');
        UnitMembership::setState($u2->id, $ambas->id, 'ambas');

        // El aviso previo anuncia SÓLO a los exclusivos activos.
        $this->assertSame(1, UnitMembership::countActiveExclusivesOf($u2->id));

        $n = UnitMembership::deactivateExclusivesOf($u2->id);
        $this->assertSame(1, $n);
        $this->assertSame(0, (int) $solo->fresh()->activo, 'El exclusivo se apaga con la unidad.');
        $this->assertSame(1, (int) $ambas->fresh()->activo, 'El compartido (Ambas) NO se toca.');
        $this->assertSame(1, (int) UnitMember::where('unit_id', $u2->id)->where('user_id', $solo->id)->value('deactivated_with_unit'));
        $this->assertSame(0, (int) UnitMember::where('unit_id', $u2->id)->where('user_id', $ambas->id)->value('deactivated_with_unit'));

        // Reactivar la unidad devuelve EXACTAMENTE al exclusivo y limpia la marca.
        $n2 = UnitMembership::reactivateAutoDeactivatedOf($u2->id);
        $this->assertSame(1, $n2);
        $this->assertSame(1, (int) $solo->fresh()->activo);
        $this->assertSame(0, (int) UnitMember::where('unit_id', $u2->id)->where('user_id', $solo->id)->value('deactivated_with_unit'));
    }

    public function test_no_resucita_a_quien_ya_estaba_de_baja_por_su_cuenta(): void
    {
        $prod = $this->prod();
        $u2   = $this->unit($prod);
        $ya   = $this->crew('YaBaja ' . Str::random(4));

        UnitMembership::setState($u2->id, $ya->id, 'solo');
        $ya->forceFill(['activo' => 0])->save();   // dado de baja ANTES, por su cuenta

        $this->assertSame(0, UnitMembership::countActiveExclusivesOf($u2->id));
        $this->assertSame(0, UnitMembership::deactivateExclusivesOf($u2->id), 'Ya estaba de baja → la unidad no lo apaga ni lo marca.');
        $this->assertSame(0, (int) UnitMember::where('unit_id', $u2->id)->where('user_id', $ya->id)->value('deactivated_with_unit'));

        // Reactivar la unidad NO debe encenderlo: no fue ella quien lo apagó.
        $this->assertSame(0, UnitMembership::reactivateAutoDeactivatedOf($u2->id));
        $this->assertSame(0, (int) $ya->fresh()->activo, 'Sigue de baja: la unidad no lo resucita.');
    }

    public function test_no_apaga_a_quien_es_exclusivo_de_otra_unidad_activa(): void
    {
        $prod = $this->prod();
        $uA   = $this->unit($prod, 'Unidad A');
        $uB   = $this->unit($prod, 'Unidad B');
        $both = $this->crew('Doble ' . Str::random(4));

        UnitMembership::setState($uA->id, $both->id, 'solo');
        UnitMembership::setState($uB->id, $both->id, 'solo');

        $n = UnitMembership::deactivateExclusivesOf($uA->id);
        $this->assertSame(0, $n, 'B sigue activa y lo necesita → apagar A no lo apaga.');
        $this->assertSame(1, (int) $both->fresh()->activo);
    }

    public function test_toggle_del_controlador_apaga_y_luego_enciende(): void
    {
        $prod = $this->prod();
        $u2   = $this->unit($prod);
        $solo = $this->crew('Solo ' . Str::random(4));
        UnitMembership::setState($u2->id, $solo->id, 'solo');

        $this->actingAs($this->admin());

        $this->post(route('production.units.toggle', $u2->id))->assertRedirect(route('production.units.index'));
        $this->assertFalse((bool) $u2->fresh()->is_active);
        $this->assertSame(0, (int) $solo->fresh()->activo, 'Desactivar la unidad apagó al exclusivo.');

        $this->post(route('production.units.toggle', $u2->id))->assertRedirect(route('production.units.index'));
        $this->assertTrue((bool) $u2->fresh()->is_active);
        $this->assertSame(1, (int) $solo->fresh()->activo, 'Reactivar la unidad devolvió al exclusivo.');
    }
}
