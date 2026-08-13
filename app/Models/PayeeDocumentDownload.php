<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BITÁCORA DE DESCARGAS de un documento fiscal del payee (RFC/CLABE/domicilio de terceros).
 * Append-only: se escribe una fila por cada serve gateado. NUNCA es un candado — es rastro.
 * Espeja la telemetría de la cédula ("quién consultó y cuándo").
 */
class PayeeDocumentDownload extends Model
{
    protected $table = 'payee_document_downloads';

    protected $fillable = [
        'payee_id', 'document_id', 'document_label',
        'user_id', 'user_name', 'ip', 'downloaded_at',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class, 'payee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
