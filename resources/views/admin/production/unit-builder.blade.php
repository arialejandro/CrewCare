@extends('layouts.app')

{{-- ============================================================================
     CONSTRUCTOR de una unidad adicional (Unidades 2c). Comparación de CrewList por
     departamento; por persona un control de 3 estados. Nadie se registra de nuevo —
     sólo se marca la presencia (pivote unit_members). CSP-safe: <form> + radios,
     estados por CSS :checked, sin <script> ni on*=.
============================================================================ --}}

@section('content')
@php
    $hintSet = array_map('mb_strtolower', $sharedHint ?? []);
    $isHint  = fn ($label) => in_array(mb_strtolower(trim((string) $label)), $hintSet, true);
@endphp

<div class="cc-ub" style="max-width:1040px;margin:0 auto;padding:18px 14px 96px;">

    <div class="cc-ub-head">
        <div>
            <h1 class="cc-ub-h1">Constructor · <span class="cc-ub-unit">{{ $unit->name }}</span></h1>
            <p class="cc-ub-sub">Marca quién trabaja en esta unidad. <strong>Nadie se registra de nuevo</strong> — sólo se marca su presencia. Lo normal es que el crew sea <em>exclusivo</em> de su unidad; los que se comparten (jefaturas de Arte, Construcción, Decoración, Locaciones) se marcan <strong>Ambas</strong>.</p>
        </div>
        <a class="cc-ub-back" href="{{ route('production.units.index') }}">← Unidades</a>
    </div>

    <div class="cc-ub-summary">
        <span class="cc-ub-kpi"><strong>{{ $enUnit }}</strong> en esta unidad</span>
        <span class="cc-ub-kpi cc-ub-kpi--shared"><strong>{{ $enBoth }}</strong> compartidas (en las dos)</span>
    </div>

    @if(session('success'))
        <div class="cc-ub-flash">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('production.units.builder.save', $unit->id) }}">
        @csrf

        @forelse($groups as $g)
            <section class="cc-ub-dept">
                <h2 class="cc-ub-dept-h">
                    {{ $g['label'] }}
                    @if($isHint($g['label']))<span class="cc-ub-hint">suele compartirse</span>@endif
                    <span class="cc-ub-count">{{ count($g['people']) }}</span>
                </h2>

                @foreach($g['people'] as $p)
                    @php $st = $states[$p['id']] ?? 'principal'; @endphp
                    <div class="cc-ub-row cc-ub-row--{{ $st }}">
                        <div class="cc-ub-who">
                            <span class="cc-ub-name">{{ $p['name'] }}</span>
                            @if(!empty($p['cargo']))<span class="cc-ub-cargo">{{ $p['cargo'] }}</span>@endif
                        </div>
                        <div class="cc-ub-seg" role="group" aria-label="Unidad de {{ $p['name'] }}">
                            <label class="cc-ub-opt">
                                <input type="radio" name="m[{{ $p['id'] }}]" value="principal" @checked($st === 'principal')>
                                <span>Principal</span>
                            </label>
                            <label class="cc-ub-opt cc-ub-opt--solo">
                                <input type="radio" name="m[{{ $p['id'] }}]" value="solo" @checked($st === 'solo')>
                                <span>Solo aquí</span>
                            </label>
                            <label class="cc-ub-opt cc-ub-opt--ambas">
                                <input type="radio" name="m[{{ $p['id'] }}]" value="ambas" @checked($st === 'ambas')>
                                <span>Ambas</span>
                            </label>
                        </div>
                    </div>
                @endforeach
            </section>
        @empty
            <div class="cc-ub-empty">No hay crew listado todavía. Da de alta al equipo por el flujo normal — aquí sólo se marca en qué unidad trabaja.</div>
        @endforelse

        <div class="cc-ub-bar">
            <span class="cc-ub-bar-note">El default es <strong>Principal</strong>: sólo tocas a quien entra a esta unidad.</span>
            <button type="submit" class="cc-ub-save">Guardar asignación</button>
        </div>
    </form>
</div>

<style>
    .cc-ub-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:.6rem; }
    .cc-ub-h1 { font-size:1.5rem; font-weight:800; margin:0 0 .25rem; color:var(--text); }
    .cc-ub-unit { color:var(--brand-primary); }
    .cc-ub-sub { color:var(--text-muted); max-width:70ch; line-height:1.5; margin:0; font-size:.92rem; }
    .cc-ub-back { flex:none; text-decoration:none; color:var(--text); border:1px solid var(--stroke); background:var(--glass); border-radius:10px; padding:.45rem .8rem; font-size:.85rem; }
    .cc-ub-back:hover { border-color:var(--brand-primary); }

    .cc-ub-summary { display:flex; gap:.6rem; flex-wrap:wrap; margin:.8rem 0 1rem; }
    .cc-ub-kpi { font-size:.9rem; color:var(--text); background:var(--glass); border:1px solid var(--stroke); border-radius:999px; padding:.35rem .85rem; }
    .cc-ub-kpi strong { font-size:1.05rem; }
    .cc-ub-kpi--shared { background:color-mix(in srgb, #f59e0b 18%, transparent); border-color:#f59e0b; }

    .cc-ub-flash { background:color-mix(in srgb, var(--brand-primary) 14%, transparent); border:1px solid var(--brand-primary); color:var(--text); border-radius:10px; padding:.6rem .9rem; margin-bottom:1rem; font-size:.9rem; }
    .cc-ub-empty { color:var(--text-muted); border:1px dashed var(--stroke); border-radius:12px; padding:2rem; text-align:center; }

    .cc-ub-dept { margin-bottom:1.1rem; border:1px solid var(--stroke); border-radius:14px; overflow:hidden; background:var(--glass); }
    .cc-ub-dept-h { display:flex; align-items:center; gap:.5rem; margin:0; padding:.6rem .9rem; font-size:.82rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text); background:color-mix(in srgb, var(--brand-secondary) 10%, transparent); border-bottom:1px solid var(--stroke); }
    .cc-ub-hint { font-size:.66rem; font-weight:700; text-transform:none; letter-spacing:0; color:#b45309; background:color-mix(in srgb, #f59e0b 22%, transparent); border-radius:999px; padding:.12rem .5rem; }
    .cc-ub-count { margin-left:auto; font-size:.78rem; color:var(--text-muted); font-weight:600; }

    .cc-ub-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.5rem .9rem; border-top:1px solid color-mix(in srgb, var(--stroke) 55%, transparent); }
    .cc-ub-row:first-of-type { border-top:0; }
    .cc-ub-row--ambas { background:color-mix(in srgb, #f59e0b 12%, transparent); }
    .cc-ub-row--solo  { background:color-mix(in srgb, var(--brand-primary) 8%, transparent); }
    .cc-ub-who { display:flex; flex-direction:column; min-width:0; }
    .cc-ub-name { font-weight:600; color:var(--text); }
    .cc-ub-cargo { font-size:.78rem; color:var(--text-muted); }

    /* Segmented control CSP-safe: el radio real se oculta; el <span> es el botón; :checked lo resalta. */
    .cc-ub-seg { display:inline-flex; flex:none; border:1px solid var(--stroke); border-radius:999px; overflow:hidden; background:var(--bg); }
    .cc-ub-opt { position:relative; margin:0; }
    .cc-ub-opt input { position:absolute; opacity:0; width:0; height:0; }
    .cc-ub-opt span { display:inline-block; padding:.34rem .8rem; font-size:.8rem; font-weight:600; color:var(--text-muted); cursor:pointer; white-space:nowrap; user-select:none; border-left:1px solid var(--stroke); }
    .cc-ub-opt:first-child span { border-left:0; }
    .cc-ub-opt input:hover + span, .cc-ub-opt input:focus-visible + span { color:var(--text); background:color-mix(in srgb, var(--stroke) 40%, transparent); }
    .cc-ub-opt input:focus-visible + span { outline:2px solid var(--brand-primary); outline-offset:-2px; }
    /* Estado seleccionado */
    .cc-ub-opt input:checked + span { color:#fff; background:var(--brand-secondary); }
    .cc-ub-opt--solo  input:checked + span { background:var(--brand-primary); }
    .cc-ub-opt--ambas input:checked + span { background:#f59e0b; color:#1f2937; }

    .cc-ub-bar { position:sticky; bottom:0; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-top:1rem; padding:.7rem .9rem; background:color-mix(in srgb, var(--bg) 92%, transparent); backdrop-filter:blur(8px); border:1px solid var(--stroke); border-radius:14px; }
    .cc-ub-bar-note { font-size:.82rem; color:var(--text-muted); }
    .cc-ub-save { flex:none; cursor:pointer; font-weight:700; font-size:.9rem; color:#fff; background:var(--brand-primary); border:1px solid var(--brand-primary); border-radius:10px; padding:.55rem 1.2rem; }
    .cc-ub-save:hover { filter:brightness(1.06); }

    @media (max-width:640px){
        .cc-ub-row { flex-direction:column; align-items:stretch; gap:.4rem; }
        .cc-ub-seg { align-self:flex-start; }
    }
</style>
@endsection
