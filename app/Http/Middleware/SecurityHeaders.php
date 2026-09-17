<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * SecurityHeaders — cabeceras de seguridad + transporte. (2026-08-30 · endurecimiento)
 *
 * SIN FRICCIÓN para el usuario: no pide nada, no interrumpe. Dos capas:
 *  - SIEMPRE (local y prod): X-Content-Type-Options, X-Frame-Options, Referrer-Policy, y la
 *    CSP en modo REPORTE (Content-Security-Policy-Report-Only) → observa qué se rompería SIN
 *    bloquear nada; las violaciones se mandan a /csp-report para medir el alcance antes de activar.
 *  - SÓLO EN PRODUCCIÓN: redirige http→https y agrega HSTS (sobre conexiones ya seguras). El
 *    local sigue en http, sin cambio.
 *
 * Gate por `app()->environment('production')` para lo de transporte; el resto es inofensivo.
 */
class SecurityHeaders
{
    /**
     * ORÍGENES EXTERNOS QUE EL MOTOR GEO NECESITA (`connect-src`).
     *
     * 🪤 LA MINA: `connect-src 'self'` a secas dejó MUERTO todo el módulo geo el día que la CSP pasó a
     * BLOQUEO, y el fallo no se ve como fallo. El navegador SÍ obtiene las coordenadas (la
     * geolocalización no la toca la CSP), pero la llamada que las convierte en dirección se bloquea en
     * silencio; el código degrada a "escríbela a mano" —que es su comportamiento correcto cuando no hay
     * internet— así que en pantalla parece que el GPS no sirve. En producción se leyó exactamente así:
     * "la geolocalización nunca funciona, ya probé en varios dispositivos". No era el dispositivo ni el
     * permiso: era esta cabecera. El log de /csp-report lo dijo desde el primer día (54 violaciones de
     * connect-src en una sola jornada) — nadie lo estaba leyendo.
     *
     * Estos tres son los servicios que usa public/js/crewcare-geo.js. Si algún día cambian AHÍ, cambian
     * AQUÍ: son el mismo contrato repartido en dos archivos.
     *   · nominatim  → dirección ⇄ coordenadas (Scouting, DSR, reportes de seguridad)
     *   · overpass   → hospitales cercanos (PAE, MEDEVAC, DSR) — dos espejos, el 2º es el respaldo
     *   · osrm       → ETA por carretera al hospital — dos espejos, igual
     *
     * Esto NO abre la política: `connect-src` sigue cerrado para todo lo demás, y ninguna otra directiva
     * se toca. Lo que estos hosts reciben son coordenadas, nada más — ningún dato de la producción ni de
     * las personas sale por aquí.
     */
    private const GEO_CONNECT_SRC = [
        'https://nominatim.openstreetmap.org',
        'https://overpass-api.de',
        'https://overpass.kumi.systems',
        'https://router.project-osrm.org',
        'https://routing.openstreetmap.de',
    ];

    public function handle(Request $request, Closure $next)
    {
        $prod = app()->environment('production');

        // (CSP · fuente única del nonce) Nonce por PETICIÓN, compartido SIEMPRE a las vistas —aunque
        // la política no se emita— para que `{{ $cspNonce }}` resuelva en local igual que en prod
        // (AppServiceProvider deja '' como piso para renders sin request). La MISMA variable alimenta
        // la política de abajo: una sola fuente para la vista y la cabecera.
        $nonce = base64_encode(random_bytes(16));
        \Illuminate\Support\Facades\View::share('cspNonce', $nonce);

        // 1) PRODUCCIÓN: fuerza https (redirección http→https). No toca el local.
        //    Con TrustProxies confiando el proxy TLS, isSecure() refleja X-Forwarded-Proto,
        //    así que detrás del reverse-proxy no hay bucle. El healthcheck se exime.
        if ($prod && ! $request->isSecure() && ! $request->is('healthz', 'up')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        $emitReport  = (bool) config('crewcare.security.csp_report', true);
        $emitEnforce = (bool) config('crewcare.security.csp_enforce', false);

        // (CSP · sweep del nonce — MISMA fuente única) Estampa el MISMO $nonce en cada <script> Y cada
        // <style> EN LÍNEA sin nonce del HTML de salida. Un solo punto, la misma fuente que la política y
        // que `$cspNonce` de las vistas: NO es un segundo mecanismo, es aplicar la fuente única. Con
        // `style-src 'self' 'nonce-…'` esto deja pasar los ~80 <style> legítimos de la app (marca, glass,
        // componentes) mientras un <style> INYECTADO —sin el nonce por-petición— queda BLOQUEADO. No
        // reescribe archivos fuente: sólo el cuerpo de la respuesta, en memoria. Idempotente.
        if ($emitReport || $emitEnforce) {
            $this->stampInlineNonce($response, $nonce);
        }

        // 2) Cabeceras de higiene — SIEMPRE (no fuerzan nada, sólo endurecen).
        $headers = [
            'X-Content-Type-Options'            => 'nosniff',
            'X-Frame-Options'                   => 'SAMEORIGIN',
            'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        // 3) CSP — la MISMA política completa (fuente única `cspPolicy`) se emite en UNO de dos modos,
        //    nunca ambos:
        //      · enforce ON  → `Content-Security-Policy` (BLOQUEA). NADA en modo reporte: la política
        //        está cerrada del todo (script/style/style-attr/img/font/object/base/connect…).
        //      · enforce OFF → `Content-Security-Policy-Report-Only` (sólo MIDE) — estado pre-cutover.
        //    El cutover a prod es del owner (CREWCARE_CSP_ENFORCE=true); el default committeado mide.
        if ($emitEnforce) {
            $headers['Content-Security-Policy'] = $this->cspPolicy($nonce);
        } elseif ($emitReport) {
            $headers['Content-Security-Policy-Report-Only'] = $this->cspPolicy($nonce);
        }

        // 4) HSTS — sólo en producción y sobre https real (nunca sobre http).
        if ($prod && $request->isSecure() && config('crewcare.security.hsts', true)) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $k => $v) {
            if (! $response->headers->has($k)) {
                $response->headers->set($k, $v);
            }
        }

        return $response;
    }

    /**
     * Estampa nonce="$nonce" en los <script> y <style> EN LÍNEA (sin `src` y sin `nonce`) del HTML de
     * salida, usando la misma fuente única ($nonce por petición). Sólo respuestas text/html normales (no
     * binarias ni en streaming). Idempotente: respeta los `src=`/`nonce=` ya presentes.
     *
     * Los <style> propios reciben el nonce → válidos bajo `style-src 'self' 'nonce-…'`; un <style>
     * inyectado (sin el nonce por-petición) queda bloqueado. Los `style=` en atributo NO llevan nonce
     * (no pueden): los cubre `style-src-attr 'unsafe-inline'`, aparte.
     *
     * SEGURIDAD ANTE EL FOOTGUN de preg_replace: si un pase fallara devolvería null → se conserva lo que
     * había ANTES de ese pase (nunca se pierde el estampado ya hecho ni se rompe el cuerpo). Nunca escribe
     * en disco: sólo transforma la salida.
     */
    private function stampInlineNonce($response, string $nonce): void
    {
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            || $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return;
        }

        $ct = (string) $response->headers->get('Content-Type', '');
        if ($ct !== '' && stripos($ct, 'text/html') === false) {
            return; // JSON, PDF, descargas… no se tocan.
        }

        $html = $response->getContent();
        if (! is_string($html) || $html === ''
            || (stripos($html, '<script') === false && stripos($html, '<style') === false)) {
            return;
        }
        $orig = $html;

        // <script …> ejecutable, sin src y sin nonce → inserta el nonce justo después de "<script".
        // Los <script type="application/json"> (bloques de datos) también reciben el nonce: es inofensivo.
        $pass = preg_replace_callback(
            '/<script(?=[\s>])(?![^>]*\bsrc=)(?![^>]*\bnonce=)([^>]*)>/i',
            static fn ($m) => '<script nonce="' . $nonce . '"' . $m[1] . '>',
            $html
        );
        if (is_string($pass)) {
            $html = $pass; // pase de <script> ok; si falló (null), se conserva $html previo.
        }

        // <style …> en línea sin nonce → mismo trato (un <style> no tiene `src`).
        $pass = preg_replace_callback(
            '/<style(?=[\s>])(?![^>]*\bnonce=)([^>]*)>/i',
            static fn ($m) => '<style nonce="' . $nonce . '"' . $m[1] . '>',
            $html
        );
        if (is_string($pass)) {
            $html = $pass;
        }

        if ($html !== $orig) {
            $response->setContent($html);
            $response->headers->remove('Content-Length'); // el largo cambió; que se recalcule al enviar.
        }
    }

    /**
     * Política CSP COMPLETA y ÚNICA — se emite en enforce (bloquea) o en report-only (mide) según el
     * flag; el MISMO texto en ambos casos. Cerrada del todo tras empaquetar librerías y autoalojar
     * fuentes:
     *   · script-src 'self' 'nonce-…'  — sólo scripts propios/con nonce (sin unsafe-inline).
     *   · style-src  'self' 'nonce-…'  — hojas propias y <style> CON nonce (los estampa el sweep); un
     *     <style> o una hoja externa INYECTADA quedan fuera. El vector serio de inyección de CSS, cerrado.
     *   · style-src-attr 'unsafe-inline' — los `style=` en atributo (1319, mayormente de correos exentos)
     *     sólo pintan SU elemento: se permiten para no reescribir 623 estáticos repartidos en 161 vistas
     *     (higiene de código, no seguridad). Un atributo no puede llevar nonce.
     *   · img-src 'self' data: blob:   — imágenes propias + placeholder (data:) + Cropper/pdf.js (blob:).
     *   · font-src 'self' data:        — TODO autoalojado; gstatic FUERA (ya no hay Google Fonts).
     *   · object-src 'none' + base-uri 'self' — hacen efectivo al nonce (cierran <object>/<base>).
     *   · default-src/frame-ancestors 'self' — el resto, acotado al propio origen.
     *   · connect-src 'self' + GEO_CONNECT_SRC — propio origen MÁS los servicios OSM del motor geo
     *     (ver la constante arriba: sin ellos la app parece tener el GPS roto).
     */
    private function cspPolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "style-src 'self' 'nonce-{$nonce}'",
            "style-src-attr 'unsafe-inline'",
            "script-src 'self' 'nonce-{$nonce}'",
            "connect-src 'self' " . implode(' ', self::GEO_CONNECT_SRC),
            'report-uri /csp-report',
        ]);
    }
}
