<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BadgeTemplate — plantilla de diseño del gafete (ID-Badge) configurable por el
 * super-admin. La tarjeta queda FIJA a 108mm×172mm (no configurable en v1); lo que
 * se guarda en `config` (LONGTEXT/JSON, cast 'array') son fuentes, colores, forma
 * y posiciones. Se usa UNA sola fila activa (active=1).
 *
 * activeConfig() fusiona DEFAULTS con la config guardada → la app siempre recibe un
 * mapa completo aunque falte una llave (a prueba de plantilla vieja / parcial).
 */
class BadgeTemplate extends Model
{
    protected $table = 'badge_templates';

    protected $fillable = ['name', 'config', 'active'];

    protected $casts = [
        'config' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Valores por defecto = comportamiento del gafete actual (mm salvo donde se indique).
     * NO cambiar las llaves: la vista _card y el diseñador dependen de estos nombres.
     */
    public const DEFAULTS = [
        'card_bg'               => 'img/nlogo25.png', // ruta relativa a public/
        'font_family'           => 'Poppins',         // allow-list: Poppins, Roboto, Montserrat, Arial
        'text_color'            => '#111111',
        // --- Nombre del proyecto (antes "marca" con "S2" hardcodeado). '' → usa branding.brand_name. ---
        'project_name'          => '',
        'brand_top'             => 15,
        'brand_left'            => 40,
        'brand_size'            => 14,                // pt
        'brand_weight'          => 300,              // 100 thin | 300 light | 400 regular | 700 bold | 900 black
        // --- Logo de producción (opcional). '' → no se muestra. ---
        'production_logo'       => '',               // ruta relativa a public/
        'production_logo_top'   => 6,
        'production_logo_left'  => 39,
        'production_logo_width' => 30,               // mm
        // --- Foto ---
        'photo_shape'           => 'circle',          // circle | rounded | square
        'photo_size'            => 50,
        'photo_top'             => 37,
        'photo_left'            => 29,
        // --- Nombre completo ---
        'label_name'            => 'NOMBRE',
        'label_name_show'       => true,             // se puede ocultar la etiqueta "NOMBRE"
        'label_name_top'        => 135,
        'name_top'              => 138,
        'name_size'             => 24,                // pt (nativo dompdf = idéntico en pantalla y PDF)
        // --- Puesto ---
        'label_position'        => 'PUESTO',
        'label_position_show'   => true,             // se puede ocultar la etiqueta "PUESTO"
        'label_position_top'    => 150,
        'position_top'          => 153,
        'position_size'         => 12,                // pt
        // --- Pie: sello "POWERED BY" CrewCare (marca de agua) + consecutivo (SIEMPRE visibles). ---
        'powered_tone'          => 'gris',           // gris (fondos claros) | blanco (fondos oscuros)
        'powered_top'           => 158,              // mm — SIEMPRE por encima del consecutivo
        'show_consecutive'      => true,             // el consecutivo siempre existe (llave conservada = true)
        'consecutive_top'       => 165,
    ];

    /** Fuentes permitidas en el diseñador (allow-list defensiva). */
    public const FONTS = ['Poppins', 'Roboto', 'Montserrat', 'Arial'];

    /** Formas de foto permitidas. */
    public const PHOTO_SHAPES = ['circle', 'rounded', 'square'];

    /** Tono del sello CrewCare (marca de agua). */
    public const POWERED_TONES = ['gris', 'blanco'];

    /** Pesos de fuente permitidos para el nombre del proyecto (valor CSS => etiqueta). */
    public const FONT_WEIGHTS = [100 => 'Thin', 300 => 'Light', 400 => 'Regular', 700 => 'Bold', 900 => 'Black'];

    /**
     * Config efectiva = DEFAULTS fusionado con la config de la fila activa (si existe).
     * Siempre devuelve un mapa completo.
     */
    public static function activeConfig(): array
    {
        $row = static::where('active', 1)->first();
        $config = ($row && is_array($row->config)) ? $row->config : [];

        return array_merge(self::DEFAULTS, $config);
    }

    /**
     * Fila activa, o una instancia NUEVA sin guardar (config = DEFAULTS) si aún no hay ninguna.
     * Útil para el diseñador cuando la tabla está vacía.
     */
    public static function current(): self
    {
        $row = static::where('active', 1)->first();
        if ($row) {
            return $row;
        }

        $new = new static();
        $new->name = 'default';
        $new->config = self::DEFAULTS;
        $new->active = true;

        return $new;
    }
}
