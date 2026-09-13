<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * IndividualOut — la salida de UNA PERSONA que se fue a distinta hora que su departamento.
 *
 * Gana sobre la salida del depto para el turnaround de esa persona (ver App\Support\Turnaround):
 * "no uses el del departamento para alguien que salió distinto sin haberlo reportado". `shoot_date`
 * es el día de rodaje (App\Support\OutWindow); `out_at` la hora real. NO se sella.
 */
class IndividualOut extends Model
{
    protected $table = 'individual_outs';

    const SOURCE_APP      = 'app';
    const SOURCE_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'production_id', 'unit_id', 'shoot_date', 'user_id', 'department_id',
        'out_at', 'source', 'note', 'registered_by_id',
    ];

    protected $casts = [
        'shoot_date' => 'date',
        'out_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
