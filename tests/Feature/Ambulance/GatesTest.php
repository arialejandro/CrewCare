<?php

namespace Tests\Feature\Ambulance;

use App\Models\AmbulanceInspection;

/**
 * PUERTAS (RBAC) del módulo de VERIFICACIÓN DE AMBULANCIAS — TRES niveles (Paso 4 · visibilidad):
 *   - GESTIONA (ambulance.manage): safety + super-admin → lectura Y escritura.
 *   - VE (ambulance.view): producción (line-producer/coordinator) → SOLO lectura.
 *   - NADA: hod (incl. transporte), medic, crew, auditor → 403 en todo.
 * Invitado: redirige a login ('auth' corre antes de 'permission').
 */
class GatesTest extends AmbulanceVerticalTestCase
{
    /** GET de LECTURA (hub, actas, proveedores): manage O view. */
    private function readGetRoutes(): array
    {
        return [
            route('ambulance.index'),
            route('ambulance.records'),
            route('ambulance.providers'),
        ];
    }

    /** GET de ESCRITURA (formularios que capturan/sellan): SOLO manage. */
    private function writeGetRoutes(): array
    {
        return [
            route('ambulance.day.form'),
            route('ambulance.inspect.form'),
        ];
    }

    private function moduleGetRoutes(): array
    {
        return array_merge($this->readGetRoutes(), $this->writeGetRoutes());
    }

    // ---------------------------- INVITADO ----------------------------

    public function test_invitado_redirige_a_login_en_todo_el_modulo(): void
    {
        foreach ($this->moduleGetRoutes() as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    // ---------------------------- GESTIONA (200 en todo) ----------------------------

    public function test_roles_con_permiso_entran_a_todo_el_modulo(): void
    {
        foreach ($this->grantedRoles() as $role) {
            $this->actingAsRole($role);
            foreach ($this->moduleGetRoutes() as $url) {
                $this->get($url)->assertOk();
            }
            auth()->logout();
        }
    }

    // ---------------------------- VE (lectura 200, escritura 403) ----------------------------

    public function test_view_roles_entran_solo_a_lectura(): void
    {
        foreach ($this->viewRoles() as $role) {
            $this->actingAsRole($role);
            foreach ($this->readGetRoutes() as $url) {
                $this->get($url)->assertOk();
            }
            foreach ($this->writeGetRoutes() as $url) {
                $this->get($url)->assertForbidden();
            }
            auth()->logout();
        }
    }

    // ---------------------------- SIN PERMISO (403 en todo) ----------------------------

    public function test_roles_sin_permiso_reciben_403(): void
    {
        foreach ($this->deniedRoles() as $role) {
            $this->actingAsRole($role);
            foreach ($this->moduleGetRoutes() as $url) {
                $this->get($url)->assertForbidden();
            }
            auth()->logout();
        }
    }

    /** El gate cubre el POST que SELLA el acta, no sólo las lecturas. */
    public function test_sin_permiso_no_puede_postear_verificacion(): void
    {
        $type = $this->aTerrestrialType();
        $this->actingAsRole('crew');

        $this->post(route('ambulance.inspect.store'), $this->inspectStorePayload($type))
            ->assertForbidden();

        $this->assertSame(0, AmbulanceInspection::count(), 'Un rol sin ambulance.manage NO debe sellar actas.');
    }

    /** El gate cubre el POST del recurso del día (Parte A). */
    public function test_sin_permiso_no_puede_declarar_recurso_del_dia(): void
    {
        $this->actingAsRole('coordinator');
        $this->post(route('ambulance.day.store'), ['state' => 'declared_none'])
            ->assertForbidden();
    }
}
