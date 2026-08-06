@extends('layouts.app')

@section('content')

{{-- (2026-07-07) Rediseño: formulario homologado al estilo moderno de la app
     (cards shadow-sm con header de color + emoji, row g-3, labels fw-semibold),
     mismo patrón que admin/scoutings/_form.blade.php. Los name="" de los inputs
     NO cambiaron: el controlador valida esos mismos nombres. --}}

{{-- (2026-07-14) Pilar 1 — captura en 2 fases. La MISMA vista sirve para CREAR (Fase 1) y
     EDITAR/completar compliance (Fase 2). $isEdit y $report los pasa el controlador; el
     helper $hz() prellena cada campo con old() y, en edición, con el valor del reporte. --}}
@php
    $isEdit = $isEdit ?? false;
    $report = $report ?? null;
    // Prefill: old() gana (tras un error de validación); si no, el valor del reporte (edición).
    $hz = function ($field, $default = null) use ($isEdit, $report) {
        return old($field, ($isEdit && $report) ? ($report->{$field} ?? $default) : $default);
    };
@endphp

<div class="container py-4">

    {{-- Encabezado de página (patrón del scouting create) --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-0 fw-bold">{{ $isEdit ? 'Editar Acción Insegura' : 'Reporte de Acción Insegura' }}</h2>
            <p class="cc-muted mb-0 small">{{ $isEdit ? 'Completa la carga de compliance (matriz de riesgo, normas y ubicación)' : 'Notificación de acto inseguro observado en producción' }}</p>
        </div>
        <a href="{{ route('hazard_notifications.index') }}" class="btn btn-outline-secondary">&larr; Volver</a>
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

    {{-- (2026-07-14) Ruta de update (Fase 2): el orquestador la registra como 'hazards.update';
         fallback defensivo a 'hazard_notifications.update' por si sigue la convención hermana. --}}
    @php
        $hzUpdateRoute = \Illuminate\Support\Facades\Route::has('hazards.update')
            ? 'hazards.update'
            : (\Illuminate\Support\Facades\Route::has('hazard_notifications.update') ? 'hazard_notifications.update' : 'hazards.update');
    @endphp
    <form action="{{ $isEdit ? route($hzUpdateRoute, $report->id) : route('hazard_notifications.store') }}" method="POST" enctype="multipart/form-data" data-cc-autosave="hazard-notification">
        @csrf
        @if($isEdit) @method('PUT') @endif

        {{-- ============ SECCIÓN: GENERAL Y LOCACIÓN ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico'])
                <span>General y Locación</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    {{-- (2026-07-24) Producción DERIVADA de Config→Marca (como el Scouting): no se
                         teclea. En edición se respeta el production_name ya guardado. --}}
                    <div class="col-12 col-md-6">
                        @php $prodName = old('production_name', ($isEdit && $report && $report->production_name) ? $report->production_name : \App\Support\Branding::get('brand_name', 'CrewCare')); @endphp
                        <label class="form-label fw-semibold">Nombre de la Producción</label>
                        <input type="text" class="form-control bg-light" value="{{ $prodName }}" readonly>
                        <input type="hidden" name="production_name" value="{{ $prodName }}">
                        <small class="cc-muted d-block">Definida en Configuración → Marca.</small>
                    </div>

                    <div class="col-12 col-md-6">
                        {{-- id estable "name_loc": es el suggestTarget del GPS silencioso de abajo. --}}
                        <label for="name_loc" class="form-label fw-semibold">Locación <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name_loc') is-invalid @enderror" id="name_loc" name="name_loc" value="{{ $hz('name_loc') }}" required>
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
                            {{-- En edición prellena los hidden con el GPS ya guardado (si lo hay). --}}
                            'latValue' => $hz('latitude'),
                            'lngValue' => $hz('longitude'),
                            'gpsAddressValue' => $hz('gps_address'),
                        ])
                    </div>

                    {{-- (2026-07-13) Ubicación GPS HONESTA (no-silenciosa): el navegador puede
                         FALLAR o DENEGAR el permiso. En éxito, el GPS silencioso llena lat/lng por
                         detrás; en fallo el JS del pie revela el aviso #gps_fail_alert y apunta al
                         campo de referencias exactas. GPS NO obligatorio duro: si faltan coordenadas,
                         `manual_location_justification` pasa a ser obligatoria (required_without). --}}

                    {{-- Aviso VISIBLE cuando la geolocalización falla / se deniega el permiso.
                         Oculto por defecto (d-none); lo revela el JS. Apunta al respaldo manual. --}}
                    <div class="col-12 d-none" id="gps_fail_alert">
                        <div class="alert alert-warning d-flex align-items-start gap-2 mb-0" role="alert">
                            @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico mt-1'])
                            <div>
                                <strong>No pudimos capturar tu ubicación por GPS.</strong>
                                El navegador no compartió la ubicación (permiso denegado o sin señal).
                                <a href="#manual_location_justification" id="gps_fail_link" class="alert-link">Describe la ubicación con referencias exactas</a>
                                para que el reporte quede trazable.
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <details class="small">
                            <summary style="cursor:pointer;" class="fw-semibold">@include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico']) Ubicación GPS (automática) — <span id="gps_state" class="cc-muted fw-normal">detectando…</span></summary>
                            <div class="form-text mt-1">Intentamos capturar tu GPS automáticamente. <strong>Si el navegador no pudo capturar el GPS</strong>, ingresa las coordenadas a mano o describe la ubicación con referencias exactas abajo.</div>
                            <div class="row g-2 mt-1">
                                <div class="col-6 col-md-4">
                                    <input type="number" step="any" min="-90" max="90" class="form-control form-control-sm" id="gps_lat_manual" placeholder="Latitud" value="{{ $hz('latitude') }}">
                                </div>
                                <div class="col-6 col-md-4">
                                    <input type="number" step="any" min="-180" max="180" class="form-control form-control-sm" id="gps_lng_manual" placeholder="Longitud" value="{{ $hz('longitude') }}">
                                </div>
                            </div>
                            {{-- (2026-07-12) Módulo 11: referencias exactas cuando no hay GPS.
                                 El FormRequest la exige (required_without:latitude) si faltan coordenadas. --}}
                            <div class="mt-2">
                                <label for="manual_location_justification" class="form-label fw-semibold mb-1">Justificación de ubicación (referencias exactas)</label>
                                <textarea class="form-control form-control-sm" id="manual_location_justification" name="manual_location_justification" rows="2" maxlength="1000" placeholder="Ej.: Set 3, esquina noreste, junto a la bodega de utilería; acceso por la puerta B.">{{ $hz('manual_location_justification') }}</textarea>
                                <div class="form-text">Si el navegador no pudo capturar el GPS, describe la ubicación con referencias exactas (obligatoria cuando no hay coordenadas).</div>
                            </div>
                        </details>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="date_observed" class="form-label fw-semibold">Fecha Observada <span class="text-danger">*</span></label>
                        {{-- date_observed va casteado a Carbon en el modelo: se formatea a Y-m-d
                             para el <input type="date"> (echar el Carbon crudo rompe el value). --}}
                        @php
                            $dateObservedVal = old('date_observed', ($isEdit && $report && $report->date_observed)
                                ? (is_object($report->date_observed) ? $report->date_observed->format('Y-m-d') : $report->date_observed)
                                : '');
                        @endphp
                        <input type="date" class="form-control @error('date_observed') is-invalid @enderror" id="date_observed" name="date_observed" value="{{ $dateObservedVal }}" required>
                        @error('date_observed')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="time_observed" class="form-label fw-semibold">Hora Observada <span class="text-danger">*</span></label>
                        <input type="time" class="form-control @error('time_observed') is-invalid @enderror" id="time_observed" name="time_observed" value="{{ $hz('time_observed') }}" required>
                        @error('time_observed')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                </div>
            </div>
        </div>

        {{-- ============ SECCIÓN: DETALLES DE LO OBSERVADO ============ --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-warning text-dark fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico'])
                <span>Detalles de lo Observado</span>
            </h3>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="location_hazard_unsafe_act" class="form-label fw-semibold">¿Dónde se observó la acción insegura? <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('location_hazard_unsafe_act') is-invalid @enderror" id="location_hazard_unsafe_act" name="location_hazard_unsafe_act" rows="3" placeholder="Sea específico: área, set, foro, pasillo…" required>{{ $hz('location_hazard_unsafe_act') }}</textarea>
                        @error('location_hazard_unsafe_act')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="description_hazard_unsafe_act" class="form-label fw-semibold">Descripción del acto inseguro <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('description_hazard_unsafe_act') is-invalid @enderror" id="description_hazard_unsafe_act" name="description_hazard_unsafe_act" rows="4" placeholder="Incluya riesgos y/o peligros asociados" required>{{ $hz('description_hazard_unsafe_act') }}</textarea>
                        @error('description_hazard_unsafe_act')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- (2026-07-24) PASO 2/2 — PERSONA INVOLUCRADA: se elige del crew, NO se imprime
                         en el documento (evita cultura punitiva); al guardar se avisa por correo a su
                         jefe directo (lead del depto). El comparador castea ambos lados a string
                         (misma disciplina que el fix de la Consecuencia). --}}
                    {{-- (2026-07-24) Persona involucrada como CAMPO DE TEXTO con autocompletar (datalist
                         del crew). Siempre resuelve a un miembro REAL: el JS del pie escribe el hidden
                         involved_user_id solo si el texto coincide con una opción → así se puede avisar
                         a su jefe. El departamento se DERIVA de la persona (fuente única), no se teclea. --}}
                    <div class="col-12 col-md-6">
                        @php
                            $selInv = $hz('involved_user_id');
                            $selInvName = '';
                            if ($selInv) {
                                $u0 = ($crew ?? collect())->firstWhere('id', (int) $selInv);
                                if (!$u0) { $u0 = \App\Models\User::find($selInv); }
                                if ($u0) { $selInvName = trim($u0->name . ' ' . ($u0->lname ?? '')); }
                            }
                        @endphp
                        <label for="involved_user_name" class="form-label fw-semibold">Persona involucrada <span class="cc-muted fw-normal">(no se imprime)</span></label>
                        <input type="text" class="form-control" id="involved_user_name" list="crew_datalist" value="{{ $selInvName }}" placeholder="Escribe para buscar en el crew…" autocomplete="off">
                        <datalist id="crew_datalist">
                            @foreach(($crew ?? collect()) as $u)
                                <option data-id="{{ $u->id }}" value="{{ trim($u->name . ' ' . ($u->lname ?? '')) }}"></option>
                            @endforeach
                        </datalist>
                        {{-- Solo viaja si el texto resuelve a un miembro real del crew (lo pone el JS). --}}
                        <input type="hidden" name="involved_user_id" id="involved_user_id" value="{{ $selInv }}">
                        {{-- (2026-07-24) Indicador de match: dice al Safety, EN EL MOMENTO, si el aviso
                             al jefe saldrá (verde) o no (ámbar: nombre no coincide con el crew). El JS lo
                             actualiza; nunca bloquea el guardado. --}}
                        <div id="involved_user_feedback" class="small mt-1" style="display:none" aria-live="polite"></div>
                        <div class="form-text">Autocompletar del crew. Se avisa a su jefe directo por correo. <strong>El documento solo muestra el área</strong>, nunca el nombre.</div>
                    </div>

                    {{-- (2026-07-24) ACTO INSEGURO RELACIONADO ← Condición hermana (enlace real). --}}
                    <div class="col-12 col-md-6">
                        <label for="related_unsafecond_id" class="form-label fw-semibold">Condición insegura relacionada <span class="cc-muted fw-normal">(opcional)</span></label>
                        @php $selRel = $hz('related_unsafecond_id'); @endphp
                        <select name="related_unsafecond_id" id="related_unsafecond_id" class="form-select">
                            <option value="">— Ninguna —</option>
                            @foreach(($siblings ?? collect()) as $s)
                                <option value="{{ $s->id }}" {{ (string) $s->id === (string) $selRel ? 'selected' : '' }}>UNS-{{ str_pad($s->id, 4, '0', STR_PAD_LEFT) }} · {{ \Illuminate\Support\Str::limit($s->name_loc ?: $s->description_unsafe_cond, 40) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Enlace real al reporte hermano; su fecha se deriva de él.</div>
                    </div>

                    {{-- (2026-07-24) FACTOR HUMANO (solo Acto): el "por qué" de la conducta. Múltiple.
                         SÍ se imprime (análisis, no señalamiento). Claves desde el modelo (fuente única). --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Factor humano <span class="cc-muted fw-normal">(¿por qué ocurrió la conducta?)</span></label>
                        @php $selHF = (array) old('human_factor', ($isEdit && $report && is_array($report->human_factor)) ? $report->human_factor : []); @endphp
                        <div class="row g-2">
                            @foreach(\App\Models\hazardnotification::HUMAN_FACTORS as $hfKey => $hfLabel)
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="human_factor[]" id="hf_{{ $hfKey }}" value="{{ $hfKey }}" {{ in_array($hfKey, $selHF, true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="hf_{{ $hfKey }}">{{ $hfLabel }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="form-text">Sin el factor humano la acción correctiva es genérica. Puedes marcar varios.</div>
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
                    {{-- (2026-07-13) Catálogo ÚNICO de eventos — OPCIONAL. Reemplaza el select de
                         category_name y el multiselect de standards[]: al elegir el evento se
                         etiqueta automáticamente su norma (snapshot badge/código) y se adjuntan
                         TODAS sus normas al pivote standardables. --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Evento / peligro (catálogo)</label>
                        @include('componentes._event-picker', [
                            'hazardEvents' => $hazardEvents ?? collect(),
                            'name'         => 'hazard_event_id',
                            'selected'     => $hz('hazard_event_id'),
                            'required'     => false,
                        ])
                        <div class="form-text">Al elegir el evento se etiqueta automáticamente su norma (CSATF/STPS/OSHA).</div>
                    </div>

                    {{-- (2026-07-09) Matriz de riesgo 5×5: el NIVEL lo calcula el servidor a partir
                         de Probabilidad (A–E) × Consecuencia (1–5). El "Nivel de riesgo" de abajo es
                         un respaldo manual (se usa sólo si no eliges los dos ejes). --}}
                    <div class="col-12 col-md-4">
                        <label for="likelihood" class="form-label fw-semibold">Probabilidad</label>
                        @php $likelihoodVal = $hz('likelihood'); @endphp
                        <select name="likelihood" id="likelihood" class="form-select">
                            <option value="">—</option>
                            @foreach(['A'=>'A · Casi seguro','B'=>'B · Probable','C'=>'C · Moderado','D'=>'D · Improbable','E'=>'E · Raro'] as $k=>$lbl)
                                <option value="{{ $k }}" {{ (string) $likelihoodVal === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="consequence" class="form-label fw-semibold">Consecuencia</label>
                        @php $consequenceVal = $hz('consequence'); @endphp
                        <select name="consequence" id="consequence" class="form-select">
                            <option value="">—</option>
                            {{-- (2026-07-23) BOMBA: PHP castea las claves '1'..'5' a ENTERO, así que
                                 comparar `(string)$val === $k` era string-vs-int → NUNCA preseleccionaba
                                 y al re-guardar se perdía la consecuencia. Se castean AMBOS lados. --}}
                            @foreach(['1'=>'1 · Insignificante','2'=>'2 · Menor','3'=>'3 · Moderado','4'=>'4 · Mayor','5'=>'5 · Catastrófico'] as $k=>$lbl)
                                <option value="{{ $k }}" {{ (string) $k === (string) $consequenceVal ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- (2026-07-23) Homologado con la Condición Insegura: el nivel se AUTOCALCULA
                         con la matriz 5×5 (Probabilidad × Consecuencia) y el select queda de sólo
                         lectura (hidden espejo envía el valor); sin ambos ejes sigue editable como
                         respaldo manual. El resultado server-side ya era idéntico (trait
                         CalculatesRiskMatrix); antes divergía sólo la UX (Acto sin este JS). --}}
                    <div class="col-12 col-md-4">
                        <label for="risk_level" class="form-label fw-semibold">Nivel de riesgo <span class="cc-muted fw-normal">(auto)</span></label>
                        @php $riskLevelVal = $hz('risk_level'); @endphp
                        <select name="risk_level" id="risk_level" class="form-select">
                            <option value="" {{ $riskLevelVal === null || $riskLevelVal === '' ? 'selected' : '' }}>—</option>
                            @foreach(['Bajo','Medio','Alto','Extremo'] as $rlOpt)
                                <option value="{{ $rlOpt }}" {{ (string) $riskLevelVal === $rlOpt ? 'selected' : '' }}>{{ $rlOpt }}</option>
                            @endforeach
                        </select>
                        {{-- Espejo: cuando el select queda bloqueado (auto) no se envía; este hidden
                             lleva el valor calculado. El JS le pone/quita name="risk_level" según el modo. --}}
                        <input type="hidden" id="risk_level_mirror" value="{{ $riskLevelVal }}">
                        <div class="form-text" id="risk_level_help">Se calcula solo si eliges Probabilidad y Consecuencia; si no, captúralo a mano.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="action_status" class="form-label fw-semibold">Estado inicial de la acción correctiva</label>
                        @php $actionStatusVal = old('action_status', ($isEdit && $report && $report->action_status) ? $report->action_status : 'Abierto'); @endphp
                        <select name="action_status" id="action_status" class="form-select">
                            @foreach(['Abierto','En proceso','Cerrado'] as $stOpt)
                                <option value="{{ $stOpt }}" {{ $actionStatusVal === $stOpt ? 'selected' : '' }}>{{ $stOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- (2026-07-13) El multiselect de "Normas aplicables (standards[])" se ELIMINÓ:
                         las normas ahora se adjuntan solas desde el evento elegido arriba. --}}
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
                        <textarea class="form-control" id="action_taken" name="action_taken" rows="3" placeholder="Nota cualquier acción inmediata tomada para minimizar los riesgos">{{ $hz('action_taken') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label for="suggestions_corrective_action" class="form-label fw-semibold">Sugerencias para la acción correctiva</label>
                        <textarea class="form-control" id="suggestions_corrective_action" name="suggestions_corrective_action" rows="3" placeholder="Acciones correctivas a largo plazo (Formulario II: Notificación de Condición Insegura y Plan de Acción)">{{ $hz('suggestions_corrective_action') }}</textarea>
                    </div>

                    {{-- (2026-07-24) RESPONSABLE + FECHA COMPROMISO de la acción correctiva. Cuelgan
                         del ActionItem auto-generado (source_field = suggestions_corrective_action) y
                         se IMPRIMEN. En edición se prellenan del item existente. --}}
                    @php
                        $ccItem  = ($isEdit && $report && $report->relationLoaded('actionItems')) ? $report->actionItems->firstWhere('source_field', 'suggestions_corrective_action') : null;
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
                        {{-- (2026-07-14) En edición: muestra la imagen ya guardada. Subir una nueva la
                             REEMPLAZA; dejar el campo vacío conserva la actual. Las rutas ya son
                             root-relativas (Storage::url), así que sirven como src directo. --}}
                        @if($isEdit && $report && $report->main_image_path)
                            <div class="mb-2">
                                <img src="{{ $report->main_image_path }}" alt="Evidencia principal actual" class="img-thumbnail" style="max-height:160px;">
                                <div class="form-text">Imagen actual. Sube otra sólo si deseas reemplazarla.</div>
                            </div>
                        @endif
                        <input type="file" class="form-control" id="main_image" name="main_image" accept="image/*,.heic,.heif" data-cc-photo>
                        <div class="form-text">Esta será la imagen principal del reporte.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Evidencias adicionales (si es necesario)</label>
                        {{-- (2026-07-14) En edición: las adicionales ya guardadas se muestran; las
                             nuevas se AGREGAN a estas (no las reemplazan). --}}
                        @if($isEdit && $report && is_array($report->additional_images_paths) && count($report->additional_images_paths))
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                @foreach($report->additional_images_paths as $imgPath)
                                    <img src="{{ $imgPath }}" alt="Evidencia adicional actual" class="img-thumbnail" style="max-height:110px;">
                                @endforeach
                            </div>
                            <div class="form-text mb-2">Imágenes actuales. Las que agregues abajo se suman a estas.</div>
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
        {{-- Autor + fecha se fijan en el servidor con el usuario autenticado. Estos campos
             son SÓLO de lectura y NO tienen atributo name, por lo que no se envían en el
             POST → no son falseables. --}}
        <div class="card shadow-sm mb-4">
            <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico'])
                <span>Firma</span>
            </h3>
            <div class="card-body">
                <div class="alert alert-secondary small mb-3 d-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico'])
                    <span>Este reporte se firma <strong>automáticamente</strong> con tu usuario y la fecha de hoy.
                    No es editable: así queda registro fiable de <strong>quién</strong> y <strong>cuándo</strong> capturó la información.
                    @if($isEdit)<br>La firma original se conserva al completar la carga de compliance.@endif</span>
                </div>
                @php
                    // En edición se muestra la AUTOFIRMA original (no se re-firma en Fase 2).
                    $firmaBy   = ($isEdit && $report && $report->make_by) ? $report->make_by : auth()->user()->name;
                    $firmaDate = ($isEdit && $report && $report->make_date)
                        ? (is_object($report->make_date) ? $report->make_date->format('d/m/Y') : \Carbon\Carbon::parse($report->make_date)->format('d/m/Y'))
                        : date('d/m/Y');
                @endphp
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label fw-semibold">Completado por</label>
                        <input type="text" class="form-control bg-light" value="{{ $firmaBy }}" readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">Fecha realizado</label>
                        <input type="text" class="form-control bg-light" value="{{ $firmaDate }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        {{-- Acciones del formulario: apiladas a lo ancho en móvil, a la derecha en escritorio --}}
        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-5">
            <a href="{{ $isEdit ? route('hazard_notifications.show', $report->id) : route('hazard_notifications.index') }}" class="btn btn-outline-secondary">Cancelar</a>
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

    // (2026-07-13) GPS HONESTO (no-silencioso): sincroniza el respaldo manual con los hidden
    // latitude/longitude del GPS silencioso Y muestra un aviso VISIBLE (#gps_fail_alert) cuando
    // la geolocalización falla o se deniega el permiso, apuntando al campo de referencias
    // exactas (manual_location_justification). NO dispara un segundo prompt de geolocalización:
    // usa la Permissions API (sin prompt) + un timeout de respaldo.
    (function () {
        var failAlert = document.getElementById('gps_fail_alert');
        var failLink  = document.getElementById('gps_fail_link');
        var justify   = document.getElementById('manual_location_justification');
        var mLat      = document.getElementById('gps_lat_manual');
        var mLng      = document.getElementById('gps_lng_manual');
        var state     = document.getElementById('gps_state');
        var shown     = false;

        // El enlace del aviso abre el <details> y enfoca el campo de referencias exactas,
        // aunque el GPS silencioso no esté disponible.
        if (failLink && justify) {
            failLink.addEventListener('click', function (e) {
                e.preventDefault();
                var det = justify.closest('details');
                if (det) det.open = true;
                justify.scrollIntoView({ behavior: 'smooth', block: 'center' });
                justify.focus();
            });
        }

        function showFail() {
            if (failAlert) failAlert.classList.remove('d-none');
            shown = true;
            if (state) state.textContent = 'no detectada — ingresa coordenadas o describe la ubicación abajo';
        }
        function hideFail() {
            if (failAlert) failAlert.classList.add('d-none');
        }
        // El usuario "resolvió" la ubicación: hay coordenadas manuales o una justificación escrita.
        function resolved() {
            var hasManual  = (mLat && mLat.value !== '') || (mLng && mLng.value !== '');
            var hasJustify = justify && justify.value.trim() !== '';
            return hasManual || hasJustify;
        }

        var box = document.querySelector('[data-geo-silent]');
        if (box) {
            var hLat = box.querySelector('input[name="latitude"]');
            var hLng = box.querySelector('input[name="longitude"]');
            if (hLat && hLng && mLat && mLng) {
                mLat.addEventListener('input', function () { if (mLat.value !== '') { hLat.value = mLat.value; hideFail(); } });
                mLng.addEventListener('input', function () { if (mLng.value !== '') { hLng.value = mLng.value; hideFail(); } });
                setInterval(function () {
                    if (hLat.value && mLat.value === '') mLat.value = hLat.value;
                    if (hLng.value && mLng.value === '') mLng.value = hLng.value;
                    if (hLat.value && hLng.value) {
                        hideFail();
                        if (state) state.textContent = 'detectada (' + (+hLat.value).toFixed(4) + ', ' + (+hLng.value).toFixed(4) + ')';
                    } else if (state && !shown) {
                        state.textContent = 'detectando…';
                    }
                }, 1200);
            }
        }

        // Al escribir la justificación, el usuario ya resolvió la ubicación: se oculta el aviso.
        if (justify) {
            justify.addEventListener('input', function () { if (justify.value.trim() !== '') hideFail(); });
        }

        // Detección de permiso DENEGADO sin re-disparar el prompt (Permissions API).
        if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({ name: 'geolocation' }).then(function (status) {
                if (status.state === 'denied' && !resolved()) showFail();
                status.onchange = function () { if (status.state === 'denied' && !resolved()) showFail(); };
            }).catch(function () { /* Permissions API no soportada: cae al timeout de abajo. */ });
        }

        // Respaldo por timeout: si tras 9 s no hay coordenadas ni justificación, el GPS no llegó
        // (permiso en 'prompt' ignorado, timeout, o navegador sin geolocalización).
        setTimeout(function () {
            var h = box ? box.querySelector('input[name="latitude"]') : null;
            var hasCoords = h && h.value !== '';
            if (!hasCoords && !resolved()) showFail();
        }, 9000);
    })();

    // (2026-07-23) Homologado con la Condición Insegura — MATRIZ canónica 5×5 (preview de
    // cliente; el servidor recalcula vía el trait CalculatesRiskMatrix). Al elegir Probabilidad
    // (A–E) y Consecuencia (1–5), el Nivel de riesgo se AUTOCALCULA y queda de SÓLO LECTURA
    // (bloqueado); un hidden espejo envía el valor. Sin ambos ejes, el select sigue editable
    // como respaldo manual.
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

    // (2026-07-24) Persona involucrada: el campo de texto (datalist) SIEMPRE resuelve a un miembro
    // real del crew → escribe involved_user_id (hidden). Y MUESTRA su estado para que el Safety sepa,
    // al capturar, si el aviso al jefe saldrá:
    //   · texto que ata a una persona real → verde ("se avisará a su jefe").
    //   · texto que NO coincide → ámbar ("no coincide con el crew — no se enviará aviso").
    //   · vacío → sin indicador (es válido no declarar involucrado).
    // Nunca bloquea el guardado; lo que evita es que el usuario CREA que avisó sin haber avisado.
    (function () {
        var input  = document.getElementById('involved_user_name');
        var hidden = document.getElementById('involved_user_id');
        var dl     = document.getElementById('crew_datalist');
        var fb     = document.getElementById('involved_user_feedback');
        if (!input || !hidden || !dl) return;
        var map = {};
        Array.prototype.forEach.call(dl.options, function (o) {
            map[(o.value || '').trim().toLowerCase()] = o.getAttribute('data-id') || '';
        });
        function setState(state, name) {
            if (fb) {
                if (state === 'none') {
                    fb.style.display = 'none';
                    fb.textContent = '';
                } else if (state === 'ok') {
                    fb.style.display = '';
                    fb.style.color = '#15803d';
                    fb.textContent = '✓ Se avisará a su jefe directo' + (name ? ' — ' + name : '') + '.';
                } else { // warn
                    fb.style.display = '';
                    fb.style.color = '#b45309';
                    fb.textContent = '⚠ No coincide con ningún miembro del crew — no se enviará aviso al jefe.';
                }
            }
            // Borde del input como refuerzo (verde / ámbar / neutro). No usa is-invalid (rojo=error):
            // un texto sin match no es un error que bloquee, es un aviso.
            input.style.borderColor = state === 'ok' ? '#15803d' : (state === 'warn' ? '#b45309' : '');
        }
        function resolve(isInit) {
            var key = (input.value || '').trim().toLowerCase();
            if (!key) { hidden.value = ''; setState('none'); return; }
            if (map[key]) { hidden.value = map[key]; setState('ok', input.value.trim()); return; }
            // Sin coincidencia exacta en el datalist:
            if (isInit && hidden.value) {
                // Edición: el involucrado guardado es válido aunque ya no esté en el crew actual.
                setState('ok', input.value.trim());
                return;
            }
            hidden.value = '';
            setState('warn');
        }
        input.addEventListener('input', function () { resolve(false); });
        input.addEventListener('change', function () { resolve(false); });
        resolve(true); // refleja el estado precargado sin borrar un id válido en edición
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
