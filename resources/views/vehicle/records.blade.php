@extends('layouts.app')
@section('content')
@php use App\Support\VehicleVerdict; @endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1000px">

        <div class="crew-header d-flex align-items-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Actas de verificación') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Busca por placas, VIN, marca, modelo, tipo o folio.') }}</p>
                </div>
            </div>
            <a href="{{ route('transport.index') }}" class="btn btn-crew-soft">{{ __('Volver') }}</a>
        </div>

        <form method="get" action="{{ route('transport.records') }}" class="mb-3">
            <div class="input-group">
                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="{{ __('Buscar…') }}">
                <button type="submit" class="btn btn-crew-soft">{{ __('Buscar') }}</button>
            </div>
        </form>

        @if ($inspections->count())
            <div class="list-group mb-3">
                @foreach ($inspections as $acta)
                    @php $apto = $acta->isApto(); @endphp
                    <a href="{{ route('transport.acta', $acta->uuid) }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-2">
                        <span>
                            <span class="fw-semibold">{{ $acta->folio() }}</span>
                            <span class="text-muted small ms-2">{{ trim(($acta->make ?: '') . ' ' . ($acta->model ?: '')) ?: $acta->type_name }} · {{ $acta->plate ?: '—' }} · {{ optional($acta->created_at)->format('d/m/Y') }}</span>
                            @if (! $acta->is_active)<span class="badge bg-secondary ms-1">{{ __('retirada') }}</span>@endif
                        </span>
                        <span class="insp-tag" style="background:{{ $apto ? '#dcfce7' : '#fee2e2' }};color:{{ $apto ? '#166534' : '#991b1b' }};">{{ $apto ? __('Apto') : __('No apto') }} · {{ VehicleVerdict::levelLabel($acta->level) }}</span>
                    </a>
                @endforeach
            </div>
            {{ $inspections->links() }}
        @else
            <p class="text-muted">{{ __('Sin actas.') }}</p>
        @endif

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
