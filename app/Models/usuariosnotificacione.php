<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class usuariosnotificacione extends Model
{
    use HasFactory;
    protected $table = 'usuariosnotificaciones';
    protected $primaryKey = 'id_usernotificacion';
    protected $fillable = [
    'nombre',
    'correo',
    'activo'];
    protected $dates = ['created_at', 'updated_at'];
}
