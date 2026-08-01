<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class departamentousuario extends Model
{
    use HasFactory;
    protected $table = 'departamentousuario';
    protected $primaryKey = 'id_departamentousuario ';
    protected $fillable = ['id_departamentousuario ','id_departamento','id'];
    protected $dates = ['created_at', 'updated_at'];
}
