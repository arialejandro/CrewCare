@extends('layouts.app')
@section('content')
{{-- AGENDA POR VEHÍCULO (Fase 4). La orden vista como agenda: cada unidad con su día en orden de
     hora (corridas de set, fuera y eventos). Marca qué PODRÍA ADELANTARSE en la misma unidad —
     INFORMA, no mueve. Las corridas por aplicación van aparte. Sólo lectura. --}}
@php
    $chip = function ($kind) {
        return match ($kind) {
            'set'    => 'bg-primary-subtle',
            'evento' => 'bg-info-subtle',
            default  => 'bg-secondary-subtle',
        };
    };
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <a href="{{ route('transport.order.show', $order) }}" class="btn btn-sm btn-outline-secondary">
                @include('componentes._icon', ['name' => 'chevron-right', 'label' => null]) {{ __('Editor') }}
            </a>
            <span class="badge {{ $order->isFrozen() ? 'bg-secondary' : 'bg-warning text-dark' }}">
                {{ $order->isFrozen() ? __('Congelada') : __('Borrador') }} · v{{ $order->version }}
            </span>
        </div>

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'truck', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Agenda por vehículo') }}</h1>
                <p class="text-muted mb-0 small">{{ \Carbon\Carbon::parse($order->order_date)->translatedFormat('l d \d\e F Y') }}</p>
            </div>
        </div>

        <p class="text-muted small">{{ __('Qué podría adelantarse sólo INFORMA: nada se mueve solo, transpo decide.') }}</p>

        @forelse ($agenda['vehicles'] as $v)
            <div class="border rounded-3 mb-3">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-body-tertiary">
                    <div class="fw-semibold">
                        @include('componentes._icon', ['name' => 'truck', 'label' => null])
                        {{ $v['vehicle_label'] }}
                        @if ($v['plate'])<span class="font-monospace text-muted small ms-1">{{ $v['plate'] }}</span>@endif
                    </div>
                    <div class="small text-muted">{{ $v['driver_label'] ?: __('sin conductor') }}</div>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($v['items'] as $it)
                        <li class="list-group-item">
                            <div class="d-flex align-items-start gap-2">
                                <span class="fw-bold font-monospace" style="min-width:5.5rem">
                                    {{ $it['start']['str'] ?: '—' }}@if ($it['end']['str'])<span class="text-muted fw-normal">–{{ $it['end']['str'] }}</span>@endif
                                </span>
                                <div class="flex-grow-1">
                                    <span class="badge {{ $chip($it['kind']) }} text-dark border me-1">{{ $it['type_label'] }}</span>
                                    <span>{{ $it['title'] }}</span>
                                    @if (! empty($it['opportunity']))
                                        @php $op = $it['opportunity']; @endphp
                                        <div class="small mt-1 text-success-emphasis">
                                            @include('componentes._icon', ['name' => 'chevron-up', 'label' => null])
                                            @if ($op['method'] === 'travel')
                                                {{ __('Podría adelantarse :n min.', ['n' => $op['minutes']]) }}
                                                <span class="text-muted">{{ __('(con traslado)') }}</span>
                                            @else
                                                {{ __('Podría adelantarse hasta :n min.', ['n' => $op['minutes']]) }}
                                                <span class="text-muted">{{ __('(sin considerar el traslado)') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-muted">{{ __('Sin corridas con vehículo todavía.') }}</p>
        @endforelse

        {{-- Corridas por aplicación: sin unidad, no cuelgan de nadie. --}}
        @if (! empty($agenda['loose']))
            <h2 class="h6 text-uppercase text-muted mb-2 mt-4">{{ __('Transporte por aplicación (sin unidad)') }}</h2>
            <div class="border rounded-3">
                <ul class="list-group list-group-flush">
                    @foreach ($agenda['loose'] as $it)
                        <li class="list-group-item d-flex align-items-start gap-2">
                            <span class="fw-bold font-monospace" style="min-width:5.5rem">{{ $it['start']['str'] ?: '—' }}</span>
                            <div><span class="badge bg-secondary-subtle text-dark border me-1">{{ $it['type_label'] }}</span> {{ $it['title'] }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
