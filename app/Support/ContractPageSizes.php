<?php

namespace App\Support;

/**
 * CONTRACT BUILDER · tamaños de página del documento.
 *
 * El corpus real de contratos firmados NO es oficio: BR70/TGH/LU/LGS son CARTA (216×279mm) y
 * Spectrum es OFICIO US / Legal (216×356mm). Por eso el tamaño es ELEGIBLE por plantilla, con
 * Carta por defecto. Alimenta: (1) el `@page` del render (PDF fiel vía Browsershot) y (2) la
 * "hoja" del editor (ancho/alto/margen reales, en mm, para que se vea como Word).
 *
 * Medidas en MILÍMETROS. `margin` = margen de página (mismo en los 4 lados por ahora).
 */
class ContractPageSizes
{
    public const DEFAULT = 'carta';

    /** key => [label, w(mm), h(mm), margin(mm)]. */
    public static function all(): array
    {
        return [
            'carta' => ['label' => 'Carta (21.6 × 27.9 cm)',        'w' => 216, 'h' => 279, 'margin' => 25],
            'legal' => ['label' => 'Oficio / Legal (21.6 × 35.6 cm)', 'w' => 216, 'h' => 356, 'margin' => 25],
            'a4'    => ['label' => 'A4 (21 × 29.7 cm)',              'w' => 210, 'h' => 297, 'margin' => 25],
        ];
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::all());
    }

    public static function normalize(?string $key): string
    {
        return self::isValid($key) ? $key : self::DEFAULT;
    }

    public static function dims(?string $key): array
    {
        return self::all()[self::normalize($key)];
    }

    /** CSS de página para el render (PDF): tamaño + margen + salto de página manual. */
    public static function pageCss(?string $key): string
    {
        $d = self::dims($key);

        return '@page{size:' . $d['w'] . 'mm ' . $d['h'] . 'mm;margin:' . $d['margin'] . 'mm}'
            . '.cc-pb{break-before:page;height:0;margin:0;border:0;padding:0}';
    }
}
