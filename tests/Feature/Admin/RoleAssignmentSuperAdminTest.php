<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Tests\QaTestCase;

/**
 * El super-admin es el ROL MÁXIMO y NO se reasigna desde /rolescrud. Antes el <select> lo
 * pintaba como line-producer (primera opción, porque super-admin no está en ASSIGNABLE) → un
 * operador con users.assign-role podía DEGRADARLO y quitarle god-mode (lockout). Aquí se blinda.
 */
class RoleAssignmentSuperAdminTest extends QaTestCase
{
    public function test_super_admin_cannot_be_demoted_from_roles_screen(): void
    {
        $target   = $this->makeUser('super-admin');
        $operator = $this->makeUser('line-producer'); // users.assign-role + all-departments
        $this->actingAs($operator);

        $this->post(route('roles.update', $target->id), ['role' => 'line-producer', 'department_id' => null])
            ->assertRedirect();

        $target->refresh();
        $this->assertTrue($target->hasRole('super-admin'), 'sigue siendo super-admin');
        $this->assertFalse($target->hasRole('line-producer'), 'no fue degradado');
    }

    public function test_roles_index_renders_super_admin_as_locked_badge(): void
    {
        $this->makeUser('super-admin', ['name' => 'Owner', 'lname' => 'Prueba']);
        $this->actingAs($this->makeUser('line-producer'));

        // La marca del badge bloqueado (título único) solo la emite la rama del super-admin.
        $this->get(route('roles.index'))->assertOk()->assertSee('Rol máximo', false);
    }

    public function test_medical_grant_is_a_noop_for_super_admin(): void
    {
        $target = $this->makeUser('super-admin');
        $this->actingAs($this->makeUser('super-admin')); // grantMedical exige super-admin como actor

        $this->post(route('roles.medical.grant', $target->id), ['permission' => 'medical.view'])->assertRedirect();

        $this->assertFalse($target->fresh()->hasDirectPermission('medical.view'), 'no agrega permiso directo redundante');
    }
}
