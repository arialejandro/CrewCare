@extends('layouts.app')
@section('content')
@php
    $snap = is_array($study->counts_snapshot) ? $study->counts_snapshot : [];
    $rows = $snap['rows'] ?? [];
    $pct = null;
    if ($study->attack_rate_population && (int) $study->attack_rate_population > 0 && $study->attack_rate_cases !== null) {
        $pct = round(($study->attack_rate_cases / $study->attack_rate_population) * 100, 1);
    }
    $groupLabel = $study->group_key ? \App\Models\IndicatorTerm::groupLabel($study->group_key) : __('Todos / mixto');
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 900px;">

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="crew-title mb-0">{{ __('Estudio de brote') }}</h1>
                <p class="text-muted mb-0 small">{{ $study->folio() }}</p>
            </div>
            <a href="{{ route('epi.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Panel') }}</a>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
            <h5 class="mb-1">{{ $study->title }}</h5>
            <div class="row g-3 small mt-1">
                <div class="col-md-4"><span class="text-muted d-block">{{ __('Grupo') }}</span>{{ $groupLabel }}</div>
                <div class="col-md-4"><span class="text-muted d-block">{{ __('Periodo') }}</span>
                    {{ $study->period_from ? optional($study->period_from)->format('d/m/Y') : '—' }}
                    – {{ $study->period_to ? optional($study->period_to)->format('d/m/Y') : '—' }}</div>
                <div class="col-md-4"><span class="text-muted d-block">{{ __('Médico') }}</span>
                    {{ $study->medic_name ?: '—' }}@if($study->medic_cedula)<span class="text-muted"> · {{ __('Cédula') }} {{ $study->medic_cedula }}</span>@endif</div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
            <h6 class="mb-2">{{ __('Definición operacional de casos') }}</h6>
            <div style="white-space:pre-line;">{{ $study->case_definition }}</div>
            <div class="row g-3 small mt-2">
                <div class="col-md-4"><span class="text-muted d-block">{{ __('Tiempo') }}</span><div style="white-space:pre-line;">{{ $study->time_description ?: '—' }}</div></div>
                <div class="col-md-4"><span class="text-muted d-block">{{ __('Lugar') }}</span><div style="white-space:pre-line;">{{ $study->place_description ?: '—' }}</div></div>
                <div class="col-md-4"><span class="text-muted d-block">{{ __('Persona') }}</span><div style="white-space:pre-line;">{{ $study->person_description ?: '—' }}</div></div>
            </div>
            <hr>
            <div class="row g-3 small">
                <div class="col-md-4"><span class="text-muted d-block">{{ __('Tasa de ataque') }}</span>
                    @if ($study->attack_rate_cases !== null || $study->attack_rate_population !== null)
                        {{ $study->attack_rate_cases ?? '—' }} / {{ $study->attack_rate_population ?? '—' }}@if(!is_null($pct)) ({{ $pct }}%)@endif
                    @else — @endif
                    @if ($study->attack_rate_note)<div class="text-muted">{{ $study->attack_rate_note }}</div>@endif
                </div>
                <div class="col-md-4"><span class="text-muted d-block">{{ __('Hipótesis') }}</span><div style="white-space:pre-line;">{{ $study->hypothesis ?: '—' }}</div></div>
                <div class="col-md-4"><span class="text-muted d-block">{{ __('Medidas de control') }}</span><div style="white-space:pre-line;">{{ $study->control_measures ?: '—' }}</div></div>
            </div>
        </div>

        {{-- Conteos que aportó el sistema, congelados --}}
        @if (! empty($rows))
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">{{ __('Conteos aportados por el sistema') }} <span class="insp-tag">{{ $snap['group_label'] ?? '' }}</span></h6>
                    <span class="text-muted small">{{ __('congelados al emitir') }}</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="table table-sm small mb-0">
                        <thead><tr><th>{{ __('Día') }}</th><th>{{ __('Locación') }}</th><th>{{ __('Casos') }}</th><th>{{ __('Personas') }}</th></tr></thead>
                        <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td>{{ \App\Support\ProductionCalendar::label($r['shoot_day'] ?? null) }}</td>
                                <td>{{ $r['location'] ?? '—' }}</td>
                                <td>{{ $r['count'] ?? 0 }}</td>
                                <td>{{ ($r['denominator'] ?? null) === null ? '—' : $r['denominator'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Sello + QR + cadena CFDI (verificable públicamente) --}}
        @include('componentes._seal-cfdi', ['doc' => $study, 'folio' => $study->folio(), 'prefix' => 'CREWCARE-BRO'])

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
