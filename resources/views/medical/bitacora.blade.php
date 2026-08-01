@extends('layouts.app')

@section('title', 'Bitácora Médica - '.($branding['brand_name'] ?? 'CrewCare'))

@section('content')

{{-- Sistema de estilos de formularios/tarjetas reutilizable (mismo lenguaje que la consulta médica). --}}
@include('componentes._form-kit')

{{-- CSS específico de ESTA vista: sólo lo que el kit no cubre
     (píldora de total, tabla del reporte y su cintillo por día).
     El claro/oscuro sale automático de los tokens de marca. --}}
@push('styles')
<style>
    .med-report { color: var(--text); }

    /* Píldora con el total de atenciones. */
    .cc-statline {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .5rem 1rem; margin-bottom: 1.25rem;
        border-radius: 999px; border: 1px solid rgba(var(--brand-primary-rgb), .22);
        background: rgba(var(--brand-primary-rgb), .1);
        color: var(--text); font-weight: 600;
    }
    .cc-statline b { color: var(--brand-primary); font-size: 1.05rem; }

    /* Tabla del reporte agrupada por día. */
    .cc-report-table { margin: 0; width: 100%; min-width: 640px; border-collapse: collapse; }
    .cc-report-table thead th {
        background: var(--surface-2); color: var(--text-muted); text-transform: uppercase;
        font-size: .72rem; letter-spacing: .05em; padding: .75rem .9rem;
        border-bottom: 2px solid var(--border); text-align: left;
    }
    .cc-report-table td { padding: .7rem .9rem; border-bottom: 1px solid var(--border); vertical-align: top; font-size: .92rem; color: var(--text); }
    .cc-report-table tbody tr:last-child td { border-bottom: 0; }
    .cc-report-table .med-name { font-weight: 600; }
    .cc-report-table .day-band td {
        background: var(--brand-primary) !important; color: var(--brand-on-primary);
        font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
        font-size: .78rem; padding: .6rem .9rem;
    }
    .cc-report-empty { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); }
    .cc-report-empty .cc-empty-ico { color: var(--brand-primary); margin-bottom: .5rem; }

    @media print { .no-print { display: none !important; } }
</style>
@endpush

<div class="med-report container-fluid py-4" style="max-width: 1200px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <div class="cc-muted small text-uppercase" style="letter-spacing:.08em; font-weight:700;">{{ $branding['brand_name'] ?? 'CrewCare' }}</div>
                <h1 class="h4 fw-bold mb-0">{{ __('Bitácora Médica') }}</h1>
                <div class="cc-muted small">{{ $rangeLabel }}</div>
            </div>
        </div>
        @if(!empty($branding['client_logo']))
            <img src="{{ $branding['client_logo'] }}" alt="{{ $branding['brand_name'] ?? 'CrewCare' }}" style="max-height:40px; max-width:150px; width:auto;">
        @endif
    </div>

    {{-- ===== Filtro por rango de fechas ===== --}}
    <div class="cc-form-card no-print">
        <div class="cc-form-card__head">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'filter', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div class="cc-form-card__titles">
                <h2 class="cc-form-card__title">{{ __('Rango de fechas') }}</h2>
                <p class="cc-form-card__sub">{{ __('Filtra la bitácora por periodo y expórtala a PDF') }}</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            <form method="GET" action="{{ route('medical.bitacora') }}" class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-md-auto">
                    <div class="cc-field mb-0">
                        <label for="from" class="cc-label">{{ __('Desde') }}</label>
                        <input id="from" type="date" name="from" value="{{ $from }}" class="form-control cc-control">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-auto">
                    <div class="cc-field mb-0">
                        <label for="to" class="cc-label">{{ __('Hasta') }}</label>
                        <input id="to" type="date" name="to" value="{{ $to }}" class="form-control cc-control">
                    </div>
                </div>
                @include('medical._medic-filter', [
                    'medicOptions'   => $medicOptions,
                    'selectedMedics' => $selectedMedics,
                ])
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary cc-cta w-100">
                        @include('componentes._icon', ['name' => 'filter', 'class' => 'cc-ico-18', 'label' => null])
                        {{ __('Filtrar') }}
                    </button>
                </div>
                {{-- $canExportLog = misma regla que el guard del controlador (fuente única): la
                     bitácora lleva DIAGNÓSTICOS, así que sólo la emiten el médico y el key medic.
                     Quien no pueda, no ve el botón (y el backend igual lo rechaza). --}}
                @if($canExportLog ?? true)
                <div class="col-12 col-md-auto">
                    <a href="{{ route('medical.bitacora.pdf', array_merge(['from' => $from, 'to' => $to], count($selectedMedics) ? ['medics' => $selectedMedics] : [])) }}"
                       target="_blank" class="cc-btn-ghost w-100 justify-content-center">
                        @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico-16', 'label' => null])
                        {{ __('Exportar PDF') }}
                    </a>
                </div>
                @endif
            </form>
        </div>
    </div>

    <div class="cc-statline">{{ __('Total de atenciones') }}: <b>{{ $totalConsultas }}</b></div>

    {{-- ===== Tabla / vacío ===== --}}
    @if($totalConsultas === 0)
        <div class="cc-form-card">
            <div class="cc-form-card__body">
                <div class="cc-report-empty">
                    <div class="cc-empty-ico">@include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-24', 'label' => null])</div>
                    <h5 class="fw-bold">{{ __('Sin atenciones registradas') }}</h5>
                    <p class="mb-0">{{ __('No hay consultas médicas en el rango seleccionado.') }}</p>
                </div>
            </div>
        </div>
    @else
        <div class="cc-form-card">
            <div class="cc-form-card__body">
                <div class="table-responsive">
                    <table class="cc-report-table">
                        <thead>
                            <tr>
                                <th style="width:18%">{{ __('Nombre') }}</th>
                                <th style="width:16%">{{ __('Departamento') }}</th>
                                <th style="width:22%">{{ __('Diagnóstico') }}</th>
                                <th style="width:22%">{{ __('Medicamento') }}</th>
                                <th style="width:22%">{{ __('Observaciones') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($byDay as $date => $day)
                                <tr class="day-band">
                                    <td colspan="5">{{ $day['label'] }}</td>
                                </tr>
                                @foreach($day['rows'] as $row)
                                    <tr>
                                        <td class="med-name">{{ $row['name'] }}</td>
                                        <td>{{ $row['department'] }}</td>
                                        <td>{{ $row['diagnosis'] }}</td>
                                        {{-- Cuando no hubo medicamento, medications YA trae el manejo
                                             (medsText); esta segunda línea es para cuando hubo ambos. --}}
                                        <td>
                                            {!! nl2br(e($row['medications'])) !!}
                                            @if(($row['management'] ?? '') !== '' && $row['medications'] !== $row['management'])
                                                <span class="d-block small fst-italic text-muted">{{ $row['management'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $row['observations'] }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
