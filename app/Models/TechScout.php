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
        // Panel general — MISMOS nombres que el Scouting H&S a propósito: es el mismo dato del
        // mundo real, y llamarlo distinto en cada documento es cómo empiezan a divergir.
        'hero_image_path',
        // ⚠ `production_type` y `manager_name` NO están aquí a propósito. Existen como columnas
        // (se crearon en la primera versión del panel) pero el owner los retiró: «esa información
        // no es relevante aquí» — la producción y su gerente son los mismos para todas las
        // locaciones, así que repetirlos en cada documento es ruido. Fuera de $fillable para que
        // no se puedan rellenar por asignación masiva sin una decisión explícita.
        'loc_setting', 'shoot_time',
        'date_prep', 'date_shoot', 'date_shoot_end', 'date_wrap',
        // Permisos, solicitudes especiales y lo pactado entre departamentos y locaciones.
        'viability_checklist', 'agreements',
    ];

    protected $casts = [
        'date_prep'           => 'date',
        'date_shoot'          => 'date',
        'date_shoot_end'      => 'date',
        'date_wrap'           => 'date',
        'viability_checklist' => 'array',
        'agreements'          => 'array',
    ];

    /** ¿El rodaje ocupa más de un día? (rango real, no un fin igual al inicio). */
    public function hasShootRange(): bool
    {
        return $this->date_shoot
            && $this->date_shoot_end
            && $this->date_shoot_end->gt($this->date_shoot);
    }

    /** Filas de viabilidad / acuerdos, ya limpias de renglones vacíos. */
    public function rows(string $campo): array
    {
        $out = [];
        foreach ((array) ($this->{$campo} ?? []) as $r) {
            if (! is_array($r)) {
                continue;
            }
            // Un renglón sin nada escrito no es un acuerdo: ensucia el documento y hace ruido
            // en la revisión. Se descarta al leer, no al guardar, para no perder nada por error.
            if (trim((string) ($r['item'] ?? '')) === '' && trim((string) ($r['detail'] ?? '')) === '') {
                continue;
            }
            $out[] = [
                'item'   => (string) ($r['item'] ?? ''),
                'detail' => (string) ($r['detail'] ?? ''),
            ];
        }

        return $out;
    }

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
