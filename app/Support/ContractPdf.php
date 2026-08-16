<?php

namespace App\Support;

/**
 * EL CONTRATO · PASO C · FASE 3 — motor HTML→PDF de los documentos de contrato (firmado + certificado),
 * en un solo lugar. Por defecto Chrome headless (PdfExporter/Browsershot). Es una COSTURA sustituible:
 * QaTestCase la reemplaza por un doble que devuelve bytes deterministas, así todo el flujo (render →
 * guardar → adjuntar) se prueba sin levantar Chrome ni colgar la suite con timeouts.
 */
class ContractPdf
{
    /** @var callable|null  fn(string $html): string — motor sustituible (tests). */
    public static $engine = null;

    /** HTML → bytes de PDF. Usa la costura si está puesta; si no, Chrome headless. */
    public static function render(string $html): string
    {
        $engine = self::$engine ?: fn (string $h) => PdfExporter::fromHtml($h);

        return (string) $engine($html);
    }
}
