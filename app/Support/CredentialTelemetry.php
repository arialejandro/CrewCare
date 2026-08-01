<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * CredentialTelemetry — avisa AL DEV, por correo, cuando la verificación de cédula
 * profesional falla por una causa de INFRAESTRUCTURA. Nada más.
 *
 * LA DECISIÓN CENTRAL — negocio vs. técnico:
 *
 *   · NEGOCIO  (REASON_NAME_MISMATCH): la fuente respondió bien y dijo que el nombre no
 *     coincide. Eso NO es una falla del sistema, es un dato de la realidad. La operación ya
 *     tiene su señal —la ausencia del badge de cédula verificada—, que es visible donde
 *     importa y por quien puede actuar. El dev no pinta nada ahí: no hay nada que arreglar
 *     en el código. Se registra con Log::warning (queda rastro) y se retorna false SIN
 *     enviar correo. Mandárselo al dev solo produciría ruido que entrena a ignorar el canal.
 *
 *   · TÉCNICO (REASON_SOURCE_DOWN, REASON_TECHNICAL): la fuente no respondió, o algo
 *     reventó. Aquí SÍ hay algo que arreglar y solo el dev puede hacerlo. Estos son los
 *     ÚNICOS motivos que disparan correo (ver TECHNICAL_REASONS).
 *
 * Un $reason desconocido se trata como NO técnico (no envía): ante la duda, no se le grita
 * al dev; se loguea para que se note el motivo mal escrito.
 *
 * DEFENSIVO por diseño, igual que SafetyAlertRecipients: la telemetría es un efecto
 * colateral y JAMÁS puede romper la operación médico-legal que la disparó. Todo el método
 * va envuelto en \Throwable y en el peor caso devuelve false. Si TELEMETRY_EMAIL no está
 * configurada, degrada en silencio (log + false), no explota.
 *
 * (2026-07-19) Creado para el módulo de verificación de cédula profesional.
 */
class CredentialTelemetry
{
    /** El nombre no coincide con la fuente oficial. NEGOCIO → NO envía correo. */
    const REASON_NAME_MISMATCH = 'nombre_no_coincide';

    /** La fuente de verificación no respondió / está caída. TÉCNICO → SÍ envía. */
    const REASON_SOURCE_DOWN = 'fuente_caida';

    /** Cualquier otro fallo técnico (parseo, excepción, respuesta ilegible). TÉCNICO → SÍ envía. */
    const REASON_TECHNICAL = 'error_tecnico';

    /**
     * Los ÚNICOS motivos que ameritan correo al dev. Todo lo demás —incluido
     * REASON_NAME_MISMATCH y cualquier motivo desconocido— se queda en el log.
     */
    const TECHNICAL_REASONS = [
        self::REASON_SOURCE_DOWN,
        self::REASON_TECHNICAL,
    ];

    /** Etiqueta legible (español) por motivo; es lo que ve el dev en el asunto y en el cuerpo. */
    const REASON_LABELS = [
        self::REASON_NAME_MISMATCH => 'El nombre no coincide con la fuente oficial',
        self::REASON_SOURCE_DOWN   => 'Fuente de verificación caída',
        self::REASON_TECHNICAL     => 'Error técnico',
    ];

    /**
     * HIGIENE DE DATOS. El $context lo arma quien llama y puede arrastrar datos personales de
     * una persona real: su nombre completo y su número de cédula. Tres medidas:
     *   1) SECRETOS — se descarta toda clave cuyo nombre contenga uno de estos fragmentos: un
     *      secreto nunca debe viajar a la bandeja del dev ni quedar en el log del servidor.
     *   2) DATOS PERSONALES — se descarta además toda clave que delate identidad (nombre,
     *      cédula): ver PERSONAL_KEY_FRAGMENTS. El dev diagnostica con el ID interno y el
     *      código de error; si necesita el dato del titular lo busca en la BD por ese ID, con
     *      el acceso que corresponda. La barrera es CENTRAL a propósito: aunque un llamador
     *      futuro olvide omitir el nombre o la cédula, NO salen de la aplicación.
     *   3) Cada valor se recorta a MAX_VALUE_LENGTH: para diagnosticar basta el principio, y
     *      un payload gigante (un HTML de error completo, por ejemplo) solo estorba.
     */
    const FORBIDDEN_KEY_FRAGMENTS = ['password', 'token', 'secret'];

    /**
     * Fragmentos que delatan un DATO PERSONAL del titular. Se tratan igual que un secreto: la
     * clave se descarta antes de que el contexto salga por correo o al log. 'name' cubre
     * `registered_name` y `app_name`; 'nombre'/'cédula' cubren sus equivalentes en español.
     * (2026-07-24 · saneo de la alerta técnica: ni el nombre ni la cédula abandonan la app.)
     */
    const PERSONAL_KEY_FRAGMENTS = ['cedula', 'cédula', 'name', 'nombre'];

    /** Tope de caracteres por valor del contexto. */
    const MAX_VALUE_LENGTH = 200;

    /**
     * Reporta un fallo de verificación. Solo los motivos técnicos generan correo.
     *
     * @param  string  $reason   Una de las constantes REASON_*.
     * @param  array   $context  Datos de diagnóstico (se sanean antes de salir).
     * @return bool              true SOLO si el correo se envió; false si era de negocio,
     *                           si falta TELEMETRY_EMAIL, o si algo falló.
     */
    public static function report(string $reason, array $context = []): bool
    {
        // Blindaje externo: envuelve TODO el método. Nunca propagar — un fallo de la
        // telemetría no puede tumbar la verificación de cédula que la disparó.
        try {
            $safeContext = self::sanitize($context);

            // 1) Clasificación. Solo lo técnico llega al dev.
            if (! in_array($reason, self::TECHNICAL_REASONS, true)) {
                if ($reason === self::REASON_NAME_MISMATCH) {
                    // Alerta de NEGOCIO: la ausencia del badge ya avisó a la operación.
                    Log::warning(
                        'CredentialTelemetry: motivo de NEGOCIO (' . $reason . '); no se envía telemetría al dev. '
                        . self::flatten($safeContext)
                    );
                } else {
                    Log::warning(
                        'CredentialTelemetry: motivo DESCONOCIDO "' . $reason . '"; se trata como NO técnico y no se envía. '
                        . self::flatten($safeContext)
                    );
                }
                return false;
            }

            // 2) Destinatario. Siempre por config() — dentro de app/ no se llama a env().
            $to = trim((string) config('services.telemetry.email'));
            if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                Log::warning(
                    'CredentialTelemetry: falta o es inválida la clave TELEMETRY_EMAIL '
                    . '(config services.telemetry.email); no se envía la telemetría de "' . $reason . '".'
                );
                return false;
            }

            $label   = isset(self::REASON_LABELS[$reason]) ? self::REASON_LABELS[$reason] : $reason;
            $subject = '[CrewCare · telemetría] ' . $label;

            $payload = [
                'reason'       => $reason,
                'reason_label' => $label,
                'context'      => $safeContext,
                'app_name'     => (string) config('app.name'),
                'url'          => (string) config('app.url'),
                'occurred_at'  => now()->format('Y-m-d H:i:s'),
                'subject'      => $subject,
            ];

            // Blindaje interno, solo alrededor del envío. Nunca propagar: si el SMTP está
            // caído, la operación que disparó esto ya terminó bien y debe seguir así.
            try {
                Mail::send('correos.telemetry-alert', $payload, function ($m) use ($to, $subject) {
                    // from() global (config/mail.php) — no se sobreescribe.
                    $m->to($to);
                    $m->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::warning('CredentialTelemetry: fallo enviando la telemetría a ' . $to . ' — ' . $e->getMessage());
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            // Blindaje total: nunca propagar.
            Log::error('CredentialTelemetry: error general — ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpia el contexto antes de que salga por correo: descarta claves sensibles,
     * normaliza cada valor a string y lo recorta. Ver FORBIDDEN_KEY_FRAGMENTS.
     */
    private static function sanitize(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            $key = (string) $key;

            if (self::isForbiddenKey($key)) {
                continue;
            }

            // Un array/objeto no se puede castear a string sin aviso (o sin reventar, si el
            // objeto no tiene __toString): se aplana antes a JSON.
            if (is_array($value) || is_object($value)) {
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
                $value   = $encoded === false ? '[no serializable]' : $encoded;
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $value = '';
            }

            $value = (string) $value;
            // mb_substr para no partir un carácter acentuado a la mitad (los nombres los traen).
            if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
                $value = mb_substr($value, 0, self::MAX_VALUE_LENGTH) . '…';
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * ¿La clave delata un secreto o un dato personal? Comparación en minúsculas y por
     * fragmento (atrapa api_token, client_secret, registered_name, cedula_sep…). Secretos y
     * datos personales pasan por la misma puerta: si la clave delata cualquiera, no viaja.
     */
    private static function isForbiddenKey(string $key): bool
    {
        $lower = mb_strtolower($key);

        $fragments = array_merge(self::FORBIDDEN_KEY_FRAGMENTS, self::PERSONAL_KEY_FRAGMENTS);
        foreach ($fragments as $fragment) {
            if (strpos($lower, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }

    /** Representación de una línea del contexto ya saneado, para el log. */
    private static function flatten(array $context): string
    {
        if (empty($context)) {
            return 'contexto: (vacío)';
        }

        $parts = [];
        foreach ($context as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        return 'contexto: ' . implode(' | ', $parts);
    }
}
