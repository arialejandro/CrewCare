<?php

namespace App\Support;

/**
 * Catálogos PRECARGADOS para el intake (Paso 3). Evitan el texto libre donde el valor está
 * ESTANDARIZADO: régimen fiscal (clave SAT c_RegimenFiscal), parentesco y estado civil. Un
 * dropdown = menos error de captura y datos homogéneos para consultarlos después (p.ej. el
 * parentesco del contacto de emergencia en un reporte médico).
 *
 * NO es una tabla: es un catálogo de dominio estable; vive en código (como los demás enums de
 * la app). El régimen guarda CLAVE + NOMBRE derivado aquí (no se confía en el cliente).
 */
class SatCatalogs
{
    /**
     * Régimen fiscal SAT (c_RegimenFiscal). [clave => [nombre, fisica, moral]].
     * Las banderas fisica/moral son para FILTRAR el dropdown por naturaleza y reducir error;
     * no son una validación legal.
     */
    public const REGIMENES = [
        '601' => ['General de Ley Personas Morales', false, true],
        '603' => ['Personas Morales con Fines no Lucrativos', false, true],
        '605' => ['Sueldos y Salarios e Ingresos Asimilados a Salarios', true, false],
        '606' => ['Arrendamiento', true, false],
        '607' => ['Régimen de Enajenación o Adquisición de Bienes', true, false],
        '608' => ['Demás ingresos', true, false],
        '610' => ['Residentes en el Extranjero sin Establecimiento Permanente en México', true, true],
        '611' => ['Ingresos por Dividendos (socios y accionistas)', true, false],
        '612' => ['Personas Físicas con Actividades Empresariales y Profesionales', true, false],
        '614' => ['Ingresos por intereses', true, false],
        '615' => ['Régimen de los ingresos por obtención de premios', true, false],
        '616' => ['Sin obligaciones fiscales', true, false],
        '620' => ['Sociedades Cooperativas de Producción que optan por diferir sus ingresos', false, true],
        '621' => ['Incorporación Fiscal', true, false],
        '622' => ['Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras', true, true],
        '623' => ['Opcional para Grupos de Sociedades', false, true],
        '624' => ['Coordinados', false, true],
        '625' => ['Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas', true, false],
        '626' => ['Régimen Simplificado de Confianza', true, true],
    ];

    /** Parentescos (beneficiario y contacto de emergencia). Etiqueta = valor guardado. */
    public const PARENTESCOS = [
        'Cónyuge', 'Concubino(a) / Pareja', 'Padre', 'Madre', 'Hijo(a)', 'Hermano(a)',
        'Abuelo(a)', 'Nieto(a)', 'Tío(a)', 'Sobrino(a)', 'Primo(a)',
        'Suegro(a)', 'Yerno / Nuera', 'Cuñado(a)', 'Amigo(a)', 'Otro',
    ];

    /** Estado civil. Etiqueta = valor guardado. */
    public const ESTADOS_CIVILES = [
        'Soltero(a)', 'Casado(a)', 'Unión libre', 'Concubinato',
        'Separado(a)', 'Divorciado(a)', 'Viudo(a)',
    ];

    /** Opciones de régimen aplicables a la naturaleza ('fisica'|'moral'). ['605' => 'Sueldos…']. */
    public static function regimenesFor(string $legalNature): array
    {
        $moral = $legalNature === 'moral';
        $out = [];
        foreach (self::REGIMENES as $code => [$name, $fisica, $esMoral]) {
            if ($moral ? $esMoral : $fisica) {
                $out[$code] = $name;
            }
        }
        return $out;
    }

    /** Nombre oficial de una clave de régimen, o null si no está en el catálogo. */
    public static function regimenName(?string $code): ?string
    {
        $code = trim((string) $code);
        return $code !== '' && isset(self::REGIMENES[$code]) ? self::REGIMENES[$code][0] : null;
    }
}
