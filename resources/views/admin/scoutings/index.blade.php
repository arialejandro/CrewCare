@extends('layouts.app')
@section('title', 'Scouting H&S - ' . ($branding['brand_name'] ?? 'CrewCare'))

@push('styles')
<style>
    /* ── Índice glass · lenguaje Cinematic Dark Glass (tokens de _brand-theme) ──
       Cabecera consistente + tarjetas de vidrio; cero colores hardcodeados. */
    .cc-idx-head{display:flex;align-items:flex-end;gap:1.25rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.75rem}
    .cc-idx-eyebrow{font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700;display:inline-flex;align-items:center;gap:.5rem}
    .cc-idx-eyebrow .cc-ico{width:15px;height:15px}
    .cc-idx-title{margin:.35rem 0 .2rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:clamp(1.5rem,2.6vw,2rem);color:var(--text);line-height:1.05}
    .cc-idx-sub{margin:0;color:var(--text-muted);font-size:.9rem}
    .cc-idx-cta{display:inline-flex;align-items:center;gap:.55rem;padding:.7rem 1.15rem;border-radius:14px;text-decoration:none;font-weight:700;font-size:.9rem;background:var(--brand-primary);color:var(--brand-on-primary);border:1px solid var(--brand-primary);box-shadow:0 12px 30px -10px var(--brand-glow);transition:transform .2s var(--ease,cubic-bezier(.16,1,.3,1)),box-shadow .2s,filter .2s}
    .cc-idx-cta:hover{transform:translateY(-2px);color:var(--brand-on-primary);filter:brightness(1.04);box-shadow:0 18px 40px -10px var(--brand-glow)}
    .cc-idx-cta .cc-ico{width:18px;height:18px}

    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.3rem .6rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap;text-decoration:none}
    .cc-chip .cc-ico{width:13px;height:13px}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}
    .cc-chip-danger{color:var(--danger);background:color-mix(in srgb,var(--danger) 16%,transparent);border-color:color-mix(in srgb,var(--danger) 34%,transparent)}
    .cc-chip-neutral{color:var(--text-muted);background:var(--glass-2);border-color:var(--stroke)}

    .cc-idx-empty{text-align:center;padding:3.5rem 1.5rem;color:var(--text-muted)}
    .cc-idx-empty .cc-ico{width:46px;height:46px;color:var(--text-muted);opacity:.55;margin-bottom:.85rem}
    .cc-idx-empty h5{font-family:'Poppins',sans-serif;font-weight:700;color:var(--text)}

    /* Tarjeta de scouting: hereda el vidrio global de .card; sólo añadimos el hover. */
    .sct-card{transition:transform .2s var(--ease,cubic-bezier(.16,1,.3,1)),box-shadow .2s,border-color .2s}
    .sct-card:hover{transform:translateY(-4px);border-color:var(--stroke-2);box-shadow:0 16px 34px -14px rgba(0,0,0,.5),var(--shadow) !important}
    /* Card INCOMPLETO (sin 'final' o sin coordenadas): glow ámbar de advertencia. */
    .sct-card-incomplete{box-shadow:0 0 0 1px color-mix(in srgb,var(--warn) 45%,transparent),0 0 18px color-mix(in srgb,var(--warn) 30%,transparent) !important}
    .sct-card-incomplete:hover{transform:translateY(-4px);box-shadow:0 0 0 1px color-mix(in srgb,var(--warn) 55%,transparent),0 0 22px color-mix(in srgb,var(--warn) 38%,transparent),0 16px 34px -14px rgba(0,0,0,.5) !important}
    .sct-cover{object-fit:cover;height:180px;width:100%;display:block}
    .sct-cover-placeholder{height:180px;width:100%;display:flex;align-items:center;justify-content:center;background:var(--surface-3);color:var(--text-muted)}
    .sct-cover-placeholder .cc-ico{width:38px;height:38px;opacity:.6}
    .sct-cover-wrap{position:relative}
    /* Badges superpuestos sobre la foto: fondo oscuro translúcido (legible sobre imagen). */
    .sct-scene-badge{position:absolute;top:.75rem;left:.75rem;background:rgba(8,12,20,.72);color:#fff;font-weight:700;letter-spacing:.5px;z-index:2;max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
    .sct-status-badge{position:absolute;top:.75rem;right:.75rem;z-index:2}
    .sct-headline{font-family:'Poppins',sans-serif}
    .sct-loc-name{color:var(--text)}
    .sct-accent-btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;background:var(--brand-primary);border:1px solid var(--brand-primary);color:var(--brand-on-primary);transition:filter .18s}
    .sct-accent-btn:hover{filter:brightness(.94);color:var(--brand-on-primary)}
    .sct-accent-btn .cc-ico{width:16px;height:16px}
</style>
@endpush

@section('content')
<div class="container-fluid mt-4 mb-5">

    <div class="cc-idx-head">
        <div>
            <div class="cc-idx-eyebrow">@include('componentes._icon', ['name' => 'map-pin']) <span>Locaciones</span></div>
            <h1 class="cc-idx-title">Scouting H&amp;S</h1>
            <p class="cc-idx-sub">Reportes de evaluación de riesgos de locación.</p>
        </div>
        @can('locations.create')
        <a href="{{ route('scoutings.create') }}" class="cc-idx-cta">
            @include('componentes._icon', ['name' => 'plus']) Nuevo Scouting H&amp;S
        </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($reports->count() > 0)
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
        @foreach($reports as $report)
        @php
            // ¿Tiene coordenadas GPS capturadas? (pueden faltar si el scouting se
            // llenó sin red / sin permiso de geolocalización).
            $hasCoords = !is_null($report->latitude) && !is_null($report->longitude);

            // COMPLETO = estatus 'final' Y coordenadas presentes. Todo lo demás
            // lleva glow ámbar + ícono de advertencia para que salte a la vista.
            $isComplete = ($report->status === 'final') && $hasCoords;

            // Etiqueta y color del estatus (Borrador / Revisión / Final).
            $statusMap = [
                'draft'    => ['label' => 'Borrador', 'class' => 'bg-secondary text-white'],
                'revision' => ['label' => 'Revisión', 'class' => 'bg-warning text-dark'],
                'final'    => ['label' => 'Final',    'class' => 'bg-success text-white'],
            ];
            $statusInfo = $statusMap[$report->status] ?? ['label' => ucfirst($report->status), 'class' => 'bg-secondary text-white'];
        @endphp
        <div class="col">
            <div class="card sct-card border-0 rounded-3 h-100 overflow-hidden {{ $isComplete ? '' : 'sct-card-incomplete' }}">

                <div class="sct-cover-wrap">
                    @if($report->scene)
                        <span class="sct-scene-badge badge fs-6 rounded-pill px-3 py-2" title="Escena {{ $report->scene }}">Esc. {{ $report->scene }}</span>
                    @endif

                    <span class="sct-status-badge badge {{ $statusInfo['class'] }}">{{ $statusInfo['label'] }}</span>

                    @if($report->main_image_path)
                        {{-- main_image_path ya es una URL lista (Storage::url → /storage/scouting_images/...), se usa directo. --}}
                        <img src="{{ $report->main_image_path }}" class="sct-cover" alt="Locación {{ $report->location_name }}">
                    @else
                        <div class="sct-cover-placeholder">
                            @include('componentes._icon', ['name' => 'map-pin', 'label' => 'Sin foto de locación'])
                        </div>
                    @endif
                </div>

                <div class="card-body d-flex flex-column">

                    <div class="mb-2">
                        <div class="fw-bold sct-loc-name sct-headline d-flex align-items-center gap-1">
                            @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico', 'label' => null])
                            <span>{{ $report->location_name }}</span>
                        </div>
                        @if($report->location_address)
                            <div class="cc-muted small text-truncate" title="{{ $report->location_address }}">{{ $report->location_address }}</div>
                        @endif
                        @if($report->production_name)
                            <div class="cc-muted small d-flex align-items-center gap-1">
                                @include('componentes._icon', ['name' => 'camera'])<span>{{ $report->production_name }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="mb-3 cc-muted small d-flex flex-wrap align-items-center gap-3">
                        <span class="d-inline-flex align-items-center gap-1">
                            @include('componentes._icon', ['name' => 'calendar'])<span>Creado {{ $report->created_at->format('d/m/Y') }}</span>
                        </span>
                        @if($report->date_shoot)
                            <span class="d-inline-flex align-items-center gap-1">
                                @include('componentes._icon', ['name' => 'clock'])<span>Shoot {{ $report->date_shoot->format('d/m/Y') }}</span>
                            </span>
                        @endif
                    </div>

                    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                        @if($isComplete)
                            <span class="cc-chip cc-chip-ok" title="Reporte completo: estatus final y ubicación GPS capturada">
                                @include('componentes._icon', ['name' => 'check-circle']) Completo
                            </span>
                        @else
                            <span class="cc-chip cc-chip-warn" title="Reporte incompleto: falta pasar a estatus final y/o capturar la ubicación GPS">
                                @include('componentes._icon', ['name' => 'alert-triangle']) Incompleto
                            </span>
                        @endif

                        @if($report->requires_specific_ra)
                            <span class="cc-chip cc-chip-danger" title="Actividades especiales declaradas — requiere Specific Risk Assessment (Cal-OSHA SB132)">
                                @include('componentes._icon', ['name' => 'shield-alert']) SB132: Requiere SRA
                            </span>
                        @endif

                        @if(!$hasCoords)
                            @can('locations.create')
                                <a href="{{ route('scoutings.edit', $report->id) }}" class="cc-chip cc-chip-danger"
                                   title="Este scouting no tiene coordenadas GPS. Edítalo para capturar la ubicación.">
                                    @include('componentes._icon', ['name' => 'map-pin']) Actualizar ubicación
                                </a>
                            @else
                                <span class="cc-chip cc-chip-danger" title="Este scouting no tiene coordenadas GPS.">
                                    @include('componentes._icon', ['name' => 'map-pin']) Actualizar ubicación
                                </span>
                            @endcan
                        @endif
                    </div>

                    <div class="mt-auto">
                        <a href="{{ route('scoutings.show', $report->id) }}" class="btn sct-accent-btn fw-bold w-100">
                            Ver Reporte @include('componentes._icon', ['name' => 'chevron-right'])
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
            @include('componentes._icon', ['name' => 'map-pin', 'label' => 'Sin reportes'])
            <h5>Aún no hay reportes de scouting</h5>
            <p class="small mb-0">
                Comienza evaluando tu primera locación.
                @can('locations.create')
                    <a href="{{ route('scoutings.create') }}">Crea el primero.</a>
                @endcan
            </p>
        </div>
    </div>
    @endif

    <div class="mt-4 d-flex justify-content-center">
        {{ $reports->links() }}
    </div>

</div>
@endsection
