<?php

namespace App\Models;

use App\Traits\GeneratesUuidKey;
use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * MAPEO DE RIESGOS Y RECURSOS (2026-08-03 · delta #50) — el DOCUMENTO.
 *
 * Editor APARTE (no vive dentro del scouting). Referencia un scouting en SOLO
 * LECTURA: de él toma sus IMÁGENES y sus EVENTOS YA EVALUADOS en el risk
 * assessment. Al colocar un peligro solo pueden citarse esos eventos (de ahí
 * salen la norma y su URL). El scouting no se modifica desde aquí.
 *
 * SELLO (HasDigitalSignatures): el SHA-256 se calcula sobre el DATO —vistas,
 * marcadores, coordenadas, event_ids y narrativas— NO sobre el render. Por eso
 * canonicalSignaturePayload() se SOBREESCRIBE para INCLUIR las tablas hijas: el
 * hash de attributesToArray() por sí solo no vería un marcador movido. El realce
 * (`image_enhanced_path`) queda FUERA del hash: mejorar una imagen no invalida el
 * sello. El estado de emisión (status/folio/sealed_*) tampoco entra al hash.
 *
 * Emisiones INDEPENDIENTES (como el póster MEDEVAC): sin cadena/sustituye-a → no
 * implementa sealRetirement() → el verificador lo lee en 2 estados: vigente/alterado.
 */
class RiskMap extends Model
{
    use HasDigitalSignatures, GeneratesUuidKey;

    protected $table = 'risk_maps';

    protected $fillable = [
        'scouting_id', 'project_id', 'location_id',
        'title', 'status', 'version',
        'folio', 'uuid', 'sealed_at', 'seal_hash', 'sealed_by',
        'created_by_id',
    ];

    protected $casts = [
        'version'   => 'integer',
        'sealed_at' => 'datetime',
    ];

    /* ------------------------------------------------------------------ */
    /* Relaciones                                                          */
    /* ------------------------------------------------------------------ */

    /** Páginas del documento (una por vista), ordenadas. */
    public function views()
    {
        return $this->hasMany(RiskMapView::class, 'risk_map_id')
            ->orderBy('sort_order')->orderBy('id');
    }

    /** Scouting de origen (SOLO LECTURA: nunca se escribe desde este módulo). */
    public function scouting()
    {
        return $this->belongsTo(ScoutingReport::class, 'scouting_id');
    }

    /* ------------------------------------------------------------------ */
    /* Estado / folio                                                      */
    /* ------------------------------------------------------------------ */

    public function isSealed(): bool
    {
        return $this->status === 'sealed';
    }

    /** Folio legible; manda sobre el patrón genérico del verificador. */
    public function folio(): string
    {
        return 'RMAP-' . str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }

    /** Nombre de la locación (vive en el scouting, solo lectura). */
    public function locationName(): string
    {
        return (string) (optional($this->scouting)->location_name ?: 'Locación');
    }

    /* ------------------------------------------------------------------ */
    /* SELLO — payload que INCLUYE las tablas hijas                        */
    /* ------------------------------------------------------------------ */

    /**
     * Payload determinista para el hash. Toma las columnas propias de CONTENIDO
     * (no las de emisión) y les anexa, de forma canónica, las vistas y sus
     * marcadores. Sin esto el sello no cubriría coordenadas ni narrativas.
     */
    public function canonicalSignaturePayload(): array
    {
        $payload = $this->attributesToArray();

        // Fuera: volátiles + estado de emisión + realce (mejorar imagen no re-sella).
        $drop = [
            'created_at', 'updated_at', 'uuid',
            'status', 'folio', 'sealed_at', 'seal_hash', 'sealed_by', 'created_by_id',
        ];
        foreach ($drop as $k) {
            unset($payload[$k]);
        }

        $views = [];
        foreach ($this->views()->with(['markers' => function ($q) {
            $q->orderBy('sort_order')->orderBy('id');
        }])->get() as $v) {
            $markers = [];
            foreach ($v->markers as $m) {
                $markers[] = [
                    'kind'           => (string) $m->kind,
                    'resource_type'  => $m->resource_type !== null ? (string) $m->resource_type : null,
                    'event_id'       => $m->event_id !== null ? (int) $m->event_id : null,
                    'x_pct'          => (string) $m->x_pct,   // DECIMAL(6,3) tal como lo guarda MySQL
                    'y_pct'          => (string) $m->y_pct,
                    'label_side'     => (string) $m->label_side,
                    'reference_text' => $m->reference_text !== null ? (string) $m->reference_text : null,
                    'polygon'        => $m->polygon,          // array|null (fuera de alcance hoy)
                    'sort_order'     => (int) $m->sort_order,
                ];
            }
            $views[] = [
                'view_type'           => (string) $v->view_type,
                'label'               => $v->label !== null ? (string) $v->label : null,
                'image_original_path' => (string) $v->image_original_path,  // qué imagen (el realce NO)
                'image_source'        => (string) $v->image_source,
                'narrative_what'      => $v->narrative_what !== null ? (string) $v->narrative_what : null,
                'narrative_decision'  => $v->narrative_decision !== null ? (string) $v->narrative_decision : null,
                'narrative_action'    => $v->narrative_action !== null ? (string) $v->narrative_action : null,
                'sort_order'          => (int) $v->sort_order,
                'markers'             => $markers,
            ];
        }

        $payload['_views'] = $views;

        $this->ksortRecursive($payload);

        return $payload;
    }

    /* ------------------------------------------------------------------ */
    /* Derivados del scouting (solo lectura) y de la página final          */
    /* ------------------------------------------------------------------ */

    /**
     * Eventos ELEGIBLES: los ya evaluados en el risk_assessment del scouting,
     * con nombre y normas (código + URL). Es la ÚNICA fuente de peligros del
     * mapeo (no hay captura libre). Keyed por id de evento.
     */
    public function eligibleEvents(): Collection
    {
        $sc = $this->scouting;
        if (! $sc) {
            return collect();
        }
        $ra = $sc->risk_assessment;
        if (is_string($ra)) {
            $ra = json_decode($ra, true);
        }
        if (! is_array($ra)) {
            return collect();
        }

        $ids = [];
        foreach ($ra as $h) {
            if (is_array($h) && ! empty($h['event_id'])) {
                $ids[] = (int) $h['event_id'];
            }
        }
        $ids = array_values(array_unique($ids));
        if (! $ids) {
            return collect();
        }

        return HazardEvent::with('standards')->whereIn('id', $ids)->get()
            ->map(function ($e) {
                return [
                    'id'    => (int) $e->id,
                    'name'  => (string) ($e->name_localized ?: $e->name_es),
                    'norms' => $e->standards->map(function ($s) {
                        $code = trim(($s->regulation_badge ? $s->regulation_badge . ' ' : '') . $s->regulation_code);
                        return ['code' => $code, 'url' => $s->reference_url];
                    })->values()->all(),
                ];
            })
            ->keyBy('id');
    }

    /**
     * Inventario CONTADO de recursos por tipo, sumando todas las vistas.
     * Devuelve las 8 llaves SIEMPRE (los ceros se imprimen).
     */
    public function resourceInventory(): array
    {
        $counts = array_fill_keys(array_keys(RiskMapMarker::RESOURCE_TYPES), 0);
        foreach ($this->views as $v) {
            foreach ($v->markers as $m) {
                if ($m->kind === 'resource' && isset($counts[$m->resource_type])) {
                    $counts[$m->resource_type]++;
                }
            }
        }
        return $counts;
    }

    /**
     * Filas de la tabla de peligros de la página final: evento · vista · normas(url).
     * Una fila por (vista, evento). Deriva del catálogo; nada se captura.
     */
    public function hazardRows(): array
    {
        $elig = $this->eligibleEvents();
        $rows = [];
        $seen = [];
        foreach ($this->views as $v) {
            foreach ($v->markers as $m) {
                if ($m->kind !== 'hazard' || ! $m->event_id) {
                    continue;
                }
                $key = $v->id . ':' . $m->event_id;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $ev = $elig->get((int) $m->event_id);
                $rows[] = [
                    'event' => $ev['name'] ?? ('#' . $m->event_id),
                    'view'  => $v->displayLabel(),
                    'norms' => $ev['norms'] ?? [],
                ];
            }
        }
        return $rows;
    }
}
