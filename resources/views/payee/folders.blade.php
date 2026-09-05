@extends('layouts.app')
@section('content')
@push('styles')@include('componentes._crew-list-styles')@endpush
{{-- CARPETAS DE DOCUMENTOS — cards por departamento con su descarga (jerarquía semana→depto→persona,
     solo fiscales del periodo) + proveedores individuales con buscador. Solo lectura. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'folder', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Carpetas de documentos') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Descarga por departamento y por semana. Documenta el PAGO: la factura atrasada cae en la semana en que llegó.') }}</p>
            </div>
            <a href="{{ route('payees.index') }}" class="btn btn-sm btn-crew-soft ms-auto">{{ __('Listado') }}</a>
        </div>

        {{-- Selector de SEMANA — rige la descarga (jerarquía y fiscales del periodo). --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('payees.folders') }}" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label small mb-1">{{ __('Semana') }}</label>
                        <select name="week" class="form-select" onchange="this.form.submit()">
                            <option value="0" @selected(!$selectedWeek)>{{ __('Sin semana — todos los documentos') }}</option>
                            @foreach($weeks as $w)
                                <option value="{{ $w->id }}" @selected($selectedWeek == $w->id)>
                                    {{ $w->displayLabel() }} · {{ optional($w->opens_on)->format('d/m') }}–{{ optional($w->closes_on)->format('d/m/Y') }}{{ $w->isOpen() ? '' : ' ('.__('cerrado').')' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            @if($selectedWeek)
                                {{ __('La descarga trae solo los documentos fiscales de esta semana. Los personales no se duplican.') }}
                            @else
                                {{ __('Sin semana: la descarga trae todos los documentos activos (para consulta).') }}
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <a href="{{ route('payees.documents.bulk', array_filter(['week' => $selectedWeek ?: null])) }}"
                           class="btn btn-crew w-100">{{ __('Descargar todos') }}</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- CARDS POR DEPARTAMENTO. --}}
        <h2 class="h6 text-muted mb-3">{{ __('Departamentos') }}</h2>
        @if($departments->isEmpty())
            <p class="text-muted">{{ __('No hay departamentos en tu alcance.') }}</p>
        @else
            <div class="row g-3 mb-4">
                @foreach($departments as $d)
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="fw-semibold">{{ $d->name }}</span>
                                    <span class="badge text-bg-secondary">{{ $counts[$d->id] ?? 0 }}</span>
                                </div>
                                <a href="{{ route('payees.documents.bulk', array_filter(['dept' => $d->id, 'week' => $selectedWeek ?: null])) }}"
                                   class="btn btn-sm btn-crew mt-3 w-100">{{ __('Descargar carpeta') }}</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- PROVEEDORES — carpeta individual, con buscador para encontrarlos uno a uno. --}}
        <h2 class="h6 text-muted mb-3">{{ __('Proveedores') }}</h2>
        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{ route('payees.folders') }}" class="row g-2 mb-3">
                    <input type="hidden" name="week" value="{{ $selectedWeek }}">
                    <div class="col-md-9">
                        <input type="text" name="q" value="{{ $q }}" class="form-control"
                               placeholder="{{ __('Buscar proveedor por nombre o RFC…') }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-crew-soft w-100">{{ __('Buscar') }}</button>
                    </div>
                </form>

                @if($providers->isEmpty())
                    <p class="text-muted mb-0">{{ $q !== '' ? __('Sin proveedores para esa búsqueda.') : __('Escribe para buscar un proveedor.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table cc-stack align-middle mb-0">
                            <thead><tr>
                                <th>{{ __('Proveedor') }}</th>
                                <th>{{ __('RFC') }}</th>
                                <th class="text-end">{{ __('Carpeta') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach($providers as $p)
                                    <tr>
                                        <td data-label="{{ __('Proveedor') }}" class="fw-semibold">{{ $p->name }}</td>
                                        <td data-label="{{ __('RFC') }}" class="small text-muted">{{ $p->rfc }}</td>
                                        <td data-label="{{ __('Carpeta') }}" class="text-end">
                                            <a href="{{ route('payees.documents.zip', $p) }}" class="btn btn-sm btn-crew-soft">{{ __('Descargar') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
