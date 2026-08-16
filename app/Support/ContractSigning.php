<?php

namespace App\Support;

use App\Events\ContractEnvelopeCompleted;
use App\Exceptions\ContractEnvelopeException;
use App\Models\ContractConsent;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeEvent;
use App\Models\ContractEnvelopeRecipient;
use App\Models\PayeeContract;
use App\Models\User;
use App\Support\ContractEventLog;
use App\Support\SignaturePositions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EL CONTRATO · PASO C — FIRMAR. La ruta es SECUENCIAL Y AUTOMÁTICA: al firmar uno, el sobre pasa
 * al siguiente; al firmar el último, se COMPLETA y avisa. Registra por destinatario las cuatro
 * marcas de tiempo (enviado/reenviado/visto/firmado), IP y método. El consentimiento va APARTE.
 *
 * Segundo factor del CONTRATADO (los internos firman con sesión, sin factor):
 *  - crew_work  → fecha de nacimiento (users.borndate), como el intake.
 *  - NO-crew (renta/servicio: ambulancia, proveedores, casas de renta, seguridad) → el RFC como
 *    "contraseña" (el payee siempre trae RFC).
 */
class ContractSigning
{
    /**
     * B4 · ¿Es el turno ABIERTO de este firmante? Unifica secuencial y paralelo:
     *  - SECUENCIAL (default): solo el destinatario apuntado por `current_recipient_id`.
     *  - PARALELO: cualquier FIRMANTE ya notificado (status enviado/visto) que aún no firma.
     * Copias y firmas ya hechas nunca están "abiertas". Equivale al check clásico en secuencial.
     */
    public static function isOpenTurn(ContractEnvelope $envelope, ContractEnvelopeRecipient $r): bool
    {
        if (! $r->isSigner() || $r->isSigned() || (int) $r->envelope_id !== (int) $envelope->id) {
            return false;
        }
        if (SignaturePositions::signParallel()) {
            return in_array($r->status, [
                ContractEnvelopeRecipient::STATUS_SENT, ContractEnvelopeRecipient::STATUS_VIEWED,
            ], true);
        }
        return (int) $envelope->current_recipient_id === (int) $r->id;
    }

    /** Enviar el sobre: draft → sent. Secuencial abre al PRIMERO; paralelo abre a TODOS los firmantes. */
    public static function send(ContractEnvelope $envelope): ContractEnvelope
    {
        if (! $envelope->isDraft()) {
            return $envelope;
        }
        $signers = $envelope->orderedRecipients()->get();
        $first   = $signers->first();
        $days    = (int) config('crewcare.contracts.expire_days', 45);
        // Plazo de vigencia: el barrido (contracts:expire-stale) lo usa para vencer sobres olvidados.
        $expiresAt = $days > 0 ? now()->addDays($days) : null;

        if (SignaturePositions::signParallel()) {
            // PARALELO: todos los firmantes reciben a la vez y firman en cualquier orden. El puntero
            // `current_recipient_id` se conserva en el primero (para la bandeja/aviso), pero el turno
            // real lo decide isOpenTurn (status), no el puntero.
            $envelope->update([
                'status'               => ContractEnvelope::STATUS_SENT,
                'sent_at'              => now(),
                'expires_at'           => $expiresAt,
                'current_recipient_id' => optional($first)->id,
            ]);
            foreach ($signers as $s) {
                $s->update(['status' => ContractEnvelopeRecipient::STATUS_SENT, 'sent_at' => now()]);
            }
            ContractEventLog::record($envelope, ContractEnvelopeEvent::SENT, [
                'payload' => ['mode' => 'parallel', 'to' => $signers->count()],
            ]);
            foreach ($signers as $s) {
                self::notifyTurn($s);   // FASE 4 · avisa a TODOS los firmantes abiertos
            }

            return $envelope->fresh();
        }

        // SECUENCIAL (default): solo el primero recibe; los demás esperan su turno.
        $envelope->update([
            'status'               => ContractEnvelope::STATUS_SENT,
            'sent_at'              => now(),
            'expires_at'           => $expiresAt,
            'current_recipient_id' => optional($first)->id,
        ]);
        if ($first) {
            $first->update(['status' => ContractEnvelopeRecipient::STATUS_SENT, 'sent_at' => now()]);
        }

        ContractEventLog::record($envelope, ContractEnvelopeEvent::SENT, [
            'recipient' => $first,
            'payload'   => ['to' => optional($first)->name],
        ]);
        self::notifyTurn($first);   // FASE 4 · avisa al primero que le toca

        return $envelope->fresh();
    }

    /**
     * FASE 4 — AVISO "te toca": despacha el correo al destinatario que entró en turno. En cola bajo el
     * mismo flag que el correo de cierre (`contracts_queue_email`; sin worker corre inline). Defensivo:
     * el aviso NUNCA rompe la firma. El propio Job revalida que siga siendo su turno.
     */
    private static function notifyTurn(?ContractEnvelopeRecipient $r): void
    {
        if (! $r) {
            return;
        }
        try {
            if (\App\Support\Features::enabled('contracts_queue_email')) {
                \App\Jobs\NotifyRecipientTurn::dispatch($r->id);
            } else {
                \App\Jobs\NotifyRecipientTurn::dispatchSync($r->id);
            }
        } catch (\Throwable $e) {
            // el aviso nunca tumba la firma
        }
    }

    /** Marca VISTO (antes de firmar, la persona puede ver los documentos). */
    public static function markViewed(ContractEnvelopeRecipient $r): void
    {
        if ($r->viewed_at === null) {
            $r->update([
                'viewed_at' => now(),
                'status'    => $r->isSigned() ? $r->status : ContractEnvelopeRecipient::STATUS_VIEWED,
            ]);
            // "Visto" ≠ "firmado": el evento que prueba que la persona TUVO el documento a la vista.
            ContractEventLog::record($r->envelope, ContractEnvelopeEvent::VIEWED, [
                'recipient' => $r, 'actor_id' => $r->user_id, 'actor_label' => $r->name,
            ]);
        }
    }

    /** Consentimiento electrónico APARTE, una vez por persona; vale para sobres posteriores. */
    public static function recordConsent(ContractEnvelopeRecipient $r, ?string $ip): void
    {
        [$type, $id] = self::consenterKey($r);
        if ($id === null || ContractConsent::has($type, $id)) {
            return;   // ya consintió (o no hay a quién atarlo)
        }
        ContractConsent::create([
            'consenter_type' => $type,
            'consenter_id'   => $id,
            'name'           => $r->name,
            'email'          => $r->email,
            'identifier'     => (string) Str::uuid(),
            'accepted_at'    => now(),
            'ip_address'     => $ip,
            'production_id'  => optional($r->envelope)->production_id,
        ]);

        ContractEventLog::record($r->envelope, ContractEnvelopeEvent::CONSENTED, [
            'recipient' => $r, 'actor_id' => $r->user_id, 'actor_label' => $r->name, 'ip' => $ip,
        ]);
    }

    /**
     * FIRMAR: registra al destinatario, aplica su FIRMA AUTÓGRAFA, lo SELLA y avanza la ruta (o
     * completa el sobre y avisa).
     *
     * $imageData — data URL PNG de la firma dibujada (DocuSign). Opcional para no romper llamadas
     * viejas; la autógrafa se exige arriba, en el controlador (validación). Al ser columna del
     * destinatario, entra al hash del sello → la firma queda verificable e íntegra.
     */
    public static function sign(ContractEnvelopeRecipient $r, string $method, ?string $ip, ?string $imageData = null): ContractEnvelope
    {
        // FASE 5 — SECCIÓN CRÍTICA bajo LOCK del sobre: serializa firmas concurrentes del mismo sobre
        // (doble submit, dos personas a la vez) → nadie firma dos veces ni la ruta avanza dos pasos.
        // Solo el ESTADO + el SELLO van dentro del lock; los efectos PESADOS (render del PDF, correos)
        // se difieren FUERA de la transacción para no retener la fila ni arriesgar la firma ya sellada.
        $outcome = DB::transaction(function () use ($r, $method, $ip, $imageData) {
            $envelope = ContractEnvelope::whereKey($r->envelope_id)->lockForUpdate()->first();
            if (! $envelope) {
                throw new ContractEnvelopeException('El sobre ya no existe.');
            }
            $r = $r->fresh();   // estado FRESCO dentro del lock (por si otra petición ya avanzó)

            if ($envelope->isStopped()) {
                throw new ContractEnvelopeException('El sobre ya no admite firmas.');
            }
            if (! self::isOpenTurn($envelope, $r)) {
                // No es su turno (secuencial), ya firmó (doble submit), o no es un firmante abierto.
                throw new ContractEnvelopeException('No es el turno de este firmante en la ruta.');
            }

            $r->update([
                'status'          => ContractEnvelopeRecipient::STATUS_SIGNED,
                'signed_at'       => now(),
                'viewed_at'       => $r->viewed_at ?: now(),
                'ip_address'      => $ip,
                'sign_method'     => $method,
                'signature_image' => $imageData ?: $r->signature_image,
            ]);

            // Sella el acto de aceptación: el hash cubre la identidad congelada + signed_at + ip +
            // método + la autógrafa. Atribuido al usuario congelado del destinatario si lo hay (los
            // internos firman logueados; el contratado no-crew no tiene user → sello sin persona).
            $r->signDocument($r->user);

            ContractEventLog::record($envelope, ContractEnvelopeEvent::SIGNED, [
                'recipient' => $r, 'actor_id' => $r->user_id, 'actor_label' => $r->name, 'ip' => $ip,
                'payload'   => ['method' => $method, 'cargo' => $r->cargo, 'role' => $r->role],
            ]);

            // ── AVANCE. PARALELO: completa cuando NO queda ningún firmante sin firmar; si aún quedan,
            //    el sobre sigue abierto (todos ya fueron avisados al enviar). SECUENCIAL: pasa al
            //    siguiente en orden, o completa si era el último. ──
            if (SignaturePositions::signParallel()) {
                $stillOpen = $envelope->orderedRecipients()
                    ->whereNotIn('status', [ContractEnvelopeRecipient::STATUS_SIGNED, ContractEnvelopeRecipient::STATUS_DECLINED])
                    ->orderBy('sort_order')->orderBy('id')->first();
                if ($stillOpen) {
                    $envelope->update(['current_recipient_id' => $stillOpen->id]);   // puntero a un abierto
                    return ['envelope' => $envelope->fresh(), 'defer' => null];
                }
            } else {
                $next = $envelope->orderedRecipients()->where('sort_order', '>', $r->sort_order)->first();
                if ($next) {
                    $envelope->update(['current_recipient_id' => $next->id]);
                    $next->update(['status' => ContractEnvelopeRecipient::STATUS_SENT, 'sent_at' => now()]);
                    return ['envelope' => $envelope->fresh(), 'defer' => ['notify_id' => (int) $next->id]];
                }
            }

            $envelope->update([
                'status'               => ContractEnvelope::STATUS_COMPLETED,
                'completed_at'         => now(),
                'current_recipient_id' => null,
            ]);
            ContractEventLog::record($envelope, ContractEnvelopeEvent::COMPLETED, ['ip' => $ip]);

            return ['envelope' => $envelope->fresh(), 'defer' => ['completed' => true]];
        });

        // ── EFECTOS SECUNDARIOS, ya COMMITEADO y FUERA del lock (no bloquean otras firmas; si fallan,
        //    la firma ya quedó firme). ──
        $envelope = $outcome['envelope'];
        $defer    = $outcome['defer'];

        if (is_array($defer) && ! empty($defer['notify_id'])) {
            self::notifyTurn(ContractEnvelopeRecipient::find($defer['notify_id']));   // FASE 4 · avisa al siguiente
        }

        if (is_array($defer) && ! empty($defer['completed'])) {
            // FASE 3 — congela el CONTRATO FIRMADO (PDF con autógrafas). En cola bajo flag (sin worker
            // corre inline). El render NUNCA rompe la firma; va ANTES del aviso para que el correo lo adjunte.
            try {
                if (\App\Support\Features::enabled('contracts_queue_render')) {
                    \App\Jobs\RenderSignedContract::dispatch($envelope->id);
                } else {
                    \App\Jobs\RenderSignedContract::dispatchSync($envelope->id);
                }
            } catch (\Throwable $e) {
                // el render nunca tumba el cierre
            }

            try {
                event(new ContractEnvelopeCompleted($envelope->fresh()));
            } catch (\Throwable $e) {
                // el aviso nunca rompe la firma
            }
        }

        return $envelope;
    }

    /**
     * RECHAZAR (Fase 2) — el firmante en turno se NIEGA a firmar, con MOTIVO obligatorio. Detiene el
     * sobre: nadie más firma, el turno se libera y el estado pasa a 'declined'. NO se sella (rechazar
     * no es un acto de firma), pero SÍ queda como evento terminal en la cadena inviolable.
     *
     * El motivo es obligatorio a nivel de dominio (no solo del formulario): un rechazo sin razón no
     * sirve a producción para corregir y reemitir.
     */
    public static function decline(ContractEnvelopeRecipient $r, ?string $ip, string $reason): ContractEnvelope
    {
        $envelope = $r->envelope;
        $reason   = trim($reason);

        if ($envelope->isStopped()) {
            throw new ContractEnvelopeException('El sobre ya no admite cambios.');
        }
        if (! self::isOpenTurn($envelope, $r)) {
            throw new ContractEnvelopeException('No es el turno de esta persona en la ruta.');
        }
        if ($reason === '') {
            throw new ContractEnvelopeException('El motivo del rechazo es obligatorio.');
        }

        $r->update(['status' => ContractEnvelopeRecipient::STATUS_DECLINED]);
        $envelope->update([
            'status'               => ContractEnvelope::STATUS_DECLINED,
            'declined_at'          => now(),
            'resolution_reason'    => $reason,
            'current_recipient_id' => null,
        ]);

        ContractEventLog::record($envelope, ContractEnvelopeEvent::DECLINED, [
            'recipient' => $r, 'actor_id' => $r->user_id, 'actor_label' => $r->name, 'ip' => $ip,
            'payload'   => ['reason' => $reason, 'cargo' => $r->cargo, 'role' => $r->role],
        ]);

        return $envelope->fresh();
    }

    /**
     * REENVIAR (Fase 2) — vuelve a poner el sobre frente a quien tiene el turno AHORA (recordatorio
     * manual). Sella el `resent_at` del destinatario en curso y lo registra. El aviso real (correo /
     * notificación) es de la Fase 4; aquí queda la marca de tiempo y el evento para la bitácora.
     */
    public static function resend(ContractEnvelope $envelope, ?User $actor = null): ContractEnvelope
    {
        if (! $envelope->isSent()) {
            throw new ContractEnvelopeException('Solo se puede reenviar un sobre en firma.');
        }

        // PARALELO: recuerda a TODOS los firmantes abiertos (enviado/visto) a la vez.
        if (SignaturePositions::signParallel()) {
            $open = $envelope->orderedRecipients()
                ->whereIn('status', [ContractEnvelopeRecipient::STATUS_SENT, ContractEnvelopeRecipient::STATUS_VIEWED])
                ->get();
            if ($open->isEmpty()) {
                throw new ContractEnvelopeException('No hay firmantes pendientes por recordar.');
            }
            foreach ($open as $o) {
                $o->update(['resent_at' => now()]);
                self::notifyTurn($o);
            }
            ContractEventLog::record($envelope, ContractEnvelopeEvent::RESENT, [
                'actor_id' => $actor ? $actor->id : optional(auth()->user())->id,
                'payload'  => ['mode' => 'parallel', 'to' => $open->count()],
            ]);

            return $envelope->fresh();
        }

        // SECUENCIAL: recordatorio al turno actual.
        if (! $envelope->current_recipient_id) {
            throw new ContractEnvelopeException('Solo se puede reenviar un sobre en firma con un turno activo.');
        }
        $current = $envelope->currentRecipient;
        if ($current) {
            $current->update(['resent_at' => now()]);
        }
        ContractEventLog::record($envelope, ContractEnvelopeEvent::RESENT, [
            'recipient'   => $current,
            'actor_id'    => $actor ? $actor->id : optional(auth()->user())->id,
            'payload'     => ['to' => optional($current)->name, 'cargo' => optional($current)->cargo],
        ]);
        self::notifyTurn($current);   // FASE 4 · el recordatorio ahora manda de verdad el aviso

        return $envelope->fresh();
    }

    /**
     * VENCER (Fase 2) — marca 'expired' un sobre en firma que rebasó su plazo. Solo aplica a sobres
     * SENT (un borrador no vence; uno terminal ya cerró). Lo dispara el barrido contracts:expire-stale
     * (manual/programable) — nada vence solo. Libera el turno y deja el evento en la cadena.
     */
    public static function expire(ContractEnvelope $envelope): ContractEnvelope
    {
        if (! $envelope->isSent()) {
            return $envelope;
        }

        $envelope->update([
            'status'               => ContractEnvelope::STATUS_EXPIRED,
            'expired_at'           => now(),
            'current_recipient_id' => null,
        ]);

        ContractEventLog::record($envelope, ContractEnvelopeEvent::EXPIRED, [
            'payload' => ['expires_at' => optional($envelope->expires_at)->toDateTimeString()],
        ]);

        return $envelope->fresh();
    }

    // ── Segundo factor del contratado ────────────────────────────────────────
    /** 'borndate' (crew) | 'rfc' (no-crew). */
    public static function factorType(ContractEnvelope $envelope): string
    {
        return optional($envelope->contract)->isCrewWork()
            ? 'borndate'
            : 'rfc';
    }

    public static function verifyFactor(ContractEnvelopeRecipient $r, string $input): bool
    {
        $envelope = $r->envelope;
        $payee    = optional($envelope->contract)->payee;
        if (! $payee) {
            return false;
        }

        if (self::factorType($envelope) === 'borndate') {
            $u  = $payee->user;
            $bd = ($u && $u->borndate) ? Carbon::parse($u->borndate)->format('Y-m-d') : null;
            return $bd !== null && trim($input) === $bd;
        }

        // RFC como "contraseña": normaliza (mayúsculas, sin espacios/guiones).
        $norm = fn ($s) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $s));
        $rfc  = $norm($payee->rfc);
        return $rfc !== '' && $norm($input) === $rfc;
    }

    /** (contract_consents) llave de la persona detrás de un destinatario. */
    private static function consenterKey(ContractEnvelopeRecipient $r): array
    {
        if ($r->user_id) {
            return [ContractConsent::TYPE_USER, (int) $r->user_id];
        }
        if ($r->payee_id) {
            return [ContractConsent::TYPE_PAYEE, (int) $r->payee_id];
        }
        return [ContractConsent::TYPE_USER, null];
    }
}
