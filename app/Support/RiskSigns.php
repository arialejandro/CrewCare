<?php

namespace App\Support;

/**
 * Biblioteca de SEÑALES industriales del Mapeo de riesgos (delta #50 · Opción A).
 *
 * Cada señal ISO/hazmat/EPP vive como <img data:image/svg+xml;base64,...> — se sirve
 * AISLADA, así los IDs/clases internos del SVG (.st0, SVGID_2_, …) no colisionan al
 * poner muchas en una página. El mapa slug=>dataURI lo genera
 * resources/svg/senaletica/_generate.php en resources/rm-signs.generated.php.
 *
 * Resolución de una CLAVE DE ICONO (la que usa _rm-icon / el pin) a su señal:
 *   1) ALIAS: clave semántica del mapeo (haz-bolt, extintor, …) → slug del archivo.
 *   2) slug directo: si la clave YA es un slug de la biblioteca (p. ej. un override
 *      risk_icon apuntando a 'adr_3b' o 'wear_safety_glasses').
 *   3) sin señal → null → _rm-icon cae a su glifo dibujado (currentColor).
 */
class RiskSigns
{
    /**
     * Clave de icono del mapeo → slug del archivo de señal. SOLO las que el owner
     * subió; las demás claves (haz-water, haz-people, botiquin, …) siguen con glifo.
     */
    const ALIAS = [
        // recursos (la señal trae su propio color)
        'extintor'          => 'extintor',
        'salida_emergencia' => 'salida_emergencia',
        'punto_reunion'     => 'punto_de_reunion',
        // peligros (haz-*) → señal ISO 7010
        'haz-warn'      => 'exclamacion',
        'hazard'        => 'exclamacion',
        'haz-bolt'      => 'riesgo_electrico',
        'haz-fall'      => 'caida_distinto_nivel',
        'haz-fallobj'   => 'caida_de_objetos',
        'haz-suspended' => 'izaje',
        'haz-collapse'  => 'aplastamiento',
        'haz-slip'      => 'superficie_resbalosa',
        'haz-temp'      => 'superficie_caliente',
        'haz-toxic'     => 'peligro_grave_para_la_salud',
        'haz-explosive' => 'explosion',
        'haz-exit'      => 'salida_emergencia',
    ];

    /** Biblioteca cargada (slug => data:URI), memorizada por proceso. */
    protected static $lib = null;

    public static function library(): array
    {
        if (self::$lib === null) {
            $file = resource_path('rm-signs.generated.php');
            self::$lib = is_file($file) ? (array) require $file : [];
        }
        return self::$lib;
    }

    /** data:URI de la señal para una clave de icono (alias o slug directo), o null. */
    public static function uriFor(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }
        $lib  = self::library();
        $slug = self::ALIAS[$key] ?? $key; // alias primero; si no, la clave YA es slug
        return $lib[$slug] ?? null;
    }

    /** ¿La clave se pinta como señal a color (y por tanto el pin NO lleva gota)? */
    public static function has(?string $key): bool
    {
        return self::uriFor($key) !== null;
    }
}
