<?php

namespace App\Support;

/**
 * CfdiParser — extrae de un XML de CFDI (4.0 o 3.3) lo mínimo para armar el enlace de verificación del
 * SAT y cotejar, SIN teclear nada: folio fiscal (UUID del Timbre), RFC emisor, RFC receptor, importe
 * total (VERBATIM — el verificador del SAT es quisquilloso con los decimales, así que NO se re-formatea),
 * sello digital del comprobante, y las descripciones de los conceptos (ahí vive la nomenclatura).
 *
 * SOLO LECTURA del archivo local; el servidor NUNCA consulta al SAT. Best-effort: si el XML no es un
 * CFDI reconocible (sin UUID ni RFCs), devuelve null y el llamador NO guarda datos CFDI.
 */
class CfdiParser
{
    /**
     * @return null|array{uuid:string, rfc_emisor:string, rfc_receptor:string, total:string,
     *                    sello:string, conceptos:array<int,string>}
     */
    /** Meses en español (con y sin acento, y la variante "setiembre") para las fechas en palabras. */
    private const MESES_ES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
        'noviembre' => 11, 'diciembre' => 12,
    ];

    public static function parse(string $xmlContent, ?string $dateOrder = null, array $knownCodes = []): ?array
    {
        if (trim($xmlContent) === '') {
            return null;
        }

        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($xmlContent);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($xml === false) {
            return null;
        }

        $namespaces = $xml->getDocNamespaces(true);
        $cfdiNs = null;
        $tfdNs  = null;
        foreach ($namespaces as $uri) {
            if ($cfdiNs === null && strpos($uri, 'sat.gob.mx/cfd/') !== false) { $cfdiNs = $uri; }
            if ($tfdNs === null && strpos($uri, 'TimbreFiscalDigital') !== false) { $tfdNs = $uri; }
        }

        // Atributos del Comprobante (raíz): sin prefijo de namespace.
        $rootAttrs = $xml->attributes();
        $total = self::attr($rootAttrs, 'Total');
        $sello = self::attr($rootAttrs, 'Sello');

        // Hijos en el namespace cfdi (Emisor / Receptor / Conceptos).
        $children  = $cfdiNs ? $xml->children($cfdiNs) : $xml->children();
        $rfcEmisor   = isset($children->Emisor)   ? self::attr($children->Emisor->attributes(), 'Rfc')   : '';
        $rfcReceptor = isset($children->Receptor) ? self::attr($children->Receptor->attributes(), 'Rfc') : '';

        // TODOS los conceptos (una factura cubre varias semanas: p.ej. 2 SEM + 2 CA de 2 semanas).
        // Se parte cada uno en {prefix, digits, raw}; la FECHA de los 6 dígitos se interpreta DESPUÉS,
        // con un orden (dd-mm-yy vs mm-dd-yy) DETECTADO por factura (una productora gringa escribe MMDDYY).
        $parts = [];
        if (isset($children->Conceptos)) {
            $conceptoNodes = $cfdiNs ? $children->Conceptos->children($cfdiNs) : $children->Conceptos->children();
            foreach ($conceptoNodes->Concepto ?? [] as $con) {
                $desc = self::attr($con->attributes(), 'Descripcion');
                if ($desc !== '') { $parts[] = self::splitConcepto($desc, $knownCodes); }
            }
        }
        // La SEMANA la resuelve una capa de lectores: primero los 6 dígitos (con el orden por factura);
        // si no hay, la FECHA EN PALABRAS ("del X al Y de MES del AÑO" → fin del rango). Agregar un
        // estilo = agregar un lector, sin reescribir la gramática.
        $order     = $dateOrder ?: self::detectDateOrder(array_column($parts, 'digits'));
        $conceptos = array_map(fn ($p) => [
            'prefix' => $p['prefix'],
            'week'   => self::digitsToWeek($p['digits'], $order) ?? self::spelledWeek($p['raw']),
            'raw'    => $p['raw'],
        ], $parts);

        // Folio fiscal (UUID) — del TimbreFiscalDigital, esté donde esté (xpath tolerante).
        $uuid = '';
        if ($tfdNs) {
            $xml->registerXPathNamespace('tfd', $tfdNs);
            $found = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (! empty($found) && isset($found[0])) {
                $uuid = self::attr($found[0]->attributes(), 'UUID');
            }
        }

        // No es un CFDI utilizable si le faltan las piezas del enlace.
        if ($uuid === '' && $rfcEmisor === '' && $rfcReceptor === '') {
            return null;
        }

        return [
            'uuid'         => $uuid,
            'rfc_emisor'   => strtoupper($rfcEmisor),
            'rfc_receptor' => strtoupper($rfcReceptor),
            'total'        => $total,   // VERBATIM: no se re-formatea
            'sello'        => $sello,
            'conceptos'    => $conceptos,
            'date_order'   => $order,   // 'dmy' | 'mdy' — cómo se interpretaron las fechas (transparencia)
        ];
    }

    /**
     * Descompone una descripción en {prefix, digits, raw}. CONFIABLE: el prefijo (SEM/CA/BOX…, letras
     * iniciales) y los 6 dígitos de la fecha vienen de un FORMATO. El puesto y la producción viven en
     * `raw` y NO se estructuran ni se cotejan EXACTO (la muestra real trae erratas: "GRNGO" por "GRINGO").
     * La INTERPRETACIÓN de los 6 dígitos (dd-mm-yy vs mm-dd-yy) se decide aparte, por factura.
     *
     * @return array{prefix: ?string, digits: ?string, raw: string}
     */
    public static function splitConcepto(string $desc, array $knownCodes = []): array
    {
        $raw    = trim($desc);
        $prefix = null;

        // PREFIJO por CATÁLOGO (posición LIBRE: inicio, medio o fin): se busca cada código conocido como
        // palabra completa y gana el que aparezca ANTES (empate → el más largo/específico). Así un
        // prefijo nuevo se reconoce agregándolo al catálogo, sin tocar código. Si el catálogo no matchea
        // (p.ej. un prefijo que aún no se dio de alta), cae al token inicial.
        if ($knownCodes) {
            $bestPos = PHP_INT_MAX;
            foreach ($knownCodes as $code) {
                $code = trim((string) $code);
                if ($code === '') { continue; }
                if (preg_match('/\b' . preg_quote($code, '/') . '\b/i', $raw, $mm, PREG_OFFSET_CAPTURE)) {
                    $pos = $mm[0][1];
                    if ($pos < $bestPos || ($pos === $bestPos && strlen($code) > strlen((string) $prefix))) {
                        $prefix  = strtoupper($code);
                        $bestPos = $pos;
                    }
                }
            }
        }
        if ($prefix === null && preg_match('/^\s*([A-Za-z]{2,12})/', $raw, $m)) {
            $prefix = strtoupper($m[1]);   // fallback: token inicial (prefijo aún no en el catálogo)
        }

        // 6 dígitos de fecha: pegados al prefijo (SEM021724) o sueltos, pero NO parte de un número mayor.
        $digits = preg_match('/(?<!\d)(\d{6})(?!\d)/', $raw, $m) ? $m[1] : null;

        return ['prefix' => $prefix, 'digits' => $digits, 'raw' => $raw];
    }

    /**
     * FECHA EN PALABRAS (español) → Y-m-d de la ÚLTIMA fecha del texto (fin del rango = final de la
     * semana, consistente con la nomenclatura). Lee "…04 de julio del 2026", "del 29 de junio al 04 de
     * julio de 2026", etc. El año es el último 20xx del texto. null si no hay una fecha en palabras.
     */
    public static function spelledWeek(string $text): ?string
    {
        $t = mb_strtolower($text, 'UTF-8');

        if (! preg_match_all('/(\d{1,2})\s+de\s+([a-záéíóúñ]+)/u', $t, $ms, PREG_SET_ORDER)) {
            return null;
        }
        $year = null;
        if (preg_match_all('/\b(20\d{2})\b/', $t, $ys)) {
            $year = (int) end($ys[1]);
        }

        $last = null;
        foreach ($ms as $m) {
            $mes = self::MESES_ES[$m[2]] ?? null;
            if ($mes !== null) {
                $last = ['d' => (int) $m[1], 'm' => $mes];
            }
        }
        if ($last === null || $year === null || $last['d'] < 1 || $last['d'] > 31) {
            return null;
        }

        try {
            return \Carbon\Carbon::create($year, $last['m'], $last['d'])->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Detecta el ORDEN de fechas de la factura: `mdy` (mm-dd-yy, productora gringa) o `dmy` (dd-mm-yy,
     * México). Busca un token INEQUÍVOCO: si al leerlo dd-mm-yy el "mes" sale >12 (p.ej. 021724 → mes 17),
     * NO puede ser dmy → es mdy; y al revés. Si TODOS son ambiguos (ambas mitades ≤12), cae a `dmy`
     * (México, igual que el default del calendario). Una factura usa UN orden para todos sus conceptos.
     */
    public static function detectDateOrder(array $digitsList): string
    {
        foreach ($digitsList as $d) {
            if ($d === null || strlen($d) !== 6) { continue; }
            $a = (int) substr($d, 0, 2);   // dmy: día  | mdy: mes
            $b = (int) substr($d, 2, 2);   // dmy: mes  | mdy: día
            $dmyOk = ($b >= 1 && $b <= 12);
            $mdyOk = ($a >= 1 && $a <= 12);
            if ($dmyOk && ! $mdyOk) { return 'dmy'; }
            if ($mdyOk && ! $dmyOk) { return 'mdy'; }
        }

        return 'dmy';   // todo ambiguo → default México (mismo criterio que period_label_template)
    }

    /** Interpreta los 6 dígitos como Y-m-d según el orden ('dmy'|'mdy'). null si no es fecha válida. */
    public static function digitsToWeek(?string $digits, string $order): ?string
    {
        if ($digits === null || strlen($digits) !== 6) {
            return null;
        }
        $p1 = (int) substr($digits, 0, 2);
        $p2 = (int) substr($digits, 2, 2);
        $yy = (int) substr($digits, 4, 2);
        [$dd, $mm] = $order === 'mdy' ? [$p2, $p1] : [$p1, $p2];
        if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) {
            return null;
        }
        try {
            return \Carbon\Carbon::createFromFormat('!d-m-y', "{$dd}-{$mm}-{$yy}")->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Convenience: descompone una descripción a {prefix, week, raw} con un orden dado (default dmy). */
    public static function parseConcepto(string $desc, string $order = 'dmy'): array
    {
        $p = self::splitConcepto($desc);

        return ['prefix' => $p['prefix'], 'week' => self::digitsToWeek($p['digits'], $order), 'raw' => $p['raw']];
    }

    /** Lee un atributo (case-sensitive) de un SimpleXMLElement de atributos; '' si no está. */
    private static function attr($attrs, string $name): string
    {
        return isset($attrs[$name]) ? trim((string) $attrs[$name]) : '';
    }
}
