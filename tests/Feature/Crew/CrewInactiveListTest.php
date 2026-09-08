<?php

namespace Tests\Feature\Crew;

use App\Models\Production;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\UnitMembership;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * CREW · LISTA DE DADOS DE BAJA + REINTEGRACIÓN (2026-09-08). El crew inactivo se ve por departamento
 * (antes no aparecía en ningún lado salvo la búsqueda global). Distingue baja individual de "apagado con
 * una unidad". Reintegrar reactiva a la persona, limpia el marcador y EVIDENCIA que falta contrato — sin
 * emitirlo. La validación del alta NO se toca.
 */
class CrewInactiveListTest extends QaTestCase
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
            $u->givePermissionTo('users.view');
            $u->givePermissionTo('users.update');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }

        return $u;
    }

    public function test_lista_muestra_inactivos_con_su_motivo_y_no_a_los_activos(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        // Nombres de UN SOLO token: User::displayName muestra el primer token → assertSee casa exacto.
        $activo   = $this->crew('SigueActivo' . Str::random(4));
        $individual = $this->crew('BajaSola' . Str::random(4));
        $conUnidad  = $this->crew('CaeConUnidad' . Str::random(4));

        // Baja individual.
        $individual->forceFill(['activo' => 0])->save();

        // Apagado con la unidad: exclusivo + desactivar la unidad.
        UnitMembership::setState($u2->id, $conUnidad->id, 'solo');
        UnitMembership::deactivateExclusivesOf($u2->id);
        $this->assertSame(0, (int) $conUnidad->fresh()->activo);

        $this->actingAs($this->admin());
        $this->get(route('crew.inactive'))
            ->assertOk()
            ->assertSee('Dados de baja')
            ->assertSee($individual->name)
            ->assertSee($conUnidad->name)
            ->assertSee('Baja individual')
            ->assertSee('Segunda unidad')       // "Apagado con «Segunda unidad»"
            ->assertDontSee($activo->name);     // los activos no salen aquí
    }

    public function test_reintegrar_reactiva_limpia_marcador_y_evidencia_contrato(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);
        $p    = $this->crew('Reintegrable ' . Str::random(4));

        UnitMembership::setState($u2->id, $p->id, 'solo');
        UnitMembership::deactivateExclusivesOf($u2->id);
        $this->assertSame(0, (int) $p->fresh()->activo);

        $this->actingAs($this->admin());
        $this->post(route('crew.reintegrate', $p->id))
            ->assertRedirect(route('crew.inactive'))
            ->assertSessionHas('reintegrated');

        $this->assertSame(1, (int) $p->fresh()->activo, 'Reintegrar reactiva a la persona.');
        $this->assertSame(0, (int) \App\Models\UnitMember::where('unit_id', $u2->id)->where('user_id', $p->id)->value('deactivated_with_unit'), 'Sale del ciclo de la unidad (marcador limpio).');

        // La evidencia "falta contrato" queda visible al reintegrar (flash) y en el aviso previo del botón.
        $this->get(route('crew.inactive'))->assertOk()->assertSee('contrato');
    }

    public function test_la_validacion_del_alta_no_cambio_sigue_rechazando_email_duplicado(): void
    {
        $prod = $this->prod();
        $existing = $this->crew('Ya Existe ' . Str::random(4));
        $existing->forceFill(['activo' => 0, 'email' => 'dup-' . Str::random(6) . '@qa.test'])->save();

        $admin = $this->makeUser('super-admin');
        try {
            $admin->givePermissionTo('users.create');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
        $this->actingAs($admin);

        // Alta con el MISMO email de alguien inactivo → sigue rechazando (unique:users,email), no duplica.
        $before = User::where('email', $existing->email)->count();
        $this->post(route('newuser'), [
            'name'  => 'Intento Duplicado',
            'email' => $existing->email,
        ])->assertSessionHasErrors('email');
        $this->assertSame($before, User::where('email', $existing->email)->count(), 'No se creó un duplicado.');
    }
}
