<?php

namespace Tests\Feature\Pae;

/**
 * RBAC del PAE — un ÚNICO gate (permission:pae.issue) blinda TODO el módulo (index, emitir,
 * store, editar, show), incluida la URL directa. Sólo safety-officer y super-admin lo tienen;
 * los otros 6 roles reciben 403. El invitado cae al login (auth corre antes del permiso).
 *
 * El verificador PÚBLICO (QR) NO va aquí: vive sin sesión y se prueba en PublicVerifierTest.
 */
class GatesTest extends PaeVerticalTestCase
{
    public static function grantedProvider(): array
    {
        return [['safety-officer'], ['super-admin']];
    }

    public static function deniedProvider(): array
    {
        return [['line-producer'], ['coordinator'], ['hod'], ['medic'], ['crew'], ['auditor']];
    }

    // ---------------------------------------------------------- INDEX / CREATE

    /** @dataProvider grantedProvider */
    public function test_index_y_emitir_abren_para_roles_con_permiso(string $role): void
    {
        $this->makeScouting(); // que el formulario tenga una locación elegible
        $this->actingAsRole($role);

        $this->get(route('pae.index'))->assertOk();
        $this->get(route('pae.create'))->assertOk();
    }

    /** @dataProvider deniedProvider */
    public function test_index_y_emitir_cerrados_para_roles_sin_permiso(string $role): void
    {
        $this->actingAsRole($role);

        $this->get(route('pae.index'))->assertForbidden();
        $this->get(route('pae.create'))->assertForbidden();
    }

    // ------------------------------------------------------------- SHOW / EDIT

    /** @dataProvider grantedProvider */
    public function test_show_y_editar_abren_para_roles_con_permiso(string $role): void
    {
        $plan = $this->sealPlan();
        $this->actingAsRole($role);

        $this->get(route('pae.show', $plan->uuid))->assertOk();
        $this->get(route('pae.edit', $plan->uuid))->assertOk();
    }

    /** @dataProvider deniedProvider */
    public function test_show_cerrado_para_roles_sin_permiso(string $role): void
    {
        $plan = $this->sealPlan();
        $this->actingAsRole($role);

        $this->get(route('pae.show', $plan->uuid))->assertForbidden();
    }

    // ----------------------------------------------------------------- STORE

    /** @dataProvider deniedProvider */
    public function test_store_cerrado_para_roles_sin_permiso(string $role): void
    {
        $sc = $this->makeScouting();
        $this->actingAsRole($role);

        $this->post(route('pae.store'), $this->storePayload([$sc->id]))->assertForbidden();
        $this->assertDatabaseCount('emergency_action_plans', 0);
    }

    // --------------------------------------------------------------- INVITADO

    public function test_rutas_exigen_sesion(): void
    {
        $plan = $this->sealPlan();

        $this->get(route('pae.index'))->assertRedirect(route('login'));
        $this->get(route('pae.create'))->assertRedirect(route('login'));
        $this->get(route('pae.show', $plan->uuid))->assertRedirect(route('login'));
    }
}
