@extends('layouts.app')
@section('content')
@push('styles')@include('componentes._crew-list-styles')@endpush
@include('componentes._confirm-submit')
{{-- CATÁLOGO DE CONCEPTOS DE PAGO — vocabulario editable de los prefijos (SEM/CA/Box Rental…). Los
     GLOBALES (sembrados) se muestran de referencia y NO se editan aquí; la producción agrega los suyos. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:900px">

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'tag', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Conceptos de pago') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Tipos de concepto de las facturas (una factura puede llevar varios). Editable por producción.') }}</p>
            </div>
            <a href="{{ route('periods.index') }}" class="btn btn-sm btn-crew-soft ms-auto">{{ __('← Periodos') }}</a>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @unless($productionId)
            <div class="alert alert-warning">{{ __('No hay una producción activa; solo se ven los conceptos globales.') }}</div>
        @endunless

        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ __('Agregar concepto') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('payment-concepts.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label small">{{ __('Código') }}</label>
                        <input type="text" name="code" class="form-control" maxlength="40" placeholder="CA" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">{{ __('Nombre') }}</label>
                        <input type="text" name="name" class="form-control" maxlength="120" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small">{{ __('Descripción') }}</label>
                        <input type="text" name="description" class="form-control" maxlength="400">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-crew w-100" @unless($productionId) disabled @endunless>{{ __('Agregar') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">{{ __('Catálogo') }}</div>
            <div class="card-body">
                @if($concepts->isEmpty())
                    <p class="text-muted mb-0">{{ __('Sin conceptos.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table cc-stack align-middle mb-0">
                            <thead><tr>
                                <th>{{ __('Código') }}</th>
                                <th>{{ __('Nombre') }}</th>
                                <th>{{ __('Descripción') }}</th>
                                <th>{{ __('Ámbito') }}</th>
                                <th class="text-end">{{ __('Acciones') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach($concepts as $concept)
                                    @php $isGlobal = $concept->production_id === null; @endphp
                                    <tr>
                                        <td data-label="{{ __('Código') }}"><span class="fw-semibold">{{ $concept->code }}</span></td>
                                        <td data-label="{{ __('Nombre') }}">{{ $concept->name }}</td>
                                        <td data-label="{{ __('Descripción') }}" class="small text-muted">{{ $concept->description }}</td>
                                        <td data-label="{{ __('Ámbito') }}">
                                            @if($isGlobal)
                                                <span class="badge text-bg-secondary">{{ __('Global') }}</span>
                                            @else
                                                <span class="badge text-bg-success">{{ __('Esta producción') }}</span>
                                            @endif
                                        </td>
                                        <td data-label="{{ __('Acciones') }}" class="text-end">
                                            @if($isGlobal)
                                                <span class="text-muted small">{{ __('Referencia') }}</span>
                                            @else
                                                <form method="POST" action="{{ route('payment-concepts.destroy', $concept) }}" class="d-inline"
                                                      data-confirm="{{ __('¿Borrar este concepto?') }}">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger">{{ __('Borrar') }}</button>
                                                </form>
                                            @endif
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
