<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Support\CrewRosterBuilder;
use App\Support\CurrentProduction;
use App\Support\UnitMembership;
use Illuminate\Http\Request;

/**
 * UNIDADES · 2c — EL CONSTRUCTOR de una unidad adicional. Una comparación de CrewList: el crew POR
 * DEPARTAMENTO (como el back), y por persona un control de 3 estados para decir si está en esta unidad.
 *
 * 🔑 Nadie se registra de nuevo: sólo se MARCA la presencia (pivote unit_members). No duplica ni pide nada.
 * 🔑 Se ve de un vistazo quién está COMPARTIDO (en las dos). Lo normal es exclusivo; lo compartido es la
 * excepción que se marca (sobre todo Arte/Construcción/Decoración/Locaciones).
 *
 * El mismo selector sirve para el SWITCH (una asignación puede cambiar por logística). Gate settings.manage
 * (mismo que el CRUD de unidades). `users`/`production_user` NUNCA llevan unidad — la pertenencia vive aquí.
 */
class UnitBuilderController extends Controller
{
    /** Departamentos donde COMPARTIR es lo esperado (se ofrecen "Ambas" como sugerencia visual, no default). */
    private const SHARED_HINT = ['Arte', 'Construcción', 'Construccion', 'Decoración', 'Decoracion', 'Locaciones'];

    public function show(Unit $unit)
    {
        $this->assertOwned($unit);

        // TODO el crew por departamento (allUnits=true: el constructor ve a todos para asignarlos).
        $roster = CrewRosterBuilder::build(auth()->user(), true);
        $states = UnitMembership::statesFor((int) $unit->id);   // [user_id => 'solo'|'ambas']

        $enBoth = collect($states)->filter(fn ($s) => $s === 'ambas')->count();
        $enUnit = count($states);

        return view('admin.production.unit-builder', [
            'unit'       => $unit,
            'groups'     => $roster['groups'] ?? [],
            'states'     => $states,
            'sharedHint' => self::SHARED_HINT,
            'enUnit'     => $enUnit,
            'enBoth'     => $enBoth,
        ]);
    }

    /**
     * Guarda la asignación de un jalón. `m[<user_id>]` ∈ {principal, solo, ambas}. Sólo escribe lo que
     * CAMBIA (diff contra el estado actual) para no barrer la pivote entera en cada guardado.
     */
    public function save(Request $request, Unit $unit)
    {
        $this->assertOwned($unit);

        $data = $request->validate([
            'm'   => ['nullable', 'array'],
            'm.*' => ['in:principal,solo,ambas'],
        ]);

        $current = UnitMembership::statesFor((int) $unit->id);   // [user_id => 'solo'|'ambas']
        $byId    = auth()->id();
        $changed = 0;

        foreach (($data['m'] ?? []) as $userId => $state) {
            $userId = (int) $userId;
            $now    = $current[$userId] ?? 'principal';
            if ($now === $state) {
                continue;   // sin cambio → no se toca
            }
            UnitMembership::setState((int) $unit->id, $userId, $state, $byId);
            $changed++;
        }

        return redirect()->route('production.units.builder', $unit->id)
            ->with('success', $changed > 0 ? "Asignación guardada ({$changed} cambio(s))." : 'Sin cambios.');
    }

    private function assertOwned(Unit $unit): void
    {
        $pid = CurrentProduction::id();
        abort_unless($unit->production_id === null || (int) $unit->production_id === (int) $pid, 404);
    }
}
