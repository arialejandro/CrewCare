@extends('layouts.app')
@section('content')

@php
    // Keys consideradas "de futuro / gran escala": apagadas por defecto y separadas
    // visualmente del resto. Se etiquetan aparte para orientar a Producción.
    $futureKeys = ['aerial_mapping', 'location_handover'];
@endphp

@push('styles')
<style>
    /* ===== Feature Flags · "Cinematic Dark Glass" ===== */
    .ff-wrap { max-width: 820px; }

    .adm-header { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .adm-icon {
        width:48px; height:48px; border-radius:14px; flex:none;
        display:inline-flex; align-items:center; justify-content:center;
        color:var(--brand-primary);
        background:color-mix(in srgb, var(--brand-primary) 16%, transparent);
        border:1px solid var(--stroke);
    }
    .adm-icon .cc-ico { width:22px; height:22px; }
    .adm-title { font-family:'Poppins',sans-serif; font-weight:600; letter-spacing:-.02em; margin:0; color:var(--text); font-size:1.35rem; }
    .adm-subtitle { color:var(--text-muted); font-size:.9rem; margin:.15rem 0 0; }

    /* Cabecera de tarjeta homologada al vidrio (antes navy horneado). */
    .ff-card .card-header {
        background:transparent; color:var(--text); font-weight:600;
        letter-spacing:.01em; border-bottom:1px solid var(--stroke);
        border-radius:var(--radius) var(--radius) 0 0;
    }
    .ff-row {
        display:flex; align-items:center; justify-content:space-between; gap:1rem;
        padding:.9rem 0; border-bottom:1px solid var(--stroke);
    }
    .ff-row:last-child { border-bottom:0; }
    .ff-label { font-weight:600; color:var(--text); margin:0; }
    .ff-key { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:.72rem; color:var(--text-muted); }
    .ff-state { font-size:.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .ff-state.on { color:var(--ok); }
    .ff-state.off { color:var(--text-muted); }
    /* Switch un poco más grande para dedo/tablet */
    .ff-switch.form-check.form-switch { padding-left:3.2em; min-height:1.6rem; }
    .ff-switch .form-check-input { width:2.6em; height:1.4em; cursor:pointer; background-color:color-mix(in srgb, var(--text-muted) 30%, transparent); border-color:var(--stroke-2); }
    .ff-future-note {
        border:1px solid var(--stroke); border-left:3px solid var(--warn);
        border-radius:12px; padding:.85rem 1.05rem; color:var(--text); font-size:.88rem;
        background:color-mix(in srgb, var(--warn) 8%, var(--glass));
    }
    .ff-future-note strong { color:var(--text); }
</style>
@endpush

<div class="container py-4 ff-wrap">

    <div class="adm-header mb-4">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'activity', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Módulos (Feature Flags)</h1>
            <p class="adm-subtitle">Enciende o apaga módulos según la logística del proyecto (super-admin).</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('features.update') }}" method="POST">
        @csrf

        {{-- Módulos de plataforma (siempre útiles) --}}
        <div class="card ff-card mb-4">
            <div class="card-header">Módulos de plataforma</div>
            <div class="card-body">
                @php $anyPlatform = false; @endphp
                @foreach($flags as $key => $on)
                    @if(! in_array($key, $futureKeys, true))
                        @php $anyPlatform = true; @endphp
                        <div class="ff-row">
                            <div>
                                <p class="ff-label">{{ $labels[$key] ?? $key }}</p>
                                <span class="ff-key">{{ $key }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="ff-state {{ $on ? 'on' : 'off' }}">{{ $on ? 'Activo' : 'Apagado' }}</span>
                                <div class="form-check form-switch ff-switch m-0">
                                    <input class="form-check-input" type="checkbox"
                                           id="flag_{{ $key }}" name="flag_{{ $key }}"
                                           value="1" {{ $on ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
                @unless($anyPlatform)
                    <p class="cc-muted small mb-0">No hay módulos de plataforma configurados.</p>
                @endunless
            </div>
        </div>

        {{-- Módulos de futuro / gran escala --}}
        <div class="card ff-card mb-4">
            <div class="card-header">Módulos de futuro (gran escala)</div>
            <div class="card-body">
                <div class="ff-future-note mb-3">
                    Estos módulos vienen <strong>apagados por defecto</strong> y se mantienen ocultos
                    en la app hasta que los enciendas aquí. Son funciones de gran escala, pensadas
                    para producciones grandes que las requieran.
                </div>
                @foreach($flags as $key => $on)
                    @if(in_array($key, $futureKeys, true))
                        <div class="ff-row">
                            <div>
                                <p class="ff-label">{{ $labels[$key] ?? $key }}</p>
                                <span class="ff-key">{{ $key }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="ff-state {{ $on ? 'on' : 'off' }}">{{ $on ? 'Activo' : 'Apagado' }}</span>
                                <div class="form-check form-switch ff-switch m-0">
                                    <input class="form-check-input" type="checkbox"
                                           id="flag_{{ $key }}" name="flag_{{ $key }}"
                                           value="1" {{ $on ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary px-4">
                @include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico me-1', 'label' => null]) Guardar cambios
            </button>
        </div>
    </form>

</div>

@endsection
