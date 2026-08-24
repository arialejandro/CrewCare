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

    /**
     * TODAS las claves que puede emitir _rm-icon. El editor precarga estos SVG
     * para pintarlos en el lienzo (si faltara una, el pin caía al glifo 'area').
     */
    const ICON_KEYS = [
        // recursos (símbolo blanco)
        'extintor', 'salida_emergencia', 'botiquin', 'punto_alarma', 'manguera_hidrante',
        'tablero_electrico', 'punto_reunion', 'acceso_ambulancia',
        // peligros (símbolo negro sobre amarillo)
        'haz-warn', 'haz-bolt', 'haz-flame', 'haz-fall', 'haz-fallobj', 'haz-suspended',
        'haz-collapse', 'haz-slip', 'haz-temp', 'haz-water', 'haz-vehicle', 'haz-people',
        'haz-bio', 'haz-toxic', 'haz-animal', 'haz-drone', 'haz-firearm', 'haz-explosive', 'haz-exit',
        // especiales
        'hazard', 'area',
    ];

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

    /** Clave de PICTOGRAMA (_rm-icon) por categoría del catálogo (símbolo base). */
    const HAZARD_ICON_KEYS = [
        'electrical' => 'haz-bolt', 'electrical_water' => 'haz-bolt', 'ev_hybrid' => 'haz-bolt', 'portable_power' => 'haz-bolt',
        'fire' => 'haz-flame', 'fire_burn' => 'haz-flame',
        'pyro_sfx' => 'haz-explosive',
        'heights' => 'haz-fall', 'stunts_high_fall' => 'haz-fall', 'aerial_work' => 'haz-fall',
        'aerial_platform' => 'haz-fall', 'wire_work' => 'haz-fall', 'stabilized_rig' => 'haz-fall',
        'rigging_hoist' => 'haz-suspended', 'camera_crane' => 'haz-suspended',
        'weather' => 'haz-temp',
        'water' => 'haz-water', 'water_work' => 'haz-water',
        'traffic' => 'haz-vehicle', 'camera_car' => 'haz-vehicle', 'stunts_vehicular' => 'haz-vehicle',
        'utility_transport' => 'haz-vehicle', 'railroad' => 'haz-vehicle',
        'crowd' => 'haz-people', 'crowd_action' => 'haz-people', 'minors_physical' => 'haz-people',
        'structural' => 'haz-warn',
        'biological' => 'haz-bio',
        'hazmat' => 'haz-toxic',
        'firearms' => 'haz-firearm',
        'animals_wrangler' => 'haz-animal',
        'drones_uas' => 'haz-drone',
        'access' => 'haz-exit', // evacuación bloqueada → símbolo de salida
        // base_camp, fight_combat, special, uncontrolled_env, confined → genérico (haz-warn)
    ];

    public static function hazardIconKey($category): string
    {
        return self::HAZARD_ICON_KEYS[(string) $category] ?? 'haz-warn';
    }

    /**
     * Símbolo (icono + etiqueta corta) de un peligro. Afina por PALABRA CLAVE del
     * nombre por encima de la categoría: dentro de "heights" hay caída DE PERSONAS
     * y golpe por OBJETO que cae; "structural" mezcla colapso y resbalones. Devuelve
     * ['icon','short']. DERIVADO: no toca columnas ni el sello.
     */
    public static function hazardSymbol($category, $nameEs = null, $iconOverride = null): array
    {
        $cat = (string) $category;

        // Override CURADO del catálogo (hazard_events.risk_icon): manda sobre todo.
        // El owner toma el icono; la etiqueta corta se queda en la de la categoría.
        // Acepta una clave dibujada (ICON_KEYS) O cualquier slug de la biblioteca de
        // señales (p. ej. 'adr_3b', 'wear_safety_glasses') — así las familias nuevas
        // (hazmat, EPP, prohibición) se pueden asignar por evento del catálogo.
        if ($iconOverride !== null && $iconOverride !== ''
            && (in_array($iconOverride, self::ICON_KEYS, true) || \App\Support\RiskSigns::has($iconOverride))) {
            return ['icon' => $iconOverride, 'short' => self::hazardCategoryLabel($cat)];
        }

        $name = $nameEs !== null ? self::normalizeName((string) $nameEs) : '';

        // CLIMA: el catálogo tiene UNA categoría 'weather' pero muchas señales de clima.
        // Se elige la específica por palabra clave del nombre (default: aviso general).
        if ($cat === 'weather') {
            return ['icon' => self::weatherSign($name), 'short' => self::hazardCategoryLabel($cat)];
        }

        if ($name !== '') {
            // Insectos (abejas/avispas/garrapatas): condición del lugar, señal propia.
            if (self::nameHasAny($name, ['abeja', 'avispa', 'garrapata', 'enjambre', 'insecto', 'mosquito', 'picadura'])) {
                return ['icon' => 'insectos', 'short' => self::hazardCategoryLabel($cat)];
            }
            // 'arma de fuego' (NO 'armado' de un andamio) → llaves específicas.
            if (self::nameHasAny($name, ['arma de fuego', 'disparo', 'municion', 'balac', 'proyectil', 'pistola', 'rifle', 'escopeta'])) {
                return ['icon' => 'haz-firearm', 'short' => 'Armas de fuego'];
            }
            if (self::nameHasAny($name, ['explos', 'pirotec', 'detonac', 'carga explosiva'])) {
                return ['icon' => 'haz-explosive', 'short' => 'Explosión / pirotecnia'];
            }
            if (self::nameHasAny($name, ['carga suspendida', 'izaje', 'suspendid', 'colgado y tensado', 'grua'])) {
                return ['icon' => 'haz-suspended', 'short' => 'Carga suspendida'];
            }
            if (self::nameHasAny($name, ['objeto', 'herramienta que cae', 'material que cae', 'golpe por objeto', 'que cae sobre', 'caida de herramienta', 'caida de material'])) {
                return ['icon' => 'haz-fallobj', 'short' => 'Caída de objetos'];
            }
            if (self::nameHasAny($name, ['colaps', 'estibad', 'estiba ', 'apilad'])) {
                return ['icon' => 'haz-collapse', 'short' => 'Colapso / estiba'];
            }
            if (self::nameHasAny($name, ['resbal', 'tropiez', 'tropez', 'pisada', 'orden y limpieza'])) {
                return ['icon' => 'haz-slip', 'short' => 'Resbalón / tropiezo'];
            }
        }

        return [
            'icon'  => self::HAZARD_ICON_KEYS[$cat] ?? 'haz-warn',
            'short' => self::hazardCategoryLabel($cat),
        ];
    }

    /** minúsculas + sin acentos, para cotejar palabras clave del nombre del evento. */
    private static function normalizeName(string $s): string
    {
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        return str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'à', 'è', 'ì', 'ò', 'ù'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u'],
            $s
        );
    }

    private static function nameHasAny(string $name, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($n !== '' && strpos($name, $n) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Señal de CLIMA por palabra clave del nombre (ya normalizado: minúsculas sin
     * acentos). Orden IMPORTA: lo más específico primero (torrencial antes que lluvia,
     * polvoriento antes que viento, frío antes que altitud). Default: aviso general.
     * Devuelve un slug de la biblioteca de señales (RiskSigns) — no una clave dibujada.
     */
    private static function weatherSign(string $name): string
    {
        $has = function (array $ns) use ($name) { return self::nameHasAny($name, $ns); };

        if ($has(['rayo', 'tormenta electrica', 'lightning', 'descarga atmosf'])) return 'clima_tormenta_electrica';
        if ($has(['huracan', 'ciclon', 'tifon']))                                 return 'clima_huracan';
        if ($has(['inundacion', 'inunda', 'desbordamiento', 'crecida']))          return 'clima_inundacion';
        if ($has(['granizo']))                                                    return 'clima_granizo';
        if ($has(['nevada', 'nieve', 'ventisca', 'nevado']))                      return 'clima_nevada';
        if ($has(['neblina', 'niebla', 'baja visibilidad', 'visibilidad reducida', 'bruma'])) return 'clima_neblina_o_baja_visibildad';
        if ($has(['lluvia torrencial', 'lluvias torrenciales', 'aguacero', 'diluvio'])) return 'clima_lluvias_torrenciales';
        if ($has(['lluvia intensa', 'lluvia fuerte']))                            return 'clima_lluvia_intensa';
        if ($has(['lluvia', 'precipitacion']))                                    return 'clima_lluvia';
        if ($has(['viento polvoriento', 'polvo', 'polvareda', 'tolvanera']))      return 'clima_viento_polvoriento';
        if ($has(['viento', 'ventarron', 'racha', 'vendaval']))                   return 'clima_vientos_fuertes';
        if ($has(['radiacion uv', 'rayos uv', 'ultravioleta', 'radiacion solar', 'indice uv'])) return 'clima_radiacion_uv_extrema';
        if ($has(['marea', 'oleaje', 'marejada']))                                return 'clima_marea_viva';
        if ($has(['calor', 'deshidratacion', 'insolacion', 'sofocante', 'caluroso', 'termico'])) return 'clima_calor';
        if ($has(['frio', 'hipotermia', 'congelacion', 'helada', 'gelida']))      return 'clima_frio';
        if ($has(['altitud', 'alta montana', 'gran altura', 'mal de montana']))   return 'clima_altitud';

        return 'clima_aviso_general';
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
                $sym = self::hazardSymbol($e->category, $e->name_es, $e->risk_icon); // override curado > palabra clave > categoría
                return [
                    'id'       => (int) $e->id,
                    'name'     => (string) ($e->name_localized ?: $e->name_es),
                    'category' => (string) $e->category,
                    'short'    => $sym['short'], // etiqueta corta del pin (afinada)
                    'icon'     => $sym['icon'],  // pictograma (afinado por nombre)
                    'norms' => $e->standards->map(function ($s) {
                        $code = trim(($s->regulation_badge ? $s->regulation_badge . ' ' : '') . $s->regulation_code);
                        return ['code' => $code, 'url' => $s->reference_url];
                    })->values()->all(),
                ];
            })
            ->keyBy('id');
    }

    /**
     * Pines de PELIGRO cuyo event_id YA NO está evaluado en el scouting (colgantes).
     * Deriva de eligibleEvents() en vivo; no toca columnas ni el sello. Es la señal de
     * que el scouting quitó ese peligro DESPUÉS de haberlo mapeado: el pin quedaría
     * huérfano (sin nombre ni normas) y —sin este control— su event_id entraría al hash
     * igual, certificando un peligro que ese scouting ya no evalúa. Devuelve una
     * colección de RiskMapMarker (su vista queda accesible por ->view).
     */
    public function orphanHazardMarkers(): Collection
    {
        $elig = $this->eligibleEvents();
        $out = collect();
        foreach ($this->views as $v) {
            foreach ($v->markers as $m) {
                if ($m->kind === 'hazard' && $m->event_id && ! $elig->has((int) $m->event_id)) {
                    $out->push($m);
                }
            }
        }
        return $out;
    }

    public function hasOrphanHazards(): bool
    {
        return $this->orphanHazardMarkers()->isNotEmpty();
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
                        'ink'   => $m->ink(),
                    ];
                } elseif ($m->kind === 'hazard' && $m->event_id) {
                    $ev    = $elig->get((int) $m->event_id);
                    $short = $ev['short'] ?? 'Peligro';
                    $icon  = $ev['icon'] ?? 'haz-warn';
                    // Clave por etiqueta+icono: dos glifos distintos con la misma
                    // etiqueta (p. ej. caída vs. objetos) aparecen ambos en la leyenda.
                    $items['h:' . $short . ':' . $icon] = [
                        'icon'  => $icon,
                        'label' => $short,
                        'color' => $m->color(),
                        'ink'   => $m->ink(),
                    ];
                }
            }
        }
        return array_values($items);
    }
}
