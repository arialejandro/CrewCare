<?php

namespace App\Models;

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

    protected $table = 'contract_envelopes';

    const STATUS_DRAFT     = 'draft';
    const STATUS_SENT      = 'sent';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /** Estado de la RUTA: fuera del hash (mutable). El `documents` congelado SÍ entra al sello. */
    protected $signatureExcludes = [
        'status', 'current_recipient_id', 'sent_at', 'completed_at', 'cancelled_at',
    ];

    protected $fillable = [
        'payee_contract_id', 'production_id', 'status', 'documents',
        'current_recipient_id', 'sent_at', 'completed_at', 'cancelled_at', 'created_by_id',
    ];

    protected $casts = [
        'documents'    => 'array',
        'sent_at'      => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(PayeeContract::class, 'payee_contract_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ContractEnvelopeRecipient::class, 'envelope_id');
    }

    /** Destinatarios en ORDEN de ruta. */
    public function orderedRecipients()
    {
        return $this->recipients()->orderBy('sort_order')->orderBy('id');
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
}
