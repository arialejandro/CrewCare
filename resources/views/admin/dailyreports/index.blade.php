@extends('layouts.app')
@section('title', 'Daily Reports - ' . ($branding['brand_name'] ?? 'CrewCare'))

@push('styles')
<style>
    /* ── Índice glass · lenguaje Cinematic Dark Glass (tokens de _brand-theme) ── */
    .cc-idx-head{display:flex;align-items:flex-end;gap:1.25rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.75rem}
    .cc-idx-eyebrow{font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700;display:inline-flex;align-items:center;gap:.5rem}
    .cc-idx-eyebrow .cc-ico{width:15px;height:15px}
    .cc-idx-title{margin:.35rem 0 .2rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:clamp(1.5rem,2.6vw,2rem);color:var(--text);line-height:1.05}
    .cc-idx-sub{margin:0;color:var(--text-muted);font-size:.9rem}
    .cc-idx-cta{display:inline-flex;align-items:center;gap:.55rem;padding:.7rem 1.15rem;border-radius:14px;text-decoration:none;font-weight:700;font-size:.9rem;background:var(--brand-primary);color:var(--brand-on-primary);border:1px solid var(--brand-primary);box-shadow:0 12px 30px -10px var(--brand-glow);transition:transform .2s var(--ease,cubic-bezier(.16,1,.3,1)),box-shadow .2s,filter .2s}
    .cc-idx-cta:hover{transform:translateY(-2px);color:var(--brand-on-primary);filter:brightness(1.04);box-shadow:0 18px 40px -10px var(--brand-glow)}
    .cc-idx-cta .cc-ico{width:18px;height:18px}

    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.3rem .6rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap}
    .cc-chip .cc-ico{width:13px;height:13px}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}
    .cc-chip-brand{color:var(--brand-primary);background:color-mix(in srgb,var(--brand-primary) 14%,transparent);border-color:color-mix(in srgb,var(--brand-primary) 30%,transparent)}
    .cc-chip-neutral{color:var(--text-muted);background:var(--glass-2);border-color:var(--stroke)}

    .cc-idx-empty{text-align:center;padding:3.5rem 1.5rem;color:var(--text-muted)}
    .cc-idx-empty .cc-ico{width:46px;height:46px;color:var(--text-muted);opacity:.55;margin-bottom:.85rem}
    .cc-idx-empty h5{font-family:'Poppins',sans-serif;font-weight:700;color:var(--text)}

    /* Tarjeta DSR: hereda vidrio global de .card; añadimos hover elevado. */
    .dsr-card{transition:transform .2s var(--ease,cubic-bezier(.16,1,.3,1)),box-shadow .2s,border-color .2s}
    .dsr-card:hover{transform:translateY(-4px);border-color:var(--stroke-2);box-shadow:0 16px 34px -14px rgba(0,0,0,.5),var(--shadow) !important}
    .dsr-cover{object-fit:cover;height:180px;width:100%;display:block}
    .dsr-cover-placeholder{height:180px;width:100%;display:flex;align-items:center;justify-content:center;background:var(--surface-3);color:var(--text-muted)}
    .dsr-cover-placeholder .cc-ico{width:38px;height:38px;opacity:.6}
    .dsr-cover-wrap{position:relative}
    .dsr-day-badge{position:absolute;top:.75rem;left:.75rem;background:rgba(8,12,20,.72);color:#fff;font-weight:700;letter-spacing:.5px;z-index:2;backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
    .dsr-status-badge{position:absolute;top:.75rem;right:.75rem;z-index:2}
    .dsr-headline{font-family:'Poppins',sans-serif}
    .dsr-date{color:var(--text)}
    .dsr-accent-btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;background:var(--brand-primary);border:1px solid var(--brand-primary);color:var(--brand-on-primary);transition:filter .18s}
    .dsr-accent-btn:hover{filter:brightness(.94);color:var(--brand-on-primary)}
    .dsr-accent-btn .cc-ico{width:16px;height:16px}
</style>
@endpush

@section('content')
<div class="container-fluid mt-4 mb-5">

    <div class="cc-idx-head">
        <div>
            <div class="cc-idx-eyebrow">@include('componentes._icon', ['name' => 'clipboard-list']) <span>Seguridad</span></div>
            <h1 class="cc-idx-title">Daily Safety Reports</h1>
            <p class="cc-idx-sub">Bitácora de seguridad diaria del proyecto {{ $branding['brand_name'] ?? 'CrewCare' }}.</p>
        </div>
        @can('dsr.create')
        <a href="{{ route('daily_reports.create') }}" class="cc-idx-cta">
            @include('componentes._icon', ['name' => 'plus']) Nuevo Daily
        </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($dailyReports->count() > 0)
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
        @foreach($dailyReports as $report)
        @php
            // Comparamos la fecha de creación con el momento actual (misma lógica que la tabla original)
            $isLocked = $report->created_at->diffInHours(now()) >= 24;
        @endphp
        <div class="col">
            <div class="card dsr-card border-0 rounded-3 h-100 overflow-hidden">

                <div class="dsr-cover-wrap">
                    <span class="dsr-day-badge badge fs-6 rounded-pill px-3 py-2">{{ \App\Support\ProductionCalendar::labelForReport($report) }}</span>

                    @if($isLocked)
                        <span class="dsr-status-badge badge bg-secondary text-white"><i class="fas fa-lock me-1"></i> Sellado</span>
                    @else
                        <span class="dsr-status-badge badge bg-success text-white"><i class="fas fa-lock-open me-1"></i> Abierto</span>
                    @endif

                    @if($report->hero_image_path)
                        {{-- hero_image_path ya es una URL lista (Storage::url) --}}
                        <img src="{{ $report->hero_image_path }}" class="dsr-cover" alt="Portada {{ \App\Support\ProductionCalendar::labelForReport($report) }}">
                    @else
                        <div class="dsr-cover-placeholder">
                            @include('componentes._icon', ['name' => 'clipboard-list', 'label' => 'Sin portada'])
                        </div>
                    @endif
                </div>

                <div class="card-body d-flex flex-column">

                    <div class="mb-2">
                        <div class="fw-bold dsr-date dsr-headline">
                            {{ \Carbon\Carbon::parse($report->report_date)->format('d/m/Y') }}
                        </div>
                        <div class="cc-muted small d-flex align-items-center gap-1">
                            @include('componentes._icon', ['name' => 'map-pin'])<span>{{ $report->location_name }}</span>
                        </div>
                    </div>

                    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                        <span class="cc-chip cc-chip-neutral">{{ $report->slug_setting }}</span>
                        <span class="cc-chip {{ str_contains($report->slug_time, 'NOCHE') ? 'cc-chip-brand' : 'cc-chip-warn' }}">
                            {{ $report->slug_time }}
                        </span>

                        @if($report->weather_condition)
                            <span class="cc-chip cc-chip-neutral">
                                @if($report->weather_condition == 'sunny') <i class="fas fa-sun"></i>
                                @elseif($report->weather_condition == 'rainy') <i class="fas fa-cloud-rain"></i>
                                @elseif($report->weather_condition == 'cloudy') <i class="fas fa-cloud"></i>
                                @else <i class="fas fa-temperature-half"></i>
                                @endif
                                @if(!is_null($report->weather_min_temp) || !is_null($report->weather_max_temp))
                                    {{ $report->weather_min_temp }}°/{{ $report->weather_max_temp }}°
                                @else
                                    {{ ucfirst($report->weather_condition) }}
                                @endif
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        @if($report->logs->count() > 0)
                            <span class="cc-chip cc-chip-ok">
                                @include('componentes._icon', ['name' => 'activity']) {{ $report->logs->count() }} Eventos
                            </span>
                        @else
                            <span class="cc-chip cc-chip-neutral">
                                @include('componentes._icon', ['name' => 'activity']) 0 Eventos
                            </span>
                        @endif
                    </div>

                    <div class="mt-auto">
                        <a href="{{ route('daily_reports.show', $report->id) }}" class="btn dsr-accent-btn fw-bold w-100">
                            Abrir Reporte @include('componentes._icon', ['name' => 'chevron-right'])
                        </a>
                    </div>

                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="card border-0">
        <div class="card-body cc-idx-empty">
            @include('componentes._icon', ['name' => 'clipboard-list', 'label' => 'Sin reportes'])
            <h5>No hay reportes aún</h5>
            <p class="small mb-0">Comienza creando el reporte del día de hoy.</p>
        </div>
    </div>
    @endif

    <div class="mt-4 d-flex justify-content-center">
        {{ $dailyReports->links() }}
    </div>

</div>
@endsection
