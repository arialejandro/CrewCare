@extends('layouts.app')
@section('content')
@php
    // Orden fijo de grupos + SIN CLASIFICAR. Colores concretos (Chart.js no lee tokens CSS).
    $groupOrder = array_keys(\App\Models\IndicatorTerm::GROUPS);
    $groupOrder[] = '_unclassified';
    $colors = [
        'gastrointestinal'   => '#d97706',
        'respiratorio'       => '#2563eb',
        'dermico'            => '#dc2626',
        'alergico'           => '#7c3aed',
        'oftalmico'          => '#0891b2',
        'musculoesqueletico' => '#16a34a',
        '_unclassified'      => '#6b7280',
    ];
    $labels = array_map(function ($d) { return $d['label']; }, $epi['days']);
    // Datasets de líneas por grupo (conteos reales; el 0 es 0 de verdad, hay día de rodaje).
    $lineData = [];
    foreach ($groupOrder as $gk) {
        $lineData[] = [
            'key'   => $gk,
            'label' => $epi['group_labels'][$gk] ?? $gk,
            'color' => $colors[$gk] ?? '#6b7280',
            'data'  => $epi['series'][$gk] ?? [],
        ];
    }
    $hasData = $epi['has_data'] && count($epi['days']) > 0;
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 1100px;">

        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Vigilancia de salud del rodaje') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Patrones agregados para leer entre el médico y el safety. Nunca nombres.') }}</p>
                </div>
            </div>
            @if ($canEmitStudy)
                <a href="{{ route('epi.outbreak.create') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'file-text', 'label' => null])
                    {{ __('Emitir estudio de brote') }}
                </a>
            @endif
        </div>

        {{-- Encuadre: esto es SILENCIOSO. No declara brotes ni dispara nada. --}}
        <div class="alert alert-light border d-flex align-items-start gap-2 small">
            @include('componentes._icon', ['name' => 'info', 'label' => null])
            <span>{{ __('Esto es estadística para conversar: no notifica, no alerta y no declara brotes. Es un conteo, no un umbral.') }}</span>
        </div>

        {{-- Cortes: locación / departamento (re-acotan los conteos). El grupo se aísla con la leyenda. --}}
        <form method="get" action="{{ route('epi.index') }}" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">{{ __('Locación') }}</label>
                <select name="location" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">{{ __('Todas') }}</option>
                    @foreach ($epi['locations'] as $loc)
                        <option value="{{ $loc }}" @selected(($filters['location'] ?? null) === $loc)>{{ $loc }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">{{ __('Departamento') }}</label>
                <select name="department" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach ($epi['departments'] as $dep)
                        <option value="{{ $dep }}" @selected(($filters['department'] ?? null) === $dep)>{{ $dep }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                @if (($filters['location'] ?? null) || ($filters['department'] ?? null))
                    <a href="{{ route('epi.index') }}" class="btn btn-sm btn-link text-muted">{{ __('Quitar cortes') }}</a>
                @endif
            </div>
        </form>

        {{-- KPIs cortos --}}
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="insp-tag">{{ __('Consultas') }}: {{ $epi['total_consults'] }}</span>
            <span class="insp-tag" style="background:#e5e7eb;color:#374151;" title="{{ __('Si esta cubeta crece, faltan términos que agregar') }}">
                {{ __('Sin clasificar') }}: {{ $epi['unclassified_total'] }}
            </span>
            <span class="insp-tag">{{ __('Días de rodaje') }}: {{ count($epi['days']) }}</span>
        </div>

        @if (! $hasData)
            <div class="card border-0 shadow-sm rounded-3 p-4 text-center text-muted">
                {{ __('Aún no hay días de rodaje con reporte para leer patrones.') }}
            </div>
        @else
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">{{ __('Consultas por grupo, por día de rodaje') }}</h5>
                    <span class="text-muted small">{{ __('clic en la leyenda para aislar un grupo') }}</span>
                </div>
                <div style="position:relative;height:340px;"><canvas id="epiChart"></canvas></div>
                <p class="text-muted small mt-2 mb-0">
                    {{ __('La línea punteada es la LÍNEA BASE de esta producción (promedio móvil de 3 días); no es un umbral.') }}
                </p>
            </div>

            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <h6 class="mb-2">{{ __('Personas en set ese día (denominador)') }}</h6>
                <div style="position:relative;height:140px;"><canvas id="epiDenom"></canvas></div>
                <p class="text-muted small mt-2 mb-0">{{ __('Sin denominador, un conteo no dice nada. Un día sin dato se ve como hueco, nunca como cero.') }}</p>
            </div>
        @endif

        {{-- Estudios de brote emitidos (el sistema NO sugiere emitir ninguno) --}}
        @if ($studies->isNotEmpty())
            <div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
                <div class="small text-muted mb-2">{{ __('Estudios de brote emitidos') }}</div>
                @foreach ($studies as $s)
                    <a href="{{ route('epi.outbreak.show', $s->uuid) }}" class="d-flex justify-content-between align-items-center text-decoration-none border rounded-3 px-3 py-2 mb-1">
                        <span><strong>{{ $s->folio() }}</strong> · {{ $s->title }}</span>
                        <span class="text-muted small">{{ optional($s->created_at)->format('d/m/Y') }}</span>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</div>

@if ($hasData)
<script>
(function () {
    if (typeof Chart === 'undefined') { return; }
    var css = getComputedStyle(document.documentElement);
    var ink   = (css.getPropertyValue('--text') || '#1f2733').trim();
    var muted = (css.getPropertyValue('--text-muted') || '#6b7683').trim();
    var grid  = (css.getPropertyValue('--border') || '#e3e8ef').trim();

    var labels = @json($labels);
    var lines  = @json($lineData);
    var baseline = @json($epi['baseline']);
    var denom = @json($epi['denominator']);

    var datasets = lines.map(function (l) {
        return {
            label: l.label,
            data: l.data,
            borderColor: l.color,
            backgroundColor: l.color,
            borderWidth: 2,
            tension: 0.25,
            pointRadius: 2,
            spanGaps: false,
            fill: false,
        };
    });
    // Línea base (promedio móvil): punteada, gris, sin puntos. null en el arranque = hueco.
    datasets.push({
        label: '{{ __('Línea base') }}',
        data: baseline,
        borderColor: muted,
        borderWidth: 1.5,
        borderDash: [5, 4],
        pointRadius: 0,
        tension: 0.25,
        spanGaps: false,
        fill: false,
    });

    new Chart(document.getElementById('epiChart').getContext('2d'), {
        type: 'line',
        data: { labels: labels, datasets: datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { color: ink, boxWidth: 12 } } },
            scales: {
                x: { ticks: { color: muted }, grid: { color: grid } },
                y: { beginAtZero: true, ticks: { color: muted, precision: 0 }, grid: { color: grid } },
            },
        },
    });

    // Denominador: barras; null = sin barra (hueco, no cero).
    new Chart(document.getElementById('epiDenom').getContext('2d'), {
        type: 'bar',
        data: { labels: labels, datasets: [{
            label: '{{ __('Personas en set') }}',
            data: denom,
            backgroundColor: 'rgba(37,99,235,0.35)',
            borderColor: '#2563eb',
            borderWidth: 1,
        }]},
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: muted }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: muted, precision: 0 }, grid: { color: grid } },
            },
        },
    });
})();
</script>
@endif

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
