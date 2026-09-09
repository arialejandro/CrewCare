<?php

namespace App\Support;

use App\Models\VehicleInspection;

/**
 * Motor de veredicto GRADUADO de la verificación de vehículo. Deriva el titular del DATO (no se
 * captura): cuenta las FALLAS por clase (critical/major/minor) y las traduce a un NIVEL interno
 * y a un veredicto público apto/no_apto.
 *
 * Gemelo de {@see InspectionVerdict}/{@see AmbulanceVerdict}, pero con veredicto GRADUADO en vez
 * de binario. Cada punto ejecutado llega como:
 *   ['class' => 'critical'|'major'|'minor', 'answer' => bool|null]
 * donde answer===true = CUMPLE, answer===false = FALLA, answer===null = SIN CONTESTAR.
 *
 * ── FAIL-SAFE (regla dura, §5) ────────────────────────────────────────────────
 *   Un punto aplicable SIN CONTESTAR nunca produce un resultado favorable: si hay cualquier
 *   nulo, el acta NO cierra (verdict = no_apto, level = null). Es el mismo default de seguridad
 *   que herramienta/ambulancia (una compuerta caída jamás da APTA): cerrar, no abrir. El
 *   controlador ADEMÁS aborta el guardado antes de llegar aquí; este guard es la segunda capa.
 *
 * ── NIVELES (de más grave a menos) ────────────────────────────────────────────
 *   alto_riesgo : ≥1 crítico            → NO APTO (reemplazo de unidad)
 *   pobre       : 0 crít. y ≥3 mayores  → NO APTO (resoluble en taller)
 *   normal      : 1-2 mayores, o ≥4 menores → APTO con observaciones
 *   bien        : 0 mayores y 1-3 menores   → APTO
 *   excelente   : cero hallazgos             → APTO
 */
class VehicleVerdict
{
    /**
     * @param  array<int,array>  $executed  cada uno ['class'=>string,'answer'=>bool|null]
     * @return array{verdict:string, level:?string, incomplete:bool, n_critical:int, n_major:int, n_minor:int}
     */
    public static function compute(array $executed): array
    {
        $crit = 0;
        $major = 0;
        $minor = 0;
        $incomplete = false;

        foreach ($executed as $p) {
            $answer = array_key_exists('answer', $p) ? $p['answer'] : null;
            if ($answer === null) {
                $incomplete = true;   // sin contestar
                continue;
            }
            if ($answer !== false) {
                continue;             // CUMPLE (no cuenta)
            }
            // FALLA → cuenta por su clase
            switch ($p['class'] ?? 'minor') {
                case 'critical': $crit++;  break;
                case 'major':    $major++; break;
                default:         $minor++; break;
            }
        }

        // FAIL-SAFE: cualquier punto aplicable sin contestar ⇒ jamás favorable.
        if ($incomplete) {
            return [
                'verdict'    => VehicleInspection::VERDICT_NO_APTO,
                'level'      => null,
                'incomplete' => true,
                'n_critical' => $crit,
                'n_major'    => $major,
                'n_minor'    => $minor,
            ];
        }

        // Cascada de niveles (de más grave a menos).
        if ($crit >= 1) {
            $level   = VehicleInspection::LEVEL_ALTO;
            $verdict = VehicleInspection::VERDICT_NO_APTO;
        } elseif ($major >= 3) {
            $level   = VehicleInspection::LEVEL_POBRE;
            $verdict = VehicleInspection::VERDICT_NO_APTO;
        } elseif ($major >= 1) {                 // 1 o 2 mayores
            $level   = VehicleInspection::LEVEL_NORMAL;
            $verdict = VehicleInspection::VERDICT_APTO;
        } elseif ($minor >= 4) {                 // 4 o más menores
            $level   = VehicleInspection::LEVEL_NORMAL;
            $verdict = VehicleInspection::VERDICT_APTO;
        } elseif ($minor >= 1) {                 // hasta 3 menores
            $level   = VehicleInspection::LEVEL_BIEN;
            $verdict = VehicleInspection::VERDICT_APTO;
        } else {                                 // cero hallazgos
            $level   = VehicleInspection::LEVEL_EXCELENTE;
            $verdict = VehicleInspection::VERDICT_APTO;
        }

        return [
            'verdict'    => $verdict,
            'level'      => $level,
            'incomplete' => false,
            'n_critical' => $crit,
            'n_major'    => $major,
            'n_minor'    => $minor,
        ];
    }

    /** Etiqueta legible del nivel interno (solo transpo/safety). */
    public static function levelLabel(?string $level): string
    {
        switch ($level) {
            case VehicleInspection::LEVEL_ALTO:      return 'Alto riesgo';
            case VehicleInspection::LEVEL_POBRE:     return 'Pobre';
            case VehicleInspection::LEVEL_NORMAL:    return 'Normal';
            case VehicleInspection::LEVEL_BIEN:      return 'Bien';
            case VehicleInspection::LEVEL_EXCELENTE: return 'Excelente';
            default:                                 return 'Incompleto';
        }
    }
}
