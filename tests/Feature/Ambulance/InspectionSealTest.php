<?php

namespace Tests\Feature\Ambulance;

use App\Models\AmbulanceInspection;
use App\Models\AmbulanceInspectionPoint;
use App\Support\AmbulanceVerdict;
use Illuminate\Support\Facades\DB;

/**
 * PERSISTENCIA + SELLADO + VEREDICTO de la VERIFICACIÓN en sitio (endpoints REALES).
 *
 * Cubre: emitir un acta COMPLETA (todos los puntos 'ok') → APTA, nace sellada y verifica
 * íntegra; una compuerta 'paro_inmediato' caída → PARO + action item (PDCA); el checklist
 * incompleto NO se guarda; la regla dura "compuerta caída JAMÁS da APTA" (fail-safe del
 * calculador); y que falsificar el veredicto en BD rompe el sello (integridad).
 */
class InspectionSealTest extends AmbulanceVerticalTestCase
{
    // =====================================================================
    //  APTA — emisión completa: persiste, sella y el acta es alcanzable.
    // =====================================================================

    public function test_verificacion_completa_ok_da_apta_y_nace_sellada(): void
    {
        $so   = $this->actingAsRole('safety-officer');
        $type = $this->aTerrestrialType('AMB-01'); // traslado, pocos puntos

        $resp = $this->post(route('ambulance.inspect.store'), $this->inspectStorePayload($type));
        $resp->assertSessionHasNoErrors();
        $resp->assertStatus(302);

        $insp = AmbulanceInspection::latest('id')->first();
        $this->assertNotNull($insp, 'El store debe persistir el acta.');
        $this->assertSame(AmbulanceInspection::VERDICT_APTA, $insp->verdict);
        $this->assertSame($so->id, (int) $insp->inspector_user_id, 'El firmante se congela server-side.');
        $this->assertNotEmpty($insp->uuid);

        // SELLO al crear: existe firma y el acta intacta verifica ÍNTEGRA.
        $fresh = AmbulanceInspection::where('uuid', $insp->uuid)->first();
        $this->assertTrue($fresh->signatures()->exists(), 'El acta debe nacer sellada.');
        $this->assertTrue($fresh->verifyLatestSignature(), 'El sello recién creado debe verificar íntegro.');

        // El acta (interna) es alcanzable por su emisor.
        $this->get(route('ambulance.acta', $insp->uuid))->assertOk();
    }

    // =====================================================================
    //  PARO — una compuerta 'paro_inmediato' caída ⇒ PARO + action item.
    // =====================================================================

    public function test_compuerta_paro_caida_da_paro_y_levanta_action_item(): void
    {
        $type  = $this->aTerrestrialType('AMB-04'); // hereda todo el checklist terrestre
        $codes = $this->applicablePointCodes($type);

        $paro = AmbulanceInspectionPoint::whereIn('code', $codes)
            ->where('is_active', 1)->where('is_gate', 1)
            ->where('outcome_if_fail', 'paro_inmediato')
            ->first();

        if (! $paro) {
            $this->markTestSkipped('El catálogo de fábrica no trae un punto compuerta paro_inmediato aplicable a AMB-04.');
        }

        $this->actingAsRole('safety-officer');
        $resp = $this->post(
            route('ambulance.inspect.store'),
            $this->inspectStorePayload($type, [$paro->code => 'fail'])
        );
        $resp->assertSessionHasNoErrors();
        $resp->assertStatus(302);

        $insp = AmbulanceInspection::latest('id')->first();
        $this->assertSame(AmbulanceInspection::VERDICT_PARO, $insp->verdict, 'Una compuerta paro caída ⇒ PARO.');
        $this->assertNull($insp->resolution_path, 'Las ambulancias no tienen vía de salida del paro.');
        $this->assertTrue($insp->isBlocked(), 'Un PARO sin desbloquear está bloqueado.');

        // El PARO genera la obligación PDCA (action item ligado al acta).
        $this->assertGreaterThanOrEqual(1, $insp->actionItems()->count(), 'El PARO debe levantar un action item.');
    }

    // =====================================================================
    //  Checklist incompleto — no se guarda un acta a medias.
    // =====================================================================

    public function test_checklist_incompleto_no_persiste_acta(): void
    {
        $this->actingAsRole('safety-officer');
        $type     = $this->aTerrestrialType('AMB-01');
        $provider = $this->makeProvider();

        // Todos 'ok' menos UNO que se omite → verificación incompleta.
        $codes   = $this->applicablePointCodes($type);
        $answers = [];
        foreach ($codes as $code) {
            $answers[$code] = 'ok';
        }
        $missing = array_pop($codes);
        unset($answers[$missing]);

        $resp = $this->post(route('ambulance.inspect.store'), [
            'type_id'     => $type->id,
            'provider_id' => $provider->id,
            'answers'     => $answers,
        ]);

        $resp->assertSessionHas('error');
        $this->assertSame(0, AmbulanceInspection::count(), 'Una verificación incompleta NO se guarda.');
    }

    // =====================================================================
    //  FAIL-SAFE (regla dura) — una compuerta caída JAMÁS produce APTA.
    // =====================================================================

    public function test_failsafe_compuerta_caida_nunca_da_apta(): void
    {
        // Compuerta caída con salida 'paro_inmediato' ⇒ PARO.
        $paro = AmbulanceVerdict::compute([
            ['is_gate' => true, 'outcome_if_fail' => AmbulanceVerdict::OUTCOME_PARO, 'answer' => false],
        ]);
        $this->assertSame(AmbulanceInspection::VERDICT_PARO, $paro['verdict']);

        // Compuerta caída con salida NULL o DESCONOCIDA ⇒ NO_EXEC, nunca APTA (dato futuro mal capturado).
        foreach ([null, 'desconocido_futuro', AmbulanceVerdict::OUTCOME_NO_EXEC] as $outcome) {
            $r = AmbulanceVerdict::compute([
                ['is_gate' => true, 'outcome_if_fail' => $outcome, 'answer' => false],
            ]);
            $this->assertNotSame(
                AmbulanceInspection::VERDICT_APTA,
                $r['verdict'],
                'Una compuerta caída (outcome=' . var_export($outcome, true) . ') JAMÁS puede dar APTA.'
            );
            $this->assertSame(AmbulanceInspection::VERDICT_NO_EXEC, $r['verdict']);
        }

        // Sólo cae un NO-compuerta ⇒ APTA (queda como condicionado, no bloquea).
        $apta = AmbulanceVerdict::compute([
            ['is_gate' => false, 'outcome_if_fail' => null, 'answer' => false],
            ['is_gate' => true,  'outcome_if_fail' => AmbulanceVerdict::OUTCOME_PARO, 'answer' => true],
        ]);
        $this->assertSame(AmbulanceInspection::VERDICT_APTA, $apta['verdict']);
        $this->assertCount(1, $apta['condicionados']);
    }

    // =====================================================================
    //  TRIPULACIÓN — estado intermedio: la UNIDAD queda apta, pero sin personal
    //  calificado el acta marca la advertencia "sin tripulación calificada".
    // =====================================================================

    public function test_sin_tripulacion_la_unidad_queda_apta_pero_con_advertencia(): void
    {
        $this->actingAsRole('safety-officer');
        $type = $this->aTerrestrialType('AMB-01');

        // Checklist COMPLETO en 'ok' pero SIN tripulación: la UNIDAD queda apta (veredicto del
        // checklist); la ADVERTENCIA de tripulación se deriva del crew_snapshot vacío.
        $this->post(
            route('ambulance.inspect.store'),
            $this->inspectStorePayload($type, [], ['crew' => []])
        )->assertSessionHasNoErrors();

        $insp = AmbulanceInspection::latest('id')->first();
        $this->assertSame(
            AmbulanceInspection::VERDICT_APTA,
            $insp->verdict,
            'El veredicto de la UNIDAD sigue siendo APTA (la tripulación no lo cambia).'
        );
        $summary = AmbulanceVerdict::crewSummary((array) $insp->crew_snapshot);
        $this->assertFalse($summary['sufficient'], 'Sin tripulación → advertencia (no suficiente).');
        $this->assertEqualsCanonicalizing(['operador', 'clinico'], $summary['missing']);
    }

    public function test_operador_o_clinico_solo_deja_apta_con_advertencia(): void
    {
        $this->actingAsRole('safety-officer');
        $type = $this->aTerrestrialType('AMB-01');

        // Solo operador (falta clínico).
        $this->post(route('ambulance.inspect.store'), $this->inspectStorePayload($type, [], [
            'crew' => [['name' => 'Operador QA', 'role' => AmbulanceVerdict::ROLE_OPERADOR]],
        ]))->assertSessionHasNoErrors();
        $insp = AmbulanceInspection::latest('id')->first();
        $this->assertSame(AmbulanceInspection::VERDICT_APTA, $insp->verdict);
        $this->assertFalse(
            AmbulanceVerdict::crewSummary((array) $insp->crew_snapshot)['sufficient'],
            'Operador sin clínico → advertencia.'
        );

        // Solo clínico (falta operador).
        $this->post(route('ambulance.inspect.store'), $this->inspectStorePayload($type, [], [
            'crew' => [['name' => 'TAMP QA', 'role' => AmbulanceVerdict::ROLE_TAMP]],
        ]))->assertSessionHasNoErrors();
        $insp = AmbulanceInspection::latest('id')->first();
        $this->assertSame(AmbulanceInspection::VERDICT_APTA, $insp->verdict);
        $this->assertFalse(
            AmbulanceVerdict::crewSummary((array) $insp->crew_snapshot)['sufficient'],
            'Clínico sin operador → advertencia.'
        );
    }

    public function test_operador_mas_clinico_registrados_apta_sin_advertencia(): void
    {
        $this->actingAsRole('safety-officer');
        $type = $this->aTerrestrialType('AMB-01');

        // El payload por defecto trae operador + TAMP SIN folio CONOCER (registrado ≠ cotejado).
        $this->post(route('ambulance.inspect.store'), $this->inspectStorePayload($type))
            ->assertSessionHasNoErrors();

        $insp = AmbulanceInspection::latest('id')->first();
        $this->assertSame(AmbulanceInspection::VERDICT_APTA, $insp->verdict);
        $this->assertTrue(
            AmbulanceVerdict::crewSummary((array) $insp->crew_snapshot)['sufficient'],
            'Operador + clínico registrados → suficiente (sin advertencia de faltantes).'
        );
    }

    public function test_acta_de_apta_sin_tripulacion_muestra_advertencia_ambar(): void
    {
        $this->actingAsRole('safety-officer');
        $type = $this->aTerrestrialType('AMB-01');
        $this->post(route('ambulance.inspect.store'), $this->inspectStorePayload($type, [], ['crew' => []]))
            ->assertSessionHasNoErrors();
        $insp = AmbulanceInspection::latest('id')->first();

        // El acta presenta el estado intermedio: título con la advertencia + la explicación.
        $this->get(route('ambulance.acta', $insp->uuid))
            ->assertOk()
            ->assertSee('SIN TRIPULACIÓN CALIFICADA', false);
    }

    public function test_paro_del_checklist_manda_sobre_la_tripulacion(): void
    {
        $type  = $this->aTerrestrialType('AMB-04');
        $codes = $this->applicablePointCodes($type);
        $paro  = AmbulanceInspectionPoint::whereIn('code', $codes)
            ->where('is_active', 1)->where('is_gate', 1)
            ->where('outcome_if_fail', 'paro_inmediato')->first();
        if (! $paro) {
            $this->markTestSkipped('Sin punto paro_inmediato aplicable a AMB-04 en el catálogo de fábrica.');
        }

        $this->actingAsRole('safety-officer');
        $this->post(
            route('ambulance.inspect.store'),
            $this->inspectStorePayload($type, [$paro->code => 'fail'], ['crew' => []])
        )->assertSessionHasNoErrors();

        $this->assertSame(
            AmbulanceInspection::VERDICT_PARO,
            AmbulanceInspection::latest('id')->first()->verdict,
            'Un PARO del checklist manda: el veredicto es de la unidad, no de la tripulación.'
        );
    }

    public function test_crew_summary_clasifica_roles_y_detecta_faltantes(): void
    {
        // Sin tripulación → faltan ambos.
        $empty = AmbulanceVerdict::crewSummary([]);
        $this->assertFalse($empty['sufficient']);
        $this->assertEqualsCanonicalizing(['operador', 'clinico'], $empty['missing']);

        // Operador + médico → suficiente; el médico cuenta como clínico. Sin cotejo = advertencia.
        $ok = AmbulanceVerdict::crewSummary([
            ['role' => AmbulanceVerdict::ROLE_OPERADOR],
            ['role' => AmbulanceVerdict::ROLE_MEDICO, 'verified' => false],
        ]);
        $this->assertTrue($ok['sufficient']);
        $this->assertTrue($ok['clinical_unverified'], 'Clínico registrado sin cotejo = advertencia.');

        // Clínico cotejado → no hay advertencia.
        $verified = AmbulanceVerdict::crewSummary([
            ['role' => AmbulanceVerdict::ROLE_OPERADOR],
            ['role' => AmbulanceVerdict::ROLE_TAMP, 'verified' => true],
        ]);
        $this->assertTrue($verified['sufficient']);
        $this->assertFalse($verified['clinical_unverified']);

        // Rol 'Otro' no cuenta como operador ni clínico.
        $other = AmbulanceVerdict::crewSummary([['role' => 'Otro'], ['role' => 'Otro']]);
        $this->assertFalse($other['sufficient']);
    }

    // =====================================================================
    //  INTEGRIDAD — falsificar el veredicto en BD rompe el sello.
    // =====================================================================

    public function test_veredicto_falsificado_en_bd_rompe_el_sello(): void
    {
        $insp = $this->sealAmbulanceInspection(['verdict' => AmbulanceInspection::VERDICT_PARO]);

        // Baseline íntegro.
        $this->assertTrue(
            AmbulanceInspection::where('uuid', $insp->uuid)->first()->verifyLatestSignature()
        );

        // Falsificar 'paro' → 'apta' directamente en la BD (el veredicto entra al hash).
        DB::table('ambulance_inspections')->where('id', $insp->id)
            ->update(['verdict' => AmbulanceInspection::VERDICT_APTA]);

        $this->assertFalse(
            AmbulanceInspection::where('uuid', $insp->uuid)->first()->verifyLatestSignature(),
            'FALLO DE INTEGRIDAD: un veredicto falsificado en BD no se detectó.'
        );
    }
}
