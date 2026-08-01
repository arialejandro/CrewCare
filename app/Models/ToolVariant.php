<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variante de un tipo de herramienta (Capa B). La marca y el modelo NO viven aquí:
 * son dato de instancia que se adjunta en set. La variante es una sub-clase de
 * catálogo (p.ej. "Sierra de riel" bajo la sierra circular).
 */
class ToolVariant extends Model
{
    protected $table = 'tool_variants';

    protected $guarded = ['id'];

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'tool_id');
    }
}
