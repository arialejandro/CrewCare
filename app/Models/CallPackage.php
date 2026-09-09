<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paquete del llamado (1 por producción+día): el FRONT subido (PDF externo del 2nd AD) + el estado de
 * aprobación + el snapshot congelado. El back se genera al vuelo. {@see \App\Support\CallPackageAssembler}.
 */
class CallPackage extends Model
{
    public const DRAFT    = 'draft';      // armándose (aún sin mandar a aprobación)
    public const PENDING  = 'pending';    // enviado a firma, faltan firmas
    public const APPROVED = 'approved';   // firmado por los requeridos → congelado
    public const CHANGED  = 'changed';    // hubo cambios de horario tras aprobar (re-aprobar)

    protected $fillable = [
        'production_id', 'call_date', 'front_path', 'front_pages',
        'sign_field_map', 'signers', 'status', 'approved_at',
        'frozen_path', 'frozen_schedule', 'extra_docs',
    ];

    protected $casts = [
        'call_date'       => 'date',
        'front_pages'     => 'integer',
        'sign_field_map'  => 'array',
        'signers'         => 'array',
        'frozen_schedule' => 'array',
        'extra_docs'      => 'array',
        'approved_at'     => 'datetime',
    ];

    public function signatures(): HasMany
    {
        return $this->hasMany(CallPackageSignature::class);
    }

    /** ¿Ya firmaron TODAS las figuras requeridas? */
    public function allSigned(): bool
    {
        $required = collect($this->signers ?? [])->pluck('key')->filter()->values();
        if ($required->isEmpty()) {
            return false;
        }
        $signed = $this->signatures->whereNotNull('signed_at')->pluck('signer_key');

        return $required->every(fn ($k) => $signed->contains($k));
    }
}
