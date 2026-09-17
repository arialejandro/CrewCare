<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TransportTravelTime — tiempo de traslado del PAR origen→destino (Fase 1).
 *
 * origen = `transport_pickup_points`; destino = `scouting_reports` (la locación del día, geolocalizada).
 * `CrewGeo.driveEta` (OSRM en el navegador) PROPONE (source='proposed'); transpo CORRIGE y lo
 * corregido PERSISTE (source='corrected'). Unique por par: a la segunda vez que se va a la misma
 * locación desde el mismo punto, ya está. El tráfico lo mete la corrección (OSRM no lo trae en vivo).
 */
class TransportTravelTime extends Model
{
    protected $table = 'transport_travel_times';

    protected $guarded = ['id'];

    protected $casts = [
        'minutes' => 'integer',
    ];

    public const SOURCE_PROPOSED  = 'proposed';
    public const SOURCE_CORRECTED = 'corrected';

    public function point(): BelongsTo
    {
        return $this->belongsTo(TransportPickupPoint::class, 'pickup_point_id');
    }

    public function scouting(): BelongsTo
    {
        return $this->belongsTo(ScoutingReport::class, 'scouting_id');
    }

    public function isCorrected(): bool
    {
        return $this->source === self::SOURCE_CORRECTED;
    }
}
