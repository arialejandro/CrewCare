<?php

namespace App\Http\Controllers;

use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;
use Illuminate\Http\Request;

/**
 * PARTE A · CALENDARIO DE RODAJE — configuración de la producción vigente (2026-08-23).
 *
 * La producción declara: fecha de inicio, duración en SEMANAS y semana de 5 o 6 días. De ahí
 * {@see ProductionCalendar} deriva el TOTAL de días de rodaje (la "M" de "Día N de M") y el wrap
 * estimado. `end_date` sigue siendo el wrap AJUSTABLE (se mueve en el 3-10 % de las producciones).
 *
 * NO escribe un calendario paralelo: sólo puebla las columnas que ProductionCalendar ya lee
 * (start_date / end_date / shoot_weeks / shoot_days_per_week). Gate: settings.manage (super-admin),
 * igual que Marca y las demás configuraciones de la productora.
 */
class ProductionCalendarController extends Controller
{
    public function edit()
    {
        $prod = CurrentProduction::get();

        return view('admin.production.calendar', [
            'prod'    => $prod,
            'summary' => ProductionCalendar::scheduleSummary(),
        ]);
    }

    public function update(Request $request)
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404, 'No hay producción vigente que configurar.');

        $data = $request->validate([
            'start_date'          => ['required', 'date'],
            'shoot_weeks'         => ['nullable', 'integer', 'min:1', 'max:104'],
            'shoot_days_per_week' => ['nullable', 'integer', 'in:5,6'],
            // Wrap AJUSTABLE: si se deja vacío se toma el derivado. No puede caer antes del inicio.
            'end_date'            => ['nullable', 'date', 'after_or_equal:start_date'],
        ], [], [
            'start_date'          => 'fecha de inicio',
            'shoot_weeks'         => 'duración en semanas',
            'shoot_days_per_week' => 'días por semana',
            'end_date'            => 'wrap',
        ]);

        // 1) Núcleo del calendario planeado.
        $prod->start_date          = $data['start_date'];
        $prod->shoot_weeks         = $data['shoot_weeks'] ?? null;
        $prod->shoot_days_per_week = $data['shoot_days_per_week'] ?? null;
        $prod->save();

        // Releer con los valores nuevos para derivar el wrap.
        ProductionCalendar::forget();
        CurrentProduction::forget();

        // 2) Wrap: el override manda; si viene vacío, se cae al derivado (si se puede derivar).
        if (! empty($data['end_date'])) {
            $prod->end_date = $data['end_date'];
        } else {
            $wrap = ProductionCalendar::plannedWrapDate();
            $prod->end_date = $wrap ? $wrap->toDateString() : null;
        }
        $prod->save();

        ProductionCalendar::forget();
        CurrentProduction::forget();

        return redirect()->route('production.calendar.edit')
            ->with('success', 'Calendario de rodaje actualizado.');
    }
}
