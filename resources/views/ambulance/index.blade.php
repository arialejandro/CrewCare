@extends('layouts.app')
@section('content')
@php
    // Chip ÚNICO del estado: UN solo color (verde / ámbar / rojo), nunca dos a la vez.
    //   verde = apta con tripulación · ámbar = apta pero sin tripulación mínima (o no ejecutable)
    //   · rojo = paro. La clave 'apta_sin_tripulacion' es un estado DERIVADO (no del veredicto).
    $verdictChip = [
        'paro'                    => ['t' => __('PARO'),             'bg' => '#fee2e2', 'fg' => '#991b1b', 'ic' => 'octagon-alert',  'bd' => '#dc2626'],
        'actividad_no_ejecutable' => ['t' => __('No ejecutable'),    'bg' => '#fef9c3', 'fg' => '#854d0e', 'ic' => 'alert-triangle', 'bd' => '#d97706'],
        'apta'                    => ['t' => __('Apta'),             'bg' => '#dcfce7', 'fg' => '#166534', 'ic' => 'circle-check',   'bd' => '#16a34a'],
        'apta_sin_tripulacion'    => ['t' => __('Sin tripulación'),  'bg' => '#fef9c3', 'fg' => '#854d0e', 'ic' => 'alert-triangle', 'bd' => '#d97706'],
    ];

    // Estado único (string) de un acta, combinando veredicto + tripulación mínima.
    $ambState = function ($acta) {
        $crew = \App\Support\AmbulanceVerdict::crewSummary((array) $acta->crew_snapshot);
        if ($acta->verdict === 'apta' && ! $crew['sufficient']) {
            return 'apta_sin_tripulacion';
        }
        return $acta->verdict;
    };
@endphp

{{-- HUB del bloque. El recurso de traslado del día se ve PRIMERO: hay ambulancia,
     hay un medio declarado (arreglo legítimo, NO falla), o hay hueco. --}}
<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4">

        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Verificación de recursos de emergencia') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Ambulancia en sitio o medio de traslado declarado: el criterio, fijado antes.') }}</p>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @can('ambulance.manage')
                <a href="{{ route('ambulance.inspect.form') }}" class="btn btn-crew-accent d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'shield-check', 'label' => null]) {{ __('Verificar ambulancia') }}
                </a>
                @endcan
                <a href="{{ route('ambulance.providers') }}" class="btn btn-crew-soft d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'truck', 'label' => null]) {{ __('Proveedores') }}
                </a>
                <a href="{{ route('ambulance.records') }}" class="btn btn-crew-soft d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'label' => null]) {{ __('Actas') }}
                </a>
            </div>
        </div>

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        {{-- ── RECURSO DEL DÍA (3 estados) ─────────────────────────────────── --}}
        <h5 class="mb-2 d-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'heart-pulse', 'label' => null])
            {{ __('Recurso de traslado del día') }}
        </h5>

        @if ($dayResource && $dayResource->hasAmbulance())
            @php
                $insp0 = $dayResource->inspection;
                // Estado ÚNICO del recurso (un color): veredicto del acta + tripulación mínima.
                $st = $insp0 ? ($verdictChip[$ambState($insp0)] ?? $verdictChip['apta']) : $verdictChip['apta'];
            @endphp
            {{-- Estado 1: hay ambulancia en sitio. El color (verde/ámbar/rojo) es UNO solo, del estado
                 real del acta: apta con tripulación = verde; apta sin tripulación mínima = ámbar. --}}
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-4" style="border-left:4px solid {{ $st['bd'] }} !important;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="insp-tag" style="background:{{ $st['bg'] }};color:{{ $st['fg'] }};">
                            @include('componentes._icon', ['name' => $st['ic'], 'label' => null]) {{ __('Ambulancia en sitio') }} · {{ $st['t'] }}
                        </span>
                        @if ($insp0)
                            <span class="text-muted small">{{ $insp0->type_name }} · {{ $insp0->provider_name ?: '—' }}</span>
                        @endif
                    </div>
                    @if ($insp0)
                        <a href="{{ route('ambulance.acta', $insp0->uuid) }}" class="btn btn-sm btn-outline-success">{{ __('Ver acta') }}</a>
                    @endif
                </div>
            </div>
        @elseif ($dayResource && $dayResource->hasDeclaredMedium())
            {{-- Estado 2: sin ambulancia, CON medio declarado. Se presenta NEUTRAL:
                 es una decisión legítima de producción, no un hallazgo. --}}
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-4" style="border-left:4px solid #2563eb !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="insp-tag" style="background:#dbeafe;color:#1e40af;">
                        @include('componentes._icon', ['name' => 'truck', 'label' => null]) {{ __('Medio de traslado declarado') }}
                    </span>
                </div>
                <div class="row g-3 small">
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Medio') }}</span>
                        {{ $dayResource->transport_means ?: '—' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Servicio a llamar') }}</span>
                        {{ $dayResource->call_service ?: '—' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Teléfono') }}</span>
                        @if ($dayResource->call_phone)
                            <a href="tel:{{ $dayResource->call_phone }}" class="d-inline-flex align-items-center gap-1">
                                @include('componentes._icon', ['name' => 'phone', 'label' => null]) {{ $dayResource->call_phone }}
                            </a>
                        @else — @endif
                    </div>
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Tiempo de respuesta') }}</span>
                        {{ $dayResource->response_time ?: '—' }}</div>
                </div>
                @can('ambulance.manage')
                <div class="mt-3">
                    <a href="{{ route('ambulance.day.form') }}" class="btn btn-sm btn-crew-soft">{{ __('Cambiar el recurso del día') }}</a>
                </div>
                @endcan
            </div>
        @else
            {{-- Estado 3 (o sin declarar): HUECO visible. Se ve como lo que es. --}}
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4 text-center" style="border:1px dashed var(--border, #d1d5db);">
                <div class="crew-empty-icon mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle">
                    @include('componentes._icon', ['name' => 'octagon-alert', 'label' => null])
                </div>
                <h5 class="mb-1">{{ __('Sin recurso de traslado declarado hoy') }}</h5>
                <p class="text-muted mb-3">{{ __('Fija el criterio antes de rodar: ambulancia en sitio o el medio con el que se atiende un traslado.') }}</p>
                <div>
                    <a href="{{ route('ambulance.day.form') }}" class="btn btn-crew-accent d-inline-flex align-items-center gap-1">
                        @include('componentes._icon', ['name' => 'file-check', 'label' => null]) {{ __('Declarar recurso del día') }}
                    </a>
                </div>
            </div>
        @endif

        {{-- ── ÚLTIMAS ACTAS ───────────────────────────────────────────────── --}}
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'clipboard-check', 'label' => null])
                {{ __('Últimas actas') }}
            </h5>
            <a href="{{ route('ambulance.records') }}" class="small">{{ __('Ver todas') }}</a>
        </div>

        @if (isset($inspections) && $inspections->count())
            <div class="row g-3">
                @foreach ($inspections as $acta)
                    @php
                        $c     = $verdictChip[$ambState($acta)] ?? $verdictChip['apta'];
                        $photo = method_exists($acta, 'unitPhotoUrl') ? $acta->unitPhotoUrl() : null;
                    @endphp
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="{{ route('ambulance.acta', $acta->uuid) }}"
                           class="card border-0 shadow-sm rounded-3 h-100 text-decoration-none amb-acard"
                           style="border-left:4px solid {{ $c['bd'] }} !important;">
                            <div class="amb-acard-media">
                                @if ($photo)
                                    <img src="{{ $photo }}" alt="{{ $acta->type_name }}" loading="lazy">
                                @else
                                    <span class="amb-acard-ph">@include('componentes._icon', ['name' => 'ambulance', 'label' => null])</span>
                                @endif
                                <span class="insp-tag amb-acard-chip" style="background:{{ $c['bg'] }};color:{{ $c['fg'] }};">
                                    @include('componentes._icon', ['name' => $c['ic'], 'label' => null]) {{ $c['t'] }}
                                </span>
                            </div>
                            <div class="p-3">
                                <div class="amb-acard-folio">{{ $acta->folio() }}</div>
                                <div class="amb-acard-sub text-truncate">{{ $acta->type_name }}</div>
                                <div class="amb-acard-sub text-truncate">{{ $acta->provider_name ?: '—' }} · {{ optional($acta->created_at)->format('d/m/Y') }}</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card border-0 shadow-sm rounded-3">
                <div class="text-center py-5">
                    <div class="crew-empty-icon mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle">
                        @include('componentes._icon', ['name' => 'clipboard-list', 'label' => null])
                    </div>
                    <h5 class="mb-1">{{ __('Sin actas') }}</h5>
                    <p class="text-muted mb-0">{{ __('Aún no se ha verificado ninguna ambulancia.') }}</p>
                </div>
            </div>
        @endif

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
    <style>
      /* Cards de "Últimas actas": foto de la unidad (o icono de ambulancia) + chip de estado.
         Theme-aware: usa los tokens de la app (--text/--text-muted/--surface-3) para claro y oscuro. */
      .amb-acard{ overflow:hidden; color:inherit; transition:transform .12s ease, box-shadow .12s ease; }
      .amb-acard:hover{ transform:translateY(-2px); box-shadow:0 .55rem 1.1rem rgba(0,0,0,.22) !important; }
      .amb-acard-media{ position:relative; aspect-ratio:16/9; background:var(--surface-3, #eceff3);
        display:flex; align-items:center; justify-content:center; overflow:hidden; }
      .amb-acard-media img{ width:100%; height:100%; object-fit:cover; display:block; }
      .amb-acard-ph{ color:var(--text-muted, #94a3b8); }
      .amb-acard-ph svg{ width:46px; height:46px; }
      .amb-acard-chip{ position:absolute; top:8px; right:8px; box-shadow:0 1px 3px rgba(0,0,0,.25); }
      .amb-acard-folio{ font-weight:700; color:var(--text, #1f2937); }
      .amb-acard-sub{ color:var(--text-muted, #566072); font-size:.82rem; }
    </style>
@endpush

@endsection
