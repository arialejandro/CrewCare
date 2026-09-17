@extends('layouts.app')
@section('content')
@push('styles')@include('componentes._crew-list-styles')@endpush
@include('componentes._confirm-submit')
{{-- VENTANA DE RECEPCIÓN POR PERIODO DE PAGO — la app se abre cada semana por esto. Centraliza
     la recepción (facturas/32-D/CSF) que hoy llega por correo. La ventana ABRE y CIERRA pero NO
     RECHAZA. El estado del tablero se DERIVA con PayeePackage (nunca "vigente"/"cumple"). --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'calendar', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Periodos de pago') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Recepción centralizada por periodo. Abre y cierra la ventana; el documento fuera de tiempo se recibe igual, marcado.') }}</p>
            </div>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @unless($productionId)
            <div class="alert alert-warning">{{ __('No hay una producción activa; no se pueden abrir periodos todavía.') }}</div>
        @endunless

        @can('periods.manage')
        {{-- GENERACIÓN EN LOTE — crea N semanas de una, con la nomenclatura compuesta desde la
             PLANTILLA (final de la semana). Ej: SEM{DD}{MM}{YY} → SEM060926. --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span>{{ __('Generar semanas en lote') }}</span>
                <a href="{{ route('payment-concepts.index') }}" class="btn btn-sm btn-crew-soft">{{ __('Catálogo de conceptos') }}</a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('periods.batch') }}" class="row g-3">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label small">{{ __('Frecuencia') }}</label>
                        <select name="frequency" class="form-select" required>
                            <option value="weekly">{{ __('Semanal') }}</option>
                            <option value="biweekly">{{ __('Quincenal') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">{{ __('Inicio (1ª semana)') }}</label>
                        <input type="date" name="start_date" id="batch-start" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">{{ __('¿Cuántas?') }}</label>
                        <input type="number" name="count" class="form-control" min="1" max="52" value="12" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">{{ __('Plantilla de nomenclatura') }}</label>
                        <input type="text" name="template" class="form-control" value="{{ $labelTemplate }}"
                               placeholder="SEM{DD}{MM}{YY}">
                        <div class="form-text">{{ __('Tokens: {DD} {MM} {YY} {YYYY} {MES} (fin de semana) · {oDD} {oD} {oMES} (inicio).') }}</div>
                    </div>
                    <div class="col-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-crew">{{ __('Generar en lote') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ __('Abrir un periodo') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('periods.store') }}" class="row g-3 js-period-form">
                    @csrf
                    @include('periods._fields', ['period' => null])
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-crew w-100">{{ __('Abrir periodo') }}</button>
                    </div>
                </form>
            </div>
        </div>
        @endcan

        @forelse($frequencies as $freqVal => $freqLabel)
            @php $group = $periods[$freqVal] ?? collect(); @endphp
            <div class="card mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>{{ $freqLabel }}</span>
                    <span class="badge text-bg-secondary">{{ $group->count() }}</span>
                </div>
                <div class="card-body">
                    @if($group->isEmpty())
                        <p class="text-muted mb-0">{{ __('Sin periodos.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table cc-stack align-middle mb-0">
                                <thead><tr>
                                    <th>{{ __('Periodo') }}</th>
                                    <th>{{ __('Ventana') }}</th>
                                    <th>{{ __('Estado') }}</th>
                                    <th class="text-end">{{ __('Acciones') }}</th>
                                </tr></thead>
                                <tbody>
                                    @foreach($group as $period)
                                        <tr>
                                            <td data-label="{{ __('Periodo') }}">
                                                <a href="{{ route('periods.show', $period) }}" class="fw-semibold text-decoration-none">{{ $period->displayLabel() }}</a>
                                                @if($period->isDayPlayer() && $period->payee)
                                                    <div class="small text-muted">{{ $period->payee->name }}</div>
                                                @endif
                                            </td>
                                            <td data-label="{{ __('Ventana') }}">
                                                {{ optional($period->opens_on)->format('d/m/Y') }} → {{ optional($period->closes_on)->format('d/m/Y') }}
                                            </td>
                                            <td data-label="{{ __('Estado') }}">
                                                @if($period->isOpen())
                                                    <span class="badge text-bg-success">{{ __('Abierto') }}</span>
                                                @else
                                                    <span class="badge text-bg-secondary">{{ __('Cerrado') }}</span>
                                                @endif
                                            </td>
                                            <td data-label="{{ __('Acciones') }}" class="text-end">
                                                <a href="{{ route('periods.show', $period) }}" class="btn btn-sm btn-crew-soft">{{ __('Ver tablero') }}</a>
                                                @can('periods.manage')
                                                    @if($period->isOpen())
                                                        <form method="POST" action="{{ route('periods.close', $period) }}" class="d-inline">
                                                            @csrf<button class="btn btn-sm btn-outline-secondary">{{ __('Cerrar') }}</button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('periods.reopen', $period) }}" class="d-inline">
                                                            @csrf<button class="btn btn-sm btn-outline-secondary">{{ __('Reabrir') }}</button>
                                                        </form>
                                                    @endif
                                                    <a href="{{ route('periods.edit', $period) }}" class="btn btn-sm btn-outline-secondary">{{ __('Editar') }}</a>
                                                    <form method="POST" action="{{ route('periods.destroy', $period) }}" class="d-inline"
                                                          data-confirm="{{ __('¿Borrar este periodo? Solo se puede si no tiene documentos recibidos.') }}">
                                                        @csrf @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger">{{ __('Borrar') }}</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @empty
        @endforelse

    </div>
</div>
@endsection
