<?php

namespace App\Http\Controllers;

use App\Models\unsafecond;
use App\Models\HazardEvent; // (2026-07-13) Catálogo único de eventos posibles (evento→norma).
use App\Http\Requests\StoreUnsafeConditionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema; // (2026-07-13) guard defensivo para cargar hazard_events.
use Illuminate\Support\Facades\Log; // breadcrumb: para Log::error en el try/catch (patrón de locationController)
use Illuminate\Support\Facades\Mail; // (2026-07-24) aviso al jefe directo del involucrado
use App\Support\Features; // (2026-07-14) Pilar 1: flag progressive_capture (Fase 1 vs estricta).

class unsafecondNotificationController extends Controller
{
    /**
     * (2026-07-14) Catálogos compartidos por create() y edit() (DRY): normas,
     * departamentos y el catálogo único de eventos (carga defensiva sin el SQL).
     *
     * @return array
     */
    private function catalogs()
    {
        // (2026-06-28) Catálogo normativo: mismo dropdown que usa el Daily Report.
        // Se pasa $standards a la vista para auto-etiquetar la norma aplicable.
        $standards = \App\Models\SafetyStandard::orderBy('category_name', 'asc')->get();
        // (2026-07-13) Coherencia: catálogo de departamentos para el nuevo campo
        // "Persona o departamento involucrado" (involved_department).
        $departments = \App\Models\Department::orderBy('name')->get();
        // (2026-07-13) Catálogo único de eventos posibles (agrupados por contexto).
        // Reemplaza el select category_name + el multiselect standards[]: al elegir un
        // evento, applyHazardEvent() etiqueta su norma y adjunta las normas al pivote.
        // Carga DEFENSIVA: si el SQL aún no está en PROD, queda colección vacía (no-op).
        $hazardEvents = Schema::hasTable('hazard_events')
            ? HazardEvent::active()->with('standards')->orderBy('context')->orderBy('sort_order')->orderBy('name_es')->get()
            : collect();

        // (2026-07-24) PASO 2/2 — crew (involucrado + responsable de la acción correctiva) y los
        // reportes HERMANOS (Actos inseguros, para el enlace real). Defensivo (colecciones vacías).
        $crew = collect();
        $siblings = collect();
        try {
            $prod = \App\Support\CurrentProduction::get();
            if ($prod) {
                $crew = $prod->members()->orderBy('users.name')->get();
            }
        } catch (\Throwable $e) {
        }
        try {
            $siblings = \App\Models\hazardnotification::orderBy('id', 'desc')->limit(200)
                ->get(['id', 'name_loc', 'description_hazard_unsafe_act', 'date_observed']);
        } catch (\Throwable $e) {
        }

        return compact('standards', 'departments', 'hazardEvents', 'crew', 'siblings');
    }

    public function create()
    {
        // (2026-07-14) Pilar 1: la MISMA vista sirve para crear y editar. En creación
        // $isEdit=false y $report=null (la vista cae a old() para el prefill).
        return View::make('admin.unsafecondnotification', $this->catalogs() + [
            'isEdit' => false,
            'report' => null,
        ]);
    }

    public function store(StoreUnsafeConditionRequest $request)
    {
        // (2026-07-09) Validación ENDURECIDA movida a StoreUnsafeConditionRequest: campos
        // clave required server-side, GPS obligatorio y `standards` validado contra el
        // catálogo (cierra la brecha "todo nullable"). validated() ya trae solo lo esperado.
        $data = $request->validated();

        // (2026-07-13) Catálogo único de eventos: el snapshot de la norma
        // (regulation_badge/regulation_code) ya NO se resuelve por category_name aquí;
        // lo hace $report->applyHazardEvent($hazard_event_id) DESPUÉS de crear el reporte
        // (etiqueta la norma principal del evento y adjunta todas al pivote standardables).
        // category_name / hazard_event_id NO son columnas del array de create(): se quitan.
        unset($data['category_name'], $data['hazard_event_id']);

        // (2026-07-07) GPS: guardado defensivo — si el OWNER aún no aplicó el ALTER
        // (database/owner-apply/2026-07-07-safety-gps.sql), se quitan para no romper create().
        if (!\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'latitude')) {
            unset($data['latitude'], $data['longitude'], $data['gps_address']);
        }

        // (2026-07-09) 'standards' es relación N:M (pivote standardables), NO columna:
        // se quita del array; se sincroniza después de create().
        unset($data['standards']);

        // (2026-07-09) Matriz 5×5: si el owner aún no aplicó las columnas de ejes, se quitan
        // (el trait CalculatesRiskMatrix sólo calcula risk_level cuando existen).
        if (!\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'likelihood')) {
            unset($data['likelihood'], $data['consequence']);
        }

        // (2026-07-12) Módulo 11: justificación de ubicación manual — guardado defensivo.
        // validated() ya puede traer la clave; si PROD aún no aplicó el ALTER, se quita para
        // no romper create() (Eloquent NO ignora atributos fillable sin columna: falla el INSERT).
        if (!\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'manual_location_justification')) {
            unset($data['manual_location_justification']);
        }

        // (2026-07-13) Coherencia: persona o departamento involucrado — guardado defensivo.
        // La columna existe en LOCAL pero PROD aún no tiene el ALTER; se quita para no romper create().
        if (!\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'involved_department')) {
            unset($data['involved_department']);
        }

        // breadcrumb: los inputs de archivo NO son columnas; se quitan del array validado antes de create()
        // (las columnas reales son main_image_path / additional_images_paths, que seteamos abajo).
        unset($data['main_image'], $data['additional_images']);

        // (2026-07-24) PASO 2/2 — responsable/fecha de la acción correctiva NO son columnas del
        // reporte (cuelgan del ActionItem); columnas nuevas con guardado defensivo; vínculo con la
        // locación autoritativa (Scouting) resuelto server-side desde el GPS.
        unset($data['corrective_owner_id'], $data['corrective_due_date']);
        // (2026-07-24) `involved_user_id` NO va en la Condición (se ancla al lugar, no a una persona).
        foreach (['related_hazard_id', 'is_recurrent', 'scouting_report_id'] as $newCol) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', $newCol)) {
                unset($data[$newCol]);
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'scouting_report_id')) {
            $scoutId = \App\Support\ScoutingLocator::nearestId($request->input('latitude'), $request->input('longitude'));
            if ($scoutId !== null) {
                $data['scouting_report_id'] = $scoutId;
            }
        }

        // Procesar la imagen principal
        if ($request->hasFile('main_image')) {
            $image = \App\Support\ImageCompressor::normalizeForUpload($request->file('main_image'));
            // breadcrumb (nombre de archivo): antes time().'_main' (predecible/colisionable). Ahora único con uniqid().
            $filename = time() . '_' . uniqid() . '_main.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('unsafe_images', $filename, 'public');
            $data['main_image_path'] = Storage::url($path);
        }

        // Procesar las imágenes adicionales
        if ($request->hasFile('additional_images')) {
            $additionalImagePaths = [];
            foreach ($request->file('additional_images') as $image) {
                $image = \App\Support\ImageCompressor::normalizeForUpload($image);
                $filename = time() . '_additional_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('unsafe_images', $filename, 'public');
                $additionalImagePaths[] = Storage::url($path);
            }
            // breadcrumb (doble json_encode): antes json_encode($paths) + cast 'array' en el modelo = doble codificación.
            // Ahora se asigna el ARRAY directo; el cast del modelo lo serializa una sola vez.
            $data['additional_images_paths'] = $additionalImagePaths;
        }

        // AUTOFIRMA (sistema cerrado): autor + fecha del servidor, NO del formulario → no falseable.
        $data['make_by']   = auth()->user()->name;
        $data['make_date'] = now()->toDateString();
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'created_by_id')) {
            $data['created_by_id'] = auth()->id();
        }

        // (2026-06-28) Lectura rápida: se fijan risk_level + action_status (default 'Abierto').
        // Guardado defensivo: si el OWNER aún no aplicó el ALTER, el form sigue funcionando.
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'action_status')) {
            $data['risk_level']    = $request->input('risk_level');
            $data['action_status'] = $request->input('action_status') ?: 'Abierto';
        }

        // (2026-07-14) Pilar 1 — captura en 2 fases: si la matriz 5×5 llega INCOMPLETA
        // (falta Probabilidad y/o Consecuencia) y la captura progresiva está encendida,
        // se marca pending_compliance=1 para que el back-office la termine en Fase 2.
        // Con el flag apagado (reglas estrictas) queda en 0. Guard defensivo por columna.
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'pending_compliance')) {
            $matrixComplete = $request->filled('likelihood') && $request->filled('consequence');
            $data['pending_compliance'] = (Features::enabled('progressive_capture') && !$matrixComplete) ? 1 : 0;
        }

        // breadcrumb (try/catch): patrón de locationController — antes create() sin protección (un fallo = error 500
        // crudo). Ahora se registra y se vuelve al form con los datos y un mensaje limpio.
        try {
            $report = unsafecond::create($data);

            // (2026-07-13) Catálogo único de eventos: guarda hazard_event_id, copia el
            // badge/código de la norma PRINCIPAL del evento (snapshot regulation_*) y
            // adjunta TODAS las normas del evento al pivote standardables. Reemplaza el
            // viejo syncStandards(standards[]) del multiselect. DEFENSIVO (no-op sin SQL).
            $report->applyHazardEvent($request->input('hazard_event_id'));

            // (2026-07-09) Motor PDCA: "Acciones correctivas" genera automáticamente una
            // acción rastreable con SLA. (2026-07-24) Con responsable + fecha compromiso del form.
            $report->syncAutoActionItem(
                $request->input('corrective_action'),
                'corrective_action',
                $request->input('corrective_owner_id') ?: null,
                $request->input('corrective_due_date') ?: null
            );

            // (2026-07-23) Sellado AL CREAR (homologado con Injury/DSR/Scouting): el documento
            // consta desde el momento del reporte, aunque siga "Abierto". refresh() relee el
            // registro para calcular el hash sobre los tipos reales de la BD (evita divergencia
            // int/string). signDocument() es defensivo (no-op sin la tabla de firmas).
            $report->refresh();
            $report->signDocument(auth()->user(), $request);

            // (2026-07-24) La Condición avisa al RESPONSABLE de la acción correctiva (algo que hay
            // que REPARAR), NO al jefe de una persona (eso es del Acto). Blindado: no rompe el store.
            $this->notifyCorrectiveResponsible($report, $request->input('corrective_owner_id'));
        } catch (\Exception $e) {
            Log::error('Error al guardar la notificación de condición insegura: ' . $e->getMessage());
            return back()->withInput()->with('error', 'No se pudo guardar la notificación. Intenta de nuevo.');
        }

        // breadcrumb: antes redirigía a 'unsafenotifications.store' (ruta POST = antipatrón, recargar reintenta el POST). Ahora va al LISTADO (index).
        return Redirect::route('unsafenotifications.index')
            ->with('success', 'Notificación de peligro creada exitosamente.');
    }

    /**
     * (2026-07-14) Pilar 1 — Fase 2 (back-office): editar. Reutiliza la MISMA vista de
     * crear en modo edición ($isEdit=true, $report). El <form> apunta a update() con PUT
     * y prefila con old()/$report->campo.
     */
    public function edit($id)
    {
        $report = unsafecond::findOrFail($id);
        // (2026-07-24) Para prellenar el responsable/fecha de la acción correctiva en el form.
        if (\Illuminate\Support\Facades\Schema::hasTable('action_items')) {
            $report->load('actionItems');
        }
        return View::make('admin.unsafecondnotification', $this->catalogs() + [
            'isEdit' => true,
            'report' => $report,
        ]);
    }

    /**
     * (2026-07-14) Pilar 1 — Fase 2 (back-office): completar la carga "burocrática".
     * Valida SIEMPRE el set ESTRICTO (compliance completo), sin importar el flag; corre
     * applyHazardEvent + syncAutoActionItem igual que store(); pone pending_compliance=0
     * cuando la matriz 5×5 está completa. NO toca la AUTOFIRMA original (make_by /
     * created_by_id / make_date se preservan).
     */
    public function update(Request $request, $id)
    {
        $report = unsafecond::findOrFail($id);

        // (2026-07-23) Blindaje anti-"solo espacios" (Fase 2 no pasa por el FormRequest, así que
        // se replica el trim de prepareForValidation): "   " → "" → dispara el obligatorio.
        // NO recorta involved_department (regla owner: no tocar el campo involucrado en este paso).
        foreach (['production_name', 'name_loc', 'location_unsafe_cond', 'description_unsafe_cond'] as $f) {
            if (is_string($request->input($f))) {
                $request->merge([$f => trim($request->input($f))]);
            }
        }

        // Fase 2: validación COMPLETA (reglas estrictas), independientemente del flag.
        $data = $request->validate(
            StoreUnsafeConditionRequest::strictRules(),
            (new StoreUnsafeConditionRequest)->messages()
        );

        // (2026-07-13) El snapshot de la norma lo resuelve applyHazardEvent() tras guardar;
        // category_name / hazard_event_id NO son columnas del array de update(): se quitan.
        unset($data['category_name'], $data['hazard_event_id']);

        // (2026-07-07) GPS: guardado defensivo si el ALTER aún no está en PROD.
        if (!\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'latitude')) {
            unset($data['latitude'], $data['longitude'], $data['gps_address']);
        }

        // (2026-07-09) 'standards' es relación N:M (pivote), NO columna.
        unset($data['standards']);

        // (2026-07-09) Matriz 5×5: sin columnas de ejes, se quitan (el trait sólo calcula si existen).
        if (!\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'likelihood')) {
            unset($data['likelihood'], $data['consequence']);
        }

        // (2026-07-12) Módulo 11: justificación de ubicación manual — guardado defensivo.
        if (!\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'manual_location_justification')) {
            unset($data['manual_location_justification']);
        }

        // (2026-07-13) Coherencia: persona o departamento involucrado — guardado defensivo.
        if (!\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'involved_department')) {
            unset($data['involved_department']);
        }

        // Los inputs de archivo NO son columnas; se quitan antes de update().
        unset($data['main_image'], $data['additional_images']);

        // (2026-07-24) PASO 2/2 — igual que store(): responsable/fecha fuera del array, guardado
        // defensivo de columnas nuevas, y vínculo con el Scouting desde el GPS.
        unset($data['corrective_owner_id'], $data['corrective_due_date']);
        // (2026-07-24) `involved_user_id` NO va en la Condición (se ancla al lugar, no a una persona).
        foreach (['related_hazard_id', 'is_recurrent', 'scouting_report_id'] as $newCol) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', $newCol)) {
                unset($data[$newCol]);
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'scouting_report_id')) {
            $scoutId = \App\Support\ScoutingLocator::nearestId($request->input('latitude'), $request->input('longitude'));
            if ($scoutId !== null) {
                $data['scouting_report_id'] = $scoutId;
            }
        }

        // Reemplazo de la imagen principal (sólo si suben una nueva; si no, se conserva).
        if ($request->hasFile('main_image')) {
            $image = \App\Support\ImageCompressor::normalizeForUpload($request->file('main_image'));
            $filename = time() . '_' . uniqid() . '_main.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('unsafe_images', $filename, 'public');
            $data['main_image_path'] = Storage::url($path);
        }

        // Reemplazo de imágenes adicionales (sólo si suben nuevas).
        if ($request->hasFile('additional_images')) {
            $additionalImagePaths = [];
            foreach ($request->file('additional_images') as $image) {
                $image = \App\Support\ImageCompressor::normalizeForUpload($image);
                $filename = time() . '_additional_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('unsafe_images', $filename, 'public');
                $additionalImagePaths[] = Storage::url($path);
            }
            $data['additional_images_paths'] = $additionalImagePaths;
        }

        // AUTOFIRMA: NO se toca en update() — make_by / make_date / created_by_id
        // se preservan del alta original (registro fiable de quién/cuándo capturó).

        // (2026-06-28) Lectura rápida: risk_level + action_status. Guardado defensivo.
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'action_status')) {
            $data['risk_level']    = $request->input('risk_level');
            $data['action_status'] = $request->input('action_status') ?: 'Abierto';
        }

        // (2026-07-14) Pilar 1 — Fase 2 cierra el compliance: si la matriz 5×5 quedó
        // completa (Probabilidad + Consecuencia), pending_compliance=0; si no, sigue en 1.
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'pending_compliance')) {
            $matrixComplete = $request->filled('likelihood') && $request->filled('consequence');
            $data['pending_compliance'] = $matrixComplete ? 0 : 1;
        }

        try {
            // (2026-07-24) Responsable de la acción correctiva ANTES del sync, para avisarle solo si
            // es NUEVO o CAMBIÓ (no re-spamea al re-guardar sin cambiarlo).
            $respOld = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('action_items')) {
                $aiPrev = $report->actionItems()->where('source_field', 'corrective_action')->first();
                $respOld = $aiPrev ? $aiPrev->owner_id : null;
            }
            $respNew = $request->input('corrective_owner_id');

            $report->update($data);

            // (2026-07-13) Snapshot de norma + N:M (igual que store).
            $report->applyHazardEvent($request->input('hazard_event_id'));

            // (2026-07-09) Motor PDCA: acción correctiva rastreable (con responsable + fecha).
            $report->syncAutoActionItem(
                $request->input('corrective_action'),
                'corrective_action',
                $request->input('corrective_owner_id') ?: null,
                $request->input('corrective_due_date') ?: null
            );

            // (2026-07-23) Re-sello HONESTO: sólo si el contenido cambió de verdad (hash_equals),
            // sobre el registro releído (refresh evita el falso positivo por int/string).
            $this->resealIfChanged($report, $request);

            // (2026-07-24) Avisa al RESPONSABLE de la acción correctiva SOLO si es nuevo o cambió.
            if ($respNew && (int) $respNew !== (int) $respOld) {
                $this->notifyCorrectiveResponsible($report, $respNew);
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar la notificación de condición insegura: ' . $e->getMessage());
            return back()->withInput()->with('error', 'No se pudo actualizar la notificación. Intenta de nuevo.');
        }

        return Redirect::route('unsafenotifications.show', $report->id)
            ->with('success', 'Notificación de condición insegura actualizada.');
    }

   public function index()
    {
        // breadcrumb: antes 'unsafecond::all()' (sin paginar). Ahora paginado de 15 en 15, más reciente primero.
        $unsafenotifications = unsafecond::orderBy('id', 'desc')->paginate(15);
        return View::make('admin.unsafeconds', compact('unsafenotifications'));
    }

    public function show($id)
    {
        $unsafenotification = unsafecond::findOrFail($id);

        // (2026-06-28) Catálogo normativo: si la fila trae un regulation_code, se resuelve
        // la URL del boletín desde safety_standards (reference_url vive ahí, NO en la tabla
        // del reporte). Defensivo: si la columna aún no existe o no hay norma, queda en null.
        $standardUrl = null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'regulation_code') && \Illuminate\Support\Facades\Schema::hasColumn('safety_standards', 'reference_url') && $unsafenotification->regulation_code) {
            $standardUrl = \App\Models\SafetyStandard::where('regulation_code', $unsafenotification->regulation_code)->value('reference_url');
        }

        // (2026-07-09) Normas vinculadas (N:M) y acciones correctivas (PDCA) — carga defensiva.
        if (\Illuminate\Support\Facades\Schema::hasTable('standardables')) {
            $unsafenotification->load('standards');
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('action_items')) {
            // (2026-07-24) owner + verifiedBy para imprimir responsable y evidencia de cierre.
            $unsafenotification->load(['actionItems.owner', 'actionItems.verifiedBy']);
        }

        // (2026-07-24) PASO 2/2 — relaciones nuevas para el documento (carga defensiva).
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'related_hazard_id')) {
            $unsafenotification->load('relatedHazard');
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'scouting_report_id')) {
            $unsafenotification->load('scoutingReport');
        }

        // (2026-08-11) EXPORT PDF SERVER-SIDE (?pdf=1) — ADITIVO, antes del return normal. Reusa la
        // MISMA vista/datos y la pasa por Browsershot (Chrome headless) → descarga idéntica a
        // window.print(). Márgenes 0 (el @page Oficio manda). Ver [[browsershot-pdf-pipeline]].
        if (request()->boolean('pdf')) {
            $html = View::make('admin.unsafecond', compact('unsafenotification', 'standardUrl'))->render();
            return \App\Support\PdfExporter::download($html, 'UNS-' . $unsafenotification->id, [0, 0, 0, 0]);
        }

        // (2026-07-24) La Condición NO tiene involucrado: se ancla al lugar. No se calcula contexto
        // de persona ni se pasa a la vista.
        return View::make('admin.unsafecond', compact('unsafenotification', 'standardUrl'))
            ->with('pdfUrl', request()->fullUrlWithQuery(['pdf' => 1]));
    }

    // (2026-06-28) Cierre del ciclo: actualizar el estado de la acción correctiva
    // después del reporte (Abierto → En proceso → Cerrado). Solo managers (permiso en la ruta).
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['action_status' => 'required|in:Abierto,En proceso,Cerrado']);
        $n = unsafecond::findOrFail($id);
        // (2026-07-09) Bloqueo de estado PDCA: no se puede "Cerrar" con acciones abiertas.
        if ($request->input('action_status') === 'Cerrado') {
            $n->assertActionItemsClosed('action_status');
        }
        // Guardado defensivo: si el OWNER aún no aplicó el ALTER, no truena.
        if (\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'action_status')) {
            $n->action_status = $request->input('action_status');
            $n->save();

            // (2026-07-23) Cambiar el estado ES un cambio real de contenido → re-sella. Antes
            // sólo se firmaba al pasar a 'Cerrado' y SIN hash_equals (apilaba firmas al re-cerrar).
            // Ahora resealIfChanged() compara el hash y sólo firma si de verdad cambió.
            $this->resealIfChanged($n, $request);
        }
        return back()->with('success', 'Estado de la acción correctiva actualizado.');
    }

    /**
     * (2026-07-23) Re-sello idempotente homologado con Injury/DSR/Scouting. refresh() relee el
     * registro persistido (hash sobre los tipos reales de la BD, evita la divergencia int/string);
     * hash_equals contra la última firma evita apilar sellos idénticos. Defensivo: no-op si aún no
     * existe la tabla de firmas.
     *
     * @param  \App\Models\unsafecond  $doc
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    private function resealIfChanged($doc, $request)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('digital_signatures')) {
            return;
        }
        $doc->refresh();
        $nuevoHash = $doc->computeDocumentHash();
        $ultima    = $doc->signatures()->latest('id')->first();
        if ($ultima === null || ! hash_equals((string) $ultima->document_hash, $nuevoHash)) {
            $doc->signDocument(auth()->user(), $request);
        }
    }

    /**
     * (2026-07-24) Avisa por correo al RESPONSABLE de la acción correctiva de una condición insegura
     * (algo que hay que REPARAR). Es la lógica de alerta de la CONDICIÓN, distinta a la del ACTO (que
     * avisa al jefe del involucrado). Blindado: cualquier fallo se loguea y se traga → nunca rompe el
     * guardado. No-op si no hay responsable asignado.
     *
     * @param  \App\Models\unsafecond $report
     * @param  int|null               $ownerId  responsable de la acción correctiva (users.id)
     * @return void
     */
    private function notifyCorrectiveResponsible($report, $ownerId)
    {
        try {
            if (empty($ownerId)) {
                return;
            }
            $user = \App\Models\User::find($ownerId);
            if (!$user) {
                return;
            }
            $email = trim((string) $user->email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $branding  = \App\Support\Branding::all();
            $brandName = isset($branding['brand_name']) ? $branding['brand_name'] : 'CrewCare';
            $when = $report->date_observed
                ? (is_object($report->date_observed) ? $report->date_observed->format('d/m/Y') : (string) $report->date_observed)
                : '';
            // La acción correctiva y su fecha compromiso viven en el ActionItem ya sincronizado.
            $due = '';
            $corrective = $report->corrective_action;
            if (\Illuminate\Support\Facades\Schema::hasTable('action_items')) {
                $ai = $report->actionItems()->where('source_field', 'corrective_action')->first();
                if ($ai) {
                    $corrective = $ai->description ?: $corrective;
                    $due = $ai->due_date ? \Carbon\Carbon::parse($ai->due_date)->format('d/m/Y') : '';
                }
            }
            $url = \Illuminate\Support\Facades\Route::has('unsafenotifications.show')
                ? route('unsafenotifications.show', $report->id) : null;
            $subject = 'Acción correctiva asignada — ' . $brandName;
            $payload = [
                'branding'       => $branding,
                'subject'        => $subject,
                'recipient_name' => $user->name,
                'type_label'     => 'Condición Insegura',
                'what'           => $report->description_unsafe_cond,
                'corrective'     => $corrective,
                'due'            => $due,
                'when'           => $when,
                'where'          => $report->name_loc ?: $report->location_unsafe_cond,
                'production'     => $report->production_name,
                'url'            => $url,
            ];
            Mail::send('correos.corrective-assignment', $payload, function ($m) use ($email, $user, $subject) {
                $m->to($email, $user->name);
                $m->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('notifyCorrectiveResponsible(unsafecond): ' . $e->getMessage());
        }
    }
}
