<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una persona dentro de un {@see FileDelivery} = una unidad de trabajo del cron: se le marca el PDF
 * con su nombre en créditos (`watermark_text`) y se le manda por correo. Los reintentos viven aquí
 * (`attempts`/`error`) → el envío nunca se pierde ni bloquea el request.
 */
class FileDeliveryRecipient extends Model
{
    public const PENDING = 'pending';
    public const SENT    = 'sent';
    public const FAILED  = 'failed';

    /** Reintentos antes de rendirse (marca `failed`). */
    public const MAX_ATTEMPTS = 3;

    protected $fillable = [
        'file_delivery_id', 'user_id', 'name', 'email',
        'watermark_text', 'status', 'attempts', 'error', 'sent_at', 'last_attempt_at',
    ];

    protected $casts = [
        'attempts'        => 'integer',
        'sent_at'         => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(FileDelivery::class, 'file_delivery_id');
    }
}
