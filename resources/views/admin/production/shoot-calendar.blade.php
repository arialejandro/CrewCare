@extends('layouts.app')
@section('content')

@push('styles')
<style>
    .cal-wrap { max-width: 1040px; }
    .adm-header { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .adm-icon { width:48px; height:48px; border-radius:14px; flex:none; display:inline-flex; align-items:center; justify-content:center;
        color:var(--brand-primary); background:color-mix(in srgb, var(--brand-primary) 16%, transparent); border:1px solid var(--stroke); }
    .adm-icon .cc-ico { width:22px; height:22px; }
    .adm-title { font-family:'Poppins',sans-serif; font-weight:600; letter-spacing:-.02em; margin:0; color:var(--text); font-size:1.35rem; }
    .adm-subtitle { color:var(--text-muted); font-size:.9rem; margin:.15rem 0 0; }
    .cal-card .card-header { background:transparent; color:var(--text); font-weight:600; border-bottom:1px solid var(--stroke);
        border-radius:var(--radius) var(--radius) 0 0; }
    .cal-wrap .form-control, .cal-wrap .form-select { background-color:var(--glass); border:1px solid var(--stroke); color:var(--text); }
    .cal-wrap .form-text { color:var(--text-muted); }
    .cal-note.muted { border-radius:12px; padding:.8rem 1rem; font-size:.9rem; border:1px solid var(--stroke); background:var(--glass); color:var(--text-muted); }

    /* ===== Navegación de mes ===== */
    .cal-nav { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:1rem; }
    .cal-nav .cal-month { font-family:'Poppins',sans-serif; font-weight:600; font-size:1.15rem; color:var(--text); text-transform:capitalize; }

    /* ===== Leyenda ===== */
    .cal-legend { display:flex; flex-wrap:wrap; gap:.5rem 1rem; font-size:.8rem; color:var(--text-muted); margin-bottom:.9rem; }
    .cal-legend .lg { display:inline-flex; align-items:center; gap:.35rem; }
    .lg .sw { width:14px; height:14px; border-radius:4px; border:1px solid var(--stroke); display:inline-block; }
    .sw.sw-shoot { background:color-mix(in srgb, var(--brand-primary) 26%, transparent); border-color:color-mix(in srgb, var(--brand-primary) 55%, var(--stroke)); }
    .sw.sw-rest { background:var(--glass); }
    .sw.sw-manual { background:transparent; border:1.5px dashed var(--brand-primary); }

    /* ===== Rejilla ===== */
    .cal-grid { width:100%; border-collapse:separate; border-spacing:6px; table-layout:fixed; }
    .cal-grid th { font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); font-weight:600; padding:.2rem; text-align:center; }
    .cal-grid th.wk-th { width:44px; }
    .wk-label { font-size:.66rem; color:var(--text-muted); text-align:center; line-height:1.1; }
    .wk-label b { color:var(--brand-primary); font-size:.82rem; display:block; }

    .cal-cell { position:relative; height:82px; border:1px solid var(--stroke); border-radius:10px; background:var(--bg-2); vertical-align:top; padding:0; overflow:visible; }
    .cal-cell.out-month { opacity:.4; }
    .cal-cell.is-shoot { background:color-mix(in srgb, var(--brand-primary) 15%, var(--bg-2)); border-color:color-mix(in srgb, var(--brand-primary) 45%, var(--stroke)); }
    .cal-cell.is-rest  { background:var(--glass); }
    .cal-cell.manual::after { content:''; position:absolute; inset:0; border-radius:10px; border:1.5px dashed var(--brand-primary); pointer-events:none; }
    .cal-cell.week-end { box-shadow: inset -4px 0 0 0 color-mix(in srgb, var(--brand-primary) 55%, transparent); }

    .cell-form { margin:0; height:100%; }
    .cell-btn { width:100%; height:100%; background:transparent; border:0; color:var(--text); text-align:left; padding:.4rem .5rem; cursor:pointer; display:flex; flex-direction:column; justify-content:space-between; border-radius:10px; }
    .cell-btn:hover { background:color-mix(in srgb, var(--brand-primary) 8%, transparent); }
    .cell-dom { font-variant-numeric:tabular-nums; font-weight:600; font-size:.95rem; }
    .cell-rno { align-self:flex-start; font-size:.72rem; font-weight:700; color:#fff; background:var(--brand-primary); border-radius:6px; padding:.05rem .4rem; letter-spacing:.02em; }
    .cell-rest-tag { font-size:.68rem; color:var(--text-muted); }

    /* ===== Chip de luz (details = menú nativo, sin JS) ===== */
    .cell-light { position:absolute; top:5px; right:5px; }
    .cell-light > summary { list-style:none; cursor:pointer; width:22px; height:22px; border-radius:6px; display:flex; align-items:center; justify-content:center;
        font-size:.62rem; font-weight:800; border:1px solid var(--stroke); color:var(--text-muted); background:var(--bg-2); }
    .cell-light > summary::-webkit-details-marker { display:none; }
    .luz-dia    > summary { color:var(--text-muted); }
    .luz-noche  > summary { color:#fff; background:#3730a3; border-color:#3730a3; }
    .luz-mixto  > summary { color:#fff; background:#7c3aed; border-color:#7c3aed; }
    .luz-amanecer > summary { color:#78350f; background:#fbbf24; border-color:#f59e0b; }
    .luz-atardecer > summary { color:#fff; background:#ea580c; border-color:#ea580c; }
    .cell-light-menu { position:absolute; top:26px; right:0; z-index:30; background:var(--bg-2); border:1px solid var(--stroke); border-radius:10px; padding:.35rem; box-shadow:0 8px 24px rgba(0,0,0,.25); min-width:118px; }
    .cell-light-menu form { display:flex; flex-direction:column; gap:.15rem; margin:0; }
    .cell-light-menu button { text-align:left; font-size:.78rem; border:0; background:transparent; color:var(--text); border-radius:6px; padding:.28rem .5rem; cursor:pointer; }
    .cell-light-menu button:hover { background:color-mix(in srgb, var(--brand-primary) 14%, transparent); }
    .cell-light-menu button.on { font-weight:700; color:var(--brand-primary); }

    /* ===== Selector de unidad ===== */
    .unit-bar { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; margin-bottom:1rem; }
    .unit-lbl { font-size:.8rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em; margin-right:.2rem; }
    .unit-chip { font-size:.85rem; text-decoration:none; color:var(--text); border:1px solid var(--stroke); background:var(--glass); border-radius:999px; padding:.28rem .8rem; }
    .unit-chip:hover { border-color:color-mix(in srgb, var(--brand-primary) 45%, var(--stroke)); }
    .unit-chip.on { background:var(--brand-primary); color:#fff; border-color:var(--brand-primary); font-weight:600; }
    .cal-unit-tag { color:var(--brand-primary); font-weight:500; font-size:.9rem; }

    @media (max-width:640px){
        .cal-cell { height:68px; }
        .cal-grid { border-spacing:4px; }
        .cell-rno { font-size:.66rem; }
    }
</style>
@endpush

@php
    use Carbon\Carbon;
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $monthLabel = $meses[$cursor->month] . ' ' . $cursor->year;
    $dows = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    $lightMeta = [
        'DÍA'       => ['ini' => 'D',  'cls' => 'luz-dia'],
        'NOCHE'     => ['ini' => 'N',  'cls' => 'luz-noche'],
        'AMANECER'  => ['ini' => 'AM', 'cls' => 'luz-amanecer'],
        'ATARDECER' => ['ini' => 'AT', 'cls' => 'luz-atardecer'],
        'MIXTO'     => ['ini' => 'MX', 'cls' => 'luz-mixto'],
    ];
    $filas = array_merge($semanas, array_fill(0, 8, ['end' => '', 'days' => '', 'last_slug' => 'DÍA']));
    $unitParam = $unitId !== null ? ['unit' => $unitId] : [];
@endphp

<div class="container py-4 cal-wrap">

    <div class="adm-header mb-4">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'calendar', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Días de rodaje</h1>
            <p class="adm-subtitle">El calendario manda: el contador avanza aunque no exista un reporte. Toca un día para alternar rodaje/descanso.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))<div class="alert alert-warning">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if(! $prod)
        <div class="alert alert-warning">No hay producción vigente que configurar.</div>
    @else

    {{-- ============ SELECTOR DE UNIDAD — solo aparece cuando existe más de una ============ --}}
    @if($units->isNotEmpty())
        <div class="unit-bar">
            <span class="unit-lbl">Unidad:</span>
            <a href="{{ route('production.shootdays.edit', ['month' => $cursor->format('Y-m')]) }}"
               class="unit-chip {{ $unitId === null ? 'on' : '' }}">Principal</a>
            @foreach($units as $u)
                <a href="{{ route('production.shootdays.edit', ['month' => $cursor->format('Y-m'), 'unit' => $u->id]) }}"
                   class="unit-chip {{ $unitId === $u->id ? 'on' : '' }}">{{ $u->name }}</a>
            @endforeach
        </div>
    @endif

    {{-- ============ ASISTENTE: generación en lote (vía rápida) ============ --}}
    <details class="card cal-card mb-4">
        <summary class="card-header" style="cursor:pointer; list-style:none;">
            ⚡ Asistente: generar semanas de un golpe
            <span class="form-text ms-2">Marca el fin de cada semana → los días se derivan hacia atrás</span>
        </summary>
        <div class="card-body">
            <form action="{{ route('production.shootdays.generate') }}" method="POST">
                @csrf
                <input type="hidden" name="month" value="{{ $cursor->format('Y-m') }}">
                                            <input type="hidden" name="unit" value="{{ $unitId }}">
                <p class="form-text mb-3">
                    {{ $daysPerWeek }} días/semana por default (ajustable por fila). Una luz <strong>NOCHE</strong> o
                    <strong>MIXTO</strong> en el último día recorre el arranque de la siguiente semana un día hábil
                    (viernes nocturno → lunes, no sábado). Las <strong>excepciones a mano</strong> de la rejilla se conservan.
                </p>
                <div class="row g-2 mb-1 d-none d-md-flex form-text">
                    <div class="col-md-5">Fin de semana</div><div class="col-md-3">Días</div><div class="col-md-4">Luz del último día</div>
                </div>
                @foreach($filas as $i => $r)
                    <div class="row g-2 mb-2">
                        <div class="col-md-5"><input type="date" name="weeks[{{ $i }}][end]" class="form-control" value="{{ $r['end'] }}"></div>
                        <div class="col-md-3"><input type="number" name="weeks[{{ $i }}][days]" class="form-control" min="1" max="7" placeholder="{{ $daysPerWeek }}" value="{{ $r['days'] }}"></div>
                        <div class="col-md-4">
                            <select name="weeks[{{ $i }}][last_slug]" class="form-select">
                                @foreach($slugs as $s)<option value="{{ $s }}" @selected(($r['last_slug'] ?? 'DÍA') === $s)>{{ $s }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                @endforeach
                <div class="d-flex justify-content-end mt-2">
                    <button type="submit" class="btn btn-primary">@include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Generar días</button>
                </div>
            </form>
        </div>
    </details>

    {{-- ============ LA REJILLA MENSUAL ============ --}}
    <div class="card cal-card">
        <div class="card-body">

            <div class="cal-nav">
                <a href="{{ route('production.shootdays.edit', array_merge(['month' => $prevMonth], $unitParam)) }}" class="btn btn-outline-secondary btn-sm">← Mes anterior</a>
                <span class="cal-month">{{ $monthLabel }}@if($unitId !== null) <span class="cal-unit-tag">· {{ $unitLabel }}</span>@endif</span>
                <a href="{{ route('production.shootdays.edit', array_merge(['month' => $nextMonth], $unitParam)) }}" class="btn btn-outline-secondary btn-sm">Mes siguiente →</a>
            </div>

            <div class="cal-legend">
                <span class="lg"><span class="sw sw-shoot"></span> Rodaje</span>
                <span class="lg"><span class="sw sw-rest"></span> Descanso</span>
                <span class="lg"><span class="sw sw-manual"></span> Marcado a mano</span>
                <span class="lg"><span class="sw" style="background:#3730a3;border-color:#3730a3;"></span> Noche</span>
                <span class="lg"><span class="sw" style="background:#7c3aed;border-color:#7c3aed;"></span> Mixto</span>
                <span class="lg"><span class="sw" style="background:#fbbf24;border-color:#f59e0b;"></span> Amanecer</span>
                <span class="lg"><span class="sw" style="background:#ea580c;border-color:#ea580c;"></span> Atardecer</span>
                <span class="lg ms-auto"><strong style="color:var(--brand-primary)">{{ $total }}</strong> días de rodaje en total</span>
            </div>

            <table class="cal-grid">
                <thead>
                    <tr>
                        <th class="wk-th">Sem</th>
                        @foreach($dows as $d)<th>{{ $d }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($weeks as $w)
                        <tr>
                            <td class="wk-label">@if($w['week_no'])<b>{{ $w['week_no'] }}</b>@endif</td>
                            @foreach($w['dias'] as $c)
                                @php
                                    $lm = $lightMeta[$c['slug']] ?? $lightMeta['DÍA'];
                                    $classes = 'cal-cell';
                                    if (! $c['inMonth']) $classes .= ' out-month';
                                    elseif ($c['isShoot']) $classes .= ' is-shoot';
                                    elseif ($c['sd']) $classes .= ' is-rest';
                                    if ($c['manual']) $classes .= ' manual';
                                    if ($c['weekEnd']) $classes .= ' week-end';
                                @endphp
                                <td class="{{ $classes }}">
                                    @if($c['inMonth'])
                                        <form action="{{ route('production.shootdays.toggle') }}" method="POST" class="cell-form">
                                            @csrf
                                            <input type="hidden" name="date" value="{{ $c['date'] }}">
                                            <input type="hidden" name="is_shoot" value="{{ $c['isShoot'] ? 0 : 1 }}">
                                            <input type="hidden" name="month" value="{{ $cursor->format('Y-m') }}">
                                            <input type="hidden" name="unit" value="{{ $unitId }}">
                                            <button type="submit" class="cell-btn" title="{{ $c['isShoot'] ? 'Marcar descanso' : 'Marcar rodaje' }}">
                                                <span class="cell-dom">{{ $c['dom'] }}</span>
                                                @if($c['shootNo'])
                                                    <span class="cell-rno">Día {{ $c['shootNo'] }}</span>
                                                @elseif($c['sd'] && ! $c['isShoot'])
                                                    <span class="cell-rest-tag">Descanso</span>
                                                @endif
                                            </button>
                                        </form>
                                        @if($c['isShoot'])
                                            <details class="cell-light {{ $lm['cls'] }}">
                                                <summary title="Luz del día">{{ $lm['ini'] }}</summary>
                                                <div class="cell-light-menu">
                                                    <form action="{{ route('production.shootdays.light') }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="date" value="{{ $c['date'] }}">
                                                        <input type="hidden" name="month" value="{{ $cursor->format('Y-m') }}">
                                            <input type="hidden" name="unit" value="{{ $unitId }}">
                                                        @foreach($slugs as $s)
                                                            <button type="submit" name="slug" value="{{ $s }}" class="{{ $c['slug'] === $s ? 'on' : '' }}">{{ $s }}</button>
                                                        @endforeach
                                                    </form>
                                                </div>
                                            </details>
                                        @endif
                                    @else
                                        <span class="cell-btn" style="cursor:default;"><span class="cell-dom">{{ $c['dom'] }}</span></span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-3">
                <a href="{{ route('production.calendar.edit') }}" class="btn btn-outline-secondary btn-sm">← Calendario planeado</a>
            </div>
        </div>
    </div>

    @endif
</div>

@endsection
