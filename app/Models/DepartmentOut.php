<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DepartmentOut — la salida (out) de un DEPARTAMENTO en un día de rodaje.
 *
 * `shoot_date` = el día de RODAJE al que pertenece (resuelto por App\Support\OutWindow, NO la fecha
 * de pared). `out_at` = la hora real de salida (puede ser de madrugada del día siguiente).
 * NO se sella. Ver la migración 2026_09_12_000001 y [[transport/…]] — nada que ver con la columna
 * `out` del back ni con PayeeContract::ROSTER_OUT.
 */
class DepartmentOut extends Model
{
    protected $table = 'department_outs';

    const SOURCE_APP      = 'app';
    const SOURCE_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'production_id', 'unit_id', 'shoot_date', 'department_id',
        'out_at', 'source', 'note', 'registered_by_id',
    ];

    protected $casts = [
        'shoot_date' => 'date',
        'out_at'     => 'datetime',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
