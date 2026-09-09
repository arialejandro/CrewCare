@extends('layouts.app')
@section('content')

@push('styles')
<style>
    /* ===== Calendario de rodaje ===== */
    .cal-wrap { max-width: 880px; }

    .adm-header { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .adm-icon {
        width:48px; height:48px; border-radius:14px; flex:none;
        display:inline-flex; align-items:center; justify-content:center;
        color:var(--brand-primary);
        background:color-mix(in srgb, var(--brand-primary) 16%, transparent);
        border:1px solid var(--stroke);
    }
    .adm-icon .cc-ico { width:22px; height:22px; }
    .adm-title { font-family:'Poppins',sans-serif; font-weight:600; letter-spacing:-.02em; margin:0; color:var(--text); font-size:1.35rem; }
    .adm-subtitle { color:var(--text-muted); font-size:.9rem; margin:.15rem 0 0; }

    .cal-card .card-header {
        background:transparent; color:var(--text); font-weight:600;
        letter-spacing:.01em; border-bottom:1px solid var(--stroke);
        border-radius:var(--radius) var(--radius) 0 0;
    }
    .cal-wrap .form-label { color:var(--text); }
    .cal-wrap .form-text { color:var(--text-muted); }
    .cal-wrap .form-control, .cal-wrap .form-select {
        background-color:var(--glass); border:1px solid var(--stroke); color:var(--text);
    }
    .cal-wrap .form-control:focus, .cal-wrap .form-select:focus {
        background-color:var(--bg-2); border-color:var(--brand-primary); color:var(--text);
        box-shadow:0 0 0 .2rem rgba(var(--brand-primary-rgb), .22);
    }

    /* Panel Planeado vs Real */
    .cal-facts { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    @media (max-width:575px){ .cal-facts { grid-template-columns:1fr; } }
    .cal-fact {
        border:1px solid var(--stroke); border-radius:12px; padding:1rem 1.1rem; background:var(--glass);
    }
    .cal-fact h4 { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin:0 0 .6rem; }
    .cal-row { display:flex; justify-content:space-between; gap:.75rem; padding:.28rem 0; font-size:.9rem; color:var(--text); }
    .cal-row span:first-child { color:var(--text-muted); }
    .cal-row .cal-big { font-variant-numeric:tabular-nums; font-weight:600; }

    .cal-note { border-radius:12px; padding:.8rem 1rem; font-size:.9rem; margin-top:1rem; border:1px solid var(--stroke); }
    .cal-note.ok    { background:color-mix(in srgb, #16a34a 12%, transparent); border-color:color-mix(in srgb,#16a34a 40%,var(--stroke)); }
    .cal-note.warn  { background:color-mix(in srgb, #d97706 14%, transparent); border-color:color-mix(in srgb,#d97706 45%,var(--stroke)); }
    .cal-note.muted { background:var(--glass); color:var(--text-muted); }
    .cal-derived { color:var(--brand-primary); font-weight:600; font-variant-numeric:tabular-nums; }
</style>
@endpush

@php
    $fmt = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '—';
@endphp

<div class="container py-4 cal-wrap">

    <div class="adm-header mb-4">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'calendar', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Calendario de rodaje</h1>
            <p class="adm-subtitle">Inicio, semanas y días por semana definen el total de días de rodaje y el wrap estimado.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if(! $prod)
        <div class="alert alert-warning">No hay producción vigente que configurar.</div>
    @else

    <form action="{{ route('production.calendar.update') }}" method="POST">
        @csrf

        <div class="card cal-card mb-4">
            <div class="card-header">{{ $prod->name }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Fecha de inicio de rodaje</label>
                        <input type="date" name="start_date" id="cal-start" class="form-control"
                               value="{{ old('start_date', optional($prod->start_date)->format('Y-m-d')) }}" required>
                        <div class="form-text">El día 1 del rodaje.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Duración (semanas)</label>
                        <input type="number" name="shoot_weeks" id="cal-weeks" class="form-control" min="1" max="104"
                               value="{{ old('shoot_weeks', $prod->shoot_weeks) }}" placeholder="Ej: 12">
                        <div class="form-text">Ajustable semana a semana.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Días por semana</label>
                        <select name="shoot_days_per_week" id="cal-dpw" class="form-select">
                            <option value="">—</option>
                            <option value="6" @selected((string) old('shoot_days_per_week', $prod->shoot_days_per_week) === '6')>6 (lunes a sábado)</option>
                            <option value="5" @selected((string) old('shoot_days_per_week', $prod->shoot_days_per_week) === '5')>5 (lunes a viernes)</option>
                        </select>
                        <div class="form-text">Total de rodaje: <span id="cal-total" class="cal-derived">—</span> días</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Wrap</label>
                        <input type="date" name="end_date" class="form-control"
                               value="{{ old('end_date', optional($prod->end_date)->format('Y-m-d')) }}">
                        <div class="form-text">
                            Derivado:
                            <span class="cal-derived">{{ $summary['planned_wrap'] ? $fmt($summary['planned_wrap']) : '—' }}</span>.
                            Déjalo vacío para usarlo, o ajústalo si tu wrap se movió.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 gap-2 flex-wrap">
            <a href="{{ route('production.shootdays.edit') }}" class="btn btn-outline-primary">
                @include('componentes._icon', ['name' => 'calendar', 'class' => 'cc-ico me-1', 'label' => null])
                Marcar los días de rodaje →
            </a>
            <button type="submit" class="btn btn-primary">
                @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Guardar calendario
            </button>
        </div>
    </form>

    {{-- ============ PLANEADO vs REAL — se muestra, no se resuelve ============ --}}
    <div class="card cal-card">
        <div class="card-header">Planeado vs real</div>
        <div class="card-body">
            <div class="cal-facts">
                <div class="cal-fact">
                    <h4>Planeado (calendario)</h4>
                    <div class="cal-row"><span>Total de días</span><span class="cal-big">{{ $summary['planned_total'] ?? '—' }}</span></div>
                    <div class="cal-row"><span>Semana</span><span>{{ $summary['weeks'] ? $summary['weeks'].' sem × '.$summary['days_per_week'].'/sem' : '—' }}</span></div>
                    <div class="cal-row"><span>Wrap estimado</span><span>{{ $summary['planned_wrap'] ? $fmt($summary['planned_wrap']) : '—' }}</span></div>
                    <div class="cal-row"><span>Wrap fijado</span><span>{{ $summary['set_wrap'] ? $fmt($summary['set_wrap']) : '—' }}</span></div>
                </div>
                <div class="cal-fact">
                    <h4>Real (reportes diarios)</h4>
                    <div class="cal-row"><span>Días con reporte</span><span class="cal-big">{{ $summary['real_days'] }}</span></div>
                    <div class="cal-row"><span>Último con reporte</span><span>{{ $summary['last_real'] ? $fmt($summary['last_real']) : '—' }}</span></div>
                    <div class="cal-row"><span>Hoy</span><span>{{ \App\Support\ProductionCalendar::todayLabel() }}</span></div>
                </div>
            </div>

            @if($summary['divergence'] === null)
                <div class="cal-note muted">Aún no hay días de rodaje con qué comparar.</div>
            @elseif($summary['divergence'] <= 0)
                <div class="cal-note ok">Al día con el plan: los reportes diarios cubren los días de rodaje esperados.</div>
            @else
                <div class="cal-note warn">
                    El plan lleva <strong>{{ $summary['real_days'] + $summary['divergence'] }}</strong> días de rodaje esperados y hay
                    <strong>{{ $summary['real_days'] }}</strong> con reporte:
                    <strong>{{ $summary['divergence'] }}</strong> día(s) sin rodar (lluvia, company move…). No se ajusta solo — el número de día sigue a los reportes.
                </div>
            @endif
        </div>
    </div>

    @endif
</div>

@push('scripts')
<script>
    // Total de rodaje EN VIVO = semanas × días/semana (sólo pinta el derivado; el servidor manda).
    (function () {
        var w = document.getElementById('cal-weeks'),
            d = document.getElementById('cal-dpw'),
            t = document.getElementById('cal-total');
        if (!w || !d || !t) return;
        function paint() {
            var weeks = parseInt(w.value, 10), dpw = parseInt(d.value, 10);
            t.textContent = (weeks > 0 && (dpw === 5 || dpw === 6)) ? (weeks * dpw) : '—';
        }
        w.addEventListener('input', paint);
        d.addEventListener('change', paint);
        paint();
    })();
</script>
@endpush

@endsection
