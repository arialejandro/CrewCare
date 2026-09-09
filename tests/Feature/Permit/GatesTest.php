<?php

namespace Tests\Feature\Permit;

/**
 * PUERTAS (RBAC) de ambas verticales.
 *
 * PERMISOS → permission:permits.issue. INSPECCIÓN → permission:tools.inspect.
 * Grant (ambos seeders): safety-officer, line-producer, super-admin. Todo lo demás: 403.
 * Invitado: redirige a login (el middleware 'auth' corre ANTES que 'permission').
 */
class GatesTest extends PermitVerticalTestCase
{
    // ---------------------------- INVITADO ----------------------------

    public function test_invitado_permisos_redirige_a_login(): void
    {
        $this->get(route('permits.index'))->assertRedirect(route('login'));
    }

    public function test_invitado_inspeccion_redirige_a_login(): void
    {
        $this->get(route('tools.index'))->assertRedirect(route('login'));
    }

    // ---------------------------- CON PERMISO (200) ----------------------------

    public function test_roles_con_permiso_ven_el_indice_de_permisos(): void
    {
        foreach ($this->grantedRoles() as $role) {
            $this->actingAsRole($role);
            $this->get(route('permits.index'))
                ->assertOk();
            // Cierra la sesión entre roles para no arrastrar identidad.
            auth()->logout();
        }
    }

    public function test_roles_con_permiso_ven_el_indice_de_inspeccion(): void
    {
        foreach ($this->grantedRoles() as $role) {
            $this->actingAsRole($role);
            $this->get(route('tools.index'))->assertOk();
            auth()->logout();
        }
    }

    // ---------------------------- SIN PERMISO (403) ----------------------------

    public function test_roles_sin_permiso_no_ven_permisos(): void
    {
        foreach ($this->deniedRoles() as $role) {
            $this->actingAsRole($role);
            $this->get(route('permits.index'))
                ->assertForbidden();
            auth()->logout();
        }
    }

    public function test_roles_sin_permiso_no_ven_inspeccion(): void
    {
        foreach ($this->deniedRoles() as $role) {
            $this->actingAsRole($role);
            $this->get(route('tools.index'))->assertForbidden();
            auth()->logout();
        }
    }

    // ---------------------------- SEPARACIÓN DE PERMISOS ----------------------------

    /**
     * EMITIR ≠ INSPECCIONAR: son permisos distintos. Aquí ambos se conceden a los mismos
     * roles de fábrica, así que la separación se prueba a nivel unitario: un usuario con
     * SOLO tools.inspect (sin permits.issue) NO entra a permisos, y viceversa.
     */
    public function test_solo_tools_inspect_no_abre_permisos(): void
    {
        $user = $this->makeUser('crew');
        $user->givePermissionTo('tools.inspect');
        $this->actingAs($user);

        $this->get(route('tools.index'))->assertOk();       // sí inspecciona
        $this->get(route('permits.index'))->assertForbidden(); // no emite
    }

    public function test_solo_permits_issue_no_abre_inspeccion(): void
    {
        $user = $this->makeUser('crew');
        $user->givePermissionTo('permits.issue');
        $this->actingAs($user);

        $this->get(route('permits.index'))->assertOk();     // sí emite
        $this->get(route('tools.index'))->assertForbidden();  // no inspecciona
    }

    // ---------------------------- POST también gateado ----------------------------

    /** La compuerta cubre el POST de emisión, no sólo el índice de lectura. */
    public function test_sin_permiso_no_puede_postear_emision(): void
    {
        $permit = $this->makePermitCatalog();
        $this->actingAsRole('crew');

        $this->post(route('permits.store', $permit->id), $this->permitStorePayload($permit))
            ->assertForbidden();

        $this->assertSame(0, \App\Models\IssuedPermit::count(), 'Un rol sin permiso NO debe emitir.');
    }

    /** La compuerta cubre el POST de inspección. */
    public function test_sin_permiso_no_puede_postear_inspeccion(): void
    {
        $tool = $this->makeToolWithPoints([['gate' => true, 'outcome' => 'reemplazo']]);
        $this->actingAsRole('crew');

        $this->post(route('tools.inspect.store', $tool->id), $this->inspectionStorePayload($tool))
            ->assertForbidden();

        $this->assertSame(0, \App\Models\ToolInspection::count(), 'Un rol sin permiso NO debe inspeccionar.');
    }
}
