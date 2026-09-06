<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Support\CurrentProduction;
use Illuminate\Http\Request;

/**
 * UNIDADES · CRUD sencillo (2026-09-06). 🔑 La UNIDAD PRINCIPAL (unit_id NULL) NO es una fila: es la
 * unidad uno, donde vive todo lo existente. Aquí se dan de alta las unidades ADICIONALES (2ª y siguientes).
 *
 * 🔴 Baja por DESACTIVACIÓN (is_active=0), NUNCA borrado. Desactivar una unidad NO toca sus documentos:
 * los que ya llevan su unit_id sellado lo CONSERVAN (el sello sigue válido); la unidad solo deja de
 * ofrecerse en los selectores. La FK a units es ON DELETE RESTRICT, así que ni siquiera se puede borrar
 * una unidad con documentos. Sin backfill: crear una unidad no mueve nada de lo existente. Gate settings.manage.
 */
class UnitController extends Controller
{
    public function index()
    {
        $prod  = CurrentProduction::get();
        $units = $prod ? Unit::forProduction($prod->id)->get() : collect();

        return view('admin.production.units', ['prod' => $prod, 'units' => $units]);
    }

    public function store(Request $request)
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404, 'No hay producción vigente.');

        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $max  = (int) Unit::forProduction($prod->id)->max('sort_order');

        Unit::create([
            'production_id' => $prod->id,
            'name'          => $data['name'],
            'sort_order'    => $max + 1,
            'is_active'     => true,
            'created_by_id' => auth()->id(),
        ]);

        return redirect()->route('production.units.index')->with('success', 'Unidad agregada. Todo lo existente sigue en la unidad principal (sin cambios).');
    }

    public function update(Request $request, Unit $unit)
    {
        $this->assertOwned($unit);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $unit->update([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? $unit->sort_order,
        ]);

        return redirect()->route('production.units.index')->with('success', 'Unidad actualizada.');
    }

    /** DESACTIVAR / reactivar. Nunca borra. No toca los documentos de la unidad. */
    public function toggle(Request $request, Unit $unit)
    {
        $this->assertOwned($unit);
        $unit->update(['is_active' => ! $unit->is_active]);

        $msg = $unit->is_active
            ? 'Unidad activada.'
            : 'Unidad desactivada. Sus documentos ya sellados conservan su unidad; solo deja de aparecer en los selectores.';

        return redirect()->route('production.units.index')->with('success', $msg);
    }

    /** La unidad debe ser de la producción vigente (o global). */
    private function assertOwned(Unit $unit): void
    {
        $pid = CurrentProduction::id();
        abort_unless($unit->production_id === null || (int) $unit->production_id === (int) $pid, 404);
    }
}
