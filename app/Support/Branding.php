<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Branding — identidad visual configurable por el super-admin (logo del cliente, nombre de marca,
 * título de la app/PWA, color primario). Lee la tabla `settings` (cacheada) y la fusiona con
 * defaults. A prueba de tabla-ausente: si `settings` aún no existe (deploy nuevo sin el CREATE
 * TABLE), devuelve solo los defaults sin tocar la BD → nada se rompe.
 */
class Branding
{
    /** Defaults = comportamiento actual del sistema (CrewCare, naranja). */
    const DEFAULTS = [
        'brand_name'      => 'CrewCare',   // reemplaza el "ENEG" hardcodeado en reportes/encabezados
        'app_title'       => 'CrewCare',   // <title> del navegador + nombre PWA
        'primary_color'   => '#ff9900',    // acento principal de marca (--brand-primary)
        'secondary_color' => '#1f2937',    // color secundario / tinta (--brand-secondary): textos de acento, hover
        'accent_color'    => '#0ea5e9',    // color de acento/realce (--brand-accent): highlights, chips, CTAs
        'client_logo'     => '',           // ruta del logo del cliente; vacío => la vista usa su fallback
        // Datos de producción para el encabezado del formato Amazon MGM (opcionales).
        'company_name'    => '',           // "Production Company"; vacío => se usa brand_name
        'office_address'  => '',           // "Production Office Address"; vacío => se muestra "—"
        // Datos del CONTRATANTE para la carátula del contrato (Paso B). company_name = razón social,
        // office_address = domicilio; estos tres los agrega B5. La carátula CONGELA lo que use al emitir.
        'rfc'                 => '',        // RFC del contratante
        'representante_legal' => '',        // representante legal
        'correo_contratante'  => '',        // correo del contratante
    ];

    const CACHE_KEY = 'branding.settings';

    /**
     * Mapa fusionado (defaults + valores guardados no vacíos). Cacheado para no consultar por request.
     */
    public static function all(): array
    {
        if (! Schema::hasTable('settings')) {
            return self::DEFAULTS;
        }

        $db = Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        // Solo sobreescribe defaults con valores realmente capturados (no null/vacío).
        $clean = [];
        foreach ($db as $k => $v) {
            if ($v !== null && $v !== '') {
                $clean[$k] = $v;
            }
        }

        return array_merge(self::DEFAULTS, $clean);
    }

    /** Lee un solo valor con default. */
    public static function get(string $key, $default = null)
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Logo principal para los DOCUMENTOS (Scouting, Daily, Cond./Actos Inseguros, Accidentes).
     * Devuelve el logo del cliente configurado en Marca (que se aloja LOCAL al subirlo, ver
     * BrandingController::store → Storage::url); si no hay uno, cae a la marca propia de CrewCare,
     * SIEMPRE LOCAL.
     *
     * (2026-07-23) Regla del owner: NINGUNA imagen de un reporte viene de la red. Antes el fallback
     * era una URL remota (eneg.crewcare.mx) → sin internet el encabezado del documento del estudio
     * salía sin logo. Ahora el default es un asset local raíz-relativo (asset() está roto en la app),
     * y es la marca de CrewCare, nunca la de un tercero.
     */
    const DEFAULT_DOCUMENT_LOGO = '/img/logo-cc-report.svg';

    public static function documentLogo(): string
    {
        $logo = self::get('client_logo', '');
        return $logo !== '' ? $logo : self::DEFAULT_DOCUMENT_LOGO;
    }

    /** Normaliza un hex (#RGB o #RRGGBB) a [r,g,b] enteros. Devuelve null si es inválido. */
    private static function hexToRgb(string $hex): ?array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (! preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            return null;
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** "r, g, b" para usar en rgba(var(--...-rgb), a). Fallback a naranja si el hex es inválido. */
    public static function rgb(string $hex): string
    {
        $c = self::hexToRgb($hex) ?? [255, 153, 0];
        return $c[0] . ', ' . $c[1] . ', ' . $c[2];
    }

    /** Texto legible SOBRE un color: oscuro si el fondo es claro, blanco si es oscuro (luminancia sRGB). */
    public static function textOn(string $hex): string
    {
        $c = self::hexToRgb($hex);
        if ($c === null) {
            return '#ffffff';
        }
        $lum = (0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]) / 255;
        return $lum > 0.6 ? '#111827' : '#ffffff';
    }

    /** Aclara (factor>1) u oscurece (factor<1) un hex por canal. Útil para estados hover. */
    public static function shade(string $hex, float $factor): string
    {
        $c = self::hexToRgb($hex);
        if ($c === null) {
            return $hex;
        }
        $r = max(0, min(255, (int) round($c[0] * $factor)));
        $g = max(0, min(255, (int) round($c[1] * $factor)));
        $b = max(0, min(255, (int) round($c[2] * $factor)));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** Invalida el cache (llamar tras guardar settings). */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
