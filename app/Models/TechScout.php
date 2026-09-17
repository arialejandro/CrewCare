<?php

namespace App\Models;

use App\Traits\GeneratesUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TECH SCOUT — recorrido técnico del departamento de Locaciones.
 *
 * Documento de TRABAJO: foto + nota de lo que hay que resolver en la locación. No se sella y no
 * se borra (sirve de consulta: «si no está en las notas, no se pidió»). Distinto del Scouting
 * H&S, que evalúa riesgos y sí es sellable — ver la migración para el porqué de la separación.
 *
 * El documento NO guarda contenido editable por varias personas: sólo la cabecera (locación,
 * dirección). Todo lo que se captura vive en [[TechScoutNote]], una por nota, de quien la puso.
 * Así dos scouters trabajan a la vez sin pisarse.
 */
class TechScout extends Model
{
    use GeneratesUuidKey;

    protected $table = 'tech_scouts';

    protected $fillable = [
        'production_id', 'unit_id', 'location_name', 'location_address',
        'latitude', 'longitude', 'created_by_id',
    ];

    public function notes(): HasMany
    {
        // CRONOLÓGICO por captura: es el orden del recorrido, y el único que generaliza entre una
        // casa y una bodega. Se descartó agrupar por área por eso mismo.
        return $this->hasMany(TechScoutNote::class, 'tech_scout_id')->orderBy('created_at');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** Etiqueta de guión usada más recientemente en este documento (para prellenar la siguiente). */
    public function lastStoryLabel(): ?string
    {
        $last = $this->notes()->whereNotNull('story_label')
            ->where('story_label', '!=', '')
            ->latest('id')->first(['story_label']);

        return $last->story_label ?? null;
    }
}
