@extends('layouts.app')

{{-- ============================================================================
     EMISIÓN del PAE. Se ancla al DÍA DE RODAJE (prellenado, editable) + 1 ó 2
     LOCACIONES (company move) elegidas del scouting, en el orden capturado. El
     organigrama se pre-llena del crew (editable). Al emitir se congela y se sella.
     NO inventa: un dato vacío se omite en el documento, no se rellena.
============================================================================ --}}

@section('content')
@include('componentes._form-kit')

@php
    $en      = app()->getLocale() === 'en';
    $source  = $source ?? null;
    $editing = (bool) $source;
    $units   = $units ?? collect();
    $prefill = ($prefill ?? []) + [
        'scoutings' => [], 'plan_date' => now()->toDateString(),
        'shoot_day' => $shootDay ?? null, 'unit_id' => null, 'unit_name' => '', 'move_time' => '', 'embed' => false,
    ];
    $cancelUrl = $editing ? route('pae.show', $source->uuid) : route('pae.index');
    $nextVer   = $editing ? ('v' . ($source->revisionNumber() + 1) . '.0') : 'v1.0';
@endphp

<div class="cc-pae container-fluid" style="max-width:1000px;margin:0 auto;padding:18px 14px 60px;">

    <div class="cc-page-head">
        <div>
            <h1 class="cc-h1">{{ $editing ? ($en ? 'New version of the plan' : 'Nueva versión del plan') : ($en ? 'Issue an emergency action plan' : 'Emitir un plan de atención a emergencias') }}</h1>
            <p class="cc-sub">
                @if($editing)
                    {{ $en ? 'Editing' : 'Editas' }} <strong>{{ $source->folio() }} {{ $source->versionLabel() }}</strong> → {{ $en ? 'a new sealed version' : 'se emite una versión sellada nueva' }} <strong>{{ $nextVer }}</strong> {{ $en ? 'that replaces it.' : 'que la reemplaza.' }}
                @else
                    {{ $en ? 'One per call sheet. It can cover two locations (company move).' : 'Uno por llamado. Puede cubrir dos locaciones (company move).' }}
                @endif
            </p>
        </div>
        <a class="cc-btn-ghost" href="{{ $cancelUrl }}">{{ $en ? 'Cancel' : 'Cancelar' }}</a>
    </div>

    @if($errors->any())
        <div class="cc-alert cc-alert--bad">
            <strong>{{ $en ? 'Check these fields:' : 'Revisa estos campos:' }}</strong>
            <ul style="margin:.3rem 0 0;padding-left:1.1rem;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($scoutings->isEmpty())
        <div class="cc-alert cc-alert--warn">
            {{ $en ? 'There are no scouting records yet. Create a scouting first — the plan reads the location, hospital and assessed risks from it.' : 'Aún no hay scoutings. Crea un scouting primero — el plan lee de ahí la locación, el hospital y los riesgos evaluados.' }}
        </div>
    @endif

    <form method="POST" action="{{ route('pae.preview') }}">
        @csrf
        @if($editing)<input type="hidden" name="supersedes_uuid" value="{{ $source->uuid }}">@endif

        {{-- 1 · CABECERA DEL LLAMADO --}}
        <div class="cc-form-card">
            <h2 class="cc-card-h">{{ $en ? 'Call sheet header' : 'Cabecera del llamado' }}</h2>
            <div class="cc-grid-3">
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Shoot day' : 'Día de rodaje' }}</span>
                    <input type="number" min="1" max="999" class="cc-control" name="shoot_day"
                           value="{{ old('shoot_day', $shootDay) }}" placeholder="{{ $en ? 'e.g. 5' : 'p. ej. 5' }}">
                    <small class="cc-hint">{{ $en ? 'Pre-filled from the date; adjust it if needed.' : 'Prellenado desde la fecha; ajústalo si hace falta.' }}</small>
                </label>
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Date' : 'Fecha' }}</span>
                    <input type="date" class="cc-control" name="plan_date" value="{{ old('plan_date', $prefill['plan_date']) }}">
                </label>
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Unit' : 'Unidad' }}</span>
                    @if($units->isNotEmpty())
                        {{-- Con unidades adicionales: se ELIGE la unidad (el servidor estampa su nombre y
                             sella el día contra SU calendario). Vacío = principal, como hoy. --}}
                        <select class="cc-control" name="unit_id">
                            <option value="">{{ \App\Models\Unit::PRINCIPAL_LABEL }}</option>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}" @selected((string) old('unit_id', $prefill['unit_id']) === (string) $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    @else
                        {{-- Sin unidades adicionales: texto libre, idéntico a hoy. --}}
                        <input type="text" class="cc-control" name="unit_name" maxlength="255"
                               value="{{ old('unit_name', $prefill['unit_name']) }}" placeholder="{{ $en ? 'Main Unit / 2nd Unit…' : 'Unidad principal / 2.ª unidad…' }}">
                    @endif
                </label>
            </div>
        </div>

        {{-- 2 · LOCACIONES (1 ó 2 — company move) --}}
        <div class="cc-form-card">
            <h2 class="cc-card-h">{{ $en ? 'Locations' : 'Locaciones' }}</h2>
            <p class="cc-sub" style="margin:-4px 0 14px;">
                {{ $en ? 'Pick the location for the day. Add a second one only for a company move — the order below is the order of the day.' : 'Elige la locación del día. Agrega una segunda solo si hay company move — el orden de abajo es el orden de la jornada.' }}
            </p>
            @php
                $old = (array) old('scoutings', $prefill['scoutings']);
                $optLabel = function ($s) { $n = trim((string) $s->location_name); return ($n !== '' ? $n : ('Locación #' . $s->id)); };
            @endphp
            <div class="cc-grid-3">
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Location 1' : 'Locación 1' }} <span class="cc-req">*</span></span>
                    <select class="cc-control" name="scoutings[]" required>
                        <option value="">{{ $en ? '— choose —' : '— elegir —' }}</option>
                        @foreach($scoutings as $s)
                            <option value="{{ $s->id }}" {{ (($old[0] ?? null) == $s->id) ? 'selected' : '' }}>{{ $optLabel($s) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Location 2 (company move)' : 'Locación 2 (company move)' }}</span>
                    <select class="cc-control" name="scoutings[]">
                        <option value="">{{ $en ? '— none —' : '— ninguna —' }}</option>
                        @foreach($scoutings as $s)
                            <option value="{{ $s->id }}" {{ (($old[1] ?? null) == $s->id) ? 'selected' : '' }}>{{ $optLabel($s) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Estimated move time' : 'Hora estimada del movimiento' }}</span>
                    <input type="text" class="cc-control" name="move_time" maxlength="50"
                           value="{{ old('move_time', $prefill['move_time']) }}" placeholder="{{ $en ? 'e.g. 14:30' : 'p. ej. 14:30' }}">
                    <small class="cc-hint">{{ $en ? 'Only used when there are two locations.' : 'Solo se usa cuando hay dos locaciones.' }}</small>
                </label>
            </div>

            <label class="cc-check">
                <input type="checkbox" name="embed_map_views" value="1" {{ old('embed_map_views', $prefill['embed'] ? '1' : '') ? 'checked' : '' }}>
                <span>{{ $en ? 'Include the risk map views (if a sealed map exists for the location)' : 'Incluir las vistas del mapa de riesgos (si la locación tiene un mapa sellado)' }}</span>
            </label>
            <small class="cc-hint" style="display:block;margin-top:4px;">
                {{ $en ? 'Otherwise the plan still lists the day\'s risks and references the map by folio + QR when one exists.' : 'Si no, el plan igual lista los riesgos del día y referencia el mapa por folio + QR cuando exista uno.' }}
            </small>
        </div>

        {{-- 3 · ORGANIGRAMA DE EMERGENCIA (editable; pre-llenado del crew) --}}
        <div class="cc-form-card">
            <h2 class="cc-card-h">{{ $en ? 'Emergency org chart' : 'Organigrama de emergencia' }}</h2>
            <p class="cc-sub" style="margin:-4px 0 12px;">
                {{ $en ? 'The first three are pre-filled from this production\'s crew. Empty slots are captured on set. 911 and the hospital are added automatically.' : 'Los tres primeros se pre-llenan del crew de esta producción. Los vacíos se capturan en set. El 911 y el hospital se agregan solos.' }}
            </p>
            <div class="pae-org-head">
                <span>{{ $en ? 'Role' : 'Rol' }}</span><span>{{ $en ? 'Name' : 'Nombre' }}</span><span>{{ $en ? 'Phone' : 'Teléfono' }}</span><span>{{ $en ? 'Radio' : 'Radio' }}</span>
            </div>
            @foreach($contacts as $c)
                <div class="pae-org-row">
                    <span class="pae-org-role">{{ $c['label'] }}</span>
                    <input type="text" class="cc-control" name="contacts[{{ $c['key'] }}][name]"
                           value="{{ old('contacts.'.$c['key'].'.name', $c['name']) }}" maxlength="255"
                           placeholder="{{ $en ? 'Name' : 'Nombre' }}">
                    <input type="tel" inputmode="tel" class="cc-control" name="contacts[{{ $c['key'] }}][phone]"
                           value="{{ old('contacts.'.$c['key'].'.phone', $c['phone']) }}" maxlength="50"
                           placeholder="{{ $en ? 'Phone' : 'Teléfono' }}">
                    <input type="text" class="cc-control" name="contacts[{{ $c['key'] }}][radio]"
                           value="{{ old('contacts.'.$c['key'].'.radio', $c['radio']) }}" maxlength="50"
                           placeholder="{{ $en ? 'Ch.' : 'Canal' }}">
                </div>
            @endforeach
        </div>

        <p class="cc-signnote">
            @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico-16'])
            {{ $en
                ? 'Emitting freezes the header, org chart and each location block (hospital, risks, map) and seals it (SHA-256). Editing issues a NEW VERSION that replaces the previous one; each version keeps its own QR verifier.'
                : 'Emitir CONGELA la cabecera, el organigrama y cada bloque de locación (hospital, riesgos, mapa) y lo sella (SHA-256). Al editar se emite una VERSIÓN NUEVA que reemplaza a la anterior; cada versión conserva su propio verificador QR.' }}
        </p>

        <div class="cc-form-actions">
            <a class="cc-btn-ghost" href="{{ $cancelUrl }}">{{ $en ? 'Cancel' : 'Cancelar' }}</a>
            <button type="submit" class="btn btn-primary cc-cta" {{ $scoutings->isEmpty() ? 'disabled' : '' }}>
                @include('componentes._icon', ['name' => 'file-check', 'class' => 'cc-ico-16'])
                {{ $en ? 'Preview' : 'Previsualizar' }}
            </button>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .cc-pae .cc-page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:18px;}
    .cc-pae .cc-h1{margin:0;font-size:1.4rem;font-weight:800;line-height:1.2;}
    .cc-pae .cc-sub{margin:.25rem 0 0;font-size:.92rem;color:var(--muted,#6b7280);}
    .cc-pae .cc-card-h{margin:0 0 4px;font-size:1.02rem;font-weight:800;}
    .cc-pae .cc-req{color:var(--danger,#c0392b);}
    .cc-pae .cc-hint{color:var(--muted,#6b7280);font-size:.8rem;}
    .cc-pae .cc-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px 18px;}
    .cc-pae .cc-check{display:flex;align-items:flex-start;gap:9px;font-size:.92rem;cursor:pointer;margin-top:12px;}
    .cc-pae .cc-check input{margin-top:3px;flex:none;width:17px;height:17px;}
    .cc-pae .cc-alert{padding:11px 13px;border-radius:11px;margin-bottom:14px;font-size:.92rem;}
    .cc-pae .cc-alert--bad{background:rgba(220,38,38,.10);border:1px solid rgba(220,38,38,.35);}
    .cc-pae .cc-alert--warn{background:rgba(245,158,11,.10);border:1px solid rgba(245,158,11,.35);}
    .cc-pae .pae-org-head{display:grid;grid-template-columns:minmax(160px,2fr) 2fr 1.3fr 1fr;gap:10px;padding:0 2px 6px;
        font-size:.7rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted,#6b7280);}
    .cc-pae .pae-org-row{display:grid;grid-template-columns:minmax(160px,2fr) 2fr 1.3fr 1fr;gap:10px;align-items:center;margin-bottom:8px;}
    .cc-pae .pae-org-role{font-size:.82rem;font-weight:600;color:var(--text,#111);}
    .cc-pae .cc-signnote{display:flex;align-items:flex-start;gap:8px;margin-top:14px;font-size:.86rem;color:var(--muted,#6b7280);}
    .cc-pae .cc-form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px;flex-wrap:wrap;}
    @media (max-width:820px){
        .cc-pae .cc-grid-3{grid-template-columns:1fr;}
        .cc-pae .pae-org-head{display:none;}
        .cc-pae .pae-org-row{grid-template-columns:1fr;gap:5px;padding:10px;border:1px solid var(--stroke,rgba(0,0,0,.08));border-radius:10px;}
    }
</style>
@endpush
