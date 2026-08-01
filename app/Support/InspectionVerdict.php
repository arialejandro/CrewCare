<?php

namespace App\Support;

use App\Models\ToolInspection;

/**
 * Calculadora de inoperatividad. Deriva el veredicto del DATO (no se captura):
 * lee `is_gate` + `outcome_if_fail` de los puntos que CAYERON y devuelve el
 * titular (PARO / ACTIVIDAD NO EJECUTABLE / APTA) más la vía de salida del paro.
 *
 * Cada punto ejecutado se pasa como:
 *   ['code'=>'PC-001', 'is_gate'=>bool, 'outcome_if_fail'=>'reemplazo|correccion_mismo_dia|actividad_no_ejecutable|condicionado', 'answer'=>bool]
 * donde answer===true = CUMPLE (el punto está bien) y answer===false = FALLA.
 * El enunciado del catálogo está redactado para que "sí" sea siempre lo seguro.
 *
 * Precedencia (de más grave a menos): PARO > ACTIVIDAD NO EJECUTABLE > APTA.
 * Dentro del PARO, `reemplazo` (fuera de servicio) manda sobre `correccion_mismo_dia`.
 */
class InspectionVerdict
{
    /**
     * @param  array<int,array>  $executedPoints
     * @return array{verdict:string, resolution_path:?string, failed_gates:array, condicionado_failed:array}
     */
    public static function compute(array $executedPoints): array
    {
        $failedGates = [];        // gates que cayeron
        $condicionado = [];       // no-gates que cayeron (observación)

        foreach ($executedPoints as $p) {
            $answered = array_key_exists('answer', $p) ? $p['answer'] : null;
            if ($answered !== false) {
                continue; // solo nos importan los que FALLARON (answer === false)
            }
            if (! empty($p['is_gate'])) {
                $failedGates[] = $p;
            } else {
                $condicionado[] = $p;
            }
        }

        $outcomes = array_map(function ($p) {
            return $p['outcome_if_fail'] ?? null;
        }, $failedGates);

        // PARO: cualquier gate de reemplazo o corrección el mismo día.
        $hasReplace = in_array(ToolInspection::PATH_REPLACE, $outcomes, true);
        $hasSameDay = in_array(ToolInspection::PATH_SAME_DAY, $outcomes, true);

        if ($hasReplace || $hasSameDay) {
            return [
                'verdict'             => ToolInspection::VERDICT_PARO,
                // reemplazo (fuera de servicio) manda sobre corrección el mismo día.
                'resolution_path'     => $hasReplace ? ToolInspection::PATH_REPLACE : ToolInspection::PATH_SAME_DAY,
                'failed_gates'        => $failedGates,
                'condicionado_failed' => $condicionado,
            ];
        }

        // FAIL-SAFE: cualquier OTRO gate caído ⇒ ACTIVIDAD NO EJECUTABLE. Un gate que
        // cae JAMÁS produce APTA, aunque su `outcome_if_fail` venga NULL o desconocido
        // (dato futuro mal capturado). Subsume el caso `actividad_no_ejecutable` y
        // conserva la evidencia (nunca vacía `failed_gates`). En un veredicto de
        // seguridad, el default correcto es cerrar, no abrir.
        if (! empty($failedGates)) {
            return [
                'verdict'             => ToolInspection::VERDICT_NO_EXEC,
                'resolution_path'     => null,
                'failed_gates'        => $failedGates,
                'condicionado_failed' => $condicionado,
            ];
        }

        // APTA — sólo si NINGÚN gate cayó (con las observaciones de los condicionados).
        return [
            'verdict'             => ToolInspection::VERDICT_APTA,
            'resolution_path'     => null,
            'failed_gates'        => [],
            'condicionado_failed' => $condicionado,
        ];
    }
}
