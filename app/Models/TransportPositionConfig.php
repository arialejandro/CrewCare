<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TransportPositionConfig — config POR PRODUCCIÓN de un puesto (Fase 2).
 *   - is_leadership: "puesto de jefatura" → habilita el modo discreto para sus ocupantes.
 *   - always_pickup: "lleva pick up siempre" → define el conjunto del modo LIGERO.
 * Configurable por producción; NADA en código. Default de jefatura = `positions.is_hod` cuando no
 * hay fila de config (ver {@see \App\Support\TransportCrew}).
 */
class TransportPositionConfig extends Model
{
    protected $table = 'transport_position_config';

    protected $guarded = ['id'];

    protected $casts = [
        'is_leadership' => 'boolean',
        'always_pickup' => 'boolean',
        'is_active'     => 'boolean',
    ];
}
