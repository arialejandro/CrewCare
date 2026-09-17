<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TransportVehicleAssignment — asignación FIJA de vehículo (Fase 2 §5).
 * El vehículo se congela a un PUESTO (position_id; en series el pasajero cambia pero el puesto no) o,
 * como EXCEPCIÓN, a una PERSONA (user_id). El DRIVER NO vive aquí: es atributo del vehículo.
 * La orden del día la PRECARGA (eso es Fase 3). Config por producción.
 */
class TransportVehicleAssignment extends Model
{
    protected $table = 'transport_vehicle_assignments';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Etiqueta del sujeto asignado (puesto o persona). */
    public function subjectLabel(): string
    {
        if ($this->user_id) {
            return optional($this->user)->name ? User::displayName($this->user) : ('#' . $this->user_id);
        }
        return optional($this->position)->name ?? ('Puesto #' . $this->position_id);
    }
}
