<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Tool;
use App\Models\ToolInspection;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\ImageCompressor;
use App\Support\InspectionVerdict;
use App\Support\InvolvedResolver;
use App\Support\ProductionCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Vertical de INSPECCIÓN DE HERRAMIENTA (delta #42): buscar → ejecutar → veredicto,
 * con acta sellada. La operación está DETENIDA mientras se inspecciona, así que
 * todo apunta a que el safety termine rápido.
 *
 * Gate: permission:tools.inspect (rutas). El verificador público del acta NO usa
 * este controlador (vive en SealVerificationController, sin sesión).
 */
class InspectionController extends Controller
{
    /**
     * Puertas de origen (A4): clave corta → modelo del reporte que dispara la inspección.
     * Se guarda la clase en origin_type; la clave nunca toca la BD.
     */
    const ORIGIN_MAP = [
        'condicion'  => \App\Models\unsafecond::class,
        'acto'       => \App\Models\hazardnotification::class,
        'dsr'        => \App\Models\DailyReport::class,
        'accidente'  => \App\Models\InjuryReport::class,
    ];

    /* ===================== PASO 1 · ENCONTRAR LA HERRAMIENTA ===================== */

    public function index(Request $request)
    {
        $tools = Tool::query()
            ->active()
            ->where('is_wildcard', 0)
            ->with('family')
            ->withCount(['checkPoints as paro_count' => function ($q) {
                // conteo de puntos de PARO (gate cuyo salida = correccion/reemplazo).
                $q->where('is_gate', 1)->whereIn('outcome_if_fail', [
                    ToolInspection::PATH_SAME_DAY, ToolInspection::PATH_REPLACE,
                ]);
            }])
            ->orderBy('code')
            ->paginate(48);

        $wildcard = Tool::where('is_wildcard', 1)->first();

        // (2026-08-08) Se retiró "la lista del día" (deuda por_jornada): era una suposición del
        // catálogo, no lo que realmente llega al set → engañosa. Ver inspection/index.blade.php.

        // Puerta (A4): si se llegó desde un hallazgo/DSR/accidente, se arrastra el vínculo.
        $launch = $this->launchParams($request);

        return view('inspection.index', compact('tools', 'wildcard', 'launch'));
    }

    /** Parámetros de "puerta" (origen + momento) que sobreviven del reporte al acta. */
    private function launchParams(Request $request): array
    {
        $out = [];
        if ($request->query('origin') && isset(self::ORIGIN_MAP[$request->query('origin')]) && $request->query('origin_id')) {
            $out['origin'] = $request->query('origin');
            $out['origin_id'] = (int) $request->query('origin_id');
        }
        if (in_array($request->query('moment'), ToolInspection::MOMENTS, true)) {
            $out['moment'] = $request->query('moment');
        }
        return $out;
    }

    /** Búsqueda AJAX — EL ALIAS ES EL PRIMER CAMPO. También nombre ES/EN, código y variantes. */
    public function search(Request $request, $q = null)
    {
        $wildcard = Tool::where('is_wildcard', 1)->first();
        $launch = $this->launchParams($request); // el origen/momento de la puerta sobrevive a la búsqueda
        $q = trim((string) $q);
        if ($q === '' || $q === 'vacio') {
            $tools = Tool::query()->active()->where('is_wildcard', 0)->with('family')
                ->withCount(['checkPoints as paro_count' => function ($qq) {
                    $qq->where('is_gate', 1)->whereIn('outcome_if_fail', [ToolInspection::PATH_SAME_DAY, ToolInspection::PATH_REPLACE]);
                }])
                ->orderBy('code')->paginate(48);
            return view('inspection.search', compact('tools', 'wildcard', 'launch'));
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        $tools = Tool::query()
            ->active()
            ->where('is_wildcard', 0)
            ->with('family')
            ->withCount(['checkPoints as paro_count' => function ($qq) {
                $qq->where('is_gate', 1)->whereIn('outcome_if_fail', [ToolInspection::PATH_SAME_DAY, ToolInspection::PATH_REPLACE]);
            }])
            ->where(function ($w) use ($like) {
                $w->where('aliases', 'like', $like)      // alias primero (el crew dice el apodo)
                  ->orWhere('name', 'like', $like)
                  ->orWhere('name_en', 'like', $like)
                  ->orWhere('code', 'like', $like)
                  ->orWhereIn('id', function ($sub) use ($like) {
                      $sub->select('tool_id')->from('tool_variants')->where('name', 'like', $like);
                  });
            })
            // el que trae el alias primero, arriba.
            ->orderByRaw('CASE WHEN aliases LIKE ? THEN 0 ELSE 1 END', [$like])
            ->orderBy('code')
            ->paginate(48);

        return view('inspection.search', compact('tools', 'wildcard', 'launch'));
    }

    /** Ficha (acción secundaria, no pesa igual que INSPECCIONAR). */
    public function show(Tool $tool)
    {
        $tool->load('family', 'variants', 'standards', 'permits');
        $points = $this->pointsFor($tool);
        return view('inspection.show', compact('tool', 'points'));
    }

    /* ===================== CONSULTA · ACTAS (POR UNIDAD FÍSICA) ===================== */

    /**
     * Histórico de actas para CONSULTAR (lo que faltaba: solo se veía el acta recién creada o
     * por su QR). La "unidad física" NO es tabla: EMERGE del número de serie. Por eso la consulta
     * busca por serie (además de dueño, tipo, marca/modelo y folio) y, sin filtro, AGRUPA por
     * serie para juntar las inspecciones de la MISMA herramienta; con `?serial=` da la línea de
     * tiempo de esa unidad concreta.
     */
    public function records(Request $request)
    {
        $q      = trim((string) $request->query('q', ''));
        $serial = trim((string) $request->query('serial', ''));

        $query = ToolInspection::query();

        if ($serial !== '') {
            $query->where('tool_serial', $serial);              // unidad concreta (llave exacta)
        } elseif ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('tool_serial', 'like', $like)
                  ->orWhere('owner_name', 'like', $like)
                  ->orWhere('tool_name', 'like', $like)
                  ->orWhere('tool_code', 'like', $like)
                  ->orWhere('tool_brand', 'like', $like)
                  ->orWhere('tool_model', 'like', $like);
            });
            if (preg_match('/(\d+)/', $q, $m)) {
                $query->orWhere('id', (int) $m[1]);             // folio INSP-000N por número
            }
        }

        if ($serial !== '') {
            $query->orderBy('created_at', 'desc');              // línea de tiempo de la unidad
        } else {
            // Agrupa por serie (las sin serie al final) y dentro, lo más reciente primero.
            $query->orderByRaw("(tool_serial IS NULL OR tool_serial = '') asc")
                  ->orderBy('tool_serial')
                  ->orderBy('created_at', 'desc');
        }

        $inspections = $query->paginate(30)->withQueryString();

        return view('inspection.records', compact('inspections', 'q', 'serial'));
    }

    /* ===================== PASO 2 · EJECUTAR EL CHECKLIST ===================== */

    public function create(Request $request, Tool $tool)
    {
        $tool->load('family', 'standards', 'permits');
        $isWildcard = (bool) $tool->is_wildcard;

        // El comodín elige familia al vuelo; su checklist se resuelve al elegirla.
        $families = $isWildcard
            ? \App\Models\ToolFamily::active()->orderBy('sort_order')->orderBy('name')->get()
            : collect();

        $familyKey = $request->query('family'); // el comodín la manda por query al elegirla
        $points = $isWildcard
            ? ($familyKey ? $this->pointsForWildcard($familyKey) : collect())
            : $this->pointsFor($tool);

        $departments = Department::where('active', 1)->orderBy('sort_order')->orderBy('name')->get();

        // Dueño de la herramienta (delta #47): crew activo para el selector. Si el dueño es de
        // una casa de renta / externo, el form ofrece además un campo de texto libre.
        $crew = User::where('activo', 1)->orderBy('name')->orderBy('lname')
            ->get(['id', 'name', 'lname', 'ncreditos']);

        // Momento (A2): default previo_al_uso; una puerta (hallazgo/DSR/accidente) puede prefijarlo.
        $moment = in_array($request->query('moment'), ToolInspection::MOMENTS, true)
            ? $request->query('moment') : ToolInspection::MOMENT_PRE_USE;

        // Origen (A4): se re-postea como clave cruda; store() la resuelve y valida.
        $origin = null; $originId = null;
        [$oType, $oId] = $this->resolveOrigin($request->query('origin'), $request->query('origin_id'));
        if ($oType) { $origin = $request->query('origin'); $originId = $oId; } // solo si resolvió a un reporte real

        // ¿Ya hay un acta VIGENTE de este tipo? (para no reinspeccionar de más).
        $vigente = $isWildcard ? null : $this->vigenteFor($tool);

        // Paso 6 (delta #44): ¿hay PERMISO DE TRABAJO vigente para lo que dispara esta herramienta,
        // hoy? Se informa y se ofrece emitir — NUNCA bloquea la inspección (son dos actos distintos).
        $permit = $tool->permits->first();
        $permitVigente = null;
        if ($permit && \Illuminate\Support\Facades\Schema::hasTable('issued_permits')) {
            $permitVigente = \App\Models\IssuedPermit::vigenteFor(
                $permit->code, $this->currentShootDay(), null, $permit->site_scope
            );
        }

        return view('inspection.execute', compact(
            'tool', 'points', 'departments', 'crew', 'isWildcard', 'families', 'familyKey',
            'moment', 'origin', 'originId', 'vigente', 'permit', 'permitVigente'
        ));
    }

    public function store(Request $request, Tool $tool)
    {
        $isWildcard = (bool) $tool->is_wildcard;

        $rules = [
            'department_id'  => 'required|integer|exists:departments,id',
            'tool_model'     => 'nullable|string|max:255',
            // Unidad FÍSICA (delta #47): marca/serie + dueño (crew o texto libre) + foto real.
            'tool_brand'     => 'nullable|string|max:120',
            'tool_serial'    => 'nullable|string|max:120',
            'owner_user_id'  => 'nullable|integer|exists:users,id',
            'owner_name'     => 'nullable|string|max:160',
            'tool_photo'     => 'nullable|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288',
            'checklist_mode' => 'nullable|in:safety,operator',
            'inspection_moment' => 'nullable|in:'.implode(',', ToolInspection::MOMENTS),
            'origin'         => 'nullable|string|in:'.implode(',', array_keys(self::ORIGIN_MAP)),
            'origin_id'      => 'nullable|integer',
            'planned_use_at' => 'nullable|date',
            'note'           => 'nullable|string|max:2000',
            'answers'        => 'required|array|min:1',
            'answers.*'      => 'in:ok,fail',
        ];
        if ($isWildcard) {
            $rules['family_key'] = 'required|string|exists:tool_families,family_key';
        }
        $data = $request->validate($rules);

        $moment = $data['inspection_moment'] ?? ToolInspection::MOMENT_PRE_USE;
        [$originType, $originId] = $this->resolveOrigin($data['origin'] ?? null, $data['origin_id'] ?? null);

        $familyKey = $isWildcard ? $data['family_key'] : ($tool->family->family_key ?? null);

        // Puntos AUTORITATIVOS del servidor (no confiar en el cliente para gate/outcome).
        $points = $isWildcard ? $this->pointsForWildcard($familyKey) : $this->pointsFor($tool);
        if ($points->isEmpty()) {
            return back()->withInput()->with('error', 'No hay checklist que ejecutar para esta herramienta.');
        }

        // Cruzar respuestas contra los puntos reales; construir la lista ejecutada + snapshot.
        $answers = $data['answers'];
        $executed = [];
        $snapshot = [];
        foreach ($points as $p) {
            $code = $p->code;
            $ans  = ($answers[$code] ?? null) === 'fail' ? false : (($answers[$code] ?? null) === 'ok' ? true : null);
            if ($ans === null) {
                return back()->withInput()->with('error', "Falta responder el punto {$code}. Una inspección incompleta no se guarda.");
            }
            $stdCodes = $p->std_codes ?? [];
            $executed[] = ['is_gate' => (bool) $p->is_gate, 'outcome_if_fail' => $p->outcome_if_fail, 'answer' => $ans];
            $snapshot[] = [
                'code'            => $code,
                'scope'           => $p->scope,
                'text'            => $p->text_es,
                'is_gate'         => (bool) $p->is_gate,
                'outcome_if_fail' => $p->outcome_if_fail,
                'standards'       => $stdCodes,
                'answer'          => $ans ? 'ok' : 'fail',
            ];
        }

        $result  = InspectionVerdict::compute($executed);

        // Observaciones = fallos condicionados (no-gate) + nota libre.
        $obsLines = [];
        foreach ($snapshot as $s) {
            if ($s['answer'] === 'fail' && ! $s['is_gate']) {
                $obsLines[] = $s['text'];
            }
        }
        if (! empty($data['note'])) {
            $obsLines[] = trim($data['note']);
        }
        $observations = $obsLines ? implode("\n", $obsLines) : null;

        // Snapshots de herramienta e inspector (doctrina de congelamiento).
        $dept = Department::find($data['department_id']);
        $author = auth()->user();
        $cred = ($author && \App\Models\MedicCredential::supportsCredentials()) ? $author->medicCredential : null;

        $toolStandards = $tool->standards()->pluck('regulation_code')->all();

        // Dueño (delta #47): si es crew, se CONGELA su nombre a mostrar; si no, texto libre.
        $ownerId   = $data['owner_user_id'] ?? null;
        $ownerName = trim((string) ($data['owner_name'] ?? ''));
        if ($ownerId) {
            $ownerUser = User::find($ownerId);
            $ownerName = $ownerUser ? User::displayName($ownerUser) : $ownerName;
        }

        // Foto REAL de la unidad: se guarda ANTES de sellar (su RUTA entra en el hash, misma
        // doctrina que las fotos del DSR). El HEIC del iPad ya llega convertido por el navegador;
        // ImageCompressor cubre el resto y NUNCA pierde la evidencia (fallback al original). Si el
        // contenido no es una imagen reconocible, queda sin foto (no rompe el sellado).
        $photoPath = $request->hasFile('tool_photo')
            ? ImageCompressor::store($request->file('tool_photo'), 'tool_inspections/photos')
            : null;

        $payload = [
            'production_id'           => CurrentProduction::id(),
            'shoot_day'               => $this->currentShootDay(),
            'tool_id'                 => $tool->id,
            'tool_code'               => $tool->code,
            'tool_name'               => $tool->name,
            'tool_family_key'         => $familyKey,
            'tool_model'              => $data['tool_model'] ?? null,
            'tool_brand'              => $data['tool_brand'] ?? null,
            'tool_serial'             => $data['tool_serial'] ?? null,
            'tool_photo_path'         => $photoPath,
            'tool_standards_snapshot' => $toolStandards,
            'checklist_mode'          => ($data['checklist_mode'] ?? ToolInspection::MODE_SAFETY),
            'inspection_moment'       => $moment,
            'origin_type'             => $originType,
            'origin_id'               => $originId,
            'checklist_snapshot'      => $snapshot,
            'verdict'                 => $result['verdict'],
            'resolution_path'         => $result['resolution_path'],
            'observations'            => $observations,
            'department_id'           => $dept ? $dept->id : null,
            'department_name'         => $dept ? $dept->name : null,
            'owner_user_id'           => $ownerId ?: null,
            'owner_name'              => $ownerName !== '' ? $ownerName : null,
            'inspector_user_id'       => $author ? $author->id : null,
            // Nombre del que firma: NOMBRE DE CRÉDITOS (User::displayName → ncreditos si existe,
            // si no cae al nombre corto). Como se acredita a la persona en la producción.
            'inspector_name'          => $author ? User::displayName($author) : null,
            'inspector_role'          => $author ? optional($author->getRoleNames())->first() : null,
            'inspector_cedula'        => $cred ? $cred->cedula : null,
            'is_active'               => 1,
        ];

        // Crear y SELLAR de forma ATÓMICA: si el sellado fallara, no queda un acta a
        // medias (sin sello). refresh() antes de firmar para hashear los valores
        // canónicos de BD. El aviso al HOD va DESPUÉS del commit (efecto externo).
        $inspection = DB::transaction(function () use ($payload, $author, $request) {
            $insp = ToolInspection::create($payload);
            $insp->refresh();
            $insp->signDocument($author, $request);
            return $insp;
        });

        // PARO (o "equipo no autorizado" en pre-uso) ⇒ genera la OBLIGACIÓN (A5): un action
        // item con el flujo PDCA que ya existe, ligado al acta, con la vía registrada.
        if ($inspection->isParo()) {
            $this->raiseActionItem($inspection, $data['planned_use_at'] ?? null, $author);
            // …y avisa al HOD del departamento (aunque no esté en sitio).
            $this->notifyDepartmentLead($inspection);
        }

        return redirect()->route('tools.inspection.show', $inspection->uuid)
            ->with('success', 'Inspección registrada y sellada.');
    }

    /* ===================== PASO 4 · EL ACTA (interna, sellada) ===================== */

    public function acta(ToolInspection $inspection)
    {
        $inspection->load('supersededBy', 'origin');
        $actionItem = \Illuminate\Support\Facades\Schema::hasTable('action_items')
            ? $inspection->actionItems()->where('source_field', 'inspection_paro')->latest('id')->first()
            : null;

        // (2026-08-11) EXPORT PDF SERVER-SIDE (?pdf=1) — ADITIVO, antes del return normal. Reusa la
        // MISMA vista/datos y la pasa por Browsershot (Chrome headless) → descarga idéntica a
        // window.print(). Márgenes 0 (el @page Oficio manda). Ver [[browsershot-pdf-pipeline]].
        if (request()->boolean('pdf')) {
            $html = view('inspection.acta', compact('inspection', 'actionItem'))->render();
            return \App\Support\PdfExporter::download($html, 'INSP-' . substr($inspection->uuid, 0, 8), [0, 0, 0, 0]);
        }

        return view('inspection.acta', compact('inspection', 'actionItem')
            + ['pdfUrl' => request()->fullUrlWithQuery(['pdf' => 1])]);
    }

    /* ===================== PASO 3 · DESBLOQUEO DEL PARO ===================== */

    public function unblock(Request $request, ToolInspection $inspection)
    {
        if (! $inspection->isParo()) {
            return back()->with('error', 'Solo un PARO se desbloquea.');
        }
        if ($inspection->unblocked_at !== null) {
            return back()->with('error', 'Este paro ya fue desbloqueado.');
        }

        // Estado + re-sellado viven en el modelo (los comparte el cierre del action item).
        $inspection->unblock(auth()->user(), $request);

        return redirect()->route('tools.inspection.show', $inspection->uuid)
            ->with('success', 'Paro desbloqueado y re-sellado.');
    }

    /* ===================== PARTE B · RETIRO / SUSTITUCIÓN DEL ACTA ===================== */

    /**
     * Retirar un acta (o marcarla sustituida por una reinspección). El sello NO se recalcula
     * ni se re-firma: el documento retirado SIGUE ÍNTEGRO, solo cambió de estado. El verificador
     * público lo mostrará "VÁLIDO PERO RETIRADO", nunca "ALTERADO".
     */
    public function retire(Request $request, ToolInspection $inspection)
    {
        if ($inspection->isRetired()) {
            return back()->with('error', 'Esta acta ya está retirada.');
        }
        $data = $request->validate([
            'retired_reason' => 'nullable|string|max:255',
            'superseded_by'  => 'nullable|string|exists:tool_inspections,uuid',
        ]);
        $author = auth()->user();

        $supersededId = null;
        if (! empty($data['superseded_by'])) {
            $sup = ToolInspection::where('uuid', $data['superseded_by'])->first();
            $supersededId = ($sup && $sup->id !== $inspection->id) ? $sup->id : null; // no auto-sustitución
        }

        // Columnas hash-excluidas → sin re-sellado (retirar no toca la integridad).
        $inspection->is_active        = 0;
        $inspection->retired_at       = now();
        $inspection->retired_by_id    = $author ? $author->id : null;
        $inspection->retired_reason   = $data['retired_reason'] ?? null;
        $inspection->superseded_by_id = $supersededId;
        $inspection->save();

        return redirect()->route('tools.inspection.show', $inspection->uuid)
            ->with('success', 'Acta retirada. El sello sigue siendo válido; solo cambió el estado a retirado.');
    }

    /* ===================== ADMIN · IMAGEN GENÉRICA DEL TIPO ===================== */

    /**
     * Grid para poblar (con el tiempo, fuera del código) la imagen GENÉRICA de referencia de cada
     * TIPO de herramienta. No bloquea nada: mientras no haya imagen, la UI pinta un placeholder.
     * Gate = el mismo `tools.inspect` — la imagen es dato de REFERENCIA (no un documento), y quien
     * inspecciona conoce las herramientas; se puede endurecer a un permiso propio si hace falta.
     */
    public function toolImages(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = Tool::query()->active()->where('is_wildcard', 0)->with('family');
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('aliases', 'like', $like);
            });
        }
        $tools = $query->orderBy('code')->paginate(60)->withQueryString();
        return view('inspection.tool-images', compact('tools', 'q'));
    }

    /** Sube/reemplaza la imagen genérica de un TIPO (borra la anterior para no acumular basura). */
    public function storeToolImage(Request $request, Tool $tool)
    {
        $request->validate([
            'image' => 'required|mimes:jpg,jpeg,png,gif,bmp,svg,webp,heic,heif|heic_ok|max:8192',
        ]);

        $path = ImageCompressor::store($request->file('image'), 'tools/reference');
        if ($path === null) {
            return back()->with('error', __('No se pudo guardar la imagen (formato no reconocido).'));
        }

        if ($tool->image_path) {
            try { \Illuminate\Support\Facades\Storage::disk('public')->delete($tool->image_path); } catch (\Throwable $e) {}
        }
        $tool->image_path = $path;
        $tool->save();

        return back()->with('success', "{$tool->code} — ".__('imagen de referencia actualizada.'));
    }

    /* ============================ Helpers ============================ */

    /** Puertas de origen (A4): valida la clave + que el reporte exista → [clase, id] o [null, null]. */
    private function resolveOrigin($originKey, $originId): array
    {
        if (! $originKey || ! isset(self::ORIGIN_MAP[$originKey]) || ! $originId) {
            return [null, null];
        }
        $class = self::ORIGIN_MAP[$originKey];
        if (! $class::whereKey($originId)->exists()) {
            return [null, null]; // no se inventa el vínculo si el reporte no existe
        }
        return [$class, (int) $originId];
    }

    /** El acta VIGENTE de un tipo, según su régimen. NULL si no hay o caducó. */
    private function vigenteFor(Tool $tool): ?ToolInspection
    {
        $q = ToolInspection::where('tool_id', $tool->id)->latest('id');
        // por jornada: solo vale la del shoot_day actual (los llamados cruzan la medianoche → shoot_day, no fecha).
        if ($tool->inspection_regime === 'por_jornada') {
            $q->where('shoot_day', $this->currentShootDay());
        }
        // por_colocacion y por_evento: no caducan por día → la última vigente vale.
        $acta = $q->first();
        return ($acta && $acta->isVigente()) ? $acta : null;
    }

    /** A5: crea el action item de la obligación (PDCA existente), ligado al acta, con vía y plazo. */
    private function raiseActionItem(ToolInspection $inspection, $plannedUseAt, $author): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('action_items')) {
            return;
        }
        $via = $inspection->resolution_path === ToolInspection::PATH_REPLACE ? 'reemplazo' : 'corrección el mismo día';
        if ($inspection->isPreUse()) {
            // Pre-uso: la fecha compromiso es ANTES DEL USO PREVISTO (si se capturó), no fin del día.
            $due  = $plannedUseAt ? \Carbon\Carbon::parse($plannedUseAt) : now()->endOfDay();
            $text = "Equipo NO AUTORIZADO ({$inspection->tool_code} {$inspection->tool_name}): corregir o sustituir ANTES DEL USO PREVISTO. Vía: {$via}.";
        } else {
            $due  = now()->endOfDay();
            $text = "PARO ({$inspection->tool_code} {$inspection->tool_name}): fuera de uso hasta corregir. Vía: {$via}.";
        }
        $inspection->syncAutoActionItem($text, 'inspection_paro', $author ? $author->id : null, $due);
    }

    /** Puntos del catálogo de un tipo concreto, con sus normas, agrupables por ámbito. */
    private function pointsFor(Tool $tool)
    {
        return $this->hydratePoints(
            DB::table('check_point_tool as ct')
                ->join('tool_check_points as p', 'p.id', '=', 'ct.tool_check_point_id')
                ->where('ct.tool_id', $tool->id)
                ->where('p.is_active', 1)
                ->orderByRaw($this->scopeOrderSql())
                ->orderBy('p.sort_order')
                ->orderBy('p.code')
                ->get(['p.id', 'p.code', 'p.scope', 'p.text_es', 'p.is_gate', 'p.severity',
                        'p.outcome_if_fail', 'p.estimated_seconds', 'p.photo_evidence'])
        );
    }

    /**
     * Comodín (HER-026): "hereda universal y familia". Resuelve = puntos universales
     * + los puntos de ámbito 'familia' que comparten los tipos de la familia elegida.
     */
    private function pointsForWildcard(string $familyKey)
    {
        $rows = DB::table('tool_check_points as p')
            ->where('p.is_active', 1)
            ->where(function ($w) use ($familyKey) {
                $w->whereIn('p.scope', ['universal', 'universal_energizada'])
                  ->orWhere(function ($f) use ($familyKey) {
                      $f->where('p.scope', 'familia')
                        ->whereIn('p.id', function ($sub) use ($familyKey) {
                            $sub->select('ct.tool_check_point_id')
                                ->from('check_point_tool as ct')
                                ->join('tools as t', 't.id', '=', 'ct.tool_id')
                                ->join('tool_families as tf', 'tf.id', '=', 't.tool_family_id')
                                ->where('tf.family_key', $familyKey);
                        });
                  });
            })
            // Sin DISTINCT: se selecciona de tool_check_points con p.id IN (...), así que
            // cada punto sale UNA vez. (DISTINCT + ORDER BY por columna fuera del SELECT
            // rompe con ONLY_FULL_GROUP_BY en MySQL 5.7.)
            ->orderByRaw($this->scopeOrderSql())
            ->orderBy('p.sort_order')
            ->orderBy('p.code')
            ->get(['p.id', 'p.code', 'p.scope', 'p.text_es', 'p.is_gate', 'p.severity',
                    'p.outcome_if_fail', 'p.estimated_seconds', 'p.photo_evidence']);

        return $this->hydratePoints($rows);
    }

    /** Adjunta a cada punto sus normas (regulation_code) para congelarlas en el acta. */
    private function hydratePoints($rows)
    {
        $rows = collect($rows);
        if ($rows->isEmpty()) {
            return $rows;
        }
        $ids = $rows->pluck('id')->all();
        $stds = DB::table('check_point_standard as cs')
            ->join('safety_standards as s', 's.id', '=', 'cs.safety_standard_id')
            ->whereIn('cs.tool_check_point_id', $ids)
            ->get(['cs.tool_check_point_id as pid', 's.regulation_code'])
            ->groupBy('pid');

        return $rows->map(function ($p) use ($stds) {
            $p->std_codes = isset($stds[$p->id]) ? $stds[$p->id]->pluck('regulation_code')->values()->all() : [];
            return $p;
        });
    }

    /** Orden de ámbitos: universal → energizada → familia → tipo → actividad. */
    private function scopeOrderSql(): string
    {
        return "FIELD(p.scope,'universal','universal_energizada','familia','tipo','actividad')";
    }

    private function currentShootDay()
    {
        try {
            return ProductionCalendar::shootDayFor(now()->toDateString());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Aviso al HOD del departamento (blindado: nunca rompe el guardado del acta). */
    private function notifyDepartmentLead(ToolInspection $inspection): void
    {
        try {
            $recipients = InvolvedResolver::leadRecipientsForDepartment($inspection->department_id);
            if (empty($recipients)) {
                return;
            }
            // Redacción según el momento: pre-uso = "equipo no autorizado" (hay ventana);
            // en uso = "PARO" (detiene el set). El cálculo del veredicto es el mismo.
            $immediate = $inspection->paroIsImmediate();
            $headline  = $immediate ? 'PARO de herramienta' : 'Equipo NO AUTORIZADO';
            $base = [
                'folio'       => $inspection->folio(),
                'tool_name'   => $inspection->tool_name,
                'tool_model'  => $inspection->tool_model,
                'verdict'     => $inspection->verdict,
                'resolution'  => $inspection->resolution_path,
                'department'  => $inspection->department_name,
                'inspector'   => $inspection->inspector_name,
                'immediate'   => $immediate,
                'moment'      => $inspection->inspection_moment,
                'subject'     => $headline.' — '.$inspection->department_name,
            ];
            foreach ($recipients as $r) {
                try {
                    Mail::send('correos.tool-inspection-paro', array_merge($base, ['recipient_name' => $r['name']]), function ($m) use ($r, $base) {
                        $m->to($r['email'], $r['name']);
                        $m->subject($base['subject']);
                    });
                } catch (\Throwable $e) {
                    Log::warning('notifyDepartmentLead(paro): fallo enviando a '.$r['email'].' — '.$e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('notifyDepartmentLead(paro): '.$e->getMessage());
        }
    }
}
