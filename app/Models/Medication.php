<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Medication — catálogo de medicamentos (variantes) del módulo de consultas médicas.
 * Una fila por VARIANTE = nombre × dosis × presentación (Paracetamol 250mg Tableta ≠
 * Paracetamol 500mg Efervescente). Crece en modo híbrido: si el médico registra una
 * variante nueva en la consulta, se da de alta aquí para reutilizarla y para poder
 * contabilizarla de forma consistente (presupuesto / materialidad).
 */
class Medication extends Model
{
    protected $table = 'medications';

    protected $fillable = ['name', 'dosage', 'presentation', 'active'];

    protected $casts = ['active' => 'boolean'];

    /** Presentaciones disponibles (fuente única para el form de consulta y reportes). */
    public const PRESENTATIONS = [
        'Tableta', 'Cápsula', 'Comprimido', 'Efervescente', 'Jarabe', 'Suspensión',
        'Gotas', 'Ampolleta', 'Sublingual', 'Gel/Tópico', 'Solución', 'Spray', 'Supositorio', 'Otro',
    ];

    /** Etiqueta legible: "Paracetamol 500mg Tableta". */
    public function getLabelAttribute()
    {
        return trim(implode(' ', array_filter([$this->name, $this->dosage, $this->presentation])));
    }
}
