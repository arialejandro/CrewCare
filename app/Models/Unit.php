<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /** Formato del sufijo de unidad en el título del contrato (productions.unit_label_format). */
    const LABEL_LONG  = 'long';   // "Unidad {n}"
    const LABEL_SHORT = 'short';  // "U{n}"

    /**
     * Tablas operativas que llevan `unit_id` (espejo de la migración 2026_09_05_000010). Sirven para CONTAR
     * lo que una unidad conserva al desactivarse (el aviso previo). Si esa lista crece, crece aquí también.
     */
    const DOCUMENT_TABLES = [
        'daily_reports', 'scouting_reports', 'unsafeconds', 'hazardnotifications', 'injury_reports',
        'risk_maps', 'medevac_posters', 'tool_inspections', 'ambulance_inspections',
        'emergency_action_plans', 'issued_permits', 'vehicle_inspections',
        'call_days', 'transport_orders',
    ];

    protected $fillable = ['production_id', 'name', 'number', 'sort_order', 'is_active', 'created_by_id'];

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

    /**
     * NÚMERO ESTABLE para una unidad NUEVA de la producción: el mayor asignado + 1 (mínimo 2, porque la
     * PRINCIPAL es la 1 implícita). NUNCA baja al desactivar/reordenar (el número es una columna guardada,
     * esos flujos no la tocan). Una unidad con documentos sellados no se puede borrar (FK RESTRICT), así
     * que un número que ya viajó a un contrato jamás se reutiliza.
     */
    public static function nextNumberFor($productionId): int
    {
        $max = (int) static::query()
            ->where(fn ($w) => $productionId === null ? $w->whereNull('production_id') : $w->where('production_id', $productionId))
            ->max('number');

        return max(1, $max) + 1;
    }

    /**
     * Sufijo de unidad para el TÍTULO del contrato. Principal (número null o 1) → cadena vacía: el
     * silencio significa principal. Formato por producción: "Unidad {n}" (largo) o "U{n}" (corto).
     */
    public static function contractLabel(?int $number, string $format = self::LABEL_LONG): string
    {
        if ($number === null || $number <= 1) {
            return '';
        }

        return $format === self::LABEL_SHORT ? ('U' . $number) : ('Unidad ' . $number);
    }

    /** Formato de etiqueta elegido por la producción (degrade-safe → largo). */
    public static function labelFormatFor($productionId): string
    {
        if (! $productionId || ! Schema::hasColumn('productions', 'unit_label_format')) {
            return self::LABEL_LONG;
        }
        $v = DB::table('productions')->where('id', $productionId)->value('unit_label_format');

        return $v === self::LABEL_SHORT ? self::LABEL_SHORT : self::LABEL_LONG;
    }

    /**
     * Cuántos registros operativos llevan ESTA unidad sellada. Al desactivar, esos documentos se CONSERVAN
     * intactos (nada se borra, la FK es RESTRICT); el número es la garantía que ve el aviso previo. Sólo una
     * unidad ADICIONAL tiene documentos propios (la principal es unit_id NULL). Degrade-safe por tabla.
     */
    public function documentCount(): int
    {
        $total = 0;
        foreach (self::DOCUMENT_TABLES as $t) {
            try {
                if (Schema::hasTable($t) && Schema::hasColumn($t, 'unit_id')) {
                    $total += DB::table($t)->where('unit_id', $this->id)->count();
                }
            } catch (\Throwable $e) {
                // degrade-safe: una tabla ausente no rompe el conteo
            }
        }

        return $total;
    }
}
