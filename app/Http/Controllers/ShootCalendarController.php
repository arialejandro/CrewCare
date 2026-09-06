<?php

namespace App\Http\Controllers;

use App\Models\ShootDay;
use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;
use App\Support\ShootCalendarBuilder;
use App\Support\ShootCalendarService;
use Illuminate\Http\Request;

/**
 * CALENDARIO DE RODAJE DINÁMICO — producción marca a mano qué días se trabajan (2026-09-06).
 *
 * 🔑 EL CALENDARIO MANDA, EL DSR CONFIRMA, Y SIN DSR EL CALENDARIO SIGUE. Producción marca el FIN de
 * cada semana; los días se derivan hacia atrás ({@see ShootCalendarBuilder}) con la regla de la
 * madrugada, y se guardan explícitos en `shoot_days`. Las excepciones a mano ganan sobre la regla.
 *
 * 🔴 NADA de importación de planes (ni parseo ni IA): el plan es confidencial. Solo captura manual.
 * Gate settings.manage (super-admin), igual que el calendario planeado.
 */
class ShootCalendarController extends Controller
{
    public function edit()
    {
        $prod = CurrentProduction::get();
        $dias = $prod
            ? ShootDay::forProduction($prod->id)->orderBy('shoot_date')->get()
            : collect();

        return view('admin.production.shoot-calendar', [
            'prod'        => $prod,
            'dias'        => $dias,
            'daysPerWeek' => ProductionCalendar::shootDaysPerWeek(),
            'slugs'       => ShootDay::SLUGS,
            'total'       => ProductionCalendar::shootDaysCount(),
        ]);
    }

    /**
     * Genera los días desde los FINES DE SEMANA marcados. Se envían TODAS las semanas (la regla de la
     * madrugada depende del orden entre semanas). Las filas vacías se ignoran.
     */
    public function generate(Request $request)
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404, 'No hay producción vigente que configurar.');

        $data = $request->validate([
            'weeks'             => ['required', 'array', 'min:1'],
            'weeks.*.end'       => ['nullable', 'date'],
            'weeks.*.days'      => ['nullable', 'integer', 'min:1', 'max:7'],
            'weeks.*.last_slug' => ['nullable', 'string', 'in:' . implode(',', ShootDay::SLUGS)],
        ]);

        // Solo semanas con fin marcado; ordenadas por fin ascendente (la madrugada mira el orden).
        $weeks = array_values(array_filter($data['weeks'], fn ($w) => ! empty($w['end'])));
        if (empty($weeks)) {
            return redirect()->route('production.shootdays.edit')
                ->with('error', 'Marca al menos el fin de una semana.');
        }
        usort($weeks, fn ($a, $b) => strcmp($a['end'], $b['end']));

        $plan = ShootCalendarBuilder::build($weeks, ProductionCalendar::shootDaysPerWeek());
        ShootCalendarService::applyPlan((int) $prod->id, $plan, auth()->id());
        ProductionCalendar::forget();

        return redirect()->route('production.shootdays.edit')
            ->with('success', 'Calendario generado: ' . count($plan) . ' días de rodaje. Las excepciones a mano se conservaron.');
    }

    /** EXCEPCIÓN a mano: marca una fecha como día de rodaje o descanso. Gana sobre la regla. */
    public function toggleDay(Request $request)
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404);

        $data = $request->validate([
            'date'     => ['required', 'date'],
            'is_shoot' => ['required', 'boolean'],
        ]);
        ShootCalendarService::markException((int) $prod->id, $data['date'], (bool) $data['is_shoot'], auth()->id());
        ProductionCalendar::forget();

        return redirect()->route('production.shootdays.edit')->with('success', 'Excepción guardada (gana sobre la regla).');
    }

    /** EXCEPCIÓN a mano: fija la LUZ de un día en el calendario (sin leer el DSR). */
    public function setSlug(Request $request)
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'slug' => ['required', 'string', 'in:' . implode(',', ShootDay::SLUGS)],
        ]);
        ShootCalendarService::setLight((int) $prod->id, $data['date'], $data['slug'], auth()->id());
        ProductionCalendar::forget();

        return redirect()->route('production.shootdays.edit')->with('success', 'Luz del día actualizada.');
    }
}
