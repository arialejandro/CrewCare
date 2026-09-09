@extends('layouts.app')
@section('title', 'Mapeo de riesgos y recursos - ' . ($branding['brand_name'] ?? 'CrewCare'))
@include('componentes._confirm-submit')

@push('styles')
<style>
    .rm-new{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;background:var(--glass-2);border:1px solid var(--stroke);border-radius:14px;padding:.75rem .9rem;margin-bottom:1.5rem}
    .rm-new select{flex:1 1 240px;min-width:200px;background:var(--surface-3);color:var(--text);border:1px solid var(--stroke);border-radius:10px;padding:.55rem .7rem;font:inherit}
    .rm-list{display:grid;gap:.75rem}
    .rm-row{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;padding:1rem 1.15rem}
    .rm-row__main{flex:1 1 300px;min-width:0}
    .rm-row__title{font-family:'Poppins',sans-serif;font-weight:700;color:var(--text);margin:0;line-height:1.2}
    .rm-row__loc{color:var(--text-muted);font-size:.85rem;margin:.15rem 0 0;display:inline-flex;align-items:center;gap:.35rem}
    .rm-row__loc .cc-ico{width:14px;height:14px}
    .rm-row__meta{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
    .rm-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem .8rem;border-radius:10px;text-decoration:none;font-weight:600;font-size:.85rem;border:1px solid var(--stroke);color:var(--text);background:var(--surface-3)}
    .rm-btn .cc-ico{width:15px;height:15px}
    .rm-btn--accent{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary)}
    .rm-btn--danger{color:var(--danger);border-color:color-mix(in srgb,var(--danger) 34%,transparent)}
</style>
@endpush

@section('content')
<div class="container-fluid mt-4 mb-5">

    <div class="cc-idx-head">
        <div>
            <div class="cc-idx-eyebrow">@include('componentes._icon', ['name' => 'shield-alert']) <span>Seguridad</span></div>
            <h1 class="cc-idx-title">Mapeo de riesgos y recursos</h1>
            <p class="cc-idx-sub">Documento por locación: dónde están los peligros y los recursos de emergencia.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Nuevo mapeo: elegir el scouting de origen --}}
    <form action="{{ route('riskmaps.store') }}" method="POST" class="rm-new">
        @csrf
        <label for="rm-scouting" class="visually-hidden">Scouting</label>
        <select name="scouting_id" id="rm-scouting" required>
            <option value="">Elige un scouting…</option>
            @foreach($scoutings as $s)
                <option value="{{ $s->id }}">{{ $s->location_name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rm-btn rm-btn--accent">
            @include('componentes._icon', ['name' => 'plus']) Nuevo mapeo
        </button>
    </form>

    @if($rows->isEmpty())
        <div class="cc-idx-empty">
            @include('componentes._icon', ['name' => 'map'])
            <h5>Aún no hay mapeos</h5>
        </div>
    @else
        <div class="rm-list">
            @foreach($rows as $r)
                <div class="card rm-row">
                    <div class="rm-row__main">
                        <p class="rm-row__title">{{ $r['title'] }}</p>
                        <p class="rm-row__loc">@include('componentes._icon', ['name' => 'map-pin']) {{ $r['location'] }}</p>
                    </div>
                    <div class="rm-row__meta">
                        @if($r['sealed'])
                            <span class="cc-chip cc-chip-ok">@include('componentes._icon', ['name' => 'shield-check']) {{ $r['folio'] }}</span>
                        @else
                            <span class="cc-chip cc-chip-warn">Borrador</span>
                        @endif
                        <span class="cc-chip cc-chip-neutral">{{ $r['views'] }} {{ $r['views'] == 1 ? 'vista' : 'vistas' }}</span>
                    </div>
                    <div class="rm-row__meta">
                        @if($r['sealed'])
                            <a href="{{ route('riskmaps.document', $r['id']) }}" class="rm-btn rm-btn--accent">@include('componentes._icon', ['name' => 'file-text']) Documento</a>
                        @else
                            <a href="{{ route('riskmaps.edit', $r['id']) }}" class="rm-btn rm-btn--accent">@include('componentes._icon', ['name' => 'pencil']) Editar</a>
                            <a href="{{ route('riskmaps.document', $r['id']) }}" class="rm-btn">@include('componentes._icon', ['name' => 'eye']) Vista previa</a>
                            <form action="{{ route('riskmaps.destroy', $r['id']) }}" method="POST" data-confirm="¿Eliminar este mapeo?" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="rm-btn rm-btn--danger">@include('componentes._icon', ['name' => 'trash-2']) Eliminar</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
