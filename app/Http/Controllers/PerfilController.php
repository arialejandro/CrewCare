<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
// use DB eliminado (2026-06-26): solo lo usaba pruebachedule (removido).

class PerfilController extends Controller
{
    public function indexb(){
        $users = User::findOrFail(auth()->user()->id);
        return view('profile', compact('users'));
    }

    // --- pruebachedule ELIMINADO (2026-06-26) — era un DUPLICADO GET-sin-auth del reset de
    //     `encuestadiaria` (reseteaba a TODOS los activos; nombre de prueba, ni retornaba).
    //     (2026-07-24) Ya NO hay reset automático: el expediente clínico se llena una vez por
    //     producción. Reapertura manual vía POST /activarencuesta/{id}.
    public function changepassword()
    {
        $users = User::findOrFail(auth()->user()->id);
        return view("changepassword",compact('users'));
 
    }
    public function updatepassword(Request $request)
    {
        // SEGURIDAD: opera SIEMPRE sobre el usuario autenticado (la ruta exige `auth` y ya
        // NO recibe {id}) → sin IDOR ni account-takeover. Antes hacía fill($request->except
        // ('password')) sobre un id arbitrario: permitía editar a cualquiera y (con admin en
        // fillable) escalar. El form solo manda `password`; aquí solo cambiamos el password.
        $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = auth()->user();
        $user->password = Hash::make($request->password);
        $user->save();

        return redirect('/home');
    }
}