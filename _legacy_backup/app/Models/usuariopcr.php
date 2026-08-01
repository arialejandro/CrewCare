<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;



class usuariopcr extends Model

{

    use HasFactory;

    protected $table = 'usuariopcr';

    protected $primaryKey = 'id_usuariopcr';

    protected $fillable = ['id_usuariopcr',

    'id_prueba',

    'id_usrio'];

    protected $dates = ['created_at', 'updated_at'];

}