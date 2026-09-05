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

        // (CSP · sweep del nonce — MISMA fuente única) Estampa el MISMO $nonce en cada <script> en
        // línea sin nonce del HTML de salida. Un solo punto, la misma fuente que la política y que
        // `$cspNonce` de las vistas: NO es un segundo mecanismo, es aplicar la fuente única. Garantiza
        // que ningún inline se escape al pasar a bloqueo (si uno se escapa, al bloquear deja de correr).
        // No reescribe archivos fuente: sólo el cuerpo de la respuesta, en memoria. Idempotente.
        // Se estampa siempre que se emita ALGUNA CSP con nonce (reporte y/o enforce).
        if ($emitReport || $emitEnforce) {
            $this->stampScriptNonce($response, $nonce);
        }

        // 2) Cabeceras de higiene — SIEMPRE (no fuerzan nada, sólo endurecen).
        $headers = [
            'X-Content-Type-Options'            => 'nosniff',
            'X-Frame-Options'                   => 'SAMEORIGIN',
            'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        // 3) CSP en modo REPORTE (no bloquea) — política COMPLETA (script + style + font + img…):
        //    sigue MIDIENDO qué se rompería en las directivas que aún no se bloquean.
        if ($emitReport) {
            $headers['Content-Security-Policy-Report-Only'] = $this->cspPolicy($nonce);
        }

        // 3b) CSP en modo BLOQUEO (enforce) — MÍNIMA: SÓLO script-src 'self' 'nonce-…' (sin
        //     unsafe-inline). Bloquea scripts no confiables SIN tocar style/font/img (que siguen en la
        //     Report-Only de arriba). Las dos cabeceras conviven: ésta bloquea lo suyo, la otra mide.
        if ($emitEnforce) {
            $headers['Content-Security-Policy'] = $this->cspEnforcePolicy($nonce);
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
     * Estampa nonce="$nonce" en los <script> EN LÍNEA (sin `src` y sin `nonce`) del HTML de salida,
     * usando la misma fuente única ($nonce por petición). Sólo respuestas text/html normales (no
     * binarias ni en streaming). Idempotente: respeta los `src=`/`nonce=` ya presentes.
     *
     * SEGURIDAD ANTE EL FOOTGUN de preg_replace: si el motor fallara devolvería null → NO se toca el
     * cuerpo (se conserva la respuesta original). Nunca escribe en disco: sólo transforma la salida.
     */
    private function stampScriptNonce($response, string $nonce): void
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
        if (! is_string($html) || $html === '' || stripos($html, '<script') === false) {
            return;
        }

        // <script …> ejecutable, sin src y sin nonce → inserta el nonce justo después de "<script".
        // Los <script type="application/json"> (bloques de datos) también reciben el nonce: es inofensivo.
        $new = preg_replace_callback(
            '/<script(?=[\s>])(?![^>]*\bsrc=)(?![^>]*\bnonce=)([^>]*)>/i',
            static fn ($m) => '<script nonce="' . $nonce . '"' . $m[1] . '>',
            $html
        );

        if (is_string($new) && $new !== '') {
            $response->setContent($new);
            $response->headers->remove('Content-Length'); // el largo cambió; que se recalcule al enviar.
        }
    }

    /**
     * Política CSP de BLOQUEO (enforce): `script-src 'self' 'nonce-…'` (sin unsafe-inline) MÁS las
     * dos directivas que hacen efectivo al nonce:
     *   · `object-src 'none'` — sin <object>/<embed>: cierra la ejecución vía plugins.
     *   · `base-uri 'self'`  — sin <base> hacia otro origen: un <base> inyectado cambiaría a dónde
     *     resuelven las rutas relativas y los propios scripts de la app cargarían desde otro origen
     *     CON el nonce intacto. Es el mismo agujero que tapa el nonce, cerrado del todo.
     * NO incluye style/font/img a propósito: ésos siguen midiéndose en la Report-Only.
     */
    private function cspEnforcePolicy(string $nonce): string
    {
        return implode('; ', [
            "script-src 'self' 'nonce-{$nonce}'",
            "object-src 'none'",
            "base-uri 'self'",
        ]);
    }

    /**
     * Política CSP OBJETIVO en modo reporte: revela inline-scripts/estilos y orígenes externos.
     * Lleva el nonce por-petición (misma fuente que `$cspNonce` de las vistas). En modo REPORTE no
     * bloquea: los scripts que ya tienen el nonce dejan de reportarse; el resto se sigue midiendo.
     */
    private function cspPolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "img-src 'self' data: blob:",
            "font-src 'self' https://fonts.gstatic.com data:",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "script-src 'self' 'nonce-{$nonce}'",
            "connect-src 'self'",
            'report-uri /csp-report',
        ]);
    }
}
