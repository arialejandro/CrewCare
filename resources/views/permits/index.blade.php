@extends('layouts.app')
@section('content')
@php
    // Etiqueta legible del alcance de sitio.
    $scopeLabel = [
        'indiferente'      => __('Vale por la jornada'),
        'reverificacion'   => __('Reverifica al mover'),
        'ligado_al_sitio'  => __('Ligado al sitio'),
    ];
    $launchQ = collect($launch ?? [])->only(['site','activity','tool_id'])->filter()->all();
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 980px;">

        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'file-check', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Permisos de trabajo') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Emite para una ACTIVIDAD, en un SITIO y una JORNADA. La herramienta lo dispara, no es su objeto.') }}</p>
                </div>
            </div>
            @if (! is_null($shootDay))
                <span class="insp-tag">{{ __('Día de rodaje') }}: {{ $shootDay }}</span>
            @endif
        </div>

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        {{-- Puerta desde la inspección (Paso 6): ¿hay permiso vigente para esta actividad aquí y hoy? --}}
        @if (! empty($launchAnswer))
            @php $la = $launchAnswer; @endphp
            <div class="card border-0 shadow-sm rounded-3 mb-4" style="border-left:4px solid {{ $la['vigente'] ? '#16a34a' : '#b45309' }} !important;">
                <div class="p-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        @include('componentes._icon', ['name' => 'help-circle', 'label' => null])
                        <strong>{{ __('¿Hay permiso vigente para esta actividad, aquí y hoy?') }}</strong>
                    </div>
                    <p class="small text-muted mb-2">{{ $la['permit']->code }} · {{ $la['permit']->name }}@if(!empty($launch['site'])) · {{ __('Sitio') }}: {{ $launch['site'] }}@endif</p>
                    @if ($la['vigente'])
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge bg-success">{{ __('SÍ — vigente') }}</span>
                            <a href="{{ route('permits.show', $la['vigente']->uuid) }}" class="btn btn-sm btn-outline-success">{{ $la['vigente']->folio() }} · {{ __('ver') }}</a>
                        </div>
                    @elseif (! empty($la['otroSitio']))
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge bg-warning text-dark">{{ __('Vigente, pero en OTRO sitio') }}</span>
                            <a href="{{ route('permits.show', $la['otroSitio']->uuid) }}" class="btn btn-sm btn-outline-warning">{{ $la['otroSitio']->folio() }} · {{ __('reverificar aquí') }}</a>
                        </div>
                    @else
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge bg-secondary">{{ __('No hay permiso vigente') }}</span>
                            <a href="{{ route('permits.create', array_merge([$la['permit']->id], $launchQ)) }}" class="btn btn-sm btn-crew-accent">
                                @include('componentes._icon', ['name' => 'plus', 'label' => null]) {{ __('Emitir') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- PENDIENTES DE CIERRE: emitidos en una jornada anterior y nunca cerrados. --}}
        @if ($pendingClose->isNotEmpty())
            <div class="card border-0 shadow-sm rounded-3 mb-4" style="border-left:4px solid #b42318 !important;">
                <div class="p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        @include('componentes._icon', ['name' => 'octagon-alert', 'label' => null])
                        <strong>{{ __('Pendientes de cierre') }}</strong>
                        <span class="insp-tag">{{ $pendingClose->count() }}</span>
                    </div>
                    <p class="small text-muted mb-2">{{ __('Emitidos en una jornada anterior y nunca cerrados. Cerrarlos es un acto de alguien; no ocurre solo.') }}</p>
                    <div class="d-flex flex-column gap-2">
                        @foreach ($pendingClose as $p)
                            <a href="{{ route('permits.show', $p->uuid) }}" class="d-flex justify-content-between align-items-center text-decoration-none border rounded-3 px-3 py-2">
                                <span><strong>{{ $p->folio() }}</strong> · {{ $p->permit_name }} <span class="text-muted">— {{ $p->activity_description }}</span></span>
                                <span class="insp-tag">{{ __('Día') }} {{ $p->shoot_day }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- VIGENTES HOY --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    @include('componentes._icon', ['name' => 'circle-check', 'label' => null])
                    <strong>{{ __('Vigentes hoy') }}</strong>
                    <span class="insp-tag">{{ $vigentesHoy->count() }}</span>
                </div>
                @if ($vigentesHoy->isEmpty())
                    <p class="small text-muted mb-0">{{ __('No hay permisos vigentes en esta jornada.') }}</p>
                @else
                    <div class="d-flex flex-column gap-2">
                        @foreach ($vigentesHoy as $p)
                            <a href="{{ route('permits.show', $p->uuid) }}" class="d-flex justify-content-between align-items-center text-decoration-none border rounded-3 px-3 py-2">
                                <span><strong>{{ $p->folio() }}</strong> · {{ $p->permit_name }} <span class="text-muted">— {{ $p->activity_description }}</span></span>
                                <span class="d-inline-flex gap-2">
                                    @if ($p->requires_fire_watch)<span class="insp-tag insp-tag--gate">{{ __('caliente') }}</span>@endif
                                    <span class="insp-tag">{{ $p->site_label }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- EMITIR: las 15 plantillas del catálogo, por familia. --}}
        <h5 class="mb-2">{{ __('Emitir un permiso') }}</h5>
        <p class="small text-muted">{{ __('Todo punto es compuerta: si uno no se cumple, no se emite.') }}</p>
        @foreach ($templates as $family => $permits)
            <div class="insp-scope-head">{{ $family }}</div>
            <div class="row g-2 mb-3">
                @foreach ($permits as $permit)
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $permit->name }}</div>
                                    <div class="small text-muted">{{ $permit->code }} · {{ $permit->points->count() }} {{ __('puntos') }}</div>
                                </div>
                                <div class="d-flex flex-column align-items-end gap-1">
                                    @if ($permit->requiresExternalAuthorization())
                                        <span class="insp-tag insp-tag--gate" title="{{ __('Autorización externa obligatoria') }}">{{ __('AUT. EXTERNA') }}</span>
                                    @endif
                                    <span class="insp-tag">{{ $scopeLabel[$permit->site_scope] ?? $permit->site_scope }}</span>
                                </div>
                            </div>
                            <div class="mt-2">
                                <a href="{{ route('permits.create', array_merge([$permit->id], $launchQ)) }}" class="btn btn-sm btn-crew-accent d-inline-flex align-items-center gap-1">
                                    @include('componentes._icon', ['name' => 'plus', 'label' => null]) {{ __('Emitir') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
