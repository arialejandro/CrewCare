<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PARTE F · Catálogo de LUGARES — clave corta + nombre ("Hotel Real de la Paz / HRP"). SIN vigencias
 * (se cambia y listo). Sus claves alimentan PICK UP / LUGAR / basecamp / la leyenda del pie del back.
 */
class CallPlace extends Model
{
    protected $table = 'call_places';

    protected $fillable = ['production_id', 'code', 'name', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function scopeForProduction($q, $pid)
    {
        return $q->where('production_id', $pid);
    }
}
