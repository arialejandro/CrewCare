@extends('layouts.app')
@section('content')
@include('componentes._row-link')
@php
    // Chip corto del veredicto (mismo lenguaje que las actas de inspección de herramienta).
    $verdictChip = [
        'paro'                    => ['t' => __('PARO'),         'bg' => '#fee2e2', 'fg' => '#991b1b'],
        'actividad_no_ejecutable' => ['t' => __('No ejecutable'),'bg' => '#fef9c3', 'fg' => '#854d0e'],
        'apta'                    => ['t' => __('Apta'),         'bg' => '#dcfce7', 'fg' => '#166534'],
    ];
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4">

        <a href="{{ route('ambulance.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-3" style="text-decoration:none;">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null]) {{ __('Recursos de emergencia') }}
        </a>

        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Actas de verificación') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Consulta el histórico de ambulancias verificadas.') }}</p>
                </div>
            </div>

            <form method="get" action="{{ route('ambulance.records') }}" class="crew-search flex-grow-1 flex-lg-grow-0" style="min-width:280px;">
                <div class="input-group">
                    <span class="input-group-text border-end-0">
                        @include('componentes._icon', ['name' => 'search', 'class' => 'cc-ico', 'label' => null])
                    </span>
                    <input class="form-control border-start-0 ps-0" type="search" name="q" value="{{ $q }}"
                           placeholder="{{ __('placas, económico, proveedor, tipo, folio…') }}">
                </div>
            </form>
        </div>

        <div class="card border-0 shadow-sm rounded-3">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0 crew-table cc-stack">
                    <thead>
                        <tr>
                            <th scope="col" class="ps-4">{{ __('Folio') }}</th>
                            <th scope="col">{{ __('Tipo') }}</th>
                            <th scope="col">{{ __('Proveedor') }}</th>
                            <th scope="col">{{ __('Placas') }}</th>
                            <th scope="col">{{ __('Veredicto') }}</th>
                            <th scope="col" class="text-end pe-4">{{ __('Fecha') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($inspections as $insp)
                            @php $vc = $verdictChip[$insp->verdict] ?? $verdictChip['apta']; @endphp
                            <tr data-href="{{ route('ambulance.acta', $insp->uuid) }}" style="cursor:pointer;">
                                <td class="ps-4" data-label="{{ __('Folio') }}"><strong>{{ $insp->folio() }}</strong></td>
                                <td data-label="{{ __('Tipo') }}">
                                    {{ $insp->type_name }} <span class="text-muted small">({{ $insp->type_code }})</span>
                                </td>
                                <td class="text-muted" data-label="{{ __('Proveedor') }}">{{ $insp->provider_name ?: '—' }}</td>
                                <td data-label="{{ __('Placas') }}">{{ $insp->plates ?: '—' }}</td>
                                <td data-label="{{ __('Veredicto') }}">
                                    <span class="insp-tag" style="background:{{ $vc['bg'] }};color:{{ $vc['fg'] }};">{{ $vc['t'] }}</span>
                                    @if ($insp->isRetired())
                                        <span class="insp-tag d-inline-block mt-1">{{ __('Retirada') }}</span>
                                    @elseif ($insp->isBlocked())
                                        <span class="insp-tag insp-tag--gate d-inline-block mt-1">{{ __('Paro activo') }}</span>
                                    @endif
                                </td>
                                <td class="text-muted text-end pe-4" data-label="{{ __('Fecha') }}">{{ optional($insp->created_at)->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="text-center py-5">
                                        <div class="crew-empty-icon mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle">
                                            @include('componentes._icon', ['name' => 'clipboard-list', 'label' => null])
                                        </div>
                                        <h5 class="mb-1">{{ __('Sin actas') }}</h5>
                                        <p class="text-muted mb-0">{{ $q !== '' ? __('No hay actas que coincidan con la búsqueda.') : __('Aún no se ha verificado ninguna ambulancia.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($inspections->hasPages())
                <div class="card-footer border-0 py-3">{!! $inspections->links() !!}</div>
            @endif
        </div>

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
