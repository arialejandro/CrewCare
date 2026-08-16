<?php

namespace App\Support;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeEvent;
use App\Models\ContractEnvelopeRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * BITÁCORA DE EVENTOS del sobre — el registro de verdad. `record()` agrega un evento inmutable a la
 * CADENA (cada hash incluye el del anterior); `verifyChain()` prueba que nadie la alteró.
 *
 * DEFENSIVO: si la tabla aún no existe (antes de aplicar el owner-apply) o algo falla, NO rompe el
 * flujo — la firma es lo importante; la bitácora nunca debe tumbar una operación. Reloj del servidor,
 * nunca del cliente. Misma llave del sello (HMAC-SHA256).
 */
class ContractEventLog
{
    /** Etiquetas humanas por evento (para el timeline; sin jerga). */
    public static function labels(): array
    {
        return [
            ContractEnvelopeEvent::CREATED    => __('Sobre creado'),
            ContractEnvelopeEvent::SENT       => __('Enviado a firma'),
            ContractEnvelopeEvent::VIEWED     => __('Documento visto'),
            ContractEnvelopeEvent::CONSENTED  => __('Consentimiento aceptado'),
            ContractEnvelopeEvent::SIGNED     => __('Firmado'),
            ContractEnvelopeEvent::COMPLETED  => __('Completado'),
            ContractEnvelopeEvent::CANCELLED  => __('Anulado'),
            ContractEnvelopeEvent::DECLINED   => __('Rechazado'),
            ContractEnvelopeEvent::RESENT     => __('Reenviado'),
            ContractEnvelopeEvent::EXPIRED    => __('Vencido'),
            ContractEnvelopeEvent::CORRECTED  => __('Corregido'),
            ContractEnvelopeEvent::DOWNLOADED => __('Descargado'),
        ];
    }

    public static function label(string $event): string
    {
        return self::labels()[$event] ?? $event;
    }

    /**
     * Agrega un evento a la cadena del sobre. `$opts`: recipient, actor_id, actor_label, ip,
     * user_agent, payload. Devuelve el evento o null (no-op defensivo).
     */
    public static function record(ContractEnvelope $envelope, string $event, array $opts = []): ?ContractEnvelopeEvent
    {
        if (! Schema::hasTable('contract_envelope_events')) {
            return null;   // antes del owner-apply: no-op, no rompe nada
        }

        try {
            $recipient   = $opts['recipient'] ?? null;
            $recipientId = $recipient instanceof ContractEnvelopeRecipient ? $recipient->id : ($recipient ?: null);

            $actorId = array_key_exists('actor_id', $opts) ? $opts['actor_id'] : optional(auth()->user())->id;
            $actorLabel = $opts['actor_label'] ?? null;
            if ($actorLabel === null && $actorId && ($u = User::find($actorId))) {
                $actorLabel = trim($u->name . ' ' . ($u->lname ?? '')) ?: $u->email;
            }

            $req = request();
            $ip  = array_key_exists('ip', $opts) ? $opts['ip'] : ($req ? $req->ip() : null);
            $ua  = array_key_exists('user_agent', $opts) ? $opts['user_agent'] : ($req ? substr((string) $req->userAgent(), 0, 500) : null);

            $occurredAt = now();                 // reloj del SERVIDOR
            $tz         = config('app.timezone');
            $payload    = $opts['payload'] ?? null;

            $prev     = ContractEnvelopeEvent::where('envelope_id', $envelope->id)->orderByDesc('id')->first();
            $prevHash = $prev ? $prev->hash : null;

            $hash = self::chainHash($envelope->id, $recipientId, $event, $actorId, $occurredAt->toDateTimeString(), $ip, $ua, $payload, $prevHash);

            return ContractEnvelopeEvent::create([
                'envelope_id'      => $envelope->id,
                'recipient_id'     => $recipientId,
                'event'            => $event,
                'actor_id'         => $actorId,
                'actor_label'      => $actorLabel,
                'occurred_at'      => $occurredAt,
                'display_timezone' => $tz,
                'ip_address'       => $ip,
                'user_agent'       => $ua,
                'payload'          => $payload,
                'prev_hash'        => $prevHash,
                'hash'             => $hash,
                'created_at'       => $occurredAt,
            ]);
        } catch (\Throwable $e) {
            Log::error('ContractEventLog: no se registró el evento "' . $event . '" — ' . $e->getMessage());
            return null;
        }
    }

    /** Todos los eventos del sobre, en orden cronológico (por id). */
    public static function forEnvelope(ContractEnvelope $envelope)
    {
        return ContractEnvelopeEvent::where('envelope_id', $envelope->id)->orderBy('id')->get();
    }

    /**
     * Verifica la CADENA completa: cada `prev_hash` enlaza con el anterior y cada `hash` se recomputa
     * idéntico. Devuelve ['ok'=>bool, 'count'|'brokenAt'|'reason'].
     */
    public static function verifyChain(ContractEnvelope $envelope): array
    {
        $events   = ContractEnvelopeEvent::where('envelope_id', $envelope->id)->orderBy('id')->get();
        $prevHash = null;

        foreach ($events as $e) {
            if ((string) $e->prev_hash !== (string) $prevHash) {
                return ['ok' => false, 'brokenAt' => $e->id, 'reason' => __('El enlace con el evento anterior no coincide.')];
            }
            $expected = self::chainHash(
                $e->envelope_id, $e->recipient_id, $e->event, $e->actor_id,
                optional($e->occurred_at)->toDateTimeString(), $e->ip_address, $e->user_agent, $e->payload, $e->prev_hash
            );
            if (! hash_equals($expected, (string) $e->hash)) {
                return ['ok' => false, 'brokenAt' => $e->id, 'reason' => __('El sello del evento no coincide (fue alterado).')];
            }
            $prevHash = $e->hash;
        }

        return ['ok' => true, 'count' => $events->count()];
    }

    /** HMAC-SHA256 (llave del sello) del contenido canónico + el hash del evento anterior. */
    private static function chainHash($envelopeId, $recipientId, string $event, $actorId, ?string $occurredAt, ?string $ip, ?string $ua, $payload, ?string $prevHash): string
    {
        $key   = (string) (config('crewcare.seal.key') ?: config('app.key'));
        $canon = [
            'envelope_id'  => (int) $envelopeId,
            'recipient_id' => $recipientId !== null ? (int) $recipientId : null,
            'event'        => $event,
            'actor_id'     => $actorId !== null ? (int) $actorId : null,
            'occurred_at'  => (string) $occurredAt,
            'ip'           => $ip,
            'user_agent'   => $ua,
            'payload'      => $payload,
            'prev'         => $prevHash,
        ];
        self::ksortRecursive($canon);   // estable ante la normalización de claves JSON de MySQL

        return hash_hmac('sha256', json_encode($canon, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $key);
    }

    private static function ksortRecursive(&$arr): void
    {
        if (! is_array($arr)) {
            return;
        }
        ksort($arr);
        foreach ($arr as &$v) {
            if (is_array($v)) {
                self::ksortRecursive($v);
            }
        }
    }
}
