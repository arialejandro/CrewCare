<?php

namespace App\Traits;

use App\Models\Addendum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trait HasMedicalAddendums — Pilar 4 (APPEND-ONLY) para InjuryReport.
 *
 * El reporte de lesión firmado es inmutable. Este trait NO altera sus columnas;
 * expone valores "efectivos" que prefieren el ÚLTIMO addendum cuando éste trae un
 * nuevo valor, y caen al valor original del reporte en caso contrario. Así las
 * estadísticas de tablero (OSHA 300/301, días perdidos) reflejan la última verdad
 * clínica sin reescribir jamás el expediente base.
 *
 * DEFENSIVO: si la tabla addendums aún no existe (owner no aplicó el SQL), todo
 * cae al valor original del reporte y nada truena.
 */
trait HasMedicalAddendums
{
    /**
     * Addendums de este reporte, del más reciente al más antiguo.
     */
    public function addendums(): HasMany
    {
        return $this->hasMany(Addendum::class, 'injury_report_id')->latest();
    }

    /**
     * El último addendum (o null si no hay tabla / no hay addendums).
     *
     * @return \App\Models\Addendum|null
     */
    public function latestAddendum()
    {
        if (!Schema::hasTable('addendums')) {
            return null;
        }

        return $this->addendums()->first();
    }

    /**
     * Nivel de tratamiento EFECTIVO: el del último addendum si trae uno no vacío,
     * si no el original del reporte.
     *
     * @return string|null
     */
    public function effectiveTreatmentLevel()
    {
        if (Schema::hasTable('addendums')) {
            $last = $this->latestAddendum();
            if ($last !== null && $last->new_treatment_level !== null && $last->new_treatment_level !== '') {
                return $last->new_treatment_level;
            }
        }

        return $this->treatment_level;
    }

    /**
     * Registrabilidad OSHA EFECTIVA: si el último addendum define new_is_recordable
     * (no-null) usa ese (casteado a bool); si no, la del reporte original.
     *
     * @return bool
     */
    public function effectiveIsRecordable(): bool
    {
        if (Schema::hasTable('addendums')) {
            $last = $this->latestAddendum();
            if ($last !== null && $last->new_is_recordable !== null) {
                return (bool) $last->new_is_recordable;
            }
        }

        return (bool) $this->is_recordable;
    }

    /**
     * Días perdidos EFECTIVOS: los del último addendum (no-null) o los del reporte.
     *
     * @return int|null
     */
    public function effectiveDaysAway()
    {
        if (Schema::hasTable('addendums')) {
            $last = $this->latestAddendum();
            if ($last !== null && $last->new_days_away !== null) {
                return (int) $last->new_days_away;
            }
        }

        return $this->days_away_from_work;
    }

    /**
     * Días de trabajo restringido EFECTIVOS: los del último addendum (no-null) o los
     * del reporte original.
     *
     * @return int|null
     */
    public function effectiveDaysRestricted()
    {
        if (Schema::hasTable('addendums')) {
            $last = $this->latestAddendum();
            if ($last !== null && $last->new_days_restricted !== null) {
                return (int) $last->new_days_restricted;
            }
        }

        return $this->days_restricted_work;
    }
}
