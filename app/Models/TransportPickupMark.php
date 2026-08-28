<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TransportPickupMark — LA MARCA "lleva pick up hoy" (Fase 3). Singleton por producción+persona,
 * SIN fecha (memoria del único día abierto). La declara el back; la precarga la lee. Sugiere, no obliga.
 */
class TransportPickupMark extends Model
{
    protected $table = 'transport_pickup_marks';

    protected $guarded = ['id'];

    protected $casts = [
        'is_marked' => 'boolean',
    ];
}
