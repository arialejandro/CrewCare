<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BadgePrint — registro de que el GAFETE (credencial) de un usuario ya se imprimió.
 *
 * La PRESENCIA de una fila significa "impreso"; borrarla = "no impreso". Sustituye el uso
 * de la columna sin sentido `users.age`. Auditable: printed_at + printed_by_id.
 *
 * PK = user_id (una fila por usuario). Ver [[panel-ui-overhaul-and-badges]].
 */
class BadgePrint extends Model
{
    protected $table = 'badge_prints';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['user_id', 'printed_at', 'printed_by_id'];

    protected $casts = [
        'printed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by_id');
    }
}
