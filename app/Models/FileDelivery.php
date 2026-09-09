<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un ENVÍO (lote): el PDF base + de quién viene + asunto/cuerpo. Sus destinatarios ({@see
 * FileDeliveryRecipient}) son las unidades de trabajo que el cron drena (marca + correo). Reusable:
 * lo crean tanto el paquete del llamado (source_type=call_package) como el módulo de distribución
 * (source_type=manual). {@see \App\Support\FileDeliveryDispatcher}.
 */
class FileDelivery extends Model
{
    public const SOURCE_CALL_PACKAGE = 'call_package';
    public const SOURCE_MANUAL       = 'manual';

    protected $fillable = [
        'production_id', 'created_by', 'source_type', 'source_id',
        'title', 'body', 'base_path', 'base_name', 'watermark',
    ];

    protected $casts = [
        'watermark' => 'boolean',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(FileDeliveryRecipient::class);
    }

    /** Conteos rápidos por estado (para el tablero de seguimiento). */
    public function counts(): array
    {
        $rows = $this->relationLoaded('recipients') ? $this->recipients : $this->recipients()->get();

        return [
            'total'   => $rows->count(),
            'sent'    => $rows->where('status', FileDeliveryRecipient::SENT)->count(),
            'failed'  => $rows->where('status', FileDeliveryRecipient::FAILED)->count(),
            'pending' => $rows->where('status', FileDeliveryRecipient::PENDING)->count(),
        ];
    }
}
