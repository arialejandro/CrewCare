<?php

namespace App\Support;

/**
 * CONTRACT BUILDER · tipografía base del contrato.
 *
 * SOLO fuentes del SISTEMA (cero peso extra, nada que cargar) en 3 familias básicas. La MISMA pila se
 * usa en las TRES superficies —hoja del editor (`.cc-page`), medidor del salto (`PRINT_CSS`) y PDF
 * (`ContractTemplateRenderer::page`)— para que el salto de página caiga al píxel real.
 *
 * Default 'mono' (neutra, tipo máquina) por decisión del owner.
 */
class ContractFonts
{
    public const DEFAULT = 'mono';
    public const SIZE_DEFAULT = '11';

    /** key => [label, stack CSS]. El ORDEN define el del selector. */
    public static function all(): array
    {
        return [
            'serif' => ['label' => 'Serif',      'stack' => 'Georgia,"Times New Roman",serif'],
            'sans'  => ['label' => 'Sans-serif', 'stack' => 'system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif'],
            'mono'  => ['label' => 'Monospace',  'stack' => 'ui-monospace,"Cascadia Code","Segoe UI Mono",Consolas,monospace'],
        ];
    }

    /** Tamaños de letra base (pt). El corpus usa letra chica; default '11' (12 se veía enorme). */
    public static function sizes(): array
    {
        return ['10' => '10 pt', '11' => '11 pt', '12' => '12 pt'];
    }

    public static function normalizeSize(?string $key): string
    {
        return ($key !== null && array_key_exists($key, self::sizes())) ? $key : self::SIZE_DEFAULT;
    }

    /** Tamaño CSS `font-size` (ej. '11pt'). */
    public static function sizePt(?string $key): string
    {
        return self::normalizeSize($key) . 'pt';
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::all());
    }

    public static function normalize(?string $key): string
    {
        return self::isValid($key) ? $key : self::DEFAULT;
    }

    /** Pila CSS `font-family` de la fuente (normalizada). */
    public static function stack(?string $key): string
    {
        return self::all()[self::normalize($key)]['stack'];
    }
}
