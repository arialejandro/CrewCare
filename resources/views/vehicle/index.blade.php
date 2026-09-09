@extends('layouts.app')
@section('content')

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4">

        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'truck', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Transportación') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Padrón de vehículos y verificación de seguridad en prep.') }}</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('transport.inspect.form') }}" class="btn btn-crew-accent d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'shield-check', 'label' => null]) {{ __('Verificar vehículo') }}
                </a>
                <a href="{{ route('transport.vehicles') }}" class="btn btn-crew-soft d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'truck', 'label' => null]) {{ __('Flota') }}
                </a>
                <a href="{{ route('transport.records') }}" class="btn btn-crew-soft d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'label' => null]) {{ __('Actas') }}
                </a>
            </div>
        </div>

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        {{-- ── FLOTA con las DOS marcas ─────────────────────────────────────── --}}
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'truck', 'label' => null]) {{ __('Flota') }}
            </h5>
            <a href="{{ route('transport.vehicles') }}" class="small">{{ __('Administrar') }}</a>
        </div>

        @if ($vehicles->count())
            <div class="row g-3 mb-4">
                @foreach ($vehicles as $vehicle)
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="{{ route('transport.vehicle.show', $vehicle) }}" class="card border-0 shadow-sm rounded-3 h-100 text-decoration-none p-3">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <div>
                                    <div class="fw-bold">{{ trim(($vehicle->make ?: '') . ' ' . ($vehicle->model ?: '')) ?: ($vehicle->type->name_es ?? '—') }}</div>
                                    <div class="text-muted small">{{ $vehicle->plate ?: __('Sin placas') }} · {{ $vehicle->type->name_es ?? '—' }}</div>
                                </div>
                                @include('componentes._icon', ['name' => 'truck', 'label' => null])
                            </div>
                            <div class="text-muted small mb-2">
                                @include('componentes._icon', ['name' => 'user', 'label' => null])
                                {{ $vehicle->driverLabel() ?: __('Sin conductor asignado') }}
                            </div>
                            @include('vehicle._marks', ['vehicle' => $vehicle])
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4 text-center" style="border:1px dashed var(--border, #d1d5db);">
                <div class="crew-empty-icon mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle">
                    @include('componentes._icon', ['name' => 'truck', 'label' => null])
                </div>
                <h5 class="mb-1">{{ __('Sin vehículos en el padrón') }}</h5>
                <p class="text-muted mb-3">{{ __('Registra la flota para verificarla y llevar sus documentos.') }}</p>
                <div><a href="{{ route('transport.vehicles') }}" class="btn btn-crew-accent">{{ __('Registrar vehículo') }}</a></div>
            </div>
        @endif

        {{-- ── ÚLTIMAS ACTAS ───────────────────────────────────────────────── --}}
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'clipboard-check', 'label' => null]) {{ __('Últimas actas') }}
            </h5>
            <a href="{{ route('transport.records') }}" class="small">{{ __('Ver todas') }}</a>
        </div>

        @if ($recent->count())
            <div class="list-group">
                @foreach ($recent as $acta)
                    @php
                        $apto = $acta->isApto();
                        $bg = $apto ? ($acta->level === 'normal' ? '#fef9c3' : '#dcfce7') : '#fee2e2';
                        $fg = $apto ? ($acta->level === 'normal' ? '#854d0e' : '#166534') : '#991b1b';
                    @endphp
                    <a href="{{ route('transport.acta', $acta->uuid) }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between">
                        <span>
                            <span class="fw-semibold">{{ $acta->folio() }}</span>
                            <span class="text-muted small ms-2">{{ trim(($acta->make ?: '') . ' ' . ($acta->model ?: '')) ?: $acta->type_name }} · {{ $acta->plate ?: '—' }}</span>
                        </span>
                        <span class="insp-tag" style="background:{{ $bg }};color:{{ $fg }};">{{ $apto ? __('Apto') : __('No apto') }} · {{ optional($acta->created_at)->format('d/m/Y') }}</span>
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-muted">{{ __('Aún no se ha verificado ningún vehículo.') }}</p>
        @endif

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
