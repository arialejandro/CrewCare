@extends('layouts.app')
@section('content')
@php
    // Estado prefijado: rebote de validación primero, valor guardado después.
    $state = old('state', $resource->state ?? '');
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 820px;">

        <a href="{{ route('ambulance.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-3" style="text-decoration:none;">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null]) {{ __('Volver a recursos de emergencia') }}
        </a>

        <div class="d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'heart-pulse', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Recurso de traslado del día') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Fija el criterio ANTES de rodar, no cuando ya pasó algo.') }}</p>
            </div>
        </div>

        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        {{-- Si ya hay un acta de ambulancia vigente, elegir "ambulancia en sitio" queda respaldado por ella. --}}
        @if ($vigente)
            <div class="alert alert-success d-flex align-items-center justify-content-between gap-2 py-2 flex-wrap">
                <span class="d-inline-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'circle-check', 'label' => null])
                    {{ __('Hay un acta de ambulancia vigente') }} ({{ $vigente->folio() }})
                </span>
                <a href="{{ route('ambulance.acta', $vigente->uuid) }}" class="btn btn-sm btn-outline-success">{{ __('Ver acta') }}</a>
            </div>
        @endif

        <form method="post" action="{{ route('ambulance.day.store') }}">
            @csrf
            {{-- Estado 1: si hay acta vigente, se liga (el controlador solo usa estos
                 campos cuando el estado es "ambulancia en sitio"). --}}
            @if ($vigente)
                <input type="hidden" name="ambulance_inspection_id" value="{{ $vigente->id }}">
                @if ($vigente->provider_id)<input type="hidden" name="provider_id" value="{{ $vigente->provider_id }}">@endif
            @endif

            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <label class="form-label small fw-semibold d-block mb-2">{{ __('¿Con qué se cuenta hoy?') }} *</label>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="state" id="stAmb" value="ambulance_on_site"
                           @checked($state === 'ambulance_on_site')>
                    <label class="form-check-label" for="stAmb">
                        <strong>{{ __('Ambulancia en sitio') }}</strong>
                        <span class="d-block text-muted small">{{ __('Hay una unidad presente. Se verifica y su acta respalda este estado.') }}</span>
                    </label>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="state" id="stMed" value="declared_medium"
                           @checked($state === 'declared_medium')>
                    <label class="form-check-label" for="stMed">
                        <strong>{{ __('Medio de traslado declarado') }}</strong>
                        <span class="d-block text-muted small">{{ __('Sin ambulancia en sitio, pero con un medio definido para atender un traslado.') }}</span>
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="state" id="stNone" value="none"
                           @checked($state === 'none')>
                    <label class="form-check-label" for="stNone">
                        <strong>{{ __('Nada por ahora') }}</strong>
                        <span class="d-block text-muted small">{{ __('Queda registrado como hueco, visible hasta que se declare un recurso.') }}</span>
                    </label>
                </div>
            </div>

            {{-- Bloque del medio declarado. Ayuda: elegir esto es una decisión legítima de
                 producción, NO una falla. El valor está en fijarlo antes, no en improvisarlo. --}}
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3" id="mediumWrap">
                <div class="alert alert-info d-flex align-items-start gap-2 py-2">
                    @include('componentes._icon', ['name' => 'info', 'label' => null])
                    <span>{{ __('Declarar un medio de traslado es una decisión válida de producción, no una falla. Lo que importa es dejar el criterio fijado por adelantado.') }}</span>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold">{{ __('Medio de traslado') }}</label>
                        <input type="text" name="transport_means" class="form-control" maxlength="255"
                               value="{{ old('transport_means', $resource->transport_means ?? '') }}"
                               placeholder="{{ __('p. ej. vehículo de producción para lo no grave') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Servicio a llamar') }}</label>
                        <input type="text" name="call_service" class="form-control" maxlength="255"
                               value="{{ old('call_service', $resource->call_service ?? '') }}"
                               placeholder="{{ __('a quién se llama para lo grave') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Teléfono') }}</label>
                        <input type="tel" name="call_phone" class="form-control" maxlength="40"
                               value="{{ old('call_phone', $resource->call_phone ?? '') }}"
                               placeholder="{{ __('número de contacto') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">{{ __('Tiempo de respuesta') }}</label>
                        <input type="text" name="response_time" class="form-control" maxlength="120"
                               value="{{ old('response_time', $resource->response_time ?? '') }}"
                               placeholder="{{ __('en cuánto llega') }}">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
                <label class="form-label small fw-semibold">{{ __('Notas (opcional)') }}</label>
                <textarea name="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes', $resource->notes ?? '') }}</textarea>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('ambulance.index') }}" class="btn btn-link text-muted">{{ __('Cancelar') }}</a>
                <button type="submit" class="btn btn-crew-accent d-inline-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'file-check', 'label' => null])
                    {{ __('Guardar recurso del día') }}
                </button>
            </div>
        </form>

    </div>
</div>

<script>
    (function () {
        var wrap = document.getElementById('mediumWrap');
        var radios = document.querySelectorAll('input[name="state"]');
        function sync() {
            var on = false;
            radios.forEach(function (r) { if (r.checked && r.value === 'declared_medium') on = true; });
            wrap.style.display = on ? '' : 'none';
        }
        radios.forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    })();
</script>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
