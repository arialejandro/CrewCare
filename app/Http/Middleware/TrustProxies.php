<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * (2026-08-30 · endurecimiento) Confía en el reverse-proxy TLS del VPS para que
     * $request->isSecure() refleje X-Forwarded-Proto → HSTS y la redirección http→https no
     * entran en bucle detrás del proxy. Se usa '*' porque en el VPS la app SOLO es alcanzable a
     * través del proxy (nginx/Cloudflare); si algún día la app quedara accesible directamente,
     * acota TRUSTED_PROXIES a la IP del proxy. En local no llega ningún X-Forwarded-* → sin efecto.
     *
     * @var array|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
