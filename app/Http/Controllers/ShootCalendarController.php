<?php

namespace App\Http\Controllers;

use App\Models\ShootDay;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;
use App\Support\ShootCalendarBuilder;
use App\Support\ShootCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * CALENDARIO DE RODAJE DINÁMICO — producción marca a mano qué días se trabajan (2026-09-06).
 *
 * 🔑 EL CALENDARIO MANDA, EL DSR CONFIRMA. La VISTA es una rejilla MENSUAL. Trabaja sobre UNA UNIDAD a la
 * vez; el selector de unidad aparece SOLO cuando existe más de una (con una sola, la pantalla es igual que
 * antes). NULL = unidad principal. La regla de la madrugada y el conteo son POR UNIDAD.
 *
 * 🔴 NADA de importación de planes. Gate settings.manage (super-admin).
 */
class ShootCalendarController extends Controller
{
    public function edit(Request $request)
    {
        $prod        = CurrentProduction::get();
        $activeUnits = $prod ? Unit::forProduction($prod->id)->where('is_active', true)->get() : collect();
        $unitId      = $this->resolveUnit($request->query('unit'), $activeUnits);

        // Mes visible: ?month; por default el mes del primer día marcado de ESTA unidad, luego el inicio.
        $default = Carbon::now();
        if ($prod) {
            $firstMarked = ShootDay::forProduction($prod->id)->forUnit($unitId)->where('is_shoot_day', 1)->min('shoot_date');
            if ($firstMarked) {
                $default = Carbon::parse($firstMarked);
            } elseif (! empty($prod->start_date)) {
                $default = Carbon::parse($prod->start_date);
            }
        }
        $cursor = $request->query('month')
            ? Carbon::createFromFormat('Y-m-d', $request->query('month') . '-01')
            : $default;
        $cursor = $cursor->startOfMonth();

        // Días marcados de ESTA unidad, y el fin de cada semana.
        $marked   = collect();
        $weekEnds = collect();
        if ($prod) {
            $rows     = ShootDay::forProduction($prod->id)->forUnit($unitId)->orderBy('shoot_date')->get();
            $marked   = $rows->keyBy(fn ($d) => $d->shoot_date->format('Y-m-d'));
            $weekEnds = $rows->where('is_shoot_day', true)->groupBy('week_no')
                ->map(fn ($g) => $g->max(fn ($d) => $d->shoot_date->format('Y-m-d')))
                ->values()->flip();
        }

        $gridStart = $cursor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd   = $cursor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $day   = $gridStart->copy();
        while ($day <= $gridEnd) {
            $cells = [];
            for ($i = 0; $i < 7; $i++) {
                $key     = $day->format('Y-m-d');
                $sd      = $marked->get($key);
                $isShoot = $sd ? (bool) $sd->is_shoot_day : false;
                $cells[] = [
                    'date'    => $key,
                    'dom'     => $day->day,
                    'inMonth' => $day->month === $cursor->month,
                    'sd'      => $sd,
                    'isShoot' => $isShoot,
                    'manual'  => $sd ? (bool) $sd->is_manual : false,
                    'slug'    => $sd ? $sd->slug() : ShootDay::SLUG_DIA,
                    'shootNo' => $isShoot ? ProductionCalendar::dayNumber($key, $unitId) : null,
                    'weekEnd' => $isShoot && $weekEnds->has($key),
                    'week_no' => $sd ? $sd->week_no : null,
                ];
                $day->addDay();
            }
            $weekNo  = collect($cells)->pluck('week_no')->filter()->first();
            $weeks[] = ['week_no' => $weekNo, 'dias' => $cells];
        }

        // Asistente (lote): reconstruye las semanas ya marcadas de ESTA unidad para editarlas.
        $semanas = [];
        if ($prod) {
            foreach ($marked->where('is_shoot_day', true)->groupBy('week_no') as $g) {
                $g    = $g->sortBy('shoot_date');
                $last = $g->last();
                $semanas[] = [
                    'end'       => optional($last->shoot_date)->format('Y-m-d'),
                    'days'      => $g->count(),
                    'last_slug' => $last->slug(),
                ];
            }
        }

        return view('admin.production.shoot-calendar', [
            'prod'        => $prod,
            'cursor'      => $cursor,
            'weeks'       => $weeks,
            'semanas'     => $semanas,
            'slugs'       => ShootDay::SLUGS,
            'units'       => $activeUnits,          // vacío = solo principal → sin selector
            'unitId'      => $unitId,
            'unitLabel'   => Unit::displayName($unitId),
            'prevMonth'   => $cursor->copy()->subMonth()->format('Y-m'),
            'nextMonth'   => $cursor->copy()->addMonth()->format('Y-m'),
            'daysPerWeek' => ProductionCalendar::shootDaysPerWeek(),
            'total'       => ProductionCalendar::shootDaysCount($unitId),
        ]);
    }

    public function generate(Request $request)
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404, 'No hay producción vigente que configurar.');
        $unitId = $this->resolveUnit($request->input('unit'), $this->activeUnits($prod));

        $data = $request->validate([
            'weeks'             => ['required', 'array', 'min:1'],
            'weeks.*.end'       => ['nullable', 'date'],
            'weeks.*.days'      => ['nullable', 'integer', 'min:1', 'max:7'],
            'weeks.*.last_slug' => ['nullable', 'string', 'in:' . implode(',', ShootDay::SLUGS)],
        ]);

        $weeks = array_values(array_filter($data['weeks'], fn ($w) => ! empty($w['end'])));
        if (empty($weeks)) {
            return $this->backToMonth($request, null, $unitId)->with('error', 'Marca al menos el fin de una semana.');
        }
        usort($weeks, fn ($a, $b) => strcmp($a['end'], $b['end']));

        $plan = ShootCalendarBuilder::build($weeks, ProductionCalendar::shootDaysPerWeek());
        ShootCalendarService::applyPlan((int) $prod->id, $plan, auth()->id(), $unitId);
        ProductionCalendar::forget();

        return $this->backToMonth($request, $weeks[0]['end'], $unitId)
            ->with('success', 'Calendario generado: ' . count($plan) . ' días de rodaje. Las excepciones a mano se conservaron.');
    }

    /** EXCEPCIÓN a mano: marca una fecha como día de rodaje o descanso EN ESTA UNIDAD. Gana sobre la regla. */
    public function toggleDay(Request $request)
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404);
        $unitId = $this->resolveUnit($request->input('unit'), $this->activeUnits($prod));

        $data = $request->validate([
            'date'     => ['required', 'date'],
            'is_shoot' => ['required', 'boolean'],
        ]);
        ShootCalendarService::markException((int) $prod->id, $data['date'], (bool) $data['is_shoot'], auth()->id(), $unitId);
        ProductionCalendar::forget();

        return $this->backToMonth($request, $data['date'], $unitId)->with('success', 'Día actualizado.');
    }

    /** EXCEPCIÓN a mano: fija la LUZ de un día EN ESTA UNIDAD (sin leer el DSR). */
    public function setSlug(Request $request)
    {
        $prod = CurrentProduction::get();
        abort_if($prod === null, 404);
        $unitId = $this->resolveUnit($request->input('unit'), $this->activeUnits($prod));

        $data = $request->validate([
            'date' => ['required', 'date'],
            'slug' => ['required', 'string', 'in:' . implode(',', ShootDay::SLUGS)],
        ]);
        ShootCalendarService::setLight((int) $prod->id, $data['date'], $data['slug'], auth()->id(), $unitId);
        ProductionCalendar::forget();

        return $this->backToMonth($request, $data['date'], $unitId)->with('success', 'Luz del día actualizada.');
    }

    /** Unidades activas de la producción (para validar la selección). */
    private function activeUnits($prod)
    {
        return $prod ? Unit::forProduction($prod->id)->where('is_active', true)->get() : collect();
    }

    /** Resuelve la unidad seleccionada: id de una unidad ACTIVA, o null (principal). Nunca confía en el input. */
    private function resolveUnit($raw, $activeUnits): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = (int) $raw;

        return $activeUnits->firstWhere('id', $id) ? $id : null;
    }

    /** Vuelve al calendario conservando el mes visible y la unidad. */
    private function backToMonth(Request $request, ?string $date = null, ?int $unitId = null)
    {
        $month = $request->input('month');
        if (! $month && $date) {
            $month = Carbon::parse($date)->format('Y-m');
        }
        $params = [];
        if ($month) {
            $params['month'] = $month;
        }
        if ($unitId !== null) {
            $params['unit'] = $unitId;
        }

        return redirect()->route('production.shootdays.edit', $params);
    }
}
