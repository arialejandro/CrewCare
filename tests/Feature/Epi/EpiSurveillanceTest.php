<?php

namespace Tests\Feature\Epi;

use App\Models\DailyReport;
use App\Models\OutbreakStudy;
use App\Models\cmedic;
use App\Support\CurrentProduction;
use App\Support\EpiSurveillance;
use App\Support\ProductionCalendar;
use App\Support\SealVerifier;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * QA — VERTICAL VIGILANCIA EPIDEMIOLÓGICA (delta #45).
 *
 * El panel es un AGREGADOR SILENCIOSO: lee las consultas selladas y devuelve CONTEOS por día de
 * rodaje. La promesa dura del módulo —y lo más valioso de esta suite— es que NUNCA expone un
 * nombre de persona ni el texto del diagnóstico: el diagnóstico se usa para resolver el grupo y se
 * descarta dentro de EpiSurveillance. El estudio de brote (documento clínico firmado) SÍ puede
 * llevar nombres, pero el VERIFICADOR PÚBLICO ('brote') sigue siendo un acuse sin PII.
 *
 * Cubre:
 *  1. RBAC del panel: solo epi.view (safety-officer, medic, super-admin) entra; el resto 403.
 *  2. INVARIANTE CLAVE: el panel y el agregador NUNCA filtran nombre ni diagnóstico, ni siquiera
 *     cuando la consulta SÍ se cuenta en el agregado.
 *  3. El estudio de brote es un documento CLÍNICO: solo el médico lo emite (safety con epi.view NO).
 *  4. Emisión → persiste + sella; verificador 'brote' íntegro; tamper → ALTERADO.
 *  5. Verificador público de 'brote' sessionless y sin PII (no filtra person_description).
 *
 * epi.view por rol (EpiPermissionsSeeder): safety-officer, medic, super-admin.
 */
class EpiSurveillanceTest extends QaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // El calendario (ProductionCalendar) cachea fechas/ancla en estáticos que sobreviven entre
        // pruebas del mismo proceso. Se limpia al arrancar cada test para que shootDates() se
        // reconstruya con lo que ESTE test sembró y no con DSRs de un test ya revertido.
        ProductionCalendar::forget();
        CurrentProduction::forget();
    }

    /** DSR mínimo para un día de rodaje real (da fecha al eje + denominador). */
    private function seedDsr(string $date, ?int $crew = 40, string $loc = 'Set Central'): DailyReport
    {
        $dsr = DailyReport::create([
            'report_date'       => $date,
            'location_name'     => $loc,
            'crew_count'        => $crew,
            'executive_summary' => 'Jornada QA',
        ]);
        ProductionCalendar::forget();
        CurrentProduction::forget();

        return $dsr;
    }

    // =====================================================================================
    //  1. RBAC DEL PANEL (permission:epi.view)
    // =====================================================================================

    /** @dataProvider rolesConEpiView */
    public function test_roles_con_epi_view_abren_el_panel(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('epi.index'))->assertOk();
    }

    public static function rolesConEpiView(): array
    {
        return [['safety-officer'], ['medic'], ['super-admin']];
    }

    /** @dataProvider rolesSinEpiView */
    public function test_roles_sin_epi_view_reciben_403_en_el_panel(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('epi.index'))->assertForbidden();
    }

    public static function rolesSinEpiView(): array
    {
        // El panel lo comparten SOLO safety y médico; ningún permiso genérico lo abre.
        return [['line-producer'], ['coordinator'], ['hod'], ['crew'], ['auditor']];
    }

    public function test_invitado_es_redirigido_a_login_en_el_panel(): void
    {
        $this->get(route('epi.index'))->assertRedirect(route('login'));
    }

    // =====================================================================================
    //  2. INVARIANTE CLAVE — EL AGREGADO NUNCA FILTRA NOMBRE NI DIAGNÓSTICO
    // =====================================================================================

    /**
     * El corazón del módulo: con una consulta REAL dentro de la ventana (se cuenta en el agregado),
     * el panel debe mostrar el CONTEO pero NUNCA el nombre del paciente ni el texto del diagnóstico.
     */
    public function test_el_panel_agrega_la_consulta_pero_no_filtra_nombre_ni_diagnostico(): void
    {
        $hoy = now()->toDateString();
        $this->seedDsr($hoy, 42);

        $patient = $this->makeUser('crew', [
            'name'  => 'JuanNombreSecreto',
            'lname' => 'ApellidoConfidencialXZ',
        ]);
        $medic = $this->makeUser('medic');

        cmedic::create([
            'id_user'           => $patient->id,
            'created_by_id'     => $medic->id,
            'consultation_date' => $hoy,
            'diagnosis'         => 'DIAGNOSTICO-SECRETO-GASTROENTERITIS-QZ',
            'medication'        => '',
            'observations'      => 'NOTA-PRIVADA-DEL-MEDICO-QZ',
        ]);

        ProductionCalendar::forget();
        CurrentProduction::forget();

        // El agregado SÍ cuenta la consulta (la vigilancia funciona)...
        $data = EpiSurveillance::build([]);
        $this->assertGreaterThanOrEqual(1, $data['total_consults'],
            'La consulta dentro de la ventana debe entrar al agregado.');

        // ...pero el dataset del panel NO contiene el nombre ni el diagnóstico en NINGUNA rama.
        $blob = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('JuanNombreSecreto', $blob, 'FUGA PII: nombre en el agregado');
        $this->assertStringNotContainsString('ApellidoConfidencialXZ', $blob, 'FUGA PII: apellido en el agregado');
        $this->assertStringNotContainsString('DIAGNOSTICO-SECRETO', $blob, 'FUGA CLÍNICA: diagnóstico en el agregado');
        $this->assertStringNotContainsString('NOTA-PRIVADA', $blob, 'FUGA CLÍNICA: nota privada en el agregado');

        // Y la RESPUESTA HTTP del panel tampoco los pinta.
        $this->actingAsRole('safety-officer');
        $resp = $this->get(route('epi.index'));
        $resp->assertOk();
        $resp->assertDontSee('JuanNombreSecreto');
        $resp->assertDontSee('ApellidoConfidencialXZ');
        $resp->assertDontSee('DIAGNOSTICO-SECRETO-GASTROENTERITIS-QZ');
        $resp->assertDontSee('NOTA-PRIVADA-DEL-MEDICO-QZ');
    }

    /**
     * Defensa en profundidad: el LISTADO de estudios que carga el panel solo trae columnas de
     * cabecera (folio/título/fecha/uuid). El cuerpo nominal del estudio (person_description) no
     * entra al scope de la vista del panel agregado, aunque el estudio lo tenga.
     */
    public function test_el_listado_de_estudios_del_panel_no_arrastra_el_cuerpo_nominal(): void
    {
        $medic = $this->makeUser('medic');
        $study = OutbreakStudy::create([
            'title'              => 'Estudio QA',
            'case_definition'    => 'Definición operacional QA.',
            'person_description' => 'PACIENTE-NOMINAL-EN-EL-CUERPO-QZ',
            'is_active'          => 1,
        ]);
        $study->refresh();
        $study->signDocument($medic);

        $this->actingAsRole('safety-officer');
        $resp = $this->get(route('epi.index'));
        $resp->assertOk();
        $resp->assertSee($study->folio());                        // el folio sí (cabecera)
        $resp->assertDontSee('PACIENTE-NOMINAL-EN-EL-CUERPO-QZ');  // el cuerpo nominal no
    }

    // =====================================================================================
    //  3. EL ESTUDIO DE BROTE ES UN DOCUMENTO CLÍNICO — SOLO EL MÉDICO LO EMITE
    // =====================================================================================

    public function test_safety_con_epi_view_ve_el_panel_pero_no_puede_abrir_el_estudio_de_brote(): void
    {
        // El safety tiene epi.view (pasa la ruta) pero NO es clínico → assertClinician() lo corta.
        $this->actingAsRole('safety-officer');
        $this->get(route('epi.outbreak.create'))->assertForbidden();
    }

    public function test_safety_con_epi_view_no_puede_emitir_un_estudio_de_brote(): void
    {
        $this->actingAsRole('safety-officer');
        $resp = $this->post(route('epi.outbreak.store'), [
            'title'           => 'Intento de safety',
            'case_definition' => 'x',
        ]);
        $resp->assertForbidden();
        $this->assertSame(0, OutbreakStudy::count(), 'El safety NO debe poder emitir el estudio clínico.');
    }

    public function test_el_medico_abre_el_formulario_del_estudio(): void
    {
        $this->actingAsRole('medic');
        $this->get(route('epi.outbreak.create'))->assertOk();
    }

    // =====================================================================================
    //  4. EMISIÓN (médico) → PERSISTE + SELLA; verificador 'brote' íntegro; tamper → ALTERADO
    // =====================================================================================

    public function test_el_medico_emite_el_estudio_y_queda_sellado_e_integro(): void
    {
        $medic = $this->actingAsRole('medic');

        $resp = $this->post(route('epi.outbreak.store'), [
            'title'           => 'Brote gastrointestinal QA',
            'case_definition' => 'Dos o más casos con diarrea en 24 h en la misma locación.',
            'group_key'       => 'gastrointestinal',
        ]);

        $study = OutbreakStudy::latest('id')->first();
        $this->assertNotNull($study, 'El estudio de brote debe persistir.');
        $resp->assertRedirect(route('epi.outbreak.show', $study->uuid));
        $resp->assertSessionHas('success');

        // Autoría congelada + snapshot de conteos.
        $this->assertSame($medic->id, (int) $study->created_by_id);
        $this->assertIsArray($study->counts_snapshot);

        // Sellado e íntegro; el verificador público lo confirma.
        $this->assertTrue($study->signatures()->exists(), 'El estudio nace sellado.');
        $this->assertTrue($study->fresh()->verifyLatestSignature(), 'El sello debe verificar íntegro.');
        $this->assertSame('ok', SealVerifier::resolve('brote', $study->uuid)['verdict']);
        $this->assertSame('Estudio de brote', SealVerifier::resolve('brote', $study->uuid)['type_label']);
    }

    public function test_tamper_sobre_el_estudio_lo_marca_alterado(): void
    {
        $this->actingAsRole('medic');
        $this->post(route('epi.outbreak.store'), [
            'title'           => 'Brote QA',
            'case_definition' => 'Definición operacional original.',
        ]);
        $study = OutbreakStudy::latest('id')->first();
        $this->assertSame('ok', SealVerifier::resolve('brote', $study->uuid)['verdict']);

        // Editar un campo FIRMADO (case_definition) por fuera rompe el hash → ALTERADO.
        DB::table('outbreak_studies')->where('id', $study->id)
            ->update(['case_definition' => 'Definición reescrita a mano.']);

        $this->assertSame('altered', SealVerifier::resolve('brote', $study->uuid)['verdict']);
    }

    public function test_el_estudio_requiere_titulo_y_definicion_de_caso(): void
    {
        $this->actingAsRole('medic');
        $resp = $this->from(route('epi.outbreak.create'))
            ->post(route('epi.outbreak.store'), ['group_key' => 'respiratorio']);

        $resp->assertSessionHasErrors(['title', 'case_definition']);
        $this->assertSame(0, OutbreakStudy::count());
    }

    // =====================================================================================
    //  5. VERIFICADOR PÚBLICO 'brote' — SESSIONLESS Y SIN PII
    // =====================================================================================

    public function test_verificador_publico_de_brote_es_sessionless_y_no_filtra_pii(): void
    {
        // El estudio SÍ lleva nombre (es un documento clínico firmado), pero el ACUSE PÚBLICO no.
        $medic = $this->makeUser('medic');
        $study = OutbreakStudy::create([
            'title'              => 'Brote con paciente nominal',
            'case_definition'    => 'Definición QA.',
            'person_description' => 'PERSONA-IDENTIFICABLE-EN-EL-DOC-QZ',
            'medic_name'         => 'Dra. NombreDelMedicoQZ',
            'is_active'          => 1,
        ]);
        $study->refresh();
        $study->signDocument($medic);

        // SIN sesión (sessionless por diseño): responde 200 con el acuse.
        $resp = $this->get('/verificar/brote/' . $study->uuid);
        $resp->assertOk();
        $resp->assertSee($study->folio());               // integridad (no PII)
        $resp->assertSee('Estudio de brote');            // etiqueta genérica
        $resp->assertDontSee('PERSONA-IDENTIFICABLE-EN-EL-DOC-QZ');
        $resp->assertDontSee('Dra. NombreDelMedicoQZ');

        // El DTO que sale de SealVerifier no acarrea el cuerpo del estudio.
        $dto = SealVerifier::resolve('brote', $study->uuid);
        $blob = json_encode($dto, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('PERSONA-IDENTIFICABLE', $blob);
        $this->assertStringNotContainsString('NombreDelMedicoQZ', $blob);
    }
}
