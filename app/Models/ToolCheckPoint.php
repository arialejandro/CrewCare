<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Punto de comprobación de herramienta (Capa B). 67 puntos, COMPARTIDOS por muchos
 * tipos (N:M). `scope` (ambito): universal / universal_energizada / familia / tipo /
 * actividad. `supersedes` = un punto de familia/tipo reemplaza a un universal cuando
 * dice lo mismo con más precisión (evita inflar el checklist).
 *
 * `is_gate` = compuerta (63) vs informativo (4). Solo la corrección con evidencia
 * y reinspección cierra una compuerta; nunca una contraseña.
 */
class ToolCheckPoint extends Model
{
    protected $table = 'tool_check_points';

    protected $guarded = ['id', 'verified_at', 'verified_by_id'];

    protected $casts = [
        'is_gate'              => 'boolean',
        'visible_to_naked_eye' => 'boolean',
        'is_active'            => 'boolean',
        'verified_at'          => 'datetime',
        'severity'             => 'integer',
        'estimated_seconds'    => 'integer',
    ];

    protected static function booted()
    {
        static::deleting(function (ToolCheckPoint $point) {
            $point->pendingStandards()->delete();
        });
    }

    /** N:M punto ↔ tipo. */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'check_point_tool', 'tool_check_point_id', 'tool_id');
    }

    /** Normas a nivel PUNTO. */
    public function standards(): BelongsToMany
    {
        return $this->belongsToMany(SafetyStandard::class, 'check_point_standard', 'tool_check_point_id', 'safety_standard_id')
            ->withTimestamps();
    }

    public function pendingStandards(): MorphMany
    {
        return $this->morphMany(CatalogPendingStandard::class, 'linkable');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
