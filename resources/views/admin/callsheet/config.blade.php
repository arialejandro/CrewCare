@extends('layouts.app')
@section('content')
@push('styles')@include('admin.callsheet._styles')@endpush

@php
    use App\Support\CallSheetEngine;
    $general = $callDay->generalHHMM();
    $fe = is_array($callDay->footer_extra) ? $callDay->footer_extra : [];
    $wrapTime = $fe['wrap_time'] ?? '';
@endphp

<div class="container py-4 cs-wrap">

    <div class="adm-header">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'clapperboard', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Llamado del día</h1>
            <p class="adm-subtitle">El general es el ancla; todo lo demás cuelga de él.</p>
        </div>
    </div>

    @include('admin.callsheet._tabs', ['active' => 'config'])

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ route('callsheet.config.save', ['date' => $nav['dateStr']]) }}" method="POST">
        @csrf

        {{-- Configuración inicial en una línea: general · Cast · BG (+ wrap tras flag) --}}
        <div class="cs-hero">
            <div class="cs-hero__field">
                <label class="cs-hero__label" for="cs-general">Llamado general</label>
                <input type="time" name="general_call" id="cs-general" class="cs-hero__time" value="{{ old('general_call', $general) }}">
            </div>
            <div class="cs-hero__sep"></div>
            <div class="cs-hero__field">
                <label class="cs-hero__label" for="cs-cast">Cast</label>
                <input type="number" min="0" name="cast_count" id="cs-cast" class="cs-hero__num" value="{{ old('cast_count', $callDay->cast_count) }}" placeholder="0">
            </div>
            <div class="cs-hero__field">
                <label class="cs-hero__label" for="cs-bg">BG</label>
                <input type="number" min="0" name="bg_count" id="cs-bg" class="cs-hero__num" value="{{ old('bg_count', $callDay->bg_count) }}" placeholder="0">
            </div>
            @feature('callsheet_wrap_estimate')
            <div class="cs-hero__sep"></div>
            <div class="cs-hero__field">
                <label class="cs-hero__label" for="cs-wrap">Wrap</label>
                <input type="time" name="wrap_time" id="cs-wrap" class="cs-hero__time" value="{{ old('wrap_time', $wrapTime) }}">
            </div>
            @endfeature
        </div>
        <p class="cs-hero__hint">El crew se cuenta solo con las marcas <b>#</b> de Personas; aquí solo Cast y BG.</p>

        {{-- Comidas (auto por la hora del general) --}}
        <div class="card cs-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Comidas</span>
                <span class="d-flex gap-2">
                    <button type="submit" formaction="{{ route('callsheet.meals.regen', ['date' => $nav['dateStr']]) }}" class="btn btn-sm btn-outline-secondary">Restablecer automáticas</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cs-add-meal">+ Servicio</button>
                </span>
            </div>
            <div class="card-body">
                @if(! $general)
                    <p class="cs-note mb-3">Define el llamado general y guarda: las comidas se establecen solas según la hora.</p>
                @endif
                <div id="cs-meals" class="cs-meals">
                    @foreach($meals as $i => $meal)
                        @php $mtime = CallSheetEngine::mealTime($meal, $general); @endphp
                        <div class="cs-mcard {{ $meal->enabled ? '' : 'is-off' }}">
                            <div class="cs-mcard__top">
                                <label class="cs-switch" title="Activo">
                                    <input type="hidden" name="meals[{{ $i }}][id]" value="{{ $meal->id }}">
                                    <input type="checkbox" class="cs-meal-on" name="meals[{{ $i }}][enabled]" value="1" @checked($meal->enabled)>
                                    <span></span>
                                </label>
                                <input type="text" class="cs-mcard__label" name="meals[{{ $i }}][label]" value="{{ $meal->label }}">
                                <button type="button" class="cs-icon-btn cs-del-meal" aria-label="Quitar">@include('componentes._icon', ['name' => 'trash-2', 'class' => 'cc-ico', 'label' => null])</button>
                            </div>
                            <div class="cs-mcard__row">
                                <input type="time" class="form-control" name="meals[{{ $i }}][time]" value="{{ $mtime }}">
                                <select class="form-select" name="meals[{{ $i }}][place_id]" title="Lugar">
                                    <option value="">Lugar</option>
                                    @foreach($places as $pl)
                                        <option value="{{ $pl->id }}" @selected($meal->place_id === $pl->id)>{{ $pl->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Notas del back (globales de la producción): se escriben una vez, salen en todos los backs. --}}
        <div class="card cs-card">
            <div class="card-header">Notas del back <span class="text-muted fw-normal" style="font-size:.82rem">· globales de la producción</span></div>
            <div class="card-body">
                <label class="form-label" for="cs-safety">Línea de seguridad (banda del encabezado)</label>
                <input type="text" id="cs-safety" name="safety_bar" class="form-control" maxlength="500"
                       value="{{ old('safety_bar', $safetyBar) }}"
                       placeholder="Tu seguridad es primero  ·  No hay llamado forzado sin aprobación del UPM  ·  Los pick ups salen a la hora marcada">
                <div class="form-text mb-3">Aparece como banda bajo el encabezado. Sepáralas con “·” si quieres varias.</div>

                <label class="form-label" for="cs-notes">Notas generales (pie del back)</label>
                <textarea id="cs-notes" name="notes" class="form-control" rows="5" placeholder="Producción no se hace responsable de autos particulares.&#10;Aviso anti-acoso: …&#10;Revisar orden de transportación …">{{ old('notes', $notes) }}</textarea>
                <div class="form-text mb-3">
                    Disclaimer de autos, anti-acoso, orden de transpo, nomenclaturas de pick up.
                    Los <b>canales de radio</b> se ajustan en <a href="{{ route('callsheet.departments', ['date' => $nav['dateStr']]) }}">Departamentos</a>
                    y la <b>leyenda de claves de lugar</b> en <a href="{{ route('callsheet.places') }}">Lugares</a>; ambas salen solas en el pie.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="cs-emerg">Emergencia / Hospital</label>
                        <textarea id="cs-emerg" name="emergency_note" class="form-control" rows="3" placeholder="Hospital más cercano: … Tel …&#10;Emergencias 911 · Cruz Roja 065&#10;Médico en set: …">{{ old('emergency_note', $emergNote) }}</textarea>
                        <div class="form-text">Sale en el pie de los formatos US / International.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cs-hotels">Hoteles</label>
                        <textarea id="cs-hotels" name="hotels_note" class="form-control" rows="3" placeholder="FS = Four Seasons&#10;SH = Sheraton&#10;SC = Suites Capri">{{ old('hotels_note', $hotelsNote) }}</textarea>
                        <div class="form-text">Clave = nombre. Sale en los formatos con Hotel.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Firma para aprobación: visible, con su explicación. --}}
        <div class="cs-sign">
            <span class="cs-sign__ico">@include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico', 'label' => null])</span>
            <div class="cs-sign__body">
                <div class="cs-sign__title">Firma para aprobación en el back</div>
                <div class="cs-sign__sub">Enciende para imprimir una línea “Aprobado por ______” al pie del PDF.</div>
            </div>
            <label class="cs-switch cs-switch--lg" title="Firma en el back">
                <input type="checkbox" name="sign_enabled" value="1" @checked(old('sign_enabled', $callDay->sign_enabled))>
                <span></span>
            </label>
        </div>

        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary">
                @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Guardar
            </button>
        </div>
    </form>
</div>

<template id="cs-meal-tpl">
    <div class="cs-mcard">
        <div class="cs-mcard__top">
            <label class="cs-switch" title="Activo">
                <input type="hidden" name="meals[__i__][id]" value="">
                <input type="checkbox" class="cs-meal-on" name="meals[__i__][enabled]" value="1" checked>
                <span></span>
            </label>
            <input type="text" class="cs-mcard__label" name="meals[__i__][label]" placeholder="Servicio">
            <button type="button" class="cs-icon-btn cs-del-meal" aria-label="Quitar">@include('componentes._icon', ['name' => 'trash-2', 'class' => 'cc-ico', 'label' => null])</button>
        </div>
        <div class="cs-mcard__row">
            <input type="time" class="form-control" name="meals[__i__][time]">
            <select class="form-select" name="meals[__i__][place_id]" title="Lugar">
                <option value="">Lugar</option>
                @foreach($places as $pl)<option value="{{ $pl->id }}">{{ $pl->code }}</option>@endforeach
            </select>
        </div>
    </div>
</template>

@push('scripts')
<script>
(function () {
    var wrap = document.getElementById('cs-meals'), tpl = document.getElementById('cs-meal-tpl'),
        add = document.getElementById('cs-add-meal'), n = {{ count($meals) }};
    add && add.addEventListener('click', function () {
        var div = document.createElement('div'); div.innerHTML = tpl.innerHTML.replace(/__i__/g, n++);
        wrap.appendChild(div.firstElementChild);
    });
    wrap && wrap.addEventListener('click', function (e) {
        var b = e.target.closest('.cs-del-meal'); if (b) b.closest('.cs-mcard').remove();
    });
    wrap && wrap.addEventListener('change', function (e) {
        if (e.target.classList.contains('cs-meal-on')) e.target.closest('.cs-mcard').classList.toggle('is-off', !e.target.checked);
    });
})();
</script>
@endpush
@endsection
