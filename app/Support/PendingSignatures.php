<?php

namespace App\Support;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\PayeeContract;

/**
 * EL CONTRATO · PASO C — FIRMAS PENDIENTES (cola de firmas). La ruta de un sobre es secuencial, pero
 * a ESCALA (50-60 contratos) cada FIGURA interna (LP, Contador, Rep. Legal, HOD…) contrafirma muchos.
 * En vez de abrir sobre por sobre, esta clase arma:
 *  - forUser()   → la BANDEJA personal: los contratos cuyo turno es AHORA de un usuario.
 *  - byFigure()  → el TABLERO admin: cuántas firmas penden por figura (quién frena la cola).
 *  - keyData()   → los datos que el firmante SÍ mira antes de firmar (contraprestación, situación
 *                  fiscal, vigencia) — para que "uno a uno" (o el lote) nunca sea a ciegas.
 *
 * Todo se deriva de lo YA existente (recipients.user_id + envelopes.current_recipient_id); sin tabla
 * nueva. `current_recipient_id` apunta SIEMPRE al que le toca (lo mueve ContractSigning::send/sign).
 */
class PendingSignatures
{
    /** @var array<string,int> cache por-request del conteo (el sidebar lo pide 2 veces) */
    private static array $countCache = [];

    /** Base: recipients cuyo TURNO es ahora (el sobre 'sent' y el actual es este recipient). */
    private static function turnQuery(?int $prodId)
    {
        return ContractEnvelopeRecipient::query()
            ->where('status', '!=', ContractEnvelopeRecipient::STATUS_SIGNED)
            ->whereHas('envelope', function ($e) use ($prodId) {
                $e->where('status', ContractEnvelope::STATUS_SENT)
                    ->whereColumn('current_recipient_id', 'contract_envelope_recipients.id');
                if ($prodId) {
                    $e->where('production_id', $prodId);
                }
            });
    }

    /** BANDEJA personal: lo que le toca firmar AHORA a un usuario, con el contrato para los datos. */
    public static function forUser(int $userId, ?int $prodId = null)
    {
        $prodId = $prodId ?? CurrentProduction::id();

        return self::turnQuery($prodId)
            ->where('user_id', $userId)
            ->with(['envelope.contract.payee', 'envelope.contract.fiscalRegime', 'envelope.contract.department'])
            ->get()
            ->sortBy(fn ($r) => optional($r->envelope)->sent_at)
            ->values();
    }

    /** Conteo ligero (badge del sidebar), cacheado por-request. */
    public static function countForUser(int $userId, ?int $prodId = null): int
    {
        $prodId = $prodId ?? CurrentProduction::id();
        $key = $userId . ':' . ($prodId ?? '0');
        if (! array_key_exists($key, self::$countCache)) {
            self::$countCache[$key] = self::turnQuery($prodId)->where('user_id', $userId)->count();
        }
        return self::$countCache[$key];
    }

    /**
     * TABLERO admin: sobres en curso agrupados por FIGURA (el `cargo` congelado del que va ahora).
     * Devuelve una colección agrupada: figura => colección de sobres (con currentRecipient + contrato).
     */
    public static function byFigure(?int $prodId = null)
    {
        $prodId = $prodId ?? CurrentProduction::id();

        return ContractEnvelope::query()
            ->where('status', ContractEnvelope::STATUS_SENT)
            ->when($prodId, fn ($q) => $q->where('production_id', $prodId))
            ->whereNotNull('current_recipient_id')
            ->with(['currentRecipient', 'contract.payee'])
            ->get()
            ->filter(fn ($e) => $e->currentRecipient)
            ->sortBy('sent_at')
            ->groupBy(fn ($e) => $e->currentRecipient->cargo ?: $e->currentRecipient->roleLabel());
    }

    /**
     * Datos CLAVE que el firmante mira antes de firmar. NUNCA a ciegas: contraprestación, situación
     * fiscal (RFC + naturaleza + régimen) y vigencia. Salen del contrato/payee (no de la plantilla).
     */
    public static function keyData(?PayeeContract $c): array
    {
        if (! $c) {
            return [];
        }
        $payee  = $c->payee;
        $regime = $c->fiscalRegime;

        return [
            'concept'    => $c->concept,
            'fee'        => $c->fee_amount,
            'currency'   => $c->fee_currency ?: 'MXN',
            'rfc'        => optional($payee)->rfc,
            'nature'     => optional($payee)->legal_nature,   // 'fisica' | 'moral'
            'regime'     => $regime ? ($regime->name ?? $regime->description ?? $regime->code ?? null) : null,
            'start'      => $c->effective_date,
            'end'        => $c->definitive_end_date,
            'department' => optional($c->department)->name,
        ];
    }

    /** Etiqueta legible del concepto del contrato. */
    public static function conceptLabel(?string $concept): string
    {
        return [
            PayeeContract::CONCEPT_CREW    => __('Miembro de crew'),
            PayeeContract::CONCEPT_RENTAL  => __('Renta de equipo'),
            PayeeContract::CONCEPT_SERVICE => __('Servicio'),
        ][$concept] ?? ($concept ?: '—');
    }

    /** Etiqueta legible de la naturaleza fiscal. */
    public static function natureLabel(?string $nature): ?string
    {
        if (! $nature) {
            return null;
        }
        return $nature === 'moral' ? __('Persona moral') : __('Persona física');
    }
}
