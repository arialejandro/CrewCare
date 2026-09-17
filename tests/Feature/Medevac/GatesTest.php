<?php

namespace Tests\Feature\Medevac;

use App\Models\MedevacPoster;

/**
 * PUERTAS (RBAC) del PÓSTER MEDEVAC.
 *
 * Permiso PROPIO medevac.issue en las rutas (create/store/show) — blinda también la URL directa.
 * Grant: safety-officer, super-admin. Todo lo demás: 403. Invitado: redirige a login.
 * line-producer discrimina: emite permisos de trabajo pero NO el póster MEDEVAC.
 */
class GatesTest extends MedevacVerticalTestCase
{
    // ---------------------------- INVITADO ----------------------------

    public function test_invitado_redirige_a_login(): void
    {
        $scouting = $this->makeScouting();
        $this->get(route('medevac.create', $scouting->id))->assertRedirect(route('login'));
    }

    // ---------------------------- CON PERMISO (200) ----------------------------

    public function test_roles_con_permiso_abren_el_formulario_de_emision(): void
    {
        $scouting = $this->makeScouting();
        foreach ($this->grantedRoles() as $role) {
            $this->actingAsRole($role);
            $this->get(route('medevac.create', $scouting->id))->assertOk();
            auth()->logout();
        }
    }

    // ---------------------------- SIN PERMISO (403) ----------------------------

    public function test_roles_sin_permiso_reciben_403(): void
    {
        $scouting = $this->makeScouting();
        foreach ($this->deniedRoles() as $role) {
            $this->actingAsRole($role);
            $this->get(route('medevac.create', $scouting->id))->assertForbidden();
            auth()->logout();
        }
    }

    /** El permiso cubre el POST que EMITE + SELLA, no sólo el formulario. */
    public function test_sin_permiso_no_puede_emitir(): void
    {
        $scouting = $this->makeScouting();
        $this->actingAsRole('crew');

        $this->post(route('medevac.store', $scouting->id), [])->assertForbidden();
        $this->assertSame(0, MedevacPoster::count(), 'Un rol sin medevac.issue NO debe emitir pósters.');
    }
}
