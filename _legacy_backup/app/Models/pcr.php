<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class pcr extends Model
{
    use HasFactory;
    protected $table = 'pcr';

    protected $primaryKey = 'id_pcr';

    protected $fillable = ['id_pcr',

    'id',
    'estado'];

    protected $dates = ['created_at', 'updated_at'];
}
