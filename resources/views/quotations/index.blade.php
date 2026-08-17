@extends('layouts.app')
@section('content')
{{-- COTIZACIÓN · listado (el paso previo al contrato). Scopeado por producción + visibilidad. --}}
@php
    $badges = [
        'recibida' => 'text-bg-secondary', 'en_negociacion' => 'text-bg-warning',
        'aceptada' => 'text-bg-success', 'rechazada' => 'text-bg-danger',
    ];
    $labels = [
        'recibida' => __('Recibida'), 'en_negociacion' => __('En negociación'),
        'aceptada' => __('Aceptada'), 'rechazada' => __('Rechazada'),
    ];
@endphp
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:920px">
        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Cotizaciones') }}</h1>
                <p class="text-muted mb-0 small">{{ __('El paso previo al contrato: muchas veces decide si se contrata o no.') }}</p>
            </div>
            <div class="ms-auto">
                <a href="{{ route('quotations.create') }}" class="btn btn-crew d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'plus', 'label' => null]) {{ __('Nueva cotización') }}
                </a>
            </div>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <div class="card"><div class="card-body p-0">
            @if($quotations->isEmpty())
                <p class="text-muted m-4">{{ __('Aún no hay cotizaciones. Registra la primera con “Nueva cotización”.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('Emisor') }}</th><th>{{ __('Número') }}</th><th>{{ __('Departamento') }}</th>
                            <th class="text-end">{{ __('Total') }}</th><th>{{ __('Estado') }}</th><th></th>
                        </tr></thead>
                        <tbody>
                            @foreach($quotations as $q)
                                @php $v = $q->currentVersion; @endphp
                                <tr>
                                    <td><a href="{{ route('quotations.show', $q) }}" class="fw-semibold text-decoration-none">{{ $q->emitter_name }}</a>
                                        @if($q->emitter_email)<div class="small text-muted">{{ $q->emitter_email }}</div>@endif</td>
                                    <td class="small">{{ optional($v)->quotation_number ?: '—' }}</td>
                                    <td class="small">{{ optional($q->department)->name ?: '—' }}</td>
                                    <td class="text-end">{{ $v ? '$'.number_format((float) $v->total, 2) : '—' }}</td>
                                    <td><span class="badge {{ $badges[$q->status] ?? 'text-bg-light' }}">{{ $labels[$q->status] ?? $q->status }}</span></td>
                                    <td class="text-end"><a href="{{ route('quotations.show', $q) }}" class="btn btn-sm btn-crew-soft">{{ __('Ver') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div></div>

        <div class="mt-3">{{ $quotations->links() }}</div>
    </div>
</div>
@endsection
