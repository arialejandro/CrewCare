<?php

namespace App\Models;

use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EL CONTRATO · PASO C — un DESTINATARIO del sobre (lo que registra el certificado). Es el ACTO
 * DE ACEPTACIÓN de una persona, congelado al crear el sobre.
 *
 * TRES papeles (el puesto define la ruta; el sobre congela la persona):
 *  - preparer   — quien prepara y valida (contabilidad de producción).
 *  - contracted — el contratado (se resuelve solo: la persona del contrato).
 *  - binder     — quien obliga a la empresa (CEO / line producer).
 *
 * FASE 3.3 — FIRMA AUTÓGRAFA (DocuSign): al firmar guarda su firma dibujada en `signature_image` y
 * se SELLA (HasDigitalSignatures). Como la imagen es columna del propio destinatario, el hash del
 * sello la cubre → "verificada e íntegra". Del hash se excluyen los campos de flujo VOLÁTILES
 * (status y las marcas de enviado/reenviado/visto): cambian por administración del sobre, no por el
 * acto de firma. SÍ entran al hash: identidad congelada, signed_at, ip, método y la autógrafa.
 */
class ContractEnvelopeRecipient extends Model
{
    use HasDigitalSignatures;

    protected $table = 'contract_envelope_recipients';

    /**
     * Columnas fuera del hash del sello (ver nota de clase). Las VOLÁTILES de flujo + `anchor_key`
     * (metadato de RUTA congelado al construir, no el acto de firma): así agregar la columna NO
     * convierte en "alterados" los sobres ya sellados antes de la Fase 1c.
     */
    protected $signatureExcludes = ['status', 'sent_at', 'resent_at', 'viewed_at', 'anchor_key'];

    const ROLE_PREPARER   = 'preparer';
    const ROLE_CONTRACTED = 'contracted';
    const ROLE_BINDER     = 'binder';
    // MÓDULO DE FIRMA · firmante genérico de la lista configurable (su `cargo` guarda el puesto real).
    const ROLE_SIGNER     = 'signer';

    const STATUS_PENDING  = 'pending';
    const STATUS_SENT     = 'sent';
    const STATUS_VIEWED   = 'viewed';
    const STATUS_SIGNED   = 'signed';
    const STATUS_DECLINED = 'declined';   // FASE 2 · el firmante se negó (con motivo)

    protected $fillable = [
        'envelope_id', 'role', 'sort_order',
        'name', 'email', 'cargo', 'anchor_key', 'empresa', 'user_id', 'payee_id',
        'status', 'sent_at', 'resent_at', 'viewed_at', 'signed_at', 'ip_address', 'sign_method',
        'signature_image',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'sent_at'    => 'datetime',
        'resent_at'  => 'datetime',
        'viewed_at'  => 'datetime',
        'signed_at'  => 'datetime',
    ];

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(ContractEnvelope::class, 'envelope_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isContracted(): bool { return $this->role === self::ROLE_CONTRACTED; }
    public function isSigned(): bool     { return $this->status === self::STATUS_SIGNED; }
    public function isDeclined(): bool   { return $this->status === self::STATUS_DECLINED; }

    /** Etiqueta legible del papel. */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_PREPARER   => __('Prepara y valida'),
            self::ROLE_CONTRACTED => __('Contratado'),
            self::ROLE_BINDER     => __('Obliga a la empresa'),
            self::ROLE_SIGNER     => __('Firmante'),
        ];
    }

    public function roleLabel(): string
    {
        return self::roleLabels()[$this->role] ?? $this->role;
    }
}
