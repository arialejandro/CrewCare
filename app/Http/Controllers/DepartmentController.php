<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;

class DepartmentController extends Controller
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
}
