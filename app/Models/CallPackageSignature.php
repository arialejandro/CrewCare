<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Firma de una figura sobre el front del paquete (rúbrica dibujada). Se coloca por coordenadas del
 * `sign_field_map` del paquete; `signed_at` NULL = aún pendiente. Reusa la rúbrica tipo contrato.
 */
class CallPackageSignature extends Model
{
    protected $fillable = [
        'call_package_id', 'signer_key', 'user_id', 'role_label', 'rubrica_image', 'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(CallPackage::class, 'call_package_id');
    }
}
