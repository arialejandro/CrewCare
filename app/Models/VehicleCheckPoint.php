<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Punto del catálogo de verificación de vehículos (Capa de catálogo).
 *
 * SEVERIDAD GRADUADA — `class` = critical | major | minor. Es VOCABULARIO DELIBERADO: no es el
 * binario `is_gate`/`es_compuerta` de herramienta/ambulancia (aquel emite veredicto binario;
 * aquí el veredicto es graduado, {@see \App\Support\VehicleVerdict}).
 *
 * `applies_when` = expresión sobre atributos del vehículo (p. ej. "seats > 8"); NULL/'' = aplica
 * siempre. La evalúa {@see \App\Support\VehicleChecklist::applies()} (gramática controlada).
 *
 * `norm_id` NULO y `verified_at` NULL en todos: salen de oficio, no de una norma.
 */
class VehicleCheckPoint extends Model
{
    protected $table = 'vehicle_check_points';

    protected $guarded = ['id', 'verified_at', 'verified_by_id'];

    const CLASS_CRITICAL = 'critical';
    const CLASS_MAJOR    = 'major';
    const CLASS_MINOR    = 'minor';
    const CLASSES = ['critical', 'major', 'minor'];

    protected $casts = [
        'requires_photo' => 'boolean',
        'is_active'      => 'boolean',
        'verified_at'    => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
