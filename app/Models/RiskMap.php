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
        'title', 'status', 'version', 'pin_scale',
        'folio', 'uuid', 'sealed_at', 'seal_hash', 'sealed_by',
        'created_by_id',
    ];

    /** Tamaño del pin (clave => px de la gota). DISPLAY, no entra al hash. */
    const PIN_SCALES = ['sm' => 24, 'md' => 32, 'lg' => 42];

    public function pinPx(): int
    {
        return self::PIN_SCALES[$this->pin_scale] ?? self::PIN_SCALES['md'];
    }

    protected $casts = [
        'version'   => 'integer',
        'sealed_at' => 'datetime',
    ];

    /**
     * Etiqueta CORTA en español por `hazard_events.category` (slug del catálogo).
     * Es lo que se pinta en el pin y en la leyenda —"Riesgo eléctrico"— en vez del
     * nombre largo y específico del evento (que sigue en la lista de normas y la tabla).
     */
    const HAZARD_CATEGORY_LABELS = [
        'access'            => 'Acceso / evacuación',
        'aerial_platform'   => 'Plataforma aérea',
        'aerial_work'       => 'Trabajo aéreo',
        'animals_wrangler'  => 'Animales',
        'base_camp'         => 'Base camp',
        'biological'        => 'Riesgo biológico',
        'camera_car'        => 'Camera car',
        'camera_crane'      => 'Grúa de cámara',
        'confined'          => 'Espacio confinado',
        'crowd'             => 'Multitudes',
        'crowd_action'      => 'Escena con extras',
        'drones_uas'        => 'Dron / UAS',
        'electrical'        => 'Riesgo eléctrico',
        'electrical_water'  => 'Eléctrico + agua',
        'ev_hybrid'         => 'Vehículo eléctrico',
        'fight_combat'      => 'Combate escénico',
        'fire'              => 'Incendio / fuego',
        'fire_burn'         => 'Fuego / quemadura',
        'firearms'          => 'Armas de fuego',
        'hazmat'            => 'Materiales peligrosos',
        'heights'           => 'Altura / caída',
        'minors_physical'   => 'Menores',
        'portable_power'    => 'Energía portátil',
        'pyro_sfx'          => 'Pirotecnia / SFX',
        'railroad'          => 'Vías / tren',
        'rigging_hoist'     => 'Rigging / izaje',
        'special'           => 'Riesgo especial',
        'stabilized_rig'    => 'Rig estabilizado',
        'structural'        => 'Riesgo estructural',
        'stunts_high_fall'  => 'Caída de altura',
        'stunts_vehicular'  => 'Stunt vehicular',
        'traffic'           => 'Tránsito vehicular',
        'uncontrolled_env'  => 'Entorno no controlado',
        'utility_transport' => 'Transporte / utility',
        'water'             => 'Agua / ahogamiento',
        'water_work'        => 'Trabajo en agua',
        'weather'           => 'Clima extremo',
        'wire_work'         => 'Wire work',
    ];

    public static function hazardCategoryLabel($category): string
    {
        $c = (string) $category;
        return self::HAZARD_CATEGORY_LABELS[$c] ?? 'Peligro';
    }

    /** Clave de PICTOGRAMA (_rm-icon) por categoría del catálogo. */
    const HAZARD_ICON_KEYS = [
        'electrical' => 'haz-bolt', 'electrical_water' => 'haz-bolt', 'ev_hybrid' => 'haz-bolt', 'portable_power' => 'haz-bolt',
        'fire' => 'haz-flame', 'fire_burn' => 'haz-flame', 'pyro_sfx' => 'haz-flame',
        'heights' => 'haz-fall', 'stunts_high_fall' => 'haz-fall', 'aerial_work' => 'haz-fall', 'aerial_platform' => 'haz-fall',
        'rigging_hoist' => 'haz-fall', 'wire_work' => 'haz-fall', 'stabilized_rig' => 'haz-fall', 'camera_crane' => 'haz-fall',
        'weather' => 'haz-temp',
        'water' => 'haz-water', 'water_work' => 'haz-water',
        'traffic' => 'haz-vehicle', 'camera_car' => 'haz-vehicle', 'stunts_vehicular' => 'haz-vehicle',
        'utility_transport' => 'haz-vehicle', 'railroad' => 'haz-vehicle',
        'crowd' => 'haz-people', 'crowd_action' => 'haz-people', 'minors_physical' => 'haz-people',
        'structural' => 'haz-struct',
        'biological' => 'haz-bio', 'hazmat' => 'haz-bio',
        'animals_wrangler' => 'haz-animal',
        'drones_uas' => 'haz-drone',
        'access' => 'salida_emergencia', // evacuación → símbolo de salida
        // base_camp, firearms, fight_combat, special, uncontrolled_env, confined → genérico
    ];

    public static function hazardIconKey($category): string
    {
        return self::HAZARD_ICON_KEYS[(string) $category] ?? 'haz-warn';
    }

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

        // Fuera: volátiles + estado de emisión + realce/preferencias de display
        // (cambiar tamaño de pin o mejorar una imagen NO re-sella).
        $drop = [
            'created_at', 'updated_at', 'uuid',
            'status', 'folio', 'sealed_at', 'seal_hash', 'sealed_by', 'created_by_id',
            'pin_scale',
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
                    'x_pct'          => (string) $m->x_pct,   // DECIMAL(6,3) tal como lo guarda MySQL (POSICIÓN DEL PELIGRO = contenido)
                    'y_pct'          => (string) $m->y_pct,
                    // label_side / label_x_pct / label_y_pct = ubicación cosmética de la etiqueta → FUERA del hash
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
                    'id'       => (int) $e->id,
                    'name'     => (string) ($e->name_localized ?: $e->name_es),
                    'category' => (string) $e->category,
                    'short'    => self::hazardCategoryLabel($e->category), // etiqueta corta del pin
                    'icon'     => self::hazardIconKey($e->category),       // pictograma por categoría
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

    /**
     * Leyenda de símbolos: los tipos DISTINTOS realmente usados en el mapeo
     * (recurso por tipo · peligro por categoría), con su icono, etiqueta corta y color.
     */
    public function legendItems(): array
    {
        $elig = $this->eligibleEvents();
        $items = [];
        foreach ($this->views as $v) {
            foreach ($v->markers as $m) {
                if ($m->kind === 'resource' && $m->resource_type) {
                    $items['r:' . $m->resource_type] = [
                        'icon'  => $m->resource_type,
                        'label' => $m->resourceLabel(),
                        'color' => $m->color(),
                    ];
                } elseif ($m->kind === 'hazard' && $m->event_id) {
                    $ev = $elig->get((int) $m->event_id);
                    $short = $ev['short'] ?? 'Peligro';
                    $items['h:' . $short] = [
                        'icon'  => $ev['icon'] ?? 'haz-warn',
                        'label' => $short,
                        'color' => $m->color(),
                    ];
                }
            }
        }
        return array_values($items);
    }
}
