<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Punto de un permiso de actividad (Capa C). 103 puntos, cada uno pertenece a un
 * solo permiso. `is_gate` SIEMPRE 1 (invariante del dominio: en permisos no hay
 * punto informativo). `executor` = safety | especialista; `requires_contact` marca
 * los que el Safety NO ejecuta (abrir un mortero cargado crea riesgo, no lo reduce).
 * `site_sensitive` es lo que se re-verifica al mover una actividad de montaje temporal.
 */
class PermitPoint extends Model
{
    protected $table = 'permit_points';

    protected $guarded = ['id', 'verified_at', 'verified_by_id'];

    protected $casts = [
        'is_gate'          => 'boolean',
        'site_sensitive'   => 'boolean',
        'requires_contact' => 'boolean',
        'is_active'        => 'boolean',
        'verified_at'      => 'datetime',
    ];

    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class, 'permit_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
