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

        // 1) PRODUCCIÓN: fuerza https (redirección http→https). No toca el local.
        //    Con TrustProxies confiando el proxy TLS, isSecure() refleja X-Forwarded-Proto,
        //    así que detrás del reverse-proxy no hay bucle. El healthcheck se exime.
        if ($prod && ! $request->isSecure() && ! $request->is('healthz', 'up')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        // 2) Cabeceras de higiene — SIEMPRE (no fuerzan nada, sólo endurecen).
        $headers = [
            'X-Content-Type-Options'            => 'nosniff',
            'X-Frame-Options'                   => 'SAMEORIGIN',
            'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        // 3) CSP en modo REPORTE (no bloquea). script-src 'self' hace que el navegador REPORTE
        //    cada script en línea → así se mide la deuda de inline antes de activar el bloqueo.
        if (config('crewcare.security.csp_report', true)) {
            $headers['Content-Security-Policy-Report-Only'] = $this->cspPolicy();
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

    /** Política CSP OBJETIVO en modo reporte: revela inline-scripts/estilos y orígenes externos. */
    private function cspPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "img-src 'self' data: blob:",
            "font-src 'self' https://fonts.gstatic.com data:",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "script-src 'self'",
            "connect-src 'self'",
            'report-uri /csp-report',
        ]);
    }
}
