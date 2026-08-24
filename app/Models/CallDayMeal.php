<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PARTE D · un SERVICIO DE COMIDA del día: offset del general (o hora fija) + toggle + lugar +
 * contingente propio (hereda el del día si no se sobrescribe). El "#" del back cuenta el crew de las
 * marcas de comida; cast/BG salen de aquí. NO se registra lo servido.
 */
class CallDayMeal extends Model
{
    protected $table = 'call_day_meals';

    protected $fillable = [
        'call_day_id', 'sort_order', 'label', 'enabled',
        'offset_minutes', 'explicit_time', 'place_id', 'place_text',
        'cast_override', 'bg_override',
    ];

    protected $casts = [
        'enabled'        => 'boolean',
        'sort_order'     => 'integer',
        'offset_minutes' => 'integer',
        'cast_override'  => 'integer',
        'bg_override'    => 'integer',
    ];

    public function callDay()
    {
        return $this->belongsTo(CallDay::class, 'call_day_id');
    }
}
