<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Término indicador (delta #45): una señal (nombre de medicamento o palabra de diagnóstico)
 * que apunta a un GRUPO de salud. NO es un catálogo cerrado de medicamentos — es un diccionario
 * corto y ajustable contra el que el panel de vigilancia resuelve las consultas.
 *
 * `is_clinician_verified` = 0 hasta que un médico lo revise: nace como PROPUESTA, el sistema no
 * finge que ya fue validado.
 */
class IndicatorTerm extends Model
{
    protected $table = 'indicator_terms';

    /** Los 6 grupos indicadores (clave normalizada). */
    const GROUPS = [
        'gastrointestinal'    => 'Gastrointestinal',
        'respiratorio'        => 'Respiratorio',
        'dermico'             => 'Dérmico y heridas',
        'alergico'            => 'Alérgico',
        'oftalmico'           => 'Oftálmico',
        'musculoesqueletico'  => 'Musculoesquelético',
    ];

    const KIND_MEDICATION = 'medicamento';  // se coteja contra medication_items[].name
    const KIND_DIAGNOSIS  = 'diagnostico';  // se coteja contra diagnosis

    protected $fillable = [
        'group_key', 'match_kind', 'term', 'display_term',
        'is_active', 'is_clinician_verified', 'source_note',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'is_clinician_verified' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /** Etiqueta legible de un grupo (o la clave si es desconocida). */
    public static function groupLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return 'Sin clasificar';
        }
        return self::GROUPS[$key] ?? $key;
    }
}
