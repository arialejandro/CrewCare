@extends('layouts.app')

{{-- ============================================================================
     EMISIÓN del póster MEDEVAC. Muestra en SOLO-LECTURA lo que se congelará del
     scouting y deja EDITAR los 3 contactos de emergencia (pre-llenados desde el
     crew; donde no hay, el emisor los escribe). Al emitir se congela y se sella.
     NO inventa: un dato vacío se marca como faltante, no se rellena.
============================================================================ --}}

@section('content')
@include('componentes._form-kit')

@php
    $s = $scouting;
    $en = app()->getLocale() === 'en';
    $hasGps  = $s->latitude !== null && $s->latitude !== '' && $s->longitude !== null && $s->longitude !== '';
    $mapsLoc = $hasGps ? 'https://www.google.com/maps?q=' . $s->latitude . ',' . $s->longitude : null;
    $dist    = trim((string) ($s->hospital_distance_km ?? ''));
    // Campos clave para un póster útil; su ausencia se avisa (no se inventa).
    $faltan = [];
    if (trim((string) $s->nearest_hospital) === '') { $faltan[] = 'hospital'; }
    if (! $hasGps) { $faltan[] = 'coordenadas GPS (mapa)'; }
    if (trim((string) $s->assembly_point) === '') { $faltan[] = 'punto de reunión'; }
@endphp

<div class="cc-medevac container-fluid" style="max-width:980px;margin:0 auto;padding:18px 14px 60px;">

    <div class="mdv-head">
        <div>
            <div class="mdv-eyebrow">@include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico-18']) MEDEVAC · {{ $en ? 'Emergency activation poster' : 'Póster de activación de emergencias' }}</div>
            <h1 class="mdv-title">{{ $s->location_name ?: ($en ? 'Untitled location' : 'Locación sin nombre') }}</h1>
            <div class="mdv-sub">
                {{ $en ? 'Revision' : 'Revisión' }} <strong>{{ $revision }}</strong>
                <span class="cc-muted">· {{ $en ? 'consecutive per location' : 'consecutivo por locación' }}</span>
            </div>
        </div>
        <a class="cc-btn-ghost" href="{{ route('scoutings.show', $s->id) }}">@include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico-16']) {{ $en ? 'Back to scouting' : 'Volver al scouting' }}</a>
    </div>

    @if($errors->any())
        <div class="mdv-alert bad">
            <strong>{{ $en ? 'Check these fields:' : 'Revisa estos campos:' }}</strong>
            <ul style="margin:.3rem 0 0;padding-left:1.1rem;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($faltan)
        <div class="mdv-alert warn">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-16'])
            {{ $en ? 'Missing on the scouting (the poster will omit these blocks, it will not fill them in):' : 'Faltan en el scouting (el póster OMITIRÁ esos bloques, no los inventa):' }}
            <strong>{{ implode(', ', $faltan) }}</strong>.
            <a href="{{ route('scoutings.edit', $s->id) }}">{{ $en ? 'Complete the scouting' : 'Completar el scouting' }}</a>
        </div>
    @endif

    {{-- 1 · LO QUE SE CONGELARÁ (solo lectura, del scouting) --}}
    <div class="cc-form-card">
        <h2 class="mdv-h">{{ $en ? 'What will be frozen' : 'Lo que se congelará' }}</h2>
        <p class="cc-muted" style="margin:-4px 0 14px;">
            {{ $en ? 'Read from the scouting at emission. If the scouting changes later, the poster keeps saying this.' : 'Se lee del scouting al emitir. Si el scouting cambia después, el póster seguirá diciendo esto.' }}
        </p>
        <div class="mdv-grid">
            <div class="mdv-cell"><span class="mdv-k">{{ $en ? 'Location' : 'Locación' }}</span><span class="mdv-v">{{ $s->location_name ?: '—' }}</span></div>
            <div class="mdv-cell"><span class="mdv-k">{{ $en ? 'Distance' : 'Distancia' }}</span><span class="mdv-v">{{ $dist !== '' ? ($dist . ' km') : '—' }}</span></div>
            <div class="mdv-cell"><span class="mdv-k">{{ $en ? 'Time (ETA)' : 'Tiempo (ETA)' }}</span><span class="mdv-v">{{ $s->hospital_eta ?: '—' }}</span></div>
            <div class="mdv-cell mdv-wide"><span class="mdv-k">{{ $en ? 'Address' : 'Dirección' }}</span><span class="mdv-v">{{ $s->location_address ?: '—' }}</span></div>
            <div class="mdv-cell mdv-wide"><span class="mdv-k">Hospital</span><span class="mdv-v">{{ $s->nearest_hospital ?: '—' }}@if($s->hospital_address) <span class="cc-muted">— {{ $s->hospital_address }}</span>@endif</span></div>
            <div class="mdv-cell"><span class="mdv-k">{{ $en ? 'Assembly point' : 'Punto de reunión' }}</span><span class="mdv-v">{{ $s->assembly_point ?: '—' }}</span></div>
            <div class="mdv-cell"><span class="mdv-k">{{ $en ? 'Emergency access' : 'Acceso de emergencia' }}</span><span class="mdv-v">{{ $s->emergency_access ?: '—' }}</span></div>
            <div class="mdv-cell"><span class="mdv-k">{{ $en ? 'Ambulance' : 'Ambulancia' }}</span><span class="mdv-v">{{ $s->ambulance_company ?: '—' }}</span></div>
            <div class="mdv-cell"><span class="mdv-k">{{ $en ? 'Emergency phone' : 'Tel. emergencia' }}</span><span class="mdv-v">{{ $s->emergency_phone ?: '—' }}</span></div>
            <div class="mdv-cell mdv-wide"><span class="mdv-k">GPS · {{ $en ? 'map' : 'mapa' }}</span>
                <span class="mdv-v">@if($mapsLoc)<a href="{{ $mapsLoc }}" target="_blank" rel="noopener">{{ $s->latitude }}, {{ $s->longitude }}</a>@else — @endif</span>
            </div>
        </div>
    </div>

    {{-- 2 · CONTACTOS DE EMERGENCIA (editables; pre-llenados del crew) --}}
    <form method="POST" action="{{ route('medevac.store', $s->id) }}" class="cc-form-card" enctype="multipart/form-data">
        @csrf
        <h2 class="mdv-h">{{ $en ? 'Emergency contacts' : 'Contactos de emergencia' }}</h2>
        <p class="cc-muted" style="margin:-4px 0 14px;">
            {{ $en ? 'Pre-filled from this production\'s crew. If a slot is empty, no one holds that role in the crew — type the real on-set contact.' : 'Pre-llenados desde el crew de esta producción. Si un campo está vacío, ese puesto no está en el crew — escribe al contacto REAL en set.' }}
        </p>

        <div class="mdv-contacts">
            @foreach($contacts as $c)
                <div class="mdv-contact">
                    <div class="mdv-contact-label">{{ $c['label'] }}</div>
                    <label class="cc-field">
                        <span class="cc-label">{{ $en ? 'Name' : 'Nombre' }}</span>
                        <input type="text" class="cc-control" name="contacts[{{ $c['key'] }}][name]"
                               value="{{ old('contacts.'.$c['key'].'.name', $c['name']) }}" maxlength="255"
                               placeholder="{{ $en ? 'Full name' : 'Nombre y apellido' }}">
                    </label>
                    <label class="cc-field">
                        <span class="cc-label">{{ $en ? 'Phone' : 'Teléfono' }}</span>
                        <input type="tel" inputmode="tel" class="cc-control" name="contacts[{{ $c['key'] }}][phone]"
                               value="{{ old('contacts.'.$c['key'].'.phone', $c['phone']) }}" maxlength="50"
                               placeholder="{{ $en ? 'Phone' : 'Teléfono' }}">
                    </label>
                </div>
            @endforeach
        </div>

        @php $hasSavedMap = strpos((string) ($scouting->hospital_map ?? ''), 'data:image') === 0; @endphp
        <div style="margin-top:16px;">
            <label class="cc-label" for="map_image">{{ $en ? 'Route map (optional)' : 'Mapa de la ruta (opcional)' }}</label>
            @if($hasSavedMap)
                <div style="margin:6px 0 8px; display:flex; align-items:center; gap:10px;">
                    <img src="{{ $scouting->hospital_map }}" alt="{{ $en ? 'Saved map' : 'Mapa guardado' }}"
                         style="height:52px; width:auto; border-radius:6px; border:1px solid var(--stroke, #d7dde5);">
                    <small class="cc-muted">
                        {{ $en
                            ? 'A saved map is on file for this location — it will be reused. Upload a new one only to replace it.'
                            : 'Esta locación ya tiene un mapa guardado — se reutilizará. Sube uno nuevo sólo para reemplazarlo.' }}
                    </small>
                </div>
            @endif
            <input type="file" id="map_image" name="map_image" accept="image/*" class="cc-control" style="padding-top:9px;">
            <small class="cc-muted d-block mt-1">
                {{ $en
                    ? 'Attach a Google Maps screenshot of the route to the hospital. It is saved to the location (so it is not lost on re-issue), embedded in the poster and sealed — it prints offline.'
                    : 'Adjunta una captura de Google Maps de la ruta locación → hospital. Se GUARDA en la locación (no se pierde al re-emitir), se incrusta en el póster y se SELLA (imprime offline).' }}
            </small>
        </div>

        <p class="cc-signnote" style="margin-top:14px;">
            @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico-16'])
            {{ $en
                ? 'Emitting freezes this data and seals it (SHA-256). Each emission is an independent document with its own QR verifier. It does not replace previous ones.'
                : 'Emitir CONGELA estos datos y los sella (SHA-256). Cada emisión es un documento INDEPENDIENTE con su propio verificador QR. No sustituye a los anteriores.' }}
        </p>

        <div class="mdv-actions">
            <a class="cc-btn-ghost" href="{{ route('scoutings.show', $s->id) }}">{{ $en ? 'Cancel' : 'Cancelar' }}</a>
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'file-check', 'class' => 'cc-ico-18'])
                {{ $en ? 'Emit and seal' : 'Emitir y sellar' }}
            </button>
        </div>
    </form>

    {{-- 3 · HISTORIAL de esta locación --}}
    @if($prior->count())
        <div class="cc-form-card">
            <h2 class="mdv-h">{{ $en ? 'Posters already emitted for this location' : 'Pósters ya emitidos de esta locación' }}</h2>
            <div class="mdv-prior">
                @foreach($prior as $p)
                    <a class="mdv-prior-row" href="{{ route('medevac.show', $p->uuid) }}">
                        <span class="mdv-prior-folio">{{ $p->folio() }}</span>
                        <span class="cc-muted">{{ $en ? 'Rev.' : 'Rev.' }} {{ $p->revision }}</span>
                        <span class="cc-muted">{{ optional($p->issued_at)->format('d/m/Y H:i') }}</span>
                        <span class="mdv-prior-by">{{ $p->issued_by_name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
    .cc-medevac .mdv-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:16px;}
    .cc-medevac .mdv-eyebrow{display:inline-flex;align-items:center;gap:7px;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--brand-primary,#ff9900);}
    .cc-medevac .mdv-title{margin:.25rem 0 .1rem;font-size:1.5rem;font-weight:800;line-height:1.15;}
    .cc-medevac .mdv-sub{font-size:.95rem;}
    .cc-medevac .mdv-h{margin:0 0 4px;font-size:1.05rem;font-weight:800;}
    .cc-medevac .mdv-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 18px;}
    .cc-medevac .mdv-cell{display:flex;flex-direction:column;gap:2px;padding:8px 10px;border:1px solid var(--stroke,rgba(0,0,0,.08));border-radius:10px;background:var(--surface-2,rgba(0,0,0,.02));}
    .cc-medevac .mdv-cell.mdv-wide{grid-column:1 / -1;}
    .cc-medevac .mdv-k{font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted,#6b7280);}
    .cc-medevac .mdv-v{font-size:.95rem;font-weight:600;word-break:break-word;}
    .cc-medevac .mdv-contacts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;}
    .cc-medevac .mdv-contact{border:1px solid var(--stroke,rgba(0,0,0,.08));border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:9px;}
    .cc-medevac .mdv-contact-label{font-size:.74rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--brand-primary,#ff9900);}
    .cc-medevac .mdv-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px;flex-wrap:wrap;}
    .cc-medevac .mdv-alert{padding:11px 13px;border-radius:11px;margin-bottom:14px;font-size:.92rem;display:block;}
    .cc-medevac .mdv-alert.bad{background:rgba(220,38,38,.10);border:1px solid rgba(220,38,38,.35);}
    .cc-medevac .mdv-alert.warn{background:rgba(245,158,11,.10);border:1px solid rgba(245,158,11,.35);}
    .cc-medevac .mdv-prior{display:flex;flex-direction:column;gap:6px;}
    .cc-medevac .mdv-prior-row{display:flex;gap:14px;align-items:center;padding:8px 10px;border:1px solid var(--stroke,rgba(0,0,0,.08));border-radius:9px;text-decoration:none;color:inherit;}
    .cc-medevac .mdv-prior-row:hover{border-color:var(--brand-primary,#ff9900);}
    .cc-medevac .mdv-prior-folio{font-weight:800;font-family:var(--mono,monospace);}
    .cc-medevac .mdv-prior-by{margin-left:auto;font-size:.85rem;color:var(--muted,#6b7280);}
    @media (max-width:720px){
        .cc-medevac .mdv-grid{grid-template-columns:1fr;}
        .cc-medevac .mdv-contacts{grid-template-columns:1fr;}
    }
</style>
@endpush
