@extends('layouts.app')

@push('styles')
<style>
    /* ── Índice glass · lenguaje Cinematic Dark Glass (tokens de _brand-theme) ── */
    .cc-idx-head{display:flex;align-items:flex-end;gap:1.25rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.5rem}
    .cc-idx-eyebrow{font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700;display:inline-flex;align-items:center;gap:.5rem}
    .cc-idx-eyebrow .cc-ico{width:15px;height:15px}
    .cc-idx-title{margin:.35rem 0 .2rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:clamp(1.5rem,2.6vw,2rem);color:var(--text);line-height:1.05}
    .cc-idx-sub{margin:0;color:var(--text-muted);font-size:.9rem}
    .cc-idx-cta{display:inline-flex;align-items:center;gap:.55rem;padding:.7rem 1.15rem;border-radius:14px;text-decoration:none;font-weight:700;font-size:.9rem;background:var(--brand-primary);color:var(--brand-on-primary);border:1px solid var(--brand-primary);box-shadow:0 12px 30px -10px var(--brand-glow);transition:transform .2s var(--ease,cubic-bezier(.16,1,.3,1)),box-shadow .2s,filter .2s}
    .cc-idx-cta:hover{transform:translateY(-2px);color:var(--brand-on-primary);filter:brightness(1.04);box-shadow:0 18px 40px -10px var(--brand-glow)}
    .cc-idx-cta .cc-ico{width:16px;height:16px}

    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.3rem .6rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap}
    .cc-chip .cc-ico{width:13px;height:13px}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}
    .cc-chip-high{color:color-mix(in srgb,var(--warn),var(--danger));background:color-mix(in srgb,var(--danger) 13%,transparent);border-color:color-mix(in srgb,var(--danger) 30%,transparent)}
    .cc-chip-danger{color:var(--danger);background:color-mix(in srgb,var(--danger) 16%,transparent);border-color:color-mix(in srgb,var(--danger) 34%,transparent)}
    .cc-chip-neutral{color:var(--text-muted);background:var(--glass-2);border-color:var(--stroke)}

    .cc-idx-count{display:inline-flex;align-items:center;gap:.45rem;padding:.55rem .9rem;border-radius:var(--radius-sm);background:var(--glass);border:1px solid var(--stroke);color:var(--text);font-weight:700;font-size:.85rem}
    .cc-idx-count .cc-ico{width:16px;height:16px;color:var(--brand-primary)}
    .cc-idx-count span{color:var(--text-muted);font-weight:600}

    .cc-idx-group-title{font-family:'Poppins',sans-serif;font-weight:700;font-size:1.05rem;color:var(--text);margin:1.75rem 0 1rem;padding-bottom:.4rem;border-bottom:1px solid var(--stroke);display:flex;align-items:center;gap:.5rem}
    .cc-idx-group-title .cc-ico{width:16px;height:16px;color:var(--brand-primary)}

    .cc-idx-empty{text-align:center;padding:3.5rem 1.5rem;color:var(--text-muted)}
    .cc-idx-empty .cc-ico{width:46px;height:46px;color:var(--text-muted);opacity:.55;margin-bottom:.85rem}
    .cc-idx-empty h5{font-family:'Poppins',sans-serif;font-weight:700;color:var(--text)}

    .report-card{position:relative;overflow:hidden;transition:transform .2s var(--ease,cubic-bezier(.16,1,.3,1)),box-shadow .2s,border-color .2s}
    .report-card:hover{transform:translateY(-4px);border-color:var(--stroke-2);box-shadow:0 16px 34px -14px rgba(0,0,0,.5),var(--shadow) !important}
    .card-header-img{height:190px;overflow:hidden}
    .card-header-img img{width:100%;height:100%;object-fit:cover;display:block}
    .rc-title{color:var(--text);font-family:'Poppins',sans-serif;font-weight:600}
    .rc-meta{color:var(--text-muted)}
    .rc-body-line{color:var(--text)}
</style>
@endpush

@section('content')
<div class="container-fluid py-4 px-3 px-md-4">

    @php
        use Carbon\Carbon;
        $grouped = $unsafenotifications->sortByDesc('date_observed')->groupBy(function($item) {
            return Carbon::parse($item->date_observed)->format('F Y');
        });
    @endphp

    <div class="cc-idx-head">
        <div>
            <div class="cc-idx-eyebrow">@include('componentes._icon', ['name' => 'alert-triangle']) <span>Seguridad</span></div>
            <h1 class="cc-idx-title">Condiciones Inseguras</h1>
            <p class="cc-idx-sub">Notificaciones de condiciones inseguras observadas.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="cc-idx-count">
                @include('componentes._icon', ['name' => 'alert-triangle'])
                {{ $unsafenotifications->total() ?? $unsafenotifications->count() }} <span>registros</span>
            </span>
            {{-- ACCIÓN PRIMARIA: crear (antes duplicada en el sidebar). --}}
            @can('hazards.create')
            <a href="/unsafenotifications/create" class="cc-idx-cta">
                @include('componentes._icon', ['name' => 'plus']) Nueva condición
            </a>
            @endcan
        </div>
    </div>

    @forelse ($grouped as $month => $reports)
        <div class="cc-idx-group-title">@include('componentes._icon', ['name' => 'calendar']) <span>{{ $month }}</span></div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            @foreach ($reports as $report)
            @php
                $rl = $report->risk_level;
                $rlChip = ['Bajo'=>'cc-chip-ok','Medio'=>'cc-chip-warn','Alto'=>'cc-chip-high','Extremo'=>'cc-chip-danger'];
                $st = $report->action_status ?: 'Abierto';
                $stChip = ['Abierto'=>'cc-chip-danger','En proceso'=>'cc-chip-warn','Cerrado'=>'cc-chip-ok'];
                $stIcon = ['Abierto'=>'circle-alert','En proceso'=>'clock','Cerrado'=>'check-circle'];
            @endphp
            <div class="col">
                <a href="{{ route('unsafenotifications.show', $report->id) }}" class="text-decoration-none d-block h-100">
                    <div class="card report-card border-0 rounded-3 h-100">
                        <div class="card-header-img">
                            <img src="{{ asset($report->main_image_path ?? 'images/default.jpg') }}" alt="Imagen principal">
                        </div>
                        <div class="card-body">
                            <h5 class="rc-title mb-1">{{ $report->production_name ?? 'Involucrados no especificados' }}</h5>
                            <p class="rc-meta small mb-2 d-flex align-items-center gap-1">
                                @include('componentes._icon', ['name' => 'calendar'])<span>{{ Carbon::parse($report->date_observed)->format('d M Y') }}</span>
                            </p>
                            <p class="mb-2 d-flex flex-wrap gap-2">
                                @if($rl)
                                    <span class="cc-chip {{ $rlChip[$rl] ?? 'cc-chip-neutral' }}">{{ $rl }}</span>
                                @endif
                                <span class="cc-chip {{ $stChip[$st] ?? 'cc-chip-neutral' }}">
                                    @include('componentes._icon', ['name' => $stIcon[$st] ?? 'info']) {{ $st }}
                                </span>
                            </p>
                            <p class="rc-body-line mb-0 small"><strong>Condición:</strong> {{ Str::limit($report->description_unsafe_cond, 34) }}</p>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    @empty
        <div class="card border-0">
            <div class="card-body cc-idx-empty">
                @include('componentes._icon', ['name' => 'alert-triangle', 'label' => 'Sin notificaciones'])
                <h5>No hay notificaciones registradas</h5>
                <p class="small mb-0">Las condiciones inseguras observadas aparecerán aquí.</p>
            </div>
        </div>
    @endforelse

    <div class="mt-4 d-flex justify-content-center">
        {!! $unsafenotifications->links() !!}
    </div>
</div>
@endsection
