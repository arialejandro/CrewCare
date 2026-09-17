<?php

namespace App\Support;

use App\Models\AmbulanceInspection;

/**
 * Calculadora del veredicto de la verificación de recurso de emergencia en sitio.
 * Deriva el titular del DATO (no se captura): lee `is_gate` + `outcome_if_fail` de
 * los puntos que CAYERON y devuelve PARO / ACTIVIDAD NO EJECUTABLE / APTA.
 *
 * Gemela de {@see InspectionVerdict}, con el vocabulario de ambulancia. A diferencia
 * de la herramienta, aquí NO hay vía de salida del paro (reemplazo/corrección): una
 * ambulancia se sustituye o se declara otro medio, así que `resolution_path` es null.
 *
 * Cada punto ejecutado se pasa como:
 *   ['is_gate'=>bool, 'outcome_if_fail'=>'paro_inmediato'|'actividad_no_ejecutable'|null, 'answer'=>bool]
 * donde answer===true = CUMPLE (el punto está bien) y answer===false = FALLA. El
 * enunciado del catálogo está redactado para que "sí" sea siempre lo seguro.
 *
 * TRIPULACIÓN (2026-08-13): el veredicto es de la UNIDAD (checklist). La tripulación es una
 * DIMENSIÓN SEPARADA que NO cambia el veredicto: {@see crewSummary()} evalúa la composición
 * mínima (≥1 operador + ≥1 clínico) y el acta la presenta como un ESTADO INTERMEDIO —una
 * ADVERTENCIA en ámbar "sin tripulación calificada"— cuando la unidad quedó apta pero falta
 * personal: la ambulancia está lista, pero no puede operar sin tripulación calificada (NOM-034).
 * El cotejo CONOCER del clínico tampoco cambia el veredicto: sin cotejar es una advertencia menor.
 *
 * Precedencia del veredicto (de más grave a menos): PARO > ACTIVIDAD NO EJECUTABLE > APTA.
 */
class AmbulanceVerdict
{
    /** Salidas de compuerta del catálogo (columna outcome_if_fail). */
    const OUTCOME_PARO    = 'paro_inmediato';
    const OUTCOME_NO_EXEC = 'actividad_no_ejecutable';

    /** Roles canónicos de la tripulación (lista controlada del formulario `execute`). */
    const ROLE_OPERADOR = 'Operador de ambulancia';
    const ROLE_TAMP     = 'Técnico en Atención Médica Prehospitalaria (TAMP)';
    const ROLE_MEDICO   = 'Médico con capacitación prehospitalaria';

    /**
     * Clasifica el rol de un tripulante en 'operador' | 'clinical' | 'other'. Se apoya en
     * palabras clave (no en igualdad exacta) para tolerar el padrón legacy en texto libre y
     * variantes de la lista controlada. Acentos incluidos ('médic' cubre médico/médica).
     *
     * @param  string|null  $role
     */
    public static function classifyRole($role): string
    {
        $r = mb_strtolower(trim((string) $role));
        if ($r === '') {
            return 'other';
        }
        // Operador / conductor de la unidad.
        if (str_contains($r, 'operador') || str_contains($r, 'conductor')) {
            return 'operador';
        }
        // Clínico prehospitalario: TAMP / técnico en atención médica / paramédico / médico / enfermería.
        foreach (['tamp', 'atención médica', 'atencion medica', 'prehospital', 'paramed', 'médic', 'medic', 'enfermer'] as $kw) {
            if (str_contains($r, $kw)) {
                return 'clinical';
            }
        }
        return 'other';
    }

    /**
     * Resume la tripulación capturada contra la composición MÍNIMA para operar
     * (al menos 1 operador + 1 clínico). "Registrado con su rol" basta para contar; el
     * cotejo CONOCER pendiente del clínico es advertencia (`clinical_unverified`), no bloqueo.
     *
     * @param  array<int,array>  $crewSnapshot  cada uno: ['role'=>?string,'crew_role'=>?string,'verified'=>?bool,...]
     * @return array{has_operador:bool,has_clinical:bool,clinical_verified:bool,clinical_unverified:bool,missing:array<int,string>,sufficient:bool}
     */
    public static function crewSummary(array $crewSnapshot): array
    {
        $hasOperador = false;
        $hasClinical = false;
        $hasClinicalVerified = false;

        foreach ($crewSnapshot as $m) {
            if (! is_array($m)) {
                continue;
            }
            $role  = $m['role'] ?? ($m['crew_role'] ?? null);
            $class = self::classifyRole($role);
            if ($class === 'operador') {
                $hasOperador = true;
            } elseif ($class === 'clinical') {
                $hasClinical = true;
                if (! empty($m['verified'])) {
                    $hasClinicalVerified = true;
                }
            }
        }

        $missing = [];
        if (! $hasOperador) {
            $missing[] = 'operador';
        }
        if (! $hasClinical) {
            $missing[] = 'clinico';
        }

        return [
            'has_operador'        => $hasOperador,
            'has_clinical'        => $hasClinical,
            'clinical_verified'   => $hasClinicalVerified,
            'clinical_unverified' => $hasClinical && ! $hasClinicalVerified,
            'missing'             => $missing,
            'sufficient'          => empty($missing),
        ];
    }

    /**
     * Veredicto de la UNIDAD, derivado SOLO del checklist (la tripulación NO lo cambia: se
     * presenta aparte como advertencia, ver {@see crewSummary()}).
     *
     * @param  array<int,array>  $executed
     * @return array{verdict:string, resolution_path:null, failed_gates:array, condicionados:array}
     */
    public static function compute(array $executed): array
    {
        $failedGates   = [];   // compuertas que cayeron
        $condicionados = [];   // no-compuertas que cayeron (observación, no bloquean)

        foreach ($executed as $p) {
            $answered = array_key_exists('answer', $p) ? $p['answer'] : null;
            if ($answered !== false) {
                continue; // solo importan los que FALLARON (answer === false)
            }
            if (! empty($p['is_gate'])) {
                $failedGates[] = $p;
            } else {
                $condicionados[] = $p;
            }
        }

        $outcomes = array_map(function ($p) {
            return $p['outcome_if_fail'] ?? null;
        }, $failedGates);

        // PARO (lo más grave): cualquier compuerta caída cuya salida sea 'paro_inmediato'.
        if (in_array(self::OUTCOME_PARO, $outcomes, true)) {
            return [
                'verdict'         => AmbulanceInspection::VERDICT_PARO,
                'resolution_path' => null, // las ambulancias no usan corrección/reemplazo
                'failed_gates'    => $failedGates,
                'condicionados'   => $condicionados,
            ];
        }

        // FAIL-SAFE: cualquier compuerta caída (con salida no_exec/NULL/desconocida) ⇒ ACTIVIDAD
        // NO EJECUTABLE. En un veredicto de seguridad el default correcto es cerrar, no abrir.
        if (! empty($failedGates)) {
            return [
                'verdict'         => AmbulanceInspection::VERDICT_NO_EXEC,
                'resolution_path' => null,
                'failed_gates'    => $failedGates,
                'condicionados'   => $condicionados,
            ];
        }

        // APTA — NINGUNA compuerta cayó (con las observaciones condicionadas).
        return [
            'verdict'         => AmbulanceInspection::VERDICT_APTA,
            'resolution_path' => null,
            'failed_gates'    => [],
            'condicionados'   => $condicionados,
        ];
    }
}
