@extends('layouts.app')
@section('content')

@push('styles')
<style>
    .cal-wrap { max-width: 760px; }
    .adm-header { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .adm-icon { width:48px; height:48px; border-radius:14px; flex:none; display:inline-flex; align-items:center; justify-content:center;
        color:var(--brand-primary); background:color-mix(in srgb, var(--brand-primary) 16%, transparent); border:1px solid var(--stroke); }
    .adm-icon .cc-ico { width:22px; height:22px; }
    .adm-title { font-family:'Poppins',sans-serif; font-weight:600; letter-spacing:-.02em; margin:0; color:var(--text); font-size:1.35rem; }
    .adm-subtitle { color:var(--text-muted); font-size:.9rem; margin:.15rem 0 0; }
    .cal-card .card-header { background:transparent; color:var(--text); font-weight:600; border-bottom:1px solid var(--stroke); border-radius:var(--radius) var(--radius) 0 0; }
    .cal-wrap .form-control { background-color:var(--glass); border:1px solid var(--stroke); color:var(--text); }
    .cal-wrap .form-text { color:var(--text-muted); }

    .unit-row { display:grid; grid-template-columns: 1fr 90px auto auto; gap:.5rem; align-items:center; border:1px solid var(--stroke); border-radius:10px; padding:.5rem .7rem; margin-bottom:.5rem; background:var(--glass); }
    .unit-row.off { opacity:.55; }
    .unit-row.principal { background:color-mix(in srgb, var(--brand-primary) 10%, var(--bg-2)); }
    .unit-name { font-weight:600; color:var(--text); }
    .unit-inline { display:flex; gap:.35rem; align-items:center; margin:0; }
    .unit-badge { font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; border-radius:6px; padding:.1rem .45rem; }
    .unit-badge.on { color:#16a34a; background:color-mix(in srgb,#16a34a 14%,transparent); }
    .unit-badge.paused { color:var(--text-muted); background:var(--glass); }
    @media (max-width:575px){ .unit-row { grid-template-columns:1fr; } }
</style>
@endpush

<div class="container py-4 cal-wrap">

    <div class="adm-header mb-4">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'folder', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Unidades</h1>
            <p class="adm-subtitle">La unidad principal siempre existe. Aquí das de alta la segunda unidad y las siguientes.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if(! $prod)
        <div class="alert alert-warning">No hay producción vigente que configurar.</div>
    @else

    <div class="card cal-card mb-4">
        <div class="card-body">
            {{-- Unidad principal: no es una fila; siempre está. --}}
            <div class="unit-row principal">
                <span class="unit-name">{{ \App\Models\Unit::PRINCIPAL_LABEL }}</span>
                <span class="form-text">unidad 1</span>
                <span class="unit-badge on">Siempre</span>
                <span class="form-text">Todo lo existente vive aquí</span>
            </div>

            @foreach($units as $u)
                @php $m = $meta[$u->id] ?? ['off' => 0, 'docs' => 0]; @endphp
                <div class="unit-row {{ $u->is_active ? '' : 'off' }}">
                    <form action="{{ route('production.units.update', $u->id) }}" method="POST" class="unit-inline" style="flex:1;">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $u->name }}" class="form-control form-control-sm" maxlength="120" required>
                        <input type="hidden" name="sort_order" value="{{ $u->sort_order }}">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Guardar</button>
                    </form>
                    <span class="form-text">orden {{ $u->sort_order }}</span>
                    {{-- 2c · Constructor: armar quién trabaja en esta unidad (pivote). --}}
                    <a href="{{ route('production.units.builder', $u->id) }}" class="btn btn-sm btn-outline-primary">Constructor →</a>
                    <span class="unit-badge {{ $u->is_active ? 'on' : 'paused' }}">{{ $u->is_active ? 'Activa' : 'Inactiva' }}</span>
                    {{-- Desactivar AVISA antes, con números (CSP-safe: el mensaje viaja en data-confirm, sin on*=).
                         Reactivar no pregunta: sólo devuelve lo que se había apagado. --}}
                    <form action="{{ route('production.units.toggle', $u->id) }}" method="POST" class="unit-inline"
                        @if($u->is_active) data-confirm="Vas a desactivar «{{ $u->name }}».&#10;&#10;· Se apagarán {{ $m['off'] }} persona(s) exclusiva(s) de esta unidad. Los compartidos (Ambas) siguen activos en la principal.&#10;· Sus {{ $m['docs'] }} documento(s) sellado(s) se conservan intactos.&#10;· Todo vuelve si la reactivas.&#10;&#10;¿Continuar?" @endif>
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $u->is_active ? 'Desactivar' : 'Activar' }}</button>
                    </form>
                </div>
            @endforeach

            {{-- Red de UI del aviso previo a desactivar (idempotente, @once). --}}
            @include('componentes._confirm-submit')

            <form action="{{ route('production.units.store') }}" method="POST" class="d-flex gap-2 mt-3">
                @csrf
                <input type="text" name="name" class="form-control" placeholder="Nombre de la nueva unidad (ej. Segunda unidad)" maxlength="120" required>
                <button type="submit" class="btn btn-primary text-nowrap">
                    @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico me-1', 'label' => null]) Agregar
                </button>
            </form>

            <p class="form-text mt-3 mb-0">
                Dar de alta una unidad NO mueve nada de lo existente: todo sigue en la principal.
                La baja es por <strong>desactivación</strong>, nunca borrado — los documentos ya sellados de una unidad conservan su unidad.
            </p>
        </div>
    </div>

    <a href="{{ route('production.shootdays.edit') }}" class="btn btn-outline-secondary btn-sm">← Días de rodaje</a>

    @endif
</div>

@endsection
