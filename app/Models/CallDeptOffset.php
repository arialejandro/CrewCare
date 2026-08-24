<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PARTE D · N2 — offset por DEPARTAMENTO. SINGLETON por producción (una fila por depto que persiste
 * día con día hasta que alguien lo cambie). `offset_minutes` respecto al general (negativo = precall);
 * o `literal_value` (O/C, D/C, "Per GC"…) que NO se recalcula.
 */
class CallDeptOffset extends Model
{
    protected $table = 'call_dept_offsets';

    protected $fillable = ['production_id', 'department_id', 'offset_minutes', 'literal_value'];

    protected $casts = ['offset_minutes' => 'integer'];
}
