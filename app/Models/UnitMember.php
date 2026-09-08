<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UnitMember — PIVOTE persona↔unidad ADICIONAL (2c). NO se sella (no usa HasDigitalSignatures): es estado de
 * asignación, no documento. Ver la migración create_unit_members y App\Support\UnitMembership.
 *
 * `exclusive` = 1: la persona está SÓLO en esta unidad (fuera de la principal). = 0: COMPARTIDA (aquí y en
 * la principal). Sin fila → la persona vive en la principal.
 */
class UnitMember extends Model
{
    protected $table = 'unit_members';

    protected $fillable = ['unit_id', 'user_id', 'exclusive', 'created_by_id'];

    protected $casts = [
        'exclusive' => 'boolean',
    ];
}
