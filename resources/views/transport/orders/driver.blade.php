@extends('layouts.app')
@section('content')
{{-- MIS CORRIDAS — pantalla del driver (§2 Capa 4). Móvil primero: se usa en la calle, de
     madrugada, con una mano. Muestra SÓLO las corridas del driver, con la DIRECCIÓN REAL de las
     privadas de sus corridas (se la gana por asignación). No es documento; no se imprime. --}}
<div class="crew-page insp-page">
    <div class="container-fluid px-3 py-4" style="max-width:640px">

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'truck', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Mis corridas') }}</h1>
                <p class="text-muted mb-0 small">{{ \Carbon\Carbon::parse($date)->translatedFormat('l d \d\e F Y') }}</p>
            </div>
        </div>

        {{-- Selector de día (una mano). --}}
        <form method="GET" action="{{ route('transport.driver.runs') }}" class="d-flex gap-2 align-items-center mb-3">
            <input type="date" name="date" value="{{ $date }}" class="form-control form-control-lg" onchange="this.form.submit()">
            <a href="{{ route('transport.driver.runs') }}" class="btn btn-lg btn-outline-secondary">{{ __('Hoy') }}</a>
        </form>

        @if (! $hasDate)
            <div class="alert alert-secondary">{{ __('No hay una orden emitida para este día.') }}</div>
        @elseif (empty($cards))
            <div class="alert alert-secondary">{{ __('No tienes corridas asignadas este día.') }}</div>
        @else
            @foreach ($cards as $c)
                <div class="border rounded-4 shadow-sm p-3 mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        @if ($c['pickup_time'])
                            <span class="fw-bold" style="font-size:1.6rem;line-height:1">{{ $c['pickup_time'] }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                        <span class="badge bg-light text-dark border">{{ $c['type'] }}</span>
                    </div>

                    @if ($c['vehicle'])
                        <div class="mb-2">
                            @include('componentes._icon', ['name' => 'truck', 'label' => null])
                            <span class="fw-semibold">{{ $c['vehicle'] }}</span>
                            @if ($c['plate'])<span class="font-monospace text-muted ms-1">{{ $c['plate'] }}</span>@endif
                        </div>
                    @endif

                    {{-- Recoger --}}
                    <div class="mb-2">
                        <div class="text-uppercase text-muted small">{{ __('Recoger en') }}</div>
                        <div class="fw-semibold">{{ $c['pickup_place'] }}</div>
                        @if ($c['pickup_street'])
                            <div class="d-flex align-items-start gap-1 mt-1">
                                @include('componentes._icon', ['name' => 'map-pin', 'label' => null])
                                <span>{{ $c['pickup_street'] }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Llevar --}}
                    <div class="mb-2">
                        <div class="text-uppercase text-muted small">{{ __('Llevar a') }}</div>
                        <div class="fw-semibold">{{ $c['dest_place'] ?: '—' }}</div>
                        @if ($c['dest_street'])
                            <div class="d-flex align-items-start gap-1 mt-1">
                                @include('componentes._icon', ['name' => 'map-pin', 'label' => null])
                                <span>{{ $c['dest_street'] }}</span>
                            </div>
                        @endif
                    </div>

                    @if (! empty($c['occupants']))
                        <div class="mb-1">
                            <div class="text-uppercase text-muted small">{{ __('Ocupantes') }}</div>
                            <ul class="mb-0 ps-3">@foreach ($c['occupants'] as $o)<li>{{ $o }}</li>@endforeach</ul>
                        </div>
                    @endif

                    @if ($c['notes'])
                        <div class="border-top pt-2 mt-2 small">{{ $c['notes'] }}</div>
                    @endif
                </div>
            @endforeach
        @endif

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
