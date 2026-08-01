<?php

namespace App\Support;

/**
 * SepRegistry — todo lo que la app sabe sobre el Registro Nacional de Profesionistas
 * de la SEP, que es el ÚNICO padrón contra el que se coteja la cédula profesional de
 * un médico. Concentra tres cosas que hasta hoy estaban dispersas o simplemente no
 * existían:
 *
 *   1. La LISTA BLANCA DE DOMINIO de la SEP (OFFICIAL_HOSTS).
 *   2. El saneado del enlace antes de pintarlo como <a> (safeHref).
 *   3. La comparación ANTISUPLANTACIÓN de nombres (namesMatch / normalizeName).
 *
 * POR QUÉ ESTA CLASE EXISTE — y por qué es la PRIMERA de su tipo:
 * hoy la app NO tiene ninguna lista blanca de dominio. La única defensa de URL que hay
 * es de ESQUEMA (se acepta http/https y se rechaza lo demás, sobre todo javascript:) y
 * está COPIADA INLINE en 3 Blades — el original vive en
 * resources/views/admin/standards/show.blade.php:64-74. Esa defensa impide un XSS por
 * esquema, pero NO dice nada sobre a QUIÉN apunta el enlace: con ella, un
 * https://buholegal.com/cedula/123 se pinta igual de "oficial" que el padrón real.
 * Para una cédula profesional eso no alcanza: el enlace es la PRUEBA de que el número
 * fue cotejado, así que el destino importa tanto como el esquema. De ahí la lista de
 * dominio, que empieza aquí.
 *
 * SIN DEPENDENCIAS DE LARAVEL, a propósito: la clase es PHP puro (parse_url, strtr,
 * preg_replace) para poder ejercitarla con un simple require en una prueba, sin
 * arrancar el framework.
 *
 * DEFENSIVA: isOfficialUrl() envuelve todo en try/catch y devuelve false ante cualquier
 * problema — un parse_url() sobre basura no debe tronar una vista.
 *
 * PHP 7.4 ESTRICTO: sin nullsafe, sin match, sin str_contains/str_starts_with.
 *
 * (2026-07-19) Creada para el cimiento de cédula profesional (PASO B).
 */
class SepRegistry
{
    /**
     * Dominios que la app reconoce como el padrón oficial. Se aceptan también sus
     * SUBDOMINIOS (ver isOfficialUrl); cualquier otro host se rechaza.
     */
    const OFFICIAL_HOSTS = ['cedulaprofesional.sep.gob.mx', 'www.cedulaprofesional.sep.gob.mx', 'sep.gob.mx', 'www.sep.gob.mx'];

    /** Buscador avanzado del registro — el punto de entrada para cotejar un número a mano. */
    const SEARCH_URL = 'https://cedulaprofesional.sep.gob.mx/cedula/presidencia/indexAvanzada.action';

    /**
     * Mapa explícito de diacríticos. Se usa strtr() y NO iconv()//TRANSLIT: iconv depende
     * del locale del servidor y en Windows/Laragon degrada distinto que en el Linux de
     * producción — un nombre que coincide en local dejaría de coincidir en prod. Un mapa
     * fijo da el MISMO resultado en las dos máquinas.
     *
     * Las mayúsculas son redundantes (normalizeName baja a minúsculas antes de aplicar el
     * mapa) pero se dejan por si alguien reutiliza el mapa fuera de ese orden.
     */
    const ACCENT_MAP = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
    ];

    /** Palabras de este largo o menos se descartan al comparar nombres ("de", "la", "y"). */
    const PARTICLE_MAX_LEN = 2;

    /**
     * ¿La URL apunta de verdad al registro de la SEP?
     *
     * Exige TRES cosas a la vez: cadena no vacía, esquema http/https, y host dentro de
     * OFFICIAL_HOSTS por igualdad exacta O como subdominio.
     *
     * @param  mixed $url
     * @return bool
     */
    public static function isOfficialUrl($url): bool
    {
        try {
            $url = trim((string) $url);
            if ($url === '') {
                return false;
            }

            // 1) Esquema. Misma defensa que ya vive inline en los 3 Blades: corta
            //    javascript:, data:, file: y demás.
            $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if ($scheme !== 'http' && $scheme !== 'https') {
                return false;
            }

            // 2) Host. parse_url devuelve null si la URL viene malformada.
            $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '') {
                return false;
            }

            foreach (self::OFFICIAL_HOSTS as $allowed) {
                $allowed = mb_strtolower($allowed);

                if ($host === $allowed) {
                    return true;
                }

                // ---- AQUÍ ES DONDE UNA LISTA BLANCA DE DOMINIO SE ROMPE EN LA PRÁCTICA ----
                // El sufijo SE COMPARA CON EL PUNTO DELANTE ('.' . $allowed). Comparar contra
                // el sufijo pelado 'sep.gob.mx' dejaría pasar 'malicioso-sep.gob.mx', que
                // TERMINA en esa cadena pero es un dominio COMPLETAMENTE AJENO, registrable
                // por cualquiera. Con el punto, 'malicioso-sep.gob.mx' termina en
                // '-sep.gob.mx' ≠ '.sep.gob.mx' y se rechaza, mientras que el subdominio
                // legítimo 'cedulaprofesional.sep.gob.mx' sí termina en '.sep.gob.mx'.
                // No quites el punto.
                $suffix = '.' . $allowed;
                $sufLen = strlen($suffix);
                if (strlen($host) > $sufLen && substr($host, -$sufLen) === $suffix) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            // Ante cualquier sorpresa, NO es oficial. Fallar cerrado.
            return false;
        }
    }

    /**
     * URL lista para meter en un href, o null si no es del registro oficial.
     *
     * Devuelve null y NO '' a propósito: obliga a la vista a preguntar `!== null` de forma
     * explícita en vez de apoyarse en la falsedad de la cadena vacía. Es el mismo patrón
     * que ya está vivo en $refHref (resources/views/admin/standards/show.blade.php:64-74),
     * y evita el bug clásico de pintar <a href=""> — un enlace que recarga la página actual
     * y aparenta que la cédula tiene respaldo cuando no lo tiene.
     *
     * @param  mixed $url
     * @return string|null
     */
    public static function safeHref($url)
    {
        if (! self::isOfficialUrl($url)) {
            return null;
        }

        return trim((string) $url);
    }

    /**
     * Normaliza un nombre de persona para poder compararlo.
     *
     * Minúsculas → sin diacríticos → sólo letras y espacios → espacios colapsados.
     * "  José  Luis  Palomino-Ramírez, Dr. " queda como "jose luis palomino ramirez dr".
     *
     * @param  mixed $name
     * @return string
     */
    public static function normalizeName($name): string
    {
        $name = mb_strtolower(trim((string) $name));

        // Diacríticos con mapa fijo (ver ACCENT_MAP: iconv depende del locale).
        $name = strtr($name, self::ACCENT_MAP);

        // Fuera puntuación, dígitos y cualquier resto no convertido por el mapa: pasan a
        // espacio (no se borran) para no pegar dos palabras que estaban separadas por un
        // guion — "palomino-ramirez" debe dar dos palabras, no "palominoramirez".
        $name = preg_replace('/[^a-z\s]/u', ' ', $name);

        // Colapsa los espacios que acabamos de generar.
        $name = preg_replace('/\s+/u', ' ', (string) $name);

        return trim((string) $name);
    }

    /**
     * ¿Estos dos nombres son de la misma persona? CONSERVADOR por diseño.
     *
     * Este método enciende (o no) un distintivo con peso legal: "cédula verificada". Ante
     * la duda devuelve false — es preferible pedir una verificación manual de más que
     * afirmar una identidad de menos.
     *
     * REGLA: se normalizan ambos nombres, se parten en palabras, se descartan las de
     * {@see PARTICLE_MAX_LEN} letras o menos ("de", "la", "y") y se devuelve true SÓLO si
     * TODAS las palabras del conjunto más pequeño están en el más grande. Así se absorbe
     * el caso normal del padrón, que suele traer los dos apellidos cuando el usuario
     * capturó sólo uno.
     *
     * TRUE:
     *   ('Giovany Galicia',      'Giovany Galicia Ramírez')  → subconjunto: falta un apellido
     *   ('GIOVANY  GALICIA',     'giovany galicia')          → mayúsculas y espacios de más
     *   ('José Luis Palomino',   'Jose Luis Palomino')       → acentos
     *   ('Palomino Jose Luis',   'Jose Luis Palomino')       → el orden NO importa
     *
     * FALSE:
     *   ('Giovany Galicia',      'Jose Luis Palomino')       → no comparten ninguna palabra
     *   ('Giovany Galicia',      'Giovany Palomino')         → coincide el nombre, no el apellido
     *   ('',                     'Giovany Galicia')          → sobre la nada no se afirma nada
     *   ('De La Y',              'Giovany Galicia')          → sólo partículas: queda vacío
     *
     * OJO al auditar: el orden se ignora y las partículas se tiran. Ese comportamiento ES
     * la regla antisuplantación, no un efecto colateral.
     *
     * @param  mixed $a
     * @param  mixed $b
     * @return bool
     */
    public static function namesMatch($a, $b): bool
    {
        $normA = self::normalizeName($a);
        $normB = self::normalizeName($b);

        // Sobre la nada no se puede afirmar coincidencia.
        if ($normA === '' || $normB === '') {
            return false;
        }

        $wordsA = self::significantWords($normA);
        $wordsB = self::significantWords($normB);

        // Un nombre hecho sólo de partículas no distingue a nadie.
        if (empty($wordsA) || empty($wordsB)) {
            return false;
        }

        // El conjunto chico debe estar CONTENIDO en el grande.
        $small = count($wordsA) <= count($wordsB) ? $wordsA : $wordsB;
        $large = count($wordsA) <= count($wordsB) ? $wordsB : $wordsA;

        foreach ($small as $word) {
            if (! in_array($word, $large, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Palabras útiles de un nombre YA normalizado: las de más de PARTICLE_MAX_LEN letras.
     *
     * @param  string $normalized
     * @return array
     */
    /**
     * (2026-07-24) Palabras del nombre del REGISTRO que no aparecen en el de la app (y al revés).
     *
     * POR QUÉ: cuando namesMatch() dice que no, el mensaje decía sólo «no coinciden» y mostraba
     * los dos nombres completos. Con "Giovani Galicia Lopez" vs "Giovany Galicia Lopez" —una
     * letra— el operador no ve la diferencia, concluye que la validación está rota e insiste.
     * Señalar la palabra exacta convierte un "no funciona" en un typo de dos segundos.
     *
     * Devuelve ['solo_en_registro' => [...], 'solo_en_app' => [...]] con las palabras
     * SIGNIFICATIVAS (las partículas cortas no distinguen a nadie y ya se ignoran en el cotejo).
     *
     * @return array
     */
    public static function nameDiff($registro, $app): array
    {
        $wr = self::significantWords(self::normalizeName($registro));
        $wa = self::significantWords(self::normalizeName($app));

        return [
            'solo_en_registro' => array_values(array_diff($wr, $wa)),
            'solo_en_app'      => array_values(array_diff($wa, $wr)),
        ];
    }

    private static function significantWords(string $normalized): array
    {
        $words = [];

        foreach (explode(' ', $normalized) as $word) {
            if ($word !== '' && mb_strlen($word) > self::PARTICLE_MAX_LEN) {
                $words[] = $word;
            }
        }

        return array_values(array_unique($words));
    }
}
