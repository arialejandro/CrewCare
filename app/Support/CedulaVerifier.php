<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CedulaVerifier — el ROBOT que consulta la cédula profesional en la fuente pública y
 * devuelve nombre + profesión/especialidad. Es el sub-paso 'sep_auto' del Paso B (el que la
 * tabla medic_credentials ya estaba preparada para recibir: source='sep_auto', autor NULL).
 *
 * ── FUENTE: BÚHOLEGAL, NO la SEP oficial ─────────────────────────────────────────
 * La SEP oficial (cedulaprofesional.sep.gob.mx) protege su consulta con CAPTCHA. Saltarlo con
 * un anticaptcha sería forzar una barrera puesta a propósito → NO se hace, y esa ruta no se
 * toca. BúhoLegal expone el MISMO Registro Nacional de Profesionistas SIN captcha; es la
 * fuente que se consulta. A cambio es frágil: 403ea a clientes sin User-Agent de navegador y
 * su HTML puede cambiar sin aviso. Por eso TODO aquí degrada con elegancia (ver más abajo).
 *
 * ── LA RECETA (replicada de la LÓGICA del PoC Node, no de su código) ──────────────
 *   a) GET a la página de consulta → extraer csrfmiddlewaretoken (es un sitio Django) y las
 *      cookies de sesión.
 *   b) POST a la misma página con el token + la cookie + el número de cédula.
 *   c) Parsear la TABLA de resultados con un parser DOM (DOMDocument/DOMXPath), NUNCA con
 *      regex sobre HTML. Por fila: número, nombre, carrera/profesión, institución, tipo, año.
 *
 * ── DESACOPLAMIENTO Y CONFIG (por qué el endpoint no está hardcodeado) ───────────
 * El endpoint, el nombre del campo del número y el User-Agent viven en config/services.php
 * ('cedula'), alimentados por env. Si la fuente cambia, se toca la CONFIG (o solo esta clase),
 * nunca el flujo del Paso B. ⚠️ EL NOMBRE EXACTO DEL CAMPO DEL NÚMERO Y LA RUTA DE CONSULTA
 * SE CONFIRMAN CONTRA EL PoC QUE FUNCIONA antes de encender el flag: los valores por defecto
 * de config son la mejor conjetura (Django + /consultasep/), no una verdad verificada por mí.
 *
 * ── NUNCA LANZA, NUNCA BLOQUEA ───────────────────────────────────────────────────
 * lookup() atrapa CUALQUIER \Throwable y lo traduce a un CedulaResult de error. El llamador
 * (MedicCredentialController::attemptAutoVerify) degrada a modo MANUAL ante un error: la
 * cédula queda pendiente y el botón "Validar" sigue disponible. El automático es una
 * comodidad, no una barrera.
 *
 * FUERA A PROPÓSITO: detección de género. El PoC la traía como heurístico frágil; aquí no se
 * usa (no aporta al cotejo y adivinar el género de una persona por su nombre es un error).
 */
class CedulaVerifier
{
    /**
     * Traducción del código de 'tipo' del registro al NIVEL académico (del PoC):
     *   C1 → Licenciatura · E/A → Especialidad · M → Maestría · D → Doctorado.
     * Sirve para saber si el médico es general (licenciatura) o especialista (especialidad),
     * dato válido para gestión de riesgo.
     */
    const TIPO_MAP = [
        'C1' => 'Licenciatura',
        'E'  => 'Especialidad',
        'A'  => 'Especialidad',
        'M'  => 'Maestría',
        'D'  => 'Doctorado',
    ];

    /** ¿Está encendido el enganche automático? (flag maestro, OFF por defecto). */
    public static function enabled(): bool
    {
        return (bool) config('services.cedula.auto', false);
    }

    /**
     * Consulta un número de cédula. Devuelve SIEMPRE un CedulaResult; jamás lanza.
     */
    public static function lookup(string $cedula): CedulaResult
    {
        $cedula = trim($cedula);
        if ($cedula === '') {
            return CedulaResult::error('empty_input', null, 'Número de cédula vacío.');
        }

        try {
            $base    = rtrim((string) config('services.cedula.base', 'https://www.buholegal.com'), '/');
            $path    = (string) config('services.cedula.search_path', '/consultasep/');
            $field   = (string) config('services.cedula.field', 'cedula');
            // PISO al timeout, igual que a retries: sin él, un CEDULA_TIMEOUT vacío/no-numérico
            // en env daría (int)'' === 0, y Guzzle interpreta timeout(0) como ESPERA INFINITA →
            // una conexión colgada a la fuente (frágil por diseño) bloquearía el request. El
            // "nunca bloquea" exige un tope real.
            $timeout = max(1, (int) config('services.cedula.timeout', 12));
            $retries = max(1, (int) config('services.cedula.retries', 2));
            $url     = $base . '/' . ltrim($path, '/');
            $ua      = self::browserUserAgent();

            // Cookie jar COMPARTIDO GET↔POST: Django coteja el csrfmiddlewaretoken del form
            // contra la cookie csrftoken. Sin jar compartido, el POST daría 403 CSRF.
            $jar = new \GuzzleHttp\Cookie\CookieJar();

            // Anti-SSRF: la fuente es externa y "puede cambiar sin aviso". Un 3xx suyo (fuente
            // comprometida / spoofeada / MITM) hacia un host interno haría que ESTE servidor
            // emita la petición a ese destino. Se sigue redirect SOLO si se queda en el mismo
            // host de la fuente y en https (así no rompe un posible POST-redirect-GET legítimo,
            // pero bloquea el salto a 169.254.169.254 y compañía). Un salto fuera lanza; el
            // try/catch de lookup lo degrada a error → modo manual, sin propagar.
            $baseHost = strtolower((string) parse_url($base, PHP_URL_HOST));
            $redirectGuard = [
                'max'       => 5,
                'strict'    => true,
                'protocols' => ['https'],
                'on_redirect' => function ($request, $response, $uri) use ($baseHost) {
                    $host = strtolower((string) $uri->getHost());
                    if ($baseHost !== '' && $host !== $baseHost) {
                        throw new \RuntimeException('CedulaVerifier: redirect fuera del host de la fuente bloqueado (' . $host . ').');
                    }
                },
            ];
            $httpOpts = ['cookies' => $jar, 'allow_redirects' => $redirectGuard];

            // (a) GET → token + cookies de sesión.
            $get = Http::withOptions($httpOpts)
                ->withHeaders(['User-Agent' => $ua, 'Accept' => 'text/html,application/xhtml+xml'])
                ->timeout($timeout)
                ->retry($retries, 400)
                ->get($url);

            if (!$get->successful()) {
                return CedulaResult::error('http_get_' . $get->status(), $get->status(),
                    'La página de consulta respondió ' . $get->status() . '.');
            }

            $token = self::extractCsrf($get->body());
            if ($token === null) {
                return CedulaResult::error('no_csrf', $get->status(),
                    'No se halló csrfmiddlewaretoken: la fuente cambió su formulario.');
            }

            // (b) POST con token + cookie + número.
            $post = Http::withOptions($httpOpts)
                ->withHeaders([
                    'User-Agent'  => $ua,
                    'Referer'     => $url,     // Django exige Referer/Origin coincidentes en HTTPS.
                    'Origin'      => $base,
                    'X-CSRFToken' => $token,
                    'Accept'      => 'text/html,application/xhtml+xml',
                ])
                ->asForm()
                ->timeout($timeout)
                ->retry($retries, 400)
                ->post($url, [
                    'csrfmiddlewaretoken' => $token,
                    $field                => $cedula,
                ]);

            if (!$post->successful()) {
                return CedulaResult::error('http_post_' . $post->status(), $post->status(),
                    'La consulta respondió ' . $post->status() . '.');
            }

            // (c) parsear la tabla.
            $rows = self::parseResults($post->body());
            if ($rows === null) {
                return CedulaResult::error('no_table', $post->status(),
                    'No se halló la tabla de resultados: la fuente cambió su HTML.');
            }
            if (count($rows) === 0) {
                return CedulaResult::makeEmpty($cedula);   // número no existe en el registro.
            }

            return CedulaResult::fromRows($cedula, $rows);
        } catch (\Throwable $e) {
            // NUNCA propaga: cualquier fallo del robot degrada a manual, no rompe la operación.
            Log::warning('CedulaVerifier::lookup — ' . $e->getMessage());
            return CedulaResult::error('exception', null, $e->getMessage());
        }
    }

    /** Nivel académico desde el código de tipo. '' si no se reconoce (no se inventa). */
    public static function levelFromTipo($tipo): string
    {
        $t = strtoupper(trim((string) $tipo));
        if ($t === '') { return ''; }
        if (isset(self::TIPO_MAP[$t])) { return self::TIPO_MAP[$t]; }   // exacto (C1, E, A, M, D)
        $two = substr($t, 0, 2);
        if (isset(self::TIPO_MAP[$two])) { return self::TIPO_MAP[$two]; }   // C1x
        $one = substr($t, 0, 1);
        if (isset(self::TIPO_MAP[$one])) { return self::TIPO_MAP[$one]; }   // E2, A1, M…, D…
        return '';
    }

    // ---------------------------------------------------------------------------
    // Parseo (DOM, sin regex sobre HTML)
    // ---------------------------------------------------------------------------

    /**
     * @return string|null  El csrfmiddlewaretoken, o null si no está (HTML cambiado/bloqueado).
     */
    protected static function extractCsrf(string $html): ?string
    {
        $doc = self::loadHtml($html);
        if ($doc === null) { return null; }
        $xp = new \DOMXPath($doc);
        $nodes = $xp->query('//input[@name="csrfmiddlewaretoken"]');
        if ($nodes !== false) {
            foreach ($nodes as $n) {
                $v = $n->getAttribute('value');
                if ($v !== '') { return $v; }
            }
        }
        return null;
    }

    /**
     * @return array|null  null = NO hay tabla de resultados reconocible (HTML cambiado → error).
     *                      []  = tabla presente pero SIN filas (número inexistente → empty).
     *                      [filas] = resultados.
     */
    protected static function parseResults(string $html): ?array
    {
        $doc = self::loadHtml($html);
        if ($doc === null) { return null; }
        $xp = new \DOMXPath($doc);

        $tables = $xp->query('//table');
        if ($tables === false || $tables->length === 0) { return null; }

        // Se prueba cada tabla; la primera cuyas cabeceras reconozcamos es la de resultados.
        foreach ($tables as $table) {
            $rows = self::parseTable($xp, $table);
            if ($rows !== null) { return $rows; }   // [] es válido (tabla sin filas de datos).
        }
        return null;
    }

    /**
     * @return array|null  null = esta tabla no parece la de resultados (cabeceras no
     *                      reconocidas). [] / [filas] = sí lo es.
     */
    protected static function parseTable(\DOMXPath $xp, \DOMNode $table): ?array
    {
        // Mapa columna→clave por TEXTO de cabecera → robusto a que la fuente reordene columnas.
        $map = [];
        $ths = $xp->query('.//tr[1]/th | .//thead//th', $table);
        if ($ths !== false) {
            $i = 0;
            foreach ($ths as $th) {
                $map[$i] = self::classifyHeader(self::text($th));
                $i++;
            }
        }
        if (count(array_filter($map)) === 0) {
            return null;   // sin cabeceras reconocibles → no es la tabla de resultados.
        }

        $rows = [];
        $trs = $xp->query('.//tr', $table);
        if ($trs === false) { return []; }
        foreach ($trs as $tr) {
            $tds = $xp->query('./td', $tr);
            if ($tds === false || $tds->length === 0) { continue; }   // fila de cabecera → saltar.

            $row = ['numero' => '', 'nombre' => '', 'carrera' => '', 'institucion' => '', 'tipo' => '', 'anio' => ''];
            $j = 0;
            foreach ($tds as $td) {
                $key = $map[$j] ?? '';   // SOBREVIVE a filas con más/menos celdas que la cabecera.
                if ($key !== '' && array_key_exists($key, $row)) {
                    $row[$key] = self::text($td);
                }
                $j++;
            }
            // Fila útil si trae al menos número o nombre (descarta filas de relleno/vacías).
            if ($row['numero'] !== '' || $row['nombre'] !== '') {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /** Clasifica una cabecera por su texto normalizado (minúsculas sin acentos). */
    protected static function classifyHeader(string $text): string
    {
        $t = SepRegistry::normalizeName($text);   // reutiliza el normalizador del Paso B.
        if ($t === '') { return ''; }
        if (strpos($t, 'cedula') !== false || strpos($t, 'numero') !== false || strpos($t, 'folio') !== false) { return 'numero'; }
        if (strpos($t, 'nombre') !== false) { return 'nombre'; }
        if (strpos($t, 'carrera') !== false || strpos($t, 'profesion') !== false || strpos($t, 'titulo') !== false) { return 'carrera'; }
        if (strpos($t, 'institucion') !== false || strpos($t, 'universidad') !== false || strpos($t, 'escuela') !== false) { return 'institucion'; }
        if (strpos($t, 'tipo') !== false || strpos($t, 'nivel') !== false) { return 'tipo'; }
        if (strpos($t, 'anio') !== false || strpos($t, 'ano') !== false || strpos($t, 'fecha') !== false || strpos($t, 'registro') !== false) { return 'anio'; }
        return '';
    }

    /** Texto de un nodo, con espacios colapsados y recortado. */
    protected static function text(\DOMNode $node): string
    {
        $t = preg_replace('/\s+/u', ' ', (string) $node->textContent);
        return trim((string) $t);
    }

    protected static function loadHtml(string $html): ?\DOMDocument
    {
        if (trim($html) === '') { return null; }
        // PHP 7.4: HTML-ENTITIES conserva los acentos. Sin esto, DOMDocument asume ISO-8859-1
        // y los nombres del registro llegan con mojibake → el cotejo antisuplantación fallaría.
        // (mb_convert_encoding con HTML-ENTITIES está deprecado en 8.1, pero esta app es 7.4.)
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc  = new \DOMDocument();
        $ok   = $doc->loadHTML($html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $ok ? $doc : null;
    }

    protected static function browserUserAgent(): string
    {
        // La fuente 403ea a clientes sin UA de navegador. Configurable por si hay que rotarlo.
        // OJO: el default de config() NO cubre la clave presente-pero-null (env sin definir
        // devuelve null, no "ausente"), así que el fallback va con ?: sobre el valor leído.
        $ua = config('services.cedula.user_agent');
        return (is_string($ua) && $ua !== '') ? $ua
            : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
    }
}
