<?php

namespace App\Http\Controllers;

use App\Models\EmergencyActionPlan;
use App\Models\ScoutingReport;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\EmergencyActionPlanBuilder;
use App\Support\PaeOrgChart;
use App\Support\ProductionCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

    /** Formulario de emisión NUEVA (v1). Prellena día de rodaje y organigrama del crew. */
    public function create()
    {
        abort_unless(EmergencyActionPlan::supported(), 404);

        return view('admin.pae.create', $this->formData(null));
    }

    /**
     * Formulario de EDICIÓN: mismo form prellenado con el PAE fuente. Al enviar se emite una
     * REVISIÓN nueva que lo supersede. Sólo se edita la versión VIGENTE (la última activa).
     */
    public function edit(EmergencyActionPlan $pae)
    {
        abort_unless(EmergencyActionPlan::supported() && EmergencyActionPlan::supportsVersioning(), 404);

        if (! $pae->is_active) {
            return redirect()->route('pae.index')
                ->with('success', 'Esa versión ya fue reemplazada; edita la vigente.');
        }

        return view('admin.pae.create', $this->formData($pae));
    }

    /** Datos del formulario: crear (source=null) o editar (source=plan a versionar). */
    protected function formData(?EmergencyActionPlan $source): array
    {
        $pid       = CurrentProduction::id();
        $scoutings = $this->eligibleScoutings($pid);

        if ($source) {
            $hd       = (array) $source->pdata('header', []);
            $move     = (array) $source->pdata('company_move', []);
            $contacts = $this->contactsFromPlan($source);
            $prefill  = [
                'scoutings' => array_values(array_map('intval', (array) $source->pdata('scouting_ids', []))),
                'plan_date' => (trim((string) ($hd['date'] ?? '')) ?: optional($source->plan_date)->toDateString()) ?: now()->toDateString(),
                'shoot_day' => $source->shoot_day,
                'unit_id'   => $source->unit_id,
                'unit_name' => (string) ($source->unit_name ?? ''),
                'move_time' => trim((string) ($move['move_time'] ?? '')),
                'embed'     => (bool) $source->pdata('embed_map_views', false),
            ];
        } else {
            $contacts = PaeOrgChart::resolve($pid);
            $prefill  = [
                'scoutings' => [],
                'plan_date' => now()->toDateString(),
                'shoot_day' => ProductionCalendar::shootDayFor(now()->toDateString()),
                'unit_id'   => null,
                'unit_name' => '',
                'move_time' => '',
                'embed'     => false,
            ];
        }

        $shootDay = $prefill['shoot_day'];

        // (2026-09-07 · Unidades 2b) Unidades ADICIONALES activas de la producción. El PAE es el único
        // documento que ELIGE su unidad por documento (nombra su unidad — antes en texto libre); las demás
        // operativas la heredan del contexto (§4). Con 0 unidades adicionales el selector no se pinta y el
        // campo de texto libre `unit_name` sigue igual que hoy → idéntico. Guard de tabla para no romper
        // una instancia sin el esquema de P2A.
        $units = collect();
        if (Schema::hasTable('units')) {
            $units = Unit::query()->forProduction($pid)->active()->get();
        }

        return compact('scoutings', 'contacts', 'shootDay', 'source', 'prefill', 'units');
    }

    /** Locaciones elegibles: scouting de la producción vigente; si no hay, los más recientes. */
    protected function eligibleScoutings($pid)
    {
        $scoutings = ScoutingReport::query()
            ->when($pid, fn ($q) => $q->where('production_id', $pid))
            ->orderByDesc('id')
            ->get();
        if ($scoutings->isEmpty()) {
            $scoutings = ScoutingReport::orderByDesc('id')->limit(30)->get();
        }
        return $scoutings;
    }

    /** Organigrama tomado del payload del plan fuente, remapeado a los slots ACTUALES. */
    protected function contactsFromPlan(EmergencyActionPlan $source): array
    {
        $crew  = (array) (($source->pdata('org', []) ?: [])['crew'] ?? []);
        $byKey = [];
        foreach ($crew as $c) {
            if (! empty($c['key'])) { $byKey[(string) $c['key']] = $c; }
        }

        $out = [];
        foreach (PaeOrgChart::SLOTS as $slot) {
            $c = $byKey[$slot['key']] ?? [];
            $out[] = [
                'key'   => $slot['key'],
                'label' => $slot['label'],
                'name'  => trim((string) ($c['name'] ?? '')),
                'phone' => trim((string) ($c['phone'] ?? '')),
                'radio' => trim((string) ($c['radio'] ?? '')),
            ];
        }
        return $out;
    }

    /** Emite: valida, construye el payload congelado, crea y sella con la firma del safety. */
    public function store(Request $request)
    {
        abort_unless(EmergencyActionPlan::supported(), 404);

        $built = $this->buildPayload($request);

        // VERSIONADO: si se está EDITANDO (supersedes_uuid), la nueva es una REVISIÓN que
        // reemplaza a la anterior; hereda su folio (root) y sube la revisión.
        $source   = null;
        $revision = 1;
        $rootId   = null;
        if (EmergencyActionPlan::supportsVersioning()) {
            $su = trim((string) $request->input('supersedes_uuid', ''));
            if ($su !== '') {
                $source = EmergencyActionPlan::where('uuid', $su)->first();
                if ($source) {
                    $revision = $source->revisionNumber() + 1;
                    $rootId   = (int) ($source->root_id ?? 0) ?: (int) $source->id;   // ancla el folio a la v1
                }
            }
        }

        $attrs = [
            'production_id'  => $built['productionId'],
            'shoot_day'      => $built['shootDay'],
            'plan_date'      => $built['planDate'],
            'unit_name'      => $built['unitName'] !== '' ? $built['unitName'] : null,
            'plan_label'     => $built['label'],
            'payload'        => $built['payload'],
            'issued_by_id'   => auth()->id(),
            'issued_by_name' => optional(auth()->user())->name,
            'issued_at'      => now(),
            'is_active'      => 1,
        ];
        // (2026-09-07 · Unidades 2b) Referencia estructurada a la unidad (unit_id) JUNTO al snapshot de
        // texto (unit_name). Guard de columna: sin P2A aplicado no se intenta escribir. NULL = principal.
        if (Schema::hasColumn('emergency_action_plans', 'unit_id')) {
            $attrs['unit_id'] = $built['unitId'];
        }
        if (EmergencyActionPlan::supportsVersioning()) {
            $attrs['revision']      = $revision;
            $attrs['supersedes_id'] = $source ? $source->id : null;
            $attrs['root_id']       = $rootId;   // NULL en la v1 → folio() usa su propio id
        }

        $plan = EmergencyActionPlan::create($attrs);

        // Sellar sobre el estado CANÓNICO en BD (refresh → hash → firma): así el recompute del
        // verificador, que carga fresco, casa exactamente. Firma el safety que emite.
        $plan->refresh();
        $plan->signDocument(auth()->user(), $request);

        // Reemplaza: apaga la versión anterior del listado (NO se borra; su sello sigue verificable
        // por su propio uuid). is_active está FUERA del hash, así que apagarla no la marca alterada.
        if ($source) {
            $source->update(['is_active' => 0]);
        }

        $msg = $source
            ? ('Nueva versión emitida: ' . $plan->folio() . ' ' . $plan->versionLabel() . ' (reemplaza a ' . $source->versionLabel() . ').')
            : ('PAE emitido y sellado (' . $plan->folio() . ').');

        return redirect()->route('pae.show', $plan->uuid)->with('success', $msg);
    }

    /**
     * PREVISUALIZACIÓN editable (patrón Wrap): construye el MISMO payload que store() sobre un
     * EmergencyActionPlan NO GUARDADO (sin sellar) y renderiza la misma vista en modo borrador,
     * con una barra "Emitir y sellar" que reenvía los campos a store(). store() sigue siendo la
     * ÚNICA autoridad que congela+sella (reconstruye el payload desde la petición), así que un
     * borrador manipulado no inyecta contenido. El borrador NO congela ni sella nada.
     */
    public function preview(Request $request)
    {
        abort_unless(EmergencyActionPlan::supported(), 404);

        $built = $this->buildPayload($request);

        // Fila EN MEMORIA (no save/no exists → verifyLatestSignature() null → se pinta sin sellar).
        $plan = new EmergencyActionPlan([
            'production_id'  => $built['productionId'],
            'shoot_day'      => $built['shootDay'],
            'plan_date'      => $built['planDate'],
            'unit_id'        => $built['unitId'],
            'unit_name'      => $built['unitName'] !== '' ? $built['unitName'] : null,
            'plan_label'     => $built['label'],
            'payload'        => $built['payload'],
            'issued_by_name' => optional(auth()->user())->name,
            'is_active'      => 1,
        ]);

        return view('admin.pae.show', [
            'plan'     => $plan,
            'borrador' => true,
            // Campos que la barra del borrador reenvía a store() para EMITIR+SELLAR. Se usan los
            // valores YA RESUELTOS (día/fecha) para que el sellado sea IDÉNTICO al preview.
            'formEcho' => [
                'scoutings'       => $built['scoutingIds'],
                'contacts'        => (array) $request->input('contacts', []),
                'shoot_day'       => (string) $built['shootDay'],
                'plan_date'       => (string) $built['planDate'],
                'unit_id'         => $built['unitId'] !== null ? (string) $built['unitId'] : '',
                'unit_name'       => (string) $built['unitName'],
                'move_time'       => (string) $built['moveTime'],
                'embed_map_views' => $built['embed'] ? '1' : '',
                'supersedes_uuid' => (string) $request->input('supersedes_uuid', ''),
            ],
        ]);
    }

    /**
     * Núcleo COMPARTIDO por store() y preview(): valida la petición y CONGELA el payload del PAE
     * (mismo builder de solo lectura). La validación lanza ValidationException (redirige sola).
     * NO guarda ni sella nada. Que ambos caminos usen ESTO garantiza que el documento sellado sea
     * idéntico al previsualizado.
     *
     * @return array{payload:array,label:string,shootDay:int,planDate:string,unitId:?int,unitName:string,moveTime:string,embed:bool,productionId:int,scoutingIds:array}
     */
    protected function buildPayload(Request $request): array
    {
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
            // (2026-09-07 · Unidades 2b) unidad ELEGIDA (adicional). nullable|integer; se re-valida contra
            // el catálogo activo abajo (whereKey). Vacío = principal, como hoy.
            'unit_id'          => 'nullable|integer',
            'unit_name'        => 'nullable|string|max:255',
            'move_time'        => 'nullable|string|max:50',
            'embed_map_views'  => 'nullable|boolean',
            'supersedes_uuid'  => 'nullable|string',   // presente = EDICIÓN → nueva revisión
            'contacts'         => 'nullable|array',
            'contacts.*.name'  => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.radio' => 'nullable|string|max:50',
        ]);

        // Locaciones EN EL ORDEN capturado (el orden del arreglo enviado manda; company move).
        $ids  = array_values(array_unique(array_map('intval', $data['scoutings'])));
        $byId = ScoutingReport::whereIn('id', $ids)->get()->keyBy('id');
        $ordered = [];
        foreach ($ids as $id) {
            if ($byId->has($id)) {
                $ordered[] = $byId->get($id);
            }
        }
        if (empty($ordered)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['scoutings' => 'Elige al menos una locación válida.']);
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

        $planDate = trim((string) ($data['plan_date'] ?? '')) ?: now()->toDateString();

        // (2026-09-07 · Unidades 2b) UNIDAD ELEGIDA. El PAE es el único documento que NOMBRA su unidad por
        // documento (antes texto libre en `unit_name`). Si el emisor eligió una unidad adicional (unit_id),
        // se resuelve contra el catálogo `units` (ACTIVA, de esta producción) y su NOMBRE se ESTAMPA como
        // snapshot en unit_name — la unidad estructurada manda sobre el texto. Sin elección → principal
        // (unit_id null) y `unit_name` sigue siendo el texto libre de hoy (compatibilidad).
        $unitId   = null;
        $unitName = trim((string) ($data['unit_name'] ?? ''));
        $rawUnit  = $data['unit_id'] ?? null;
        if ($rawUnit !== null && $rawUnit !== '' && Schema::hasTable('units')) {
            $unit = Unit::query()
                ->forProduction($ordered[0]->production_id ?: CurrentProduction::id())
                ->active()->whereKey((int) $rawUnit)->first();
            if ($unit) {
                $unitId   = (int) $unit->id;
                $unitName = (string) $unit->name;   // foto congelada del nombre de la unidad elegida
            }
        }

        // Día de rodaje: lo tecleado manda; si no, se deriva de la fecha CONTRA LA UNIDAD ELEGIDA (misma
        // regla que el DSR). 🔴 Un PAE de la 2ª unidad sella SU día, no el de la principal — y como
        // shoot_day se sella y no se reescribe, sellar el de la principal quedaría mal para siempre.
        $shootDay = self::resolveShootDay($data['shoot_day'] ?? null, $planDate, $unitId);
        $moveTime = trim((string) ($data['move_time'] ?? ''));
        $embed    = ! empty($data['embed_map_views']);

        $opts = [
            'shoot_day'       => $shootDay,
            'plan_date'       => $planDate,
            'unit_name'       => $unitName,
            'move_time'       => $moveTime,
            'embed_map_views' => $embed,
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

        return [
            'payload'      => $payload,
            'label'        => $label,
            'shootDay'     => $shootDay,
            'planDate'     => $planDate,
            'unitId'       => $unitId,
            'unitName'     => $unitName,
            'moveTime'     => $moveTime,
            'embed'        => $embed,
            'productionId' => $ordered[0]->production_id ?: CurrentProduction::id(),
            'scoutingIds'  => $ids,
        ];
    }

    /** El PAE sellado (ligado por uuid, no por id secuencial). Se lee del payload congelado. */
    public function show(EmergencyActionPlan $pae)
    {
        abort_unless(EmergencyActionPlan::supported(), 404);

        // (2026-08-11) EXPORT PDF SERVER-SIDE (?pdf=1) — ADITIVO, antes del return normal. Reusa la
        // MISMA vista/datos y la pasa por Browsershot (Chrome headless) → descarga idéntica a
        // window.print(). Márgenes 0 (el @page Oficio manda). Ver [[browsershot-pdf-pipeline]].
        if (request()->boolean('pdf')) {
            $html = view('admin.pae.show', ['plan' => $pae])->render();
            return \App\Support\PdfExporter::download($html, 'PAE-' . $pae->id, [0, 0, 0, 0]);
        }

        return view('admin.pae.show', [
            'plan'   => $pae,
            'pdfUrl' => request()->fullUrlWithQuery(['pdf' => 1]),
        ]);
    }

    /**
     * Día de rodaje: si el emisor tecleó un número, se RESPETA; si no, se DERIVA de la fecha del plan
     * CONTRA LA UNIDAD del documento (misma regla que DailyReportController::resolveShootDay). Sin ancla,
     * cae a 1. 🔴 NULL = principal; una unidad adicional deriva contra SU propio calendario (día 1 el
     * primero) para no sellar el número de la principal (irreversible).
     */
    protected static function resolveShootDay($typed, string $planDate, ?int $unitId = null): int
    {
        if ($typed !== null && $typed !== '') {
            return (int) $typed;
        }
        $derived = ProductionCalendar::shootDayFor($planDate, $unitId);
        return $derived !== null ? (int) $derived : 1;
    }
}
