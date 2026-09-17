@extends('layouts.app')
@section('content')
{{-- Bloque 2 §1 — Listado de ÓRDENES por día. La orden se CONGELA, no se sella. --}}

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1000px">

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Orden de transportación') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Se emite por día. Se congela; no se sella ni firma.') }}</p>
            </div>
        </div>

        @if (session('ok'))
            <div class="alert alert-success py-2">{{ session('ok') }}</div>
        @endif

        @if ($canFull)
            <form method="POST" action="{{ route('transport.order.create') }}" class="d-flex align-items-end gap-2 mb-4">
                @csrf
                <div>
                    <label class="form-label small text-muted mb-1">{{ __('Día de la orden') }}</label>
                    <input type="date" name="order_date" value="{{ old('order_date', $today) }}" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    @include('componentes._icon', ['name' => 'file-plus', 'label' => null]) {{ __('Nueva orden / retomar borrador') }}
                </button>
            </form>
            @error('order_date')<div class="text-danger small mb-3">{{ $message }}</div>@enderror
        @endif

        @forelse ($ordersByDate as $date => $orders)
            <div class="mb-3">
                <div class="text-uppercase text-muted small fw-semibold mb-2">
                    {{ \Carbon\Carbon::parse($date)->translatedFormat('l d \d\e F Y') }}
                </div>
                <div class="list-group">
                    @foreach ($orders as $order)
                        <a href="{{ route('transport.order.show', $order) }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between">
                            <span>
                                <span class="fw-semibold">v{{ $order->version }}</span>
                                <span class="text-muted small ms-2">{{ $order->runs()->count() }} {{ __('corridas') }}</span>
                            </span>
                            @if ($order->isFrozen())
                                <span class="badge bg-secondary">@include('componentes._icon', ['name' => 'clipboard-check', 'label' => null]) {{ __('Congelada') }}</span>
                            @else
                                <span class="badge bg-warning text-dark">{{ __('Borrador') }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-muted">{{ __('Aún no hay órdenes.') }}</p>
        @endforelse

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
