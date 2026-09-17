<?php

namespace Tests\Feature\Crew;

use Tests\QaTestCase;

/**
 * PUERTAS DE PERMISO del vertical CREW + RBAC.
 *
 * Para cada grupo `permission:users.*` (y crew.view / reports.export / import),
 * se verifica que un rol SIN el permiso reciba 403 y uno CON el permiso NO reciba 403
 * (200 en GET que renderiza, 302 en POST que muta/redirige). super-admin pasa por
 * Gate::before. La matriz esperada se deriva de RolesAndPermissionsSeeder.
 *
 * NOTA sobre import (/importcrew, /crewstore): NO usa `permission:` sino el middleware
 * legacy `admin` (flag binario users.admin). Por eso un no-admin NO recibe 403 sino un
 * redirect('/') — se prueba aparte (test_import_solo_admin_*).
 */
class CrewRbacGatesTest extends QaTestCase
{
    /** Crea un objetivo real y devuelve su id (para las rutas con {id}). */
    private function targetId(): int
    {
        return $this->makeUser('crew')->id;
    }

    private function assertAllowedGet(string $role, string $url): void
    {
        $this->actingAsRole($role);
        $this->get($url)->assertOk();
    }

    private function assertForbiddenGet(string $role, string $url): void
    {
        $this->actingAsRole($role);
        $this->get($url)->assertForbidden();
    }

    // ---------- users.view : /usuarioscrud, /idcardscrud ----------

    public function test_users_view_permitidos_ven_el_listado(): void
    {
        foreach (['super-admin', 'line-producer', 'coordinator', 'hod', 'auditor'] as $role) {
            $this->assertAllowedGet($role, route('usuarioscrud'));
        }
    }

    public function test_users_view_denegados_reciben_403(): void
    {
        foreach (['medic', 'safety-officer', 'crew'] as $role) {
            $this->assertForbiddenGet($role, route('usuarioscrud'));
        }
    }

    public function test_users_view_idcards_respeta_la_misma_puerta(): void
    {
        $this->assertAllowedGet('coordinator', route('idcardscrud'));
        $this->assertForbiddenGet('crew', route('idcardscrud'));
    }

    // ---------- users.create : /adduser ----------

    public function test_users_create_permitidos_ven_el_alta(): void
    {
        foreach (['super-admin', 'line-producer', 'coordinator', 'hod'] as $role) {
            $this->assertAllowedGet($role, route('adduser'));
        }
    }

    public function test_users_create_denegados_reciben_403(): void
    {
        foreach (['medic', 'safety-officer', 'crew', 'auditor'] as $role) {
            $this->assertForbiddenGet($role, route('adduser'));
        }
    }

    // ---------- users.update : /useredit/{id} ----------

    public function test_users_update_permitidos_abren_la_ficha(): void
    {
        $id = $this->targetId();
        foreach (['super-admin', 'line-producer', 'coordinator'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('useredit', ['id' => $id]))->assertOk();
        }
    }

    public function test_users_update_denegados_reciben_403(): void
    {
        $id = $this->targetId();
        foreach (['hod', 'medic', 'safety-officer', 'crew', 'auditor'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('useredit', ['id' => $id]))->assertForbidden();
        }
    }

    // ---------- users.deactivate : POST /desactivarusuario/{id} ----------

    public function test_users_deactivate_permitidos_no_reciben_403(): void
    {
        foreach (['super-admin', 'line-producer'] as $role) {
            $this->actingAsRole($role);
            $id = $this->makeUser('crew')->id; // objetivo fresco por rol
            $this->post(route('desactivarusuario', ['id' => $id]))->assertStatus(302);
        }
    }

    public function test_users_deactivate_denegados_reciben_403(): void
    {
        $id = $this->targetId();
        foreach (['coordinator', 'hod', 'medic', 'safety-officer', 'crew', 'auditor'] as $role) {
            $this->actingAsRole($role);
            $this->post(route('desactivarusuario', ['id' => $id]))->assertForbidden();
        }
    }

    // ---------- users.assign-role : GET /rolescrud, POST /activaradmin/{id} ----------

    public function test_users_assign_role_permitidos_ven_la_pantalla(): void
    {
        foreach (['super-admin', 'line-producer'] as $role) {
            $this->assertAllowedGet($role, route('roles.index'));
        }
    }

    public function test_users_assign_role_denegados_reciben_403(): void
    {
        foreach (['coordinator', 'hod', 'medic', 'safety-officer', 'crew', 'auditor'] as $role) {
            $this->assertForbiddenGet($role, route('roles.index'));
        }
    }

    public function test_activaradmin_denegado_para_quien_no_asigna_rol(): void
    {
        $id = $this->targetId();
        // coordinator PUEDE crear/editar usuarios pero NO asignar rol → 403 al togglear admin.
        $this->actingAsRole('coordinator');
        $this->post(route('activaradmin', ['id' => $id]))->assertForbidden();
    }

    // ---------- roles.manage-permissions : GET /permisoscrud (SOLO super-admin) ----------

    public function test_manage_permissions_solo_super_admin(): void
    {
        $this->assertAllowedGet('super-admin', route('roles.permissions.edit'));

        foreach (['line-producer', 'coordinator', 'hod', 'medic', 'safety-officer', 'crew', 'auditor'] as $role) {
            $this->assertForbiddenGet($role, route('roles.permissions.edit'));
        }
    }

    // ---------- crew.view : GET /searchusers/{valor} ----------

    public function test_crew_view_permitidos_buscan(): void
    {
        foreach (['super-admin', 'line-producer', 'coordinator', 'hod', 'medic', 'safety-officer', 'auditor'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('searchusers', ['valor' => 'qa']))->assertOk();
        }
    }

    public function test_crew_view_denegado_para_crew(): void
    {
        $this->actingAsRole('crew');
        $this->get(route('searchusers', ['valor' => 'qa']))->assertForbidden();
    }

    // ---------- reports.export : GET /nophoto (CSV) ----------

    public function test_reports_export_permitidos_descargan_csv(): void
    {
        foreach (['super-admin', 'line-producer', 'safety-officer'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('expCsv'))->assertOk();
        }
    }

    public function test_reports_export_denegados_reciben_403(): void
    {
        foreach (['coordinator', 'hod', 'medic', 'crew', 'auditor'] as $role) {
            $this->assertForbiddenGet($role, route('expCsv'));
        }
    }

    // ---------- import : middleware legacy `admin` (NO permission) ----------

    public function test_import_solo_admin_lo_ve(): void
    {
        // super-admin (admin=1) pasa.
        $this->actingAsRole('super-admin');
        $this->get(route('importcrew'))->assertOk();
    }

    public function test_import_no_admin_es_redirigido_no_403(): void
    {
        // Un rol con users.create pero admin=0 NO llega al import: AdminMiddleware -> redirect('/').
        foreach (['line-producer', 'coordinator', 'hod', 'crew'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('importcrew'))->assertRedirect('/');
        }
    }

    // ---------- invitado (sin sesion) ----------

    public function test_invitado_redirigido_a_login_en_rutas_con_auth(): void
    {
        $this->get(route('adduser'))->assertRedirect(route('login'));
        $this->get(route('roles.index'))->assertRedirect(route('login'));
        $this->get(route('expCsv'))->assertRedirect(route('login'));
    }
}
