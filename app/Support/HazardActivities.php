<?php

namespace App\Support;

use App\Models\HazardEvent;

/**
 * FUENTE ÚNICA de la agrupación por ACTIVIDAD (delta #49, Paso 1 de captura fluida).
 *
 * El safety piensa por ACTIVIDAD ("hoy hay SFX y stunts"), no por categoría de peligro.
 * Aquí se agrupan las ~38 categorías del catálogo en 12 ACTIVIDADES (aprobado por el
 * owner 2026-08-01, con "todo lo de cámara en rigging"). NO renombra ni inventa
 * categorías: solo las ordena. El catálogo completo sigue alcanzable.
 *
 * Esta clase es la fuente ÚNICA para evitar que dos implementaciones divergan (como ya
 * pasó con el cintillo y el store() espejo del DSR). El componente compartido de
 * selección por actividad la consume; los eventos sin categoría mapeada caen en "otros"
 * (solo alcanzables por el catálogo completo, nunca escondidos).
 */
class HazardActivities
{
    /**
     * 12 actividades → categorías que agrupan. Orden = orden de presentación.
     * Las claves de categoría existen en HazardEvent::categories() / hazard_events.category.
     */
    const MAP = [
        'sfx_pyro'         => ['es' => 'SFX y pirotecnia',        'en' => 'SFX & pyrotechnics',   'categories' => ['pyro_sfx', 'fire_burn', 'firearms']],
        'stunts'           => ['es' => 'Stunts',                  'en' => 'Stunts',               'categories' => ['stunts_vehicular', 'stunts_high_fall', 'wire_work', 'fight_combat']],
        'aerial_drones'    => ['es' => 'Aéreo y drones',          'en' => 'Aerial & drones',      'categories' => ['aerial_work', 'drones_uas']],
        'water'            => ['es' => 'Agua',                    'en' => 'Water',                'categories' => ['water', 'water_work', 'electrical_water']],
        'heights_rigging'  => ['es' => 'Altura y rigging',        'en' => 'Heights & rigging',    'categories' => ['heights', 'rigging_hoist', 'aerial_platform', 'camera_crane', 'camera_car', 'stabilized_rig']],
        'electrical'       => ['es' => 'Eléctrico',               'en' => 'Electrical',           'categories' => ['electrical', 'portable_power']],
        'vehicles'         => ['es' => 'Vehículos y tráfico',     'en' => 'Vehicles & traffic',   'categories' => ['traffic', 'utility_transport', 'ev_hybrid', 'railroad']],
        'animals'          => ['es' => 'Animales',                'en' => 'Animals',              'categories' => ['biological', 'animals_wrangler']],
        'crowds'           => ['es' => 'Multitudes y figuración', 'en' => 'Crowds & background',  'categories' => ['crowd', 'crowd_action', 'minors_physical', 'uncontrolled_env']],
        'construction_art' => ['es' => 'Construcción y arte',     'en' => 'Construction & art',   'categories' => ['structural', 'confined']],
        'base_camp'        => ['es' => 'Base camp y logística',   'en' => 'Base camp & logistics', 'categories' => ['base_camp']],
        'location_general' => ['es' => 'General de locación',     'en' => 'Location general',     'categories' => ['access', 'fire', 'weather', 'hazmat', 'special']],
    ];

    /** Clave de la cubeta "otros": eventos sin categoría mapeada (incl. category NULL). */
    const OTHER_KEY = 'otros';

    /** Lista de actividades [clave => etiqueta localizada], en orden. */
    public static function list($lang = 'es')
    {
        $out = [];
        foreach (self::MAP as $key => $meta) {
            $out[$key] = $meta[$lang === 'en' ? 'en' : 'es'];
        }
        return $out;
    }

    /**
     * Las 38 categorías (con su etiqueta localizada) agrupadas bajo sus 12 actividades,
     * en el orden de MAP. Fuente única para plegar rejillas por actividad (DSR "Temas
     * Tratados"). Las categorías sin actividad mapeada caen en 'otros'.
     *
     * @return array<string, array{label:string, categories:array<string,string>}>
     */
    public static function categoriesGrouped($lang = 'es')
    {
        $catLabels = \App\Models\HazardEvent::categoriesLocalized();
        $out = [];
        $mapped = [];
        foreach (self::MAP as $actKey => $meta) {
            $cats = [];
            foreach ($meta['categories'] as $ck) {
                $mapped[$ck] = true;
                if (isset($catLabels[$ck])) { $cats[$ck] = $catLabels[$ck]; }
            }
            if ($cats) {
                $out[$actKey] = ['label' => self::label($actKey, $lang), 'categories' => $cats];
            }
        }
        $others = [];
        foreach ($catLabels as $ck => $label) {
            if (!isset($mapped[$ck])) { $others[$ck] = $label; }
        }
        if ($others) {
            $out[self::OTHER_KEY] = ['label' => self::label(self::OTHER_KEY, $lang), 'categories' => $others];
        }
        return $out;
    }

    public static function label($key, $lang = 'es')
    {
        if ($key === self::OTHER_KEY) {
            return $lang === 'en' ? 'Other / full catalog' : 'Otros / catálogo completo';
        }
        if (!isset(self::MAP[$key])) {
            return $key;
        }
        return self::MAP[$key][$lang === 'en' ? 'en' : 'es'];
    }

    /**
     * Actividad a la que pertenece una categoría, o null si ninguna la agrupa
     * (esos eventos viven solo en el catálogo completo). Cacheado por request.
     */
    public static function activityForCategory($category)
    {
        static $index = null;
        if ($index === null) {
            $index = [];
            foreach (self::MAP as $key => $meta) {
                foreach ($meta['categories'] as $cat) {
                    $index[$cat] = $key;
                }
            }
        }
        if ($category === null || $category === '') {
            return null;
        }
        return $index[$category] ?? null;
    }

    /**
     * Payload enriquecido de un catálogo de eventos para el componente compartido.
     * Cada evento: id, code, name, category, activity (o 'otros'), control PROPUESTO
     * (localizado, '' si no hay), EPP sugerido, y normas [{badge,code,url}] para citar.
     *
     * @param  \Illuminate\Support\Collection|iterable $events  (con relación 'standards' cargada)
     * @param  string $lang
     * @return array
     */
    public static function catalogPayload($events, $lang = 'es')
    {
        $out = [];
        foreach ($events as $ev) {
            $activity = self::activityForCategory($ev->category);
            $name = $lang === 'en'
                ? (trim((string) ($ev->name_en ?? '')) ?: $ev->name_es)
                : $ev->name_es;

            $norms = [];
            $standards = $ev->relationLoaded('standards') ? $ev->standards : $ev->standards()->get();
            foreach ($standards as $st) {
                $norms[] = [
                    'badge' => (string) $st->regulation_badge,
                    'code'  => (string) $st->regulation_code,
                    'url'   => (string) ($st->reference_url ?? ''),
                ];
            }

            $out[] = [
                'id'       => $ev->id,
                'code'     => (string) $ev->code,
                'name'     => (string) $name,
                'category' => (string) ($ev->category ?? ''),
                'activity' => $activity ?: self::OTHER_KEY,
                'control'  => $ev->controlMeasure($lang),   // '' si no hay medida redactada
                'ppe'      => is_array($ev->required_ppe) ? array_values($ev->required_ppe) : [],
                'norms'    => $norms,
                'l'        => (string) ($ev->default_likelihood ?? ''),
                'c'        => (string) ($ev->default_consequence ?? ''),
            ];
        }
        return $out;
    }
}
