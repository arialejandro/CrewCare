@extends('layouts.app')
@section('content')
@php
    $triggerLabels = [
        'identidad' => __('Identidad (¿misma unidad y tripulación?)'),
        'persona'   => __('Persona / tripulación'),
        'unidad'    => __('Unidad'),
        'consumo'   => __('Consumo / botiquín'),
        'riesgo'    => __('Riesgo del día'),
    ];
    $ramaLabels = [
        'todas'     => __('Comunes a toda ambulancia'),
        'terrestre' => __('Terrestre'),
        'aerea'     => __('Aérea'),
        'maritima'  => __('Marítima'),
    ];
    $riskLabels = [1 => __('Muy bajo'), 2 => __('Bajo'), 3 => __('Medio'), 4 => __('Alto'), 5 => __('Muy alto')];
    $capLabels  = [1 => __('Apéndice A · traslado'), 2 => __('Apéndice B · básica'), 3 => __('Apéndice C · avanzada'), 4 => __('Apéndice D · UCI')];

    // Selección completa = hay tipo y disparador válido; si no, se muestra el selector.
    $validTriggers = ['identidad', 'persona', 'unidad', 'consumo', 'riesgo'];
    $hasSelection  = $type && in_array($trigger, $validTriggers, true);
    $isRiesgo      = ($trigger === 'riesgo');
    $oldCrew       = old('crew', []);
    $gateCount = 0;
    foreach ($points as $gp) { if ($gp->is_gate) $gateCount++; }
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 820px;">

        <a href="{{ route('ambulance.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-3" style="text-decoration:none;">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null]) {{ __('Volver a recursos de emergencia') }}
        </a>

        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @if (! $hasSelection)
            {{-- ── SELECTOR: elegir tipo + disparador antes de verificar ────────── --}}
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Verificar ambulancia') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Elige el tipo y qué se verifica hoy.') }}</p>
                </div>
            </div>

            <form method="get" action="{{ route('ambulance.inspect.form') }}">
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Tipo de ambulancia') }} *</label>
                            <select name="type_id" id="pickType" class="form-select" required>
                                <option value="" data-rama="">{{ __('— Tipo —') }}</option>
                                @foreach ($types as $t)
                                    <option value="{{ $t->id }}" data-rama="{{ $t->rama }}"
                                        @selected((int) old('type_id', optional($type)->id) === (int) $t->id)>
                                        {{ $t->code }} · {{ $t->name_es }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Qué se verifica') }} *</label>
                            <select name="trigger" class="form-select" required>
                                <option value="">{{ __('— Disparador —') }}</option>
                                @foreach ($triggerLabels as $k => $lbl)
                                    <option value="{{ $k }}" @selected(old('trigger', $trigger) === $k)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('Riesgo e Identidad no llevan checklist.') }}</div>
                        </div>
                        <div class="col-md-6" id="capWrap" style="display:none;">
                            <label class="form-label small fw-semibold">{{ __('Capacidad resolutiva') }}</label>
                            <select name="capacity_level" class="form-select">
                                <option value="">{{ __('— Capacidad —') }}</option>
                                @foreach ($capLabels as $lvl => $lbl)
                                    <option value="{{ $lvl }}" @selected((int) old('capacity_level', $capacityLevel) === $lvl)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('Solo aérea/marítima: qué apéndice cumple por su capacidad.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-crew-accent d-inline-flex align-items-center gap-2">
                        @include('componentes._icon', ['name' => 'chevron-right', 'label' => null])
                        {{ __('Continuar') }}
                    </button>
                </div>
            </form>

            <script>
                (function () {
                    var sel = document.getElementById('pickType');
                    var cap = document.getElementById('capWrap');
                    if (!sel || !cap) return;
                    function sync() {
                        var opt = sel.options[sel.selectedIndex];
                        var rama = opt ? (opt.getAttribute('data-rama') || '') : '';
                        cap.style.display = (rama && rama !== 'terrestre') ? '' : 'none';
                    }
                    sel.addEventListener('change', sync); sync();
                })();
            </script>

        @else
            {{-- ── FORMULARIO EN SITIO ──────────────────────────────────────── --}}
            <div class="d-flex align-items-start gap-3 mb-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ $type->name_es }}</h1>
                    <p class="text-muted mb-0 small">
                        {{ $type->code }}
                        @if ($type->apendice) · {{ __('Apéndice') }} {{ $type->apendice }}@endif
                        @if ($type->name_en) · {{ $type->name_en }}@endif
                    </p>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="insp-tag">{{ __('Verificación') }}: {{ $triggerLabels[$trigger] ?? $trigger }}</span>
                @if (! empty($capacityLevel))<span class="insp-tag">{{ $capLabels[(int) $capacityLevel] ?? $capacityLevel }}</span>@endif
                @if (! $isRiesgo)
                    <span class="insp-tag">{{ $points->count() }} {{ __('puntos') }}</span>
                    @if ($gateCount > 0)<span class="insp-tag insp-tag--gate">{{ $gateCount }} {{ __('compuerta(s)') }}</span>@endif
                @endif
                <a href="{{ route('ambulance.inspect.form') }}" class="insp-tag" style="text-decoration:none;">{{ __('cambiar tipo') }}</a>
            </div>

            <form method="post" action="{{ route('ambulance.inspect.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type_id" value="{{ $type->id }}">
                <input type="hidden" name="trigger_scope" value="{{ $trigger }}">
                @if (! empty($capacityLevel))<input type="hidden" name="capacity_level" value="{{ $capacityLevel }}">@endif

                {{-- (a) DATOS DE LA UNIDAD ─────────────────────────────────────── --}}
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                    <h5 class="mb-3">{{ __('Datos de la unidad') }}</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Proveedor') }}</label>
                            <select name="provider_id" id="providerSel" class="form-select js-typeahead">
                                <option value="">{{ __('— Proveedor —') }}</option>
                                @foreach ($providers as $prov)
                                    <option value="{{ $prov->id }}" @selected((int) old('provider_id') === (int) $prov->id)>{{ $prov->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="provider_name" class="form-control mt-2" maxlength="255"
                                   value="{{ old('provider_name') }}" placeholder="{{ __('o escribe el nombre del proveedor') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">{{ __('Placas') }}</label>
                            <input type="text" name="plates" class="form-control" maxlength="40" value="{{ old('plates') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">{{ __('N.º económico') }}</label>
                            <input type="text" name="economic_number" class="form-control" maxlength="60" value="{{ old('economic_number') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">{{ __('Foto de la unidad (opcional)') }}</label>
                            <input type="file" name="unit_photo" class="form-control" accept="image/*,.heic,.heif" capture="environment" data-cc-photo>
                            <div class="form-text">{{ __('La unidad real; queda sellada en el acta.') }}</div>
                        </div>
                    </div>
                </div>

                {{-- (b) TRIPULACIÓN DE HOY ──────────────────────────────────────── --}}
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                    <h5 class="mb-1">{{ __('Tripulación de hoy') }}</h5>
                    <p class="text-muted small">{{ __('Marca del padrón quién se presentó. Si alguien no está, agrégalo abajo.') }}</p>

                    {{-- Padrón del proveedor elegido (se muestra el del proveedor seleccionado). --}}
                    @foreach ($providers as $prov)
                        <div class="crew-roster" data-provider="{{ $prov->id }}" style="display:none;">
                            @forelse ($prov->crew as $m)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="crew_ids[]" value="{{ $m->id }}"
                                           id="crew{{ $m->id }}" @checked(in_array($m->id, (array) old('crew_ids', [])))>
                                    <label class="form-check-label" for="crew{{ $m->id }}">
                                        {{ $m->full_name }}@if ($m->crew_role)<span class="text-muted small"> · {{ $m->crew_role }}</span>@endif
                                    </label>
                                </div>
                            @empty
                                <p class="text-muted small mb-0">{{ __('Este proveedor no tiene padrón; agrega tripulantes abajo.') }}</p>
                            @endforelse
                        </div>
                    @endforeach
                    <p id="crewHint" class="text-muted small">{{ __('Elige un proveedor para ver su padrón, o agrega tripulantes abajo.') }}</p>

                    {{-- Tripulantes de texto libre (quien no está en el padrón). --}}
                    <div id="crewRows">
                        @foreach ($oldCrew as $i => $oc)
                            <div class="row g-2 align-items-center mb-2 crew-row">
                                <div class="col">
                                    <input type="text" name="crew[{{ $i }}][name]" class="form-control form-control-sm"
                                           maxlength="255" placeholder="{{ __('Nombre') }}" value="{{ $oc['name'] ?? '' }}">
                                </div>
                                <div class="col">
                                    <input type="text" name="crew[{{ $i }}][role]" class="form-control form-control-sm"
                                           maxlength="120" placeholder="{{ __('Rol') }}" value="{{ $oc['role'] ?? '' }}">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" id="addCrew" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                        @include('componentes._icon', ['name' => 'plus', 'label' => null]) {{ __('Agregar tripulante') }}
                    </button>

                    <template id="crewTpl">
                        <div class="row g-2 align-items-center mb-2 crew-row">
                            <div class="col">
                                <input type="text" name="crew[__IDX__][name]" class="form-control form-control-sm" maxlength="255" placeholder="{{ __('Nombre') }}">
                            </div>
                            <div class="col">
                                <input type="text" name="crew[__IDX__][role]" class="form-control form-control-sm" maxlength="120" placeholder="{{ __('Rol') }}">
                            </div>
                        </div>
                    </template>
                </div>

                @if ($isRiesgo)
                    {{-- (d) RIESGO DEL DÍA: no hay checklist. Lo fija el criterio del safety;
                         el catálogo NO decide si el tipo alcanza para el riesgo de hoy. --}}
                    <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                        <h5 class="mb-1">{{ __('Correspondencia con el riesgo del día') }}</h5>
                        <p class="text-muted small">{{ __('Esto lo fija el criterio del safety, no el catálogo: ¿el tipo de ambulancia alcanza para el riesgo de la jornada?') }}</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">{{ __('Nivel de riesgo del día') }} *</label>
                                <select name="day_risk_level" class="form-select" required>
                                    <option value="">{{ __('— Nivel —') }}</option>
                                    @foreach ($riskLabels as $lvl => $lbl)
                                        <option value="{{ $lvl }}" @selected((int) old('day_risk_level') === $lvl)>{{ $lvl }} · {{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold d-block">{{ __('¿El tipo alcanza para ese riesgo?') }} *</label>
                                <div class="d-flex gap-3 mt-1">
                                    <label class="d-inline-flex align-items-center gap-1">
                                        <input type="radio" name="correspondence_ok" value="1" required @checked(old('correspondence_ok') === '1')>
                                        {{ __('Sí, corresponde') }}
                                    </label>
                                    <label class="d-inline-flex align-items-center gap-1">
                                        <input type="radio" name="correspondence_ok" value="0" @checked(old('correspondence_ok') === '0')>
                                        {{ __('No corresponde') }}
                                    </label>
                                </div>
                                <div class="form-text">{{ __('Si no corresponde, la actividad puede no ser ejecutable con este recurso.') }}</div>
                            </div>
                        </div>
                    </div>
                @elseif ($points->count() > 0)
                    {{-- (c) CHECKLIST BINARIO, agrupado por rama. --}}
                    <div class="alert alert-warning d-flex align-items-start gap-2 py-2">
                        @include('componentes._icon', ['name' => 'octagon-alert', 'label' => null])
                        <span>{{ __('Un fallo en una compuerta puede ser PARO: la unidad no se usa hasta corregir o reemplazar.') }}</span>
                    </div>

                    @foreach ($points->groupBy('rama') as $rama => $groupPoints)
                        <div class="insp-scope-head">{{ $ramaLabels[$rama] ?? $rama }}</div>
                        @foreach ($groupPoints as $p)
                            <div class="insp-point {{ $p->is_gate ? 'is-gate' : 'is-info' }}">
                                <p class="insp-point__text">{{ $p->text_es }}</p>
                                <div class="insp-point__meta">
                                    <span class="insp-tag">{{ $p->code }}</span>
                                    @if ($p->is_gate)
                                        <span class="insp-tag insp-tag--gate">{{ __('Compuerta') }}</span>
                                    @else
                                        <span class="insp-tag">{{ __('Informativo') }}</span>
                                    @endif
                                    @if ($p->norm_ref)<span class="insp-tag">{{ $p->norm_ref }}</span>@endif
                                    @if ($p->requires_document)<span class="insp-tag">{{ __('Se pide el documento') }}</span>@endif
                                </div>
                                @if ($p->requires_document)
                                    <div class="form-text">{{ __('No se abre el botiquín: se pide el papel (vigencias, calibres, caducidades).') }}</div>
                                @endif
                                <div class="insp-answer">
                                    <label>
                                        <input type="radio" name="answers[{{ $p->code }}]" value="ok" required @checked(old('answers.'.$p->code) === 'ok')>
                                        <span class="btn-ans">@include('componentes._icon', ['name' => 'circle-check', 'label' => null]) {{ __('Cumple') }}</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="answers[{{ $p->code }}]" value="fail" @checked(old('answers.'.$p->code) === 'fail')>
                                        <span class="btn-ans">@include('componentes._icon', ['name' => 'octagon-alert', 'label' => null]) {{ __('Falla') }}</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                @else
                    {{-- Este disparador no trae puntos de checklist para este tipo. --}}
                    <div class="alert alert-info d-flex align-items-start gap-2 py-2">
                        @include('componentes._icon', ['name' => 'info', 'label' => null])
                        <span>{{ __('Esta verificación no tiene puntos de checklist para este tipo. Registra los datos de la unidad y las observaciones.') }}</span>
                    </div>
                @endif

                {{-- (e) OBSERVACIONES ───────────────────────────────────────────── --}}
                <div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
                    <label class="form-label small fw-semibold">{{ __('Observaciones (opcional)') }}</label>
                    <textarea name="note" class="form-control" rows="2" maxlength="2000">{{ old('note') }}</textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="{{ route('ambulance.index') }}" class="btn btn-link text-muted">{{ __('Abandonar sin guardar') }}</a>
                    <button type="submit" class="btn btn-crew-accent d-inline-flex align-items-center gap-2">
                        @include('componentes._icon', ['name' => 'shield-check', 'label' => null])
                        {{ __('Cerrar verificación y sellar') }}
                    </button>
                </div>
            </form>

            <script>
                (function () {
                    // Muestra el padrón del proveedor elegido; oculta el resto.
                    var sel = document.getElementById('providerSel');
                    var rosters = document.querySelectorAll('.crew-roster');
                    var hint = document.getElementById('crewHint');
                    function syncRoster() {
                        var v = sel ? sel.value : '';
                        var shown = false;
                        rosters.forEach(function (r) {
                            var match = (r.getAttribute('data-provider') === v && v !== '');
                            r.style.display = match ? '' : 'none';
                            if (match) shown = true;
                        });
                        if (hint) hint.style.display = shown ? 'none' : '';
                    }
                    if (sel) { sel.addEventListener('change', syncRoster); syncRoster(); }

                    // Agrega filas de tripulante de texto libre.
                    var btn = document.getElementById('addCrew');
                    var rows = document.getElementById('crewRows');
                    var tpl = document.getElementById('crewTpl');
                    if (btn && rows && tpl) {
                        var idx = {{ count($oldCrew) }};
                        btn.addEventListener('click', function () {
                            var html = tpl.innerHTML.replace(/__IDX__/g, idx);
                            var holder = document.createElement('div');
                            holder.innerHTML = html.trim();
                            rows.appendChild(holder.firstChild);
                            idx++;
                        });
                    }
                })();
            </script>

            {{-- Cámara del set: convierte HEIC y comprime EN EL NAVEGADOR antes de subir. --}}
            <script src="/js/cc-photo.js"></script>
            <script src="/js/cc-photo-auto.js"></script>

            {{-- Buscador "escribe-y-filtra" para el <select> de proveedor (fallback sin JS: el select nativo). --}}
            @include('componentes._typeahead')
        @endif

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
