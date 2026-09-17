<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TransportRunOccupant — ocupante de una corrida (Bloque 2 §2).
 *
 * OCUPANTE = persona + "carga" (`load_note`, descriptor libre) + puntero a departamento.
 * La persona sale de: crew (user_id), padrón cast/agencia/cliente (party_id), o texto libre
 * como escape (sólo name_snapshot). `name_snapshot` congela el nombre mostrado.
 */
class TransportRunOccupant extends Model
{
    protected $table = 'transport_run_occupants';

    protected $guarded = ['id'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public const SOURCE_CREW   = 'crew';
    public const SOURCE_CAST   = 'cast';
    public const SOURCE_AGENCY = 'agency';
    public const SOURCE_CLIENT = 'client';
    public const SOURCE_FREE   = 'free';

    public const SOURCES = [
        self::SOURCE_CREW, self::SOURCE_CAST, self::SOURCE_AGENCY, self::SOURCE_CLIENT, self::SOURCE_FREE,
    ];

    // ── Relaciones ───────────────────────────────────────────────────────────
    public function run(): BelongsTo
    {
        return $this->belongsTo(TransportOrderRun::class, 'transport_order_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(TransportParty::class, 'party_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** Nombre a mostrar: snapshot primero (congelado), luego la fuente viva. */
    public function displayName(): string
    {
        if (trim((string) $this->name_snapshot) !== '') {
            return $this->name_snapshot;
        }
        if ($this->source === self::SOURCE_CREW && $this->user) {
            return User::displayName($this->user);
        }
        if ($this->party) {
            return $this->party->name;
        }

        return '';
    }
}
