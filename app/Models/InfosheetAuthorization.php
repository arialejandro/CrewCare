<?php

namespace App\Models;

use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EL INFOSHEET · FASE 3 — una AUTORIZACIÓN del paso 2 (aprobar el trato antes de generar el
 * contrato). Acto de aceptación de un autorizador (Line Producer y/o los puestos que configure el
 * módulo de firma), CONGELADO y SELLADO con su firma autógrafa: `signature_image` es una columna
 * del modelo, así entra al hash del sello (`signDocument`) → el "verificada e íntegra" es real.
 * Distinta de la firma del SOBRE (esa formaliza los documentos).
 */
class InfosheetAuthorization extends Model
{
    use HasDigitalSignatures;

    protected $table = 'infosheet_authorizations';

    protected $fillable = [
        'payee_contract_id', 'production_id', 'position_id', 'role',
        'user_id', 'name', 'signature_image', 'accepted_at', 'ip_address',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    // Sin $signatureExcludes: todo (incluida la autógrafa) entra al hash — es una fila inmutable.

    public function contract(): BelongsTo
    {
        return $this->belongsTo(PayeeContract::class, 'payee_contract_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
