@extends('layouts.app')

@section('content')

{{-- (2026-07-14) Pilar 1 — Fase 2: la MISMA vista crea y edita. $isEdit alterna el
     destino del <form> (store vs update+PUT) y el prefill: old() gana; en edición cae
     al valor guardado del reporte. Los name="" NO cambian. --}}
@php
    $isEdit = $isEdit ?? false;
    $report = $injuryReport ?? null;
    $formAction = ($isEdit && $report) ? route('injury_reports.update', $report->id) : route('injury_reports.store');

    // (2026-07-31) Pilar 1 — captura ágil en 2 fases (mismo patrón que unsafecondnotification).
    // El flag progressive_capture (default ON) relaja a nullable los campos "de fondo" en la
    // Fase 1 (store de set); en Fase 2 (edición) o con el flag OFF se valida el set COMPLETO.
    // Se espeja EXACTO el criterio del servidor: InjuryReportController::store() valida con
    // strict = !$progressive y update() con strict = true; validationRules() usa
    // $req = $strict ? 'required' : 'nullable'. Así los `*` y `required` del cliente se gatean
    // con $strict y NO bloquean la captura ágil en set (que deja el reporte pending_compliance).
    $progressive = \App\Support\Features::enabled('progressive_capture');
    $strict = !$progressive || $isEdit;
    $req = $strict ? 'required' : '';

    // Prefill escalar: old() gana; en edición cae al valor del reporte; si no, $default.
    $v = function ($field, $default = '') use ($report) {
        return old($field, $report ? ($report->{$field} ?? $default) : $default);
    };

    // Prefill de colecciones/JSON (edición). old() con notación de punto recupera el input
    // previo tras un error de validación; el default cae al valor guardado del reporte.
    $rInjuryTypes = old('injury_type', ($report && is_array($report->injury_type)) ? $report->injury_type : []);
    $rPpe         = ($report && is_array($report->ppe_details)) ? $report->ppe_details : [];
    $rPpeTypes    = old('ppe_details.types', (isset($rPpe['types']) && is_array($rPpe['types'])) ? $rPpe['types'] : []);
    $rRca         = ($report && is_array($report->root_cause_analysis)) ? $report->root_cause_analysis : [];
    $rRcaCats     = old('root_cause_analysis.categories', (isset($rRca['categories']) && is_array($rRca['categories'])) ? $rRca['categories'] : []);
    // Horas: la columna TIME devuelve 'HH:MM:SS'; el input type=time espera 'HH:MM'.
    $rTime        = old('time', ($report && $report->time) ? substr($report->time, 0, 5) : '');
    $rCallTime    = old('call_time', ($report && $report->call_time) ? substr($report->call_time, 0, 5) : '');
@endphp

{{-- (2026-07-07) Rediseño: formulario homologado al estilo moderno de la app
     (cards shadow-sm con header de color + emoji, row g-3, labels fw-semibold),
     mismo patrón que admin/scoutings/_form.blade.php. Los name="" de los inputs
     NO cambiaron: el controlador valida esos mismos nombres. Se agregó old() a
     todos los campos para no perder lo tecleado tras un error de validación. --}}

<style>
    /* Dropdown de sugerencias del autocompletado de "Persona Afectada" */
    .suggestion-item {
        cursor: pointer;
        border-left: none;
        border-right: none;
    }
    .suggestion-item:hover, .suggestion-item.active {
        background-color: #f8f9fa;
    }
    #name_suggestions {
        box-shadow: 0 5px 10px rgba(0,0,0,0.1);
    }
    #name_suggestions::-webkit-scrollbar {
        width: 8px;
    }
    #name_suggestions::-webkit-scrollbar-thumb {
        background-color: #ddd;
        border-radius: 4px;
    }
</style>

<div class="container py-4">

    {{-- Encabezado de página (patrón del scouting create) --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-0 fw-bold">{{ $isEdit ? 'Editar Reporte de Accidente / Lesión' : 'Reporte de Accidente / Lesión' }}</h2>
            <p class="cc-muted mb-0 small">{{ $isEdit ? 'Completa la carga de compliance del incidente (Injury Report)' : 'Registro de incidente con persona afectada (Injury Report)' }}</p>
        </div>
        <a href="{{ route('injury_reports.index') }}" class="btn btn-outline-secondary">&larr; Volver</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Errores al enviar:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" @if(!$isEdit) data-cc-drafts="injury-report" @endif>
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        {{-- (captura fluida · Paso A) Borradores en el dispositivo. Solo en alta. --}}
        @if(!$isEdit)
            @include('componentes._drafts-tray', ['draftType' => 'injury-report'])
        @endif

        {{-- ============ SECCIÓN: DATOS GENERALES Y LOCACIÓN ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico'])
                <span>Datos Generales y Locación</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="production_title" class="form-label fw-semibold">Producción @if($strict)<span class="text-danger">*</span>@endif</label>
                        <input type="text" name="production_title" id="production_title" class="form-control @error('production_title') is-invalid @enderror" value="{{ $v('production_title') }}" {{ $req }}>
                        @error('production_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        {{-- id estable "location": es el suggestTarget del GPS silencioso de abajo. --}}
                        <label for="location" class="form-label fw-semibold">Locación</label>
                        <input type="text" name="location" id="location" class="form-control" value="{{ $v('location') }}">
                        {{-- El JS escribe aquí "📍 Detectamos que estás en: X (a Ym)" cuando hay
                             un scouting a ≤500 m. Vacío si no hay detección. --}}
                        <small id="geo-note" class="cc-muted d-block"></small>
                        {{-- GPS SILENCIOSO (cero fricción): sin botones ni coordenadas visibles.
                             Detecta la posición por detrás, guarda lat/lng/gps_address en hidden
                             y sugiere el NOMBRE de la locación scouteada en #location (solo si
                             está vacía). Lógica en public/js/crewcare-geo.js. --}}
                        @include('componentes._geo-capture', [
                            'mode' => 'silent',
                            'suggestTarget' => '#location',
                            'noteTarget' => '#geo-note',
                            'latValue' => old('latitude', $report->latitude ?? null),
                            'lngValue' => old('longitude', $report->longitude ?? null),
                            'gpsAddressValue' => old('gps_address', $report->gps_address ?? null),
                        ])
                    </div>
                    <div class="col-12 col-md-6">
                        {{-- (2026-07-13) COHERENCIA: la etiqueta "Tipo de evento" no casaba con lo
                             que guarda la columna (production_dates). Se re-etiqueta a "Fechas de producción". --}}
                        <label for="production_dates" class="form-label fw-semibold">Fechas de producción</label>
                        <input type="text" name="production_dates" id="production_dates" class="form-control" value="{{ $v('production_dates') }}" placeholder="Ej.: 12–20 jul 2026">
                    </div>
                    {{-- (2026-07-13) COHERENCIA: departamento pasa de texto libre a catálogo.
                         Se sigue guardando el NAME (string) en injury_reports.department.
                         Si el valor previo (old) no está en el catálogo, se conserva como
                         opción propia para no perder lo tecleado; opción final "Otro (no listado)". --}}
                    @php
                        $selectedDept   = $v('department');
                        $deptCatalog    = $departments->pluck('name');
                        $deptInCatalog  = ($selectedDept !== null && $selectedDept !== '' && $deptCatalog->contains($selectedDept));
                    @endphp
                    <div class="col-12 col-md-6">
                        <label for="department" class="form-label fw-semibold">Departamento de producción</label>
                        <select name="department" id="department" class="form-select">
                            <option value="">— Selecciona —</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->name }}" {{ $selectedDept === $d->name ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                            @if($selectedDept !== null && $selectedDept !== '' && !$deptInCatalog && $selectedDept !== 'Otro (no listado)')
                                <option value="{{ $selectedDept }}" selected>{{ $selectedDept }}</option>
                            @endif
                            <option value="Otro (no listado)" {{ $selectedDept === 'Otro (no listado)' ? 'selected' : '' }}>Otro (no listado)</option>
                        </select>
                    </div>
                    {{-- (2026-07-09) Patrón/contratista del lesionado (aviso IMSS/STPS). --}}
                    <div class="col-12 col-md-6">
                        <label for="employer_name" class="form-label fw-semibold">Patrón / empresa contratante</label>
                        <input type="text" name="employer_name" id="employer_name" class="form-control" value="{{ $v('employer_name') }}" placeholder="Empleador del lesionado (para aviso IMSS/STPS)">
                    </div>

                    {{-- (2026-07-12) MÓDULO 11: Justificación de ubicación manual. Respaldo del GPS
                         silencioso: si el dispositivo no capturó coordenadas, la ubicación se
                         describe a mano y se vuelve obligatoria. Gated por columna (defensivo prod). --}}
                    @if(\Illuminate\Support\Facades\Schema::hasColumn('injury_reports', 'manual_location_justification'))
                    <div class="col-12">
                        <label for="manual_location_justification" class="form-label fw-semibold">Justificación de ubicación (si no hay GPS)</label>
                        <textarea name="manual_location_justification" id="manual_location_justification" class="form-control" rows="2" placeholder="Si el dispositivo no capturó coordenadas GPS, describe con precisión dónde ocurrió el incidente.">{{ $v('manual_location_justification') }}</textarea>
                        <div class="form-text">Obligatoria cuando no se detecta ubicación GPS automáticamente.</div>
                    </div>
                    @endif

                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: DETALLES DEL INCIDENTE ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-warning text-dark fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'clock', 'class' => 'cc-ico'])
                <span>Detalles del Incidente</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label for="incident_date" class="form-label fw-semibold">Fecha del incidente @if($strict)<span class="text-danger">*</span>@endif</label>
                        <input type="date" name="incident_date" id="incident_date" class="form-control @error('incident_date') is-invalid @enderror" value="{{ $v('incident_date') }}" {{ $req }}>
                        @error('incident_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="reported_date" class="form-label fw-semibold">Fecha reportada @if($strict)<span class="text-danger">*</span>@endif</label>
                        <input type="date" name="reported_date" id="reported_date" class="form-control @error('reported_date') is-invalid @enderror" value="{{ $v('reported_date') }}" {{ $req }}>
                        @error('reported_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="time" class="form-label fw-semibold">Hora</label>
                        <input type="time" name="time" id="time" class="form-control" value="{{ $rTime }}">
                    </div>
                    {{-- (2026-07-09) Hora de llamado / inicio de turno → cálculo de fatiga (horas trabajadas antes). --}}
                    <div class="col-12 col-md-4">
                        <label for="call_time" class="form-label fw-semibold">Hora de llamado / inicio de turno</label>
                        <input type="time" name="call_time" id="call_time" class="form-control" value="{{ $rCallTime }}">
                        <div class="form-text">Se usa para calcular las horas trabajadas antes del incidente (fatiga).</div>
                    </div>
                    <div class="col-12">
                        <label for="incident_location" class="form-label fw-semibold">Ubicación del incidente</label>
                        <input type="text" name="incident_location" id="incident_location" class="form-control" placeholder="Sea específico: área, set, foro, pasillo…" value="{{ $v('incident_location') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: PERSONA AFECTADA ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-primary text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico'])
                <span>Persona Afectada</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    {{-- position-relative: ancla el dropdown absoluto de sugerencias a esta columna --}}
                    <div class="col-12 col-md-6 position-relative">
                        <label for="name_input" class="form-label fw-semibold">Nombre @if($strict)<span class="text-danger">*</span>@endif</label>
                        <input type="text" name="name" id="name_input" class="form-control @error('name') is-invalid @enderror" value="{{ $v('name') }}" {{ $req }} autocomplete="off">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <input type="hidden" name="user_id" id="user_id" value="{{ $v('user_id') }}">
                        <div id="name_suggestions" class="list-group mt-1 d-none" style="position: absolute; z-index: 1000; width: 100%; max-height: 200px; overflow-y: auto;"></div>
                        <small class="cc-muted">Escribe para buscar en el crew; al elegir se autollenan puesto, nacimiento y teléfono.</small>
                    </div>
                    {{-- (2026-07-13) COHERENCIA: puesto pasa de texto libre a catálogo (Position).
                         Se sigue guardando el NAME (string) en injury_reports.position. El
                         autocompletado del crew inyecta el puesto vía JS (setPositionValue), que
                         conserva valores fuera del catálogo como opción temporal. Opción final
                         "Otro (no listado)". Nota: al elegir un miembro del crew el servidor
                         sobreescribe este puesto con su puestodepartamento real. --}}
                    @php
                        $selectedPos  = $v('position');
                        $posCatalog   = $positions->pluck('name');
                        $posInCatalog = ($selectedPos !== null && $selectedPos !== '' && $posCatalog->contains($selectedPos));
                    @endphp
                    <div class="col-12 col-md-6">
                        <label for="position_input" class="form-label fw-semibold">Puesto</label>
                        <select name="position" id="position_input" class="form-select">
                            <option value="">— Selecciona —</option>
                            @foreach($positions as $pos)
                                <option value="{{ $pos->name }}" {{ $selectedPos === $pos->name ? 'selected' : '' }}>{{ $pos->name }}</option>
                            @endforeach
                            @if($selectedPos !== null && $selectedPos !== '' && !$posInCatalog && $selectedPos !== 'Otro (no listado)')
                                <option value="{{ $selectedPos }}" selected class="js-temp-position">{{ $selectedPos }}</option>
                            @endif
                            <option value="Otro (no listado)" {{ $selectedPos === 'Otro (no listado)' ? 'selected' : '' }}>Otro (no listado)</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="dob_input" class="form-label fw-semibold">Fecha de nacimiento</label>
                        <input type="date" name="dob" id="dob_input" class="form-control" value="{{ $v('dob') }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="phone_input" class="form-label fw-semibold">Teléfono</label>
                        <input type="tel" inputmode="numeric" name="phone" id="phone_input" class="form-control" value="{{ $v('phone') }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="other_input" class="form-label fw-semibold">Otro (especificar)</label>
                        <input type="text" name="other" id="other_input" class="form-control" value="{{ $v('other') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: TESTIGOS (MÓDULO 7) ============ --}}
        {{-- Repeater simple (JS vanilla). Opcional: si no se agrega ninguna fila, el
             arreglo 'witnesses' no se envía y no dispara validación. Gated por tabla. --}}
        @if(\Illuminate\Support\Facades\Schema::hasTable('witnesses'))
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-info text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico'])
                <span>Testigos</span>
            </h3>
            <div class="card-body">
                <p class="cc-muted small mb-3">Registra a quienes presenciaron el incidente (opcional). El <strong>teléfono</strong> y la <strong>declaración</strong> son datos sensibles con acceso restringido (H&amp;S / médico).</p>
                <div id="witnesses_container">
                    @php
                        // Edición: siembra los testigos guardados; old() gana tras un error de validación.
                        $reportWitnesses = ($report && $report->relationLoaded('witnesses'))
                            ? $report->witnesses->map(function ($w) {
                                return ['name' => $w->name, 'phone' => $w->phone, 'statement' => $w->statement];
                              })->toArray()
                            : [];
                        $oldWitnesses = old('witnesses', $reportWitnesses);
                    @endphp
                    @foreach($oldWitnesses as $wi => $w)
                    <div class="witness-row border rounded p-3 mb-2">
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold">Nombre</label>
                                <input type="text" name="witnesses[{{ $wi }}][name]" class="form-control" value="{{ $w['name'] ?? '' }}" maxlength="255">
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label small fw-semibold">Teléfono</label>
                                <input type="text" name="witnesses[{{ $wi }}][phone]" class="form-control" value="{{ $w['phone'] ?? '' }}" maxlength="50">
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label small fw-semibold">Declaración</label>
                                <input type="text" name="witnesses[{{ $wi }}][statement]" class="form-control" value="{{ $w['statement'] ?? '' }}" maxlength="2000">
                            </div>
                        </div>
                        <div class="text-end mt-2">
                            <button type="button" class="btn btn-sm btn-outline-danger js-remove-witness">✕ Quitar</button>
                        </div>
                    </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="add_witness_btn">＋ Agregar testigo</button>
            </div>
        </div>
        @endif

        {{-- ============ SECCIÓN: DETALLES DE LA LESIÓN ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-danger text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'heart-pulse', 'class' => 'cc-ico'])
                <span>Detalles de la Lesión</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="body_part" class="form-label fw-semibold">Parte del cuerpo afectada</label>
                        <input type="text" name="body_part" id="body_part" class="form-control" value="{{ $v('body_part') }}">
                    </div>
                    <div class="col-12">
                        <fieldset>
                            {{-- (2026-07-31) injury_type es 'required|array' en Fase 2/edición (strict) y
                                 nullable en Fase 1. Se gatea el `*` con $strict. NO se pone `required`
                                 nativo en los checkboxes: en un grupo forzaría marcar TODOS; la regla
                                 "al menos uno" la valida el servidor y aquí se refleja con el @error. --}}
                            <label class="form-label fw-semibold d-block">Tipo de lesión o enfermedad @if($strict)<span class="text-danger">*</span>@endif</label>
                            @php $oldInjuryTypes = $rInjuryTypes; @endphp
                            <div class="row g-2">
                                @foreach(['Contusión', 'Dislocación', 'Esguince/Distensión', 'Abrasión', 'Interna', 'Fractura', 'Amputación', 'Cuerpo extraño', 'Corte', 'Quemadura', 'Reacción química'] as $type)
                                    {{-- col-6 en móvil (2 columnas legibles a 375px), 3 columnas en md+ --}}
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="injury_type[]" value="{{ $type }}" id="injury_{{ $loop->index }}" {{ in_array($type, $oldInjuryTypes) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="injury_{{ $loop->index }}">{{ $type }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('injury_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </fieldset>
                    </div>

                    {{-- (2026-07-09) EPP estructurado (NOM-017 / Bulletin #21). --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold d-block">Equipo de protección personal (EPP)</label>
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label for="ppe_worn" class="form-label small">¿Portaba EPP?</label>
                                <select name="ppe_details[worn]" id="ppe_worn" class="form-select">
                                    <option value="">—</option>
                                    @php $rPpeWorn = old('ppe_details.worn', $rPpe['worn'] ?? ''); @endphp
                                    <option value="si" {{ $rPpeWorn === 'si' ? 'selected' : '' }}>Sí</option>
                                    <option value="no" {{ $rPpeWorn === 'no' ? 'selected' : '' }}>No</option>
                                    <option value="na" {{ $rPpeWorn === 'na' ? 'selected' : '' }}>N/A</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-8">
                                <label for="ppe_condition" class="form-label small">Estado / observaciones del EPP</label>
                                <input type="text" name="ppe_details[condition]" id="ppe_condition" class="form-control" value="{{ old('ppe_details.condition', $rPpe['condition'] ?? '') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small d-block">Tipos de EPP involucrado</label>
                                @php $oldPpe = (array) $rPpeTypes; @endphp
                                @foreach(['Casco','Guantes','Lentes','Botas','Arnés','Protección auditiva','Chaleco','Respirador'] as $ppe)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="ppe_details[types][]" value="{{ $ppe }}" id="ppe_{{ $loop->index }}" {{ in_array($ppe, $oldPpe) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="ppe_{{ $loop->index }}">{{ $ppe }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: TRATAMIENTO ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-danger text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico'])
                <span>Tratamiento</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label for="treatment_type" class="form-label fw-semibold">Tipo de tratamiento</label>
                        <input type="text" name="treatment_type" id="treatment_type" class="form-control" value="{{ $v('treatment_type') }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="treatment_by" class="form-label fw-semibold">Primer respondiente</label>
                        <input type="text" name="treatment_by" id="treatment_by" class="form-control" value="{{ $v('treatment_by') }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="hospital" class="form-label fw-semibold">Doctor / Hospital</label>
                        <input type="text" name="hospital" id="hospital" class="form-control" value="{{ $v('hospital') }}">
                    </div>
                    <div class="col-12">
                        <label for="treatment_comments" class="form-label fw-semibold">Comentarios</label>
                        <textarea name="treatment_comments" id="treatment_comments" class="form-control" rows="3">{{ $v('treatment_comments') }}</textarea>
                    </div>

                    {{-- (2026-07-09) Registrabilidad OSHA 300/301: nivel de atención + días.
                         is_recordable se determina SOLO en el servidor a partir de estos campos. --}}
                    <div class="col-12 col-md-4">
                        <label for="treatment_level" class="form-label fw-semibold">Nivel de atención (OSHA)</label>
                        <select name="treatment_level" id="treatment_level" class="form-select">
                            <option value="">—</option>
                            @php $rTreatmentLevel = $v('treatment_level'); @endphp
                            @foreach(['first_aid'=>'Primeros auxilios','medical_treatment'=>'Tratamiento médico','hospitalization'=>'Hospitalización','fatality'=>'Fatalidad'] as $k=>$lbl)
                                <option value="{{ $k }}" {{ $rTreatmentLevel === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Determina la registrabilidad OSHA 300/301 (automática).</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="days_away_from_work" class="form-label fw-semibold">Días de ausencia</label>
                        <input type="number" min="0" name="days_away_from_work" id="days_away_from_work" class="form-control" value="{{ old('days_away_from_work', $report->days_away_from_work ?? 0) }}">
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="days_restricted_work" class="form-label fw-semibold">Días de trabajo restringido</label>
                        <input type="number" min="0" name="days_restricted_work" id="days_restricted_work" class="form-control" value="{{ old('days_restricted_work', $report->days_restricted_work ?? 0) }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: DESCRIPCIÓN Y CAUSAS ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico'])
                <span>Descripción del Incidente</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        {{-- (2026-07-31) what_happened es el ÚNICO campo SIEMPRE obligatorio (regla fija
                             'required' en InjuryReportController), también en Fase 1 ágil. El `*` y el
                             `required` NO se gatean con $strict: van siempre. --}}
                        <label for="what_happened" class="form-label fw-semibold">¿Qué ocurrió? <span class="text-danger">*</span></label>
                        <textarea name="what_happened" id="what_happened" class="form-control @error('what_happened') is-invalid @enderror" rows="3" required>{{ $v('what_happened') }}</textarea>
                        @error('what_happened')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="what_caused" class="form-label fw-semibold">¿Qué causó el incidente? @if($strict)<span class="text-danger">*</span>@endif</label>
                        <textarea name="what_caused" id="what_caused" class="form-control @error('what_caused') is-invalid @enderror" rows="3" {{ $req }}>{{ $v('what_caused') }}</textarea>
                        @error('what_caused')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- (2026-07-20) MECANISMO DE LA LESIÓN: cómo la persona entró en contacto con el
                         daño. Distinto de "qué pasó" y de "qué lo causó". Se guarda DENTRO del JSON
                         root_cause_analysis[mechanism] a propósito: NO es columna nueva en
                         injury_reports, así que el sello SHA de lo ya firmado no cambia. --}}
                    <div class="col-12">
                        <label for="rca_mechanism" class="form-label fw-semibold">Mecanismo de la lesión</label>
                        <div class="form-text mb-1">Cómo la persona entró en contacto con el daño (p. ej.: el objeto cayó desde 2 m e impactó el hombro derecho). Es el eslabón entre qué pasó y qué lo causó.</div>
                        <textarea name="root_cause_analysis[mechanism]" id="rca_mechanism" class="form-control" rows="2" maxlength="2000">{{ old('root_cause_analysis.mechanism', $rRca['mechanism'] ?? '') }}</textarea>
                    </div>

                    {{-- (2026-07-09) Análisis de causa raíz ESTRUCTURADO (NOM-019/030, OSHA RCA). --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold d-block mt-2">Análisis de causa raíz</label>
                        <div class="form-text mb-2">Distingue la causa inmediata, los factores contribuyentes y la causa raíz de fondo.</div>
                    </div>

                    {{-- (2026-07-13) COHERENCIA: categorías de causa raíz (checkboxes). Se guardan
                         dentro del mismo JSON root_cause_analysis[categories][]. Conviven con los
                         3 textareas narrativos de abajo. --}}
                    <div class="col-12">
                        <label class="form-label small fw-semibold d-block">Categoría de la causa</label>
                        @php $oldRcaCats = (array) $rRcaCats; @endphp
                        @foreach(['Falla humana','Condición ambiental','Falla de equipo','Procedimiento','Organizacional'] as $rcaCat)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="root_cause_analysis[categories][]" value="{{ $rcaCat }}" id="rca_cat_{{ $loop->index }}" {{ in_array($rcaCat, $oldRcaCats) ? 'checked' : '' }}>
                                <label class="form-check-label" for="rca_cat_{{ $loop->index }}">{{ $rcaCat }}</label>
                            </div>
                        @endforeach
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="rca_immediate" class="form-label small fw-semibold">Causa inmediata</label>
                        <textarea name="root_cause_analysis[immediate]" id="rca_immediate" class="form-control" rows="2">{{ old('root_cause_analysis.immediate', $rRca['immediate'] ?? '') }}</textarea>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="rca_contributing" class="form-label small fw-semibold">Factores contribuyentes</label>
                        <textarea name="root_cause_analysis[contributing]" id="rca_contributing" class="form-control" rows="2">{{ old('root_cause_analysis.contributing', $rRca['contributing'] ?? '') }}</textarea>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="rca_root" class="form-label small fw-semibold">Causa raíz</label>
                        <textarea name="root_cause_analysis[root]" id="rca_root" class="form-control" rows="2">{{ old('root_cause_analysis.root', $rRca['root'] ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: EVALUACIÓN DEL RIESGO ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-primary text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-ico'])
                <span>Evaluación del Riesgo</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    {{-- (2026-07-13) Catálogo ÚNICO de eventos: sustituye al select de
                         category_name y al multiselect de standards[]. Al elegir el evento,
                         el controlador etiqueta su norma (snapshot badge/código) y adjunta
                         todas sus normas al pivote standardables. OPCIONAL (como lo era
                         category_name). --}}
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

                    {{-- (2026-07-13) COHERENCIA: se retiran los ejes legacy seriousness/frequency.
                         La evaluación se homologa a la matriz 5×5 (Probabilidad × Consecuencia);
                         el nivel de riesgo (risk_level) lo calcula el servidor y ambos ejes son
                         OBLIGATORIOS. La primera opción vacía + required bloquea el envío sin selección. --}}
                    <div class="col-12 col-md-6">
                        <label for="likelihood" class="form-label fw-semibold">Probabilidad (A–E) @if($strict)<span class="text-danger">*</span>@endif</label>
                        <select name="likelihood" id="likelihood" class="form-select @error('likelihood') is-invalid @enderror" {{ $req }}>
                            <option value="">—</option>
                            @php $rLikelihood = $v('likelihood'); @endphp
                            @foreach(['A'=>'A · Casi seguro','B'=>'B · Probable','C'=>'C · Moderado','D'=>'D · Improbable','E'=>'E · Raro'] as $k=>$lbl)
                                <option value="{{ $k }}" {{ $rLikelihood === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                        @error('likelihood')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="consequence" class="form-label fw-semibold">Consecuencia (1–5) @if($strict)<span class="text-danger">*</span>@endif</label>
                        <select name="consequence" id="consequence" class="form-select @error('consequence') is-invalid @enderror" {{ $req }}>
                            <option value="">—</option>
                            @php $rConsequence = (string) $v('consequence'); @endphp
                            @foreach(['1'=>'1 · Insignificante','2'=>'2 · Menor','3'=>'3 · Moderado','4'=>'4 · Mayor','5'=>'5 · Catastrófico'] as $k=>$lbl)
                                <option value="{{ $k }}" {{ $rConsequence === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                        @error('consequence')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- (2026-07-13) El multiselect de "Normas aplicables (standards[])" se
                         retiró: las normas ahora se adjuntan solas desde el evento elegido
                         (applyHazardEvent → standardables). --}}
                </div>
            </div>
        </div>

        {{-- (2026-07-13) COHERENCIA: se retiró el bloque viejo "Notificación a Protección Civil"
             (notified_to_worksafe/date_notified/notified_by/notified_comment). Las notificaciones
             formales a autoridades se capturan únicamente en el módulo 10 (authority_notifications,
             abajo). Las columnas legacy se conservan en la BD; sólo se dejan de capturar aquí. --}}

        {{-- ============ SECCIÓN: NOTIFICACIÓN A AUTORIDADES (MÓDULO 10) ============ --}}
        {{-- Repeater de avisos a autoridades (IMSS/STPS/Protección Civil/M. Público).
             Si el incidente es REGISTRABLE, el servidor exige al menos una fila con
             autoridad + folio; el aviso dinámico de abajo lo anticipa. Gated por columna. --}}
        @if(\Illuminate\Support\Facades\Schema::hasColumn('injury_reports', 'authority_notifications'))
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-secondary text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-ico'])
                <span>Notificación a Autoridades</span>
            </h3>
            <div class="card-body">
                <div id="recordable_notice" class="alert alert-warning small d-none d-flex align-items-start gap-2">
                    @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico mt-1'])
                    <span>Este incidente parece <strong>REGISTRABLE</strong> (OSHA / IMSS / STPS). Debes registrar al menos una notificación a la autoridad con <strong>autoridad</strong> y <strong>número de folio</strong>.</span>
                </div>
                <p class="cc-muted small mb-3">Avisos formales a autoridades laborales/civiles. Añade una fila por cada notificación realizada.</p>
                <div id="authority_container">
                    @php
                        // Edición: siembra los avisos guardados (JSON); old() gana tras error de validación.
                        $reportNotes = ($report && is_array($report->authority_notifications)) ? $report->authority_notifications : [];
                        $oldNotes = old('authority_notifications', $reportNotes);
                    @endphp
                    @foreach($oldNotes as $ni => $note)
                    <div class="authority-row border rounded p-3 mb-2">
                        <div class="row g-2">
                            <div class="col-12 col-md-3">
                                <label class="form-label small fw-semibold">Autoridad</label>
                                <select name="authority_notifications[{{ $ni }}][authority]" class="form-select">
                                    <option value="">—</option>
                                    @foreach(['IMSS','STPS','Protección Civil','Ministerio Público'] as $auth)
                                        <option value="{{ $auth }}" {{ ($note['authority'] ?? '') === $auth ? 'selected' : '' }}>{{ $auth }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label small fw-semibold">Fecha/hora</label>
                                <input type="datetime-local" name="authority_notifications[{{ $ni }}][notified_at]" class="form-control" value="{{ $note['notified_at'] ?? '' }}">
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label small fw-semibold">Notificó</label>
                                <input type="text" name="authority_notifications[{{ $ni }}][notified_by]" class="form-control" value="{{ $note['notified_by'] ?? '' }}" maxlength="255">
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label small fw-semibold">Folio</label>
                                <input type="text" name="authority_notifications[{{ $ni }}][folio_number]" class="form-control" value="{{ $note['folio_number'] ?? '' }}" maxlength="100">
                            </div>
                        </div>
                        <div class="text-end mt-2">
                            <button type="button" class="btn btn-sm btn-outline-danger js-remove-authority">✕ Quitar</button>
                        </div>
                    </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="add_authority_btn">＋ Agregar notificación</button>
            </div>
        </div>
        @endif

        {{-- ============ SECCIÓN: PREVENCIÓN Y COMENTARIOS ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-secondary text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico'])
                <span>Prevención y Comentarios Adicionales</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="preventions" class="form-label fw-semibold">Acciones de prevención @if($strict)<span class="text-danger">*</span>@endif</label>
                        <textarea name="preventions" id="preventions" class="form-control @error('preventions') is-invalid @enderror" rows="4" placeholder="Ejemplo: Se colocarán señalamientos visibles en la zona, el personal recibirá capacitación adicional sobre el equipo, etc." {{ $req }}>{{ $v('preventions') }}</textarea>
                        @error('preventions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Describe las acciones que se tomarán para evitar que este tipo de incidente vuelva a ocurrir.</div>
                    </div>
                    <div class="col-12">
                        <label for="further_comments" class="form-label fw-semibold">Comentarios adicionales</label>
                        <textarea name="further_comments" id="further_comments" class="form-control" rows="3" placeholder="Agrega cualquier observación relevante...">{{ $v('further_comments') }}</textarea>
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
                            <div class="mb-2">
                                <img src="{{ $report->main_image_path }}" alt="Evidencia principal actual" class="img-thumbnail" style="max-height: 140px;">
                                <div class="form-text">Imagen actual. Sube una nueva solo si deseas reemplazarla.</div>
                            </div>
                        @endif
                        <input type="file" name="main_image" id="main_image" class="form-control" accept="image/*,.heic,.heif" data-cc-photo>
                        <div class="form-text">Esta será la imagen principal del reporte.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Evidencias adicionales (si es necesario)</label>
                        @if($isEdit && $report && is_array($report->additional_images_paths) && count($report->additional_images_paths))
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                @foreach($report->additional_images_paths as $imgPath)
                                    <img src="{{ $imgPath }}" alt="Evidencia adicional" class="img-thumbnail" style="max-height: 90px;">
                                @endforeach
                            </div>
                            <div class="form-text mb-2">Las imágenes actuales se conservan; las nuevas que agregues se suman.</div>
                        @endif
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
        {{-- El autor y la fecha se registran en el servidor con el usuario autenticado.
             Sin atributo name → no se envían ni pueden falsearse desde el form. --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico'])
                <span>Firma</span>
            </h3>
            <div class="card-body">
                <div class="alert alert-secondary small mb-3 d-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico'])
                    @if($isEdit)
                        <span>La <strong>firma original</strong> (autor y fecha de captura) se conserva y <strong>no cambia</strong> al editar.
                        Así queda registro fiable de <strong>quién</strong> capturó por primera vez la información.</span>
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
                        <input type="text" class="form-control bg-light" value="{{ $isEdit && $report && $report->make_date ? \Illuminate\Support\Carbon::parse($report->make_date)->format('d/m/Y') : date('d/m/Y') }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        {{-- Acciones del formulario: apiladas a lo ancho en móvil, a la derecha en escritorio --}}
        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-5">
            <a href="{{ $isEdit && $report ? route('injury_reports.show', $report->id) : route('injury_reports.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary fw-bold">{{ $isEdit ? 'Actualizar Reporte' : 'Guardar Reporte' }}</button>
        </div>
    </form>
</div>


<script>
$(document).ready(function() {
    const $nameInput = $('#name_input');
    const $suggestions = $('#name_suggestions');
    let currentSearchTerm = '';

    // (2026-07-13) COHERENCIA: #position_input ahora es un <select> de catálogo.
    // El puesto real del crew (puestodepartamento) puede NO estar en el catálogo;
    // si no existe la opción, se agrega una temporal para no perder el dato en pantalla
    // (el servidor de todas formas usa el puestodepartamento real al elegir un crew).
    function setPositionValue(val) {
        var $sel = $('#position_input');
        if ($sel.length === 0) { return; }
        $sel.find('option.js-temp-position').remove();
        val = val || '';
        if (val === '') { $sel.val(''); return; }
        var exists = $sel.find('option').filter(function () { return this.value === val; }).length > 0;
        if (!exists) {
            $sel.append($('<option>', { value: val, text: val, 'class': 'js-temp-position' }));
        }
        $sel.val(val);
    }

    // Función para limpiar todos los campos
    function clearUserFields() {
        setPositionValue('');
        $('#dob_input').val('');
        $('#phone_input').val('');
        $('#user_id').val('');
    }

    // Función para cargar sugerencias
    function loadSuggestions(term) {
        if (term.length < 2) {
            $suggestions.addClass('d-none').empty();
            clearUserFields();
            return;
        }

        currentSearchTerm = term;

        $.get('/users/search', { term: term }, function(users) {
            console.log('Respuesta del servidor:', users);
            // Verificar si el término de búsqueda no ha cambiado durante la solicitud
            if (term !== currentSearchTerm) return;

            $suggestions.empty();

            if (users.length > 0) {
                users.forEach(user => {
                    $suggestions.append(`
                        <a href="#" class="list-group-item list-group-item-action suggestion-item"
                           data-id="${user.id}"
                           data-name="${user.label}"
                           data-position="${user.position || ''}"
                           data-dob="${user.dob || ''}"
                           data-phone="${user.phone || ''}">
                            ${user.label} ${user.position ? ' - ' + user.position : ''}
                        </a>
                    `);
                });
                $suggestions.removeClass('d-none');
            } else {
                $suggestions.append(`
                    <div class="list-group-item cc-muted">
                        No se encontraron usuarios
                    </div>
                `);
                $suggestions.removeClass('d-none');
                clearUserFields();
            }
        }).fail(function() {
            $suggestions.addClass('d-none');
            clearUserFields();
        });
    }

    // Evento de entrada en el campo nombre
    $nameInput.on('input', function() {
        const term = $(this).val();
        loadSuggestions(term);
    });

    // Selección de sugerencia
$(document).on('click', '.suggestion-item', function(e) {
    e.preventDefault();
    const user = {
        id: $(this).data('id'),
        name: $(this).data('name'),
        position: $(this).data('position'),
        dob: $(this).data('dob'),
        phone: $(this).data('phone')
    };

    $nameInput.val(user.name);
    setPositionValue(user.position || '');
    $('#dob_input').val(user.dob || '');
    $('#phone_input').val(user.phone || '');
    $('#user_id').val(user.id);

    $suggestions.addClass('d-none').empty();
});

    // Ocultar sugerencias al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#name_input, #name_suggestions').length) {
            $suggestions.addClass('d-none');
        }
    });

    // Manejar teclado (flechas arriba/abajo y enter)
    $nameInput.on('keydown', function(e) {
        if ($suggestions.hasClass('d-none')) return;

        const $items = $suggestions.find('.suggestion-item');
        let $selected = $suggestions.find('.active');

        switch(e.keyCode) {
            case 38: // Flecha arriba
                e.preventDefault();
                if ($selected.length) {
                    $selected.removeClass('active');
                    const $prev = $selected.prev('.suggestion-item');
                    if ($prev.length) $prev.addClass('active');
                } else {
                    $items.last().addClass('active');
                }
                break;

            case 40: // Flecha abajo
                e.preventDefault();
                if ($selected.length) {
                    $selected.removeClass('active');
                    const $next = $selected.next('.suggestion-item');
                    if ($next.length) $next.addClass('active');
                } else {
                    $items.first().addClass('active');
                }
                break;

            case 13: // Enter
                e.preventDefault();
                if ($selected.length) {
                    $selected.trigger('click');
                }
                break;

            case 27: // Escape
                $suggestions.addClass('d-none');
                break;
        }
    });
});
</script>
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
</script>

{{-- (2026-07-12) MÓDULOS 7 y 10: repeaters de Testigos y Notificaciones a autoridad
     (JS vanilla, sin dependencias) + aviso dinámico de registrabilidad. --}}
<script>
(function () {
    // ---- MÓDULO 7: Testigos ----
    var witnessContainer = document.getElementById('witnesses_container');
    var witnessAddBtn    = document.getElementById('add_witness_btn');
    if (witnessContainer && witnessAddBtn) {
        var witnessIndex = witnessContainer.querySelectorAll('.witness-row').length;

        var witnessRowHtml = function (i) {
            return '<div class="row g-2">'
                + '<div class="col-12 col-md-4"><label class="form-label small fw-semibold">Nombre</label>'
                + '<input type="text" name="witnesses[' + i + '][name]" class="form-control" maxlength="255"></div>'
                + '<div class="col-12 col-md-3"><label class="form-label small fw-semibold">Teléfono</label>'
                + '<input type="text" name="witnesses[' + i + '][phone]" class="form-control" maxlength="50"></div>'
                + '<div class="col-12 col-md-5"><label class="form-label small fw-semibold">Declaración</label>'
                + '<input type="text" name="witnesses[' + i + '][statement]" class="form-control" maxlength="2000"></div>'
                + '</div>'
                + '<div class="text-end mt-2"><button type="button" class="btn btn-sm btn-outline-danger js-remove-witness">✕ Quitar</button></div>';
        };

        witnessAddBtn.addEventListener('click', function () {
            var row = document.createElement('div');
            row.className = 'witness-row border rounded p-3 mb-2';
            row.innerHTML = witnessRowHtml(witnessIndex);
            witnessContainer.appendChild(row);
            witnessIndex++;
        });

        witnessContainer.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('js-remove-witness')) {
                var r = e.target.closest('.witness-row');
                if (r) { r.remove(); }
            }
        });
    }

    // ---- MÓDULO 10: Notificaciones a autoridad ----
    var authContainer = document.getElementById('authority_container');
    var authAddBtn    = document.getElementById('add_authority_btn');
    var authorities   = ['IMSS', 'STPS', 'Protección Civil', 'Ministerio Público'];

    if (authContainer && authAddBtn) {
        var authIndex = authContainer.querySelectorAll('.authority-row').length;

        var authRowHtml = function (i) {
            var opts = '<option value="">—</option>';
            for (var a = 0; a < authorities.length; a++) {
                opts += '<option value="' + authorities[a] + '">' + authorities[a] + '</option>';
            }
            return '<div class="row g-2">'
                + '<div class="col-12 col-md-3"><label class="form-label small fw-semibold">Autoridad</label>'
                + '<select name="authority_notifications[' + i + '][authority]" class="form-select">' + opts + '</select></div>'
                + '<div class="col-12 col-md-3"><label class="form-label small fw-semibold">Fecha/hora</label>'
                + '<input type="datetime-local" name="authority_notifications[' + i + '][notified_at]" class="form-control"></div>'
                + '<div class="col-12 col-md-3"><label class="form-label small fw-semibold">Notificó</label>'
                + '<input type="text" name="authority_notifications[' + i + '][notified_by]" class="form-control" maxlength="255"></div>'
                + '<div class="col-12 col-md-3"><label class="form-label small fw-semibold">Folio</label>'
                + '<input type="text" name="authority_notifications[' + i + '][folio_number]" class="form-control" maxlength="100"></div>'
                + '</div>'
                + '<div class="text-end mt-2"><button type="button" class="btn btn-sm btn-outline-danger js-remove-authority">✕ Quitar</button></div>';
        };

        authAddBtn.addEventListener('click', function () {
            var row = document.createElement('div');
            row.className = 'authority-row border rounded p-3 mb-2';
            row.innerHTML = authRowHtml(authIndex);
            authContainer.appendChild(row);
            authIndex++;
        });

        authContainer.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('js-remove-authority')) {
                var r = e.target.closest('.authority-row');
                if (r) { r.remove(); }
            }
        });
    }

    // ---- Aviso dinámico de registrabilidad (espeja el predicado del servidor) ----
    var notice = document.getElementById('recordable_notice');
    var tl = document.getElementById('treatment_level');
    var da = document.getElementById('days_away_from_work');
    var dr = document.getElementById('days_restricted_work');

    var evalRecordable = function () {
        if (!notice) { return; }
        var level = tl ? tl.value : '';
        var away  = da ? (parseInt(da.value, 10) || 0) : 0;
        var restr = dr ? (parseInt(dr.value, 10) || 0) : 0;
        var recordable = (level === 'medical_treatment' || level === 'hospitalization' || level === 'fatality')
            || away > 0 || restr > 0;
        if (recordable) { notice.classList.remove('d-none'); }
        else { notice.classList.add('d-none'); }
    };

    if (tl) { tl.addEventListener('change', evalRecordable); }
    if (da) { da.addEventListener('input', evalRecordable); }
    if (dr) { dr.addEventListener('input', evalRecordable); }
    evalRecordable();

    // (2026-07-15) Tras un error de validación: lleva al primer campo marcado y enfócalo.
    var firstErr = document.querySelector('.is-invalid') || document.querySelector('.alert-danger');
    if (firstErr) {
        firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (firstErr.classList.contains('is-invalid') && typeof firstErr.focus === 'function') {
            try { firstErr.focus({ preventScroll: true }); } catch (e) { firstErr.focus(); }
        }
    }
})();
</script>
@endsection

@push('scripts')
{{-- HEIC (iPhone): conversión a JPEG en el navegador antes de subir (el servidor no decodifica HEIC). --}}
<script src="/js/cc-photo.js"></script>
<script src="/js/cc-photo-auto.js"></script>
@endpush
