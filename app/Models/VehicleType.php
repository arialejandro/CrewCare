<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de vehículo. EL TIPO PROPONE (attr_profile), LOS ATRIBUTOS CONFIRMAN (vehicles.attr_values).
 * Catálogo EDITABLE por la producción (a diferencia del de ambulancias, de fondo).
 *
 * `attr_profile` (JSON) es el perfil precargado del tipo:
 *   {powertrain, has_cargo_box, seats, has_lpg_or_sanitary, has_genset_or_heat_appliances,
 *    water_tank_liters, tows}
 * `is_special` = el tipo 'especial' (sin perfil; todo se declara a mano).
 *
 * `verified_at` NULL = de oficio, sin auditar. El estado se DECLARA; nadie nace verificado.
 */
class VehicleType extends Model
{
    protected $table = 'vehicle_types';

    // verified_at / verified_by_id son estado server-only: fuera del mass-assign.
    protected $guarded = ['id', 'verified_at', 'verified_by_id'];

    protected $casts = [
        'attr_profile' => 'array',
        'is_special'   => 'boolean',
        'is_active'    => 'boolean',
        'verified_at'  => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /** Perfil de atributos propuesto (array), con las llaves canónicas garantizadas. */
    public function profile(): array
    {
        return \App\Support\VehicleChecklist::normalizeAttributes((array) ($this->attr_profile ?? []));
    }
}
