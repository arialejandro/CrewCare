{{-- ============================================================================================
     INJURY REPORT — SALIDA LITE (NOTIFICACIÓN A PRODUCCIÓN). "Cinematic Dark Glass".
     Ruta: GET /accident/{id} → InjuryReportController@show (abierta a injury.view).

     Es un DOCUMENTO DISTINTO del expediente completo (admin/injuryreport.blade.php), NO el mismo
     con secciones ocultas: un PDF exportado no respeta permisos, así que Producción recibe SÓLO
     qué · cuándo · dónde · quién · estado · nivel de riesgo. SIN declaración de testigos, SIN
     detalle clínico, SIN causa raíz de fondo, SIN addendum. Su export (window.print) es libre.

     El expediente completo (causa raíz, testigos, atención, anexos) se sirve en
     /accident/{id}/completo, gateado por la policy viewMedical para verlo Y para imprimirlo.

     Recibe: $injuryReport, $canComplete (bool: ¿el que mira pasa viewMedical? → muestra enlace).
     PHP 7.4. i18n reports.*. Vista STANDALONE. Contrato de maquetado: abre stage+sheet+table; el
     parcial _report-v2-foot cierra </article></div> y emite el pie.
============================================================================================ --}}
@php
    use Illuminate\Support\Facades\Schema;
    if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); }
    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = $brand['brand_name'] ?? 'CrewCare';
    $primary   = $brand['primary_color'] ?? '#ff9900';

    $riskLevel   = Schema::hasColumn('injury_reports', 'risk_level') ? $injuryReport->risk_level : null;
    $likelihood  = Schema::hasColumn('injury_reports', 'likelihood') ? strtoupper((string) $injuryReport->likelihood) : '';
    $consequence = Schema::hasColumn('injury_reports', 'consequence') ? (int) $injuryReport->consequence : 0;

    if ($riskLevel === 'Extremo')   { $riskTone = 'danger'; }
    elseif ($riskLevel === 'Alto')  { $riskTone = 'warn';   }
    elseif ($riskLevel === 'Medio') { $riskTone = 'caution';}
    elseif ($riskLevel)             { $riskTone = 'ok';     }
    else                            { $riskTone = 'neutral';}

    // Etiquetas de los ejes SÓLO para la lectura (sin rejilla).
    $mtxProb = ['A' => 'Casi seguro', 'B' => 'Probable', 'C' => 'Moderado', 'D' => 'Improbable', 'E' => 'Raro'];
    $mtxCons = [1 => 'Insignif.', 2 => 'Menor', 3 => 'Moderada', 4 => 'Mayor', 5 => 'Catastróf.'];
    $hasCell = isset($mtxProb[$likelihood]) && $consequence >= 1 && $consequence <= 5;

    // Sub-rótulo del lesionado: departamento · puesto.
    $injDept = trim((string) ($injuryReport->department ?? ''));
    $injPos  = trim((string) ($injuryReport->position ?? ''));
    $injSub  = $injPos !== '' ? trim($injDept . ($injDept !== '' ? ' · ' : '') . $injPos) : $injDept;

    // Nombre COMPLETO del lesionado (fuente legal = USER ligado; cae al 'name' del reporte).
    $injUser = $injuryReport->user;
    if ($injUser) {
        $injFull = trim($injUser->name . ' ' . (string) ($injUser->lname ?? '') . ' ' . (string) ($injUser->lname2 ?? ''));
    } else {
        $injFull = '';
    }
    if ($injFull === '') { $injFull = trim((string) $injuryReport->name); }
    if ($injFull === '') { $injFull = '—'; }

    // Cadena CFDI (misma que el expediente): SHA-256 + UUID del documento + timestamp del sellado.
    $sig       = Schema::hasTable('digital_signatures') ? $injuryReport->verifyLatestSignature() : null;
    $sigRecord = Schema::hasTable('digital_signatures') ? $injuryReport->signatures()->latest('id')->first() : null;

    $folio    = 'INJ-' . str_pad((string) $injuryReport->id, 4, '0', STR_PAD_LEFT);
    // UUID REAL (el mismo del sello); conserva el sufijo NOTIFICACIÓN que distingue a la salida lite.
    $footUuid = 'UUID: ' . ($injuryReport->uuid ?: '—') . ' | NOTIFICACIÓN';

    // NOMBRE DE CRÉDITOS del reportante (card 1 + pie): autor por created_by_id → displayName.
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
<title>{{ $brandName }} · {{ __('reports.injury_lite_subtitle') }} · {{ $injuryReport->name }}</title>
@include('componentes._report-v2-head')
</head>
<body>

<div class="ambient"><div class="b b1"></div><div class="b b2"></div></div>

@include('componentes._report-v2-toolbar', ['backRoute' => route('injury_reports.index')])

{{-- Selector de vista lite ⇄ completa (estás en la LITE). El paso a la completa sólo aparece
     para quien pase viewMedical. No se imprime. --}}
<div class="ops no-print">
  <div class="viewseg">
    <span class="seg active">@include('componentes._icon', ['name' => 'file-text']) {{ __('reports.link_lite_notification') }}</span>
    @if($canComplete)<a class="seg" href="{{ route('injury_reports.show_complete', $injuryReport->id) }}">@include('componentes._icon', ['name' => 'shield']) {{ __('reports.link_complete_record') }}</a>@endif
  </div>
  <span class="ops-note">{{ __('reports.injury_lite_subtitle') }}</span>
</div>

<div class="stage">
  <article class="sheet">
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        'heroImage'    => $injuryReport->main_image_path,
        'heroProject'  => $brandName,
        'heroLocation' => $heroLoc,
        'heroDate'     => $heroDate,
        'heroTime'     => $heroTime,
        'heroModule'   => __('reports.injury_lite_subtitle'),
      ])
    </td></tr></thead>
    <tbody><tr><td>

    {{-- QUICK-READ BAND: quién · riesgo · estado. --}}
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
        <div class="cell"><span class="lbl">{{ __('reports.label_risk') }}</span><span class="v {{ in_array($riskLevel, ['Extremo','Alto'], true) ? 'warn' : '' }}">{{ $riskLevel ?: '—' }}</span></div>
        <div class="cell"><span class="lbl">{{ __('reports.injury_band_status') }}</span><span class="v">{{ __('reports.injury_status_under_care') }}</span></div>
      </div>
    </div>

    <div class="body">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — {{ __('reports.injury_lite_subtitle') }} — {{ $injuryReport->name }}</h1>

      {{-- NOTA: esto es la notificación; el expediente clínico va aparte. --}}
      <div class="panel" style="margin-bottom:18px;font-size:.8rem;color:var(--muted);display:flex;gap:8px;align-items:flex-start">
        <span style="color:var(--brand);flex:none">@include('componentes._icon', ['name' => 'info'])</span>
        <span>{{ __('reports.injury_lite_note') }}</span>
      </div>

      {{-- DATOS DEL INCIDENTE: qué / cuándo / dónde / quién. --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h2>{{ __('reports.injury_module') }}</h2><span class="line"></span></div>
        <div class="panel"><div class="facts">
          <div class="fact"><div class="k">{{ __('reports.label_folio') }}</div><div class="v mono">{{ $folio }}</div></div>
          <div class="fact"><div class="k">{{ __('reports.label_date') }}</div><div class="v mono">{{ $heroDate ?: '—' }}{{ $heroTime ? ' · ' . $heroTime : '' }}</div></div>
          @if($heroLoc)
          <div class="fact"><div class="k">{{ __('reports.label_gps_location') }}</div><div class="v">{{ $heroLoc }}</div></div>
          @endif
          @if($injuryReport->body_part)
          <div class="fact"><div class="k">{{ __('reports.label_body_part') }}</div><div class="v">{{ $injuryReport->body_part }}</div></div>
          @endif
          @if($injuryReport->make_by)
          <div class="fact"><div class="k">{{ __('reports.label_reporter') }}</div><div class="v">{{ $injuryReport->make_by }}</div></div>
          @endif
        </div></div>
      </section>

      {{-- QUÉ PASÓ (descripción del hecho; sin análisis clínico ni de causa). --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ __('reports.injury_section_what_happened') }}</h2></div>
        <p class="desc">{{ $injuryReport->what_happened ?: __('reports.injury_empty_what_happened') }}</p>
      </section>

      {{-- NIVEL DE RIESGO: resultado + lectura, SIN rejilla. --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg><h2>{{ __('reports.label_risk') }}</h2><span class="line"></span></div>
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

      {{-- FIRMAS E INTEGRIDAD: cadena CFDI (ambas salidas la llevan). --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg><h2>{{ __('reports.label_signatures_integrity') }}</h2><span class="line"></span></div>
        <div class="sign">
          <div class="sig"><div class="who">{{ $creditName }}</div><div class="role">{{ __('reports.label_reporter') }}</div></div>
          <div class="sig"><div class="who">{{ $injFull }}</div><div class="role">{{ __('reports.label_injured_person') }}</div></div>
        </div>
        {{-- Integridad + sello estilo CFDI (mismo partial que la completa). --}}
        @include('componentes._seal-cfdi', ['injuryReport' => $injuryReport])
      </section>
    </div>
    </td></tr></tbody>
    <tfoot><tr><td><div class="footer-spacer"></div></td></tr></tfoot>
    </table>

    @include('componentes._report-v2-foot', [
      'footPreparedName' => $injuryReport->make_by ?: '—',
      'footPreparedMeta' => __('reports.label_reporter') . ($injuryReport->make_date ? ' · ' . \Carbon\Carbon::parse($injuryReport->make_date)->format('d M Y') : ''),
      'footUuid'         => $footUuid,
    ])
</body>
</html>
