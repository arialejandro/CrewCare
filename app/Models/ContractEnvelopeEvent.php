<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EL CONTRATO · PASO C — un EVENTO de la bitácora del sobre. APPEND-ONLY: se crea y jamás se
 * actualiza ni se borra (los guards de abajo lo imponen a nivel de modelo; la cadena de hashes lo
 * impone a nivel de evidencia). El estado del sobre se DERIVA de estos eventos.
 *
 * `occurred_at` es el reloj del SERVIDOR; `display_timezone` registra la zona mostrada.
 * `hash` = HMAC-SHA256 del contenido + `prev_hash` → cadena inviolable sin la llave del sello.
 */
class ContractEnvelopeEvent extends Model
{
    public $timestamps = false;   // solo created_at (lo fija el servicio); nunca updated_at

    protected $table = 'contract_envelope_events';

    // Eventos del ciclo de vida (los de Fase 2+ ya declarados para no re-tocar el modelo).
    const CREATED   = 'created';
    const SENT      = 'sent';
    const VIEWED    = 'viewed';
    const CONSENTED = 'consented';
    const SIGNED    = 'signed';
    const COMPLETED = 'completed';
    const CANCELLED = 'cancelled';
    const DECLINED  = 'declined';   // Fase 2
    const RESENT    = 'resent';     // Fase 2
    const EXPIRED   = 'expired';    // Fase 2
    const CORRECTED = 'corrected';  // Fase 2
    const DOWNLOADED = 'downloaded';

    protected $fillable = [
        'envelope_id', 'recipient_id', 'event', 'actor_id', 'actor_label',
        'occurred_at', 'display_timezone', 'ip_address', 'user_agent', 'payload',
        'prev_hash', 'hash', 'created_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    /**
     * APPEND-ONLY duro: bloquea update y delete a nivel de modelo. La bitácora es evidencia; una fila
     * que se puede editar o borrar no prueba nada. (Los tests con RefreshDatabase truncan la tabla,
     * no llaman a delete() del modelo, así que no chocan con esto.)
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('La bitácora del sobre es append-only: un evento no se edita.');
        });
        static::deleting(function () {
            throw new \RuntimeException('La bitácora del sobre es append-only: un evento no se borra.');
        });
    }

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(ContractEnvelope::class, 'envelope_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(ContractEnvelopeRecipient::class, 'recipient_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
