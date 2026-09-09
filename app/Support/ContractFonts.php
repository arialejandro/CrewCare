<?php

namespace App\Support;

/**
 * CONTRACT BUILDER · tipografía base del contrato.
 *
 * SOLO fuentes UNIVERSALES (las 8 clásicas "web-safe" que el owner citó): están en el sistema en la
 * práctica y cada stack termina en una familia genérica (sans-serif/serif/monospace) → degradan bien
 * en cualquier plataforma y en el PDF (Browsershot en Windows las tiene todas). Cero carga de fuentes.
 * La MISMA pila se usa en las TRES superficies —hoja del editor (`.cc-page`), medidor del salto
 * (`PRINT_CSS`) y PDF (`ContractTemplateRenderer::page`)— para que el salto de página caiga al píxel.
 *
 * Default 'couriernew' (el corpus real de contratos = Courier New 9pt).
 */
class ContractFonts
{
    public const DEFAULT = 'couriernew';
    public const SIZE_DEFAULT = '9';   // el corpus mono real = Courier New 9pt

    /** Alias de valores viejos (mig 088 nació con mono/serif/sans) → nuevas claves nombradas. */
    private const ALIASES = ['mono' => 'couriernew', 'serif' => 'georgia', 'sans' => 'arial'];

    /** key => [label, stack CSS]. El ORDEN define el del selector. Solo fuentes universales del sistema. */
    public static function all(): array
    {
        return [
            'arial'      => ['label' => 'Arial',           'stack' => 'Arial,Helvetica,sans-serif'],
            'helvetica'  => ['label' => 'Helvetica',       'stack' => 'Helvetica,Arial,sans-serif'],
            'verdana'    => ['label' => 'Verdana',         'stack' => 'Verdana,Geneva,sans-serif'],
            'tahoma'     => ['label' => 'Tahoma',          'stack' => 'Tahoma,Geneva,sans-serif'],
            'times'      => ['label' => 'Times New Roman', 'stack' => '"Times New Roman",Times,serif'],
            'georgia'    => ['label' => 'Georgia',         'stack' => 'Georgia,"Times New Roman",serif'],
            'couriernew' => ['label' => 'Courier New',     'stack' => '"Courier New",Courier,monospace'],
            'courier'    => ['label' => 'Courier',         'stack' => 'Courier,"Courier New",monospace'],
        ];
    }

    /** Tamaños de letra base (pt). El corpus mono real es 9pt; default '9'. */
    public static function sizes(): array
    {
        return ['9' => '9 pt', '10' => '10 pt', '11' => '11 pt', '12' => '12 pt'];
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
        if ($key !== null && isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        return self::isValid($key) ? $key : self::DEFAULT;
    }

    /** Pila CSS `font-family` de la fuente (normalizada). */
    public static function stack(?string $key): string
    {
        return self::all()[self::normalize($key)]['stack'];
    }
}
