<?php

namespace Tests\Feature\Permit;

use App\Models\ToolInspection;
use App\Support\InspectionVerdict;

/**
 * VEREDICTO DERIVADO de la inspección de herramienta.
 *
 * El veredicto (APTA / PARO / ACTIVIDAD NO EJECUTABLE) se DERIVA del checklist, nunca se
 * teclea: la calculadora lo computa de `is_gate` + `outcome_if_fail` de los puntos que cayeron.
 *
 * REGLA DURA — "Gate caído nunca da APTA": si UN gate falla, el veredicto JAMÁS es APTA,
 * ni siquiera si su `outcome_if_fail` viene NULL o con un valor desconocido (dato futuro mal
 * capturado). Se prueba en los DOS niveles: la función pura y el flujo real del controlador
 * (que usa puntos AUTORITATIVOS del servidor, no confía en el cliente).
 */
class InspectionVerdictTest extends PermitVerticalTestCase
{
    // ======================================================================
    // NIVEL 1 · función pura InspectionVerdict::compute()
    // ======================================================================

    public function test_todos_cumplen_da_apta(): void
    {
        $r = InspectionVerdict::compute([
            ['is_gate' => true,  'outcome_if_fail' => 'reemplazo', 'answer' => true],
            ['is_gate' => false, 'outcome_if_fail' => null,        'answer' => true],
        ]);
        $this->assertSame(ToolInspection::VERDICT_APTA, $r['verdict']);
        $this->assertNull($r['resolution_path']);
    }

    public function test_gate_reemplazo_caido_da_paro_via_reemplazo(): void
    {
        $r = InspectionVerdict::compute([
            ['is_gate' => true, 'outcome_if_fail' => ToolInspection::PATH_REPLACE, 'answer' => false],
        ]);
        $this->assertSame(ToolInspection::VERDICT_PARO, $r['verdict']);
        $this->assertSame(ToolInspection::PATH_REPLACE, $r['resolution_path']);
    }

    public function test_reemplazo_manda_sobre_correccion_el_mismo_dia(): void
    {
        $r = InspectionVerdict::compute([
            ['is_gate' => true, 'outcome_if_fail' => ToolInspection::PATH_SAME_DAY, 'answer' => false],
            ['is_gate' => true, 'outcome_if_fail' => ToolInspection::PATH_REPLACE,  'answer' => false],
        ]);
        $this->assertSame(ToolInspection::VERDICT_PARO, $r['verdict']);
        $this->assertSame(ToolInspection::PATH_REPLACE, $r['resolution_path'], 'Fuera de servicio manda sobre corrección.');
    }

    /** Un no-gate que cae es OBSERVACIÓN: sigue APTA (con condicionado registrado). */
    public function test_no_gate_caido_sigue_apta_con_observacion(): void
    {
        $r = InspectionVerdict::compute([
            ['is_gate' => true,  'outcome_if_fail' => 'reemplazo', 'answer' => true],
            ['is_gate' => false, 'outcome_if_fail' => null,        'answer' => false],
        ]);
        $this->assertSame(ToolInspection::VERDICT_APTA, $r['verdict']);
        $this->assertCount(1, $r['condicionado_failed']);
    }

    // ---- GUARDAS DE LA REGLA DURA (fail-safe): gate caído JAMÁS da APTA ----

    public function test_bug_guard_gate_con_outcome_null_caido_no_da_apta(): void
    {
        $r = InspectionVerdict::compute([
            ['is_gate' => true, 'outcome_if_fail' => null, 'answer' => false],
        ]);
        $this->assertNotSame(
            ToolInspection::VERDICT_APTA, $r['verdict'],
            'FALLO DE SEGURIDAD: un gate caído con outcome NULL dio APTA.'
        );
        $this->assertSame(ToolInspection::VERDICT_NO_EXEC, $r['verdict']);
    }

    public function test_bug_guard_gate_con_outcome_desconocido_caido_no_da_apta(): void
    {
        $r = InspectionVerdict::compute([
            ['is_gate' => true, 'outcome_if_fail' => 'valor_futuro_no_mapeado', 'answer' => false],
        ]);
        $this->assertNotSame(
            ToolInspection::VERDICT_APTA, $r['verdict'],
            'FALLO DE SEGURIDAD: un gate caído con outcome desconocido dio APTA.'
        );
        $this->assertSame(ToolInspection::VERDICT_NO_EXEC, $r['verdict']);
    }

    /** El fail-safe NUNCA pierde la evidencia: el gate caído queda listado. */
    public function test_gate_caido_desconocido_conserva_evidencia(): void
    {
        $r = InspectionVerdict::compute([
            ['is_gate' => true, 'outcome_if_fail' => 'actividad_no_ejecutable', 'answer' => false],
        ]);
        $this->assertSame(ToolInspection::VERDICT_NO_EXEC, $r['verdict']);
        $this->assertNotEmpty($r['failed_gates']);
    }

    // ======================================================================
    // NIVEL 2 · flujo real del controlador (puntos autoritativos del servidor)
    // ======================================================================

    public function test_checklist_limpio_persiste_acta_apta_y_sellada(): void
    {
        $tool = $this->makeToolWithPoints([
            ['gate' => true,  'outcome' => 'reemplazo'],
            ['gate' => false, 'outcome' => null],
        ]);
        $this->actingAsRole('safety-officer');

        $resp = $this->post(route('tools.inspect.store', $tool->id), $this->inspectionStorePayload($tool));
        $resp->assertStatus(302);

        $insp = ToolInspection::latest('id')->first();
        $this->assertNotNull($insp, 'La inspección debe persistir.');
        $this->assertSame(ToolInspection::VERDICT_APTA, $insp->verdict);
        $this->assertTrue($insp->signatures()->exists(), 'El acta debe quedar sellada.');
        $this->assertTrue($insp->fresh()->verifyLatestSignature(), 'El sello debe verificar íntegro.');
    }

    public function test_gate_caido_persiste_paro_no_apta(): void
    {
        $tool = $this->makeToolWithPoints([
            ['gate' => true, 'outcome' => 'reemplazo'],
        ]);
        $codes = $this->toolPointCodes($tool);
        $this->actingAsRole('safety-officer');

        $resp = $this->post(route('tools.inspect.store', $tool->id),
            $this->inspectionStorePayload($tool, [$codes[0] => 'fail']));
        $resp->assertStatus(302);

        $insp = ToolInspection::latest('id')->first();
        $this->assertSame(ToolInspection::VERDICT_PARO, $insp->verdict);
        $this->assertTrue($insp->isParo());
        $this->assertSame(ToolInspection::PATH_REPLACE, $insp->resolution_path);
    }

    /**
     * ⭐ BUG GUARD ESTRELLA (a través del controlador): un gate con `outcome_if_fail`
     * DESCONOCIDO que se responde 'fail' NO puede persistir un acta APTA. El veredicto lo
     * deriva el servidor de los puntos de la BD, así que el cliente no puede forzarlo.
     */
    public function test_bug_guard_controlador_gate_desconocido_caido_nunca_apta(): void
    {
        $tool = $this->makeToolWithPoints([
            ['gate' => true, 'outcome' => 'outcome_que_no_existe'],
        ]);
        $codes = $this->toolPointCodes($tool);
        $this->actingAsRole('safety-officer');

        $this->post(route('tools.inspect.store', $tool->id),
            $this->inspectionStorePayload($tool, [$codes[0] => 'fail']))->assertStatus(302);

        $insp = ToolInspection::latest('id')->first();
        $this->assertNotNull($insp);
        $this->assertNotSame(
            ToolInspection::VERDICT_APTA, $insp->verdict,
            'FALLO DE SEGURIDAD: gate caído con outcome desconocido produjo un acta APTA.'
        );
        $this->assertSame(ToolInspection::VERDICT_NO_EXEC, $insp->verdict);
    }

    /**
     * El MODO no cambia el veredicto: en modo 'operator' un gate caído tampoco da APTA
     * (la seguridad no depende de quién ejecuta el checklist).
     */
    public function test_modo_operador_no_ablanda_el_gate(): void
    {
        $tool = $this->makeToolWithPoints([
            ['gate' => true, 'outcome' => 'reemplazo'],
        ]);
        $codes = $this->toolPointCodes($tool);
        $this->actingAsRole('safety-officer');

        $this->post(route('tools.inspect.store', $tool->id),
            $this->inspectionStorePayload($tool, [$codes[0] => 'fail'], ['checklist_mode' => 'operator']))
            ->assertStatus(302);

        $insp = ToolInspection::latest('id')->first();
        $this->assertNotSame(ToolInspection::VERDICT_APTA, $insp->verdict);
    }

    /** No se puede colar un acta APTA "en blanco": faltar un punto NO guarda nada. */
    public function test_checklist_incompleto_no_persiste(): void
    {
        $tool = $this->makeToolWithPoints([
            ['gate' => true, 'outcome' => 'reemplazo'],
            ['gate' => true, 'outcome' => 'reemplazo'],
        ]);
        $codes = $this->toolPointCodes($tool);
        $this->actingAsRole('safety-officer');

        // Sólo responde el primero → falta el segundo.
        $payload = [
            'department_id' => $this->aDepartmentId(),
            'answers'       => [$codes[0] => 'ok'],
        ];
        $resp = $this->post(route('tools.inspect.store', $tool->id), $payload);
        $resp->assertStatus(302);
        $resp->assertSessionHas('error');

        $this->assertSame(0, ToolInspection::count(), 'Una inspección incompleta no se guarda.');
    }

    /** Una herramienta sin checklist no produce un acta APTA vacua. */
    public function test_herramienta_sin_puntos_no_produce_acta(): void
    {
        $tool = $this->makeToolWithPoints([]); // sin puntos
        $this->actingAsRole('safety-officer');

        $resp = $this->post(route('tools.inspect.store', $tool->id), [
            'department_id' => $this->aDepartmentId(),
            'answers'       => ['x' => 'ok'], // pasa la regla min:1, pero no hay puntos reales
        ]);
        $resp->assertStatus(302);
        $resp->assertSessionHas('error');
        $this->assertSame(0, ToolInspection::count());
    }
}
