<?php
namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

class queuevirtual extends Model
{
    protected $table = 'testqueue';

    protected $fillable = [
    'mainday'];
}
