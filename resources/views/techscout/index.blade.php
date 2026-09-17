{{-- ============================================================================================
     TECH SCOUT — LISTADO. Deliberadamente con la MISMA estética que el índice de Scouting H&S:
     misma cabecera, mismas tarjetas de vidrio con portada, mismos chips. Son documentos hermanos
     del mismo departamento; si cada uno inventara su propio aspecto, la app se sentiría como
     varias apps pegadas. Pedido explícito del owner.
============================================================================================ --}}
@extends('layouts.app')
@section('title', 'Tech Scout - ' . ($branding['brand_name'] ?? 'CrewCare'))

@push('styles')
@include('componentes._crew-list-styles')
<style>
    /* Mismas clases y medidas que admin/scoutings/index para que las dos rejillas se vean iguales. */
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
    .cc-chip-neutral{color:var(--text-muted);background:var(--glass-2);border-color:var(--stroke)}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}

    /* TELÉFONO. El owner trabaja en iPad; los scouters traen sólo el móvil. Ahí la cabecera
       ocupaba media pantalla antes de la primera tarjeta, y el botón de alta quedaba a un lado,
       estrecho. Se compacta el titular y el botón se va a todo el ancho: es la acción con la que
       se llega a esta pantalla. */
    @media (max-width:575.98px){
        .cc-idx-head{gap:.6rem;margin-bottom:1.1rem}
        .cc-idx-title{font-size:1.35rem;margin:.2rem 0 .1rem}
        .cc-idx-sub{font-size:.82rem}
        .cc-idx-cta{width:100%;justify-content:center}
        .sct-cover,.sct-cover-placeholder{height:150px}
    }

    .cc-idx-empty{text-align:center;padding:3.5rem 1.5rem;color:var(--text-muted)}
    .cc-idx-empty .cc-ico{width:46px;height:46px;color:var(--text-muted);opacity:.55;margin-bottom:.85rem}
    .cc-idx-empty h5{font-family:'Poppins',sans-serif;font-weight:700;color:var(--text)}

    .sct-card{transition:transform .2s var(--ease,cubic-bezier(.16,1,.3,1)),box-shadow .2s,border-color .2s}
    .sct-card:hover{transform:translateY(-4px);border-color:var(--stroke-2);box-shadow:0 16px 34px -14px rgba(0,0,0,.5),var(--shadow) !important}
    .sct-cover-wrap{position:relative}
    .sct-cover{object-fit:cover;height:180px;width:100%;display:block}
    .sct-cover-placeholder{height:180px;display:flex;align-items:center;justify-content:center;background:var(--glass-2);color:var(--text-muted)}
    .sct-cover-placeholder .cc-ico{width:38px;height:38px;opacity:.5}
    .sct-notes-badge{position:absolute;top:.6rem;right:.6rem;z-index:2}
    .sct-loc-name{font-size:1rem;color:var(--text)}
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4 py-4" style="max-width:1200px">

    <div class="cc-idx-head">
        <div>
            <span class="cc-idx-eyebrow">
                @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico'])
                Locaciones
            </span>
            <h1 class="cc-idx-title">Tech Scout</h1>
            {{-- Lo que resuelve, no lo que es: antes esto se anotaba en libreta y se pasaba a Word. --}}
            <p class="cc-idx-sub">El scouting técnico de locaciones: la foto y lo que hay que resolver, anotado en el momento.</p>
        </div>
        <a href="{{ route('techscout.create') }}" class="cc-idx-cta">
            @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico'])
            <span>Nuevo Tech Scout</span>
        </a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if (! count($scouts))
        <div class="card p-4 cc-idx-empty">
            @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico'])
            <h5>Aún no hay Tech Scouts</h5>
            <p class="mb-0">Empieza uno al llegar a la locación.</p>
        </div>
    @else
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            @foreach ($scouts as $s)
                <div class="col">
                    <div class="card sct-card border-0 rounded-3 h-100 overflow-hidden">

                        <div class="sct-cover-wrap">
                            <span class="sct-notes-badge cc-chip {{ $s->notes_count ? 'cc-chip-ok' : 'cc-chip-neutral' }}">
                                @include('componentes._icon', ['name' => 'camera'])
                                {{ $s->notes_count }} {{ $s->notes_count === 1 ? 'nota' : 'notas' }}
                            </span>

                            @if ($s->hero_image_path)
                                <img src="{{ $s->hero_image_path }}" class="sct-cover" alt="Locación {{ $s->location_name }}">
                            @else
                                <div class="sct-cover-placeholder">
                                    @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico', 'label' => 'Sin portada'])
                                </div>
                            @endif
                        </div>

                        <div class="card-body d-flex flex-column">
                            <div class="mb-2">
                                <div class="fw-bold sct-loc-name d-flex align-items-center gap-1">
                                    @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico', 'label' => null])
                                    <span>{{ $s->location_name }}</span>
                                </div>
                                @if ($s->location_address)
                                    <div class="cc-muted small text-truncate" title="{{ $s->location_address }}">{{ $s->location_address }}</div>
                                @endif
                            </div>

                            <div class="mb-3 cc-muted small d-flex flex-wrap align-items-center gap-3">
                                <span class="d-inline-flex align-items-center gap-1">
                                    @include('componentes._icon', ['name' => 'calendar'])<span>Creado {{ $s->created_at->format('d/m/Y') }}</span>
                                </span>
                                @if ($s->date_shoot)
                                    <span class="d-inline-flex align-items-center gap-1">
                                        @include('componentes._icon', ['name' => 'clock'])<span>Shoot {{ $s->date_shoot->format('d/m/Y') }}@if($s->hasShootRange()) – {{ $s->date_shoot_end->format('d/m/Y') }}@endif</span>
                                    </span>
                                @endif
                            </div>

                            <div class="mt-auto d-flex gap-2">
                                <a href="{{ route('techscout.show', $s->id) }}" class="btn btn-crew-accent btn-sm">Abrir</a>
                                <a href="{{ route('techscout.document', $s->id) }}" class="btn btn-crew-soft btn-sm">Documento</a>
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $scouts->links() }}</div>
    @endif

</div>
@endsection
