<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Firma digital / no-repudio (SHA-256 del payload canónico del documento).
 *
 * Pertenece (morphTo) a cualquier reporte de seguridad firmable (Hazard, Unsafe,
 * Injury, Scouting, Daily). Se genera al finalizar/cerrar el documento vía
 * App\Traits\HasDigitalSignatures: guarda el hash del contenido en ese momento
 * (document_hash), quién firmó (user_id + role_at_signing) y metadatos de la
 * petición (ip_address / user_agent) para trazabilidad inmutable.
 *
 * Tabla: digital_signatures (database/owner-apply/2026-07-12-modules-6-14.sql).
 */
class DigitalSignature extends Model
{
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'user_id',
        'role_at_signing',
        'ip_address',
        'user_agent',
        'document_hash',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    /**
     * El documento firmado (Hazard/Unsafe/Injury/Scouting/Daily).
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuario que firmó (nullable: la firma preserva user_id como snapshot).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
