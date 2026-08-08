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
 * Precedencia (de más grave a menos): PARO > ACTIVIDAD NO EJECUTABLE > APTA.
 */
class AmbulanceVerdict
{
    /** Salidas de compuerta del catálogo (columna outcome_if_fail). */
    const OUTCOME_PARO    = 'paro_inmediato';
    const OUTCOME_NO_EXEC = 'actividad_no_ejecutable';

    /**
     * @param  array<int,array>  $executed
     * @return array{verdict:string, resolution_path:null, failed_gates:array, condicionados:array}
     */
    public static function compute(array $executed): array
    {
        $failedGates  = [];   // compuertas que cayeron
        $condicionados = [];  // no-compuertas que cayeron (observación, no bloquean)

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

        // PARO: cualquier compuerta caída cuya salida sea 'paro_inmediato'.
        if (in_array(self::OUTCOME_PARO, $outcomes, true)) {
            return [
                'verdict'         => AmbulanceInspection::VERDICT_PARO,
                'resolution_path' => null, // las ambulancias no usan corrección/reemplazo
                'failed_gates'    => $failedGates,
                'condicionados'   => $condicionados,
            ];
        }

        // FAIL-SAFE: cualquier OTRA compuerta caída ⇒ ACTIVIDAD NO EJECUTABLE. Una
        // compuerta que cae JAMÁS produce APTA, aunque su `outcome_if_fail` venga
        // 'actividad_no_ejecutable', NULL o desconocido (dato futuro mal capturado).
        // En un veredicto de seguridad el default correcto es cerrar, no abrir.
        if (! empty($failedGates)) {
            return [
                'verdict'         => AmbulanceInspection::VERDICT_NO_EXEC,
                'resolution_path' => null,
                'failed_gates'    => $failedGates,
                'condicionados'   => $condicionados,
            ];
        }

        // APTA — solo si NINGUNA compuerta cayó (con las observaciones condicionadas).
        return [
            'verdict'         => AmbulanceInspection::VERDICT_APTA,
            'resolution_path' => null,
            'failed_gates'    => [],
            'condicionados'   => $condicionados,
        ];
    }
}
