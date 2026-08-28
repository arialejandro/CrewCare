<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * VISTA DE ADMINISTRACIÓN DEL CATÁLOGO ORGANIZACIONAL (fusión semilla+vivo).
 *
 * Para corregir el catálogo SIN tocar la base: listado por departamento (puestos por rango),
 * toggle de is_hod inline (marca OPERATIVA, un depto puede tener VARIAS — sin unicidad), alta de
 * puesto/departamento, edición de nombre es/en + rango + binding + grado, y baja por DESACTIVACIÓN
 * (nunca borrado) mostrando cuántas personas quedan asignadas.
 *
 * Gate: catalogs.view (leer) / catalogs.manage (mutar) — mismo permiso que el CRUD viejo.
 */
class CatalogAdminController extends Controller
{
    public const BINDINGS = ['production', 'block', 'unit'];
    public const GRADES    = ['manager', 'coordinator', 'supervisor', 'key', 'assistant', 'operator'];

    public function index()
    {
        $assignByPos  = DB::table('production_user')->whereNotNull('position_id')
            ->select('position_id', DB::raw('COUNT(*) c'))->groupBy('position_id')->pluck('c', 'position_id');
        $assignByDept = DB::table('production_user')->whereNotNull('department_id')
            ->select('department_id', DB::raw('COUNT(*) c'))->groupBy('department_id')->pluck('c', 'department_id');

        $departments = DB::table('departments')
            ->orderByRaw('active DESC')->orderBy('sort_order')->orderBy('name')->get();

        $positionsByDept = DB::table('positions')
            ->whereNull('production_id')
            ->orderByRaw('active DESC')->orderBy('rank')->orderBy('name')
            ->get()->groupBy('department_id');

        return view('admin.catalogo.index', [
            'departments'     => $departments,
            'positionsByDept' => $positionsByDept,
            'assignByPos'     => $assignByPos,
            'assignByDept'    => $assignByDept,
            'similar'         => $this->similarByPosition($positionsByDept),
            'bindings'        => self::BINDINGS,
            'grades'          => self::GRADES,
        ]);
    }

    // ───────────────────────────── PUESTOS ─────────────────────────────
    public function toggleHod($id)
    {
        $pos = DB::table('positions')->where('id', $id)->first();
        abort_unless($pos, 404);
        DB::table('positions')->where('id', $id)->update([
            'is_hod'     => $pos->is_hod ? 0 : 1,
            'updated_at' => Carbon::now(),
        ]);

        return back()->with('success', 'Marca de jefatura actualizada para “' . $pos->name . '”.');
    }

    public function storePosition(Request $request)
    {
        $data = $this->validatePosition($request);
        $dept = DB::table('departments')->where('id', $data['department_id'])->first();
        abort_unless($dept, 422);

        DB::table('positions')->insert([
            'department_id' => $data['department_id'],
            'production_id' => null,
            'name'          => $data['name'],
            'name_en'       => $data['name_en'] ?: null,
            'rank'          => $data['rank'],
            'binding'       => $data['binding'],
            'grade'         => $data['grade'] ?: null,
            'existence'     => 'core',
            'hod_capable'   => $request->boolean('is_hod') ? 1 : 0,
            'is_hod'        => $request->boolean('is_hod') ? 1 : 0,
            'active'        => 1,
            'sort_order'    => (int) $dept->sort_order * 100 + (int) $data['rank'],
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ]);

        return back()->with('success', 'Puesto “' . $data['name'] . '” creado.');
    }

    public function updatePosition(Request $request, $id)
    {
        $pos = DB::table('positions')->where('id', $id)->first();
        abort_unless($pos, 404);
        $data = $this->validatePosition($request);
        $dept = DB::table('departments')->where('id', $data['department_id'])->first();
        abort_unless($dept, 422);

        DB::table('positions')->where('id', $id)->update([
            'department_id' => $data['department_id'],
            'name'          => $data['name'],
            'name_en'       => $data['name_en'] ?: null,
            'rank'          => $data['rank'],
            'binding'       => $data['binding'],
            'grade'         => $data['grade'] ?: null,
            'sort_order'    => (int) $dept->sort_order * 100 + (int) $data['rank'],
            'updated_at'    => Carbon::now(),
        ]);

        return redirect()->route('catalogo.index')->with('success', 'Puesto “' . $data['name'] . '” actualizado.');
    }

    public function deactivatePosition($id)
    {
        DB::table('positions')->where('id', $id)->update(['active' => 0, 'updated_at' => Carbon::now()]);

        return back()->with('success', 'Puesto desactivado (no se borró: conserva su historial).');
    }

    public function activatePosition($id)
    {
        DB::table('positions')->where('id', $id)->update(['active' => 1, 'updated_at' => Carbon::now()]);

        return back()->with('success', 'Puesto reactivado.');
    }

    // ─────────────────────────── DEPARTAMENTOS ──────────────────────────
    public function storeDepartment(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:120|unique:departments,name',
            'name_en' => 'nullable|string|max:255',
        ]);
        $maxSort = (int) DB::table('departments')->max('sort_order');

        DB::table('departments')->insert([
            'name'        => $data['name'],
            'name_en'     => $data['name_en'] ?: null,
            'existence'   => 'core',
            'active'      => 1,
            'sort_order'  => $maxSort + 10,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);

        return back()->with('success', 'Departamento “' . $data['name'] . '” creado.');
    }

    public function updateDepartment(Request $request, $id)
    {
        $dept = DB::table('departments')->where('id', $id)->first();
        abort_unless($dept, 404);
        $data = $request->validate([
            'name'    => 'required|string|max:120|unique:departments,name,' . $id,
            'name_en' => 'nullable|string|max:255',
        ]);
        DB::table('departments')->where('id', $id)->update([
            'name'       => $data['name'],
            'name_en'    => $data['name_en'] ?: null,
            'updated_at' => Carbon::now(),
        ]);

        return redirect()->route('catalogo.index')->with('success', 'Departamento actualizado.');
    }

    public function deactivateDepartment($id)
    {
        DB::table('departments')->where('id', $id)->update(['active' => 0, 'updated_at' => Carbon::now()]);

        return back()->with('success', 'Departamento desactivado (no se borró).');
    }

    public function activateDepartment($id)
    {
        DB::table('departments')->where('id', $id)->update(['active' => 1, 'updated_at' => Carbon::now()]);

        return back()->with('success', 'Departamento reactivado.');
    }

    // ─────────────────────────── edición (páginas) ──────────────────────
    public function editPosition($id)
    {
        $item = DB::table('positions')->where('id', $id)->first();
        abort_unless($item, 404);
        $departments = DB::table('departments')->where('active', 1)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.catalogo.edit-position', [
            'item' => $item, 'departments' => $departments,
            'bindings' => self::BINDINGS, 'grades' => self::GRADES,
        ]);
    }

    public function editDepartment($id)
    {
        $item = DB::table('departments')->where('id', $id)->first();
        abort_unless($item, 404);

        return view('admin.catalogo.edit-department', ['item' => $item]);
    }

    // ───────────────────────────── utilidades ──────────────────────────
    /**
     * Señala (NO fusiona) puestos con nombre muy parecido DENTRO de un mismo departamento:
     * mismo "esqueleto" de nombre (abreviaturas: "Coordinador de Arte" ≈ "Coord. de Arte") o el
     * mismo `catalog_key` (misma función canónica). Devuelve [position_id => [nombres hermanos]].
     */
    private function similarByPosition($positionsByDept): array
    {
        $similar = [];
        foreach ($positionsByDept as $rows) {
            $groups = [];   // esqueleto de nombre => [filas]
            foreach ($rows as $p) {
                if (! $p->active) {
                    continue;
                }
                $sk = $this->skeleton($p->name);
                if ($sk !== '') {
                    $groups[$sk][] = $p;
                }
            }
            foreach ($groups as $group) {
                if (count($group) < 2) {
                    continue;
                }
                foreach ($group as $p) {
                    foreach ($group as $q) {
                        if ($q->id !== $p->id) {
                            $similar[$p->id][$q->id] = $q->name;   // dedup por id
                        }
                    }
                }
            }
        }
        foreach ($similar as $id => $names) {
            $similar[$id] = array_values(array_unique($names));
        }

        return $similar;
    }

    /** Esqueleto de nombre: sin acentos/puntuación, sin stopwords, 4 primeras letras por palabra, ordenado. */
    private function skeleton(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($name)), -1, PREG_SPLIT_NO_EMPTY);
        $stop = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'en', 'a', 'para'];
        $sig = [];
        foreach ($words as $w) {
            if (! in_array($w, $stop, true)) {
                $sig[] = substr($w, 0, 4);
            }
        }
        sort($sig);

        return implode('|', $sig);
    }

    private function validatePosition(Request $request): array
    {
        return $request->validate([
            'name'          => 'required|string|max:150',
            'name_en'       => 'nullable|string|max:255',
            'department_id' => 'required|integer|exists:departments,id',
            'rank'          => 'required|integer|min:1|max:99',
            'binding'       => 'required|in:' . implode(',', self::BINDINGS),
            'grade'         => 'nullable|in:' . implode(',', self::GRADES),
        ]);
    }
}
