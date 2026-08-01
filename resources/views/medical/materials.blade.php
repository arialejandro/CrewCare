@extends('layouts.app')

@section('title', 'Conteo de Medicamentos - '.($branding['brand_name'] ?? 'CrewCare'))

@section('content')

{{-- Sistema de estilos de formularios/tarjetas reutilizable (mismo lenguaje que la consulta médica). --}}
@include('componentes._form-kit')

{{-- CSS específico de ESTA vista: sólo lo que el kit no cubre
     (banner de uso interno, tarjetas de estadística y tabla del reporte).
     El claro/oscuro sale automático de los tokens de marca. --}}
@push('styles')
<style>
    .med-report { color: var(--text); }

    /* Banner de uso interno: usa el token de advertencia (antes hardcodeado en ámbar). */
    .cc-banner {
        display: flex; align-items: center; gap: .6rem;
        padding: .8rem 1.05rem; margin-bottom: 1.25rem;
        border: 1px solid color-mix(in srgb, var(--warn) 35%, transparent);
        border-left: 4px solid var(--warn);
        border-radius: var(--radius-sm, 11px);
        background: color-mix(in srgb, var(--warn) 12%, transparent);
        color: var(--warn); font-weight: 600; font-size: .9rem; line-height: 1.4;
    }
    .cc-banner .cc-ico-18 { flex: none; }

    /* Tarjetas de estadística: se apilan en móvil (auto-fit + minmax). */
    .cc-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
    .cc-stat {
        padding: 1rem 1.15rem; border: 1px solid var(--stroke, var(--border));
        border-radius: var(--radius, 16px); background: var(--surface-2);
    }
    .cc-stat__lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); font-weight: 600; }
    .cc-stat__val { font-size: 1.9rem; font-weight: 800; color: var(--brand-primary); line-height: 1.1; margin-top: .25rem; }

    /* Tabla del reporte. */
    .cc-report-table { margin: 0; width: 100%; min-width: 520px; border-collapse: collapse; }
    .cc-report-table thead th {
        background: var(--surface-2); color: var(--text-muted); text-transform: uppercase;
        font-size: .72rem; letter-spacing: .05em; padding: .75rem .9rem;
        border-bottom: 2px solid var(--border); text-align: left;
    }
    .cc-report-table td { padding: .7rem .9rem; border-bottom: 1px solid var(--border); font-size: .92rem; color: var(--text); }
    .cc-report-table tbody tr:last-child td { border-bottom: 0; }
    .cc-report-table .qty { text-align: right; font-weight: 700; }
    .cc-report-empty { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); }
    .cc-report-empty .cc-empty-ico { color: var(--brand-primary); margin-bottom: .5rem; }

    /* Subnav Conteo / Materialidad. */
    .cc-subnav { display: flex; gap: .5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .cc-subnav a {
        padding: .5rem .95rem; border-radius: 10px; font-size: .9rem; font-weight: 600;
        text-decoration: none; border: 1px solid var(--stroke, var(--border)); color: var(--text-muted);
        transition: color .15s ease, border-color .15s ease, background-color .15s ease;
    }
    .cc-subnav a.is-active { background: var(--brand-primary); color: #fff; border-color: var(--brand-primary); }
    .cc-subnav a:hover:not(.is-active) { color: var(--brand-primary); border-color: var(--brand-primary); }

    @media print { .no-print { display: none !important; } }
</style>
@endpush

<div class="med-report container-fluid py-4" style="max-width: 1000px;">

    <div class="cc-banner">
        @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-ico-18', 'label' => null])
        <span>{{ __('CONTEO DE CONSUMO — USO INTERNO/FISCAL, NO COSTEO') }}</span>
    </div>

    {{-- Pestañas: Conteo (activa) · Materialidad (evidencia fiscal). --}}
    <nav class="cc-subnav no-print">
        <a href="{{ route('medical.materials') }}" class="is-active">{{ __('Conteo') }}</a>
        <a href="{{ route('medical.materiality') }}">{{ __('Materialidad') }}</a>
    </nav>

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'package', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <div class="cc-muted small text-uppercase" style="letter-spacing:.08em; font-weight:700;">{{ $branding['brand_name'] ?? 'CrewCare' }}</div>
                <h1 class="h4 fw-bold mb-0">{{ __('Conteo de Medicamentos') }}</h1>
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
                <p class="cc-form-card__sub">{{ __('Filtra el conteo por periodo y expórtalo a PDF') }}</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            <form method="GET" action="{{ route('medical.materials') }}" class="row g-3 align-items-end">
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
                {{-- El CONTEO no lleva diagnósticos (son cantidades por variante): lo exporta quien
                     pueda verlo. El candado de emisión es sólo para la bitácora clínica. --}}
                <div class="col-12 col-md-auto">
                    <a href="{{ route('medical.materials.pdf', array_merge(['from' => $from, 'to' => $to], count($selectedMedics) ? ['medics' => $selectedMedics] : [])) }}"
                       target="_blank" class="cc-btn-ghost w-100 justify-content-center">
                        @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico-16', 'label' => null])
                        {{ __('Exportar PDF') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Indicadores ===== --}}
    <div class="cc-stat-grid">
        <div class="cc-stat">
            <div class="cc-stat__lbl">{{ __('Atenciones') }}</div>
            <div class="cc-stat__val">{{ $totalConsultas }}</div>
        </div>
        <div class="cc-stat">
            <div class="cc-stat__lbl">{{ __('Medicamentos distintos') }}</div>
            <div class="cc-stat__val">{{ $distinctMeds }}</div>
        </div>
    </div>

    {{-- ===== Tabla / vacío ===== --}}
    @if(empty($totals))
        <div class="cc-form-card">
            <div class="cc-form-card__body">
                <div class="cc-report-empty">
                    <div class="cc-empty-ico">@include('componentes._icon', ['name' => 'package', 'class' => 'cc-ico-24', 'label' => null])</div>
                    <h5 class="fw-bold">{{ __('Sin medicamentos registrados') }}</h5>
                    <p class="mb-0">{{ __('No hay consumo de medicamentos en el rango seleccionado.') }}</p>
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
                                <th style="width:40%">{{ __('Medicamento') }}</th>
                                <th style="width:20%">{{ __('Dosis') }}</th>
                                <th style="width:22%">{{ __('Presentación') }}</th>
                                <th style="width:18%" class="text-end">{{ __('Cantidad Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($totals as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item['name'] }}</td>
                                    <td>{{ $item['dosage'] }}</td>
                                    <td>{{ $item['presentation'] }}</td>
                                    <td class="qty">{{ $item['quantity'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
