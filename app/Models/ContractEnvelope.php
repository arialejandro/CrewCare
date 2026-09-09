<?php

namespace App\Models;

use App\Traits\GeneratesUuidKey;
use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EL CONTRATO · PASO C — EL SOBRE. Un PAQUETE (carátula+clausulado+anexos) que se firma junto,
 * pertenece a UN contrato. Se SELLA con {@see HasDigitalSignatures}: el hash cubre el `documents`
 * (snapshot del paquete) → prueba que el conjunto de documentos no cambió. El ESTADO de la ruta
 * (status, quién va, timestamps) queda FUERA del hash (es mutable; no es el documento).
 *
 * El sello (digital_signatures) es la INTEGRIDAD del documento; los `recipients` son el ACTO DE
 * ACEPTACIÓN de cada persona. Cosas distintas que conviven; no se fusionan.
 */
class ContractEnvelope extends Model
{
    use HasDigitalSignatures;
    use GeneratesUuidKey;   // FASE 3 · uuid público para el verificador (/verificar/cenv/{uuid})

    protected $table = 'contract_envelopes';

    const STATUS_DRAFT     = 'draft';
    const STATUS_SENT      = 'sent';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    // FASE 2 · caminos de escape: el firmante se NIEGA (declined) o el sobre VENCE (expired).
    const STATUS_DECLINED  = 'declined';
    const STATUS_EXPIRED   = 'expired';

    /**
     * Estado de la RUTA: fuera del hash (mutable). El `documents` congelado SÍ entra al sello. Las
     * columnas de Fase 2 (expires_at/declined_at/expired_at/resolution_reason) son metadato de ruta →
     * también se excluyen para que los sobres YA sellados NO se vuelvan "alterados" al agregarlas.
     */
    protected $signatureExcludes = [
        'status', 'current_recipient_id', 'sent_at', 'completed_at', 'cancelled_at',
        'expires_at', 'declined_at', 'expired_at', 'resolution_reason',
        // FASE 3 · el documento firmado es un artefacto DERIVADO (autógrafas + paquete, ambos ya
        // sellados); su integridad va en la bitácora, no en el sello del sobre.
        'signed_document',
        'signed_annexes',
    ];

    protected $fillable = [
        'uuid', 'payee_contract_id', 'production_id', 'status', 'documents', 'signed_document', 'signed_annexes',
        'current_recipient_id', 'sent_at', 'completed_at', 'cancelled_at', 'created_by_id',
        'expires_at', 'declined_at', 'expired_at', 'resolution_reason',
    ];

    protected $casts = [
        'documents'       => 'array',
        'signed_document' => 'array',
        'signed_annexes'  => 'array',
        'sent_at'         => 'datetime',
        'completed_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
        'expires_at'      => 'datetime',
        'declined_at'     => 'datetime',
        'expired_at'      => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(PayeeContract::class, 'payee_contract_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ContractEnvelopeRecipient::class, 'envelope_id');
    }

    /**
     * La RUTA DE FIRMA en orden: SOLO firmantes (excluye las copias de B1). NULL histórico cuenta
     * como firmante. La usan enviar/firmar (turno) y el certificado/stepper — todos quieren firmantes.
     */
    public function orderedRecipients()
    {
        return $this->recipients()
            ->where(function ($q) {
                $q->whereNull('delivery_mode')
                  ->orWhere('delivery_mode', '!=', ContractEnvelopeRecipient::DELIVERY_COPY);
            })
            ->orderBy('sort_order')->orderBy('id');
    }

    /** B1 · destinatarios de COPIA / acuse (solo reciben; fuera de la ruta de firma). */
    public function copyRecipients()
    {
        return $this->recipients()
            ->where('delivery_mode', ContractEnvelopeRecipient::DELIVERY_COPY)
            ->orderBy('sort_order')->orderBy('id');
    }

    public function currentRecipient(): BelongsTo
    {
        return $this->belongsTo(ContractEnvelopeRecipient::class, 'current_recipient_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isDraft(): bool     { return $this->status === self::STATUS_DRAFT; }
    public function isSent(): bool      { return $this->status === self::STATUS_SENT; }
    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
    public function isDeclined(): bool  { return $this->status === self::STATUS_DECLINED; }
    public function isExpired(): bool   { return $this->status === self::STATUS_EXPIRED; }

    /** ¿Ya se congeló el documento firmado (render con autógrafas) en disco? */
    public function hasSignedDocument(): bool
    {
        return ! empty($this->signed_document['path'] ?? null);
    }

    /** Estados TERMINALES: el sobre ya no admite acciones de firma/ruta. */
    public function isStopped(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_DECLINED, self::STATUS_EXPIRED,
        ], true);
    }

    /** ¿La firma se detuvo por un camino de escape (no por completarse)? Muestra el motivo. */
    public function isStoppedShort(): bool
    {
        return in_array($this->status, [
            self::STATUS_CANCELLED, self::STATUS_DECLINED, self::STATUS_EXPIRED,
        ], true);
    }

    /** Folio estable para el verificador público y la cadena CFDI. */
    public function folio(): string
    {
        return 'CENV-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * FASE 3 · vigencia de 3 estados para el verificador público. Los caminos de escape (anulado /
     * rechazado / vencido) RETIRAN el sobre sin alterar su sello: es estado, no manipulación. El
     * verificador lo muestra como "válido, pero {etiqueta}". `superseded_folio` queda null (los
     * contratos aún no se reemiten en cadena). El MOTIVO (texto libre) nunca sale por aquí — el acuse
     * es público; el motivo se ve solo en la página interna del sobre.
     *
     * @return array{retired_at:?string, retired_label:string, superseded_folio:?string}|null
     */
    public function sealRetirement(): ?array
    {
        $when = null; $label = null;
        if ($this->cancelled_at !== null) {
            $when = $this->cancelled_at; $label = __('Anulado');
        } elseif ($this->declined_at !== null) {
            $when = $this->declined_at; $label = __('Rechazado');
        } elseif ($this->expired_at !== null) {
            $when = $this->expired_at; $label = __('Vencido');
        } else {
            return null; // vigente
        }

        return [
            'retired_at'       => $when->format('d/m/Y'),
            'retired_label'    => $label,
            'superseded_folio' => null,
        ];
    }
}
