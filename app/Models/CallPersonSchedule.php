<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PARTE D · N3 — horario/pick up/marca de comida por PERSONA. SINGLETON por producción (persiste día
 * con día). El offset de persona GANA sobre el del departamento. `*_literal` para valores que no son
 * hora (horario: O/C, D/C, texto; pick up: N/A, SD, W/N). `meal_mark` = el "#" del back (1 = come).
 */
class CallPersonSchedule extends Model
{
    protected $table = 'call_person_schedules';

    protected $fillable = [
        'production_id', 'unit_id', 'user_id',
        'schedule_offset_minutes', 'schedule_literal',
        'pickup_offset_minutes', 'pickup_literal', 'pickup_place_id', 'pickup_place_text',
        'hotel_code', 'meal_mark',
    ];

    protected $casts = [
        'schedule_offset_minutes' => 'integer',
        'pickup_offset_minutes'   => 'integer',
        'meal_mark'               => 'boolean',
    ];
}
