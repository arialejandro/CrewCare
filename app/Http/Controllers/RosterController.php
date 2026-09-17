<?php

namespace App\Http\Controllers;

use App\Support\DayRosterBuilder;
use App\Support\ProductionCalendar;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * RosterController — "¿QUIÉN TRABAJA HOY?" (2026-08-22). SOLO LECTURA.
 *
 * Responde de un vistazo, desde el teléfono, quién está llamado una fecha. Por defecto HOY,
 * navegable a cualquier día. El estado por-día lo arma DayRosterBuilder en consultas constantes
 * (no itera rosterStateOn por persona). Scope y orden reusan applyDepartmentScope/applyRosterOrder.
 *
 * Gate `crew.view`: el HOD entra y ve SU departamento; producción/coordinación/super-admin ven
 * todo (tienen crew.view.all-departments). El crew self-service no tiene crew.view → no entra.
 */
class RosterController extends Controller
{
    public function index(Request $request)
    {
        // (2026-08-23) RETIRADO por el owner: redundante con el llamado. Detrás del flag
        // `roster_day_view` (apagado por default); se reactiva desde Feature Flags si hace falta.
        abort_unless(\App\Support\Features::enabled('roster_day_view'), 404);

        // Fecha navegable; por defecto HOY. Sólo Y-m-d; cualquier otra cosa cae a hoy (nada de 500).
        $date = Carbon::today();
        $raw = (string) $request->query('date', '');
        if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            try {
                $date = Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
            } catch (\Throwable $e) {
                $date = Carbon::today();
            }
        }

        $roster = DayRosterBuilder::build($request->user(), $date);

        // Contexto de calendario: etiqueta del día ("Día 12"/"Prep -3"/"Domingo"/"—") + navegación
        // + "fuera del rodaje" EXPLÍCITO (compuesto contra ancla/wrap; prep NO es "fuera").
        $anchor = ProductionCalendar::anchorDate();
        $wrap   = ProductionCalendar::wrapDate();

        return view('admin.roster.index', [
            'roster'       => $roster,
            'date'         => $date,
            // "Día N de M" cuando el total planeado está configurado (PARTE A); si no, "Día N".
            'dayLabel'     => ProductionCalendar::dayLabelWithTotal($date),
            'prevDate'     => $date->copy()->subDay()->toDateString(),
            'nextDate'     => $date->copy()->addDay()->toDateString(),
            'todayDate'    => Carbon::today()->toDateString(),
            'isToday'      => $date->isSameDay(Carbon::today()),
            'noAnchor'     => $anchor === null,
            'beforeAnchor' => $anchor !== null && $date->lt($anchor),
            'afterWrap'    => $wrap !== null && $date->gt($wrap),
            'wrapDate'     => $wrap,
        ]);
    }
}
