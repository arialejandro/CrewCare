<?php

namespace App\Http\Controllers;

use App\Models\EmergencyActionPlan;
use App\Models\ScoutingReport;
use App\Support\CurrentProduction;
use App\Support\EmergencyActionPlanBuilder;
use App\Support\PaeOrgChart;
use App\Support\ProductionCalendar;
use Illuminate\Http\Request;

/**
 * PaeController — EMISIÓN del PLAN DE ATENCIÓN A EMERGENCIAS (PAE, 2026-08-06).
 *
 * Hermano del MedevacController: mismo motor (builder de solo lectura → payload
 * congelado → sello). NO es captura nueva: RENDERIZA desde el/los SCOUTING elegidos
 * (locación autoritativa + riesgos ya evaluados) más el organigrama del crew.
 *
 *   index   → PAE emitidos de la producción vigente.
 *   create  → formulario de emisión: día de rodaje (prellenado, editable), 1 ó 2
 *             locaciones (company move) en el orden capturado + hora del movimiento,
 *             organigrama pre-llenado del crew (editable), opción de embeber las
 *             vistas del mapa de riesgos sellado.
 *   store   → CONGELA el payload (EmergencyActionPlanBuilder), lo crea y lo SELLA
 *             (firma el safety que emite) → redirige al documento.
 *   show    → el PAE sellado, listo para window.print().
 *
 * QUIÉN: sólo el safety. El gate es el middleware de la RUTA (permission:pae.issue),
 * que cubre también la URL directa — lo cablea el owner en la zona reservada. El
 * verificador PÚBLICO (QR) vive en su ruta sin sesión (SealVerifier 'pae').
 */
class PaeController extends Controller
{
    /** PAE ya emitidos (de la producción vigente si la hay). */
    public function index()
    {
        abort_unless(EmergencyActionPlan::supported(), 404);

        $pid   = CurrentProduction::id();
        $plans = EmergencyActionPlan::query()
            ->where('is_active', 1)
            ->when($pid, fn ($q) => $q->where('production_id', $pid))
            ->orderByDesc('id')
            ->get();

        return view('admin.pae.index', compact('plans'));
    }

    /** Formulario de emisión. Prellena día de rodaje y organigrama; lista las locaciones. */
    public function create()
    {
        abort_unless(EmergencyActionPlan::supported(), 404);

        $pid = CurrentProduction::id();

        // Locaciones elegibles: scouting de la producción vigente; si no hay, los más recientes.
        $scoutings = ScoutingReport::query()
            ->when($pid, fn ($q) => $q->where('production_id', $pid))
            ->orderByDesc('id')
            ->get();
        if ($scoutings->isEmpty()) {
            $scoutings = ScoutingReport::orderByDesc('id')->limit(30)->get();
        }

        $contacts  = PaeOrgChart::resolve($pid);
        $shootDay  = ProductionCalendar::shootDayFor(now()->toDateString());   // derivación editable

        return view('admin.pae.create', compact('scoutings', 'contacts', 'shootDay'));
    }

    /** Emite: valida, construye el payload congelado, crea y sella con la firma del safety. */
    public function store(Request $request)
    {
        abort_unless(EmergencyActionPlan::supported(), 404);

        // Los dos selectores de locación pueden venir vacíos ("") → se limpian a enteros
        // positivos y se quitan duplicados ANTES de validar (loc1 requerida, loc2 opcional).
        $request->merge([
            'scoutings' => array_values(array_unique(array_filter(
                array_map('intval', (array) $request->input('scoutings', [])),
                fn ($v) => $v > 0
            ))),
        ]);

        $data = $request->validate([
            'scoutings'        => 'required|array|min:1|max:2',
            'scoutings.*'      => 'integer|exists:scouting_reports,id',
            'shoot_day'        => 'nullable|integer|min:1|max:999',
            'plan_date'        => 'nullable|date',
            'unit_name'        => 'nullable|string|max:255',
            'move_time'        => 'nullable|string|max:50',
            'embed_map_views'  => 'nullable|boolean',
            'header_show'      => 'nullable|array',
            'header_show.*'    => 'nullable|boolean',
            'contacts'         => 'nullable|array',
            'contacts.*.name'  => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.radio' => 'nullable|string|max:50',
        ]);

        // Locaciones EN EL ORDEN capturado (el orden del arreglo enviado manda; company move).
        $ids = array_values(array_unique(array_map('intval', $data['scoutings'])));
        $byId = ScoutingReport::whereIn('id', $ids)->get()->keyBy('id');
        $ordered = [];
        foreach ($ids as $id) {
            if ($byId->has($id)) {
                $ordered[] = $byId->get($id);
            }
        }
        if (empty($ordered)) {
            return back()->withInput()->withErrors(['scoutings' => 'Elige al menos una locación válida.']);
        }

        // Contactos ORDENADOS por slot, tal como el emisor los confirmó (pre-llenados o a mano).
        $raw = (array) $request->input('contacts', []);
        $contacts = [];
        foreach (PaeOrgChart::SLOTS as $slot) {
            $c = (array) ($raw[$slot['key']] ?? []);
            $contacts[] = [
                'key'   => $slot['key'],
                'label' => $slot['label'],
                'name'  => trim((string) ($c['name'] ?? '')),
                'phone' => trim((string) ($c['phone'] ?? '')),
                'radio' => trim((string) ($c['radio'] ?? '')),
            ];
        }

        // Día de rodaje: lo tecleado manda; si no, se deriva de la fecha (misma regla que el DSR).
        $planDate = trim((string) ($data['plan_date'] ?? '')) ?: now()->toDateString();
        $shootDay = self::resolveShootDay($data['shoot_day'] ?? null, $planDate);

        // Celdas del encabezado a imprimir (checkboxes; si NO viene el arreglo → todas).
        $headerShow = $request->has('header_show') ? (array) $request->input('header_show', []) : null;

        $opts = [
            'shoot_day'       => $shootDay,
            'plan_date'       => $planDate,
            'unit_name'       => trim((string) ($data['unit_name'] ?? '')),
            'move_time'       => trim((string) ($data['move_time'] ?? '')),
            'embed_map_views' => ! empty($data['embed_map_views']),
            'header_show'     => $headerShow,
            'contacts'        => $contacts,
        ];

        $payload = EmergencyActionPlanBuilder::build($ordered, $opts);

        // Etiqueta de listado (snapshot): día + locaciones.
        $names = array_map(fn ($s) => trim((string) $s->location_name), $ordered);
        $names = array_values(array_filter($names, fn ($n) => $n !== ''));
        $label = ($shootDay ? ('Día ' . $shootDay) : 'PAE');
        if ($names) {
            $label .= ' · ' . implode(' → ', $names);
        }

        $plan = EmergencyActionPlan::create([
            'production_id'  => $ordered[0]->production_id ?: CurrentProduction::id(),
            'shoot_day'      => $shootDay,
            'plan_date'      => $planDate,
            'unit_name'      => $opts['unit_name'] !== '' ? $opts['unit_name'] : null,
            'plan_label'     => $label,
            'payload'        => $payload,
            'issued_by_id'   => auth()->id(),
            'issued_by_name' => optional(auth()->user())->name,
            'issued_at'      => now(),
            'is_active'      => 1,
        ]);

        // Sellar sobre el estado CANÓNICO en BD (refresh → hash → firma): así el recompute del
        // verificador, que carga fresco, casa exactamente. Firma el safety que emite.
        $plan->refresh();
        $plan->signDocument(auth()->user(), $request);

        return redirect()->route('pae.show', $plan->uuid)
            ->with('success', 'PAE emitido y sellado (' . $plan->folio() . ').');
    }

    /** El PAE sellado (ligado por uuid, no por id secuencial). Se lee del payload congelado. */
    public function show(EmergencyActionPlan $pae)
    {
        abort_unless(EmergencyActionPlan::supported(), 404);

        return view('admin.pae.show', ['plan' => $pae]);
    }

    /**
     * Día de rodaje: si el emisor tecleó un número, se RESPETA; si no, se DERIVA de la fecha
     * del plan (misma regla que DailyReportController::resolveShootDay). Sin ancla, cae a 1.
     */
    protected static function resolveShootDay($typed, string $planDate): int
    {
        if ($typed !== null && $typed !== '') {
            return (int) $typed;
        }
        $derived = ProductionCalendar::shootDayFor($planDate);
        return $derived !== null ? (int) $derived : 1;
    }
}
