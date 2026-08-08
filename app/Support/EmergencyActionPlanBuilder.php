<?php

namespace App\Support;

use App\Models\RiskMap;
use App\Models\ScoutingReport;

/**
 * EmergencyActionPlanBuilder — el MOTOR (solo lectura) del PAE (2026-08-06).
 *
 * Hermano de MedevacPosterBuilder: mismo patrón "leer fuente → congelar payload
 * versionado → sellar como documento". Recibe la(s) LOCACIÓN(es) elegida(s) (1 ó 2,
 * en el orden capturado), el organigrama YA confirmado por el emisor y las opciones
 * de emisión; devuelve el PAYLOAD que EmergencyActionPlan congela y sella. No pinta
 * nada, no guarda nada.
 *
 * ── DOS REGLAS QUE MANDAN (idénticas al MEDEVAC) ─────────────────────────────────
 * 1. NO INVENTAR. Un campo vacío se queda vacío: su bloque no aparece en el PAE. No
 *    se rellena con "N/D" ni con el valor de otra locación. Los únicos derivados son
 *    los links de Google Maps (punteros deterministas a coords/dirección capturadas).
 * 2. CONGELAR. Lo que sale de aquí es la fotografía del documento. Si mañana cambia
 *    el scouting o el mapa de riesgos, el PAE emitido sigue diciendo lo que dijo.
 *
 * ── RIESGOS DEL DÍA (decisión del owner: híbrido + opción de embeber vistas) ──────
 * Por locación se congela un RESUMEN de los peligros YA EVALUADOS en el scouting
 * (scouting_reports.risk_assessment — la MISMA fuente que cita el mapa de riesgos),
 * así el PAE se lee solo aunque no exista un mapa emitido. Si además hay un MAPA de
 * riesgos SELLADO de esa locación, se referencia por folio + QR; y si el emisor lo
 * pidió (embed_map_views), se EMBEBEN sus vistas anotadas como data-URIs congelados
 * (entran al hash → offline y a prueba de manipulación).
 */
class EmergencyActionPlanBuilder
{
    /** Versión del cálculo. Viaja en el payload congelado. Subirla si cambia el SIGNIFICADO.
     *   v2 (2026-08-08 · Parte D): +day_resource (badge del recurso de traslado del día). Additivo:
     *   los PAE v1 no lo traen y la vista cae a null (no se pinta la tarjeta). */
    const VERSION = 2;

    /**
     * Construye el payload congelado del PAE.
     *
     * @param  array|\Illuminate\Support\Collection $scoutings  1 ó 2 ScoutingReport, en el orden elegido
     * @param  array $opts  ['shoot_day','plan_date','unit_name','contacts'(4 slots),'move_time','embed_map_views'(bool)]
     * @return array
     */
    public static function build($scoutings, array $opts = []): array
    {
        $list = [];
        foreach ($scoutings as $s) {
            if ($s instanceof ScoutingReport) {
                $list[] = $s;
            }
        }

        $embed  = ! empty($opts['embed_map_views']);
        $isMove = count($list) > 1;

        // Proyecto: el del primer scouting si lo trae; si no, la producción vigente. Se
        // CONGELA como respaldo, pero la vista lo presenta EN VIVO con brand_name (misma
        // convención que el MEDEVAC y los reportes: el nombre de proyecto puede cambiar por
        // confidencialidad y debe reflejarse en TODO documento; el sello protege el contenido).
        $project = '';
        if (! empty($list)) {
            $project = self::str($list[0]->production_name);
        }
        if ($project === '') {
            $prod = CurrentProduction::get();
            $project = $prod ? self::str($prod->name) : '';
        }

        // Imagen principal del hero: la foto de portada del PRIMER scouting (misma que su
        // propia ficha). '' si no hay → el hero sale sin fondo (nunca se inventa una imagen).
        $mainImage = ! empty($list) ? self::str($list[0]->main_image_path) : '';

        // Datos del LLAMADO para el hero (del 1er scouting): tipo (Int./Ext. + día/noche),
        // escenas y fecha de rodaje — como la cabecera del scouting. Vacío = no se imprime.
        $call = ['setting' => '', 'shoot_time' => '', 'scenes' => '', 'shoot_date' => ''];
        if (! empty($list)) {
            $s0 = $list[0];
            $call['setting']    = self::str($s0->loc_setting);
            $call['shoot_time'] = self::str($s0->shoot_time);
            $call['scenes']     = self::str($s0->scene);
            $call['shoot_date'] = $s0->date_shoot
                ? \Illuminate\Support\Carbon::parse($s0->date_shoot)->toDateString() : '';
        }

        $locations = [];
        $seq = 0;
        $scoutingIds = [];
        foreach ($list as $s) {
            $seq++;
            $scoutingIds[] = (int) $s->id;
            $locations[] = self::locationBlock($s, $seq, $embed);
        }

        return [
            'version' => self::VERSION,

            // Insumos de la EMISIÓN (para poder RE-EMITIR/editar: se releen frescos al versionar).
            'scouting_ids'    => $scoutingIds,
            'embed_map_views' => $embed,

            // 1 · CABECERA DEL LLAMADO.
            'header' => [
                'shoot_day'  => isset($opts['shoot_day']) && $opts['shoot_day'] !== '' ? (int) $opts['shoot_day'] : null,
                'date'       => self::str($opts['plan_date'] ?? ''),
                'project'    => $project,
                'unit'       => self::str($opts['unit_name'] ?? ''),
                'main_image' => $mainImage,
                'call'       => $call,   // tipo de llamado + escenas + fecha de rodaje (del 1er scouting)
            ],

            // 2 · ORGANIGRAMA DE EMERGENCIA (una sola vez — mismo crew ese día).
            'org' => [
                'crew'     => self::cleanContacts((array) ($opts['contacts'] ?? [])),
                'services' => [PaeOrgChart::EMERGENCY_SERVICE],   // 911 (el hospital va por locación)
            ],

            // Company move: misma unidad que se mueve → UN organigrama, N bloques de locación.
            'company_move' => [
                'is_move'   => $isMove,
                'move_time' => $isMove ? self::str($opts['move_time'] ?? '') : '',
            ],

            // (Parte D) RECURSO DE TRASLADO DEL DÍA — badge congelado (ambulancia en sitio con su
            // acta/veredicto, medio declarado, o hueco). Uno por producción+día; NO sobreclama el
            // cotejo de tripulación ("documentos revisados", nunca "contra registro").
            'day_resource' => AmbulanceResourceBadge::forDay(
                ! empty($list) ? $list[0]->production_id : null,
                (isset($opts['shoot_day']) && $opts['shoot_day'] !== '') ? (int) $opts['shoot_day'] : null
            ),

            // 3 · UN BLOQUE POR LOCACIÓN (se repite; en el orden capturado).
            'locations' => $locations,
        ];
    }

    /**
     * Bloque congelado de una locación: hospital + distancia/ETA + mapa + accesos +
     * RIESGOS del día + (opcional) referencia/vistas del mapa de riesgos sellado.
     */
    private static function locationBlock(ScoutingReport $s, int $seq, bool $embed): array
    {
        $lat = self::num($s->latitude);
        $lng = self::num($s->longitude);
        $hasGps = $lat !== null && $lng !== null;

        $hospitalName    = self::str($s->nearest_hospital);
        $hospitalAddress = self::str($s->hospital_address);

        return [
            'seq'     => $seq,
            'name'    => self::str($s->location_name),
            'address' => self::str($s->location_address),

            'gps'      => $hasGps ? ['lat' => $lat, 'lng' => $lng] : null,
            'maps_url' => $hasGps ? ('https://www.google.com/maps?q=' . $lat . ',' . $lng) : '',

            'hospital' => [
                'name'        => $hospitalName,
                'address'     => $hospitalAddress,
                'distance_km' => self::str($s->hospital_distance_km),   // '' si NULL → no aparece
                'eta'         => self::str($s->hospital_eta),
                'maps_url'    => self::hospitalMapsUrl($hasGps, $lat, $lng, $hospitalName, $hospitalAddress),
            ],

            'assembly_point'    => self::str($s->assembly_point),
            'emergency_access'  => self::str($s->emergency_access),
            'ambulance_company' => self::str($s->ambulance_company),
            'emergency_phone'   => self::str($s->emergency_phone),

            // Mapa de la ruta guardado en el scouting (data-URI), congelado. Vacío = no se imprime.
            'route_map' => self::routeMap($s),

            // RIESGOS del día: resumen de los peligros ya evaluados en el scouting.
            'risks' => self::riskSummary($s),

            // Referencia al mapa de riesgos SELLADO de esta locación (+ vistas si se pidió).
            'riskmap' => self::riskMapRef($s, $embed),
        ];
    }

    /**
     * RESUMEN de riesgos desde scouting_reports.risk_assessment (la misma fuente que el
     * mapa). Sólo filas con un peligro real; nada inventado. Congela lo mínimo para
     * leerse corriendo: peligro, categoría, nivel y la norma con su URL.
     */
    private static function riskSummary(ScoutingReport $s): array
    {
        $rows = $s->risk_assessment;
        if (! is_array($rows)) {
            return [];
        }

        // Normas por evento resueltas en UNA sola consulta (mismo camino que el scouting:
        // HazardEvent::with('standards')). Sólo las filas con event_id llevan norma; el
        // texto libre NO adivina norma por su nombre (regla de honestidad del owner).
        $eventIds = [];
        foreach ($rows as $r) {
            if (is_array($r) && ! empty($r['event_id'])) {
                $eventIds[(int) $r['event_id']] = (int) $r['event_id'];
            }
        }
        $eventsById = collect();
        if ($eventIds && \Illuminate\Support\Facades\Schema::hasTable('hazard_events')) {
            $eventsById = \App\Models\HazardEvent::with('standards')
                ->whereIn('id', array_values($eventIds))
                ->get()->keyBy('id');
        }

        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $hazard = self::str($r['event_name'] ?? ($r['hazard'] ?? ''));
            $cat    = self::str($r['key'] ?? '');
            if ($hazard === '' && $cat === '') {
                continue;   // fila vacía → no se inventa
            }

            $eventId = ! empty($r['event_id']) ? (int) $r['event_id'] : null;
            [$standards, $overflow] = ($eventId && $eventsById->has($eventId))
                ? self::resolveStandards($eventsById->get($eventId))
                : [[], 0];

            $out[] = [
                'hazard'         => $hazard,
                'category'       => $cat,
                'category_label' => $cat !== '' ? RiskMap::hazardCategoryLabel($cat) : '',
                'rating'         => self::str($r['rating'] ?? ''),
                'residual'       => self::str($r['residual'] ?? ''),
                'control'        => self::str($r['control'] ?? ''),       // Medida de control (del scouting)
                'responsable'    => self::str($r['personnel'] ?? ''),     // Responsable (del scouting)
                'event_id'       => $eventId,
                // Normas N:M del evento, CONGELADAS (CSATF primero, tope 3). Vacío sin evento.
                'standards'      => $standards,
                'standards_more' => $overflow,   // cuántas quedaron fuera del tope de 3 (0 normalmente)
                // Snapshot plano de la norma principal (compat/legado). Sólo con evento;
                // sin event_id se deja vacío para no pintar un badge que nadie clasificó.
                'badge'          => $eventId ? self::str($r['badge'] ?? '') : '',
                'code'           => $eventId ? self::str($r['code'] ?? '') : '',
                'url'            => $eventId ? self::str($r['url'] ?? '') : '',
                'unclassified'   => ! empty($r['unclassified']),
            ];
        }
        return $out;
    }

    /**
     * Normas del evento → lista congelada [ ['badge','code','url'], ... ] con CSATF PRIMERO
     * y tope de 3. Devuelve [lista, sobrantes]. Mismo origen que el scouting ($event->standards).
     * Partición manual (no usort): PHP 7.4 no garantiza orden estable en usort.
     */
    private static function resolveStandards(\App\Models\HazardEvent $event): array
    {
        $csatf = [];
        $rest  = [];
        foreach ($event->standards as $std) {
            $row = [
                'badge' => self::str($std->regulation_badge),
                'code'  => self::str($std->regulation_code),
                'url'   => self::str($std->reference_url ?? ''),
            ];
            if ($row['badge'] === 'CSATF') {
                $csatf[] = $row;
            } else {
                $rest[] = $row;
            }
        }
        $all      = array_merge($csatf, $rest);
        $overflow = max(0, count($all) - 3);
        return [array_slice($all, 0, 3), $overflow];
    }

    /**
     * Referencia al MAPA DE RIESGOS sellado de esta locación (por scouting). null si no
     * hay ninguno. Si $embed, además congela las VISTAS anotadas como data-URIs.
     */
    private static function riskMapRef(ScoutingReport $s, bool $embed)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('risk_maps')) {
            return null;
        }
        $map = RiskMap::where('scouting_id', $s->id)
            ->where('status', 'sealed')
            ->latest('id')->first();
        if (! $map) {
            return null;   // sin mapa sellado → no se referencia (vacío = vacío)
        }

        $ref = [
            'folio'      => $map->folio(),
            'verify_url' => (string) (\App\Support\SealVerifier::urlFor($map) ?: ''),
            'views'      => [],
        ];

        if ($embed) {
            foreach ($map->views()->orderBy('sort_order')->orderBy('id')->get() as $v) {
                $uri = self::imageToDataUri($v->imageUrl());
                if ($uri !== '') {
                    $ref['views'][] = [
                        'label' => $v->displayLabel(),
                        'image' => $uri,
                    ];
                }
            }
        }
        return $ref;
    }

    /**
     * Mapa de la ruta al hospital guardado en el scouting (data-URI). Sólo se acepta un
     * data-URI de imagen; cualquier otra cosa → '' (vacío = no se imprime, nunca se inventa).
     */
    private static function routeMap(ScoutingReport $s): string
    {
        // 1) Mapa de la ruta guardado en el scouting.
        $candidate = self::str($s->hospital_map ?? '');
        if (strpos($candidate, 'data:image') === 0) {
            return $candidate;
        }
        // 2) Si no, el mapa del MEDEVAC sellado de esta locación (owner 2026-08-06:
        //    "si hay mapa en el MEDEVAC lo colocamos"). Se congela igual que todo lo demás.
        if (\Illuminate\Support\Facades\Schema::hasTable('medevac_posters')) {
            $poster = \App\Models\MedevacPoster::where('scouting_report_id', $s->id)
                ->where('is_active', 1)->latest('id')->first();
            if ($poster) {
                $mi = self::str($poster->pdata('map_image', ''));
                if (strpos($mi, 'data:image') === 0) {
                    return $mi;
                }
            }
        }
        return '';
    }

    /**
     * Link de Google Maps al hospital: ruta (dir) desde la locación si hay GPS, o
     * búsqueda por texto si no. '' si no hay a dónde apuntar (no se imprime enlace muerto).
     */
    private static function hospitalMapsUrl($hasGps, $lat, $lng, string $name, string $address): string
    {
        $dest = trim($name . ' ' . $address);
        if ($dest === '') {
            return '';
        }
        if ($hasGps) {
            return 'https://www.google.com/maps/dir/?api=1&origin=' . $lat . ',' . $lng
                . '&destination=' . rawurlencode($dest);
        }
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($dest);
    }

    /**
     * Normaliza los 4 puestos del organigrama: sólo las claves esperadas, valores
     * recortados. Cada slot queda siempre presente (name/phone '' si vacío) para que la
     * vista sepa cuáles hay y cuáles no. La etiqueta es la canónica (no la del request).
     */
    private static function cleanContacts(array $contacts): array
    {
        $byKey = [];
        foreach ($contacts as $c) {
            if (! is_array($c) || empty($c['key'])) {
                continue;
            }
            $byKey[(string) $c['key']] = $c;
        }

        $out = [];
        foreach (PaeOrgChart::SLOTS as $slot) {
            $c = $byKey[$slot['key']] ?? [];
            $out[] = [
                'key'   => $slot['key'],
                'label' => $slot['label'],
                'name'  => self::str($c['name'] ?? ''),
                'phone' => self::str($c['phone'] ?? ''),
                'radio' => self::str($c['radio'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Convierte una imagen local (/storage/…) a un JPEG data-URI DOWNSCALEADO (~1100px),
     * para congelar las vistas del mapa dentro del sello del PAE. Aislado (GD directo): NO
     * toca ImageCompressor. Devuelve '' si GD falta, el archivo no existe o algo falla —
     * un hueco es preferible a romper la emisión (nunca se inventa una imagen).
     */
    private static function imageToDataUri(string $url, int $maxW = 1100): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        // Si ya es un data-URI, se usa tal cual (ya está congelado).
        if (strpos($url, 'data:image') === 0) {
            return $url;
        }
        // Sólo imágenes locales servidas desde /storage/… (offline por diseño).
        if (strpos($url, '/storage/') !== 0) {
            return '';
        }
        if (! function_exists('imagecreatefromstring')) {
            return '';   // GD ausente
        }

        $abs = public_path(ltrim($url, '/'));
        if (! is_file($abs) || ! is_readable($abs)) {
            return '';
        }
        $bytes = @file_get_contents($abs);
        if ($bytes === false || $bytes === '') {
            return '';
        }

        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return '';
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);
            return '';
        }

        // Downscale sólo si excede el ancho objetivo (nunca agranda).
        if ($w > $maxW) {
            $nw = $maxW;
            $nh = (int) round($h * ($maxW / $w));
            $dst = imagecreatetruecolor($nw, $nh);
            // Fondo blanco (JPEG no tiene alfa) para no ennegrecer PNG transparentes.
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        ob_start();
        $ok = imagejpeg($src, null, 82);
        $data = ob_get_clean();
        imagedestroy($src);
        if (! $ok || $data === false || $data === '') {
            return '';
        }
        return 'data:image/jpeg;base64,' . base64_encode($data);
    }

    /** Cadena recortada; '' para null. */
    private static function str($v): string
    {
        return trim((string) ($v ?? ''));
    }

    /** Float o null (para GPS y links). Cadena vacía → null. */
    private static function num($v): ?float
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return null;
        }
        return (float) $v;
    }
}
