<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class departamento extends Model
{
    use HasFactory;
    protected $table = 'departamentos';
    protected $primaryKey = 'id_departamentos';
    protected $fillable = ['departamento','activo'];
    protected $dates = ['created_at', 'updated_at'];
}
