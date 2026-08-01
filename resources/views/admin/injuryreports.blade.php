@extends('layouts.app')

@push('styles')
<style>
    /* ── Índice glass · lenguaje Cinematic Dark Glass (tokens de _brand-theme) ── */
    .cc-idx-head{display:flex;align-items:flex-end;gap:1.25rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.25rem}
    .cc-idx-eyebrow{font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700;display:inline-flex;align-items:center;gap:.5rem}
    .cc-idx-eyebrow .cc-ico{width:15px;height:15px}
    .cc-idx-title{margin:.35rem 0 .2rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:clamp(1.5rem,2.6vw,2rem);color:var(--text);line-height:1.05}
    .cc-idx-sub{margin:0;color:var(--text-muted);font-size:.9rem}

    .cc-idx-count{display:inline-flex;align-items:center;gap:.45rem;padding:.55rem .9rem;border-radius:var(--radius-sm);background:var(--glass);border:1px solid var(--stroke);color:var(--text);font-weight:700;font-size:.85rem}
    .cc-idx-count .cc-ico{width:16px;height:16px;color:var(--brand-primary)}
    .cc-idx-count span{color:var(--text-muted);font-weight:600}

    /* Buscador glass con icono. */
    .cc-search{position:relative;max-width:520px;margin-bottom:1.5rem}
    .cc-search .cc-ico{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);width:17px;height:17px;color:var(--text-muted);pointer-events:none}
    .cc-search input{width:100%;height:44px;padding:0 .95rem 0 2.4rem;border-radius:var(--radius-sm);border:1px solid var(--stroke);background:var(--glass);color:var(--text);font-size:.9rem;transition:border-color .18s,box-shadow .18s,background .18s}
    .cc-search input::placeholder{color:var(--text-muted)}
    .cc-search input:focus{outline:none;border-color:var(--brand-primary);background:var(--glass-2);box-shadow:0 0 0 .2rem rgba(var(--brand-primary-rgb),.22)}

    .cc-idx-group-title{font-family:'Poppins',sans-serif;font-weight:700;font-size:1.05rem;color:var(--text);margin:1.75rem 0 1rem;padding-bottom:.4rem;border-bottom:1px solid var(--stroke);display:flex;align-items:center;gap:.5rem}
    .cc-idx-group-title .cc-ico{width:16px;height:16px;color:var(--brand-primary)}

    .cc-idx-empty{text-align:center;padding:3.5rem 1.5rem;color:var(--text-muted)}
    .cc-idx-empty .cc-ico{width:46px;height:46px;color:var(--text-muted);opacity:.55;margin-bottom:.85rem}
    .cc-idx-empty h5{font-family:'Poppins',sans-serif;font-weight:700;color:var(--text)}

    /* Tarjeta accidente: hereda vidrio global de .card; hover elevado. */
    .injury-card{overflow:hidden;transition:transform .2s var(--ease,cubic-bezier(.16,1,.3,1)),box-shadow .2s,border-color .2s;height:100%}
    .injury-card:hover{transform:translateY(-4px);border-color:var(--stroke-2);box-shadow:0 16px 34px -14px rgba(0,0,0,.5),var(--shadow) !important}
    .injury-img-wrapper{height:190px;overflow:hidden}
    .injury-img{width:100%;height:100%;object-fit:cover;display:block}
    .injury-img-placeholder{height:190px;display:flex;align-items:center;justify-content:center;background:var(--surface-3);color:var(--text-muted)}
    .injury-img-placeholder .cc-ico{width:38px;height:38px;opacity:.6}
    .injury-meta{font-size:.9rem;color:var(--text);display:flex;justify-content:space-between;align-items:center;gap:.75rem;padding:.75rem 1rem}
    .injury-meta .im-date{color:var(--text);font-weight:700}
    .injury-meta .im-line{color:var(--text-muted);display:flex;align-items:center;gap:.35rem}
    .injury-meta .cc-ico{width:14px;height:14px}
</style>
@endpush

@section('content')
<div class="container-fluid py-4 px-3 px-md-4">

    @php
        $groupedReports = $injuryReports->sortByDesc('incident_date')->groupBy(function($item) {
            return \Carbon\Carbon::parse($item->incident_date)->format('M Y');
        });
    @endphp

    <div class="cc-idx-head">
        <div>
            <div class="cc-idx-eyebrow">@include('componentes._icon', ['name' => 'ambulance']) <span>Seguridad</span></div>
            <h1 class="cc-idx-title">Reportes de Accidentes</h1>
            <p class="cc-idx-sub">Registro de lesiones e incidentes de producción.</p>
        </div>
        <span class="cc-idx-count">
            @include('componentes._icon', ['name' => 'ambulance'])
            {{ $injuryReports->count() }} <span>reportes</span>
        </span>
    </div>

    <div class="cc-search">
        @include('componentes._icon', ['name' => 'search'])
        <input type="text" id="searchInput" placeholder="Buscar por producción, locación o departamento…" aria-label="Buscar reportes de accidentes">
    </div>

    @foreach($groupedReports as $month => $reports)
        <div class="cc-idx-group-title">@include('componentes._icon', ['name' => 'calendar']) <span>{{ $month }}</span></div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            @foreach($reports as $report)
            <div class="col report-card"
                 data-search="{{ strtolower($report->production_title . ' ' . $report->location . ' ' . $report->department) }}">
                <a href="{{ route('injury_reports.show', $report->id) }}" class="text-decoration-none d-block h-100">
                    <div class="card injury-card border-0 rounded-3">
                        @if($report->main_image_path)
                            <div class="injury-img-wrapper">
                                <img src="{{ asset($report->main_image_path) }}" class="injury-img" alt="Evidencia fotográfica">
                            </div>
                        @else
                            <div class="injury-img-placeholder">
                                @include('componentes._icon', ['name' => 'ambulance', 'label' => 'Sin evidencia fotográfica'])
                            </div>
                        @endif

                        <div class="injury-meta">
                            <div>
                                <div class="im-date">{{ \Carbon\Carbon::parse($report->incident_date)->format('d M Y') }}</div>
                                <div class="im-line">
                                    @include('componentes._icon', ['name' => 'map-pin'])<span>{{ $report->location ?? 'Sin locación' }}</span>
                                </div>
                            </div>
                            <div class="im-line">
                                @include('componentes._icon', ['name' => 'building-2'])<span>{{ $report->department ?? 'Sin depto.' }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    @endforeach

    @if($injuryReports->isEmpty())
        <div class="card border-0">
            <div class="card-body cc-idx-empty">
                @include('componentes._icon', ['name' => 'ambulance', 'label' => 'Sin reportes'])
                <h5>No hay reportes de accidentes</h5>
                <p class="small mb-0">Los reportes de lesiones e incidentes aparecerán aquí.</p>
            </div>
        </div>
    @endif
</div>

<script>
    document.getElementById('searchInput').addEventListener('input', function () {
        const search = this.value.toLowerCase();
        const cards = document.querySelectorAll('.report-card');

        cards.forEach(card => {
            const keywords = card.getAttribute('data-search');
            card.style.display = keywords.includes(search) ? 'block' : 'none';
        });
    });
</script>
@endsection
