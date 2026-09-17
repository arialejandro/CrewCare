@extends('layouts.app')
@section('content')
{{-- VISTA LITE de producción (§9): tarjeta de circulación, placas, licencia, conductor. SIN nivel de
     riesgo, sin puntos, sin acta. Es lo único que ve producción con transport.view. --}}

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1000px">

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'truck', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div class="flex-grow-1">
                <h1 class="crew-title mb-0">{{ __('Transportación') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Flota: placas, documentos y conductor.') }}</p>
            </div>
            {{-- §4: producción entra a la orden del día (y sus versiones) desde aquí. Sin direcciones ni actas. --}}
            <a href="{{ route('transport.order.index') }}" class="btn btn-outline-primary">
                @include('componentes._icon', ['name' => 'clipboard-list', 'label' => null]) {{ __('Orden de transportación') }}
            </a>
        </div>

        @if ($vehicles->count())
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr class="text-muted small text-uppercase">
                            <th>{{ __('Vehículo') }}</th>
                            <th>{{ __('Placas') }}</th>
                            <th>{{ __('Tarjeta de circulación') }}</th>
                            <th>{{ __('Licencia del conductor') }}</th>
                            <th>{{ __('Conductor') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vehicles as $vehicle)
                            @php
                                $state    = $vehicle->requiredDocState();
                                $tarjeta  = $state['VEH_TARJETA'] ?? null;
                                $licencia = $vehicle->driverLicense(); // §2: del paquete del conductor
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ trim(($vehicle->make ?: '') . ' ' . ($vehicle->model ?: '')) ?: ($vehicle->type->name_es ?? '—') }}</div>
                                    <div class="text-muted small">{{ $vehicle->type->name_es ?? '—' }}</div>
                                </td>
                                <td class="font-monospace">{{ $vehicle->plate ?: '—' }}</td>
                                <td>
                                    @if ($tarjeta)
                                        <span class="text-success small">@include('componentes._icon', ['name' => 'file-check', 'label' => null]) {{ __('Vigente') }}@if($tarjeta->effectiveValidUntil()) · {{ $tarjeta->effectiveValidUntil()->format('d/m/Y') }}@endif</span>
                                    @else
                                        <span class="text-muted small">{{ __('Sin validar') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($licencia && $licencia->isValidated())
                                        <span class="text-success small">@include('componentes._icon', ['name' => 'file-check', 'label' => null]) {{ __('Vigente') }}@if($licencia->effectiveValidUntil()) · {{ $licencia->effectiveValidUntil()->format('d/m/Y') }}@endif</span>
                                    @elseif ($licencia)
                                        <span class="text-muted small">{{ __('Sin validar') }}</span>
                                    @else
                                        <span class="text-muted small">{{ __('Sin registrar') }}</span>
                                    @endif
                                </td>
                                <td>{{ $vehicle->driverLabel() ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted">{{ __('Sin vehículos registrados.') }}</p>
        @endif

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
