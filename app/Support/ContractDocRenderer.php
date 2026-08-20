<?php

namespace App\Support;

use App\Models\ContractTemplate;
use Illuminate\Support\Facades\Storage;

/**
 * CONTRACT BUILDER · MOTOR UNIFICADO — el PDF final de una plantilla como "HOJA FIJA + etiquetas por
 * coordenadas", sin importar si nació como HTML redactado o como PDF subido:
 *
 *   1) HOJA BASE:
 *        · PDF subido → el archivo tal cual.
 *        · HTML       → el body con {{tokens}} llenos, pasado por Chrome ({@see ContractPdf}). (Las
 *                       anclas inline `[[firma:]]` de plantillas VIEJAS se estampan aquí para no romper
 *                       compat; las NUEVAS mueven sus firmas al `field_map`.)
 *   2) ETIQUETAS: se sobreponen las del `field_map` (firma/rúbrica/fecha por coordenadas) con
 *        {@see ContractPdfStamper::stampBytes}. Sin `field_map` → la hoja base ES el documento (idéntico
 *        al comportamiento anterior).
 *
 * Es la costura que unifica el editor DocuSign (campos libres) con el firmado y la ceremonia.
 */
class ContractDocRenderer
{
    /** PDF final (bytes) de la plantilla con datos + firmas del mapa CLAVE→props. */
    public static function renderPdf(ContractTemplate $template, array $values, array $sigMap): string
    {
        // PDF subido: el motor de estampado ya lee el archivo y sobrepone TODO su field_map (datos+firmas).
        if ($template->isPdfSource()) {
            return ContractPdfStamper::stamp($template, $values, $sigMap);
        }

        // HTML: hoja base por Chrome + (si hay) etiquetas por coordenadas encima.
        $base = self::htmlBase($template, $values, $sigMap);

        $fields = $template->placedFields();
        if (empty($fields)) {
            return $base;   // sin campos por coordenadas → la hoja base ya es el documento
        }

        return ContractPdfStamper::stampBytes($base, $fields, $values, $sigMap);
    }

    /**
     * La HOJA BASE de una plantilla HTML: body con {{tokens}} llenos → Chrome. Si la plantilla tiene
     * firmas por COORDENADAS (field_map con `sign`), las anclas inline `[[firma:]]` residuales se
     * LIMPIAN (las firmas se estampan por coordenadas encima → evita doble firma). Sin coordenadas, se
     * estampan inline como siempre (compat con plantillas viejas).
     */
    public static function htmlBase(ContractTemplate $template, array $values, array $sigMap): string
    {
        $body = (string) $template->body;

        if (! empty($template->signFields())) {
            $body   = preg_replace('/\[\[firma:[a-z0-9_:\-]+(?:\|-?\d+,-?\d+)?\]\]/i', '', $body);
            $sigMap = [];   // las firmas van por coordenadas, no inline
        }

        $inner = ContractTemplateRenderer::stampAnchors(ContractTemplateRenderer::fill($body, $values), $sigMap);
        $html  = ContractTemplateRenderer::page(
            $inner, $template->architecture, $template->page_size, null, $template->font_family, $template->font_size
        );

        return ContractPdf::render($html);
    }

    /** Ruta absoluta / bytes del PDF subido (para servirlo o estamparlo). */
    public static function uploadedPdfBytes(ContractTemplate $template): ?string
    {
        $path = $template->pdf_path;
        if (! $template->isPdfSource() || ! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->get($path);
    }
}
