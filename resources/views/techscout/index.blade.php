@extends('layouts.app')
@section('title', 'Tech Scout - ' . ($branding['brand_name'] ?? 'CrewCare'))

@push('styles')
<style>
    .ts-head{display:flex;align-items:flex-end;gap:1.25rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.75rem}
    .ts-eyebrow{font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700;display:inline-flex;align-items:center;gap:.5rem}
    .ts-eyebrow .cc-ico{width:15px;height:15px}
    .ts-title{margin:.35rem 0 .2rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:clamp(1.5rem,2.6vw,2rem);color:var(--text);line-height:1.05}
    .ts-sub{margin:0;color:var(--text-muted);font-size:.9rem}
    .ts-cta{display:inline-flex;align-items:center;gap:.55rem;padding:.7rem 1.15rem;border-radius:14px;text-decoration:none;font-weight:700;font-size:.9rem;background:var(--brand-primary);color:var(--brand-on-primary);border:1px solid var(--brand-primary);box-shadow:0 12px 30px -10px var(--brand-glow)}
    .ts-cta:hover{color:var(--brand-on-primary);filter:brightness(1.04)}
    .ts-cta .cc-ico{width:18px;height:18px}
    .ts-card{display:block;text-decoration:none;color:inherit;transition:transform .2s,border-color .2s}
    .ts-card:hover{transform:translateY(-3px);border-color:var(--stroke-2)}
    .ts-card h3{font-family:'Poppins',sans-serif;font-weight:700;font-size:1rem;margin:0 0 .2rem;color:var(--text)}
    .ts-meta{font-size:.78rem;color:var(--text-muted);display:flex;gap:.9rem;flex-wrap:wrap;align-items:center}
    .ts-meta .cc-ico{width:13px;height:13px;vertical-align:-2px}
    .ts-empty{text-align:center;padding:3.5rem 1.5rem;color:var(--text-muted)}
    .ts-empty .cc-ico{width:46px;height:46px;opacity:.55;margin-bottom:.85rem}
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4 py-4" style="max-width:1100px">

    <div class="ts-head">
        <div>
            <span class="ts-eyebrow">
                @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico'])
                Locaciones
            </span>
            <h1 class="ts-title">Tech Scout</h1>
            {{-- Qué es, en una línea y en el idioma del usuario: no "módulo de notas", sino lo que
                 resuelve. Antes esto se hacía en libreta y se pasaba a Word a mano. --}}
            <p class="ts-sub">El recorrido técnico: la foto y lo que hay que resolver, anotado en el momento.</p>
        </div>
        <a href="{{ route('techscout.create') }}" class="ts-cta">
            @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico'])
            <span>Nuevo recorrido</span>
        </a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if (! count($scouts))
        <div class="card p-4 ts-empty">
            @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico'])
            <h5>Aún no hay recorridos</h5>
            <p class="mb-0">Empieza uno al llegar a la locación: solo necesitas el nombre y la dirección.</p>
        </div>
    @else
        <div class="row g-3">
            @foreach ($scouts as $s)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('techscout.show', $s->id) }}" class="card p-3 h-100 ts-card">
                        <h3>{{ $s->location_name }}</h3>
                        @if ($s->location_address)
                            <p class="ts-sub mb-2" style="font-size:.8rem">{{ $s->location_address }}</p>
                        @endif
                        <div class="ts-meta">
                            <span>@include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico'])
                                {{ $s->notes_count }} {{ $s->notes_count === 1 ? 'nota' : 'notas' }}</span>
                            <span>@include('componentes._icon', ['name' => 'calendar', 'class' => 'cc-ico'])
                                {{ $s->created_at->format('d/m/Y') }}</span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $scouts->links() }}</div>
    @endif

</div>
@endsection
