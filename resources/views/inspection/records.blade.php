@extends('layouts.app')
@section('content')
@php
    // Etiqueta corta del veredicto para el chip de cada acta.
    $verdictChip = [
        'paro'                    => ['t' => __('PARO'),        'bg' => '#fee2e2', 'fg' => '#991b1b'],
        'actividad_no_ejecutable' => ['t' => __('No ejecutable'),'bg' => '#fef9c3', 'fg' => '#854d0e'],
        'apta'                    => ['t' => __('Apta'),        'bg' => '#dcfce7', 'fg' => '#166534'],
    ];
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4">

        <a href="{{ route('tools.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-3" style="text-decoration:none;">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null]) {{ __('Inspección de herramienta') }}
        </a>

        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Actas de inspección') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Consulta el histórico. Busca por N.º de serie para ver la unidad concreta.') }}</p>
                </div>
            </div>

            <form method="get" action="{{ route('tools.records') }}" class="crew-search flex-grow-1 flex-lg-grow-0" style="min-width:280px;">
                <div class="input-group">
                    <span class="input-group-text border-end-0">
                        @include('componentes._icon', ['name' => 'search', 'class' => 'cc-ico', 'label' => null])
                    </span>
                    <input class="form-control border-start-0 ps-0" type="search" name="q" value="{{ $q }}"
                           placeholder="{{ __('serie, dueño, herramienta, folio…') }}">
                </div>
            </form>
        </div>

        {{-- Cuando se filtra por una unidad concreta (?serial=): cabecera + limpiar. --}}
        @if ($serial !== '')
            <div class="alert alert-info d-flex align-items-center justify-content-between gap-2 flex-wrap py-2">
                <span class="d-inline-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'wrench', 'label' => null])
                    {{ __('Historial de la unidad') }} · <strong>{{ $serial }}</strong>
                </span>
                <a href="{{ route('tools.records') }}" class="btn btn-sm btn-outline-secondary">{{ __('Quitar filtro') }}</a>
            </div>
        @endif

        <div class="card border-0 shadow-sm rounded-3">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0 crew-table cc-stack">
                    <thead>
                        <tr>
                            <th scope="col" class="ps-4">{{ __('Folio') }}</th>
                            <th scope="col">{{ __('Herramienta') }}</th>
                            <th scope="col">{{ __('N.º de serie') }}</th>
                            <th scope="col">{{ __('Dueño') }}</th>
                            <th scope="col">{{ __('Depto.') }}</th>
                            <th scope="col">{{ __('Veredicto') }}</th>
                            <th scope="col">{{ __('Estado') }}</th>
                            <th scope="col" class="text-end pe-4">{{ __('Fecha') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($inspections as $insp)
                            @php $vc = $verdictChip[$insp->verdict] ?? $verdictChip['apta']; @endphp
                            <tr onclick="window.location='{{ route('tools.inspection.show', $insp->uuid) }}'" style="cursor:pointer;">
                                <td class="ps-4" data-label="{{ __('Folio') }}"><strong>{{ $insp->folio() }}</strong></td>
                                <td data-label="{{ __('Herramienta') }}">
                                    {{ $insp->tool_name }} <span class="text-muted small">({{ $insp->tool_code }})</span>
                                    @if ($insp->tool_brand || $insp->tool_model)
                                        <span class="d-block text-muted small">{{ trim(($insp->tool_brand ? $insp->tool_brand.' ' : '').($insp->tool_model ?? '')) }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('N.º de serie') }}">
                                    @if ($insp->tool_serial)
                                        <a href="{{ route('tools.records', ['serial' => $insp->tool_serial]) }}"
                                           onclick="event.stopPropagation();" title="{{ __('Ver esta unidad') }}">{{ $insp->tool_serial }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-muted" data-label="{{ __('Dueño') }}">{{ $insp->ownerLabel() ?: '—' }}</td>
                                <td class="text-muted" data-label="{{ __('Depto.') }}">{{ $insp->department_name ?: '—' }}</td>
                                <td data-label="{{ __('Veredicto') }}">
                                    <span class="insp-tag" style="background:{{ $vc['bg'] }};color:{{ $vc['fg'] }};">{{ $vc['t'] }}</span>
                                </td>
                                <td data-label="{{ __('Estado') }}">
                                    @if ($insp->isRetired())
                                        <span class="insp-tag">{{ __('Retirada') }}</span>
                                    @elseif ($insp->isBlocked())
                                        <span class="insp-tag insp-tag--gate">{{ __('Paro activo') }}</span>
                                    @else
                                        <span class="insp-tag" style="background:#dcfce7;color:#166534;">{{ __('Vigente') }}</span>
                                    @endif
                                </td>
                                <td class="text-muted text-end pe-4" data-label="{{ __('Fecha') }}">{{ optional($insp->created_at)->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="text-center py-5">
                                        <div class="crew-empty-icon mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle">
                                            @include('componentes._icon', ['name' => 'clipboard-list', 'label' => null])
                                        </div>
                                        <h5 class="mb-1">{{ __('Sin actas') }}</h5>
                                        <p class="text-muted mb-0">{{ $q !== '' || $serial !== '' ? __('No hay actas que coincidan con la búsqueda.') : __('Aún no se ha registrado ninguna inspección.') }}</p>
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
