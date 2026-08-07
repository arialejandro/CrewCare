@extends('layouts.app')

@section('content')

{{-- (2026-07-07) Rediseño: formulario homologado al estilo moderno de la app
     (cards shadow-sm con header de color + emoji, row g-3, labels fw-semibold),
     mismo patrón que admin/scoutings/_form.blade.php. Los name="" de los inputs
     NO cambiaron: el controlador valida esos mismos nombres. --}}

@php
    // (2026-07-14) Pilar 1 — vista compartida crear/editar.
    $isEdit = $isEdit ?? false;
    $report = $report ?? null;
    // Flag de captura progresiva (Fase 1). Si ON, los campos no-esenciales NO son
    // required en el cliente (captura ágil en set); si OFF, se conserva required.
    $progressive = \App\Support\Features::enabled('progressive_capture');
    // "Estricto" = validación completa esperada: cuando el flag está OFF, o SIEMPRE al
    // editar (update() valida el set completo en Fase 2). En ese caso el cliente exige
    // los campos clave y muestra el asterisco; si no, la captura de set es ágil.
    $strict = !$progressive || $isEdit;
    $req = $strict ? 'required' : '';
    // Prefill: old() manda; en edición cae al valor del reporte (defensivo con null).
    $pf = function ($field, $default = '') use ($report) {
        return old($field, $report ? ($report->{$field} ?? $default) : $default);
    };
@endphp

<div class="container py-4">

    {{-- Encabezado de página (patrón del scouting create) --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-0 fw-bold">{{ $isEdit ? 'Editar Condición Insegura' : 'Reporte de Condición Insegura' }}</h2>
            <p class="cc-muted mb-0 small">{{ $isEdit ? 'Completa la información de compliance de esta notificación' : 'Notificación de condición insegura y plan de acción' }}</p>
        </div>
        <a href="{{ route('unsafenotifications.index') }}" class="btn btn-outline-secondary">&larr; Volver</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $isEdit ? route('unsafenotifications.update', $report->id) : route('unsafenotifications.store') }}" method="POST" enctype="multipart/form-data" @if(!$isEdit) data-cc-drafts="unsafe-condition" @endif>
        @csrf
        @if($isEdit) @method('PUT') @endif

        {{-- (captura fluida · Paso A) Borradores en el dispositivo. Solo en alta. --}}
        @if(!$isEdit)
            @include('componentes._drafts-tray', ['draftType' => 'unsafe-condition'])
        @endif

        {{-- ============ SECCIÓN: GENERAL Y LOCACIÓN ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico'])
                <span>General y Locación</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    {{-- (2026-07-13) Coherencia: este campo SIEMPRE guardó en la columna
                         production_name (nombre del proyecto); estaba MAL etiquetado como
                         "Persona o departamento involucrado". Reetiquetado a "Producción",
                         igual que Hazard. La persona/departamento va ahora en el select de abajo. --}}
                    {{-- (2026-07-24) Producción DERIVADA de Config→Marca (como el Scouting): no se
                         teclea. En edición se respeta el production_name ya guardado. --}}
                    <div class="col-12 col-md-6">
                        @php $prodName = old('production_name', ($report && $report->production_name) ? $report->production_name : \App\Support\Branding::get('brand_name', 'CrewCare')); @endphp
                        <label class="form-label fw-semibold">Nombre de la Producción</label>
                        <input type="text" class="form-control bg-light" value="{{ $prodName }}" readonly>
                        <input type="hidden" name="production_name" value="{{ $prodName }}">
                        <small class="cc-muted d-block">Definida en Configuración → Marca.</small>
                    </div>

                    {{-- (2026-07-24) CORRECCIÓN — se RETIRARON "Persona o departamento involucrado"
                         (involved_department) y "Persona involucrada" (involved_user_id). Una condición
                         insegura es un peligro DEL LUGAR, no de una persona: se ancla al Scouting +
                         locación (abajo) y a un RESPONSABLE de la acción correctiva (con dueño y plazo).
                         La persona pertenece al ACTO Inseguro, que es una conducta. --}}

                    <div class="col-12 col-md-6">
                        {{-- id estable "name_loc": es el suggestTarget del GPS silencioso de abajo. --}}
                        {{-- (2026-07-23) Locación SIEMPRE obligatoria (decisión owner): el GPS puede
                             fallar en sótano/foro/sin señal, pero la locación no puede quedar vacía;
                             se llena por detección GPS o a mano. Homologado con el Acto Inseguro. --}}
                        <label for="name_loc" class="form-label fw-semibold">Locación <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name_loc') is-invalid @enderror" id="name_loc" name="name_loc" value="{{ $pf('name_loc') }}" required>
                        @error('name_loc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        {{-- El JS escribe aquí "📍 Detectamos que estás en: X (a Ym)" cuando hay
                             un scouting a ≤500 m. Vacío si no hay detección. --}}
                        <small id="geo-note" class="cc-muted d-block"></small>
                        {{-- GPS SILENCIOSO (cero fricción): sin botones ni coordenadas visibles.
                             Detecta la posición por detrás, guarda lat/lng/gps_address en hidden
                             y sugiere el NOMBRE de la locación scouteada en #name_loc (solo si
                             está vacía). Lógica en public/js/crewcare-geo.js. --}}
                        @include('componentes._geo-capture', [
                            'mode' => 'silent',
                            'suggestTarget' => '#name_loc',
                            'noteTarget' => '#geo-note',
                            // Prefill en modo edición (los hidden llevan las coordenadas guardadas).
                            'latValue' => $pf('latitude'),
                            'lngValue' => $pf('longitude'),
                            'gpsAddressValue' => $pf('gps_address'),
                        ])
                    </div>

                    {{-- (2026-07-09) Ubicación GPS OBLIGATORIA. El GPS silencioso llena lat/lng por
                         detrás; este respaldo permite capturarlas a mano si el dispositivo no comparte
                         ubicación (permiso denegado / interior). Escribe en los MISMOS hidden. --}}
                    {{-- (2026-07-13) Coherencia GPS HONESTO (igual que Hazard): aviso VISIBLE del
                         estado de la geolocalización — es obligatoria. Si el navegador NO comparte
                         la ubicación, el aviso se vuelve una advertencia visible y se abre la captura
                         manual de abajo. Ya no falla "en silencio" tras enviar el formulario. --}}
                    <div class="col-12">
                        {{-- (2026-07-14) GPS silencioso: el aviso arranca OCULTO (d-none) y sólo se
                             muestra si la geolocalización FALLA o se deniega (el JS le quita d-none).
                             Nunca aparece "antes de fallar" ni bloquea el submit. --}}
                        <div id="gps_notice" class="alert alert-info d-none d-flex align-items-center gap-2 py-2 px-3 mb-2 small" role="status">
                            <span id="gps_notice_icon">📍</span>
                            <span id="gps_notice_text">Detectando tu ubicación…</span>
                        </div>
                        <details id="gps_details" class="small">
                            <summary style="cursor:pointer;" class="fw-semibold">@include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico']) Ubicación GPS (automática) — <span id="gps_state" class="cc-muted fw-normal">detectando…</span></summary>
                            <div class="row g-2 mt-1">
                                <div class="col-6 col-md-4">
                                    <input type="number" step="any" min="-90" max="90" class="form-control form-control-sm" id="gps_lat_manual" placeholder="Latitud" value="{{ $pf('latitude') }}">
                                </div>
                                <div class="col-6 col-md-4">
                                    <input type="number" step="any" min="-180" max="180" class="form-control form-control-sm" id="gps_lng_manual" placeholder="Longitud" value="{{ $pf('longitude') }}">
                                </div>
                            </div>
                            <div class="form-text">Intentamos capturar tu GPS automáticamente. <strong>Si el navegador no pudo capturar el GPS</strong>, ingresa las coordenadas a mano o describe la ubicación con referencias exactas abajo. El GPS no bloquea el envío; la locación de arriba sí es obligatoria.</div>
                            {{-- (2026-07-12) Módulo 11: referencias exactas cuando no hay GPS.
                                 El FormRequest la exige (required_without:latitude) si faltan coordenadas. --}}
                            <div class="mt-2">
                                <label for="manual_location_justification" class="form-label fw-semibold mb-1">Justificación de ubicación (referencias exactas)</label>
                                <textarea class="form-control form-control-sm" id="manual_location_justification" name="manual_location_justification" rows="2" maxlength="1000" placeholder="Ej.: Set 3, esquina noreste, junto a la bodega de utilería; acceso por la puerta B.">{{ $pf('manual_location_justification') }}</textarea>
                                <div class="form-text">Describe la ubicación con referencias exactas si el dispositivo no comparte GPS.</div>
                            </div>
                        </details>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="date_observed" class="form-label fw-semibold">Fecha Observada @if($strict)<span class="text-danger">*</span>@endif</label>
                        <input type="date" class="form-control @error('date_observed') is-invalid @enderror" id="date_observed" name="date_observed" value="{{ old('date_observed', ($report && $report->date_observed) ? $report->date_observed->format('Y-m-d') : '') }}" {{ $req }}>
                        @error('date_observed')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="time_observed" class="form-label fw-semibold">Hora Observada @if($strict)<span class="text-danger">*</span>@endif</label>
                        <input type="time" class="form-control @error('time_observed') is-invalid @enderror" id="time_observed" name="time_observed" value="{{ $pf('time_observed') }}" {{ $req }}>
                        @error('time_observed')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: DETALLES DE LA CONDICIÓN ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-warning text-dark fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico'])
                <span>Detalles de la Condición Insegura</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    {{-- (2026-07-24) ENLACE REAL al Acto inseguro hermano — reemplaza el "¿Hay una
                         notificación? Sí/No + fecha" que sugería una relación SIN FK. La fecha se
                         DERIVA del reporte vinculado; las columnas viejas (unsafe_act_notify /
                         date_notify_unsafe_act) se conservan solo para leer el histórico. --}}
                    <div class="col-12 col-md-6">
                        <label for="related_hazard_id" class="form-label fw-semibold">Acto inseguro relacionado <span class="cc-muted fw-normal">(opcional)</span></label>
                        @php $selRel = $pf('related_hazard_id'); @endphp
                        <select name="related_hazard_id" id="related_hazard_id" class="form-select">
                            <option value="">— Ninguno —</option>
                            @foreach(($siblings ?? collect()) as $s)
                                <option value="{{ $s->id }}" {{ (string) $s->id === (string) $selRel ? 'selected' : '' }}>HAZ-{{ str_pad($s->id, 4, '0', STR_PAD_LEFT) }} · {{ \Illuminate\Support\Str::limit($s->name_loc ?: $s->description_hazard_unsafe_act, 40) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Enlace real al reporte hermano; su fecha se toma de él.</div>
                    </div>

                    {{-- (2026-07-24) RECURRENCIA (solo Condición): las condiciones repetidas revelan un
                         problema de PROCESO, no del objeto. Se imprime. --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold d-block">¿Ya se había reportado esta condición?</label>
                        @php $selRec = $pf('is_recurrent'); @endphp
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" id="rec_yes" name="is_recurrent" value="1" {{ (string) $selRec === '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="rec_yes">Sí (recurrente)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" id="rec_no" name="is_recurrent" value="0" {{ (string) $selRec === '0' ? 'checked' : '' }}>
                            <label class="form-check-label" for="rec_no">No</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="location_unsafe_cond" class="form-label fw-semibold">¿Dónde se observó la condición insegura? @if($strict)<span class="text-danger">*</span>@endif</label>
                        <textarea class="form-control @error('location_unsafe_cond') is-invalid @enderror" id="location_unsafe_cond" name="location_unsafe_cond" rows="3" placeholder="Sea específico: área, set, foro, pasillo…" {{ $req }}>{{ $pf('location_unsafe_cond') }}</textarea>
                        @error('location_unsafe_cond')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        {{-- (2026-07-14) Fase 1: la descripción es lo ÚNICO obligatorio (ágil en set). --}}
                        <label for="description_unsafe_cond" class="form-label fw-semibold">Descripción de la condición insegura <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('description_unsafe_cond') is-invalid @enderror" id="description_unsafe_cond" name="description_unsafe_cond" rows="4" placeholder="Incluya riesgos y/o peligros asociados" required>{{ $pf('description_unsafe_cond') }}</textarea>
                        @error('description_unsafe_cond')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: CLASIFICACIÓN Y RIESGO ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-primary text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-ico'])
                <span>Clasificación y Riesgo</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    {{-- (2026-07-13) Catálogo ÚNICO de eventos posibles (agrupado por contexto).
                         Reemplaza el viejo select de category_name Y el multiselect standards[]:
                         al elegir el evento, el servidor etiqueta su norma (badge/código) y
                         adjunta todas las normas del evento al pivote. OPCIONAL (como antes). --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Evento / peligro (catálogo)</label>
                        @include('componentes._event-picker', [
                            'hazardEvents' => $hazardEvents ?? collect(),
                            'name'         => 'hazard_event_id',
                            'selected'     => old('hazard_event_id', $report->hazard_event_id ?? null),
                            'required'     => false,
                        ])
                        <div class="form-text">Al elegir el evento se etiqueta automáticamente su norma (CSATF/STPS/OSHA).</div>
                    </div>

                    {{-- (2026-07-09) Matriz de riesgo 5×5: el NIVEL lo calcula el servidor a partir
                         de Probabilidad (A–E) × Consecuencia (1–5). El "Nivel de riesgo" de abajo es
                         un respaldo manual (se usa sólo si no eliges los dos ejes). --}}
                    <div class="col-12 col-md-4">
                        <label for="likelihood" class="form-label fw-semibold">Probabilidad</label>
                        @php $selLk = $pf('likelihood'); @endphp
                        <select name="likelihood" id="likelihood" class="form-select">
                            <option value="">—</option>
                            @foreach(['A'=>'A · Casi seguro','B'=>'B · Probable','C'=>'C · Moderado','D'=>'D · Improbable','E'=>'E · Raro'] as $k=>$lbl)
                                <option value="{{ $k }}" {{ $selLk === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="consequence" class="form-label fw-semibold">Consecuencia</label>
                        @php $selCq = (string) $pf('consequence'); @endphp
                        <select name="consequence" id="consequence" class="form-select">
                            <option value="">—</option>
                            {{-- (2026-07-23) BOMBA: las claves '1'..'5' son ENTERO tras la coerción de PHP;
                                 `$selCq === $k` (string vs int) nunca preseleccionaba y perdía la
                                 consecuencia al re-guardar. Se castea la clave a string en ambos lados. --}}
                            @foreach(['1'=>'1 · Insignificante','2'=>'2 · Menor','3'=>'3 · Moderado','4'=>'4 · Mayor','5'=>'5 · Catastrófico'] as $k=>$lbl)
                                <option value="{{ $k }}" {{ (string) $k === $selCq ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- (2026-07-13) Coherencia: el NIVEL lo calcula el servidor (trait
                         CalculatesRiskMatrix) desde Probabilidad × Consecuencia con la matriz
                         canónica 5×5. Cuando eliges los dos ejes, este control se AUTOCALCULA y
                         queda de SÓLO LECTURA (bloqueado); un hidden espejo envía el valor. Si NO
                         eliges ambos ejes, sigue editable como respaldo manual. --}}
                    <div class="col-12 col-md-4">
                        <label for="risk_level" class="form-label fw-semibold">Nivel de riesgo <span class="cc-muted fw-normal">(auto)</span></label>
                        @php $selRl = $pf('risk_level'); @endphp
                        <select name="risk_level" id="risk_level" class="form-select">
                            <option value="" {{ $selRl == '' ? 'selected' : '' }}>—</option>
                            <option value="Bajo" {{ $selRl == 'Bajo' ? 'selected' : '' }}>Bajo</option>
                            <option value="Medio" {{ $selRl == 'Medio' ? 'selected' : '' }}>Medio</option>
                            <option value="Alto" {{ $selRl == 'Alto' ? 'selected' : '' }}>Alto</option>
                            <option value="Extremo" {{ $selRl == 'Extremo' ? 'selected' : '' }}>Extremo</option>
                        </select>
                        {{-- Espejo: cuando el <select> queda bloqueado (auto) no se envía; este hidden
                             lleva el valor calculado. El JS le pone/quita name="risk_level" según el modo. --}}
                        <input type="hidden" id="risk_level_mirror" value="{{ $selRl }}">
                        <div class="form-text" id="risk_level_help">Se calcula solo si eliges Probabilidad y Consecuencia; si no, captúralo a mano.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="action_status" class="form-label fw-semibold">Estado inicial de la acción correctiva</label>
                        @php $selStatus = $pf('action_status', 'Abierto'); @endphp
                        <select name="action_status" id="action_status" class="form-select">
                            <option value="Abierto" {{ $selStatus == 'Abierto' ? 'selected' : '' }}>Abierto</option>
                            <option value="En proceso" {{ $selStatus == 'En proceso' ? 'selected' : '' }}>En proceso</option>
                            <option value="Cerrado" {{ $selStatus == 'Cerrado' ? 'selected' : '' }}>Cerrado</option>
                        </select>
                    </div>

                    {{-- (2026-07-13) El multiselect standards[] se ELIMINÓ: las normas ahora se
                         adjuntan solas desde el evento elegido arriba (applyHazardEvent →
                         standardables). Ya no hay doble captura de normas. --}}
                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: ACCIONES Y SEGUIMIENTO ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-secondary text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-ico'])
                <span>Acciones y Seguimiento</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="action_taken" class="form-label fw-semibold">Acción tomada</label>
                        <textarea class="form-control" id="action_taken" name="action_taken" rows="3" placeholder="Nota cualquier acción inmediata tomada para minimizar los riesgos">{{ $pf('action_taken') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label for="corrective_action" class="form-label fw-semibold">Acciones correctivas</label>
                        <textarea class="form-control" id="corrective_action" name="corrective_action" rows="3" placeholder="Acciones correctivas a largo plazo / plan de acción">{{ $pf('corrective_action') }}</textarea>
                    </div>

                    {{-- (2026-07-24) RESPONSABLE + FECHA COMPROMISO de la acción correctiva. Cuelgan
                         del ActionItem auto-generado (source_field = corrective_action) y se IMPRIMEN. --}}
                    @php
                        $ccItem  = ($isEdit && $report && $report->relationLoaded('actionItems')) ? $report->actionItems->firstWhere('source_field', 'corrective_action') : null;
                        $ccOwner = old('corrective_owner_id', $ccItem ? $ccItem->owner_id : '');
                        $ccDue   = old('corrective_due_date', ($ccItem && $ccItem->due_date) ? \Carbon\Carbon::parse($ccItem->due_date)->format('Y-m-d') : '');
                    @endphp
                    <div class="col-12 col-md-6">
                        <label for="corrective_owner_id" class="form-label fw-semibold">Responsable de la acción correctiva</label>
                        <select name="corrective_owner_id" id="corrective_owner_id" class="form-select">
                            <option value="">— Sin asignar —</option>
                            @foreach(($crew ?? collect()) as $u)
                                <option value="{{ $u->id }}" {{ (string) $u->id === (string) $ccOwner ? 'selected' : '' }}>{{ trim($u->name . ' ' . ($u->lname ?? '')) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="corrective_due_date" class="form-label fw-semibold">Fecha compromiso</label>
                        <input type="date" name="corrective_due_date" id="corrective_due_date" class="form-control" value="{{ $ccDue }}">
                        <div class="form-text">Quién se hace cargo y para cuándo. Se imprime en el documento.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: EVIDENCIA FOTOGRÁFICA ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico'])
                <span>Evidencia Fotográfica</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="main_image" class="form-label fw-semibold">Evidencia principal</label>
                        @if($isEdit && $report && $report->main_image_path)
                            {{-- (2026-07-14) Edición: se muestra la imagen actual. Subir una nueva la reemplaza; dejarlo vacío la conserva. --}}
                            <div class="mb-2">
                                <img src="{{ $report->main_image_path }}" alt="Evidencia actual" class="img-thumbnail" style="max-height:140px;">
                                <div class="form-text">Imagen actual. Sube otra para reemplazarla o déjalo vacío para conservarla.</div>
                            </div>
                        @endif
                        <input type="file" class="form-control" id="main_image" name="main_image" accept="image/*,.heic,.heif" data-cc-photo>
                        <div class="form-text">Esta será la imagen principal del reporte.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Evidencias adicionales (si es necesario)</label>
                        <div id="additional_images_container"></div>
                        {{-- d-grid = botón de ancho completo en móvil; en md+ vuelve a auto. --}}
                        <div class="d-grid d-md-block">
                            <button type="button" class="btn btn-outline-secondary" onclick="addImageField()">＋ Agregar otra imagen</button>
                        </div>
                        <div class="form-text">Puedes agregar varias imágenes como evidencia.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: FIRMA (AUTOFIRMA — sistema cerrado) ============ --}}
        {{-- Autor y fecha se registran del lado del servidor con el usuario autenticado.
             Estos campos son SOLO LECTURA (sin name=), no se envían ni se pueden falsear. --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico'])
                <span>Firma</span>
            </h3>
            <div class="card-body">
                <div class="alert alert-secondary small mb-3 d-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico'])
                    @if($isEdit)
                        <span>La firma original se <strong>conserva</strong> al editar: registra <strong>quién</strong> y <strong>cuándo</strong> capturó el reporte y no se sobrescribe.</span>
                    @else
                        <span>Este reporte se firma <strong>automáticamente</strong> con tu usuario y la fecha de hoy.
                        No es editable: así queda registro fiable de <strong>quién</strong> y <strong>cuándo</strong> capturó la información.</span>
                    @endif
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label fw-semibold">Completado por</label>
                        <input type="text" class="form-control bg-light" value="{{ $isEdit && $report ? ($report->make_by ?? auth()->user()->name) : auth()->user()->name }}" readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Fecha realizado</label>
                        <input type="text" class="form-control bg-light" value="{{ $isEdit && $report && $report->make_date ? $report->make_date->format('d/m/Y') : date('d/m/Y') }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        {{-- Acciones del formulario: apiladas a lo ancho en móvil, a la derecha en escritorio --}}
        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-5">
            <a href="{{ route('unsafenotifications.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary fw-bold">{{ $isEdit ? 'Guardar cambios' : 'Enviar Notificación' }}</button>
        </div>
    </form>
</div>

<script>
    let imageCounter = 0;

    // Agrega un input-group (archivo + botón quitar) por cada evidencia adicional.
    // El name="additional_images[]" NO cambia: es el que valida el controlador.
    function addImageField() {
        imageCounter++;
        const container = document.getElementById('additional_images_container');
        const div = document.createElement('div');
        div.classList.add('input-group', 'mb-2');
        div.innerHTML = `
            <input type="file" class="form-control" id="additional_image_${imageCounter}" name="additional_images[]" accept="image/*,.heic,.heif" aria-label="Imagen adicional ${imageCounter}" data-cc-photo>
            <button type="button" class="btn btn-outline-danger" onclick="removeImageField(this)" title="Eliminar">✕</button>
        `;
        container.appendChild(div);
    }

    function removeImageField(button) {
        button.parentNode.remove();
    }

    // (2026-07-13) Coherencia — GPS HONESTO (igual que Hazard): respaldo manual ↔ hidden del
    // GPS silencioso + AVISO VISIBLE del estado. Ya no falla en silencio: si la geolocalización
    // no responde (permiso denegado / interior), se muestra una advertencia visible y se abre la
    // captura manual. La ubicación sigue siendo OBLIGATORIA (required en el FormRequest).
    (function () {
        var box = document.querySelector('[data-geo-silent]');
        if (!box) return;
        var hLat = box.querySelector('input[name="latitude"]');
        var hLng = box.querySelector('input[name="longitude"]');
        var mLat = document.getElementById('gps_lat_manual');
        var mLng = document.getElementById('gps_lng_manual');
        var state = document.getElementById('gps_state');
        var notice = document.getElementById('gps_notice');
        var noticeIcon = document.getElementById('gps_notice_icon');
        var noticeText = document.getElementById('gps_notice_text');
        var details = document.getElementById('gps_details');
        if (!hLat || !hLng || !mLat || !mLng) return;

        function setNotice(cls, icon, html, forceShow) {
            if (!notice) return;
            // (2026-07-14) El aviso arranca OCULTO (d-none). Sólo se revela si la
            // geolocalización FALLA (forceShow) o si ya estaba visible; nunca aparece
            // "antes de fallar" ni bloquea el submit.
            if (notice.classList.contains('d-none') && !forceShow) return;
            notice.className = 'alert ' + cls + ' d-flex align-items-center gap-2 py-2 px-3 mb-2 small';
            if (noticeIcon) noticeIcon.textContent = icon;
            if (noticeText) noticeText.innerHTML = html;
        }
        function hasCoords() { return !!((hLat.value && hLng.value) || (mLat.value && mLng.value)); }

        function refresh() {
            // Espejo bidireccional manual ↔ hidden (respeta lo que el usuario escribe a mano).
            if (mLat.value !== '') hLat.value = mLat.value;
            if (mLng.value !== '') hLng.value = mLng.value;
            if (hLat.value && mLat.value === '') mLat.value = hLat.value;
            if (hLng.value && mLng.value === '') mLng.value = hLng.value;
            if (hasCoords()) {
                var la = (+hLat.value).toFixed(4), ln = (+hLng.value).toFixed(4);
                setNotice('alert-success', '✓', 'Ubicación GPS capturada (' + la + ', ' + ln + '). Ajústala si es necesario.');
                if (state) state.textContent = 'detectada (' + la + ', ' + ln + ')';
            }
        }
        function fail() {
            if (hasCoords()) return;
            // forceShow=true: aquí SÍ se revela el aviso (la geolocalización falló).
            setNotice('alert-warning', '⚠️', 'No pudimos obtener tu ubicación automáticamente (permiso denegado o sin señal). <strong>Ingrésala manualmente</strong> abajo.', true);
            if (state) state.textContent = 'ingrésala manualmente';
            if (details) details.open = true;
        }

        mLat.addEventListener('input', function () { if (failTimer) { clearTimeout(failTimer); failTimer = null; } refresh(); });
        mLng.addEventListener('input', function () { if (failTimer) { clearTimeout(failTimer); failTimer = null; } refresh(); });
        // El GPS silencioso avisa con este evento al capturar la posición por detrás.
        box.addEventListener('geo:captured', function () { if (failTimer) { clearTimeout(failTimer); failTimer = null; } refresh(); });

        var failTimer = null;
        if (hasCoords()) {
            // old() tras un error de validación: ya hay coordenadas, refleja el éxito.
            refresh();
        } else {
            // Aún sin coordenadas: da tiempo al GPS silencioso (timeout de 12 s en el JS de geo);
            // si a los ~14 s no hay nada, muestra la advertencia visible y abre la captura manual.
            failTimer = setTimeout(function () { if (!hasCoords()) fail(); }, 14000);
            // Si el permiso ya está denegado, no esperes: avisa de inmediato.
            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'geolocation' }).then(function (st) {
                    if (st.state === 'denied' && !hasCoords()) { if (failTimer) { clearTimeout(failTimer); failTimer = null; } fail(); }
                }).catch(function () { /* sin permisos API: se queda con el timeout */ });
            }
        }
        // Respaldo periódico por si el hidden se llena por una ruta que no dispara el evento.
        setInterval(refresh, 1500);
    })();

    // (2026-07-13) Coherencia — MATRIZ canónica 5×5 (preview de cliente; el servidor recalcula
    // y manda vía el trait CalculatesRiskMatrix). Cuando eliges Probabilidad (A–E) y Consecuencia
    // (1–5), el Nivel de riesgo se AUTOCALCULA y queda de SÓLO LECTURA (bloqueado); un hidden
    // espejo envía el valor. Sin ambos ejes, el select sigue editable como respaldo manual.
    (function () {
        // Grid Amazon MGM: fila = likelihood A–E, col = consequence 1–5. L/M/H/E → Bajo/Medio/Alto/Extremo.
        var GRID = {
            A: ['Medio', 'Alto', 'Alto', 'Extremo', 'Extremo'],
            B: ['Medio', 'Medio', 'Alto', 'Alto', 'Extremo'],
            C: ['Bajo', 'Medio', 'Medio', 'Alto', 'Extremo'],
            D: ['Bajo', 'Medio', 'Medio', 'Alto', 'Alto'],
            E: ['Bajo', 'Bajo', 'Medio', 'Medio', 'Alto']
        };
        var lk = document.getElementById('likelihood');
        var cq = document.getElementById('consequence');
        var rl = document.getElementById('risk_level');
        var mirror = document.getElementById('risk_level_mirror');
        var help = document.getElementById('risk_level_help');
        if (!lk || !cq || !rl) return;

        function apply() {
            var a = lk.value, c = cq.value;
            if (GRID[a] && c) {
                var lvl = GRID[a][parseInt(c, 10) - 1];
                // AUTO: bloquea el select y envía el valor por el hidden espejo (los disabled no se envían).
                rl.value = lvl;
                rl.disabled = true;
                rl.removeAttribute('name');
                rl.classList.add('bg-light');
                if (mirror) { mirror.setAttribute('name', 'risk_level'); mirror.value = lvl; }
                if (help) help.textContent = 'Calculado por la matriz 5×5 (Probabilidad × Consecuencia): ' + lvl + '.';
            } else {
                // MANUAL: reactiva el select como respaldo y desconecta el espejo.
                rl.disabled = false;
                rl.setAttribute('name', 'risk_level');
                rl.classList.remove('bg-light');
                if (mirror) mirror.removeAttribute('name');
                if (help) help.textContent = 'Se calcula solo si eliges Probabilidad y Consecuencia; si no, captúralo a mano.';
            }
        }
        lk.addEventListener('change', apply);
        cq.addEventListener('change', apply);
        apply(); // estado inicial (respeta old() tras un error de validación)
    })();

    // (2026-07-15) Tras un error de validación: lleva al primer campo marcado y enfócalo.
    (function () {
        var firstErr = document.querySelector('.is-invalid') || document.querySelector('.alert-danger');
        if (!firstErr) return;
        firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (firstErr.classList.contains('is-invalid') && typeof firstErr.focus === 'function') {
            try { firstErr.focus({ preventScroll: true }); } catch (e) { firstErr.focus(); }
        }
    })();
</script>
@endsection

@push('scripts')
{{-- HEIC (iPhone): conversión a JPEG en el navegador antes de subir (el servidor no decodifica HEIC). --}}
<script src="/js/cc-photo.js"></script>
<script src="/js/cc-photo-auto.js"></script>
@endpush
