<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UNIDAD de rodaje (2026-09-06). 🔑 `unit_id` NULL en un documento = la UNIDAD PRINCIPAL (la unidad uno);
 * NO existe una fila para ella. Las unidades ADICIONALES (2ª y siguientes) SÍ tienen fila e id, y por eso
 * quedan selladas en sus documentos. Baja por DESACTIVACIÓN (is_active=0), NUNCA borrado (la FK a units
 * es ON DELETE RESTRICT). No se sella — es catálogo, no documento.
 */
class Unit extends Model
{
    protected $table = 'units';

    /** Etiqueta de la unidad principal (unit_id NULL). No es una fila: es un concepto. */
    const PRINCIPAL_LABEL = 'Unidad principal';

    protected $fillable = ['production_id', 'name', 'sort_order', 'is_active', 'created_by_id'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /** Unidades de la producción vigente (o globales, production_id NULL). Ordenadas. */
    public function scopeForProduction($query, $productionId)
    {
        return $query->where(function ($w) use ($productionId) {
            $w->whereNull('production_id')->orWhere('production_id', $productionId);
        })->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Nombre a MOSTRAR para un unit_id (NULL → principal). Fuente ÚNICA para no repetir el "NULL = principal".
     */
    public static function displayName($unitId): string
    {
        if ($unitId === null || $unitId === '') {
            return self::PRINCIPAL_LABEL;
        }
        $u = self::find($unitId);

        return $u ? $u->name : ('Unidad #' . $unitId);
    }
}
