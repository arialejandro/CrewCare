<?php

namespace App\Http\Controllers;

use App\Support\CurrentUnit;
use Illuminate\Http\Request;

/**
 * UnitContextController — cambia la UNIDAD VIGENTE de la sesión (Unidades 2b, opción (a)).
 *
 * El selector del topbar postea aquí. NO es gestión (no toca el catálogo `units`): sólo fija en la sesión
 * en qué unidad se está trabajando. NULL = principal. CurrentUnit::set() valida contra las unidades
 * ACTIVAS de la producción vigente (un id ajeno/desactivado cae a principal, falla seguro).
 */
class UnitContextController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate([
            'unit_id' => 'nullable|integer',
        ]);

        $raw = $data['unit_id'] ?? null;
        CurrentUnit::set(($raw === null || $raw === '') ? null : (int) $raw);

        // Vuelve a la pantalla donde estaba (el selector vive en el topbar de todas). Sin mensaje: la
        // franja de la unidad vigente ya lo grita en pantalla.
        return redirect()->back();
    }
}
