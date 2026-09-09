<?php

namespace App\Support;

/**
 * CallSheetFormats — presets del BACK del llamado (crew list) sobre el formato UNIVERSAL tipo
 * CASPER / Silver-Olson-Williams 201. Todos comparten el MISMO esqueleto (# · Puesto · Nombre +
 * bandas grises + rejilla + 3 columnas + pie); sólo cambian unos ejes, catalogados de 12 llamados
 * reales (TGH, PSI, APS2, LU, LGS, HTLR, ENEG, LB, QPCS, THECHASE, AP…):
 *
 *   - columnas de logística del centro   (hotel / pickup / lugar / call / out)
 *   - estilo del bloque de comidas        (matriz de conteo / "listo @" + totales / lunch / lista)
 *   - bloques de notas del pie            (generales / nomenclatura / radio / emergencia / hoteles)
 *   - idioma de los encabezados           (es / en)
 *
 * El motor ({@see \App\Support\CallSheetEngine}) NO cambia: entrega los mismos datos por persona; el
 * preset sólo decide QUÉ columnas/labels/bloques se pintan. Las columnas sin dato aún (hotel, out) se
 * pintan vacías (rellenables), igual que en los llamados reales.
 */
class CallSheetFormats
{
    /** Metadatos de cada columna de logística (label es/en, ancho %, alineación). */
    public const COLUMNS = [
        'hotel'  => ['es' => 'Hotel', 'en' => 'Hotel', 'w' => 11, 'align' => 'center', 'bold' => false],
        'pickup' => ['es' => 'P/U',   'en' => 'P.UP',  'w' => 11, 'align' => 'center', 'bold' => false],
        'place'  => ['es' => '@',     'en' => '@',     'w' => 9,  'align' => 'center', 'bold' => false],
        'call'   => ['es' => 'Hora',  'en' => 'Call',  'w' => 13, 'align' => 'center', 'bold' => true],
        'out'    => ['es' => 'Out',   'en' => 'Out',   'w' => 10, 'align' => 'center', 'bold' => false],
    ];

    /**
     * Los 4 presets (familias del catálogo). Cada uno:
     *   label     — nombre visible del formato
     *   base      — de qué llamado real sale (referencia)
     *   paper     — legal (oficio) | letter (carta)
     *   lang      — es | en (encabezados; los datos son los mismos)
     *   bands     — gray | black (color de la banda de departamento)
     *   cols      — columnas de logística (además de # · Puesto · Nombre), en orden
     *   meals     — matrix | ready | lunch | list | none
     *   notes     — bloques del pie, en orden
     */
    public const PRESETS = [
        'mx' => [
            'label' => 'México (estándar)',
            'base'  => 'TGH',
            'paper' => 'legal', 'lang' => 'es', 'bands' => 'gray',
            'cols'  => ['pickup', 'place', 'call'],
            'meals' => 'matrix',
            'notes' => ['general', 'nomenclature', 'radio'],
        ],
        'mx_hotel' => [
            'label' => 'México + Hotel (unidad foránea)',
            'base'  => 'TGH 2ª unidad / LGS',
            'paper' => 'legal', 'lang' => 'es', 'bands' => 'gray',
            'cols'  => ['hotel', 'pickup', 'place', 'call'],
            'meals' => 'matrix',
            'notes' => ['general', 'nomenclature', 'hotels', 'radio'],
        ],
        'intl' => [
            'label' => 'International (EN)',
            'base'  => 'PSI / LU',
            'paper' => 'legal', 'lang' => 'en', 'bands' => 'gray',
            'cols'  => ['hotel', 'pickup', 'call'],
            'meals' => 'ready',
            'notes' => ['hotels', 'nomenclature', 'radio_grid'],
        ],
        'us' => [
            'label' => 'US (minimal)',
            'base'  => 'APS2 / AP',
            'paper' => 'legal', 'lang' => 'en', 'bands' => 'gray',
            'cols'  => ['pickup', 'call'],
            'meals' => 'lunch',
            'notes' => ['emergency', 'radio'],
        ],
    ];

    public const DEFAULT = 'mx';

    /** Ajustes GLOBALES que se aplican sobre cualquier preset (se eligen una vez por producción). */
    public const PAPERS = ['legal' => 'Oficio', 'letter' => 'Carta'];
    public const BANDS  = ['gray' => 'Gris', 'black' => 'Negro'];

    /** Preset válido o el default. */
    public static function resolve(?string $name): array
    {
        $key = $name && isset(self::PRESETS[$name]) ? $name : self::DEFAULT;
        return ['key' => $key] + self::PRESETS[$key];
    }

    /** Opciones para el selector (key => label). */
    public static function options(): array
    {
        $out = [];
        foreach (self::PRESETS as $k => $p) {
            $out[$k] = $p['label'];
        }
        return $out;
    }

    /**
     * Resuelve las columnas de la tabla para un preset: # + Puesto + Nombre + las de logística.
     * Reparte el ancho: # fijo (4%), logística por su `w`, y el resto lo dividen Puesto y Nombre.
     * Devuelve filas con {key, label, w, align, bold} listas para el blade.
     */
    public static function tableColumns(array $preset): array
    {
        $en = ($preset['lang'] ?? 'es') === 'en';
        $logi = [];
        $used = 0;
        foreach ($preset['cols'] as $key) {
            $c = self::COLUMNS[$key] ?? null;
            if (! $c) { continue; }
            $logi[] = ['key' => $key, 'label' => $c[$en ? 'en' : 'es'], 'w' => $c['w'], 'align' => $c['align'], 'bold' => $c['bold']];
            $used += $c['w'];
        }
        $rest = max(20, 96 - $used);         // 4% para #; el resto lo dividen Puesto/Nombre
        $half = round($rest / 2, 1);

        return array_merge([
            ['key' => 'num',   'label' => '#',                        'w' => 4,     'align' => 'center', 'bold' => false],
            ['key' => 'title', 'label' => $en ? 'Title' : 'Puesto',   'w' => $half, 'align' => 'left',   'bold' => false],
            ['key' => 'name',  'label' => $en ? 'Name' : 'Nombre',    'w' => $half, 'align' => 'left',   'bold' => false],
        ], $logi);
    }
}
