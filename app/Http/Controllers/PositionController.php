<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Position;
use App\Models\Department;

class PositionController extends Controller
{
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
}
