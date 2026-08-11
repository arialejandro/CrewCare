<?php

namespace Tests\Feature\Incident;

use App\Models\hazardnotification;
use App\Models\InjuryReport;
use App\Models\unsafecond;
use Tests\QaTestCase;

/**
 * QA — VERTICAL INJURY (accidentes) + HAZARD/UNSAFE (actos y condiciones inseguras): PUERTAS RBAC.
 *
 * Fuente de verdad de la matriz rol→permiso: RolesAndPermissionsSeeder (chain de fábrica).
 *   injury.view    → super-admin, line-producer, coordinator, hod, medic, safety-officer, auditor  (NO crew)
 *   injury.create  → super-admin, line-producer, medic, safety-officer, crew                        (NO coordinator/hod/auditor)
 *   hazards.view   → super-admin, line-producer, coordinator, hod, safety-officer, auditor          (NO medic, NO crew)
 *   hazards.create → super-admin, line-producer, medic, safety-officer, crew                        (NO coordinator/hod/auditor)
 *   hazards.manage → super-admin, line-producer, medic, safety-officer                              (NO coordinator/hod/crew/auditor)
 *
 * Las rutas usan el MIDDLEWARE de permiso (no las policies a nivel fila para la ficha general):
 * un rol sin el permiso recibe 403 ANTES de tocar el controlador; el invitado va a login.
 *
 * Injury DOS SALIDAS: la LITE (`/accident/{id}`) sólo exige injury.view; la COMPLETA
 * (`/accident/{id}/completo`) exige además la policy viewMedical vía $this->authorize().
 */
class IncidentRbacGatesTest extends QaTestCase
{
    /** Crea un injury sellado por un capturador dedicado (NO el viewer) para probar viewMedical. */
    private function makeSealedInjury(): InjuryReport
    {
        $author = $this->makeUser('safety-officer');
        $injury = InjuryReport::create([
            'name'          => 'Lesionado QA',
            'what_happened' => 'Corte al operar herramienta',
            'created_by_id' => $author->id,
            'likelihood'    => 'C',
            'consequence'   => 3,
        ]);
        $injury->refresh();
        $injury->signDocument($author);

        return $injury;
    }

    // ==================================================================================
    //  1. INJURY — injury.create (formulario + store)
    // ==================================================================================

    /** @dataProvider rolesConInjuryCreate */
    public function test_roles_con_injury_create_abren_el_formulario(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('injury_reports.create'))->assertOk();
    }

    public function rolesConInjuryCreate(): array
    {
        return [['super-admin'], ['line-producer'], ['medic'], ['safety-officer'], ['crew']];
    }

    /** @dataProvider rolesSinInjuryCreate */
    public function test_roles_sin_injury_create_reciben_403_en_el_formulario(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('injury_reports.create'))->assertForbidden();
        // El store también queda cerrado (el middleware corta antes de validar).
        $this->post(route('injury_reports.store'), [])->assertForbidden();
    }

    public function rolesSinInjuryCreate(): array
    {
        return [['coordinator'], ['hod'], ['auditor']];
    }

    public function test_invitado_es_redirigido_a_login_en_injury(): void
    {
        $this->get(route('injury_reports.create'))->assertRedirect(route('login'));
        $this->get(route('injury_reports.index'))->assertRedirect(route('login'));
    }

    // ==================================================================================
    //  2. INJURY — injury.view (listado + ficha LITE)
    // ==================================================================================

    /** @dataProvider rolesConInjuryView */
    public function test_roles_con_injury_view_abren_el_listado(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('injury_reports.index'))->assertOk();
    }

    public function rolesConInjuryView(): array
    {
        return [['super-admin'], ['line-producer'], ['coordinator'], ['hod'], ['medic'], ['safety-officer'], ['auditor']];
    }

    public function test_crew_no_puede_ver_el_listado_de_injury(): void
    {
        // crew tiene injury.create pero NO injury.view.
        $this->actingAsRole('crew');
        $this->get(route('injury_reports.index'))->assertForbidden();
    }

    public function test_crew_no_puede_ver_la_ficha_lite_de_injury(): void
    {
        // GUARDA de comportamiento: la ficha LITE se cierra por permiso de MÓDULO (injury.view),
        // NO por propiedad. crew reporta pero NO revisa: aun su propio reporte le da 403.
        $injury = $this->makeSealedInjury();
        $this->actingAsRole('crew');
        $this->get(route('injury_reports.show', $injury->id))->assertForbidden();
    }

    // ==================================================================================
    //  3. INJURY DOS SALIDAS — LITE (injury.view) vs COMPLETA (viewMedical)
    // ==================================================================================

    /** @dataProvider rolesConInjuryView */
    public function test_la_ficha_lite_se_abre_con_solo_injury_view(string $role): void
    {
        $injury = $this->makeSealedInjury();
        $this->actingAsRole($role);
        $this->get(route('injury_reports.show', $injury->id))->assertOk();
    }

    /** @dataProvider rolesConViewMedical */
    public function test_expediente_completo_se_abre_para_roles_con_view_medical(string $role): void
    {
        $injury = $this->makeSealedInjury();
        $this->actingAsRole($role);
        $this->get(route('injury_reports.show_complete', $injury->id))->assertOk();
    }

    public function rolesConViewMedical(): array
    {
        // viewMedical: super-admin (Gate::before), line-producer/safety-officer (hazards.manage),
        // medic (isMedic). Ninguno es el capturador del injury de prueba.
        return [['super-admin'], ['line-producer'], ['safety-officer'], ['medic']];
    }

    /** @dataProvider rolesSinViewMedical */
    public function test_expediente_completo_da_403_a_roles_con_injury_view_pero_sin_view_medical(string $role): void
    {
        // coordinator/hod/auditor pasan el permiso de módulo (injury.view → la LITE abre)
        // pero NO viewMedical → la COMPLETA (silo clínico) les da 403, no se sirve ni en blanco.
        $injury = $this->makeSealedInjury();
        $this->actingAsRole($role);
        $this->get(route('injury_reports.show', $injury->id))->assertOk();            // LITE ok
        $this->get(route('injury_reports.show_complete', $injury->id))->assertForbidden(); // COMPLETA no
    }

    public function rolesSinViewMedical(): array
    {
        return [['coordinator'], ['hod'], ['auditor']];
    }

    public function test_el_capturador_no_privilegiado_ve_su_propio_expediente_completo(): void
    {
        // Un injury capturado por ESTE coordinador: la policy viewMedical concede al autor
        // (created_by_id) aunque su rol no tenga hazards.manage ni sea médico.
        $coord  = $this->makeUser('coordinator');
        $injury = InjuryReport::create([
            'name'          => 'Propio',
            'what_happened' => 'x',
            'created_by_id' => $coord->id,
        ]);
        $injury->refresh();
        $injury->signDocument($coord);

        $this->actingAs($coord);
        $this->get(route('injury_reports.show_complete', $injury->id))->assertOk();
    }

    // ==================================================================================
    //  4. INJURY — la MATRIZ 5×5 (rejilla) NO se imprime en NINGUNA salida (item 5).
    // ==================================================================================

    public function test_la_rejilla_5x5_no_aparece_ni_en_la_lite_ni_en_la_completa(): void
    {
        // El componente de rejilla (_risk-matrix → clase `rmx-grid`) vive SÓLO en el FORM de
        // captura y en el Scouting; el documento legal muestra el RESULTADO + su lectura, no la
        // rejilla. Guarda contra que alguien incluya la matriz completa en el PDF público.
        $injury = $this->makeSealedInjury();

        $this->actingAsRole('safety-officer'); // pasa injury.view Y viewMedical (ambas salidas)
        $this->get(route('injury_reports.show', $injury->id))
            ->assertOk()
            ->assertDontSee('rmx-grid', false)   // LITE sin rejilla
            ->assertSee('Moderado');             // pero SÍ la lectura del eje (Prob C)
        $this->get(route('injury_reports.show_complete', $injury->id))
            ->assertOk()
            ->assertDontSee('rmx-grid', false);  // COMPLETA tampoco lleva rejilla
    }

    // ==================================================================================
    //  5. HAZARD (acto inseguro) — create / view / manage
    // ==================================================================================

    /** @dataProvider rolesConHazardsCreate */
    public function test_roles_con_hazards_create_abren_el_form_de_acto_inseguro(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('hazard_notifications.create'))->assertOk();
    }

    public function rolesConHazardsCreate(): array
    {
        return [['super-admin'], ['line-producer'], ['medic'], ['safety-officer'], ['crew']];
    }

    /** @dataProvider rolesSinHazardsCreate */
    public function test_roles_sin_hazards_create_reciben_403_en_el_form(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('hazard_notifications.create'))->assertForbidden();
        $this->post(route('hazard_notifications.store'), [])->assertForbidden();
    }

    public function rolesSinHazardsCreate(): array
    {
        return [['coordinator'], ['hod'], ['auditor']];
    }

    /** @dataProvider rolesConHazardsView */
    public function test_roles_con_hazards_view_abren_el_listado(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('hazard_notifications.index'))->assertOk();
    }

    public function rolesConHazardsView(): array
    {
        return [['super-admin'], ['line-producer'], ['coordinator'], ['hod'], ['safety-officer'], ['auditor']];
    }

    public function test_medic_y_crew_no_tienen_hazards_view(): void
    {
        // QUIRK REAL de la matriz: `medic` gestiona (hazards.manage) pero NO tiene hazards.view,
        // así que el LISTADO/FICHA de actos le da 403 aunque pueda cambiar su estado. crew tampoco.
        foreach (['medic', 'crew'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('hazard_notifications.index'))->assertForbidden();
        }
    }

    /** @dataProvider rolesSinHazardsManage */
    public function test_roles_sin_hazards_manage_no_cambian_el_estado(string $role): void
    {
        $haz = hazardnotification::create([
            'production_name'               => 'Demo',
            'description_hazard_unsafe_act' => 'Cable expuesto',
        ]);
        $this->actingAsRole($role);
        $this->post(route('hazard_notifications.status', $haz->id), ['action_status' => 'En proceso'])
            ->assertForbidden();
    }

    public function rolesSinHazardsManage(): array
    {
        return [['coordinator'], ['hod'], ['crew'], ['auditor']];
    }

    // ==================================================================================
    //  6. UNSAFE (condición insegura) — mismos permisos hazards.* que el acto
    // ==================================================================================

    public function test_unsafe_create_view_y_manage_respetan_los_mismos_permisos(): void
    {
        $cond = unsafecond::create([
            'production_name'         => 'Demo',
            'name_loc'               => 'Set 1',
            'description_unsafe_cond' => 'Piso mojado sin señal',
        ]);

        // create: safety-officer sí, coordinator no.
        $this->actingAsRole('safety-officer');
        $this->get(route('unsafenotifications.create'))->assertOk();
        $this->actingAsRole('coordinator');
        $this->get(route('unsafenotifications.create'))->assertForbidden();

        // view: coordinator sí (hazards.view), crew no.
        $this->actingAsRole('coordinator');
        $this->get(route('unsafenotifications.index'))->assertOk();
        $this->get(route('unsafenotifications.show', $cond->id))->assertOk();
        $this->actingAsRole('crew');
        $this->get(route('unsafenotifications.index'))->assertForbidden();

        // manage (updateStatus): coordinator no (sin hazards.manage), safety-officer sí.
        $this->actingAsRole('coordinator');
        $this->post(route('unsafenotifications.status', $cond->id), ['action_status' => 'En proceso'])
            ->assertForbidden();
    }
}
