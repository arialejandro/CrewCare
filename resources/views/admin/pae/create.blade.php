@extends('layouts.app')

{{-- ============================================================================
     EMISIÓN del PAE. Se ancla al DÍA DE RODAJE (prellenado, editable) + 1 ó 2
     LOCACIONES (company move) elegidas del scouting, en el orden capturado. El
     organigrama se pre-llena del crew (editable). Al emitir se congela y se sella.
     NO inventa: un dato vacío se omite en el documento, no se rellena.
============================================================================ --}}

@section('content')
@include('componentes._form-kit')

@php $en = app()->getLocale() === 'en'; @endphp

<div class="cc-pae container-fluid" style="max-width:980px;margin:0 auto;padding:18px 14px 60px;">

    <div class="pae-head">
        <div>
            <div class="pae-eyebrow">@include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico-18']) PAE · {{ $en ? 'Emergency Action Plan' : 'Plan de Atención a Emergencias' }}</div>
            <h1 class="pae-title">{{ $en ? 'Issue an emergency action plan' : 'Emitir un plan de atención a emergencias' }}</h1>
            <div class="pae-sub cc-muted">{{ $en ? 'One per call sheet. It can cover two locations (company move).' : 'Uno por llamado. Puede cubrir dos locaciones (company move).' }}</div>
        </div>
        <a class="cc-btn-ghost" href="{{ route('pae.index') }}">@include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico-16']) {{ $en ? 'Back' : 'Volver' }}</a>
    </div>

    @if($errors->any())
        <div class="pae-alert bad">
            <strong>{{ $en ? 'Check these fields:' : 'Revisa estos campos:' }}</strong>
            <ul style="margin:.3rem 0 0;padding-left:1.1rem;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($scoutings->isEmpty())
        <div class="pae-alert warn">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-16'])
            {{ $en ? 'There are no scouting records yet. Create a scouting first — the plan reads the location, hospital and assessed risks from it.' : 'Aún no hay scoutings. Crea un scouting primero — el plan lee de ahí la locación, el hospital y los riesgos evaluados.' }}
        </div>
    @endif

    <form method="POST" action="{{ route('pae.store') }}">
        @csrf

        {{-- 1 · CABECERA DEL LLAMADO --}}
        <div class="cc-form-card">
            <h2 class="pae-h">{{ $en ? 'Call sheet header' : 'Cabecera del llamado' }}</h2>
            <div class="pae-grid">
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Shoot day' : 'Día de rodaje' }}</span>
                    <input type="number" min="1" max="999" class="cc-control" name="shoot_day"
                           value="{{ old('shoot_day', $shootDay) }}" placeholder="{{ $en ? 'e.g. 5' : 'p. ej. 5' }}">
                    <small class="cc-muted">{{ $en ? 'Pre-filled from the date; adjust it if needed.' : 'Prellenado desde la fecha; ajústalo si hace falta.' }}</small>
                </label>
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Date' : 'Fecha' }}</span>
                    <input type="date" class="cc-control" name="plan_date" value="{{ old('plan_date', now()->toDateString()) }}">
                </label>
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Unit' : 'Unidad' }}</span>
                    <input type="text" class="cc-control" name="unit_name" maxlength="255"
                           value="{{ old('unit_name') }}" placeholder="{{ $en ? 'Main Unit / 2nd Unit…' : 'Unidad principal / 2.ª unidad…' }}">
                </label>
            </div>
        </div>

        {{-- 2 · LOCACIONES (1 ó 2 — company move) --}}
        <div class="cc-form-card">
            <h2 class="pae-h">{{ $en ? 'Locations' : 'Locaciones' }}</h2>
            <p class="cc-muted" style="margin:-4px 0 14px;">
                {{ $en ? 'Pick the location for the day. Add a second one only for a company move — the order below is the order of the day.' : 'Elige la locación del día. Agrega una segunda solo si hay company move — el orden de abajo es el orden de la jornada.' }}
            </p>
            @php
                $old = (array) old('scoutings', []);
                $optLabel = function ($s) {
                    $n = trim((string) $s->location_name);
                    return ($n !== '' ? $n : ('Locación #' . $s->id));
                };
            @endphp
            <div class="pae-grid">
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Location 1' : 'Locación 1' }} <span style="color:#c0392b">*</span></span>
                    <select class="cc-control" name="scoutings[]" required>
                        <option value="">{{ $en ? '— choose —' : '— elegir —' }}</option>
                        @foreach($scoutings as $s)
                            <option value="{{ $s->id }}" @selected(($old[0] ?? null) == $s->id)>{{ $optLabel($s) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Location 2 (company move)' : 'Locación 2 (company move)' }}</span>
                    <select class="cc-control" name="scoutings[]">
                        <option value="">{{ $en ? '— none —' : '— ninguna —' }}</option>
                        @foreach($scoutings as $s)
                            <option value="{{ $s->id }}" @selected(($old[1] ?? null) == $s->id)>{{ $optLabel($s) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="cc-field">
                    <span class="cc-label">{{ $en ? 'Estimated move time' : 'Hora estimada del movimiento' }}</span>
                    <input type="text" class="cc-control" name="move_time" maxlength="50"
                           value="{{ old('move_time') }}" placeholder="{{ $en ? 'e.g. 14:30' : 'p. ej. 14:30' }}">
                    <small class="cc-muted">{{ $en ? 'Only used when there are two locations.' : 'Solo se usa cuando hay dos locaciones.' }}</small>
                </label>
            </div>

            <label class="pae-check" style="margin-top:12px;">
                <input type="checkbox" name="embed_map_views" value="1" @checked(old('embed_map_views'))>
                <span>{{ $en ? 'Include the risk map views (if a sealed map exists for the location)' : 'Incluir las vistas del mapa de riesgos (si la locación tiene un mapa sellado)' }}</span>
            </label>
            <small class="cc-muted d-block" style="margin-top:4px;">
                {{ $en ? 'Otherwise the plan still lists the day\'s risks and references the map by folio + QR when one exists.' : 'Si no, el plan igual lista los riesgos del día y referencia el mapa por folio + QR cuando exista uno.' }}
            </small>
        </div>

        {{-- 3 · ORGANIGRAMA DE EMERGENCIA (editable; pre-llenado del crew) --}}
        <div class="cc-form-card">
            <h2 class="pae-h">{{ $en ? 'Emergency org chart' : 'Organigrama de emergencia' }}</h2>
            <p class="cc-muted" style="margin:-4px 0 14px;">
                {{ $en ? 'Pre-filled from this production\'s crew. If a slot is empty, no one holds that role in the crew — type the real on-set contact. 911 and the hospital are added automatically.' : 'Pre-llenado desde el crew de esta producción. Si un campo está vacío, ese puesto no está en el crew — escribe al contacto REAL en set. El 911 y el hospital se agregan solos.' }}
            </p>
            <div class="pae-contacts">
                @foreach($contacts as $c)
                    <div class="pae-contact">
                        <div class="pae-contact-label">{{ $c['label'] }}</div>
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
        </div>

        <p class="cc-signnote" style="margin-top:14px;">
            @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico-16'])
            {{ $en
                ? 'Emitting freezes the header, org chart and each location block (hospital, risks, map) and seals it (SHA-256). Each emission is an independent document with its own QR verifier. It does not replace previous ones.'
                : 'Emitir CONGELA la cabecera, el organigrama y cada bloque de locación (hospital, riesgos, mapa) y lo sella (SHA-256). Cada emisión es un documento INDEPENDIENTE con su propio verificador QR. No sustituye a los anteriores.' }}
        </p>

        <div class="pae-actions">
            <a class="cc-btn-ghost" href="{{ route('pae.index') }}">{{ $en ? 'Cancel' : 'Cancelar' }}</a>
            <button type="submit" class="btn btn-primary cc-cta" @disabled($scoutings->isEmpty())>
                @include('componentes._icon', ['name' => 'file-check', 'class' => 'cc-ico-18'])
                {{ $en ? 'Emit and seal' : 'Emitir y sellar' }}
            </button>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .cc-pae .pae-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:16px;}
    .cc-pae .pae-eyebrow{display:inline-flex;align-items:center;gap:7px;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--brand-primary,#ff9900);}
    .cc-pae .pae-title{margin:.25rem 0 .1rem;font-size:1.5rem;font-weight:800;line-height:1.15;}
    .cc-pae .pae-sub{font-size:.95rem;}
    .cc-pae .pae-h{margin:0 0 4px;font-size:1.05rem;font-weight:800;}
    .cc-pae .pae-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px 18px;}
    .cc-pae .pae-contacts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
    .cc-pae .pae-contact{border:1px solid var(--stroke,rgba(0,0,0,.08));border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:9px;}
    .cc-pae .pae-contact-label{font-size:.74rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--brand-primary,#ff9900);}
    .cc-pae .pae-check{display:flex;align-items:flex-start;gap:9px;font-size:.92rem;cursor:pointer;}
    .cc-pae .pae-check input{margin-top:3px;flex:none;width:17px;height:17px;}
    .cc-pae .pae-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px;flex-wrap:wrap;}
    .cc-pae .pae-alert{padding:11px 13px;border-radius:11px;margin-bottom:14px;font-size:.92rem;display:block;}
    .cc-pae .pae-alert.bad{background:rgba(220,38,38,.10);border:1px solid rgba(220,38,38,.35);}
    .cc-pae .pae-alert.warn{background:rgba(245,158,11,.10);border:1px solid rgba(245,158,11,.35);}
    @media (max-width:720px){
        .cc-pae .pae-grid{grid-template-columns:1fr;}
        .cc-pae .pae-contacts{grid-template-columns:1fr;}
    }
</style>
@endpush
