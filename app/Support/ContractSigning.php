<?php

namespace App\Support;

use App\Events\ContractEnvelopeCompleted;
use App\Exceptions\ContractEnvelopeException;
use App\Models\ContractConsent;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\PayeeContract;
use Illuminate\Support\Carbon;
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
    /** Enviar el sobre: draft → sent, el primer destinatario recibe. */
    public static function send(ContractEnvelope $envelope): ContractEnvelope
    {
        if (! $envelope->isDraft()) {
            return $envelope;
        }
        $first = $envelope->orderedRecipients()->first();
        $envelope->update([
            'status'               => ContractEnvelope::STATUS_SENT,
            'sent_at'              => now(),
            'current_recipient_id' => optional($first)->id,
        ]);
        if ($first) {
            $first->update(['status' => ContractEnvelopeRecipient::STATUS_SENT, 'sent_at' => now()]);
        }
        return $envelope->fresh();
    }

    /** Marca VISTO (antes de firmar, la persona puede ver los documentos). */
    public static function markViewed(ContractEnvelopeRecipient $r): void
    {
        if ($r->viewed_at === null) {
            $r->update([
                'viewed_at' => now(),
                'status'    => $r->isSigned() ? $r->status : ContractEnvelopeRecipient::STATUS_VIEWED,
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
    }

    /**
     * FIRMAR: registra al destinatario y avanza la ruta (o completa el sobre y avisa).
     */
    public static function sign(ContractEnvelopeRecipient $r, string $method, ?string $ip): ContractEnvelope
    {
        $envelope = $r->envelope;

        if ($envelope->isCompleted() || $envelope->isCancelled()) {
            throw new ContractEnvelopeException('El sobre ya no admite firmas.');
        }
        if ((int) $envelope->current_recipient_id !== (int) $r->id) {
            throw new ContractEnvelopeException('No es el turno de este firmante en la ruta.');
        }
        if ($r->isSigned()) {
            return $envelope->fresh();
        }

        $r->update([
            'status'      => ContractEnvelopeRecipient::STATUS_SIGNED,
            'signed_at'   => now(),
            'viewed_at'   => $r->viewed_at ?: now(),
            'ip_address'  => $ip,
            'sign_method' => $method,
        ]);

        // Avanza al siguiente en la ruta; si no hay, COMPLETA y avisa.
        $next = $envelope->orderedRecipients()->where('sort_order', '>', $r->sort_order)->first();
        if ($next) {
            $envelope->update(['current_recipient_id' => $next->id]);
            $next->update(['status' => ContractEnvelopeRecipient::STATUS_SENT, 'sent_at' => now()]);
        } else {
            $envelope->update([
                'status'               => ContractEnvelope::STATUS_COMPLETED,
                'completed_at'         => now(),
                'current_recipient_id' => null,
            ]);
            try {
                event(new ContractEnvelopeCompleted($envelope->fresh()));
            } catch (\Throwable $e) {
                // el aviso nunca rompe la firma
            }
        }

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
