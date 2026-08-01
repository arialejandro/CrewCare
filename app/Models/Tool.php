<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Tipo de herramienta o equipo (Capa B), HER-001..HER-073. Catálogo de FONDO:
 * se consulta, no se edita (sin CRUD, ver 2026-07-26-tools-permits-catalog.sql).
 *
 * `verified_at` NULL = redactado desde conocimiento de oficio, sin auditar. El
 * estado se DECLARA; nadie nace verificado. HER-026 es el comodín (`is_wildcard`),
 * sin familia: la familia se elige al inspeccionar.
 *
 * Normas ancladas a DOS niveles: al ítem (`standards()`) y al punto de comprobación
 * (`ToolCheckPoint::standards()`). Lo que no resuelve queda en pending().
 */
class Tool extends Model
{
    protected $table = 'tools';

    // verified_at / verified_by_id son estado server-only: fuera del mass-assign.
    protected $guarded = ['id', 'verified_at', 'verified_by_id'];

    protected $casts = [
        'is_wildcard'                  => 'boolean',
        'requires_designated_operator' => 'boolean',
        'is_accessory'                 => 'boolean',
        'is_active'                    => 'boolean',
        'verified_at'                  => 'datetime',
        'aliases'                      => 'array',
        'departments'                  => 'array',
        'stages'                       => 'array',
        'critical_parts'               => 'array',
        'failure_modes'                => 'array',
        'stop_checks'                  => 'array',
        'not_executable_checks'        => 'array',
        'observation_checks'           => 'array',
        'min_ppe'                      => 'array',
        'budget'                       => 'array',
    ];

    protected static function booted()
    {
        // Retiro = is_active=0, NUNCA ->delete(). Pero si alguna vez se borra,
        // los pivotes con FK dura se purgan solos (CASCADE); el vínculo pendiente
        // polimórfico NO tiene FK, así que lo purgamos a mano.
        static::deleting(function (Tool $tool) {
            $tool->pendingStandards()->delete();
        });
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(ToolFamily::class, 'tool_family_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ToolVariant::class, 'tool_id');
    }

    /** N:M tipo ↔ punto de comprobación (los 4 args van explícitos, patrón del repo). */
    public function checkPoints(): BelongsToMany
    {
        return $this->belongsToMany(ToolCheckPoint::class, 'check_point_tool', 'tool_id', 'tool_check_point_id');
    }

    /** Normas a nivel ÍTEM. */
    public function standards(): BelongsToMany
    {
        return $this->belongsToMany(SafetyStandard::class, 'tool_standard', 'tool_id', 'safety_standard_id')
            ->withTimestamps();
    }

    /** Permisos que ESTA herramienta dispara (relación autoritativa, por ID). */
    public function permits(): BelongsToMany
    {
        return $this->belongsToMany(Permit::class, 'permit_tool', 'tool_id', 'permit_id');
    }

    /** Vínculos de norma que aún no resuelven contra safety_standards. */
    public function pendingStandards(): MorphMany
    {
        return $this->morphMany(CatalogPendingStandard::class, 'linkable');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
