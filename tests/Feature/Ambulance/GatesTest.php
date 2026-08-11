<?php

namespace Tests\Feature\Ambulance;

use App\Models\AmbulanceInspection;

/**
 * PUERTAS (RBAC) del módulo de VERIFICACIÓN DE AMBULANCIAS.
 *
 * Gate ÚNICO: permission:ambulance.manage a nivel de grupo de rutas → cubre TODO el módulo
 * (hub, recurso del día, verificación, actas, proveedores, documentos). Grant: safety-officer,
 * super-admin. Todo lo demás: 403. Invitado: redirige a login ('auth' corre antes de 'permission').
 *
 * line-producer es el discriminador: sí emite permisos de trabajo, pero NO gestiona ambulancias.
 */
class GatesTest extends AmbulanceVerticalTestCase
{
    /** Rutas GET del módulo que deben respetar el gate único. */
    private function moduleGetRoutes(): array
    {
        return [
            route('ambulance.index'),
            route('ambulance.day.form'),
            route('ambulance.inspect.form'),
            route('ambulance.records'),
            route('ambulance.providers'),
        ];
    }

    // ---------------------------- INVITADO ----------------------------

    public function test_invitado_redirige_a_login_en_todo_el_modulo(): void
    {
        foreach ($this->moduleGetRoutes() as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    // ---------------------------- CON PERMISO (200) ----------------------------

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

    // ---------------------------- SIN PERMISO (403) ----------------------------

    public function test_roles_sin_permiso_reciben_403(): void
    {
        foreach ($this->deniedRoles() as $role) {
            $this->actingAsRole($role);
            $this->get(route('ambulance.index'))->assertForbidden();
            $this->get(route('ambulance.inspect.form'))->assertForbidden();
            $this->get(route('ambulance.records'))->assertForbidden();
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
