<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        // (2026-08-30) El navegador manda los reportes de la CSP sin token CSRF (no es un form).
        'csp-report',
        // (2026-09-12) Webhook de Salidas·WhatsApp (capa Meta, APAGADA): lo llama Meta, no un form del
        // sitio; su autenticidad la da la FIRMA X-Hub-Signature-256, no el token CSRF. La ruta igual
        // aborta 404 mientras el flag `outs_whatsapp` esté apagado (ver OutWhatsappController).
        'webhooks/outs/whatsapp',
    ];
}
