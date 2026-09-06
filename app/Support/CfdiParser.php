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
    public static function parse(string $xmlContent): ?array
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

        $conceptos = [];
        if (isset($children->Conceptos)) {
            $conceptoNodes = $cfdiNs ? $children->Conceptos->children($cfdiNs) : $children->Conceptos->children();
            foreach ($conceptoNodes->Concepto ?? [] as $con) {
                $desc = self::attr($con->attributes(), 'Descripcion');
                if ($desc !== '') { $conceptos[] = $desc; }
            }
        }

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
        ];
    }

    /** Lee un atributo (case-sensitive) de un SimpleXMLElement de atributos; '' si no está. */
    private static function attr($attrs, string $name): string
    {
        return isset($attrs[$name]) ? trim((string) $attrs[$name]) : '';
    }
}
