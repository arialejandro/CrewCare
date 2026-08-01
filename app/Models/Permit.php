<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Permiso de actividad (Capa C), PER-01..PER-15. Catálogo de FONDO: se consulta,
 * no se edita. En permisos NO hay punto informativo: todo punto es compuerta
 * (`PermitPoint::is_gate` = 1, 103/103), porque no hay permiso a medias.
 *
 * `site_scope` ya viene corregido (PER-05/06/07 quedaron en `reverificacion`, no
 * `por_definir`). `ext_auth_mandatory` es el campo que luego impedirá emitir: el
 * permiso interno REGISTRA que la autorización de la autoridad existe y está
 * vigente, y NO sustituye a esa autorización.
 */
class Permit extends Model
{
    protected $table = 'permits';

    protected $guarded = ['id', 'verified_at', 'verified_by_id'];

    protected $casts = [
        'covers'             => 'array',
        'signatures'         => 'array',
        'reverify_on_move'   => 'array',
        'budget'             => 'array',
        'ext_auth_mandatory' => 'boolean',
        'is_active'          => 'boolean',
        'verified_at'        => 'datetime',
    ];

    protected static function booted()
    {
        static::deleting(function (Permit $permit) {
            $permit->pendingStandards()->delete();
        });
    }

    /** Puntos del permiso (OWNED 1:N: cada punto pertenece a un solo permiso). */
    public function points(): HasMany
    {
        return $this->hasMany(PermitPoint::class, 'permit_id');
    }

    /** Normas a nivel ÍTEM. */
    public function standards(): BelongsToMany
    {
        return $this->belongsToMany(SafetyStandard::class, 'permit_standard', 'permit_id', 'safety_standard_id')
            ->withTimestamps();
    }

    /** Herramientas que disparan este permiso (por ID). */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'permit_tool', 'permit_id', 'tool_id');
    }

    public function pendingStandards(): MorphMany
    {
        return $this->morphMany(CatalogPendingStandard::class, 'linkable');
    }

    /** ¿Requiere autorización de una autoridad externa antes de emitir? */
    public function requiresExternalAuthorization(): bool
    {
        return (bool) $this->ext_auth_mandatory;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
