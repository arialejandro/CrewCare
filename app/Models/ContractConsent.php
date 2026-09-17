<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * EL CONTRATO · PASO C — CONSENTIMIENTO ELECTRÓNICO. Aceptación, UNA VEZ por persona, de firmar
 * electrónicamente. Va APARTE del acto de firma y vale para sobres posteriores (no se re-pide).
 * Llave por persona: (consenter_type, consenter_id).
 */
class ContractConsent extends Model
{
    protected $table = 'contract_consents';

    const TYPE_USER  = 'user';
    const TYPE_PAYEE = 'payee';

    protected $fillable = [
        'consenter_type', 'consenter_id', 'name', 'email',
        'identifier', 'accepted_at', 'ip_address', 'production_id',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    /** ¿Esta persona ya consintió alguna vez? (vale para sobres posteriores). */
    public static function has(string $type, int $id): bool
    {
        return static::where('consenter_type', $type)->where('consenter_id', $id)->exists();
    }
}
