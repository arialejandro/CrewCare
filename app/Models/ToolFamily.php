<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Familia de herramienta (Capa B). Catálogo de FONDO: se consulta, no se edita.
 * 11 familias, clave estable en `family_key` (la fuente guarda el nombre visible;
 * el importador mapea nombre→clave y persiste la clave).
 */
class ToolFamily extends Model
{
    protected $table = 'tool_families';

    protected $guarded = ['id'];

    protected $casts = [
        'is_energized' => 'boolean',
        'is_active'    => 'boolean',
    ];

    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class, 'tool_family_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
