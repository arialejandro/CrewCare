@extends('layouts.app')
@section('content')

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:960px">

        <div class="crew-header d-flex align-items-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'truck', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Editar vehículo') }}</h1>
                    <p class="text-muted mb-0 small">{{ trim(($vehicle->make ?: '') . ' ' . ($vehicle->model ?: '')) ?: ($vehicle->type->name_es ?? '—') }}</p>
                </div>
            </div>
            <a href="{{ route('transport.vehicle.show', $vehicle) }}" class="btn btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'arrow-left', 'label' => null]) {{ __('Volver') }}
            </a>
        </div>

        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4">
            <form method="post" action="{{ route('transport.vehicle.update', $vehicle) }}">
                @csrf
                @include('vehicle._form-fields', ['vehicle' => $vehicle, 'types' => $types, 'crew' => $crew, 'payees' => $payees, 'attrs' => $attrs])
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-crew-accent">{{ __('Guardar cambios') }}</button>
                    <a href="{{ route('transport.vehicle.show', $vehicle) }}" class="btn btn-crew-soft">{{ __('Cancelar') }}</a>
                </div>
            </form>
        </div>

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
