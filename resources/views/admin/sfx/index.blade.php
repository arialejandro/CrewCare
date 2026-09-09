@extends('layouts.app')
@section('title', 'Panel SFX en vivo')

@feature('sds_sfx')
@push('styles')
<style>
    /* ── Índice glass · lenguaje Cinematic Dark Glass (tokens de _brand-theme) ── */
    .cc-idx-head{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.75rem}
    .cc-idx-headline{display:flex;align-items:center;gap:1rem;min-width:0}
    .cc-idx-icon{width:48px;height:48px;border-radius:14px;flex:none;display:inline-flex;align-items:center;justify-content:center;
        color:var(--brand-primary);background:color-mix(in srgb,var(--brand-primary) 15%,transparent);border:1px solid color-mix(in srgb,var(--brand-primary) 28%,transparent)}
    .cc-idx-icon .cc-ico{width:22px;height:22px}
    .cc-idx-eyebrow{font-size:.66rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700}
    .cc-idx-title{margin:.1rem 0 .1rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:1.5rem;color:var(--text);line-height:1.05}
    .cc-idx-sub{margin:0;color:var(--text-muted);font-size:.86rem}
    .cc-idx-ghost{display:inline-flex;align-items:center;gap:.5rem;padding:.62rem 1.05rem;border-radius:var(--radius-sm);text-decoration:none;font-weight:600;font-size:.88rem;background:var(--glass);color:var(--text);border:1px solid var(--stroke);transition:background .18s,border-color .18s}
    .cc-idx-ghost:hover{background:var(--glass-2);border-color:var(--stroke-2);color:var(--text)}
    .cc-idx-ghost .cc-ico{width:16px;height:16px}

    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.28rem .58rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    /* Variante ámbar para el chip "pendiente de verificación" (misma receta por tokens
       que admin/consumables/index): aquí solo existía .cc-chip-ok. */
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}

    /* Indicador "● Activo" con latido (verde semántico = --ok). */
    .sfx-live-dot{display:inline-block;width:.6rem;height:.6rem;border-radius:50%;background:var(--ok);margin-right:.35rem;vertical-align:middle;animation:sfxPulse 1.4s infinite}
    @keyframes sfxPulse{
        0%   {box-shadow:0 0 0 0 color-mix(in srgb,var(--ok) 55%,transparent);}
        70%  {box-shadow:0 0 0 .5rem color-mix(in srgb,var(--ok) 0%,transparent);}
        100% {box-shadow:0 0 0 0 color-mix(in srgb,var(--ok) 0%,transparent);}
    }
    .sfx-active-card{border-left:4px solid var(--ok) !important}
    .sfx-start-card{border-left:4px solid var(--brand-primary) !important}
    .sfx-label{font-family:'Poppins',sans-serif;font-weight:600;color:var(--text)}
    .sfx-item{background:var(--glass-2);border:1px solid var(--stroke);border-radius:var(--radius-sm)}
    .sfx-item .sfx-name{color:var(--text)}
    .sfx-item .sfx-sub{color:var(--text-muted)}
    .sfx-recent-title{color:var(--text-muted)}
    .sfx-recent li{color:var(--text)}
    .sfx-recent .sfx-recent-time{color:var(--text-muted)}
    .sfx-empty{text-align:center;padding:2.25rem 1rem;color:var(--text-muted)}
    .sfx-empty .cc-ico{width:40px;height:40px;opacity:.55;margin-bottom:.6rem}
    .sfx-empty .fw-semibold{color:var(--text)}

    /* Resumen (KPIs) + estado "ninguno en curso" — para que el panel no se vea vacío. */
    .sfx-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.9rem;margin-bottom:1.5rem}
    .sfx-stat{background:var(--glass);border:1px solid var(--stroke);border-radius:var(--radius-sm);padding:.9rem 1.1rem}
    .sfx-stat__n{font-family:'Poppins',sans-serif;font-weight:800;font-size:1.7rem;line-height:1;color:var(--text)}
    .sfx-stat__l{margin-top:.25rem;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;font-weight:700;color:var(--text-muted)}
    .sfx-stat.is-live{border-color:color-mix(in srgb,var(--ok) 40%,transparent);background:color-mix(in srgb,var(--ok) 8%,var(--glass))}
    .sfx-stat.is-live .sfx-stat__n{color:var(--ok)}
    .sfx-none{display:flex;align-items:center;gap:.5rem;color:var(--text-muted);font-size:.88rem;padding:.6rem .2rem}
    .sfx-dot-idle{display:inline-block;width:.6rem;height:.6rem;border-radius:50%;background:var(--text-muted);opacity:.5;flex:none}
    @media (max-width:560px){.sfx-stats{grid-template-columns:1fr 1fr}}
</style>
@endpush

@section('content')
{{-- Confirmación de submit sin JS inline: el mensaje de «Detener» lleva el effect_label,
     que es texto libre del usuario y no puede viajar dentro de un literal JS. --}}
@include('componentes._confirm-submit')
<div class="container-fluid py-4 px-3 px-md-4">

    <div class="cc-idx-head">
        <div class="cc-idx-headline">
            <span class="cc-idx-icon">@include('componentes._icon', ['name' => 'flame', 'label' => 'SFX'])</span>
            <div>
                <div class="cc-idx-eyebrow">Seguridad · SFX</div>
                <h1 class="cc-idx-title">Panel SFX en vivo</h1>
                <p class="cc-idx-sub">Dispara y detiene efectos especiales; cada transición queda en el DSR del día.</p>
            </div>
        </div>
        @can('sds.view')
            <a href="{{ route('consumables.index') }}" class="cc-idx-ghost">
                @include('componentes._icon', ['name' => 'droplet']) SDS / Consumibles
            </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
            <i class="fa-solid fa-circle-check me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Resumen: el panel SIEMPRE tiene algo que decir, aunque no haya efectos en curso. --}}
    <div class="sfx-stats">
        <div class="sfx-stat {{ $active->count() ? 'is-live' : '' }}">
            <div class="sfx-stat__n">{{ $active->count() }}</div>
            <div class="sfx-stat__l">En curso</div>
        </div>
        <div class="sfx-stat">
            <div class="sfx-stat__n">{{ $closedToday }}</div>
            <div class="sfx-stat__l">Cerrados hoy</div>
        </div>
        <div class="sfx-stat">
            <div class="sfx-stat__n">{{ $totalEver }}</div>
            <div class="sfx-stat__l">Registrados</div>
        </div>
    </div>

    <div class="row g-4">

        {{-- ─────────── Iniciar efecto (form) ─────────── --}}
        <div class="col-12 col-lg-5">
            <div class="card border-0 rounded-3 sfx-start-card h-100">
                <div class="card-body p-4">
                    <h5 class="sfx-label mb-3"><i class="fa-solid fa-play text-primary me-1"></i> Iniciar efecto</h5>

                    <form method="POST" action="{{ route('sfx.start') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="effect_label" class="form-label">Efecto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('effect_label') is-invalid @enderror"
                                   id="effect_label" name="effect_label" value="{{ old('effect_label') }}"
                                   maxlength="255" placeholder="Ej. Humo atmosférico set A" required>
                            @error('effect_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="consumable_id" class="form-label">Consumible / SDS</label>
                            <select class="form-select @error('consumable_id') is-invalid @enderror"
                                    id="consumable_id" name="consumable_id">
                                <option value="">— Sin ligar / no aplica —</option>
                                @foreach($consumables as $consumable)
                                    <option value="{{ $consumable->id }}" {{ (string) old('consumable_id') === (string) $consumable->id ? 'selected' : '' }}>
                                        {{ $consumable->name }} ({{ $consumable->type_label }})@if($consumable->isPendingVerification()) — pendiente de verificación @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('consumable_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if($consumables->isEmpty())
                                <div class="form-text">
                                    No hay consumibles activos.
                                    @can('sds.create')
                                        <a href="{{ route('consumables.create') }}">Crea uno</a> para ligar su SDS.
                                    @endcan
                                </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="production_ref" class="form-label">Referencia de producción</label>
                            <input type="text" class="form-control @error('production_ref') is-invalid @enderror"
                                   id="production_ref" name="production_ref" value="{{ old('production_ref') }}"
                                   maxlength="120" placeholder="Escena / secuencia / toma (opcional)">
                            @error('production_ref')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="safety_criteria" class="form-label">Criterio del Safety</label>
                            <textarea class="form-control @error('safety_criteria') is-invalid @enderror"
                                      id="safety_criteria" name="safety_criteria" rows="3"
                                      placeholder="Texto libre: condiciones exigidas, distancias, EPP, extintor a la mano, personal despejado…">{{ old('safety_criteria') }}</textarea>
                            @error('safety_criteria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Descripción libre del criterio con el que se autoriza el efecto (no hay casillas fijas).</div>
                        </div>

                        <button type="submit" class="btn btn-success fw-semibold w-100">
                            <i class="fa-solid fa-play me-1"></i> Iniciar y registrar en DSR
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ─────────── Columna viva: en curso + bitácora reciente ─────────── --}}
        <div class="col-12 col-lg-7 d-flex flex-column gap-4">

            {{-- Efectos ACTIVOS (toggle) --}}
            <div class="card border-0 rounded-3 sfx-active-card">
                <div class="card-body p-4">
                    <h5 class="sfx-label mb-3">
                        <i class="fa-solid fa-tower-broadcast text-success me-1"></i>
                        Efectos en curso
                        <span class="cc-chip {{ $active->count() ? 'cc-chip-ok' : 'cc-chip-neutral' }} ms-1">{{ $active->count() }}</span>
                    </h5>

                    @forelse($active as $sfx)
                    <div class="sfx-item d-flex flex-wrap align-items-start justify-content-between gap-2 p-3 mb-2">
                        <div class="me-2">
                            <div class="sfx-name fw-semibold">
                                <span class="sfx-live-dot"></span>
                                <span class="text-success small fw-bold text-uppercase me-1">Activo</span>
                                {{ $sfx->effect_label }}
                            </div>
                            <div class="sfx-sub small mt-1">
                                @if($sfx->consumable)
                                    <span class="me-2"><i class="fa-solid fa-flask-vial me-1"></i>{{ $sfx->consumable->name }}</span>
                                    @if($sfx->consumable->isPendingVerification())
                                        <span class="cc-chip cc-chip-warn me-2" title="Ficha capturada en set. Sus datos aún no han sido validados por un responsable de SDS.">
                                            @include('componentes._icon', ['name' => 'alert-triangle']) pendiente de verificación
                                        </span>
                                    @endif
                                @endif
                                @if($sfx->production_ref)
                                    <span class="me-2"><i class="fa-solid fa-clapperboard me-1"></i>{{ $sfx->production_ref }}</span>
                                @endif
                                @if($sfx->started_at)
                                    <span><i class="fa-regular fa-clock me-1"></i>Inicio {{ $sfx->started_at->format('H:i') }}</span>
                                @endif
                            </div>
                            @if($sfx->safety_criteria)
                                <div class="sfx-sub small mt-1"><i class="fa-solid fa-shield-halved me-1"></i>{{ $sfx->safety_criteria }}</div>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('sfx.stop', $sfx->id) }}"
                              data-confirm="¿Detener «{{ $sfx->effect_label }}»?">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm fw-semibold">
                                <i class="fa-solid fa-stop me-1"></i> Detener
                            </button>
                        </form>
                    </div>
                    @empty
                    <div class="sfx-none">
                        <span class="sfx-dot-idle"></span> Ninguno en curso ahora. Inicia uno con el panel de la izquierda.
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Bitácora reciente — SIEMPRE visible: el panel no se ve vacío aunque no haya nada en curso. --}}
            <div class="card border-0 rounded-3">
                <div class="card-body p-4">
                    <h5 class="sfx-label mb-3">
                        <i class="fa-regular fa-rectangle-list text-secondary me-1"></i>
                        Bitácora reciente
                        @if($recent->count())<span class="cc-chip cc-chip-neutral ms-1">{{ $recent->count() }}</span>@endif
                    </h5>

                    @if($recent->count())
                        <ul class="list-unstyled mb-0 sfx-recent">
                            @foreach($recent as $sfx)
                            <li class="d-flex justify-content-between align-items-center py-1 small">
                                <span class="text-truncate me-2">
                                    <i class="fa-solid fa-check me-1"></i>{{ $sfx->effect_label }}
                                    @if($sfx->consumable)<span class="sfx-recent-time">· {{ $sfx->consumable->name }}</span>@endif
                                    @if($sfx->consumable && $sfx->consumable->isPendingVerification())
                                        <span class="cc-chip cc-chip-warn ms-1" title="Ficha capturada en set. Sus datos aún no han sido validados por un responsable de SDS.">
                                            @include('componentes._icon', ['name' => 'alert-triangle']) pendiente de verificación
                                        </span>
                                    @endif
                                </span>
                                <span class="sfx-recent-time text-nowrap">
                                    @if($sfx->started_at){{ $sfx->started_at->format('H:i') }}@endif
                                    @if($sfx->ended_at) → {{ $sfx->ended_at->format('H:i') }}@endif
                                </span>
                            </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="sfx-empty">
                            @include('componentes._icon', ['name' => 'flame', 'label' => 'Sin registros todavía'])
                            <div class="fw-semibold">Aún no se ha disparado ningún efecto.</div>
                            <small>Cuando inicies y detengas efectos, aparecerán aquí y en el DSR del día.</small>
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
@endfeature
