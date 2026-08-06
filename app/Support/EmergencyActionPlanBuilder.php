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
    /** Versión del cálculo. Viaja en el payload congelado. Subirla si cambia el SIGNIFICADO. */
    const VERSION = 1;

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

        // Proyecto: el del primer scouting si lo trae; si no, la producción vigente.
        $project = '';
        if (! empty($list)) {
            $project = self::str($list[0]->production_name);
        }
        if ($project === '') {
            $prod = CurrentProduction::get();
            $project = $prod ? self::str($prod->name) : '';
        }

        $locations = [];
        $seq = 0;
        foreach ($list as $s) {
            $seq++;
            $locations[] = self::locationBlock($s, $seq, $embed);
        }

        return [
            'version' => self::VERSION,

            // 1 · CABECERA DEL LLAMADO.
            'header' => [
                'shoot_day' => isset($opts['shoot_day']) && $opts['shoot_day'] !== '' ? (int) $opts['shoot_day'] : null,
                'date'      => self::str($opts['plan_date'] ?? ''),
                'project'   => $project,
                'unit'      => self::str($opts['unit_name'] ?? ''),
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
            $out[] = [
                'hazard'         => $hazard,
                'category'       => $cat,
                'category_label' => $cat !== '' ? RiskMap::hazardCategoryLabel($cat) : '',
                'rating'         => self::str($r['rating'] ?? ''),
                'residual'       => self::str($r['residual'] ?? ''),
                'badge'          => self::str($r['badge'] ?? ''),
                'code'           => self::str($r['code'] ?? ''),
                'url'            => self::str($r['url'] ?? ''),
                'unclassified'   => ! empty($r['unclassified']),
            ];
        }
        return $out;
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
        $candidate = self::str($s->hospital_map ?? '');
        return strpos($candidate, 'data:image') === 0 ? $candidate : '';
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
