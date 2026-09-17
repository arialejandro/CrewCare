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
                    <h1 class="crew-title mb-0">{{ __('Flota') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Padrón de vehículos de la instancia.') }}</p>
                </div>
            </div>
            <a href="{{ route('transport.index') }}" class="btn btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'arrow-left', 'label' => null]) {{ __('Volver') }}
            </a>
        </div>

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <div class="row g-4">
            {{-- Lista --}}
            <div class="col-lg-7">
                @if ($vehicles->count())
                    <div class="list-group">
                        @foreach ($vehicles as $vehicle)
                            <a href="{{ route('transport.vehicle.show', $vehicle) }}" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold">{{ trim(($vehicle->make ?: '') . ' ' . ($vehicle->model ?: '')) ?: ($vehicle->type->name_es ?? '—') }}</div>
                                        <div class="text-muted small">{{ $vehicle->plate ?: __('Sin placas') }} · {{ $vehicle->type->name_es ?? '—' }} · {{ $vehicle->driverLabel() ?: __('sin conductor') }}</div>
                                    </div>
                                    @include('componentes._icon', ['name' => 'chevron-right', 'label' => null])
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted">{{ __('Aún no hay vehículos.') }}</p>
                @endif
            </div>

            {{-- Alta --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4">
                    <h5 class="mb-3 d-flex align-items-center gap-2">@include('componentes._icon', ['name' => 'plus', 'label' => null]) {{ __('Registrar vehículo') }}</h5>
                    <form method="post" action="{{ route('transport.vehicle.store') }}">
                        @csrf
                        @include('vehicle._form-fields', ['vehicle' => null, 'types' => $types, 'crew' => $crew, 'payees' => $payees])
                        <div class="mt-4">
                            <button type="submit" class="btn btn-crew-accent">{{ __('Registrar') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
