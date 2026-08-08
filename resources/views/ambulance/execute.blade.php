@extends('layouts.app')
@section('content')
@php
    $ramaLabels = [
        'todas'     => __('Comunes a toda ambulancia'),
        'terrestre' => __('Terrestre'),
        'aerea'     => __('Aérea'),
        'maritima'  => __('Marítima'),
    ];
    $riskLabels = [1 => __('Muy bajo'), 2 => __('Bajo'), 3 => __('Medio'), 4 => __('Alto'), 5 => __('Muy alto')];
    $capLabels  = [1 => __('Apéndice A · traslado'), 2 => __('Apéndice B · básica'), 3 => __('Apéndice C · avanzada'), 4 => __('Apéndice D · cuidados intensivos')];
    // Roles SIN abreviar (la sigla va entre paréntesis; el valor guardado es el texto completo).
    $crewRoles = [
        'Técnico en Atención Médica Prehospitalaria (TAMP)',
        'Médico con capacitación prehospitalaria',
        'Operador de ambulancia',
        'Otro',
    ];
    $oldCrew   = old('crew', []);
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

        @if (! $type)
            {{-- ── SELECTOR: elegir el tipo de ambulancia (una sola vez) ─────────── --}}
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Verificar ambulancia') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Cada ambulancia en set se verifica completa. Elige el tipo para armar el checklist.') }}</p>
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
                                        @selected((int) old('type_id') === (int) $t->id)>
                                        {{ $t->code }} · {{ $t->name_es }}
                                    </option>
                                @endforeach
                            </select>
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
            {{-- ── FORMULARIO EN SITIO: un solo checklist COMPLETO ──────────────── --}}
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
                <span class="insp-tag">{{ __('Verificación completa') }}</span>
                @if (! empty($capacityLevel))<span class="insp-tag">{{ $capLabels[(int) $capacityLevel] ?? $capacityLevel }}</span>@endif
                <span class="insp-tag">{{ $points->count() }} {{ __('puntos') }}</span>
                @if ($gateCount > 0)<span class="insp-tag insp-tag--gate">{{ $gateCount }} {{ __('compuerta(s)') }}</span>@endif
                <a href="{{ route('ambulance.inspect.form') }}" class="insp-tag" style="text-decoration:none;">{{ __('cambiar tipo') }}</a>
            </div>

            <div class="alert alert-info d-flex align-items-start gap-2 py-2">
                @include('componentes._icon', ['name' => 'info', 'label' => null])
                <span>{{ __('Constancia de verificación de recurso de emergencia en sitio. No sustituye la inspección sanitaria de la autoridad.') }}</span>
            </div>

            <form method="post" action="{{ route('ambulance.inspect.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type_id" value="{{ $type->id }}">
                @if (! empty($capacityLevel))<input type="hidden" name="capacity_level" value="{{ $capacityLevel }}">@endif

                {{-- (a) DATOS DE LA UNIDAD + EMPRESA ───────────────────────────── --}}
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                    <h5 class="mb-1">{{ __('Unidad y empresa') }}</h5>
                    <p class="text-muted small">{{ __('Elige la empresa si ya está dada de alta. Si es de otra empresa, da una de alta aquí mismo.') }}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Empresa proveedora (existente)') }}</label>
                            <select name="provider_id" id="providerSel" class="form-select js-typeahead">
                                <option value="">{{ __('— Empresa —') }}</option>
                                @foreach ($providers as $prov)
                                    <option value="{{ $prov->id }}" @selected((int) old('provider_id') === (int) $prov->id)>{{ $prov->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('…o alta de empresa nueva') }}</label>
                            <input type="text" name="new_provider_name" class="form-control" maxlength="255"
                                   value="{{ old('new_provider_name') }}" placeholder="{{ __('Nombre de la empresa') }}">
                            <div class="form-text">{{ __('Déjalo vacío si elegiste una empresa arriba.') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Placas') }}</label>
                            <input type="text" name="plates" class="form-control" maxlength="40" value="{{ old('plates') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Número económico') }}</label>
                            <input type="text" name="economic_number" class="form-control" maxlength="60" value="{{ old('economic_number') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Locación de la verificación') }}</label>
                            <input type="text" name="location_label" id="ambLocation" class="form-control" maxlength="255"
                                   value="{{ old('location_label') }}" placeholder="{{ __('Dónde se verificó') }}">
                            <small id="ambLocationNote" class="form-text">{{ __('Se sugiere sola por GPS; puedes ajustarla.') }}</small>
                            {{-- GPS-back SILENCIOSO: capta la posición por detrás (lat/lng ocultos) y, si hay
                                 un scouting cercano, sugiere su NOMBRE en la Locación. Ver [[scouting-geo-module]]. --}}
                            @include('componentes._geo-capture', [
                                'mode'          => 'silent',
                                'suggestTarget' => '#ambLocation',
                                'noteTarget'    => '#ambLocationNote',
                            ])
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">{{ __('Foto de la unidad (opcional)') }}</label>
                            <input type="file" name="unit_photo" class="form-control" accept="image/*,.heic,.heif" capture="environment" data-cc-photo>
                            <div class="form-text">{{ __('La unidad real; queda sellada en el acta. Opcional.') }}</div>
                        </div>
                    </div>
                </div>

                {{-- (a2) EVIDENCIA FOTOGRÁFICA (varias, opcional) ─────────────── --}}
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                    <h5 class="mb-1">{{ __('Evidencia fotográfica (opcional)') }}</h5>
                    <p class="text-muted small">{{ __('Agrega las fotos que necesites como prueba de lo verificado: sostienen tanto una revocación (paro) como una autorización (apta) después del hecho. Quedan selladas en el acta.') }}</p>
                    <div id="evidenceRows">
                        <div class="mb-2 evidence-row">
                            <input type="file" name="evidence_photos[]" class="form-control" accept="image/*,.heic,.heif" capture="environment" data-cc-photo>
                        </div>
                    </div>
                    <button type="button" id="addEvidence" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                        @include('componentes._icon', ['name' => 'plus', 'label' => null]) {{ __('Agregar otra foto') }}
                    </button>
                    <template id="evidenceTpl">
                        <div class="mb-2 evidence-row">
                            <input type="file" name="evidence_photos[]" class="form-control" accept="image/*,.heic,.heif" capture="environment" data-cc-photo>
                        </div>
                    </template>
                </div>

                {{-- (b) TRIPULACIÓN + COTEJO CONOCER ───────────────────────────── --}}
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                    <h5 class="mb-1">{{ __('Tripulación') }}</h5>
                    <p class="text-muted small">
                        {{ __('El Técnico en Atención Médica Prehospitalaria (TAMP) se coteja como verificado con su folio CONOCER, la foto de su certificado y una foto de la persona (el certificado puede ser de otra persona: por eso se coteja la cara contra el papel).') }}
                    </p>

                    {{-- Padrón de la empresa elegida (si ya tiene tripulantes dados de alta). --}}
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
                                <p class="text-muted small mb-0">{{ __('Esta empresa no tiene tripulantes dados de alta; agrégalos abajo.') }}</p>
                            @endforelse
                        </div>
                    @endforeach
                    <p id="crewHint" class="text-muted small">{{ __('Elige una empresa para ver su padrón, o agrega tripulantes abajo.') }}</p>

                    {{-- Altas nuevas de tripulante (con cotejo CONOCER). --}}
                    <div id="crewRows">
                        @foreach ($oldCrew as $i => $oc)
                            @include('ambulance.partials._crew-new-row', ['i' => $i, 'oc' => $oc, 'crewRoles' => $crewRoles])
                        @endforeach
                    </div>

                    <button type="button" id="addCrew" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                        @include('componentes._icon', ['name' => 'plus', 'label' => null]) {{ __('Agregar tripulante') }}
                    </button>

                    <template id="crewTpl">
                        @include('ambulance.partials._crew-new-row', ['i' => '__IDX__', 'oc' => [], 'crewRoles' => $crewRoles])
                    </template>
                </div>

                {{-- (c) CHECKLIST COMPLETO, agrupado por rama. --}}
                <div class="alert alert-warning d-flex align-items-start gap-2 py-2">
                    @include('componentes._icon', ['name' => 'octagon-alert', 'label' => null])
                    <span>{{ __('Un fallo en una compuerta puede ser PARO: la unidad no se usa hasta corregir o reemplazar.') }}</span>
                </div>
                <p class="text-muted small">{{ __('Nota: TAMP = Técnico en Atención Médica Prehospitalaria; RPBI = Residuos Peligrosos Biológico-Infecciosos; DEA = Desfibrilador Externo Automatizado.') }}</p>

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

                {{-- (d) CORRESPONDENCIA CON EL RIESGO DEL DÍA (opcional) ─────────── --}}
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                    <h5 class="mb-1">{{ __('Correspondencia con el riesgo del día (opcional)') }}</h5>
                    <p class="text-muted small">{{ __('Lo fija el criterio del safety, no el catálogo: ¿el tipo de ambulancia alcanza para el riesgo de la jornada? Déjalo vacío si no aplica hoy.') }}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Nivel de riesgo del día') }}</label>
                            <select name="day_risk_level" class="form-select">
                                <option value="">{{ __('— Sin declarar —') }}</option>
                                @foreach ($riskLabels as $lvl => $lbl)
                                    <option value="{{ $lvl }}" @selected((int) old('day_risk_level') === $lvl)>{{ $lvl }} · {{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold d-block">{{ __('¿El tipo alcanza para ese riesgo?') }}</label>
                            <div class="d-flex gap-3 mt-1">
                                <label class="d-inline-flex align-items-center gap-1">
                                    <input type="radio" name="correspondence_ok" value="1" @checked(old('correspondence_ok') === '1')>
                                    {{ __('Sí, corresponde') }}
                                </label>
                                <label class="d-inline-flex align-items-center gap-1">
                                    <input type="radio" name="correspondence_ok" value="0" @checked(old('correspondence_ok') === '0')>
                                    {{ __('No corresponde') }}
                                </label>
                            </div>
                            <div class="form-text">{{ __('Queda registrado en el acta como dato de la verificación.') }}</div>
                        </div>
                    </div>
                </div>

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
                    // Muestra el padrón de la empresa elegida; oculta el resto.
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

                    // Agrega filas de tripulante nuevo (con inputs de foto data-cc-photo, que
                    // cc-photo-auto.js cablea solo por delegación).
                    var btn = document.getElementById('addCrew');
                    var rows = document.getElementById('crewRows');
                    var tpl = document.getElementById('crewTpl');
                    if (btn && rows && tpl) {
                        var idx = {{ count($oldCrew) }};
                        btn.addEventListener('click', function () {
                            var html = tpl.innerHTML.replace(/__IDX__/g, idx);
                            var holder = document.createElement('div');
                            holder.innerHTML = html.trim();
                            rows.appendChild(holder.firstElementChild);
                            idx++;
                        });
                    }

                    // Agrega más inputs de evidencia fotográfica (cada uno lo cablea
                    // cc-photo-auto.js por delegación).
                    var eBtn = document.getElementById('addEvidence');
                    var eRows = document.getElementById('evidenceRows');
                    var eTpl = document.getElementById('evidenceTpl');
                    if (eBtn && eRows && eTpl) {
                        eBtn.addEventListener('click', function () {
                            var holder = document.createElement('div');
                            holder.innerHTML = eTpl.innerHTML.trim();
                            eRows.appendChild(holder.firstElementChild);
                        });
                    }
                })();
            </script>

            {{-- Cámara del set: convierte HEIC y comprime EN EL NAVEGADOR antes de subir. --}}
            <script src="/js/cc-photo.js"></script>
            <script src="/js/cc-photo-auto.js"></script>

            {{-- Buscador "escribe-y-filtra" para el <select> de empresa (fallback sin JS: el select nativo). --}}
            @include('componentes._typeahead')
        @endif

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
