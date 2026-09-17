<?php

namespace App\Models;

use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equipo declarado para seguro (PASO 3). Es una DECLARACIÓN con consecuencia económica
 * (lo no declarado no se cubre) → se FIRMA con la capa simple, calcando {@see IssuedPermit}:
 * la identidad del que acepta se CONGELA en columnas (acceptor_*) + accepted_at, y el sello
 * SHA vive en `digital_signatures` vía {@see HasDigitalSignatures}. El estado post-firma
 * (`is_active`) queda HASH-EXCLUIDO: retirar no invalida la firma.
 *
 * ⚠ NO es el equipo RENTADO: ese se paga y va como {@see PayeeContract} (concepto
 * equipment_rental). Pueden ser la misma pieza física, pero son registros distintos.
 */
class PayeeDeclaredEquipment extends Model
{
    use HasDigitalSignatures;

    protected $table = 'payee_declared_equipment';

    /** Estado posterior a la firma: NO entra al hash (retirar no invalida el sello). */
    protected $signatureExcludes = ['is_active'];

    protected $fillable = [
        'payee_id', 'description', 'invoice_holder', 'declared_value',
        'acceptor_user_id', 'acceptor_name', 'acceptor_role', 'accepted_at',
        'is_active',
    ];

    protected $casts = [
        'declared_value' => 'decimal:2',
        'accepted_at'    => 'datetime',
        'is_active'      => 'boolean',
    ];

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class, 'payee_id');
    }

    /** ¿Ya se firmó la declaración (aceptación + sello)? */
    public function isSigned(): bool
    {
        return $this->accepted_at !== null && $this->signatures()->exists();
    }
}
