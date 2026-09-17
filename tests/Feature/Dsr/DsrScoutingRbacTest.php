<?php

namespace Tests\Feature\Dsr;

use Tests\QaTestCase;

/**
 * PUERTAS DE PERMISO del vertical DSR + SCOUTING.
 *
 * Matriz de fábrica (RolesAndPermissionsSeeder):
 *   dsr.create / dsr.update : super-admin, line-producer, safety-officer.
 *   dsr.view                : + coordinator, hod, auditor  (auditor = todos los *.view).
 *   locations.create        : super-admin, line-producer, coordinator, safety-officer.
 *   locations.view          : + auditor  (auditor = todos los *.view). hod NO tiene locations.view.
 *
 * Se prueba, para cada puerta: rol CON permiso -> 200/302 (no 403); rol SIN permiso -> 403;
 * invitado -> redirect a login. Las mutaciones (store/update) se prueban en el nivel de
 * autorización con payload mínimo suficiente para pasar el middleware; su persistencia se
 * cubre aparte (DsrPersistenceTest / ScoutingPersistenceTest).
 */
class DsrScoutingRbacTest extends QaTestCase
{
    // ---------------------------------------------------------------------
    //  INVITADO -> LOGIN (sin sesión, todas las rutas del vertical redirigen)
    // ---------------------------------------------------------------------

    /** @dataProvider rutasGetProvider */
    public function test_invitado_es_redirigido_a_login(string $routeName, array $params): void
    {
        $this->get(route($routeName, $params))->assertRedirect(route('login'));
    }

    public static function rutasGetProvider(): array
    {
        return [
            'dsr.index'        => ['daily_reports.index', []],
            'dsr.create'       => ['daily_reports.create', []],
            'dsr.show'         => ['daily_reports.show', ['id' => 1]],
            'scouting.index'   => ['scoutings.index', []],
            'scouting.create'  => ['scoutings.create', []],
            'scouting.show'    => ['scoutings.show', ['id' => 1]],
            'scouting.edit'    => ['scoutings.edit', ['id' => 1]],
            'scouting.amazon'  => ['scoutings.amazon', ['id' => 1]],
        ];
    }

    // ---------------------------------------------------------------------
    //  dsr.create  (GET /dsr-reports/create)
    // ---------------------------------------------------------------------

    /** @dataProvider dsrCreateRolesProvider */
    public function test_gate_dsr_create_form(string $role, bool $allowed): void
    {
        $this->actingAsRole($role);
        $resp = $this->get(route('daily_reports.create'));
        if ($allowed) {
            $resp->assertOk();
        } else {
            $resp->assertForbidden();
        }
    }

    public static function dsrCreateRolesProvider(): array
    {
        return [
            'super-admin'    => ['super-admin', true],
            'line-producer'  => ['line-producer', true],
            'safety-officer' => ['safety-officer', true],
            'coordinator'    => ['coordinator', false],
            'hod'            => ['hod', false],
            'medic'          => ['medic', false],
            'crew'           => ['crew', false],
            'auditor'        => ['auditor', false],
        ];
    }

    // ---------------------------------------------------------------------
    //  dsr.view  (GET /dsr-reports index)
    // ---------------------------------------------------------------------

    /** @dataProvider dsrViewRolesProvider */
    public function test_gate_dsr_index(string $role, bool $allowed): void
    {
        $this->actingAsRole($role);
        $resp = $this->get(route('daily_reports.index'));
        if ($allowed) {
            $resp->assertOk();
        } else {
            $resp->assertForbidden();
        }
    }

    public static function dsrViewRolesProvider(): array
    {
        return [
            'super-admin'    => ['super-admin', true],
            'line-producer'  => ['line-producer', true],
            'coordinator'    => ['coordinator', true],
            'hod'            => ['hod', true],
            'safety-officer' => ['safety-officer', true],
            'auditor'        => ['auditor', true],
            'medic'          => ['medic', false],
            'crew'           => ['crew', false],
        ];
    }

    // ---------------------------------------------------------------------
    //  dsr.update  (POST /dsr-reports/{id}/update)  — sólo autorización
    // ---------------------------------------------------------------------

    /** medic NO tiene dsr.update: el middleware corta antes del controlador. */
    public function test_gate_dsr_update_denegado_a_medic(): void
    {
        $this->actingAsRole('medic');
        $this->post(route('daily_reports.update', ['id' => 999999]))->assertForbidden();
    }

    /** coordinator ve DSR pero NO puede cerrar el día (dsr.update). */
    public function test_gate_dsr_update_denegado_a_coordinator(): void
    {
        $this->actingAsRole('coordinator');
        $this->post(route('daily_reports.update', ['id' => 999999]))->assertForbidden();
    }

    /** safety-officer SÍ tiene dsr.update: pasa el gate (404 por id inexistente, NO 403). */
    public function test_gate_dsr_update_permitido_a_safety_pasa_el_gate(): void
    {
        $this->actingAsRole('safety-officer');
        // findOrFail sobre un id inexistente => 404. Lo relevante: NO es 403 (pasó el permiso).
        $this->post(route('daily_reports.update', ['id' => 999999]))->assertNotFound();
    }

    // ---------------------------------------------------------------------
    //  dsr.create  (POST /dsr-reports/{id}/log)  — autorización del log
    // ---------------------------------------------------------------------

    public function test_gate_dsr_log_denegado_a_coordinator(): void
    {
        // coordinator tiene dsr.view pero NO dsr.create -> no puede agregar hallazgos.
        $this->actingAsRole('coordinator');
        $this->post(route('daily_logs.store', ['id' => 999999]))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    //  locations.create  (GET /scoutings/create)
    // ---------------------------------------------------------------------

    /** @dataProvider scoutCreateRolesProvider */
    public function test_gate_scouting_create_form(string $role, bool $allowed): void
    {
        $this->actingAsRole($role);
        $resp = $this->get(route('scoutings.create'));
        if ($allowed) {
            $resp->assertOk();
        } else {
            $resp->assertForbidden();
        }
    }

    public static function scoutCreateRolesProvider(): array
    {
        return [
            'super-admin'    => ['super-admin', true],
            'line-producer'  => ['line-producer', true],
            'coordinator'    => ['coordinator', true],
            'safety-officer' => ['safety-officer', true],
            'hod'            => ['hod', false],
            'medic'          => ['medic', false],
            'crew'           => ['crew', false],
            'auditor'        => ['auditor', false], // sólo *.view; locations.create NO
        ];
    }

    // ---------------------------------------------------------------------
    //  locations.view  (GET /scoutings index)
    // ---------------------------------------------------------------------

    /** @dataProvider scoutViewRolesProvider */
    public function test_gate_scouting_index(string $role, bool $allowed): void
    {
        $this->actingAsRole($role);
        $resp = $this->get(route('scoutings.index'));
        if ($allowed) {
            $resp->assertOk();
        } else {
            $resp->assertForbidden();
        }
    }

    public static function scoutViewRolesProvider(): array
    {
        return [
            'super-admin'    => ['super-admin', true],
            'line-producer'  => ['line-producer', true],
            'coordinator'    => ['coordinator', true],
            'safety-officer' => ['safety-officer', true],
            'auditor'        => ['auditor', true],
            'hod'            => ['hod', false], // hod NO tiene locations.view
            'medic'          => ['medic', false],
            'crew'           => ['crew', false],
        ];
    }

    // ---------------------------------------------------------------------
    //  locations.create  (PUT /scoutings/{id})  — sólo autorización
    // ---------------------------------------------------------------------

    public function test_gate_scouting_update_denegado_a_auditor(): void
    {
        // auditor ve scoutings (locations.view) pero NO puede editarlos (locations.create).
        $this->actingAsRole('auditor');
        $this->put(route('scoutings.update', ['id' => 999999]))->assertForbidden();
    }
}
