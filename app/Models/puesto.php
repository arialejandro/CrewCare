<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class puesto extends Model
{
    use HasFactory;
    protected $table = 'puestos';
    protected $primaryKey = 'id_puestos';
    protected $fillable = ['id_departamento',
    'name'];
    protected $dates = ['created_at', 'updated_at'];
}
