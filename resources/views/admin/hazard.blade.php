{{-- ============================================================================================
     ACTO INSEGURO (Unsafe Act) — v2 "Cinematic Dark Glass". Documento STANDALONE (no layout).
     Ruta: GET /unsafeact/{id} → name hazard_notifications.show → HazardNotificationController@show.
     Chrome compartido: _report-v2-head (fuentes+CSS+motor impresión) · _report-v2-toolbar · _report-v2-foot.
     DOS CARAS: vidrio cinematográfico en pantalla / documento blanco firmable al Exportar PDF (data-view=print).
     PHP 7.4. Guards Schema::hasColumn/hasTable. i18n reports.* (lee locale al render; ?lang=en|es).
     El 5×5 es propio del Amazon MGM: aquí el riesgo se muestra con chips (nivel + Prob×Cons).
     Ghost del diseño anterior: admin/hazard-legacy.blade.php (revertir = renombrar).
============================================================================================ --}}
@php
    use Illuminate\Support\Facades\Schema;
    if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); }
    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = $brand['brand_name'] ?? 'CrewCare';
    $primary   = $brand['primary_color'] ?? '#ff9900';

    $hz = $hazardNotification;

    // ---- Riesgo (guards defensivos por si prod no tiene las columnas del delta) ----
    $riskLevel   = Schema::hasColumn('hazardnotifications', 'risk_level') ? $hz->risk_level : null;
    $likelihood  = Schema::hasColumn('hazardnotifications', 'likelihood') ? strtoupper((string) $hz->likelihood) : '';
    $consequence = Schema::hasColumn('hazardnotifications', 'consequence') ? (int) $hz->consequence : 0;
    if ($riskLevel === 'Extremo')   { $riskTone = 'danger'; }
    elseif ($riskLevel === 'Alto')  { $riskTone = 'warn';   }
    elseif ($riskLevel === 'Medio') { $riskTone = 'caution';}
    elseif ($riskLevel)             { $riskTone = 'ok';     }
    else                            { $riskTone = 'neutral';}
    $mtxProb = ['A' => 'Casi seguro', 'B' => 'Probable', 'C' => 'Moderado', 'D' => 'Improbable', 'E' => 'Raro'];
    $mtxCons = [1 => 'Insignificante', 2 => 'Menor', 3 => 'Moderada', 4 => 'Mayor', 5 => 'Catastrófica'];
    $hasPC   = isset($mtxProb[$likelihood]) && $consequence >= 1 && $consequence <= 5;

    // ---- Cintillo: LEAD = acto/evento (catálogo) con contexto como subrótulo ----
    $hzEvent   = $hz->hazardEvent;
    $leadValue = ($hzEvent ? $hzEvent->name_localized : null) ?: ($hz->name_loc ?: '—');
    $leadSub   = $hzEvent ? $hzEvent->context_label : null;

    // ---- Estatus de la acción (PDCA) ----
    $status     = $hz->action_status ?: 'Abierto';
    $statusTone = $status === 'Cerrado' ? 'ok' : ($status === 'Abierto' ? 'warn' : 'caution');

    // ---- Firma digital / no-repudio: la verificación y la cadena CFDI las resuelve
    //      internamente el parcial _seal-cfdi (evita una doble llamada a verifyLatestSignature). ----

    // ---- Folio / UUID ----
    $folio    = 'HAZ-' . str_pad((string) $hz->id, 4, '0', STR_PAD_LEFT);
    // UUID REAL del documento (el mismo del sello CFDI), no un código derivado del id.
    $footUuid = 'UUID: ' . ($hz->uuid ?: '—') . ' | ' . config('crewcare.doc_version');

    // NOMBRE DE CRÉDITOS de quien elaboró (firmas + pie + sello): autor por created_by_id →
    // User::displayName (ncreditos; si vacío, nombre corto). Si no hay autor, cae a make_by.
    $__author = ! empty($hz->created_by_id) ? \App\Models\User::find($hz->created_by_id) : null;
    $creditName = $__author ? \App\Models\User::displayName($__author) : ($hz->make_by ?: '—');

    // ---- Hero ----
    $heroDate = $hz->date_observed ? \Carbon\Carbon::parse($hz->date_observed)->translatedFormat('d M Y') : null;
    $heroTime = $hz->time_observed ?: null;
    $heroLoc  = $hz->name_loc ?: ($hz->location_hazard_unsafe_act ?: '');

    $isManager = auth()->check() && auth()->user()->can('hazards.manage');
    $hasFlash  = session()->has('success') || session()->has('error') || (isset($errors) && $errors->any());
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brandName }} · {{ __('reports.hazard_module') }} · {{ $leadValue }}</title>
@include('componentes._report-v2-head')
</head>
<body>

<div class="ambient"><div class="b b1"></div><div class="b b2"></div></div>

@include('componentes._report-v2-toolbar', ['backRoute' => route('hazard_notifications.index')])

<div class="stage">
  <article class="sheet">
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        'heroImage'    => $hz->main_image_path,
        'heroProject'  => $brandName,
        'heroLocation' => $heroLoc,
        'heroDate'     => $heroDate,
        'heroTime'     => $heroTime,
        'heroModule'   => __('reports.hazard_module'),
      ])
    </td></tr></thead>
    <tbody><tr><td>

    {{-- QUICK-READ BAND --}}
    <div class="band">
      <div class="lead">
        <span class="ic">@include('componentes._icon', ['name' => 'alert-triangle'])</span>
        <span class="who">
          <span class="lbl">{{ __('reports.band_lead_hazard') }}</span>
          <span class="val">{{ $leadValue }}</span>
          @if($leadSub)<span class="sub">{{ $leadSub }}</span>@endif
        </span>
      </div>
      <div class="stats">
        <div class="cell"><span class="lbl">{{ __('reports.label_risk') }}</span><span class="v {{ in_array($riskLevel, ['Extremo','Alto'], true) ? 'warn' : '' }}">{{ $riskLevel ?: '—' }}</span></div>
        <div class="cell"><span class="lbl">{{ __('reports.injury_band_status') }}</span><span class="v {{ $status === 'Cerrado' ? 'ok' : ($status === 'Abierto' ? 'warn' : '') }}">{{ $status }}</span></div>
        <div class="cell"><span class="lbl">{{ __('reports.label_observed') }}</span><span class="v">{{ $heroDate ?: '—' }}</span></div>
      </div>
    </div>

    <div class="body">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — {{ __('reports.hazard_module') }} — {{ $leadValue }}</h1>

      {{-- CONTROLES OPERATIVOS (no-print): flash + actualización de estatus PDCA (managers) --}}
      @if($hasFlash || $isManager)
      <div class="no-print" style="margin-bottom:20px">
        @if(session('success'))<div class="alert ok">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert bad">{{ session('error') }}</div>@endif
        @if(isset($errors) && $errors->any())@foreach($errors->all() as $e)<div class="alert bad">{{ $e }}</div>@endforeach @endif
        @can('hazards.manage')
        <div class="ops">
          <span class="ops-note">{{ __('reports.injury_band_status') }} · PDCA</span>
          @can('tools.inspect')
          <a href="{{ route('tools.index', ['origin' => 'acto', 'origin_id' => $hz->id, 'moment' => 'por_hallazgo']) }}"
             style="display:inline-flex;align-items:center;gap:6px;margin-bottom:10px;padding:8px 12px;border:1px solid #b45309;border-radius:8px;color:#b45309;text-decoration:none;font-size:14px;">
             @include('componentes._icon', ['name' => 'wrench']) {{ __('Inspeccionar herramienta involucrada') }}
          </a>
          @endcan
          <form action="{{ route('hazard_notifications.status', $hz->id) }}" method="POST">
            @csrf
            <select name="action_status" class="field">
              @foreach(['Abierto', 'En proceso', 'Cerrado'] as $opt)
                <option value="{{ $opt }}" {{ ($hz->action_status ?: 'Abierto') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
              @endforeach
            </select>
            <button class="btn brand" type="submit">@include('componentes._icon', ['name' => 'check-circle']) {{ __('reports.label_update_status') }}</button>
          </form>
        </div>
        @endcan
      </div>
      @endif

      {{-- DATOS DEL REPORTE --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h2>{{ __('reports.hazard_module') }}</h2><span class="line"></span></div>
        <div class="panel"><div class="facts">
          <div class="fact"><div class="k">{{ __('reports.label_folio') }}</div><div class="v mono">{{ $folio }}</div></div>
          <div class="fact"><div class="k">{{ __('reports.label_observed') }}</div><div class="v mono">{{ $heroDate ?: '—' }}{{ $heroTime ? ' · ' . $heroTime : '' }}</div></div>
          @if($hz->location_hazard_unsafe_act)
          <div class="fact"><div class="k">{{ __('reports.label_act_location') }}</div><div class="v">{{ $hz->location_hazard_unsafe_act }}</div></div>
          @endif
          @if($hz->latitude && $hz->longitude)
          <div class="fact"><div class="k">{{ __('reports.label_gps_location') }}</div><div class="v mono">{{ number_format((float) $hz->latitude, 4) }}, {{ number_format((float) $hz->longitude, 4) }}</div></div>
          @endif
          @if($hz->gps_address)
          <div class="fact"><div class="k">{{ __('reports.label_gps_location') }}</div><div class="v">{{ $hz->gps_address }}</div></div>
          @endif
          @if($hz->regulation_code)
          <div class="fact"><div class="k">{{ __('reports.label_applicable_regulation') }}</div><div class="v"><span class="badge badge-{{ $hz->regulation_badge }}">{{ $hz->regulation_badge }}</span> <span class="mono" style="font-size:.78rem">{{ $hz->regulation_code }}</span></div></div>
          @endif
          @if(Schema::hasColumn('hazardnotifications', 'scouting_report_id') && $hz->scoutingReport)
          <div class="fact"><div class="k">{{ __('reports.label_scouting_link') }}</div><div class="v">{{ $hz->scoutingReport->location_name ?: ($hz->scoutingReport->location_address ?: '—') }}</div></div>
          @endif
          @if(!empty($involvedDeptName))
          {{-- El documento imprime SOLO el ÁREA del involucrado, nunca el nombre. --}}
          <div class="fact"><div class="k">{{ __('reports.label_involved_area') }}</div><div class="v">{{ $involvedDeptName }}</div></div>
          @endif
          @if(Schema::hasColumn('hazardnotifications', 'related_unsafecond_id') && $hz->relatedUnsafeCond)
          @php $rel = $hz->relatedUnsafeCond; $relDate = $rel->date_observed ? \Carbon\Carbon::parse($rel->date_observed)->format('d/m/Y') : null; @endphp
          <div class="fact"><div class="k">{{ __('reports.label_related_report') }}</div><div class="v mono">UNS-{{ str_pad((string) $rel->id, 4, '0', STR_PAD_LEFT) }}{{ $relDate ? ' · ' . $relDate : '' }}</div></div>
          @endif
          @if($hz->make_by)
          <div class="fact"><div class="k">{{ __('reports.label_by') }}</div><div class="v">{{ $hz->make_by }}</div></div>
          @endif
        </div></div>
      </section>

      {{-- (2026-07-24) INVOLUCRADO — uso interno, NO SE IMPRIME (no-print). El documento solo
           muestra el ÁREA (arriba); el NOMBRE se revela aquí únicamente a Safety (hazards.manage)
           o al jefe directo del depto del involucrado. Su jefe además recibe el aviso por correo. --}}
      @if($canViewInvolved && $involvedUser)
      <div class="no-print" style="margin:0 0 20px">
        <div class="panel" style="border-color:color-mix(in srgb,var(--warn) 35%,transparent)">
          <div style="font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.12em;font-weight:700;margin-bottom:3px">{{ __('reports.label_involved_internal') }}</div>
          <div style="font-weight:700">{{ trim($involvedUser->name . ' ' . ($involvedUser->lname ?? '')) }}@if(!empty($involvedDeptName)) <span style="color:var(--muted);font-weight:400">· {{ $involvedDeptName }}</span>@endif</div>
        </div>
      </div>
      @endif

      {{-- REFERENCIA DE UBICACIÓN MANUAL (captura sin GPS) --}}
      @if(Schema::hasColumn('hazardnotifications', 'manual_location_justification') && $hz->manual_location_justification)
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg><h2>{{ __('reports.hazard_manual_location_ref') }}</h2></div>
        <p class="desc">{{ $hz->manual_location_justification }}</p>
      </section>
      @endif

      {{-- DESCRIPCIÓN DEL ACTO INSEGURO --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ __('reports.hazard_section_description') }}</h2></div>
        <p class="desc">{{ $hz->description_hazard_unsafe_act ?: __('reports.empty_description') }}</p>
      </section>

      {{-- RIESGO: chips (nivel + Prob×Cons). El 5×5 vive en el Amazon MGM. --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg><h2>{{ __('reports.label_risk') }}</h2><span class="line"></span></div>
        <div class="riskrow">
          <span class="rc {{ $riskTone }}">@include('componentes._icon', ['name' => 'alert-triangle'])<span class="t"><span class="l">{{ __('reports.label_risk') }}</span><span class="v">{{ $riskLevel ?: '—' }}</span></span></span>
          @if($hasPC)
          <span class="rc neutral">@include('componentes._icon', ['name' => 'activity'])<span class="t"><span class="l">{{ __('reports.label_likelihood') }}</span><span class="v">{{ $mtxProb[$likelihood] }}</span></span></span>
          <span class="rc neutral">@include('componentes._icon', ['name' => 'zap'])<span class="t"><span class="l">{{ __('reports.label_consequence') }}</span><span class="v">{{ $mtxCons[$consequence] }}</span></span></span>
          @endif
        </div>
      </section>

      {{-- FACTOR HUMANO (solo Acto): el "por qué" de la conducta. Análisis, no señalamiento. --}}
      @php $hf = is_array($hz->human_factor) ? $hz->human_factor : []; @endphp
      @if(count($hf))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg><h2>{{ __('reports.section_human_factor') }}</h2></div>
        <div class="chips">
          @foreach($hf as $hfKey)
            @if(isset(\App\Models\hazardnotification::HUMAN_FACTORS[$hfKey]))
            <span class="chip">{{ \App\Models\hazardnotification::HUMAN_FACTORS[$hfKey] }}</span>
            @endif
          @endforeach
        </div>
      </section>
      @endif

      {{-- ACCIÓN TOMADA / SUGERENCIAS --}}
      <section class="sec"><div class="two">
        <div>
          <div class="sec-h"><span class="bar"></span><h2>{{ __('reports.hazard_section_action_taken') }}</h2></div>
          <div class="panel" style="font-size:.88rem">{{ $hz->action_taken ?: __('reports.hazard_empty_action_taken') }}</div>
        </div>
        <div>
          <div class="sec-h"><span class="bar"></span><h2>{{ __('reports.hazard_section_suggestions') }}</h2></div>
          <p class="desc">{{ $hz->suggestions_corrective_action ?: __('reports.hazard_empty_suggestions') }}</p>
        </div>
      </div></section>

      {{-- ACCIONES CORRECTIVAS (PDCA) --}}
      @if(Schema::hasTable('action_items') && $hz->actionItems->count())
      @php $stL = ['open' => __('reports.status_open'), 'in_progress' => __('reports.status_in_progress'), 'closed' => __('reports.status_closed')]; @endphp
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg><h2>{{ __('reports.section_corrective_actions') }}</h2></div>
        <div style="display:flex;flex-direction:column;gap:8px">
          @foreach($hz->actionItems as $item)
          @php $st = $item->status; $ov = method_exists($item, 'isOverdue') && $item->isOverdue(); @endphp
          <div class="panel" style="{{ $ov ? 'border-color:color-mix(in srgb,var(--danger) 45%,transparent)' : '' }}">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:start">
              <p style="margin:0;font-size:.86rem">{{ $item->description }}</p>
              <span class="chip {{ $st === 'closed' ? 'ok' : ($st === 'open' ? 'warn' : '') }}" style="flex:none">{{ isset($stL[$st]) ? $stL[$st] : $st }}</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:4px 18px;margin-top:6px;font-size:.72rem;color:var(--muted)">
              <span><strong>{{ __('reports.label_responsible') }}:</strong> {{ $item->owner ? $item->owner->name : '—' }}</span>
              <span style="{{ $ov ? 'color:var(--danger);font-weight:700' : '' }}"><strong>{{ __('reports.label_deadline') }}:</strong> {{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('d M Y H:i') : '—' }}{{ $ov ? ' · ' . __('reports.label_overdue') : '' }}</span>
              @if($item->source)<span><strong>{{ __('reports.label_source') }}:</strong> {{ $item->source === 'auto' ? __('reports.label_auto') : __('reports.label_manual') }}</span>@endif
            </div>
            {{-- (2026-07-24) EVIDENCIA DE CIERRE (SÍ se imprime): quién cerró, cuándo y la foto de
                 mitigación. "Cerrado" sin evidencia es la afirmación más débil de un reporte. --}}
            @if($item->status === 'closed')
            <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--stroke);font-size:.72rem;color:var(--muted)">
              <strong>{{ __('reports.label_closed_by') }}:</strong> {{ $item->verifiedBy ? $item->verifiedBy->name : '—' }}{{ $item->closed_at ? ' · ' . \Carbon\Carbon::parse($item->closed_at)->format('d M Y H:i') : '' }}
              @if(Schema::hasColumn('action_items', 'mitigation_image_path') && $item->mitigation_image_path)
              <div style="margin-top:6px">
                <div style="font-weight:700;color:var(--text);margin-bottom:3px">{{ __('reports.label_closure_evidence') }}</div>
                <img src="{{ $item->mitigation_image_path }}" alt="{{ __('reports.label_closure_evidence') }}" style="max-height:150px;max-width:100%;border-radius:8px;border:1px solid var(--stroke)">
                @if(Schema::hasColumn('action_items', 'mitigation_note') && $item->mitigation_note)<div style="margin-top:3px">{{ $item->mitigation_note }}</div>@endif
              </div>
              @endif
            </div>
            @endif
            <div class="no-print">@include('componentes._wa-mitigation-link', ['item' => $item])</div>
            @can('hazards.manage')
            <div class="no-print" style="margin-top:8px;padding-top:8px;border-top:1px solid var(--stroke);display:flex;gap:8px">
              @if(in_array($item->status, ['open', 'in_progress'], true))
              <form action="{{ route('action_items.close', $item->id) }}" method="POST">@csrf<button class="btn ok sm" type="submit">{{ __('reports.label_mark_closed') }}</button></form>
              @else
              <form action="{{ route('action_items.reopen', $item->id) }}" method="POST">@csrf<button class="btn sm" type="submit">{{ __('reports.label_reopen') }}</button></form>
              @endif
            </div>
            @endcan
          </div>
          @endforeach
        </div>
      </section>
      @endif

      {{-- NORMAS APLICABLES --}}
      @if(Schema::hasTable('standardables') && $hz->standards->count())
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ __('reports.section_applicable_standards') }}</h2></div>
        <div class="chips">
          @foreach($hz->standards as $std)
          <span class="chip"><span class="badge badge-{{ $std->regulation_badge }}">{{ $std->regulation_badge }}</span> {{ $std->category_name_localized }} <span style="color:var(--faint);font-family:var(--mono);font-size:.7rem">{{ $std->regulation_code }}</span></span>
          @endforeach
        </div>
      </section>
      @endif

      {{-- EVIDENCIA FOTOGRÁFICA --}}
      @php
        $gallery = [];
        if ($hz->main_image_path) { $gallery[] = $hz->main_image_path; }
        if (is_array($hz->additional_images_paths)) { $gallery = array_merge($gallery, $hz->additional_images_paths); }
      @endphp
      @if(count($gallery))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg><h2>{{ __('reports.hazard_section_graphic_evidence') }}</h2></div>
        <div class="photos">
          @foreach($gallery as $i => $img)
          <div class="photo"><img src="{{ $img }}" loading="lazy" alt="{{ __('reports.hazard_section_graphic_evidence') }} {{ $i + 1 }}"><span class="cap">{{ __('reports.hazard_section_graphic_evidence') }} {{ $i + 1 }}</span></div>
          @endforeach
        </div>
      </section>
      @endif

      {{-- FIRMAS E INTEGRIDAD --}}
      {{-- (2026-07-23) Homologado con Injury/DSR/Scouting: el título y el rol de firma ya no
           reusan label_risk_assessment ("Risk Assessment" en inglés dentro del doc ES); el bloque
           .sign es el hueco de la firma AUTÓGRAFA (módulo futuro) y la cadena SHA/CFDI la pinta
           _seal-cfdi (cotejable), en vez del banner artesanal con el UUID que "se leía como sello".
           Los gemelos sellan al crearse, así que SIEMPRE llevan cadena vigente. --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg><h2>{{ __('reports.label_signatures_integrity') }}</h2><span class="line"></span></div>
        {{-- Casilla 1 = nombre de créditos sobre la línea de firma; casilla 2 = fecha como dato SIN
             línea (.sig--plain): la fecha no se firma. --}}
        <div class="sign">
          <div class="sig"><div class="who">{{ $creditName }}</div><div class="role">{{ __('reports.label_prepared_by') }}</div></div>
          <div class="sig sig--plain"><div class="who">{{ $hz->make_date ? \Carbon\Carbon::parse($hz->make_date)->translatedFormat('d M Y') : '—' }}</div><div class="role">{{ __('reports.label_date') }}</div></div>
        </div>
        @include('componentes._seal-cfdi', ['doc' => $hz, 'folio' => $folio, 'prefix' => 'CREWCARE-HAZ'])
      </section>
    </div>
    </td></tr></tbody>
    <tfoot><tr><td><div class="footer-spacer"></div></td></tr></tfoot>
    </table>
    @include('componentes._report-v2-foot', [
      'footPreparedName' => $creditName,
      {{-- (2026-07-23) Antes reusaba label_risk_assessment ("Risk Assessment" — inglés dentro del
           doc ES). Se usa el nombre localizado del módulo, que ya nombra el tipo de documento. --}}
      'footPreparedMeta' => __('reports.hazard_module'), // pie SIN fecha (owner 2026-08)
      'footUuid'         => $footUuid,
    ])

</body>
</html>
