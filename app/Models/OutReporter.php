<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OutReporter — persona DESIGNADA para reportar la salida de un departamento (ver migración
 * 2026_09_13_000001). Autoridad por designación, NO por puesto. Sólo aplica al reporte POR EL
 * DEPARTAMENTO (WhatsApp / pegar mensaje); marcar la propia salida no la requiere.
 */
class OutReporter extends Model
{
    protected $table = 'out_reporters';

    protected $fillable = ['production_id', 'department_id', 'user_id', 'designated_by_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
