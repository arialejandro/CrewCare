<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * Correo de TELEMETRÍA TÉCNICA (dev). Recibe los fallos de INFRAESTRUCTURA del módulo
     * médico-legal (fuente de verificación caída, error técnico) — NUNCA las alertas de
     * negocio, que se quedan en la operación. Si la clave está vacía, la telemetría
     * simplemente no envía (degradación silenciosa, se registra en el log).
     */
    'telemetry' => [
        'email' => env('TELEMETRY_EMAIL'),
    ],

    /*
     * WhatsApp click-to-chat (wa.me). 'country_code' es la LADA PAÍS por defecto que se
     * antepone a un número LOCAL de 10 dígitos (ver App\Support\Phone): sin ella WhatsApp
     * leería la lada local como país y abriría un chat equivocado. México (52) por defecto;
     * una instalación de otro país lo cambia AQUÍ (WHATSAPP_COUNTRY_CODE) sin tocar código.
     */
    'whatsapp' => [
        'country_code' => env('WHATSAPP_COUNTRY_CODE', '52'),
    ],

    /*
     * ROBOT DE VERIFICACIÓN DE CÉDULA (sub-paso sep_auto del Paso B).
     *
     * Consulta el Registro Nacional de Profesionistas a través de BÚHOLEGAL (fuente pública
     * SIN captcha; la SEP oficial tiene captcha y NO se toca). Ver App\Support\CedulaVerifier.
     *
     * 'auto' es el INTERRUPTOR MAESTRO: OFF por defecto. Se enciende (CEDULA_AUTO_VERIFY=true)
     * cuando se confirme que la fuente se consulta de forma estable. Apagado = todo el Paso B
     * sigue en modo MANUAL, exactamente como antes de este sub-paso.
     *
     * ⚠️ 'search_path' y 'field' son la MEJOR CONJETURA (Django + /consultasep/). CONFIRMARLOS
     * contra el PoC que funciona antes de encender el flag: si no coinciden, el POST fallará y
     * el robot degradará a manual avisando al dev (no rompe nada, pero no verificará solo).
     */
    'cedula' => [
        'auto'        => env('CEDULA_AUTO_VERIFY', false),
        'base'        => env('CEDULA_SOURCE_URL', 'https://www.buholegal.com'),
        'search_path' => env('CEDULA_SEARCH_PATH', '/consultasep/'),
        'field'       => env('CEDULA_FIELD', 'cedula'),
        'timeout'     => env('CEDULA_TIMEOUT', 12),
        'retries'     => env('CEDULA_RETRIES', 2),
        'user_agent'  => env('CEDULA_USER_AGENT'),
    ],

];
