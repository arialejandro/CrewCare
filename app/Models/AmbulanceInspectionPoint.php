<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Punto binario de verificación de ambulancia (NOM-034-SSA3-2013), AMBV-001..
 * Catálogo de FONDO (ver 2026-08-08-ambulance-catalog.sql, delta #51). Gemelo de
 * {@see ToolCheckPoint}. La pertenencia a un TIPO se DERIVA por rama + nivel
 * ({@see AmbulanceType::applicablePoints()}), no por pivote.
 *
 * COMPUERTA vs INFORMATIVO: `is_gate` + `outcome_if_fail` gobiernan el veredicto
 * (una compuerta caída JAMÁS da resultado favorable — fail-safe del acta, que se
 * arma en un delta posterior). `requires_document`=1 = no se abre el botiquín: se
 * pide el papel (caducidades, calibres, vigencias).
 *
 * `verified_at` NULL = derivado del texto de la NOM, sin auditar por responsable
 * sanitario. El estado se DECLARA.
 */
class AmbulanceInspectionPoint extends Model
{
    protected $table = 'ambulance_inspection_points';

    // verified_at / verified_by_id son estado server-only: fuera del mass-assign.
    protected $guarded = ['id', 'verified_at', 'verified_by_id'];

    protected $casts = [
        'min_level'         => 'integer',
        'max_level'         => 'integer',
        'is_gate'           => 'boolean',
        'requires_document' => 'boolean',
        'is_active'         => 'boolean',
        'verified_at'       => 'datetime',
        'trigger_scopes'    => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function isGate(): bool
    {
        return (bool) $this->is_gate;
    }

    /** ¿Este punto se re-verifica bajo el disparador dado (identidad/persona/unidad/consumo/riesgo)? */
    public function triggersOn(string $scope): bool
    {
        return in_array($scope, (array) $this->trigger_scopes, true);
    }
}
