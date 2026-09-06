@extends('layouts.app')
@section('content')

@push('styles')
<style>
    .cal-wrap { max-width: 920px; }
    .adm-header { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .adm-icon {
        width:48px; height:48px; border-radius:14px; flex:none; display:inline-flex;
        align-items:center; justify-content:center; color:var(--brand-primary);
        background:color-mix(in srgb, var(--brand-primary) 16%, transparent); border:1px solid var(--stroke);
    }
    .adm-icon .cc-ico { width:22px; height:22px; }
    .adm-title { font-family:'Poppins',sans-serif; font-weight:600; letter-spacing:-.02em; margin:0; color:var(--text); font-size:1.35rem; }
    .adm-subtitle { color:var(--text-muted); font-size:.9rem; margin:.15rem 0 0; }
    .cal-card .card-header { background:transparent; color:var(--text); font-weight:600; border-bottom:1px solid var(--stroke); border-radius:var(--radius) var(--radius) 0 0; }
    .cal-wrap .form-control, .cal-wrap .form-select { background-color:var(--glass); border:1px solid var(--stroke); color:var(--text); }
    .cal-wrap .form-text { color:var(--text-muted); }
    .cal-note { border-radius:12px; padding:.8rem 1rem; font-size:.9rem; border:1px solid var(--stroke); }
    .cal-note.muted { background:var(--glass); color:var(--text-muted); }

    .wk-grid { display:grid; grid-template-columns: 1.4fr .7fr 1.3fr; gap:.6rem; align-items:center; margin-bottom:.5rem; }
    .wk-head { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:.35rem; }
    @media (max-width:575px){ .wk-grid { grid-template-columns:1fr; } .wk-head { display:none; } }

    .sd-table { display:flex; flex-direction:column; gap:.35rem; }
    .sd-row { display:grid; grid-template-columns: 1.3fr .6fr .8fr auto auto auto; gap:.55rem; align-items:center;
              border:1px solid var(--stroke); border-radius:10px; padding:.4rem .6rem; background:var(--glass); }
    .sd-row.sd-rest { opacity:.62; }
    .sd-date { font-variant-numeric:tabular-nums; color:var(--text); font-weight:500; }
    .sd-week, .sd-state { color:var(--text-muted); font-size:.85rem; }
    .sd-inline { display:flex; gap:.3rem; align-items:center; margin:0; }
    .sd-inline .form-select-sm { min-width:120px; }
    .sd-badge { font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--brand-primary);
                background:color-mix(in srgb, var(--brand-primary) 14%, transparent); border-radius:6px; padding:.1rem .4rem; }
    @media (max-width:767px){ .sd-row { grid-template-columns:1fr 1fr; } }
</style>
@endpush

@php
    $dowNames = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    $dow = fn($d) => $dowNames[\Carbon\Carbon::parse($d)->dayOfWeek];

    // Reconstruye las semanas desde los días guardados, para poder editarlas.
    $semanas = [];
    foreach ($dias->where('is_shoot_day', true)->groupBy('week_no') as $wk => $g) {
        $g    = $g->sortBy('shoot_date');
        $last = $g->last();
        $semanas[] = [
            'end'       => optional($last->shoot_date)->format('Y-m-d'),
            'days'      => $g->count(),
            'last_slug' => $last->slug(),
        ];
    }
    $filas = array_merge($semanas, array_fill(0, 10, ['end' => '', 'days' => '', 'last_slug' => 'DÍA']));
@endphp

<div class="container py-4 cal-wrap">

    <div class="adm-header mb-4">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'calendar', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Días de rodaje</h1>
            <p class="adm-subtitle">El calendario manda: el contador avanza aunque no exista un reporte. El reporte confirma.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-warning">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if(! $prod)
        <div class="alert alert-warning">No hay producción vigente que configurar.</div>
    @else

    <form action="{{ route('production.shootdays.generate') }}" method="POST">
        @csrf
        <div class="card cal-card mb-3">
            <div class="card-header">Marca el fin de cada semana</div>
            <div class="card-body">
                <p class="form-text mb-3">
                    Marca el <strong>último día</strong> de cada semana; los días se derivan hacia atrás
                    ({{ $daysPerWeek }}/semana por default, ajustable por fila). Una luz <strong>NOCHE</strong> o
                    <strong>MIXTO</strong> en el último día recorre el arranque de la semana siguiente un día hábil
                    (un viernes nocturno → el siguiente no es el sábado sino el lunes). Deja filas vacías para menos semanas;
                    las <strong>excepciones a mano</strong> de abajo se conservan al regenerar.
                </p>
                <div class="wk-grid wk-head"><span>Fin de semana</span><span>Días</span><span>Luz del último día</span></div>
                @foreach($filas as $i => $r)
                    <div class="wk-grid">
                        <input type="date" name="weeks[{{ $i }}][end]" class="form-control" value="{{ $r['end'] }}">
                        <input type="number" name="weeks[{{ $i }}][days]" class="form-control" min="1" max="7" placeholder="{{ $daysPerWeek }}" value="{{ $r['days'] }}">
                        <select name="weeks[{{ $i }}][last_slug]" class="form-select">
                            @foreach($slugs as $s)<option value="{{ $s }}" @selected(($r['last_slug'] ?? 'DÍA') === $s)>{{ $s }}</option>@endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="d-flex justify-content-between mb-4">
            <a href="{{ route('production.calendar.edit') }}" class="btn btn-outline-secondary">← Calendario planeado</a>
            <button type="submit" class="btn btn-primary">
                @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Generar días
            </button>
        </div>
    </form>

    <div class="card cal-card">
        <div class="card-header">Días generados <span class="text-muted fw-normal">· {{ $total }} de rodaje</span></div>
        <div class="card-body">
            @if($dias->isEmpty())
                <div class="cal-note muted">Aún no hay días marcados. Marca los fines de semana arriba y genera.</div>
            @else
                <div class="sd-table">
                    @foreach($dias as $d)
                        @php $f = $d->shoot_date->format('Y-m-d'); @endphp
                        <div class="sd-row {{ $d->is_shoot_day ? '' : 'sd-rest' }}">
                            <span class="sd-date">{{ $dow($f) }} {{ $d->shoot_date->format('d/m/Y') }}</span>
                            <span class="sd-week">Sem {{ $d->week_no ?? '—' }}</span>
                            <span class="sd-state">{{ $d->is_shoot_day ? 'Rodaje' : 'Descanso' }}</span>
                            <form action="{{ route('production.shootdays.light') }}" method="POST" class="sd-inline">
                                @csrf
                                <input type="hidden" name="date" value="{{ $f }}">
                                <select name="slug" class="form-select form-select-sm">
                                    @foreach($slugs as $s)<option value="{{ $s }}" @selected($d->slug() === $s)>{{ $s }}</option>@endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Luz</button>
                            </form>
                            <form action="{{ route('production.shootdays.toggle') }}" method="POST" class="sd-inline">
                                @csrf
                                <input type="hidden" name="date" value="{{ $f }}">
                                <input type="hidden" name="is_shoot" value="{{ $d->is_shoot_day ? 0 : 1 }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $d->is_shoot_day ? 'Marcar descanso' : 'Marcar rodaje' }}</button>
                            </form>
                            @if($d->is_manual)<span class="sd-badge">a mano</span>@else<span></span>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @endif
</div>

@endsection
