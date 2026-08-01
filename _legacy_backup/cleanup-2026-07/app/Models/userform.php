<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class userform extends Model
{
    use HasFactory;
    protected $table = 'userform';
    protected $primaryKey = 'id_userform';
    protected $fillable = ['id_userform',
    'id_formulario',
    'id_usuario'];
    protected $dates = ['created_at', 'updated_at'];
}
