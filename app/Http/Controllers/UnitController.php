<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\UnitMembership;
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

        // Números para el AVISO previo a desactivar (§4): cuánta gente exclusiva se apagará y cuántos
        // documentos conserva cada unidad. La gente compartida (ambas) no cuenta: no se apaga.
        $meta = [];
        foreach ($units as $u) {
            $meta[$u->id] = [
                'off'  => UnitMembership::countActiveExclusivesOf((int) $u->id),
                'docs' => $u->documentCount(),
            ];
        }

        return view('admin.production.units', ['prod' => $prod, 'units' => $units, 'meta' => $meta]);
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

    /**
     * DESACTIVAR / reactivar. Nunca borra ni toca los documentos de la unidad.
     *
     * 🔑 Al DESACTIVAR, su gente EXCLUSIVA se apaga con ella (users.activo=0, mismo mecanismo que el crew):
     * no queda en limbo ni reaparece en la principal. Los COMPARTIDOS no se tocan. Al REACTIVAR, vuelven
     * EXACTAMENTE los que la unidad apagó. Ver App\Support\UnitMembership::deactivateExclusivesOf.
     */
    public function toggle(Request $request, Unit $unit)
    {
        $this->assertOwned($unit);
        $turningOff = (bool) $unit->is_active;   // estaba activa → la vamos a apagar

        if ($turningOff) {
            $n = UnitMembership::deactivateExclusivesOf((int) $unit->id);
            $unit->update(['is_active' => false]);
            $msg = 'Unidad desactivada.'
                . ($n > 0 ? " Se apagaron {$n} persona(s) exclusiva(s) de esta unidad (los compartidos siguen activos)." : '')
                . ' Sus documentos ya sellados conservan su unidad; todo vuelve si la reactivas.';
        } else {
            $unit->update(['is_active' => true]);
            $n = UnitMembership::reactivateAutoDeactivatedOf((int) $unit->id);
            $msg = 'Unidad reactivada.'
                . ($n > 0 ? " Volvieron {$n} persona(s) que se habían apagado con la unidad." : '');
        }

        return redirect()->route('production.units.index')->with('success', $msg);
    }

    /** La unidad debe ser de la producción vigente (o global). */
    private function assertOwned(Unit $unit): void
    {
        $pid = CurrentProduction::id();
        abort_unless($unit->production_id === null || (int) $unit->production_id === (int) $pid, 404);
    }
}
