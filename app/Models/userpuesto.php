<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class userpuesto extends Model
{
    use HasFactory;
    protected $table = 'userpuesto';
    protected $primaryKey = 'id_userpuesto';
    protected $fillable = ['id_puestos',
    'id_puesto',
    'name',
    'id'];
}
