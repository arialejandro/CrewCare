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

        // EL INFOSHEET · la tarjeta del perfil muestra la mitad PERSONAL (intake, "qué falta", con
        // enlace a su asistente) y la mitad del TRATO en SOLO LECTURA (el contrato crew_work de la
        // producción vigente, si producción ya lo capturó). No crea contratos: solo consulta.
        $payee = \App\Models\Payee::firstOrCreate(
            ['user_id' => $users->id],
            ['legal_nature' => \App\Models\Payee::NATURE_FISICA, 'name' => trim($users->name.' '.$users->lname), 'is_active' => 1]
        );
        $intakeSteps  = \App\Support\IntakeProgress::steps($payee);
        $intakeUrl    = \App\Http\Controllers\IntakeController::invitationUrl($users);
        $dealContract = $payee->contracts()
            ->where('concept', \App\Models\PayeeContract::CONCEPT_CREW)
            ->where('production_id', \App\Support\CurrentProduction::id())
            ->first();

        return view('profile', compact('users', 'payee', 'intakeSteps', 'intakeUrl', 'dealContract'));
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