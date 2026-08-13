{{-- ============================================================================================
     INJURY REPORT — EXPEDIENTE COMPLETO (H&S/legal). "Cinematic Dark Glass".
     Ruta: GET /accident/{id}/completo → InjuryReportController@showComplete (gateado por la
     policy viewMedical, para VER y para IMPRIMIR). La salida LITE (notificación a Producción)
     es un documento DISTINTO: admin/injuryreport-lite.blade.php en /accident/{id}.
     DOS CARAS del papel:
       · En PANTALLA: documento de vidrio cinematográfico sobre fondo oscuro + luz ambiental.
       · Al EXPORTAR PDF (botón o Ctrl/⌘+P): se transforma en documento BLANCO legible y firmable.
         El "modo papel" lo acciona [data-view="print"] → sirve IGUAL para el preview en pantalla
         (toggle "Vista impresión") y para el print real, así el PDF se ve idéntico al preview.
     PHP 7.4: sin match()/enums/nullsafe/promoción. i18n intacto (reports.*). Guards Schema::hasColumn.
     Vista STANDALONE (no extiende layouts.app) para la experiencia inmersiva de documento.
     (2026-07-20) SIN rejilla 5×5 en el documento: sólo el RESULTADO + la lectura (item 5). La
     matriz sigue como herramienta de cálculo en el FORM de captura, no en el PDF legal.
============================================================================================ --}}
@php
    use Illuminate\Support\Facades\Schema;
    if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); }
    $brand   = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = $brand['brand_name'] ?? 'CrewCare';
    $primary = $brand['primary_color'] ?? '#ff9900';

    // ---- Riesgo (guards defensivos: prod puede no tener las columnas del delta) ----
    $riskLevel   = Schema::hasColumn('injury_reports', 'risk_level') ? $injuryReport->risk_level : null;
    $likelihood  = Schema::hasColumn('injury_reports', 'likelihood') ? strtoupper((string) $injuryReport->likelihood) : '';
    $consequence = Schema::hasColumn('injury_reports', 'consequence') ? (int) $injuryReport->consequence : 0;
    $injRecordable = Schema::hasColumn('injury_reports', 'is_recordable') ? $injuryReport->is_recordable : null;
    $hoursPrior    = Schema::hasColumn('injury_reports', 'hours_worked_prior') ? $injuryReport->hours_worked_prior : null;

    // Tono del nivel de riesgo (icono + texto, no solo color).
    if ($riskLevel === 'Extremo')   { $riskTone = 'danger'; }
    elseif ($riskLevel === 'Alto')  { $riskTone = 'warn';   }
    elseif ($riskLevel === 'Medio') { $riskTone = 'caution';}
    elseif ($riskLevel)             { $riskTone = 'ok';     }
    else                            { $riskTone = 'neutral';}

    // Sub-rótulo del lesionado: departamento · puesto (del propio reporte).
    $injDept = trim((string) ($injuryReport->department ?? ''));
    $injPos  = trim((string) ($injuryReport->position ?? ''));
    $injSub  = $injPos !== '' ? trim($injDept . ($injDept !== '' ? ' · ' : '') . $injPos) : $injDept;

    // Nombre COMPLETO del lesionado. injury_reports solo guarda 'name' (nombre de pila);
    // el/los apellido(s) viven en el USER ligado (lname/lname2), que es la fuente legal del
    // nombre para un documento clínico. Si hay usuario, se compone de ahí; si no, cae al 'name'.
    $injUser = $injuryReport->user;
    if ($injUser) {
        $injFull = trim($injUser->name . ' ' . (string) ($injUser->lname ?? '') . ' ' . (string) ($injUser->lname2 ?? ''));
    } else {
        $injFull = '';
    }
    if ($injFull === '') { $injFull = trim((string) $injuryReport->name); }
    if ($injFull === '') { $injFull = '—'; }

    // (2026-07-20) Lectura del riesgo SIN rejilla: sólo etiquetas de los ejes para el texto
    // "Probable × Mayor = Alto". El NIVEL viene de $riskLevel (columna risk_level, autoridad
    // del trait CalculatesRiskMatrix); ya no se recalcula desde una rejilla hardcodeada.
    $mtxProb = ['A' => 'Casi seguro', 'B' => 'Probable', 'C' => 'Moderado', 'D' => 'Improbable', 'E' => 'Raro'];
    $mtxCons = [1 => 'Insignif.', 2 => 'Menor', 3 => 'Moderada', 4 => 'Mayor', 5 => 'Catastróf.'];
    $hasCell = isset($mtxProb[$likelihood]) && $consequence >= 1 && $consequence <= 5;

    // (2026-07-20) Valores EFECTIVOS (lesión evolucionada por addenda): el ÚLTIMO addendum
    // manda; si no hay tabla/addenda, cae al valor propio del reporte. effective*() vive en
    // HasMedicalAddendums. Guard defensivo por si prod no tiene la tabla addendums.
    $hasAddenda     = Schema::hasTable('addendums');
    $effRecordable  = ($injRecordable === null) ? null : ($hasAddenda ? $injuryReport->effectiveIsRecordable() : (bool) $injRecordable);
    $effDaysRestr   = $hasAddenda ? $injuryReport->effectiveDaysRestricted() : (int) $injuryReport->days_restricted_work;
    $effTreatLevel  = $hasAddenda ? $injuryReport->effectiveTreatmentLevel() : $injuryReport->treatment_level;
    $addendaEvolved = $hasAddenda && $injuryReport->relationLoaded('addendums') && $injuryReport->addendums->count() > 0;

    // Causa raíz estructurada + mecanismo de la lesión (viven en el JSON root_cause_analysis).
    $rca          = is_array($injuryReport->root_cause_analysis) ? $injuryReport->root_cause_analysis : [];
    $rcaCats      = (isset($rca['categories']) && is_array($rca['categories'])) ? array_filter($rca['categories']) : [];
    $rcaMechanism = isset($rca['mechanism']) ? trim((string) $rca['mechanism']) : '';
    $hasRootCause = (isset($rca['immediate']) && trim((string) $rca['immediate']) !== '')
        || (isset($rca['contributing']) && trim((string) $rca['contributing']) !== '')
        || (isset($rca['root']) && trim((string) $rca['root']) !== '')
        || count($rcaCats) > 0;

    // Firma digital: estado (verificada/alterada) + el REGISTRO de la última firma para la
    // cadena CFDI (SHA-256 + UUID del documento + timestamp del SELLADO, no del incidente).
    $sig       = Schema::hasTable('digital_signatures') ? $injuryReport->verifyLatestSignature() : null;
    $sigRecord = Schema::hasTable('digital_signatures') ? $injuryReport->signatures()->latest('id')->first() : null;

    // Folio / UUID.
    $folio   = 'INJ-' . str_pad((string) $injuryReport->id, 4, '0', STR_PAD_LEFT);
    // UUID REAL del documento (el mismo del sello CFDI), no un código derivado del id.
    $footUuid = 'UUID: ' . ($injuryReport->uuid ?: '—') . ' | ' . config('crewcare.doc_version');

    // NOMBRE DE CRÉDITOS del reportante (card 1 de firmas + pie): autor por created_by_id →
    // User::displayName. El lesionado y el testigo NO se tocan (son otras personas reales).
    $__author = ! empty($injuryReport->created_by_id) ? \App\Models\User::find($injuryReport->created_by_id) : null;
    $creditName = $__author ? \App\Models\User::displayName($__author) : ($injuryReport->make_by ?: '—');

    $heroDate = $injuryReport->incident_date ? \Carbon\Carbon::parse($injuryReport->incident_date)->translatedFormat('d M Y') : null;
    $heroTime = $injuryReport->time ? \Carbon\Carbon::parse($injuryReport->time)->format('H:i') : null;
    $heroLoc  = $injuryReport->location ?: ($injuryReport->incident_location ?? '');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brandName }} · {{ __('reports.injury_module') }} · {{ $injuryReport->name }}</title>
@include('componentes._report-v2-head')
</head>
<body>

<div class="ambient"><div class="b b1"></div><div class="b b2"></div></div>

@include('componentes._report-v2-toolbar', ['backRoute' => route('injury_reports.index')])

{{-- (2026-07-20) DOS SALIDAS: selector de vista lite ⇄ completa (estás en la COMPLETA).
     No se imprime. --}}
<div class="ops no-print">
  <div class="viewseg">
    <a class="seg" href="{{ route('injury_reports.show', $injuryReport->id) }}">@include('componentes._icon', ['name' => 'file-text']) {{ __('reports.link_lite_notification') }}</a>
    <span class="seg active">@include('componentes._icon', ['name' => 'shield']) {{ __('reports.link_complete_record') }}</span>
  </div>
  <span class="ops-note">{{ __('reports.injury_module') }}</span>
  @can('tools.inspect')
  <a class="seg" href="{{ route('tools.index', ['origin' => 'accidente', 'origin_id' => $injuryReport->id, 'moment' => 'por_hallazgo']) }}">
     @include('componentes._icon', ['name' => 'wrench']) {{ __('Inspeccionar herramienta involucrada') }}
  </a>
  @endcan
</div>

<div class="stage">
  <article class="sheet">
    {{-- Motor de paginación: <thead> (HERO DE MARCA _doc-hero) + <tfoot> (espaciador) se REPITEN por hoja impresa. --}}
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        'heroImage'    => $injuryReport->main_image_path,
        'heroProject'  => $brandName,
        'heroLocation' => $heroLoc,
        'heroDate'     => $heroDate,
        'heroTime'     => $heroTime,
        'heroModule'   => __('reports.injury_module'),
      ])
    </td></tr></thead>
    <tbody><tr><td>

    {{-- QUICK-READ BAND --}}
    @php
        // Estado registrable EFECTIVO (refleja la evolución por addenda).
        $bandStatus     = $effRecordable === null ? null : ($effRecordable ? __('reports.injury_recordable') : __('reports.injury_not_recordable'));
        $bandStatusTone = $effRecordable === null ? '' : ($effRecordable ? 'warn' : 'ok');
        $bandRiskTone   = in_array($riskLevel, ['Extremo', 'Alto'], true) ? 'warn' : '';
    @endphp
    <div class="band">
      <div class="lead">
        <span class="ic">@include('componentes._icon', ['name' => 'user'])</span>
        <span class="who">
          <span class="lbl">{{ __('reports.label_injured_person') }}</span>
          <span class="val">{{ $injFull }}</span>
          @if($injSub !== '')<span class="sub">{{ $injSub }}</span>@endif
        </span>
      </div>
      <div class="stats">
        @if($injuryReport->body_part)
        <div class="cell"><span class="lbl">{{ __('reports.label_body_part') }}</span><span class="v">{{ $injuryReport->body_part }}</span></div>
        @endif
        <div class="cell"><span class="lbl">{{ __('reports.label_risk') }}</span><span class="v {{ $bandRiskTone }}">{{ $riskLevel ?: '—' }}</span></div>
        <div class="cell"><span class="lbl">{{ __('reports.injury_band_status') }}</span><span class="v {{ $bandStatusTone }}">{{ $bandStatus ?: '—' }}</span></div>
      </div>
    </div>

    <div class="body">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — {{ __('reports.injury_module') }} — {{ $injuryReport->name }}</h1>

      {{-- DATOS DEL INCIDENTE --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h2>{{ __('reports.injury_module') }}</h2><span class="line"></span></div>
        <div class="panel"><div class="facts">
          <div class="fact"><div class="k">{{ __('reports.label_folio') }}</div><div class="v mono">{{ $folio }}</div></div>
          <div class="fact"><div class="k">{{ __('reports.label_date') }}</div><div class="v mono">{{ $heroDate ?: '—' }}{{ $heroTime ? ' · ' . $heroTime : '' }}</div></div>
          @if($injuryReport->latitude && $injuryReport->longitude)
          <div class="fact"><div class="k">{{ __('reports.label_gps_location') }}</div><div class="v mono">{{ number_format((float)$injuryReport->latitude, 4) }}, {{ number_format((float)$injuryReport->longitude, 4) }}</div></div>
          @endif
          @if($heroLoc)
          <div class="fact"><div class="k">{{ __('reports.label_gps_location') }}</div><div class="v">{{ $heroLoc }}</div></div>
          @endif
          @if($effRecordable !== null)
          <div class="fact"><div class="k">OSHA</div><div class="v">{{ $effRecordable ? __('reports.injury_recordable') : __('reports.injury_not_recordable') }}</div></div>
          @endif
          @if($effDaysRestr > 0)
          <div class="fact"><div class="k">{{ __('reports.label_days_restricted') }}</div><div class="v">{{ $effDaysRestr }}</div></div>
          @endif
          <div class="fact"><div class="k">{{ __('reports.label_hospital') }}</div>
            @can('viewMedical', $injuryReport)<div class="v">{{ $injuryReport->hospital ?: '—' }}</div>@else<div class="v restricted">{{ __('reports.label_restricted_medical') }}</div>@endcan
          </div>
          @if($injuryReport->make_by)
          <div class="fact"><div class="k">{{ __('reports.label_by') }}</div><div class="v">{{ $injuryReport->make_by }}</div></div>
          @endif
        </div></div>
      </section>

      {{-- DESCRIPCIÓN / CAUSA --}}
      <section class="sec"><div class="two">
        <div>
          <div class="sec-h"><span class="bar"></span><h2>{{ __('reports.injury_section_what_happened') }}</h2></div>
          <p class="desc">{{ $injuryReport->what_happened ?: __('reports.injury_empty_what_happened') }}</p>
        </div>
        <div>
          <div class="sec-h"><span class="bar"></span><h2>{{ __('reports.injury_section_what_caused') }}</h2></div>
          <p class="desc">{{ $injuryReport->what_caused ?: __('reports.injury_empty_what_caused') }}</p>
        </div>
      </div></section>

      {{-- MECANISMO DE LA LESIÓN (item 2): el eslabón "cómo la persona entró en contacto con el
           daño", distinto de QUÉ PASÓ y de QUÉ LO CAUSÓ. Vive en root_cause_analysis[mechanism]. --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg><h2>{{ __('reports.injury_section_mechanism') }}</h2></div>
        <p class="desc">{{ $rcaMechanism !== '' ? $rcaMechanism : __('reports.injury_empty_mechanism') }}</p>
      </section>

      {{-- ANÁLISIS DE CAUSA RAÍZ ESTRUCTURADO (item 1): categoría · inmediata · contribuyentes ·
           raíz. Se guarda (JSON root_cause_analysis) desde 2026-07-09; ahora SÍ se pinta. --}}
      @if($hasRootCause)
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg><h2>{{ __('reports.injury_section_root_cause') }}</h2><span class="line"></span></div>
        @if(count($rcaCats))
        <div class="chips" style="margin-bottom:10px">
          @foreach($rcaCats as $cat)<span class="chip">{{ $cat }}</span>@endforeach
        </div>
        @endif
        <div class="panel" style="display:flex;flex-direction:column;gap:10px">
          @if(isset($rca['immediate']) && trim((string) $rca['immediate']) !== '')
          <div><div class="k" style="font-size:.58rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:3px">{{ __('reports.label_immediate_cause') }}</div><p class="desc" style="margin:0">{{ is_array($rca['immediate']) ? implode(', ', $rca['immediate']) : $rca['immediate'] }}</p></div>
          @endif
          @if(isset($rca['contributing']) && trim((string) $rca['contributing']) !== '')
          <div><div class="k" style="font-size:.58rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:3px">{{ __('reports.label_contributing_factors') }}</div><p class="desc" style="margin:0">{{ is_array($rca['contributing']) ? implode(', ', $rca['contributing']) : $rca['contributing'] }}</p></div>
          @endif
          @if(isset($rca['root']) && trim((string) $rca['root']) !== '')
          <div><div class="k" style="font-size:.58rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:3px">{{ __('reports.label_root_cause') }}</div><p class="desc" style="margin:0">{{ is_array($rca['root']) ? implode(', ', $rca['root']) : $rca['root'] }}</p></div>
          @endif
        </div>
      </section>
      @endif

      {{-- PERSONAS Y ROLES (item 3): reportante · primer respondiente · testigo, hoy difuminados,
           ahora SEPARADOS. Reportante = make_by (server-side); primer respondiente = treatment_by;
           testigo(s) = relación witnesses. Pueden coincidir; el documento los distingue. --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><h2>{{ __('reports.injury_section_roles') }}</h2><span class="line"></span></div>
        <div class="panel"><div class="facts">
          <div class="fact"><div class="k">{{ __('reports.label_reporter') }}</div><div class="v">{{ $injuryReport->make_by ?: '—' }}</div><div style="font-size:.62rem;color:var(--faint);margin-top:2px">{{ __('reports.injury_role_reporter_hint') }}</div></div>
          <div class="fact"><div class="k">{{ __('reports.label_first_responder') }}</div><div class="v">{{ $injuryReport->treatment_by ?: '—' }}</div><div style="font-size:.62rem;color:var(--faint);margin-top:2px">{{ __('reports.injury_role_first_responder_hint') }}</div></div>
          <div class="fact"><div class="k">{{ __('reports.label_witness') }}</div><div class="v">{{ (Schema::hasTable('witnesses') && $injuryReport->witnesses->count()) ? $injuryReport->witnesses->pluck('name')->implode(', ') : '—' }}</div><div style="font-size:.62rem;color:var(--faint);margin-top:2px">{{ __('reports.injury_role_witness_hint') }}</div></div>
        </div></div>
      </section>

      {{-- TIPO DE LESIÓN --}}
      @if(is_array($injuryReport->injury_type) && count($injuryReport->injury_type))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ __('reports.injury_section_type') }}</h2></div>
        <div class="chips">@foreach($injuryReport->injury_type as $t)<span class="chip">{{ $t }}</span>@endforeach</div>
      </section>
      @endif

      {{-- RIESGO: chips + matriz 5×5 --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg><h2>{{ __('reports.label_risk') }}</h2><span class="line"></span></div>
        <div class="riskrow">
          <span class="rc {{ $riskTone }}">@include('componentes._icon', ['name' => 'alert-triangle'])<span class="t"><span class="l">{{ __('reports.label_risk') }}</span><span class="v">{{ $riskLevel ?: '—' }}</span></span></span>
          @if($injRecordable !== null)
          <span class="rc {{ $injRecordable ? 'warn' : 'ok' }}">@include('componentes._icon', ['name' => $injRecordable ? 'clipboard-list' : 'check-circle'])<span class="t"><span class="l">OSHA 300</span><span class="v">{{ $injRecordable ? __('reports.injury_recordable') : __('reports.injury_not_recordable') }}</span></span></span>
          @endif
          @if($hoursPrior !== null)
          <span class="rc {{ $hoursPrior > 12 ? 'danger' : 'neutral' }}">@include('componentes._icon', ['name' => 'clock'])<span class="t"><span class="l">{{ __('reports.label_hours_worked_prior') }}</span><span class="v">{{ $hoursPrior }} h</span></span></span>
          @endif
        </div>
        {{-- (2026-07-20) SIN rejilla 5×5 en el documento (item 5): sólo el RESULTADO y su lectura
             ("Probable × Mayor = Alto") + el nivel. La matriz sigue como herramienta de cálculo en
             el FORM de captura, NO en el PDF legal (y con esto muere la rejilla hardcodeada). El
             nivel viene de risk_level (columna, autoridad del trait CalculatesRiskMatrix). --}}
        <div class="panel">
          <div class="mlegend" style="margin-top:0">
            @if($hasCell)
              <span style="color:var(--text);font-size:.88rem">
                <b>{{ $mtxProb[$likelihood] }}</b> ({{ __('reports.label_probability') }})
                × <b>{{ $mtxCons[$consequence] }}</b> ({{ __('reports.label_consequence') }})
                = <b style="color:{{ $riskTone === 'danger' ? 'var(--r-5)' : ($riskTone === 'warn' ? 'var(--r-4)' : ($riskTone === 'caution' ? 'var(--r-3)' : ($riskTone === 'ok' ? 'var(--r-1)' : 'var(--text)'))) }}">{{ $riskLevel ?: '—' }}</b>
              </span>
            @else
              <span style="color:var(--muted)">{{ __('reports.label_risk') }}: <b style="color:var(--text)">{{ $riskLevel ?: '—' }}</b></span>
            @endif
          </div>
        </div>
      </section>

      {{-- EPP (gated) --}}
      @if(is_array($injuryReport->ppe_details) && count(array_filter($injuryReport->ppe_details)))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><h2>{{ __('reports.injury_section_ppe') }}</h2></div>
        @can('viewMedical', $injuryReport)
        @php
          $ppe = $injuryReport->ppe_details;
          $wornLabels = ['si' => __('reports.label_yes'), 'no' => __('reports.label_no'), 'na' => __('reports.label_na')];
          $wornRaw = isset($ppe['worn']) ? $ppe['worn'] : null;
          $wornLabel = ($wornRaw !== null && isset($wornLabels[$wornRaw])) ? $wornLabels[$wornRaw] : $wornRaw;
          $ppeTypes = (isset($ppe['types']) && is_array($ppe['types'])) ? implode(', ', $ppe['types']) : (isset($ppe['types']) ? $ppe['types'] : '');
        @endphp
        <div class="panel" style="font-size:.84rem;display:flex;flex-wrap:wrap;gap:6px 22px">
          @if($wornLabel !== null && $wornLabel !== '')<span><strong>{{ __('reports.label_ppe_worn') }}:</strong> {{ $wornLabel }}</span>@endif
          @if($ppeTypes !== '')<span><strong>{{ __('reports.label_ppe_types') }}:</strong> {{ $ppeTypes }}</span>@endif
          @if(!empty($ppe['condition']))<span><strong>{{ __('reports.label_condition') }}:</strong> {{ $ppe['condition'] }}</span>@endif
        </div>
        @else<p class="restricted">{{ __('reports.label_restricted_medical') }}</p>@endcan
      </section>
      @endif

      {{-- TRATAMIENTO / PREVENCIÓN --}}
      <section class="sec"><div class="two">
        <div>
          <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2v2M5 2v2M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/></svg><h2>{{ __('reports.label_treatment') }}</h2></div>
          <div class="panel" style="font-size:.84rem">
            @can('viewMedical', $injuryReport)
              <p style="margin:0 0 4px"><strong>{{ __('reports.label_type') }}:</strong> {{ $injuryReport->treatment_type ?: '—' }}</p>
              {{-- (2026-07-20) Primer respondiente se muestra en PERSONAS Y ROLES. Aquí el NIVEL DE
                   ATENCIÓN efectivo (refleja la evolución por addenda). --}}
              @if($effTreatLevel)
              <p style="margin:0 0 4px"><strong>{{ __('reports.label_treatment_level') }}:</strong> {{ __('reports.treatment_' . ($effTreatLevel === 'medical_treatment' ? 'medical' : $effTreatLevel)) }}</p>
              @endif
              @if($injuryReport->treatment_comments)<p style="margin:6px 0 0;color:var(--muted)">{{ $injuryReport->treatment_comments }}</p>@endif
              {{-- PASO B — CÉDULA PROFESIONAL de quien capturó el parte, SI es médico.
                   Aditivo puro: ni una columna nueva en `injury_reports`, así que el sello
                   SHA-256 de los reportes ya firmados no cambia (el hash usa
                   attributesToArray(): columnas propias, nunca relaciones).
                   Se etiqueta como "Reporte capturado por" y NO como médico tratante: el
                   tratante vive en texto libre (`hospital`/`treatment_by`) y no hay forma de
                   afirmar que sean la misma persona. El badge solo aparece si quien capturó
                   tiene rol medic Y cédula registrada.
                   Sin chips a propósito: esto se imprime (window.print) y en papel un chip
                   de color no dice nada; el número y la fecha del cotejo sí. --}}
              @php
                $__capturer = $injuryReport->createdBy;
                $__capCred  = ($__capturer && \App\Models\MedicCredential::supportsCredentials() && $__capturer->isMedic())
                    ? $__capturer->medicCredential : null;
              @endphp
              @if($__capCred)
                <p style="margin:6px 0 0">
                  <strong>{{ __('Reporte capturado por') }}:</strong> {{ $__capturer->fullName() }}
                  · {{ __('Cédula prof.') }} {{ $__capCred->cedula }}
                  @if($__capCred->isVerified())
                    ({{ __('verificada') }}@if($__capCred->verified_at) {{ $__capCred->verified_at->format('d/m/Y') }}@endif)
                  @else
                    <span style="color:var(--warn,#c2410c);font-weight:700">({{ __('SIN VERIFICAR') }})</span>
                  @endif
                </p>
              @endif
            @else<p class="restricted">{{ __('reports.label_restricted_medical') }}</p>@endcan
          </div>
        </div>
        <div>
          <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg><h2>{{ __('reports.label_prevention') }}</h2></div>
          <div class="panel" style="font-size:.84rem">{{ $injuryReport->preventions ?: __('reports.injury_empty_preventions') }}</div>
        </div>
      </div></section>

      {{-- ACCIONES CORRECTIVAS (PDCA) --}}
      @if(Schema::hasTable('action_items') && $injuryReport->actionItems->count())
      @php $stL = ['open' => __('reports.status_open'), 'in_progress' => __('reports.status_in_progress'), 'closed' => __('reports.status_closed')]; @endphp
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg><h2>{{ __('reports.section_corrective_actions') }}</h2></div>
        <div style="display:flex;flex-direction:column;gap:8px">
          @foreach($injuryReport->actionItems as $item)
          @php $st = $item->status; $ov = $item->isOverdue(); @endphp
          <div class="panel" style="{{ $ov ? 'border-color:color-mix(in srgb,var(--danger) 45%,transparent)' : '' }}">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:start">
              <p style="margin:0;font-size:.84rem">{{ $item->description }}</p>
              <span class="chip {{ $st === 'closed' ? 'ok' : ($st === 'open' ? 'warn' : '') }}" style="flex:none">{{ isset($stL[$st]) ? $stL[$st] : $st }}</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:4px 18px;margin-top:6px;font-size:.72rem;color:var(--muted)">
              <span><strong>{{ __('reports.label_responsible') }}:</strong> {{ $item->owner ? $item->owner->name : '—' }}</span>
              <span class="{{ $ov ? '' : '' }}" style="{{ $ov ? 'color:var(--danger);font-weight:700' : '' }}"><strong>{{ __('reports.label_due') }}:</strong> {{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->translatedFormat('d M Y') : '—' }}{{ $ov ? ' · ' . __('reports.label_overdue_caps') : '' }}</span>
            </div>
          </div>
          @endforeach
        </div>
      </section>
      @endif

      {{-- NORMAS APLICABLES --}}
      @if(Schema::hasTable('standardables') && $injuryReport->standards->count())
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ __('reports.section_applicable_standards') }}</h2></div>
        <div class="chips">
          @foreach($injuryReport->standards as $std)
          <span class="chip"><span class="badge badge-{{ $std->regulation_badge }}">{{ $std->regulation_badge }}</span> {{ $std->category_name_localized }} <span style="color:var(--faint);font-family:var(--mono);font-size:.7rem">{{ $std->regulation_code }}</span></span>
          @endforeach
        </div>
      </section>
      @endif

      {{-- TESTIGOS --}}
      @if(Schema::hasTable('witnesses') && $injuryReport->witnesses->count())
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg><h2>{{ __('reports.section_witnesses') }}</h2></div>
        {{-- (2026-07-20) Testigos con TELÉFONO + DECLARACIÓN (antes sólo el nombre). Va en el
             expediente completo (gateado por viewMedical); la salida lite NO los muestra. --}}
        <div style="display:flex;flex-direction:column;gap:8px">
          @foreach($injuryReport->witnesses as $w)
          <div class="panel">
            <div style="display:flex;flex-wrap:wrap;gap:4px 18px;align-items:baseline">
              <span style="font-weight:700">@include('componentes._icon', ['name' => 'user']) {{ $w->name }}</span>
              @if($w->phone)<span style="font-size:.76rem;color:var(--muted)"><strong>{{ __('reports.label_phone') }}:</strong> {{ $w->phone }}</span>@endif
            </div>
            @if($w->statement)<p class="desc" style="margin:8px 0 0;font-size:.84rem"><span style="color:var(--muted);font-size:.66rem;text-transform:uppercase;letter-spacing:.08em">{{ __('reports.label_witness_statement') }}</span><br>{{ $w->statement }}</p>@endif
          </div>
          @endforeach
        </div>
      </section>
      @endif

      {{-- NOTIFICACIÓN A AUTORIDADES --}}
      @if(Schema::hasColumn('injury_reports', 'authority_notifications') && is_array($injuryReport->authority_notifications) && count($injuryReport->authority_notifications))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg><h2>{{ __('reports.section_authority_notifications') }}</h2></div>
        <div class="panel" style="padding:4px 8px;overflow-x:auto">
          <table class="tbl">
            <thead><tr><th>{{ __('reports.label_authority') }}</th><th>{{ __('reports.label_date') }}</th><th>{{ __('reports.label_notified_by') }}</th><th>{{ __('reports.label_folio') }}</th></tr></thead>
            <tbody>
            @foreach($injuryReport->authority_notifications as $note)
              <tr><td style="font-weight:700">{{ $note['authority'] ?? '—' }}</td><td>{{ !empty($note['notified_at']) ? \Carbon\Carbon::parse($note['notified_at'])->translatedFormat('d M Y') : '—' }}</td><td>{{ $note['notified_by'] ?? '—' }}</td><td class="mono">{{ $note['folio_number'] ?? '—' }}</td></tr>
            @endforeach
            </tbody>
          </table>
        </div>
      </section>
      @endif

      {{-- EVIDENCIA FOTOGRÁFICA --}}
      @php
        $gallery = [];
        if ($injuryReport->main_image_path) { $gallery[] = $injuryReport->main_image_path; }
        if (is_array($injuryReport->additional_images_paths)) { $gallery = array_merge($gallery, $injuryReport->additional_images_paths); }
      @endphp
      @if(count($gallery))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg><h2>{{ __('reports.injury_section_photo_evidence') }}</h2></div>
        <div class="photos">
          @foreach($gallery as $i => $img)
          <div class="photo"><img src="{{ $img }}" loading="lazy" alt="{{ __('reports.injury_section_photo_evidence') }} {{ $i + 1 }}"><span class="cap">{{ __('reports.injury_section_photo_evidence') }} {{ $i + 1 }}</span></div>
          @endforeach
        </div>
      </section>
      @endif

      {{-- ANEXOS MÉDICOS (addenda) — cada uno como anexo firmado con su propio sello (item 8).
           Va DESPUÉS del cuerpo del expediente y ANTES de la integridad del documento base. --}}
      @include('componentes._addendum-annex', ['injuryReport' => $injuryReport, 'folio' => $folio])

      {{-- FIRMAS E INTEGRIDAD (item 10; antes "Risk Assessment") --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg><h2>{{ __('reports.label_signatures_integrity') }}</h2><span class="line"></span></div>
        <div class="sign">
          {{-- Reportante (quien levantó el reporte): NOMBRE DE CRÉDITOS. --}}
          <div class="sig"><div class="who">{{ $creditName }}</div><div class="role">{{ __('reports.label_reporter') }}</div></div>
          {{-- Lesionado + ACEPTACIÓN DE LA NARRATIVA (item 4): la firma declara que reconoce que
               los hechos narrados son correctos (no sólo "fue notificado"). --}}
          <div class="sig"><div class="who">{{ $injFull }}</div><div class="role">{{ __('reports.label_injured_person') }}</div><div style="font-size:.68rem;color:var(--muted);margin-top:8px;font-style:italic">{{ __('reports.injury_acceptance_declaration') }}</div></div>
          {{-- TESTIGO. Cierra los tres roles que el documento debe poder acreditar
               (reportante · lesionado · testigo). Si hay testigos capturados se nombra al
               primero; si no, la línea queda igualmente para firmarse a mano. --}}
          <div class="sig"><div class="who">{{ optional($injuryReport->witnesses->first())->name ?: '—' }}</div><div class="role">{{ __('reports.label_witness') }}</div></div>
        </div>
        {{-- EL HUECO DE LA FIRMA AUTÓGRAFA. No es un aviso de "pendiente": el espacio en
             blanco sobre cada línea ES el hueco, ya impreso y ya firmable a mano hoy. Cuando
             llegue el módulo de firma digital, se dibujará ahí. El sello SHA de abajo es otra
             cosa y no espera a nadie. --}}
        <div style="font-size:.66rem;color:var(--faint);margin-top:8px;font-style:italic">{{ __('reports.sign_space_hint') }}</div>

        {{-- Integridad + sello estilo CFDI (banner + sello digital + cadena original + metadatos).
             Compartido con la salida lite para que la firma se vea igual en ambas. --}}
        @include('componentes._seal-cfdi', ['injuryReport' => $injuryReport])
      </section>
    </div>
    </td></tr></tbody>
    <tfoot><tr><td><div class="footer-spacer"></div></td></tr></tfoot>
    </table>

    @include('componentes._report-v2-foot', [
      'footPreparedName' => $creditName,
      'footPreparedMeta' => __('reports.label_reporter'), // pie SIN fecha (owner 2026-08)
      'footUuid'         => $footUuid,
    ])
</body>
</html>
