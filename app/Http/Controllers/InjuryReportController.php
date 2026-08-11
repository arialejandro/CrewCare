<?php
namespace App\Http\Controllers;

use App\Models\InjuryReport;
use App\Models\User;
use App\Models\HazardEvent;
use App\Support\Features;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class InjuryReportController extends Controller
{
    public function inicial()
    {
        $injuryReports = InjuryReport::latest()->paginate(10);
        return view('admin.injuryreports', compact('injuryReports'));
    }

    public function create()
    {
        $user = auth()->user();
        // (2026-06-28) Catálogo normativo: mismo dropdown que usa el Daily Report.
        // Se pasa $standards a la vista para auto-etiquetar la norma aplicable.
        $standards = \App\Models\SafetyStandard::orderBy('category_name', 'asc')->get();
        // (2026-07-13) Catálogo ÚNICO de eventos: sustituye category_name + standards[].
        // Carga DEFENSIVA (si la tabla aún no existe en PROD, queda vacío y el form sigue).
        $hazardEvents = Schema::hasTable('hazard_events')
            ? HazardEvent::active()->with('standards')->orderBy('context')->orderBy('sort_order')->orderBy('name_es')->get()
            : collect();
        // (2026-07-13) COHERENCIA: departamento y puesto pasan de texto libre a
        // catálogo (se sigue guardando el NAME como string en injury_reports).
        $departments = \App\Models\Department::orderBy('name')->get();
        $positions   = \App\Models\Position::orderBy('name')->get();
        // (2026-07-14) Pilar 1 (Fase 2): la MISMA vista sirve para crear y editar.
        // En creación $isEdit=false y $injuryReport queda sin definir (la vista lo
        // resuelve a null).
        $isEdit = false;
        return view('admin.injuryreportcreate', compact('user', 'standards', 'hazardEvents', 'departments', 'positions', 'isEdit'));
    }

    /**
     * (2026-07-14) Pilar 1 — Fase 2 (back-office): editar un reporte ya capturado
     * para completar la carga de compliance. Reutiliza injuryreportcreate con
     * $isEdit=true + $injuryReport (prefill). Mismo catálogo que create().
     */
    public function edit($id)
    {
        $injuryReport = InjuryReport::findOrFail($id);
        $user = auth()->user();
        $standards = \App\Models\SafetyStandard::orderBy('category_name', 'asc')->get();
        $hazardEvents = Schema::hasTable('hazard_events')
            ? HazardEvent::active()->with('standards')->orderBy('context')->orderBy('sort_order')->orderBy('name_es')->get()
            : collect();
        $departments = \App\Models\Department::orderBy('name')->get();
        $positions   = \App\Models\Position::orderBy('name')->get();
        // Testigos para prefill del repeater (evita N+1 en la vista). Defensivo.
        if (Schema::hasTable('witnesses')) {
            $injuryReport->load('witnesses');
        }
        $isEdit = true;
        return view('admin.injuryreportcreate', compact('user', 'standards', 'hazardEvents', 'departments', 'positions', 'isEdit', 'injuryReport'));
    }

    public function store(Request $request)
{
    // (2026-07-14) Pilar 1 — captura en 2 FASES. En Fase 1 (progressive ON, default)
    // el store de móvil pide lo MÍNIMO (solo what_happened); todo lo demás es
    // nullable y el compliance se completa luego en la edición (Fase 2). Si el flag
    // está OFF se conservan las reglas ESTRICTAS de siempre.
    $progressive = Features::enabled('progressive_capture');

    // Fase 1 → reglas mínimas (strict = false); Fase 1 OFF → reglas estrictas.
    $validatedData = $request->validate($this->validationRules($request, !$progressive));

    // (2026-07-12) MÓDULO 10: la obligatoriedad de aviso a la autoridad (registrable)
    // es carga "burocrática" → solo se exige en el flujo ESTRICTO. En Fase 1 (ágil) NO
    // se bloquea: queda como pendiente de compliance para completarse en la edición.
    if (!$progressive) {
        $this->assertRecordableNotified($request);
    }

    // Preparar datos para la base de datos (unsets defensivos, JSON, imágenes, autollenado).
    $dataForDb = $this->prepareDataForDb($request, $validatedData);

    // AUTOFIRMA (sistema cerrado): autor + fecha del servidor, NO del formulario → no falseable.
    $dataForDb['make_by']   = auth()->user()->name;
    $dataForDb['make_date'] = now()->toDateString();
    if (Schema::hasColumn('injury_reports', 'created_by_id')) {
        $dataForDb['created_by_id'] = auth()->id();
    }

    // (2026-07-14) Pilar 1: si falta la matriz 5×5 (likelihood/consequence), el reporte
    // queda marcado como pendiente de compliance para cerrarse en Fase 2. Guard por columna.
    if (Schema::hasColumn('injury_reports', 'pending_compliance')) {
        $matrixIncomplete = empty($request->input('likelihood')) || empty($request->input('consequence'));
        $dataForDb['pending_compliance'] = $matrixIncomplete ? 1 : 0;
    }

    try {
        // Crear el reporte en la base de datos
        $report = InjuryReport::create($dataForDb);

        // (2026-07-13) Catálogo ÚNICO de eventos + Motor PDCA. applyHazardEvent():
        // (a) guarda hazard_event_id, (b) copia el badge/código de la norma PRINCIPAL a
        // regulation_badge/regulation_code, (c) adjunta TODAS las normas del evento al
        // pivote standardables (syncStandards). Es DEFENSIVO (no-op sin el SQL).
        // Las "Acciones de prevención" generan una acción correctiva rastreable con SLA.
        $report->applyHazardEvent($request->input('hazard_event_id'));
        $report->syncAutoActionItem($request->input('preventions'), 'preventions');

        // (2026-07-12) MÓDULO 7: Testigos (1:N, opcionales, solo filas con nombre).
        $this->syncWitnesses($report, $request);

        // (2026-07-12) MÓDULO 6: Firma digital SHA-256 sobre el ESTADO FINAL del
        // registro. refresh() trae los valores canónicos de la BD (incluidas las
        // columnas que fija el Observer: is_recordable / hours_worked_prior /
        // risk_level) para que el hash firmado coincida con lo que releerá
        // verifyLatestSignature(). signDocument() ya trae guard interno (no-op si
        // la tabla digital_signatures aún no existe).
        $report->refresh();
        $report->signDocument(auth()->user(), $request);

        // (2026-08-11) BUG-INC-01: el crew tiene injury.create pero NO injury.view,
        // así que redirigir SIEMPRE a injury_reports.show le daba un 403 al crear su
        // propio accidente. Ramificamos por permiso: quien puede VER el expediente va
        // al reporte; el resto aterriza en su home con un acuse, SIN exponer el
        // documento (evita el 403 y protege la PII clínica).
        if (auth()->user()->can('injury.view')) {
            return redirect()
                ->route('injury_reports.show', $report->id)
                ->with('success', 'Reporte creado exitosamente.');
        }

        return redirect()
            ->route('home')
            ->with('success', 'Tu reporte fue recibido. Gracias por notificarlo.');

    } catch (\Exception $e) {
        // Log del error para depuración
        \Log::error('Error al crear reporte: ' . $e->getMessage());

        // Si ocurre un error, redirigir de vuelta con el mensaje de error
        return back()
            ->withInput()
            ->with('error', 'Error al crear el reporte. Por favor intente nuevamente.');
    }
}

    /**
     * (2026-07-14) Pilar 1 — Fase 2 (back-office): completa la carga de compliance
     * de un reporte ya capturado. Valida el set COMPLETO/estricto (igual que store con
     * el flag apagado), aplica el catálogo de evento y el PDCA, y cierra el pendiente
     * de compliance cuando la matriz 5×5 queda completa. NUNCA toca la autofirma
     * (make_by / make_date / created_by_id).
     */
    public function update(Request $request, $id)
    {
        $injuryReport = InjuryReport::findOrFail($id);

        // Fase 2 SIEMPRE valida estricto (matriz 5×5, injury_type, causas, etc.).
        $validatedData = $request->validate($this->validationRules($request, true));

        // En Fase 2 la obligatoriedad de aviso a la autoridad (registrable) SÍ aplica.
        $this->assertRecordableNotified($request);

        // Reutiliza exactamente la misma preparación que store (unsets/JSON/imágenes).
        // Se pasa el reporte existente para APPEND (no reemplazo) de imágenes adicionales.
        $dataForDb = $this->prepareDataForDb($request, $validatedData, $injuryReport);

        // (2026-07-14) Pilar 1: al completar la matriz 5×5 se cierra el pendiente de
        // compliance. En el flujo estricto la matriz siempre está completa → 0.
        if (Schema::hasColumn('injury_reports', 'pending_compliance')) {
            $matrixComplete = !empty($request->input('likelihood')) && !empty($request->input('consequence'));
            $dataForDb['pending_compliance'] = $matrixComplete ? 0 : 1;
        }

        // NO se tocan make_by / make_date / created_by_id: la autofirma original se conserva
        // (no se incluyen en $dataForDb, así que update() no las sobrescribe).

        try {
            $injuryReport->update($dataForDb);

            // Mismo cierre que store: catálogo de evento + PDCA. Ambos son idempotentes
            // (applyHazardEvent re-sincroniza; syncAutoActionItem es único por source_field).
            $injuryReport->applyHazardEvent($request->input('hazard_event_id'));
            $injuryReport->syncAutoActionItem($request->input('preventions'), 'preventions');

            // MÓDULO 7: Testigos — se reemplazan (delete + recreate) para reflejar la edición.
            $this->syncWitnesses($injuryReport, $request, true);

            // RE-SELLADO (2026-07-22). store() ya sella al crear, pero update() NO volvía a
            // firmar — y la Fase 2 de back-office es justo donde se completan la matriz 5×5,
            // el tipo de lesión y las causas. Todas esas son columnas del reporte, o sea del
            // payload del hash: cualquier edición legítima dejaba el documento pintando
            // "Alterado tras la firma" sin que nadie lo hubiera alterado, que es peor que no
            // sellar (un sello que grita en falso enseña a ignorarlo).
            // Sólo re-firma si el contenido CAMBIÓ de verdad, y va blindado: que falle el
            // sellado no puede costar la edición que ya se guardó.
            $injuryReport->refresh();
            try {
                $ultima = \Illuminate\Support\Facades\Schema::hasTable('digital_signatures')
                    ? $injuryReport->signatures()->latest('id')->first()
                    : null;
                if ($ultima === null || !hash_equals($ultima->document_hash, $injuryReport->computeDocumentHash())) {
                    $injuryReport->signDocument(auth()->user(), $request);
                }
            } catch (\Throwable $e) {
                \Log::warning('No se pudo re-sellar el reporte de lesión #' . $injuryReport->id . ': ' . $e->getMessage());
            }

            return redirect()
                ->route('injury_reports.show', $injuryReport->id)
                ->with('success', 'Reporte actualizado exitosamente.');

        } catch (\Exception $e) {
            \Log::error('Error al actualizar reporte de lesión: ' . $e->getMessage());
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar el reporte. Por favor intente nuevamente.');
        }
    }

    /**
     * (2026-07-14) Reglas de validación compartidas por store()/update().
     * $strict=true → set COMPLETO (matriz/injury_type/causas obligatorios), usado por
     * update() y por store() con progressive OFF. $strict=false → Fase 1 (móvil ágil):
     * SOLO what_happened es obligatorio; el resto se relaja a nullable conservando los
     * formatos (date/numeric/in).
     */
    private function validationRules(Request $request, $strict)
    {
        // MÓDULO 11: la justificación manual solo se vuelve OBLIGATORIA (sin GPS) en el
        // flujo estricto y si la columna existe (defensivo prod). En Fase 1 no bloquea.
        $manualLocationRule = 'nullable|string|max:1000';
        if ($strict && Schema::hasColumn('injury_reports', 'manual_location_justification')) {
            $manualLocationRule .= '|required_without:latitude';
        }

        // Prefijo de obligatoriedad de los campos "de fondo".
        $req = $strict ? 'required' : 'nullable';

        return [
            'production_title' => "{$req}|string|max:255",
            'production_dates' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'incident_date' => "{$req}|date|before_or_equal:today",
            // El cross-field after_or_equal:incident_date solo aplica en estricto (en Fase 1
            // incident_date puede venir vacío y rompería la comparación).
            'reported_date' => $strict
                ? 'required|date|after_or_equal:incident_date|before_or_equal:today'
                : 'nullable|date|before_or_equal:today',
            'time' => 'nullable|date_format:H:i',
            'incident_location' => 'nullable|string|max:255',
            // (2026-07-07) GPS opcional: coordenadas + dirección detectada (reverse geocoding).
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'gps_address' => 'nullable|string|max:500',
            'name' => "{$req}|string|max:255",
            'position' => 'nullable|string|max:255',
            'dob' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'other' => 'nullable|string|max:255',
            'body_part' => 'nullable|string|max:255',
            'injury_type' => "{$req}|array",
            'injury_type.*' => 'string|max:255',
            'treatment_type' => 'nullable|string|max:255',
            'treatment_by' => 'nullable|string|max:255',
            'hospital' => 'nullable|string|max:255',
            'treatment_comments' => 'nullable|string|max:1000',
            // (2026-07-14) what_happened es el ÚNICO campo obligatorio también en Fase 1.
            'what_happened' => 'required|string|max:2000',
            'what_caused' => "{$req}|string|max:2000",
            'preventions' => "{$req}|string|max:1000",
            'further_comments' => 'nullable|string|max:1000',
            'user_id' => 'nullable|exists:users,id',
            // (2026-07-09) Límite de imagen subido a 12 MB (5 MB rechazaba fotos de celular en silencio).
            'main_image' => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            'additional_images.*' => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            // (2026-06-28) Catálogo normativo legacy: nullable por compat con envíos viejos.
            'category_name' => 'nullable|string',
            // (2026-07-13) Catálogo ÚNICO de eventos. 'nullable|integer' (NO exists) para no
            // romper PROD antes del SQL; applyHazardEvent() lo resuelve de forma defensiva.
            'hazard_event_id' => 'nullable|integer',
            // (2026-07-09) Datos laborales + fatiga.
            'employer_name' => 'nullable|string|max:255',
            'call_time' => 'nullable|date_format:H:i',
            // (2026-07-09) Registrabilidad OSHA 300/301 (is_recordable se fuerza server-side).
            'treatment_level' => 'nullable|in:first_aid,medical_treatment,hospitalization,fatality',
            'days_away_from_work' => 'nullable|integer|min:0|max:9999',
            'days_restricted_work' => 'nullable|integer|min:0|max:9999',
            // (2026-07-13) Matriz 5×5: en estricto son OBLIGATORIOS; en Fase 1 nullable
            // (si faltan, el reporte queda pending_compliance=1).
            'likelihood' => $strict ? 'required|in:A,B,C,D,E' : 'nullable|in:A,B,C,D,E',
            'consequence' => $strict ? 'required|integer|between:1,5' : 'nullable|integer|between:1,5',
            // (2026-07-09) Causa raíz estructurada (JSON).
            'root_cause_analysis' => 'nullable|array',
            'root_cause_analysis.immediate' => 'nullable|string|max:2000',
            'root_cause_analysis.contributing' => 'nullable|string|max:2000',
            'root_cause_analysis.root' => 'nullable|string|max:2000',
            // (2026-07-20) MECANISMO DE LA LESIÓN: cómo la persona entró en contacto con el
            // daño. Vive DENTRO del JSON root_cause_analysis (llave nueva) a propósito: NO es
            // una columna nueva en injury_reports, así que el sello SHA de lo ya firmado no
            // cambia. Sólo afecta capturas nuevas.
            'root_cause_analysis.mechanism' => 'nullable|string|max:2000',
            // (2026-07-13) COHERENCIA: categorías de causa raíz (checkboxes) — additive.
            'root_cause_analysis.categories' => 'nullable|array',
            'root_cause_analysis.categories.*' => 'string|max:255',
            // (2026-07-09) EPP estructurado (JSON).
            'ppe_details' => 'nullable|array',
            'ppe_details.worn' => 'nullable|in:si,no,na',
            'ppe_details.types' => 'nullable|array',
            'ppe_details.types.*' => 'nullable|string|max:100',
            'ppe_details.condition' => 'nullable|string|max:255',
            // (2026-07-09) Vínculo N:M a normas aplicables.
            'standards' => 'nullable|array',
            'standards.*' => 'integer|exists:safety_standards,id',
            // (2026-07-12) MÓDULO 7: Testigos (1:N). Opcionales; si hay fila, el nombre es required.
            'witnesses' => 'nullable|array',
            'witnesses.*.name' => 'required_with:witnesses|string|max:255',
            'witnesses.*.phone' => 'nullable|string|max:50',
            'witnesses.*.statement' => 'nullable|string|max:2000',
            // (2026-07-12) MÓDULO 10: Notificaciones a autoridad (JSON estructurado).
            'authority_notifications' => 'nullable|array',
            'authority_notifications.*.authority' => 'nullable|string|max:100',
            'authority_notifications.*.notified_at' => 'nullable|date',
            'authority_notifications.*.notified_by' => 'nullable|string|max:255',
            'authority_notifications.*.folio_number' => 'nullable|string|max:100',
            // (2026-07-12) MÓDULO 11: Justificación de ubicación manual (sin GPS).
            'manual_location_justification' => $manualLocationRule,
        ];
    }

    /**
     * (2026-07-12) MÓDULO 10: si el incidente es REGISTRABLE (nivel médico/hospitalización/
     * fatalidad o días perdidos/restringidos), exige al menos UN aviso a la autoridad con
     * autoridad y folio no vacíos. No-op si la columna no existe (defensivo prod).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function assertRecordableNotified(Request $request)
    {
        if (!Schema::hasColumn('injury_reports', 'authority_notifications')) {
            return;
        }

        $treatmentLevel = $request->input('treatment_level');
        $daysAway       = (int) $request->input('days_away_from_work', 0);
        $daysRestricted = (int) $request->input('days_restricted_work', 0);
        $isRecordable   = in_array($treatmentLevel, ['medical_treatment', 'hospitalization', 'fatality'], true)
            || $daysAway > 0 || $daysRestricted > 0;

        if (!$isRecordable) {
            return;
        }

        $hasValidNotification = false;
        foreach ((array) $request->input('authority_notifications', []) as $note) {
            if (is_array($note)
                && trim((string) ($note['authority'] ?? '')) !== ''
                && trim((string) ($note['folio_number'] ?? '')) !== '') {
                $hasValidNotification = true;
                break;
            }
        }

        if (!$hasValidNotification) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'authority_notifications' => 'Este incidente es REGISTRABLE: registra al menos una notificación a la autoridad con autoridad y número de folio.',
            ]);
        }
    }

    /**
     * (2026-07-14) Preparación compartida de datos para BD (store + update). Aplica los
     * mismos unsets/guards defensivos, normaliza JSON y fechas vacías, autollena desde el
     * usuario elegido y procesa imágenes. En edición ($existing) las imágenes adicionales
     * se AGREGAN a las ya guardadas (no se reemplazan) y la principal solo cambia si se
     * sube una nueva. NO fija autofirma ni pending_compliance (eso lo hace cada acción).
     *
     * @param  \App\Models\InjuryReport|null  $existing
     * @return array
     */
    private function prepareDataForDb(Request $request, array $validatedData, InjuryReport $existing = null)
    {
        $dataForDb = $validatedData;

        // category_name / hazard_event_id NO se escriben por mass assignment: el snapshot de
        // la norma y el hazard_event_id los fija applyHazardEvent() de forma defensiva.
        unset($dataForDb['category_name'], $dataForDb['hazard_event_id']);

        // GPS defensivo: si el owner aún no aplicó el ALTER, se quitan las coordenadas.
        if (!Schema::hasColumn('injury_reports', 'latitude')) {
            unset($dataForDb['latitude'], $dataForDb['longitude'], $dataForDb['gps_address']);
        }

        // 'standards' (N:M pivote standardables) y 'witnesses' (1:N) NO son columnas.
        unset($dataForDb['standards'], $dataForDb['witnesses']);

        // MÓDULO 10: notificaciones a autoridad (JSON). Filtra filas vacías; array vacío → null.
        if (Schema::hasColumn('injury_reports', 'authority_notifications')) {
            $notifications = [];
            if (isset($dataForDb['authority_notifications']) && is_array($dataForDb['authority_notifications'])) {
                foreach ($dataForDb['authority_notifications'] as $note) {
                    if (!is_array($note)) {
                        continue;
                    }
                    $filled = array_filter($note, function ($v) {
                        return trim((string) $v) !== '';
                    });
                    if (!empty($filled)) {
                        $notifications[] = $note;
                    }
                }
            }
            $dataForDb['authority_notifications'] = empty($notifications) ? null : array_values($notifications);
        } else {
            unset($dataForDb['authority_notifications']);
        }

        // MÓDULO 11: justificación de ubicación manual. Guard defensivo.
        if (!Schema::hasColumn('injury_reports', 'manual_location_justification')) {
            unset($dataForDb['manual_location_justification']);
        }

        // Columnas estructurales nuevas: si el owner aún no aplicó el delta, se quitan.
        if (!Schema::hasColumn('injury_reports', 'is_recordable')) {
            unset(
                $dataForDb['employer_name'], $dataForDb['call_time'], $dataForDb['treatment_level'],
                $dataForDb['days_away_from_work'], $dataForDb['days_restricted_work'],
                $dataForDb['likelihood'], $dataForDb['consequence'],
                $dataForDb['root_cause_analysis'], $dataForDb['ppe_details']
            );
        }

        // JSON estructurados: si vienen totalmente vacíos, se guarda null.
        foreach (['root_cause_analysis', 'ppe_details'] as $jsonKey) {
            if (isset($dataForDb[$jsonKey]) && is_array($dataForDb[$jsonKey])) {
                $nonEmpty = array_filter(\Illuminate\Support\Arr::flatten($dataForDb[$jsonKey]), function ($v) {
                    return trim((string) $v) !== '';
                });
                if (empty($nonEmpty)) {
                    $dataForDb[$jsonKey] = null;
                }
            }
        }

        // (2026-07-14) Fase 1: normaliza fechas/horas vacías a NULL. En captura ágil pueden
        // venir sin llenar y un '' rompe columnas DATE/TIME en MySQL estricto.
        foreach (['incident_date', 'reported_date', 'time', 'call_time', 'dob'] as $dt) {
            if (array_key_exists($dt, $dataForDb) && $dataForDb[$dt] === '') {
                $dataForDb[$dt] = null;
            }
        }

        // Si se seleccionó un usuario del crew, se autollenan sus datos.
        if (!empty($validatedData['user_id'])) {
            $selectedUser = User::findOrFail($validatedData['user_id']);
            $dataForDb['name'] = $selectedUser->name;
            // El modelo User usa `puestodepartamento` y `borndate` (no position/dob).
            $dataForDb['position'] = $selectedUser->puestodepartamento;
            $dataForDb['dob'] = $selectedUser->borndate ? $selectedUser->borndate->format('Y-m-d') : null;
            $dataForDb['phone'] = $selectedUser->phone;
        } else {
            $dataForDb['name'] = $validatedData['name'] ?? null;
        }

        // Imagen principal: solo se reemplaza si se sube una nueva (en edición conserva la actual).
        if ($request->hasFile('main_image')) {
            // HEIC (iPhone) → JPEG si el servidor puede; si no, la validación 'heic_ok' ya lo rechazó.
            $image = \App\Support\ImageCompressor::normalizeForUpload($request->file('main_image'));
            // Nombre único (time()+uniqid()) para evitar colisiones en el mismo segundo.
            $filename = time() . '_' . uniqid() . '_main.' . \App\Support\ImageCompressor::safeExtensionOrBin($image);
            $path = $image->storeAs('injury_images', $filename, 'public');
            $dataForDb['main_image_path'] = Storage::url($path);
        }

        // Imágenes adicionales: en edición se AGREGAN a las existentes (no se pierden).
        if ($request->hasFile('additional_images')) {
            $additionalImagePaths = ($existing && is_array($existing->additional_images_paths))
                ? $existing->additional_images_paths
                : [];
            foreach ($request->file('additional_images') as $image) {
                $image = \App\Support\ImageCompressor::normalizeForUpload($image);
                $filename = time() . '_additional_' . uniqid() . '.' . \App\Support\ImageCompressor::safeExtensionOrBin($image);
                $path = $image->storeAs('injury_images', $filename, 'public');
                $additionalImagePaths[] = Storage::url($path);
            }
            // El modelo castea additional_images_paths => 'array' (no json_encode manual).
            $dataForDb['additional_images_paths'] = $additionalImagePaths;
        }

        return $dataForDb;
    }

    /**
     * (2026-07-12) MÓDULO 7: sincroniza testigos (1:N). Crea solo las filas con nombre no
     * vacío (opcionales). En edición ($replace=true) reemplaza el conjunto: borra los
     * testigos previos y recrea desde el request (la vista los re-envía prellenados).
     * No-op si la tabla no existe (defensivo prod).
     */
    private function syncWitnesses(InjuryReport $report, Request $request, $replace = false)
    {
        if (!Schema::hasTable('witnesses')) {
            return;
        }

        if ($replace) {
            $report->witnesses()->delete();
        }

        foreach ((array) $request->input('witnesses', []) as $witness) {
            if (!is_array($witness)) {
                continue;
            }
            $witnessName = trim((string) ($witness['name'] ?? ''));
            if ($witnessName === '') {
                continue;
            }
            $report->witnesses()->create([
                'name'      => $witnessName,
                'phone'     => isset($witness['phone']) ? $witness['phone'] : null,
                'statement' => isset($witness['statement']) ? $witness['statement'] : null,
            ]);
        }
    }






/**
 * (2026-07-20) DOS SALIDAS — salida LITE (default).
 *
 * `/accident/{id}` sirve la NOTIFICACIÓN a Producción: qué, cuándo, dónde, quién,
 * estado y nivel de riesgo. SIN declaración de testigos, SIN detalle clínico, SIN
 * causa raíz de fondo, SIN addendum. Es la que se comparte con producción y su
 * export PDF (window.print) es libre para cualquiera con injury.view.
 *
 * NO es "el expediente con secciones ocultas" (un PDF exportado no respeta permisos):
 * es un DOCUMENTO DISTINTO. El expediente completo vive en showComplete(), gateado por
 * la policy viewMedical, tanto para verlo como para imprimirlo.
 *
 * $canComplete le dice a la vista si mostrar el enlace al expediente completo (sólo a
 * quien pase viewMedical: capturador, H&S con hazards.manage, médico, super-admin).
 */
public function show($id)
{
    $injuryReport = InjuryReport::with('user')->findOrFail($id);

    $canComplete = \Illuminate\Support\Facades\Gate::allows('viewMedical', $injuryReport);

    // (2026-08-11) EXPORT PDF SERVER-SIDE (?pdf=1) — ADITIVO, antes del return normal. Reusa la
    // MISMA vista/datos y la pasa por Browsershot (Chrome headless) → descarga idéntica a
    // window.print(). Márgenes 0 (el @page Oficio manda). Ver [[browsershot-pdf-pipeline]].
    if (request()->boolean('pdf')) {
        $html = view('admin.injuryreport-lite', compact('injuryReport', 'canComplete'))->render();
        return \App\Support\PdfExporter::download($html, 'INJ-' . $injuryReport->id, [0, 0, 0, 0]);
    }

    return view('admin.injuryreport-lite', compact('injuryReport', 'canComplete')
        + ['pdfUrl' => request()->fullUrlWithQuery(['pdf' => 1])]);
}

/**
 * (2026-07-20) DOS SALIDAS — salida COMPLETA (expediente H&S/legal).
 *
 * `/accident/{id}/completo` sirve TODO: causa raíz, mecanismo, roles separados,
 * testigos con declaración, tratamiento, EPP, y los anexos médicos (addenda) como
 * anexos firmados. Su ACCESO y su EXPORT PDF quedan gateados por la policy
 * viewMedical vía $this->authorize() — quien no pase, 403 (no se sirve ni en blanco).
 * OJO: super-admin la salta por Gate::before; probar con un usuario no super-admin.
 */
public function showComplete($id)
{
    $injuryReport = InjuryReport::with('user')->findOrFail($id);

    // GATE del expediente completo: mismo silo médico del reporte de lesión.
    $this->authorize('viewMedical', $injuryReport);

    // (2026-06-28) Catálogo normativo: si la fila trae un regulation_code, se resuelve la URL
    // del boletín desde safety_standards (reference_url vive ahí, NO en la tabla del reporte).
    // Defensivo: si la columna aún no existe o no hay norma, queda en null.
    $standardUrl = null;
    if (\Illuminate\Support\Facades\Schema::hasColumn('injury_reports', 'regulation_code') && \Illuminate\Support\Facades\Schema::hasColumn('safety_standards', 'reference_url') && $injuryReport->regulation_code) {
        $standardUrl = \App\Models\SafetyStandard::where('regulation_code', $injuryReport->regulation_code)->value('reference_url');
    }

    // (2026-07-09) Normas vinculadas (N:M) y acciones correctivas (PDCA) — carga defensiva.
    if (\Illuminate\Support\Facades\Schema::hasTable('standardables')) {
        $injuryReport->load('standards');
    }
    if (\Illuminate\Support\Facades\Schema::hasTable('action_items')) {
        $injuryReport->load('actionItems');
    }

    // (2026-07-12) MÓDULO 7: Testigos — carga defensiva (evita N+1 en la vista).
    if (\Illuminate\Support\Facades\Schema::hasTable('witnesses')) {
        $injuryReport->load('witnesses');
    }

    // (2026-07-20) Anexos médicos (addenda) + autor y su cédula, para pintarlos como
    // anexos firmados. Carga defensiva: la relación de cédula sólo si su tabla existe.
    //
    // (2026-07-24 · PASO 2/3, item 6) COHERENCIA CON EL AISLAMIENTO POR MÉDICO — decisión
    // explícita: los addenda NO se aíslan entre médicos, a diferencia de las consultas
    // (cmedic::visibleTo). Son partes de UN expediente de accidente que ya viaja completo a
    // producción y legal, no encuentros clínicos privados; además un médico de relevo necesita
    // LEER el anexo previo para agregar un seguimiento legítimo (misma lógica de reconciliación
    // que el cintillo de tratamiento previo). La AddendumPolicy sigue mandando en la PROPIEDAD:
    // sólo el médico que lo escribió puede editarlo. Ver: leer sí, escribir sobre lo ajeno no.
    // El candado de acceso a esta vista sigue siendo viewMedical (arriba).
    if (\Illuminate\Support\Facades\Schema::hasTable('addendums')) {
        $addendumRel = \App\Models\MedicCredential::supportsCredentials()
            ? ['addendums.createdBy.medicCredential']
            : ['addendums.createdBy'];
        $injuryReport->load($addendumRel);
    }

    // (2026-08-11) EXPORT PDF SERVER-SIDE (?pdf=1) — ADITIVO, antes del return normal. El GATE
    // viewMedical de arriba también protege el export (no se sirve ni el PDF sin permiso).
    // Márgenes 0 (el @page Oficio manda). Ver [[browsershot-pdf-pipeline]].
    if (request()->boolean('pdf')) {
        $html = view('admin.injuryreport', compact('injuryReport', 'standardUrl'))->render();
        return \App\Support\PdfExporter::download($html, 'INJ-' . $injuryReport->id . '-completo', [0, 0, 0, 0]);
    }

    return view('admin.injuryreport', compact('injuryReport', 'standardUrl')
        + ['pdfUrl' => request()->fullUrlWithQuery(['pdf' => 1])]);
}

    public function searchUsers(Request $request)
{
    // Validación ligera del input AJAX (la query ya usa bindings de Eloquent y no es inyectable)
    $request->validate(['term' => 'nullable|string|max:255']);

    $term = $request->input('term');
    
    return User::query()
        ->where(function($query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                  ->orWhere('lname', 'like', "%{$term}%")
                  ->orWhere('lname2', 'like', "%{$term}%");
        })
        ->limit(10)
        ->get(['id', 'name', 'lname', 'lname2', 'puestodepartamento as position', 'borndate', 'phone', 'zone'])
        ->map(function($user) {
            $fullName = trim("{$user->name} {$user->lname} {$user->lname2}");
            
            return [
                'id' => $user->id,
                'value' => $fullName,
                'label' => $fullName,
                'position' => $user->position ? $user->position . ($user->zone ? ' - ' . $user->zone : '') : null,
                'dob' => $user->borndate ? $user->borndate->format('Y-m-d') : null,
                'phone' => $user->phone
            ];
        });
}

    // breadcrumb: se eliminó el método privado storeImage() por código muerto.
    // No se llamaba desde ningún lado del repo; la subida de imágenes en store() es inline.
}