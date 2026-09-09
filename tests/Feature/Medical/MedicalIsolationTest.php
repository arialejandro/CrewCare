<?php

namespace Tests\Feature\Medical;

use App\Models\cmedic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * QA — VERTICAL MÉDICO + AISLAMIENTO CLÍNICO.
 *
 * El expediente clínico es el dato más sensible de la app: hay PII clínica (diagnóstico,
 * medicamento, nota privada) y el aislamiento ENTRE MÉDICOS es un requisito duro. Esta suite:
 *
 *  1. PUERTAS de permiso (medical.view / medical.create / medical.materials): rol con permiso
 *     entra, rol sin permiso recibe 403 (o redirect a login para el invitado).
 *  2. AISLAMIENTO POR PROPIEDAD (cmedic::scopeVisibleTo): un médico común ve SÓLO sus consultas;
 *     el KEY MEDIC (medical.consolidate) y los observadores con medical.view ven TODAS.
 *  3. PERSISTENCIA: el endpoint real de alta persiste en `cmedic` con el autor correcto.
 *  4. XOR de paciente a nivel BD (trigger SIGNAL 45000): exactamente uno de id_user /
 *     lite_patient_id.
 *  5. CARACTERIZACIÓN de una FUGA REAL detectada en la bitácora semanal (ver docblock del test).
 *
 * Roles sembrados por el DatabaseSeeder de fábrica (RolesAndPermissionsSeeder):
 *   medical.view       → super-admin, line-producer, coordinator, hod, medic
 *   medical.create     → super-admin, medic
 *   medical.materials  → super-admin, line-producer, medic, safety-officer
 *   medical.consolidate→ NADIE por rol (se otorga DIRECTO a la persona / super-admin por Gate::before)
 *   medical.view NO lo tienen crew ni auditor (el auditor está excluido a propósito).
 */
class MedicalIsolationTest extends QaTestCase
{
    /**
     * Crea una consulta de crew persistida (pasa el trigger XOR: id_user set, lite null).
     * medication/observations son NOT NULL en `cmedic`; se pasan explícitos.
     */
    private function makeConsult(int $patientId, int $medicId, string $diagnosis, string $observations): cmedic
    {
        return cmedic::create([
            'id_user'           => $patientId,
            'lite_patient_id'   => null,
            'created_by_id'     => $medicId,
            'consultation_date' => now()->toDateString(),
            'diagnosis'         => $diagnosis,
            'medication'        => '',
            'observations'      => $observations,
        ]);
    }

    // =====================================================================================
    //  1. PUERTAS DE PERMISO
    // =====================================================================================

    /** @dataProvider rolesConMedicalView */
    public function test_roles_con_medical_view_abren_el_listado_medico(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('medicocrud'))->assertOk();
    }

    public static function rolesConMedicalView(): array
    {
        return [
            ['super-admin'], ['line-producer'], ['coordinator'], ['hod'], ['medic'],
        ];
    }

    /** @dataProvider rolesSinMedicalView */
    public function test_roles_sin_medical_view_reciben_403_en_el_listado_medico(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('medicocrud'))->assertForbidden();
    }

    public static function rolesSinMedicalView(): array
    {
        // crew y auditor NO tienen medical.view (expediente clínico = dato sensible).
        return [['crew'], ['auditor'], ['safety-officer']];
    }

    public function test_invitado_es_redirigido_a_login_en_el_listado_medico(): void
    {
        $this->get(route('medicocrud'))->assertRedirect(route('login'));
    }

    public function test_solo_super_admin_y_medic_abren_el_form_de_alta_de_consulta(): void
    {
        $patient = $this->makeUser('crew');

        // medic: permission medical.create + isClinician → 200.
        $this->actingAsRole('medic');
        $this->get(route('cmedica.create', $patient->id))->assertOk();
    }

    public function test_roles_sin_medical_create_no_abren_el_form_de_alta(): void
    {
        $patient = $this->makeUser('crew');

        // coordinator TIENE medical.view pero NO medical.create → la ruta lo corta con 403.
        $this->actingAsRole('coordinator');
        $this->get(route('cmedica.create', $patient->id))->assertForbidden();

        // crew no tiene ninguno → 403.
        $this->actingAsRole('crew');
        $this->get(route('cmedica.create', $patient->id))->assertForbidden();
    }

    public function test_super_admin_no_medico_no_puede_figurar_como_autor_clinico(): void
    {
        // super-admin TIENE medical.create (pasa la ruta), pero NO es isClinician → el
        // controlador lo rebota (no puede quedar como autor de una valoración clínica).
        $patient = $this->makeUser('crew');
        $this->actingAsRole('super-admin');
        $this->get(route('cmedica.create', $patient->id))
            ->assertRedirect('/medicocrud'); // create() redirige, no aborta, para el no-clínico
    }

    /** @dataProvider rolesConMaterials */
    public function test_roles_con_medical_materials_abren_el_conteo(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('medical.materials'))->assertOk();
    }

    public static function rolesConMaterials(): array
    {
        return [['super-admin'], ['line-producer'], ['medic'], ['safety-officer']];
    }

    public function test_roles_sin_medical_materials_reciben_403_en_el_conteo(): void
    {
        // coordinator y hod tienen medical.view pero NO medical.materials.
        $this->actingAsRole('coordinator');
        $this->get(route('medical.materials'))->assertForbidden();

        $this->actingAsRole('hod');
        $this->get(route('medical.materials'))->assertForbidden();

        $this->actingAsRole('crew');
        $this->get(route('medical.materials'))->assertForbidden();
    }

    // =====================================================================================
    //  2. AISLAMIENTO CLÍNICO POR PROPIEDAD (cmedic::scopeVisibleTo) — LO CENTRAL
    // =====================================================================================

    public function test_medico_comun_ve_solo_sus_consultas_no_las_de_otro_medico(): void
    {
        $patient = $this->makeUser('crew');
        $medicA  = $this->makeUser('medic');
        $medicB  = $this->makeUser('medic');

        $consultA = $this->makeConsult($patient->id, $medicA->id, 'Dx de A', 'Nota privada de A');
        $consultB = $this->makeConsult($patient->id, $medicB->id, 'Dx de B', 'Nota privada de B');

        // Médico A: el scope de propiedad debe devolver SÓLO lo suyo.
        $visiblesParaA = cmedic::visibleTo($medicA)->where('id_user', $patient->id)
            ->pluck('id_cmedic')->all();

        $this->assertContains($consultA->id_cmedic, $visiblesParaA, 'A debe ver su propia consulta');
        $this->assertNotContains(
            $consultB->id_cmedic,
            $visiblesParaA,
            'FUGA CLÍNICA: el médico A ve la consulta del médico B'
        );
        $this->assertCount(1, $visiblesParaA, 'A sólo debe ver 1 consulta (la suya)');

        // Simétrico: B tampoco ve la de A.
        $visiblesParaB = cmedic::visibleTo($medicB)->where('id_user', $patient->id)
            ->pluck('id_cmedic')->all();
        $this->assertContains($consultB->id_cmedic, $visiblesParaB);
        $this->assertNotContains($consultA->id_cmedic, $visiblesParaB);
    }

    public function test_key_medic_consolidate_ve_las_consultas_de_todos_los_medicos(): void
    {
        $patient = $this->makeUser('crew');
        $medicA  = $this->makeUser('medic');
        $medicB  = $this->makeUser('medic');
        // KEY MEDIC: médico + permiso DIRECTO medical.consolidate (así se otorga en /rolescrud).
        $keyMedic = $this->makeUser('medic');
        $keyMedic->givePermissionTo('medical.consolidate');

        $consultA = $this->makeConsult($patient->id, $medicA->id, 'Dx de A', 'Nota A');
        $consultB = $this->makeConsult($patient->id, $medicB->id, 'Dx de B', 'Nota B');

        $visibles = cmedic::visibleTo($keyMedic)->where('id_user', $patient->id)
            ->pluck('id_cmedic')->all();

        $this->assertContains($consultA->id_cmedic, $visibles);
        $this->assertContains($consultB->id_cmedic, $visibles);
        $this->assertCount(2, $visibles, 'El key medic consolida: ve AMBAS');
    }

    /** @dataProvider observadoresConMedicalView */
    public function test_observador_no_clinico_con_medical_view_ve_todas_las_consultas(string $role): void
    {
        $patient = $this->makeUser('crew');
        $medicA  = $this->makeUser('medic');
        $medicB  = $this->makeUser('medic');

        $consultA = $this->makeConsult($patient->id, $medicA->id, 'Dx de A', 'Nota A');
        $consultB = $this->makeConsult($patient->id, $medicB->id, 'Dx de B', 'Nota B');

        $observer = $this->makeUser($role);

        $visibles = cmedic::visibleTo($observer)->where('id_user', $patient->id)
            ->pluck('id_cmedic')->all();

        // Los observadores (coordinador/line-producer/super-admin) no atienden: no hay secreto
        // médico-a-médico entre ellos → ven todas (scopeVisibleTo devuelve la query intacta).
        $this->assertContains($consultA->id_cmedic, $visibles);
        $this->assertContains($consultB->id_cmedic, $visibles);
    }

    public static function observadoresConMedicalView(): array
    {
        return [['super-admin'], ['line-producer'], ['coordinator']];
    }

    public function test_scope_sin_visor_es_restrictivo(): void
    {
        $patient = $this->makeUser('crew');
        $medicA  = $this->makeUser('medic');
        $this->makeConsult($patient->id, $medicA->id, 'Dx', 'Nota');

        // viewer null → err-restrictive (1=0): no ve nada.
        $this->assertCount(0, cmedic::visibleTo(null)->get()->all());
    }

    public function test_historialWR_no_filtra_por_autor_pero_aisla_las_consultas_del_medico(): void
    {
        // Verificación END-TO-END por HTTP del aislamiento en el expediente individual:
        // el médico A abre el historial del paciente; la vista NO debe pintar el Dx del médico B.
        $patient = $this->makeUser('crew');
        $medicA  = $this->makeUser('medic');
        $medicB  = $this->makeUser('medic');

        $this->makeConsult($patient->id, $medicA->id, 'DIAGNOSTICO-EXCLUSIVO-DE-A', 'Nota A');
        $this->makeConsult($patient->id, $medicB->id, 'DIAGNOSTICO-EXCLUSIVO-DE-B', 'Nota B');

        $this->actingAs($medicA);
        $resp = $this->get(route('historialwr', $patient->id));
        $resp->assertOk();
        $resp->assertSee('DIAGNOSTICO-EXCLUSIVO-DE-A');
        $resp->assertDontSee('DIAGNOSTICO-EXCLUSIVO-DE-B'); // aislamiento medic-a-medic
    }

    public function test_consultaDoc_oculta_la_nota_privada_a_un_medico_ajeno(): void
    {
        // El documento sellado de UNA consulta de B: un médico A puede ABRIRLO (continuidad de
        // atención: ve dx/medicamento), pero la NOTA PRIVADA (observations) queda oculta.
        $patient = $this->makeUser('crew');
        $medicA  = $this->makeUser('medic');
        $medicB  = $this->makeUser('medic');

        $consultB = $this->makeConsult($patient->id, $medicB->id, 'DxCompartidoB', 'NOTA-SECRETA-DE-B-xyz');

        $this->actingAs($medicA);
        $resp = $this->get(route('consulta.documento', $consultB->id_cmedic));
        $resp->assertOk();
        $resp->assertDontSee('NOTA-SECRETA-DE-B-xyz'); // la nota privada NO se filtra a A

        // El dueño (B) sí la ve.
        $this->actingAs($medicB);
        $this->get(route('consulta.documento', $consultB->id_cmedic))
            ->assertOk()
            ->assertSee('NOTA-SECRETA-DE-B-xyz');
    }

    // =====================================================================================
    //  3. PERSISTENCIA VÍA ENDPOINT REAL
    // =====================================================================================

    public function test_el_alta_via_endpoint_persiste_con_el_autor_correcto(): void
    {
        $patient = $this->makeUser('crew');
        $medic   = $this->actingAsRole('medic');

        $resp = $this->post(route('cmedica.store', $patient->id), [
            'diagnosis'    => 'Cefalea tensional QA',
            'observations' => 'Reposo e hidratación',
            'aditional'    => null,
        ]);

        $resp->assertRedirect('/medicocrud');
        $resp->assertSessionHas('success');

        $this->assertDatabaseHas('cmedic', [
            'id_user'       => $patient->id,
            'created_by_id' => $medic->id,       // autofirma correcta
            'diagnosis'     => 'Cefalea tensional QA',
            'lite_patient_id' => null,           // consulta de crew (XOR)
        ]);
    }

    public function test_el_alta_es_rechazada_si_falta_el_diagnostico(): void
    {
        $patient = $this->makeUser('crew');
        $this->actingAsRole('medic');

        $resp = $this->from(route('cmedica.create', $patient->id))
            ->post(route('cmedica.store', $patient->id), [
                'observations' => 'sin diagnóstico',
            ]);

        $resp->assertSessionHasErrors('diagnosis');
        $this->assertDatabaseCount('cmedic', 0);
    }

    // =====================================================================================
    //  4. XOR DE PACIENTE A NIVEL BD (trigger SIGNAL 45000)
    // =====================================================================================

    public function test_insert_con_ambos_id_user_y_lite_patient_id_falla_por_el_trigger(): void
    {
        $patient = $this->makeUser('crew');
        // lite_patients existe (delta aplicado en fábrica). Creamos un lite mínimo para el FK.
        $liteId = DB::table('lite_patients')->insertGetId([
            'full_name'  => 'Extra QA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessageMatches('/exactamente uno de id_user/');

        // Ambos presentes → (false)=(false) → SIGNAL 45000.
        DB::table('cmedic')->insert([
            'id_user'         => $patient->id,
            'lite_patient_id' => $liteId,
            'diagnosis'       => 'x',
            'medication'      => '',
            'observations'    => '',
            'created_at'      => now(),
        ]);
    }

    public function test_insert_sin_ninguno_de_los_dos_falla_por_el_trigger(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessageMatches('/exactamente uno de id_user/');

        // Ninguno presente → (true)=(true) → SIGNAL 45000.
        DB::table('cmedic')->insert([
            'id_user'         => null,
            'lite_patient_id' => null,
            'diagnosis'       => 'x',
            'medication'      => '',
            'observations'    => '',
            'created_at'      => now(),
        ]);
    }

    public function test_insert_con_exactamente_uno_pasa_el_trigger(): void
    {
        $patient = $this->makeUser('crew');

        // Sólo id_user → XOR válido, el insert prospera.
        $id = DB::table('cmedic')->insertGetId([
            'id_user'         => $patient->id,
            'lite_patient_id' => null,
            'diagnosis'       => 'ok',
            'medication'      => '',
            'observations'    => '',
            'created_at'      => now(),
        ]);

        $this->assertDatabaseHas('cmedic', ['id_cmedic' => $id, 'id_user' => $patient->id]);
    }

    // =====================================================================================
    //  5. BITÁCORA CONSOLIDADA — sólo KEY MEDIC (BUG-01 cerrado, opción B) + grant del panel (BUG-04)
    // =====================================================================================

    /**
     * BUG-01 CERRADO (opción B, 2026-08-11): la bitácora consolida las consultas de TODOS los
     * médicos con su nota privada `observations`, así que VER y EMITIR la bitácora dejan de estar
     * al alcance de `medical.view` y exigen `medical.consolidate` (KEY MEDIC; el super-admin pasa
     * por Gate::before). El super-admin lo otorga/quita desde /rolescrud a médico, safety-officer o
     * producción. Estas guardas quedan ACTIVAS contra regresión de la fuga.
     */
    public function test_bitacora_solo_la_ve_el_key_medic_no_un_medico_comun(): void
    {
        $medicComun = $this->makeUser('medic'); // solo medical.view
        $this->actingAs($medicComun);
        $this->get(route('medical.bitacora'))->assertForbidden();

        $keyMedic = $this->makeUser('medic');
        $keyMedic->givePermissionTo('medical.consolidate');
        $this->actingAs($keyMedic);
        $this->get(route('medical.bitacora'))->assertOk();
    }

    public function test_pdf_de_bitacora_solo_lo_emite_el_key_medic(): void
    {
        $medicComun = $this->makeUser('medic');
        $this->actingAs($medicComun);
        $this->get(route('medical.bitacora.pdf'))->assertForbidden();

        $keyMedic = $this->makeUser('medic');
        $keyMedic->givePermissionTo('medical.consolidate');
        $this->actingAs($keyMedic);
        $this->get(route('medical.bitacora.pdf'))->assertOk();
    }

    /**
     * BUG-04 + petición del owner: el super-admin otorga `medical.consolidate` DESDE EL PANEL
     * (/rolescrud) a un safety-officer (o producción), y el grant honra ESE permiso — antes el
     * controlador hardcodeaba medical.view, así que "hacer key medic" no funcionaba. Tras otorgarlo,
     * el safety-officer ve la bitácora.
     */
    public function test_super_admin_otorga_consolidacion_a_un_safety_officer_desde_el_panel(): void
    {
        $safety = $this->makeUser('safety-officer');

        $this->actingAs($safety);
        $this->get(route('medical.bitacora'))->assertForbidden(); // aún no

        $this->actingAsRole('super-admin');
        $this->post(route('roles.medical.grant', $safety->id), ['permission' => 'medical.consolidate'])
            ->assertRedirect();

        // El grant otorgó medical.consolidate (no medical.view por error) → BUG-04 blindado.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $safety = $safety->fresh();
        $this->assertTrue($safety->hasDirectPermission('medical.consolidate'));
        $this->assertFalse($safety->hasDirectPermission('medical.view'));

        $this->actingAs($safety);
        $this->get(route('medical.bitacora'))->assertOk();
    }
}
