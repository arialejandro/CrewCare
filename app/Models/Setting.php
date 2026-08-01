<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Setting — almacén simple clave/valor para configuración editable en vivo (branding, etc.).
 * PK es la columna `key` (string), no autoincremental.
 */
class Setting extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];
}
