<?php

namespace App\Http\Controllers;

use App\Models\HazardNotification;
use App\Models\HazardEvent; // (2026-07-13) Catálogo ÚNICO de eventos posibles (homologación de reportes).
use App\Http\Requests\StoreHazardRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema; // (2026-07-13) Carga defensiva del catálogo de eventos.
use Illuminate\Support\Facades\Log; // (2026-06-28) try/catch en store() replica patrón de locationController
use Illuminate\Support\Facades\Mail; // (2026-07-24) aviso al jefe directo del involucrado

class HazardNotificationController extends Controller
{
    /**
     * Muestra el formulario para crear una nueva notificación de peligro.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        // (2026-06-28) Catálogo normativo legado: se conserva $standards (no estorba;
        // el selector nuevo ya no lo usa, pero la vista sigue recibiéndolo sin problema).
        $standards = \App\Models\SafetyStandard::orderBy('category_name', 'asc')->get();

        // (2026-07-13) Catálogo ÚNICO de eventos posibles (agrupados por contexto).
        // Carga DEFENSIVA: si PROD aún no aplicó el SQL, queda colección vacía y el
        // formulario sigue funcionando (el picker degrada a solo "Sin evento / No aplica").
        $hazardEvents = Schema::hasTable('hazard_events')
            ? HazardEvent::active()->with('standards')->orderBy('context')->orderBy('sort_order')->orderBy('name_es')->get()
            : collect();

        // (2026-07-14) Pilar 1: la vista se reutiliza en modo edición (Fase 2). En create()
        // arranca en modo alta ($isEdit=false, sin $report a prellenar).
        return View::make('admin.hazardnotification', compact('standards', 'hazardEvents'))
            ->with('isEdit', false)
            ->with('report', null)
            ->with($this->step2Catalogs());
    }

    /**
     * (2026-07-24) PASO 2/2 — catálogos NUEVOS para el formulario: el crew (para el involucrado y
     * el responsable de la acción correctiva) y los reportes HERMANOS (Condiciones inseguras, para
     * el enlace real). Defensivo: si falta la producción/tabla, quedan colecciones vacías.
     *
     * @return array
     */
    private function step2Catalogs()
    {
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
            $siblings = \App\Models\unsafecond::orderBy('id', 'desc')->limit(200)
                ->get(['id', 'name_loc', 'description_unsafe_cond', 'date_observed']);
        } catch (\Throwable $e) {
        }
        return ['crew' => $crew, 'siblings' => $siblings];
    }

    /**
     * Almacena una nueva notificación de peligro en la base de datos.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreHazardRequest $request)
    {
        // (2026-07-09) Validación ENDURECIDA movida a StoreHazardRequest: campos clave
        // required server-side, GPS obligatorio y `standards` validado contra el catálogo
        // (cierra la brecha "obligatoriedad solo del lado cliente"). validated() ya trae
        // solo las claves esperadas.
        $data = $request->validated();

        // (2026-07-13) Catálogo ÚNICO de eventos: el snapshot de la norma (regulation_badge /
        // regulation_code) YA NO se resuelve aquí por category_name. Ahora lo hace
        // $report->applyHazardEvent($hazard_event_id) DESPUÉS de create() (ver más abajo):
        // copia la norma principal del evento y adjunta todas sus normas al pivote standardables.

        // (2026-07-07) GPS: guardado defensivo — si el OWNER aún no aplicó el ALTER
        // (database/owner-apply/2026-07-07-safety-gps.sql), se quitan para no romper create().
        if (!\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'latitude')) {
            unset($data['latitude'], $data['longitude'], $data['gps_address']);
        }

        // (2026-07-13) Catálogo ÚNICO de eventos: 'hazard_event_id' es columna snapshot,
        // pero se maneja DESPUÉS de create() vía applyHazardEvent() (que además copia la
        // norma y adjunta el pivote). Se quita del array de create() con guardado defensivo:
        // si PROD aún no aplicó el ALTER, el trait es no-op y no rompe el INSERT.
        unset($data['hazard_event_id']);
        // (2026-07-13) 'standards' ya no se envía (lo sustituye el evento del catálogo);
        // por compatibilidad, si llegara a colarse en validated() se descarta.
        unset($data['standards']);

        // (2026-07-09) Matriz 5×5: si el owner aún no aplicó las columnas de ejes, se quitan
        // (el trait CalculatesRiskMatrix sólo calcula risk_level cuando existen).
        if (!\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'likelihood')) {
            unset($data['likelihood'], $data['consequence']);
        }

        // (2026-07-12) Módulo 11: justificación de ubicación manual — guardado defensivo.
        // validated() ya puede traer la clave; si PROD aún no aplicó el ALTER, se quita para
        // no romper create() (Eloquent NO ignora atributos fillable sin columna: falla el INSERT).
        if (!\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'manual_location_justification')) {
            unset($data['manual_location_justification']);
        }

        // Los inputs de archivo NO son columnas de la tabla: se quitan del array que va a create().
        // (2026-06-28) main_image / additional_images son ficheros; las columnas son
        // main_image_path / additional_images_paths.
        unset($data['main_image'], $data['additional_images']);

        // (2026-07-24) PASO 2/2 — responsable/fecha de la acción correctiva NO son columnas del
        // reporte (cuelgan del ActionItem): se sacan del array antes de create().
        unset($data['corrective_owner_id'], $data['corrective_due_date']);
        // Columnas NUEVAS con guardado defensivo (si el owner aún no aplicó el SQL, se quitan).
        foreach (['involved_user_id', 'related_unsafecond_id', 'human_factor', 'scouting_report_id'] as $newCol) {
            if (!Schema::hasColumn('hazardnotifications', $newCol)) {
                unset($data[$newCol]);
            }
        }
        // Vínculo con la LOCACIÓN autoritativa (Scouting) resuelto server-side desde el GPS. Sin
        // match (o sin GPS) queda NULL y la locación se captura a mano (name_loc, ya obligatoria).
        if (Schema::hasColumn('hazardnotifications', 'scouting_report_id')) {
            $scoutId = \App\Support\ScoutingLocator::nearestId($request->input('latitude'), $request->input('longitude'));
            if ($scoutId !== null) {
                $data['scouting_report_id'] = $scoutId;
            }
        }

        // Procesar la imagen principal
        if ($request->hasFile('main_image')) {
            $image = \App\Support\ImageCompressor::normalizeForUpload($request->file('main_image'));
            // (2026-06-28) nombre único: antes time().'_main.' colisionaba si dos uploads
            // caían en el mismo segundo. Se añade uniqid().
            $filename = time() . '_' . uniqid() . '_main.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('hazard_images', $filename, 'public');
            $data['main_image_path'] = Storage::url($path);
        }

        // Procesar las imágenes adicionales
        if ($request->hasFile('additional_images')) {
            $additionalImagePaths = [];
            foreach ($request->file('additional_images') as $image) {
                $image = \App\Support\ImageCompressor::normalizeForUpload($image);
                $filename = time() . '_additional_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('hazard_images', $filename, 'public');
                $additionalImagePaths[] = Storage::url($path);
            }
            // (2026-06-28) BUG: antes json_encode() + el modelo castea 'additional_images_paths'=>'array'
            // => doble codificación. Se asigna el ARRAY directo; el cast lo serializa una sola vez.
            $data['additional_images_paths'] = $additionalImagePaths;
        }

        // AUTOFIRMA (sistema cerrado): autor + fecha del servidor, NO del formulario → no falseable.
        // Se coloca justo antes de create() para que ningún unset() previo lo borre.
        $data['make_by']   = auth()->user()->name;
        $data['make_date'] = now()->toDateString();
        if (\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'created_by_id')) {
            $data['created_by_id'] = auth()->id();
        }

        // (2026-06-28) Chips de severidad/estado: SON capturados por el usuario (NO autofirma).
        // Salvaguarda para despliegues frescos: sólo se asignan si la columna ya existe.
        // action_status arranca en 'Abierto' por defecto (cierre del ciclo se hace después).
        if (\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'action_status')) {
            $data['risk_level']    = $request->input('risk_level');
            $data['action_status'] = $request->input('action_status') ?: 'Abierto';
        }

        // (2026-07-14) Pilar 1 — captura en 2 fases. Con progressive_capture ON, si la matriz
        // 5×5 llega incompleta (falta likelihood o consequence) el reporte nace "pendiente de
        // compliance" (pending_compliance=1) para completarse en back-office (Fase 2 / edit()).
        // Con el flag OFF (modo estricto) el reporte nace completo (=0). Guard hasColumn: si
        // PROD aún no aplicó el ALTER, se omite y no rompe el INSERT.
        if (\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'pending_compliance')) {
            if (\App\Support\Features::enabled('progressive_capture')) {
                $matrixComplete = $request->filled('likelihood') && $request->filled('consequence');
                $data['pending_compliance'] = $matrixComplete ? 0 : 1;
            } else {
                $data['pending_compliance'] = 0;
            }
        }

        // (2026-06-28) try/catch replica patrón de locationController@store:
        // antes un fallo de create() reventaba sin manejo; ahora se loguea y se vuelve con input.
        try {
            $report = HazardNotification::create($data);

            // (2026-07-13) Catálogo ÚNICO de eventos: al elegir un evento (a) guarda
            // hazard_event_id, (b) copia badge/código de su norma principal a
            // regulation_badge/regulation_code y (c) adjunta TODAS sus normas al pivote
            // standardables (syncStandards interno). DEFENSIVO: no-op si el SQL no está.
            $report->applyHazardEvent($request->input('hazard_event_id'));

            // (2026-07-09) Motor PDCA: la "Sugerencia para la acción correctiva" genera
            // automáticamente una acción rastreable con SLA.
            // (2026-07-24) Con responsable + fecha compromiso capturados en el form (o los defaults).
            $report->syncAutoActionItem(
                $request->input('suggestions_corrective_action'),
                'suggestions_corrective_action',
                $request->input('corrective_owner_id') ?: null,
                $request->input('corrective_due_date') ?: null
            );

            // (2026-07-23) Sellado AL CREAR (homologado con Injury/DSR/Scouting): el documento
            // consta desde el momento en que se reporta, aunque siga "Abierto". refresh() relee el
            // registro persistido para que el hash se calcule sobre los tipos reales de la BD
            // (evita la divergencia int/string). signDocument() es defensivo (no-op sin tabla).
            $report->refresh();
            $report->signDocument(auth()->user(), $request);

            // (2026-07-24) Aviso al JEFE DIRECTO del involucrado (blindado: nunca rompe el store).
            $this->notifyInvolvedLead($report);

            return Redirect::route('hazard_notifications.index')
                ->with('success', 'Notificación de peligro creada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al guardar la notificación de peligro: ' . $e->getMessage());

            return back()->withInput()->with('error', 'No se pudo guardar la notificación de peligro. Intenta de nuevo.');
        }
    }

    /**
     * (2026-07-14) Pilar 1 — Fase 2 (back-office): muestra la MISMA vista de crear en modo
     * edición para completar la carga "burocrática" (matriz 5×5, normas, ubicación exacta,
     * etc.) que en Fase 1 (móvil/set) pudo quedar pendiente.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $report = HazardNotification::findOrFail($id);

        // Aislamiento por autor (auditoría #1): editar sólo el autor o la consolidación
        // (safety.consolidate). Un safety no edita el hallazgo de otro. Leer la ficha SÍ es transversal.
        abort_unless(\App\Support\ReportVisibility::canMutate(auth()->user(), $report), 403,
            'Solo el autor o la consolidación de seguridad pueden editar este reporte.');

        // (2026-07-24) Para prellenar el responsable/fecha de la acción correctiva en el form.
        if (\Illuminate\Support\Facades\Schema::hasTable('action_items')) {
            $report->load('actionItems');
        }

        // Mismos catálogos que create() (carga defensiva del catálogo de eventos).
        $standards = \App\Models\SafetyStandard::orderBy('category_name', 'asc')->get();
        $hazardEvents = Schema::hasTable('hazard_events')
            ? HazardEvent::active()->with('standards')->orderBy('context')->orderBy('sort_order')->orderBy('name_es')->get()
            : collect();

        return View::make('admin.hazardnotification', compact('standards', 'hazardEvents'))
            ->with('isEdit', true)
            ->with('report', $report)
            ->with($this->step2Catalogs());
    }

    /**
     * (2026-07-14) Pilar 1 — Fase 2: guarda la carga completa de compliance. Valida el set
     * COMPLETO (equivale a las reglas estrictas legadas); corre applyHazardEvent() y
     * syncAutoActionItem() igual que store(); y pone pending_compliance=0 cuando la matriz
     * 5×5 quedó completa. NO toca la AUTOFIRMA original (make_by / created_by_id / make_date).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $report = HazardNotification::findOrFail($id);

        // Aislamiento por autor (auditoría #1): sólo el autor o la consolidación pueden editar/re-sellar.
        abort_unless(\App\Support\ReportVisibility::canMutate(auth()->user(), $report), 403,
            'Solo el autor o la consolidación de seguridad pueden editar este reporte.');

        // (2026-07-23) Blindaje anti-"solo espacios" (Fase 2 no pasa por el FormRequest, así que
        // se replica aquí el trim de prepareForValidation): "   " → "" → dispara el obligatorio.
        foreach (['production_name', 'name_loc', 'location_hazard_unsafe_act', 'description_hazard_unsafe_act'] as $f) {
            if (is_string($request->input($f))) {
                $request->merge([$f => trim($request->input($f))]);
            }
        }

        // Validación COMPLETA de compliance (obligatoriedad server-side plena). La matriz 5×5
        // sigue siendo nullable: si se completa, el reporte deja de estar "pendiente".
        $data = $request->validate([
            'production_name'               => 'required|string|max:255',
            'name_loc'                      => 'required|string|max:255',
            // (2026-07-23) GPS nullable (decisión owner); la locación de arriba es el campo obligatorio.
            'latitude'                      => 'nullable|numeric|between:-90,90',
            'longitude'                     => 'nullable|numeric|between:-180,180',
            'gps_address'                   => 'nullable|string|max:500',
            'manual_location_justification' => 'nullable|string|max:1000',
            'date_observed'                 => 'required|date',
            'time_observed'                 => 'required',
            'location_hazard_unsafe_act'    => 'required|string',
            'description_hazard_unsafe_act' => 'required|string',
            'action_taken'                  => 'nullable|string',
            'suggestions_corrective_action' => 'nullable|string',
            'main_image'                    => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            'additional_images'             => 'nullable|array',
            'additional_images.*'           => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            'hazard_event_id'               => 'nullable|integer',
            'risk_level'                    => 'nullable|in:Bajo,Medio,Alto,Extremo',
            'action_status'                 => 'nullable|in:Abierto,En proceso,Cerrado',
            'likelihood'                    => 'nullable|in:A,B,C,D,E',
            'consequence'                   => 'nullable|integer|between:1,5',
            // (2026-07-24) PASO 2/2 (Fase 2 no pasa por el FormRequest → se repiten aquí).
            'involved_user_id'              => 'nullable|integer|exists:users,id',
            'related_unsafecond_id'         => 'nullable|integer|exists:unsafeconds,id',
            'human_factor'                  => 'nullable|array',
            'human_factor.*'                => 'string|in:' . implode(',', array_keys(\App\Models\hazardnotification::HUMAN_FACTORS)),
            'corrective_owner_id'           => 'nullable|integer|exists:users,id',
            'corrective_due_date'           => 'nullable|date',
        ]);

        // ---- Guardado defensivo de columnas nuevas (mismo patrón que store()) ----
        if (!\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'latitude')) {
            unset($data['latitude'], $data['longitude'], $data['gps_address']);
        }
        // 'hazard_event_id' se maneja vía applyHazardEvent() (snapshot norma + pivote), no en update().
        unset($data['hazard_event_id']);
        if (!\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'likelihood')) {
            unset($data['likelihood'], $data['consequence']);
        }
        if (!\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'manual_location_justification')) {
            unset($data['manual_location_justification']);
        }
        // Los inputs de archivo NO son columnas: se quitan del array que va a update().
        unset($data['main_image'], $data['additional_images']);

        // (2026-07-24) PASO 2/2 — igual que store(): responsable/fecha fuera del array, guardado
        // defensivo de columnas nuevas, y vínculo con el Scouting desde el GPS.
        unset($data['corrective_owner_id'], $data['corrective_due_date']);
        foreach (['involved_user_id', 'related_unsafecond_id', 'human_factor', 'scouting_report_id'] as $newCol) {
            if (!Schema::hasColumn('hazardnotifications', $newCol)) {
                unset($data[$newCol]);
            }
        }
        if (Schema::hasColumn('hazardnotifications', 'scouting_report_id')) {
            $scoutId = \App\Support\ScoutingLocator::nearestId($request->input('latitude'), $request->input('longitude'));
            if ($scoutId !== null) {
                $data['scouting_report_id'] = $scoutId;
            }
        }

        // Imagen principal: reemplazo OPCIONAL (si no se sube, se conserva la existente).
        if ($request->hasFile('main_image')) {
            $image = \App\Support\ImageCompressor::normalizeForUpload($request->file('main_image'));
            $filename = time() . '_' . uniqid() . '_main.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('hazard_images', $filename, 'public');
            $data['main_image_path'] = Storage::url($path);
        }

        // Imágenes adicionales: se AGREGAN a las existentes (no se pierden las previas).
        if ($request->hasFile('additional_images')) {
            $additionalImagePaths = is_array($report->additional_images_paths) ? $report->additional_images_paths : [];
            foreach ($request->file('additional_images') as $image) {
                $image = \App\Support\ImageCompressor::normalizeForUpload($image);
                $filename = time() . '_additional_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('hazard_images', $filename, 'public');
                $additionalImagePaths[] = Storage::url($path);
            }
            $data['additional_images_paths'] = $additionalImagePaths;
        }

        // Chips de severidad/estado: capturados por el usuario (NO autofirma). Se conserva el
        // estado actual si el form no lo trae.
        if (\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'action_status')) {
            $data['risk_level']    = $request->input('risk_level');
            $data['action_status'] = $request->input('action_status') ?: ($report->action_status ?: 'Abierto');
        }

        // (2026-07-14) Fase 2: al completar la matriz 5×5, el reporte deja de estar
        // "pendiente de compliance"; si aún falta un eje, permanece pendiente (=1).
        if (\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'pending_compliance')) {
            $data['pending_compliance'] = ($request->filled('likelihood') && $request->filled('consequence')) ? 0 : 1;
        }

        // NOTA: NO se tocan make_by / make_date / created_by_id → la autofirma original queda intacta.

        try {
            $report->update($data);

            // (2026-07-24) Capturar SI cambió el involucrado JUSTO tras el update(): applyHazardEvent
            // y resealIfChanged() hacen saves/refresh que resetean wasChanged(); leerlo después daría
            // siempre false y el aviso nunca dispararía.
            $involvedChanged = $report->wasChanged('involved_user_id');

            // Igual que store(): snapshot de la norma del evento + pivote standardables, y
            // motor PDCA de la sugerencia de acción correctiva (con responsable + fecha compromiso).
            $report->applyHazardEvent($request->input('hazard_event_id'));
            $report->syncAutoActionItem(
                $request->input('suggestions_corrective_action'),
                'suggestions_corrective_action',
                $request->input('corrective_owner_id') ?: null,
                $request->input('corrective_due_date') ?: null
            );

            // (2026-07-23) Re-sello HONESTO: sólo si el contenido cambió de verdad. refresh() relee
            // el registro para calcular el hash sobre los tipos reales de la BD; hash_equals evita
            // apilar firmas idénticas cuando se guarda sin cambios.
            $this->resealIfChanged($report, $request);

            // (2026-07-24) Aviso al jefe SOLO si el involucrado cambió en esta edición (no re-spamea).
            if ($involvedChanged) {
                $this->notifyInvolvedLead($report);
            }

            return Redirect::route('hazard_notifications.show', $report->id)
                ->with('success', 'Notificación de peligro actualizada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al actualizar la notificación de peligro: ' . $e->getMessage());

            return back()->withInput()->with('error', 'No se pudo actualizar la notificación de peligro. Intenta de nuevo.');
        }
    }

    /**
     * Muestra una lista de todas las notificaciones de peligro.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Aislamiento por propiedad (auditoría #1): autor, con bypass safety.consolidate.
        $hazardNotifications = \App\Support\ReportVisibility::apply(HazardNotification::query(), auth()->user())
            ->orderBy('id', 'desc')->paginate(15);
        return View::make('admin.hazards', compact('hazardNotifications'));
    }

    /**
     * Muestra los detalles de una notificación de peligro específica.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $hazardNotification = HazardNotification::findOrFail($id);

        // (2026-06-28) Catálogo normativo: si la fila trae un regulation_code, se resuelve
        // la URL del boletín desde safety_standards (reference_url vive ahí, NO en la tabla
        // del reporte). Defensivo: si la columna aún no existe o no hay norma, queda en null.
        $standardUrl = null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'regulation_code') && \Illuminate\Support\Facades\Schema::hasColumn('safety_standards', 'reference_url') && $hazardNotification->regulation_code) {
            $standardUrl = \App\Models\SafetyStandard::where('regulation_code', $hazardNotification->regulation_code)->value('reference_url');
        }

        // (2026-07-09) Normas vinculadas (N:M) y acciones correctivas (PDCA) — carga defensiva.
        if (\Illuminate\Support\Facades\Schema::hasTable('standardables')) {
            $hazardNotification->load('standards');
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('action_items')) {
            // (2026-07-24) owner + verifiedBy para imprimir responsable y evidencia de cierre.
            $hazardNotification->load(['actionItems.owner', 'actionItems.verifiedBy']);
        }

        // (2026-07-24) PASO 2/2 — relaciones nuevas para el documento (carga defensiva).
        if (\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'related_unsafecond_id')) {
            $hazardNotification->load('relatedUnsafeCond');
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'scouting_report_id')) {
            $hazardNotification->load('scoutingReport');
        }

        // Contexto del INVOLUCRADO: el documento imprime SOLO el área; el nombre se revela (fuera de
        // impresión) únicamente a Safety (hazards.manage) o al jefe directo del depto del involucrado.
        $involvedUser = null;
        $involvedDeptName = '';
        $canViewInvolved = false;
        if (\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'involved_user_id') && $hazardNotification->involved_user_id) {
            $involvedUser = \App\Models\User::find($hazardNotification->involved_user_id);
            if ($involvedUser) {
                $involvedDeptName = \App\Support\InvolvedResolver::departmentNames($involvedUser->id);
                $viewer = auth()->user();
                $canViewInvolved = $viewer && ($viewer->can('hazards.manage')
                    || \App\Support\InvolvedResolver::viewerLeadsInvolvedDept($viewer->id, $involvedUser->id));
            }
        }

        // (2026-08-11) EXPORT PDF SERVER-SIDE (?pdf=1) — ADITIVO, antes del return normal. Reusa la
        // MISMA vista/datos y la pasa por Browsershot (Chrome headless) → descarga idéntica a
        // window.print(). Márgenes 0 (el @page Oficio manda). Ver [[browsershot-pdf-pipeline]].
        if (request()->boolean('pdf')) {
            $html = View::make('admin.hazard', compact('hazardNotification', 'standardUrl', 'involvedUser', 'involvedDeptName', 'canViewInvolved'))->render();
            return \App\Support\PdfExporter::download($html, 'HAZ-' . $hazardNotification->id, [0, 0, 0, 0]);
        }

        return View::make('admin.hazard', compact('hazardNotification', 'standardUrl', 'involvedUser', 'involvedDeptName', 'canViewInvolved'))
            ->with('pdfUrl', request()->fullUrlWithQuery(['pdf' => 1]));
    }

    /**
     * (2026-06-28) Cierre del ciclo: actualiza el estado de la acción correctiva
     * de un reporte existente (Abierto / En proceso / Cerrado).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['action_status' => 'required|in:Abierto,En proceso,Cerrado']);
        $n = HazardNotification::findOrFail($id);
        // Aislamiento por autor (auditoría #1): cambiar el estado / cerrar el hallazgo = autor o
        // consolidación. Un safety no cierra el hallazgo de otro.
        abort_unless(\App\Support\ReportVisibility::canMutate(auth()->user(), $n), 403,
            'Solo el autor o la consolidación de seguridad pueden cambiar el estado de este reporte.');
        // (2026-07-09) Bloqueo de estado PDCA: no se puede pasar a "Cerrado" si quedan
        // acciones correctivas abiertas (lanza ValidationException → se muestra en el show).
        if ($request->input('action_status') === 'Cerrado') {
            $n->assertActionItemsClosed('action_status');
        }
        // Salvaguarda: si el OWNER aún no aplicó el ALTER, no rompe.
        if (\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'action_status')) {
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
     * @param  \App\Models\HazardNotification  $doc
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
     * (2026-07-24) Avisa por correo al JEFE DIRECTO (lead del depto) del involucrado. El nombre del
     * involucrado viaja SOLO por este canal (nunca al documento). Blindado: cualquier fallo se
     * loguea y se traga → jamás rompe el guardado del reporte (mismo patrón que el listener de
     * seguridad). No-op si no hay involucrado o no se resuelve ningún lead.
     *
     * @param  \App\Models\HazardNotification $report
     * @return void
     */
    private function notifyInvolvedLead($report)
    {
        try {
            if (!Schema::hasColumn('hazardnotifications', 'involved_user_id') || empty($report->involved_user_id)) {
                return;
            }
            $recipients = \App\Support\InvolvedResolver::leadRecipients($report->involved_user_id);
            if (empty($recipients)) {
                return;
            }
            $involved = \App\Models\User::find($report->involved_user_id);
            if (!$involved) {
                return;
            }
            $branding  = \App\Support\Branding::all();
            $brandName = isset($branding['brand_name']) ? $branding['brand_name'] : 'CrewCare';
            $when = $report->date_observed
                ? (is_object($report->date_observed) ? $report->date_observed->format('d/m/Y') : (string) $report->date_observed)
                : '';
            $url = \Illuminate\Support\Facades\Route::has('hazard_notifications.show')
                ? route('hazard_notifications.show', $report->id) : null;
            $base = [
                'branding'            => $branding,
                'subject'             => 'Miembro de tu equipo en un reporte de seguridad — ' . $brandName,
                'type_label'          => 'Acto Inseguro',
                'involved_name'       => trim($involved->name . ' ' . ($involved->lname ?? '')),
                'involved_department' => \App\Support\InvolvedResolver::departmentNames($involved->id),
                'what'                => $report->description_hazard_unsafe_act,
                'when'                => $when,
                'where'               => $report->name_loc ?: $report->location_hazard_unsafe_act,
                'production'          => $report->production_name,
                'url'                 => $url,
            ];
            foreach ($recipients as $r) {
                try {
                    Mail::send('correos.involved-alert', array_merge($base, ['recipient_name' => $r['name']]), function ($m) use ($r, $base) {
                        $m->to($r['email'], $r['name']);
                        $m->subject($base['subject']);
                    });
                } catch (\Throwable $e) {
                    Log::warning('notifyInvolvedLead(hazard): fallo enviando a ' . $r['email'] . ' — ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('notifyInvolvedLead(hazard): ' . $e->getMessage());
        }
    }
}