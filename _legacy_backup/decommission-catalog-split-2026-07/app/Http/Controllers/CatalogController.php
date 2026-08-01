<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Position;
use App\Models\usuariosnotificacione;

/**
 * CatalogController — ÚLTIMO corte del God Object AdminController (strangler, paso #2, 2026-06-28).
 *
 * Gestiona los catálogos de Departamentos, Puestos y Notificaciones. Al moverse aquí, el
 * AdminController quedó vacío y se retiró a _legacy_backup/.
 *
 * CAMBIO DE FONDO (decisión owner 2026-06-28): el CRUD ahora opera sobre las tablas NUEVAS
 * `departments` / `positions` (las que el alta de crew y el futuro call sheet SÍ consumen), en
 * lugar de las legacy `departamentos` / `puestos` que editaba el AdminController y que NADIE leía.
 * Así, lo que se captura aquí es exactamente lo que se usa en el resto del sistema.
 *
 * ENDURECIMIENTO respecto al original:
 *  - Validación explícita en cada create/update (antes: $request->all() = mass-assignment).
 *  - Mutaciones (activar/desactivar) por POST + findOrFail (antes: activar* por GET).
 *  - `sort_order` (NUEVO): orden canónico de departamentos y puestos → fluye a Crew List y al
 *    llamado, reemplazando el viejo hack por-persona (users.labn / "Jerarquía").
 *
 * Notificaciones: siguen en el modelo legacy `usuariosnotificacione` (no tienen equivalente nuevo);
 * se modernizan igual (validación + POST) pero sin sort_order (no requieren orden).
 */
class CatalogController extends Controller
{
    // ───────────────────────────── DEPARTAMENTOS ─────────────────────────────

    public function departamentocrud()
    {
        $departments = Department::orderBy('sort_order')->orderBy('name')->paginate(15);
        return view('admin.departamentocrud', compact('departments'));
    }

    public function creardepartamento(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:120|unique:departments,name',
            'radio_channel' => 'nullable|string|max:80',
            'sort_order'    => 'nullable|integer|min:0',
        ]);
        $data['active'] = 1;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        Department::create($data);
        return redirect('/departamentocrud')->with('success', 'Departamento creado.');
    }

    public function editardepartamento($id)
    {
        $item = Department::findOrFail($id);
        return view('admin.editardepartamento', compact('item'));
    }

    public function savedepartamento(Request $request, $id)
    {
        $item = Department::findOrFail($id);
        $data = $request->validate([
            'name'          => 'required|string|max:120|unique:departments,name,'.$item->id,
            'radio_channel' => 'nullable|string|max:80',
            'sort_order'    => 'nullable|integer|min:0',
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $item->update($data);
        return redirect('/departamentocrud')->with('success', 'Departamento actualizado.');
    }

    public function activardepartamento($id)
    {
        Department::findOrFail($id)->update(['active' => 1]);
        return redirect('/departamentocrud')->with('success', 'Departamento activado.');
    }

    public function desactivardepartamento($id)
    {
        Department::findOrFail($id)->update(['active' => 0]);
        return redirect('/departamentocrud')->with('success', 'Departamento desactivado.');
    }

    // ───────────────────────────── PUESTOS ─────────────────────────────

    public function positionscrud()
    {
        // Solo los puestos del CATÁLOGO GLOBAL (production_id NULL) — los que reusa cualquier
        // producción. Ordenados por sort_order para que el orden fluya al alta de crew y al llamado.
        $positions = Position::with('department')
            ->whereNull('production_id')
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(15);
        $departments = Department::where('active', 1)->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.positionscrud', compact('positions', 'departments'));
    }

    public function crearpositions(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'department_id' => 'required|integer|exists:departments,id',
            'is_hod'        => 'nullable|boolean',
            'sort_order'    => 'nullable|integer|min:0',
        ]);
        Position::create([
            'name'          => $data['name'],
            'department_id' => $data['department_id'],
            'production_id' => null, // catálogo global
            'is_hod'        => $request->boolean('is_hod'),
            'active'        => 1,
            'sort_order'    => $data['sort_order'] ?? 0,
        ]);
        return redirect('/positionscrud')->with('success', 'Puesto creado.');
    }

    public function editpositions($id)
    {
        $item = Position::findOrFail($id);
        $departments = Department::where('active', 1)->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.editarposition', compact('item', 'departments'));
    }

    public function saveposition(Request $request, $id)
    {
        $item = Position::findOrFail($id);
        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'department_id' => 'required|integer|exists:departments,id',
            'is_hod'        => 'nullable|boolean',
            'sort_order'    => 'nullable|integer|min:0',
        ]);
        $item->update([
            'name'          => $data['name'],
            'department_id' => $data['department_id'],
            'is_hod'        => $request->boolean('is_hod'),
            'sort_order'    => $data['sort_order'] ?? 0,
        ]);
        return redirect('/positionscrud')->with('success', 'Puesto actualizado.');
    }

    public function activarposition($id)
    {
        Position::findOrFail($id)->update(['active' => 1]);
        return redirect('/positionscrud')->with('success', 'Puesto activado.');
    }

    public function desactivarposition($id)
    {
        Position::findOrFail($id)->update(['active' => 0]);
        return redirect('/positionscrud')->with('success', 'Puesto desactivado.');
    }

    // ───────────────────────────── NOTIFICACIONES ─────────────────────────────
    // (modelo legacy usuariosnotificacione — sin equivalente nuevo; sin sort_order)

    public function notificacioncrud()
    {
        $notificaciones = usuariosnotificacione::orderBy('id_usernotificacion', 'desc')->paginate(15);
        return view('admin.notificacioncrud', compact('notificaciones'));
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
