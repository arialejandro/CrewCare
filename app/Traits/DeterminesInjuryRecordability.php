<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Trait DeterminesInjuryRecordability — reglas de OSHA/fatiga para el Accidente.
 *
 * En el evento `saving` del InjuryReport:
 *   1) FATIGA: calcula hours_worked_prior = time(incidente) − call_time (maneja el
 *      cruce de medianoche). Registra un WARNING interno si supera 12 h.
 *   2) REGISTRABILIDAD (OSHA 29 CFR 1904): fuerza is_recordable = true si
 *      treatment_level ∈ {medical_treatment, hospitalization, fatality}
 *      o si days_away_from_work > 0 o days_restricted_work > 0.
 *
 * DEFENSIVO: cada escritura se protege con Schema::hasColumn → si el owner aún no
 * aplicó el delta, no intenta escribir columnas inexistentes (no truena).
 */
trait DeterminesInjuryRecordability
{
    public static function bootDeterminesInjuryRecordability()
    {
        static::saving(function ($model) {
            $table = $model->getTable();

            // --- (1) Fatiga: horas trabajadas antes del incidente ---
            if (Schema::hasColumn($table, 'hours_worked_prior')) {
                $call = self::minutesFromTime(isset($model->call_time) ? $model->call_time : null);
                $inc  = self::minutesFromTime(isset($model->time) ? $model->time : null);
                if ($call !== null && $inc !== null) {
                    $diff = $inc - $call;
                    if ($diff < 0) {
                        $diff += 1440; // turno que cruza la medianoche
                    }
                    $hours = round($diff / 60, 2);
                    $model->hours_worked_prior = $hours;

                    if ($hours > 12) {
                        Log::warning('[Injury fatigue] hours_worked_prior=' . $hours
                            . 'h (>12h) — lesionado "' . (isset($model->name) ? $model->name : '')
                            . '", incidente ' . (isset($model->incident_date) ? $model->incident_date : ''));
                    }
                }
            }

            // --- (2) Registrabilidad OSHA 300/301 ---
            if (Schema::hasColumn($table, 'is_recordable')) {
                $recordableLevels = ['medical_treatment', 'hospitalization', 'fatality'];
                $level      = isset($model->treatment_level) ? $model->treatment_level : null;
                $away       = (int) (isset($model->days_away_from_work) ? $model->days_away_from_work : 0);
                $restricted = (int) (isset($model->days_restricted_work) ? $model->days_restricted_work : 0);

                $model->is_recordable = (in_array($level, $recordableLevels, true) || $away > 0 || $restricted > 0);
            }
        });
    }

    /**
     * Convierte "H:i" / "H:i:s" a minutos desde medianoche, o null si no es hora válida.
     *
     * @param  mixed $value
     * @return int|null
     */
    private static function minutesFromTime($value)
    {
        if (empty($value)) {
            return null;
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})/', (string) $value, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }
        return $h * 60 + $min;
    }
}
