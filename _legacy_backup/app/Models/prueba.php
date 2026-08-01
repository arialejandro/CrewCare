<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class prueba extends Model
{
    use HasFactory;
    protected $table = 'prueba';

    protected $primaryKey = 'id_prueba';

    protected $fillable = ['id_prueba',
    'resultado'];

    protected $dates = ['created_at', 'updated_at'];
}