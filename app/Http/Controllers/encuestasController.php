<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class encuestasController extends Controller
{
        public function viewencuesta()
    {
        if(auth()->user()->encuestadiaria){
            return redirect('/home');
        }else{
            $user = User::findOrFail(auth()->user()->id);

            return view('formulario',compact('user'));
        }

    }

    // --- checkfroms (codigo MUERTO con bug `findOrFail(id)` sin $) ELIMINADO — limpieza de codigo muerto (2026-06-25) ---
}
