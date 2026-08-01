<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\usuariosnotificacione;
use App\Models\User;

class NotificationController extends Controller
{
    // ───────────────────────────── NOTIFICACIONES ─────────────────────────────
    // (modelo legacy usuariosnotificacione — sin equivalente nuevo; sin sort_order)

    public function notificacioncrud()
    {
        $notificaciones = usuariosnotificacione::orderBy('id_usernotificacion', 'desc')->paginate(15);

        // (2026-07-19) Selector desde BD: usuarios activos con correo para elegir un contacto
        // existente sin reteclear su correo (prellena el mismo formulario de alta). No cambia
        // el flujo — sigue creando una fila en usuariosnotificaciones.
        $crewUsers = User::where('activo', 1)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.notificacioncrud', compact('notificaciones', 'crewUsers'));
    }

    public function crearnotificacion(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|max:255',
        ]);
        $data['activo'] = 1;
        usuariosnotificacione::create($data);
        return redirect('/notificacioncrud')->with('success', 'Notificación creada.');
    }

    public function editarnotificacion($id)
    {
        $item = usuariosnotificacione::findOrFail($id);
        return view('admin.editarnotificacion', compact('item'));
    }

    public function savenotificacion(Request $request, $id)
    {
        $item = usuariosnotificacione::findOrFail($id);
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|max:255',
        ]);
        $item->update($data);
        return redirect('/notificacioncrud')->with('success', 'Notificación actualizada.');
    }

    public function activarnotificacion($id)
    {
        usuariosnotificacione::findOrFail($id)->update(['activo' => 1]);
        return redirect('/notificacioncrud')->with('success', 'Notificación activada.');
    }

    public function desactivarnotificacion($id)
    {
        usuariosnotificacione::findOrFail($id)->update(['activo' => 0]);
        return redirect('/notificacioncrud')->with('success', 'Notificación desactivada.');
    }
}
