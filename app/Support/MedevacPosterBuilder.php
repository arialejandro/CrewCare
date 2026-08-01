<?php

namespace App\Support;

use App\Models\ScoutingReport;

/**
 * MedevacPosterBuilder — el MOTOR (mínimo) del póster MEDEVAC (delta #46, 2026-07-31).
 *
 * Primera plantilla del motor de salida de documentos. Es de SOLO LECTURA: recibe el
 * SCOUTING (locación autoritativa) + los CONTACTOS ya confirmados por el emisor, y
 * devuelve el PAYLOAD que MedevacPoster congela y sella. No pinta nada, no guarda nada.
 *
 * ── DOS REGLAS QUE MANDAN ────────────────────────────────────────────────────────
 * 1. NO INVENTAR. Un campo vacío se queda vacío: su bloque no aparece en el póster. No
 *    se rellena con "N/D" ni con el valor de otra locación. Los ÚNICOS valores derivados
 *    son los links de Google Maps: son punteros DETERMINISTAS a coordenadas/dirección que
 *    alguien capturó, no datos nuevos que nadie afirmó.
 * 2. CONGELAR. Lo que sale de aquí es la fotografía del documento. Si mañana cambia el
 *    scouting, el póster emitido sigue diciendo lo que dijo (y el sello lo prueba).
 *
 * Lo GENÉRICO del motor (reutilizable por el PAE / boletines): el patrón "leer fuente →
 * congelar payload versionado → sellar como documento". Lo ESPECÍFICO del MEDEVAC: el
 * mapeo de campos del scouting y los tres contactos de emergencia.
 */
class MedevacPosterBuilder
{
    /**
     * Versión del cálculo. Viaja en el payload congelado para saber qué lógica lo produjo.
     * Subirla cuando cambie el SIGNIFICADO de algún campo del payload.
     */
    const VERSION = 1;

    /**
     * Construye el payload congelado.
     *
     * @param  \App\Models\ScoutingReport $s
     * @param  array                      $contacts   [['key','label','name','phone'], ...] ya confirmados por el emisor
     * @param  string|null                $mapImage   data-URI del mapa que adjuntó el emisor (se congela DENTRO del sello), o null
     * @return array
     */
    public static function build(ScoutingReport $s, array $contacts, $mapImage = null): array
    {
        $lat = self::num($s->latitude);
        $lng = self::num($s->longitude);
        $hasGps = $lat !== null && $lng !== null;

        $hospitalName    = self::str($s->nearest_hospital);
        $hospitalAddress = self::str($s->hospital_address);

        // Proyecto: el del scouting si lo trae; si no, el de la producción vigente.
        $project = self::str($s->production_name);
        if ($project === '') {
            $prod = CurrentProduction::get();
            $project = $prod ? self::str($prod->name) : '';
        }

        // Compañía: la de Marca; si está vacía, el nombre de marca (mismo criterio que el export Amazon).
        $company = self::str(Branding::get('company_name', ''));
        if ($company === '') {
            $company = self::str(Branding::get('brand_name', 'CrewCare'));
        }

        return [
            'version' => self::VERSION,

            // Cabecera del documento (logo + textos). El logo es ruta LOCAL (offline por diseño).
            'brand' => [
                'logo'         => Branding::documentLogo(),
                'company_name' => $company,
                'project_name' => $project,
            ],

            // Franja LOCACIÓN · DISTANCIA · TIEMPO + link de mapa.
            'location' => [
                'name'    => self::str($s->location_name),
                'address' => self::str($s->location_address),
            ],
            'gps'         => $hasGps ? ['lat' => $lat, 'lng' => $lng] : null,
            'maps_url'    => $hasGps ? ('https://www.google.com/maps?q=' . $lat . ',' . $lng) : '',
            'distance_km' => self::str($s->hospital_distance_km),   // '' si NULL (la celda DISTANCIA no aparece)
            'eta'         => self::str($s->hospital_eta),

            // HOSPITAL, DIRECCIÓN y LINK DE GOOGLE MAPS (ruta al hospital).
            'hospital' => [
                'name'     => $hospitalName,
                'address'  => $hospitalAddress,
                'maps_url' => self::hospitalMapsUrl($hasGps, $lat, $lng, $hospitalName, $hospitalAddress),
            ],

            // Datos de emergencia extra (ya existentes; el bloque vacío no aparece).
            'ambulance_company' => self::str($s->ambulance_company),
            'emergency_phone'   => self::str($s->emergency_phone),
            'assembly_point'    => self::str($s->assembly_point),
            'emergency_access'  => self::str($s->emergency_access),

            // CONTACTOS DE EMERGENCIA (3 columnas), congelados tal como el emisor los confirmó.
            'contacts' => self::cleanContacts($contacts),

            // MAPA de la ruta, CONGELADO como data-URI DENTRO del sello (los bytes entran al hash
            // → cambiarlo marca ALTERADO; vacío = no se imprime). Se toma el que sube el emisor; si
            // no sube uno, cae al que quedó GUARDADO en el scouting (persiste entre emisiones).
            'map_image' => self::resolveMap($mapImage, $s),
        ];
    }

    /**
     * Link de Google Maps al hospital: ruta (dir) desde la locación si hay GPS, o búsqueda del
     * hospital por texto si no. '' si no hay ni hospital ni a dónde apuntar (no se imprime un
     * enlace muerto). Es un puntero a datos capturados, no un dato inventado.
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
     * Normaliza los 3 contactos: sólo las claves esperadas, valores recortados. Cada slot queda
     * siempre presente (name/phone '' si vacío) para que la vista sepa cuáles hay y cuáles no.
     */
    private static function cleanContacts(array $contacts): array
    {
        // Índice por key de lo recibido.
        $byKey = [];
        foreach ($contacts as $c) {
            if (! is_array($c) || empty($c['key'])) {
                continue;
            }
            $byKey[(string) $c['key']] = $c;
        }

        $out = [];
        foreach (MedevacContacts::SLOTS as $slot) {
            $c = $byKey[$slot['key']] ?? [];
            $out[] = [
                'key'   => $slot['key'],
                'label' => $slot['label'],   // etiqueta canónica (no la que venga del request)
                'name'  => self::str($c['name'] ?? ''),
                'phone' => self::str($c['phone'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Mapa a congelar: el que sube el emisor gana; si no hay, el guardado en el scouting (persiste
     * entre emisiones). Sólo se acepta un data-URI de imagen; cualquier otra cosa → '' (vacío = no
     * se imprime, nunca se inventa).
     */
    private static function resolveMap($uploaded, ScoutingReport $s): string
    {
        $candidate = is_string($uploaded) && $uploaded !== '' ? $uploaded : self::str($s->hospital_map ?? '');
        return strpos($candidate, 'data:image') === 0 ? $candidate : '';
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
