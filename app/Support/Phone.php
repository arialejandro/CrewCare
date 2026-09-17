<?php

namespace App\Support;

/**
 * Normaliza teléfonos para enlaces WhatsApp click-to-chat (wa.me).
 *
 * wa.me EXIGE el número en formato internacional COMPLETO (código de país + número,
 * solo dígitos, sin '+', sin ceros de salida). Si se le pasa un número LOCAL de 10
 * dígitos, WhatsApp interpreta los 2 primeros como código de país (55 = Brasil) y abre
 * un chat EQUIVOCADO. Por eso, ante un número local de 10 dígitos, ANTEPONEMOS el código
 * de país configurado.
 *
 * ⚠ La lada país NO está hardcodeada: sale de `config('services.whatsapp.country_code')`
 * (env WHATSAPP_COUNTRY_CODE), con México (52) solo como default. Si la app deja de ser
 * de nivel México, se cambia AHÍ en un solo lugar. La constante DEFAULT_CC es el último
 * recurso si no hay config.
 *
 * No adivina países ajenos: un número que ya trae código (>=11 dígitos, o prefijo
 * internacional '00') se respeta tal cual. Como el envío del recordatorio es MANUAL
 * (un clic por persona; el humano ve el chat antes de mandar), un caso raro extranjero
 * se detecta antes de enviar.
 */
class Phone
{
    /** Código de país de ÚLTIMO recurso si no hay config (México). */
    public const DEFAULT_CC = '52';

    /**
     * Devuelve SOLO dígitos, con código de país, listo para 'https://wa.me/{n}'.
     * Cadena vacía si no hay dígitos usables (el llamador cae a wa.me sin número →
     * WhatsApp abre el selector de contacto). Pasa $defaultCc explícito para forzar una
     * lada país concreta; si es null se toma de config (env WHATSAPP_COUNTRY_CODE).
     */
    public static function whatsapp(?string $raw, ?string $defaultCc = null): string
    {
        $defaultCc = $defaultCc ?: (string) config('services.whatsapp.country_code', self::DEFAULT_CC);
        $digits = preg_replace('/\D+/', '', (string) $raw);

        if ($digits === '') {
            return '';
        }

        // '00' = prefijo internacional de salida → lo que sigue ya trae código de país.
        if (str_starts_with($digits, '00')) {
            return ltrim(substr($digits, 2), '0');
        }

        // Número local de 10 dígitos (México, sin código de país) → anteponerlo.
        if (strlen($digits) === 10) {
            return $defaultCc . $digits;
        }

        // Ya trae código de país (o es un formato que no tocamos): se respeta.
        return $digits;
    }
}
