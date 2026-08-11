{{-- ============================================================================================
     SCOUTING / LOCATION RISK ASSESSMENT — v2 "Cinematic Dark Glass". Documento STANDALONE (no layout).
     Ruta: GET /scoutings/{id} → name scoutings.show → ScoutingReportController@show (var única $report).
     Chrome compartido: _report-v2-head (fuentes+CSS+motor impresión) · _report-v2-toolbar · _report-v2-foot.
     DOS CARAS: vidrio cinematográfico en pantalla / documento blanco firmable al Exportar PDF (data-view=print).
     PHP 7.4 (sin match/enums/nullsafe/promoción). Guards Schema::hasColumn/hasTable. i18n reports.* (?lang=en|es).
     La matriz 5×5 (rejilla Amazon MGM EXACTA, App\Traits\CalculatesRiskMatrix / riskRating) es el HOGAR
     natural de este reporte: se resalta la celda del PEOR peligro (Prob×Cons) de la tabla de riesgos.
     Ghost del diseño anterior: admin/scoutings/show-legacy.blade.php (revertir = renombrar). NO tocar amazon.blade.php.
============================================================================================ --}}
@php
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\Lang;
    if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); }
    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = $brand['brand_name'] ?? 'CrewCare';
    $primary   = $brand['primary_color'] ?? '#ff9900';
    $en        = app()->getLocale() === 'en';

    // ---- Normaliza cada fila de risk_assessment (tolerando el esquema viejo), igual que el legacy ----
    $oldRiskMap = ['Bajo' => 'L', 'Medio' => 'M', 'Alto' => 'H', 'Extremo' => 'E'];
    $rows = [];
    foreach ((is_array($report->risk_assessment) ? $report->risk_assessment : []) as $h) {
        $rows[] = [
            'hazard'      => $h['hazard'] ?? ($h['label'] ?? ''),
            'likelihood'  => $h['likelihood'] ?? null,
            'consequence' => $h['consequence'] ?? null,
            'rating'      => $h['rating'] ?? ($oldRiskMap[$h['risk'] ?? ''] ?? null),
            'control'     => $h['control'] ?? ($h['note'] ?? null),
            'residual'    => $h['residual'] ?? null,
            'personnel'   => $h['personnel'] ?? null,
            'badge'       => $h['badge'] ?? null,
            'code'        => $h['code'] ?? null,
            'url'         => $h['url'] ?? null,
            // (2026-07-24) Peligro SIN evento del catálogo. Se marca en el propio documento, no
            // sólo en la base: quien lo lee tiene que saber que ese renglón no se va a poder
            // contrastar en el reporte de wrap. Las filas viejas (anteriores a la marca) no
            // llevan la clave y por eso no se acusan de nada.
            'sinclas'     => ! empty($h['unclassified']),
        ];
    }

    // Clasificación L/M/H/E → palabra i18n (reutiliza rating_*).
    $ratingWord = ['L' => __('reports.rating_low'), 'M' => __('reports.rating_medium'), 'H' => __('reports.rating_high'), 'E' => __('reports.rating_very_high')];

    // ---- Matriz 5×5 Amazon MGM (IDÉNTICA a ScoutingReportController@riskRating / CalculatesRiskMatrix) ----
    $mtxRows  = ['A', 'B', 'C', 'D', 'E'];
    $mtxProb  = $en
        ? ['A' => 'Almost certain', 'B' => 'Likely', 'C' => 'Moderate', 'D' => 'Unlikely', 'E' => 'Rare']
        : ['A' => 'Casi seguro', 'B' => 'Probable', 'C' => 'Moderado', 'D' => 'Improbable', 'E' => 'Raro'];
    $mtxCons  = $en
        ? [1 => 'Insignif.', 2 => 'Minor', 3 => 'Moderate', 4 => 'Major', 5 => 'Catastr.']
        : [1 => 'Insignif.', 2 => 'Menor', 3 => 'Moderada', 4 => 'Mayor', 5 => 'Catastróf.'];
    $mtxGrid  = [
        'A' => ['M','H','H','E','E'], 'B' => ['M','M','H','H','E'], 'C' => ['L','M','M','H','E'],
        'D' => ['L','M','M','H','H'], 'E' => ['L','L','M','M','H'],
    ];
    $mtxColor = ['L' => 'var(--r-1)', 'M' => 'var(--r-3)', 'H' => 'var(--r-4)', 'E' => 'var(--r-5)'];

    // Peor peligro del sitio: fila con la clasificación más severa (E>H>M>L) que tenga Prob+Cons válidas.
    $sevOrder  = ['L' => 1, 'M' => 2, 'H' => 3, 'E' => 4];
    $mtxLk     = '';
    $mtxCs     = 0;
    $worstRate = null;
    $worstSev  = 0;
    foreach ($rows as $r) {
        $lk = strtoupper((string) ($r['likelihood'] ?? ''));
        $cs = (int) ($r['consequence'] ?? 0);
        // Preferimos derivar de la rejilla (autoridad); si no hay Prob+Cons, cae al rating guardado.
        $rt = (isset($mtxGrid[$lk]) && $cs >= 1 && $cs <= 5) ? $mtxGrid[$lk][$cs - 1] : ($r['rating'] ?? null);
        $sev = isset($sevOrder[$rt]) ? $sevOrder[$rt] : 0;
        if ($sev > $worstSev) {
            $worstSev  = $sev;
            $worstRate = $rt;
            if (isset($mtxGrid[$lk]) && $cs >= 1 && $cs <= 5) { $mtxLk = $lk; $mtxCs = $cs; }
        }
    }
    $hasCell  = isset($mtxGrid[$mtxLk]) && $mtxCs >= 1 && $mtxCs <= 5;
    $riskWord = $worstRate ? ($ratingWord[$worstRate] ?? $worstRate) : null;
    $riskWarn = in_array($worstRate, ['H', 'E'], true);

    // ---- Firma digital / no-repudio ----
    $sigVerified = Schema::hasTable('digital_signatures') ? $report->verifyLatestSignature() : null;
    $latestSig   = ($sigVerified !== null) ? $report->signatures()->latest('id')->first() : null;

    // ---- Folio / UUID ----
    $folio    = 'SCOUT-' . str_pad((string) $report->id, 4, '0', STR_PAD_LEFT);
    $footUuid = 'UUID: ' . $brandName . '-SCOUT-' . (16210 + $report->id) . '-' . \Carbon\Carbon::parse($report->created_at)->format('dmY') . ' | ' . config('crewcare.doc_version');

    // ---- Hero: proyecto = nombre canónico de marca (branding global, NO el production_name libre
    //      que es inconsistente/vacío); locación = nombre; fecha = shoot ----
    $heroProject = $brandName;
    $heroDate    = $report->date_shoot ? $report->date_shoot->format('d M Y') : null;

    // ---- Sub-línea del LLAMADO en el hero (homologada con el PAE): tipo Int./Ext. · día/noche · escenas ----
    $heroCallType = implode(' · ', array_filter([
        $report->loc_setting ? ($report->loc_setting === 'Mixto' ? 'Int./Ext.' : $report->loc_setting) : '',
        trim((string) $report->shoot_time),
    ]));
    $heroMeta = implode('   |   ', array_filter([
        $heroCallType,
        trim((string) $report->scene) !== '' ? ('Esc. ' . trim((string) $report->scene)) : '',
    ])) ?: null;

    // ---- Rango de fechas (prep · shoot · wrap) para la banda y la ficha ----
    $dtParts = [];
    if ($report->date_prep)  { $dtParts[] = $report->date_prep->format('d M'); }
    if ($report->date_shoot) { $dtParts[] = $report->date_shoot->format('d M Y'); }
    if ($report->date_wrap)  { $dtParts[] = $report->date_wrap->format('d M'); }
    $dtRange = count($dtParts) ? implode(' · ', $dtParts) : null;

    // ---- Sub-rótulo de la banda: escenario (el proyecto ya vive en el hero; no se repite aquí) ----
    $bandSubBits = [];
    if ($report->loc_setting)     { $bandSubBits[] = $report->loc_setting === 'Mixto' ? 'Int./Ext.' : $report->loc_setting; }
    $bandSub = count($bandSubBits) ? implode(' · ', $bandSubBits) : '—';

    // ---- Controles operativos (no-print) ----
    $canEdit  = auth()->check() && auth()->user()->can('locations.create');
    $hasFlash = session()->has('success') || session()->has('error') || (isset($errors) && $errors->any());

    // ---- Inventario / logística / EPP (gated por columna del delta 6-14) ----
    $hasMaxHeadcount = Schema::hasColumn('scouting_reports', 'max_headcount');
    $hasEmergInv     = Schema::hasColumn('scouting_reports', 'emergency_equipment_inventory');
    $hasLogistics    = Schema::hasColumn('scouting_reports', 'logistics_facilities');
    $hasReqPpe       = Schema::hasColumn('scouting_reports', 'required_ppe');

    $maxHeadcount = $hasMaxHeadcount ? $report->max_headcount : null;
    $eei = ($hasEmergInv && is_array($report->emergency_equipment_inventory)) ? $report->emergency_equipment_inventory : [];
    $lf  = ($hasLogistics && is_array($report->logistics_facilities)) ? $report->logistics_facilities : [];
    $ppe = ($hasReqPpe && is_array($report->required_ppe)) ? $report->required_ppe : [];

    $eeiFire = $eei['fire_extinguishers'] ?? null;
    $eeiKits = $eei['first_aid_kits'] ?? null;
    $eeiAed  = !empty($eei['aed']);
    $lfHyd   = $lf['hydration_stations'] ?? null;
    $lfRest  = !empty($lf['restrooms']);
    $lfShade = !empty($lf['shade_areas']);

    $hasHeadcount = ($maxHeadcount !== null && $maxHeadcount !== '');
    $hasEeiData   = ($eeiFire !== null && $eeiFire !== '') || ($eeiKits !== null && $eeiKits !== '') || $eeiAed;
    $hasLfData    = $lfRest || $lfShade || ($lfHyd !== null && $lfHyd !== '');
    $hasPpeData   = count($ppe) > 0;
    $showInventory = $hasHeadcount || $hasEeiData || $hasLfData || $hasPpeData;

    // ---- Galería adicional (normalizada) ----
    $gallery = $report->additionalImagesList();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brandName }} · {{ __('reports.scouting_title') }} · {{ $report->location_name }}</title>
@include('componentes._report-v2-head')
<style>
  /* Estilos propios del Scouting (scopeados). El motor de impresión ya vive en _report-v2-head;
     aquí solo chips de clasificación de riesgo, banner SB-132 y estados de viabilidad. Print-safe:
     usan las variables --r-* fijas del chrome, que no cambian entre pantalla y papel. */
  .sb132-banner{display:flex;gap:12px;align-items:center;padding:14px 16px;border-radius:var(--radius-sm);
    border:1px solid color-mix(in srgb,var(--r-5) 55%,transparent);background:color-mix(in srgb,var(--r-5) 12%,transparent);
    margin-bottom:22px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .sb132-banner svg{width:26px;height:26px;color:var(--r-5);flex:none}
  .sb132-banner .t{font-family:var(--poster);font-weight:900;font-style:italic;text-transform:uppercase;font-size:1rem;color:var(--r-5);line-height:1.1}
  .sb132-banner .d{font-size:.78rem;color:var(--muted);margin-top:2px}
  .rate{display:inline-block;font-weight:800;font-size:.68rem;padding:2px 8px;border-radius:6px;color:#111;white-space:nowrap;
    -webkit-print-color-adjust:exact;print-color-adjust:exact}
  .rate-L{background:var(--r-1)}.rate-M{background:var(--r-3)}.rate-H{background:var(--r-4);color:#fff}.rate-E{background:var(--r-5);color:#fff}
  .rate-none{background:var(--panel);border:1px solid var(--stroke);color:var(--muted)}
  .vstat{display:inline-block;font-weight:700;font-size:.66rem;padding:2px 9px;border-radius:20px;
    -webkit-print-color-adjust:exact;print-color-adjust:exact}
  .vstat.ok{background:color-mix(in srgb,var(--ok) 18%,transparent);color:var(--ok)}
  .vstat.pend{background:color-mix(in srgb,var(--r-5) 18%,transparent);color:var(--r-5)}
  .vstat.mid{background:color-mix(in srgb,var(--r-3) 24%,transparent);color:var(--r-4)}
  .invgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
  .invcard{background:var(--panel);border:1px solid var(--stroke);border-radius:var(--radius-sm);padding:13px 15px}
  .invcard .h{font-size:.58rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
  .invcard .row{display:flex;justify-content:space-between;gap:10px;font-size:.82rem;padding:3px 0}
  .invcard .row .k{color:var(--muted)}
  .invcard .row .v{font-weight:700}
  .invcard .v.ok{color:var(--ok)} .invcard .v.off{color:var(--faint)}
  /* (2026-07-22) Marca de borrador: un scouting no-final impreso debe delatarse para no
     confundirse con el entregable final. Va DENTRO del hero (thead) → el motor de paginación la
     repite en CADA hoja. Usa --danger, ya print-safe tras el arreglo central.
     (El texto visible sale de reports.draft_watermark; este comentario evita la palabra en
     mayúsculas a propósito, porque los comentarios CSS SÍ viajan al navegador.) */
  .draft-flag{display:flex;gap:9px;align-items:center;justify-content:center;text-align:center;
    padding:8px 14px;margin-top:10px;border:2px dashed var(--danger);border-radius:var(--radius-sm);
    background:color-mix(in srgb,var(--danger) 12%,transparent);color:var(--danger);
    font-family:var(--poster);font-weight:900;font-style:italic;text-transform:uppercase;
    font-size:.86rem;letter-spacing:.02em;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .draft-flag svg{width:20px;height:20px;flex:none}
  .draft-flag .n{font-weight:400;font-style:normal;text-transform:none;font-family:var(--font);
    font-size:.72rem;letter-spacing:0;opacity:.85}
  @media (max-width:720px){ .invgrid{grid-template-columns:1fr} }

  /* ============================================================================
     RESPONSIVE MÓVIL (≤767px). SOLO presentación: nada aquí toca impresión ni
     escritorio (el @media print y :root[data-view="print"] viven en el chrome y
     quedan intactos). Metas: cero scroll horizontal del BODY; hero/botones
     apilados sin cortarse; las tablas de datos conservan su scroll DENTRO del
     panel (el overflow-x:auto ya existente). */
  @media (max-width:767px){
    /* Candado: ninguna parte de la página desborda a lo ancho del viewport. Las
       tablas anchas siguen deslizándose dentro de su .panel (scroll propio). */
    html,body{max-width:100%;overflow-x:hidden}

    /* CAUSA RAÍZ del corte: .report-wrap (motor de paginación) es auto-layout, así
       que su <td> se estira al min-width:640px de la tabla de riesgos y el
       overflow-x:auto del panel deja de poder recortar → el documento "se sale".
       Con layout fijo el <td> queda al 100% y el panel vuelve a deslizar la tabla. */
    .report-wrap{table-layout:fixed}

    /* Toolbar flotante: que ENVUELVA en vez de cortarse (toque cómodo). */
    .toolbar{left:10px;right:10px;top:12px;flex-wrap:wrap;justify-content:flex-end;gap:6px;padding:6px}
    .tb{height:38px;padding:0 10px;font-size:.75rem}
    .tb.ico{width:38px}
    /* Reserva de aire para una toolbar de hasta 2 filas + laterales más finos. */
    .stage{padding:116px 12px 48px}

    /* Cuerpo con menos padding: más ancho útil para tablas y fichas. */
    .body{padding:18px 14px 4px}

    /* Hero: el logo y el nombre/locación dejan de montarse uno sobre otro y el
       texto de la locación puede envolver en vez de recortarse. */
    .doc-hero .hero-logo img{max-width:40vw;height:auto}
    .hero-side{max-width:50%;right:12px;top:12px}
    .hero-callbox{max-width:100%}
    .hero-callbox .cl-loc{white-space:normal;overflow:visible}
    .draft-flag{flex-wrap:wrap}

    /* Banda "de un vistazo": apilada (el chrome ya lo hace a 720; se asegura a 767). */
    .band{flex-direction:column}
    .band .lead{border-right:0;border-bottom:1px solid var(--stroke)}

    /* Fichas y firmas a menos columnas. */
    .facts{grid-template-columns:repeat(2,1fr)}
    .sign{grid-template-columns:1fr}

    /* Controles operativos: folio arriba, botones apilados a lo ancho (sin cortar). */
    .ops{flex-direction:column;align-items:stretch;gap:8px}
    .ops .ops-note{text-align:left}
    .ops>div{flex-direction:column;align-items:stretch;width:100%}
    .ops>div .btn{width:100%;min-height:44px}
    /* Cualquier botón del documento: el texto envuelve y nunca desborda su caja. */
    .btn{max-width:100%;white-space:normal;height:auto;min-height:38px;justify-content:center;text-align:center}

    /* Pista visual de que las tablas anchas se deslizan: barra fina siempre visible
       en los paneles con scroll horizontal (los selecciona por su estilo en línea). */
    .panel[style*="overflow-x:auto"]{-webkit-overflow-scrolling:touch}
    .panel[style*="overflow-x:auto"]::-webkit-scrollbar{height:6px}
    .panel[style*="overflow-x:auto"]::-webkit-scrollbar-thumb{background:var(--stroke-2);border-radius:3px}
  }
</style>
</head>
<body>

<div class="ambient"><div class="b b1"></div><div class="b b2"></div></div>

@include('componentes._report-v2-toolbar', ['backRoute' => route('scoutings.index')])

<div class="stage">
  <article class="sheet">
    {{-- Motor de paginación: <thead> (HERO DE MARCA _doc-hero) + <tfoot> (espaciador) se REPITEN por hoja impresa. --}}
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        'heroImage'    => $report->main_image_path,
        'heroProject'  => $heroProject,
        'heroLocation' => $report->location_name,
        'heroDate'     => $heroDate,
        'heroTime'     => null,
        'heroMeta'     => $heroMeta,
        {{-- La locación se OCULTA del cuadro negro: ya vive abajo en el cintillo (UBICACIÓN) y
             repetirla aquí era redundante. La caja negra queda como "llamado": fecha + escenas/tipo. --}}
        'heroHideCallLoc' => true,
        'heroModule'   => __('reports.scouting_title'),
      ])
      @if($report->status !== 'final')
      <div class="draft-flag">@include('componentes._icon', ['name' => 'alert-triangle']) {{ __('reports.draft_watermark') }} <span class="n">{{ __('reports.draft_note') }}</span></div>
      @endif
    </td></tr></thead>
    <tbody><tr><td>

    {{-- QUICK-READ BAND --}}
    <div class="band">
      <div class="lead">
        <span class="ic">@include('componentes._icon', ['name' => 'map-pin'])</span>
        <span class="who">
          <span class="lbl">{{ __('reports.label_location') }}</span>
          <span class="val">{{ $report->location_name ?: '—' }}</span>
          <span class="sub">{{ $bandSub }}</span>
        </span>
      </div>
      <div class="stats">
        <div class="cell"><span class="lbl">{{ __('reports.label_complexity') }}</span><span class="v">{{ $report->complexity ?: '—' }}</span></div>
        <div class="cell"><span class="lbl">{{ __('reports.label_risk') }}</span><span class="v {{ $riskWarn ? 'warn' : '' }}">{{ $riskWord ?: '—' }}</span></div>
        <div class="cell"><span class="lbl">{{ __('reports.label_shoot') }}</span><span class="v">{{ $heroDate ?: '—' }}</span></div>
      </div>
    </div>

    <div class="body">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — {{ __('reports.scouting_title') }} — {{ $report->location_name }}</h1>

      {{-- CONTROLES OPERATIVOS (no-print): flash + formato Amazon MGM + editar --}}
      @if($hasFlash || $canEdit || (auth()->check() && auth()->user()->can('medevac.issue')))
      <div class="no-print" style="margin-bottom:20px">
        @if(session('success'))<div class="alert ok">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert bad">{{ session('error') }}</div>@endif
        @if(isset($errors) && $errors->any())@foreach($errors->all() as $e)<div class="alert bad">{{ $e }}</div>@endforeach @endif
        <div class="ops">
          <span class="ops-note">{{ $folio }}</span>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <a class="btn" href="{{ route('scoutings.amazon', $report->id) }}">@include('componentes._icon', ['name' => 'file-text']) {{ __('reports.scouting_view_amazon_format') }}</a>
            @can('medevac.issue')
            <a class="btn" href="{{ route('medevac.create', $report->id) }}">@include('componentes._icon', ['name' => 'ambulance']) {{ $en ? 'Issue MEDEVAC' : 'Emitir MEDEVAC' }}</a>
            @endcan
            @can('locations.create')
            <a class="btn brand" href="{{ route('scoutings.edit', $report->id) }}">@include('componentes._icon', ['name' => 'pencil']) {{ $en ? 'Edit' : 'Editar' }}</a>
            @endcan
          </div>
        </div>
      </div>
      @endif

      {{-- BANNER SB-132 (imprimible: es un aviso de compliance) --}}
      @if($report->requires_specific_ra)
      <div class="sb132-banner">
        @include('componentes._icon', ['name' => 'alert-triangle'])
        <div>
          <div class="t">{{ __('reports.scouting_sb132_banner_title') }}</div>
          <div class="d">{{ __('reports.scouting_sb132_banner_desc') }}</div>
        </div>
      </div>
      @endif

      {{-- DATOS DEL SCOUTING (ficha: producción + locación + fechas) --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg><h2>{{ __('reports.scouting_section_details') }}</h2><span class="line"></span></div>
        <div class="panel"><div class="facts">
          <div class="fact"><div class="k">{{ __('reports.label_folio') }}</div><div class="v mono">{{ $folio }}</div></div>
          <div class="fact"><div class="k">{{ __('reports.scouting_label_production') }}</div><div class="v">{{ $brandName }}</div></div>
          @if($report->production_type)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_production_type') }}</div><div class="v">{{ $report->production_type }}</div></div>
          @endif
          @if($report->manager_name)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_manager') }}</div><div class="v">{{ $report->manager_name }}</div></div>
          @endif
          @if($report->safety_rep_name)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_safety_rep') }}</div><div class="v">{{ $report->safety_rep_name }}</div></div>
          @endif
          @if($report->scene)
          <div class="fact"><div class="k">{{ __('reports.label_scene') }}</div><div class="v">{{ $report->scene }}</div></div>
          @endif
          @if($report->loc_setting)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_setting') }}</div><div class="v">{{ $report->loc_setting === 'Mixto' ? 'Int./Ext.' : $report->loc_setting }}</div></div>
          @endif
          @if($report->shoot_time)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_shoot_time') }}</div><div class="v">{{ $report->shoot_time }}</div></div>
          @endif
          <div class="fact"><div class="k">{{ __('reports.label_complexity') }}</div><div class="v">{{ $report->complexity ?: '—' }}</div></div>
          <div class="fact"><div class="k">{{ __('reports.label_status') }}</div><div class="v">{{ $report->status ?: '—' }}</div></div>
          @if($dtRange)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_dates') }}</div><div class="v">{{ $dtRange }}</div></div>
          @endif
          @if($report->location_address)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_address') }}</div><div class="v">{{ $report->location_address }}</div></div>
          @endif
          @if($report->latitude && $report->longitude)
          <div class="fact"><div class="k">{{ __('reports.label_gps_location') }}</div><div class="v mono">{{ number_format((float) $report->latitude, 4) }}, {{ number_format((float) $report->longitude, 4) }}</div></div>
          @endif
          @if($report->make_by)
          <div class="fact"><div class="k">{{ __('reports.label_by') }}</div><div class="v">{{ $report->make_by }}</div></div>
          @endif
        </div>
        @if($report->latitude && $report->longitude)
        <div class="no-print" style="margin-top:12px">
          <a class="btn sm" href="https://www.google.com/maps?q={{ $report->latitude }},{{ $report->longitude }}" target="_blank" rel="noopener">@include('componentes._icon', ['name' => 'map']) {{ __('reports.label_view_map') }}</a>
        </div>
        @endif
        </div>
      </section>

      {{-- DESGLOSE SB-132 (detalle estructurado; solo si la columna existe y trae datos) --}}
      @if($report->requires_specific_ra
          && Schema::hasColumn('scouting_reports', 'sb132_details')
          && is_array($report->sb132_details)
          && count($report->sb132_details))
      @php
        $sb = $report->sb132_details;
        // fuego/altura son claves NUEVAS del delta; se resuelven con Lang::has + literal de respaldo
        // por locale (evita imprimir la clave cruda si aún no se agregó la traducción).
        $actLabels = [
            'armas'      => __('reports.scouting_activity_armas'),
            'pirotecnia' => __('reports.scouting_activity_pirotecnia'),
            'stunts'     => __('reports.scouting_activity_stunts'),
            'aereo'      => __('reports.scouting_activity_aereo'),
            'agua'       => __('reports.scouting_activity_agua'),
            'off-road'   => __('reports.scouting_activity_offroad'),
            'fuego'      => Lang::has('reports.scouting_activity_fuego')
                              ? __('reports.scouting_activity_fuego')
                              : ($en ? 'Open flame / fire' : 'Fuego abierto / llamas'),
            'altura'     => Lang::has('reports.scouting_activity_altura')
                              ? __('reports.scouting_activity_altura')
                              : ($en ? 'Work at height / rigging' : 'Trabajo en altura / rigging'),
        ];
        $acts = [];
        foreach ((is_array($sb['activity_type'] ?? null) ? $sb['activity_type'] : []) as $a) {
            $acts[] = $actLabels[$a] ?? ucfirst(str_replace(['-', '_'], ' ', $a));
        }
        $cpr = $sb['certified_personnel_required'] ?? null;
        $cprTxt = is_bool($cpr) ? ($cpr ? __('reports.label_yes') : __('reports.label_no')) : (($cpr === null || $cpr === '') ? '—' : $cpr);
        $sceneNo = $sb['scene_number'] ?? null;
      @endphp
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg><h2>{{ __('reports.scouting_sb132_detail_title') }}</h2></div>
        <div class="panel"><div class="facts">
          <div class="fact" style="grid-column:1/-1"><div class="k">{{ __('reports.scouting_sb132_activities_declared') }}</div><div class="v">{{ count($acts) ? implode(', ', $acts) : '—' }}</div></div>
          <div class="fact"><div class="k">{{ __('reports.label_scene') }}</div><div class="v">{{ ($sceneNo !== null && $sceneNo !== '') ? $sceneNo : '—' }}</div></div>
          <div class="fact"><div class="k">{{ __('reports.scouting_sb132_certified_personnel') }}</div><div class="v">{{ $cprTxt }}</div></div>
        </div></div>
      </section>
      @endif

      {{-- EVALUACIÓN DE RIESGOS H&S: tabla de peligros + chip del peor + matriz 5×5 --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg><h2>{{ __('reports.scouting_section_risk_assessment') }}</h2><span class="line"></span></div>

        @if(count($rows))
        <div class="panel" style="padding:4px 8px;overflow-x:auto;margin-bottom:14px">
          <table class="tbl" style="min-width:640px">
            <thead><tr>
              <th>{{ __('reports.scouting_th_hazard') }}</th>
              <th title="{{ __('reports.label_probability') }}">P</th>
              <th title="{{ __('reports.label_consequence') }}">C</th>
              <th>{{ __('reports.scouting_th_classification') }}</th>
              <th>{{ __('reports.scouting_th_controls') }}</th>
              <th>{{ __('reports.scouting_th_residual') }}</th>
              <th>{{ __('reports.scouting_th_personnel') }}</th>
            </tr></thead>
            <tbody>
              @foreach($rows as $row)
              <tr>
                <td style="font-weight:700">
                  {{ $row['hazard'] ?: '—' }}
                  @if($row['sinclas'])
                  <span class="badge" style="background:var(--warn,#b45309);color:#fff;font-size:.62rem;margin-left:6px;vertical-align:middle">{{ __('reports.scouting_unclassified') }}</span>
                  @endif
                  @if(!empty($row['badge']))
                  <div style="margin-top:5px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    <span class="badge badge-{{ $row['badge'] }}">{{ $row['badge'] }}</span>
                    <span class="mono" style="font-size:.68rem;color:var(--faint)">{{ $row['code'] }}</span>
                    @if(!empty($row['url']))
                    <a class="no-print" href="{{ $row['url'] }}" target="_blank" rel="noopener" style="font-size:.68rem;font-weight:700;color:var(--brand);text-decoration:none">{{ __('reports.label_bulletin') }} ↗</a>
                    @endif
                  </div>
                  @endif
                </td>
                <td class="mono" style="text-align:center;font-weight:700">{{ $row['likelihood'] ?: '—' }}</td>
                <td class="mono" style="text-align:center;font-weight:700">{{ $row['consequence'] ?: '—' }}</td>
                <td style="text-align:center">
                  @if($row['rating'])
                  <span class="rate rate-{{ $row['rating'] }}">{{ $row['rating'] }} · {{ $ratingWord[$row['rating']] ?? '' }}</span>
                  @else
                  <span class="rate rate-none">—</span>
                  @endif
                </td>
                <td style="color:var(--muted)">{{ $row['control'] ?: '—' }}</td>
                <td style="text-align:center">
                  @if($row['residual'])<span class="rate rate-{{ $row['residual'] }}">{{ $row['residual'] }}</span>@else<span style="color:var(--faint)">—</span>@endif
                </td>
                <td style="color:var(--muted)">{{ $row['personnel'] ?: '—' }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="riskrow">
          <span class="rc {{ $worstRate === 'E' ? 'danger' : ($worstRate === 'H' ? 'warn' : ($worstRate === 'M' ? 'caution' : ($worstRate === 'L' ? 'ok' : 'neutral'))) }}">@include('componentes._icon', ['name' => 'alert-triangle'])<span class="t"><span class="l">{{ __('reports.label_risk') }}</span><span class="v">{{ $riskWord ?: '—' }}</span></span></span>
          @if($hasCell)
          <span class="rc neutral">@include('componentes._icon', ['name' => 'activity'])<span class="t"><span class="l">{{ __('reports.label_probability') }}</span><span class="v">{{ $mtxProb[$mtxLk] }}</span></span></span>
          <span class="rc neutral">@include('componentes._icon', ['name' => 'alert-triangle'])<span class="t"><span class="l">{{ __('reports.label_consequence') }}</span><span class="v">{{ $mtxCons[$mtxCs] }}</span></span></span>
          @endif
        </div>

        {{-- (2026-08-04) La MATRIZ 5×5 se retiró de la vista/reporte del scouting (pedido del
             owner): la lectura de riesgo ya vive en los chips de arriba (.riskrow: Riesgo /
             Probabilidad / Consecuencia) y en las columnas P·C·Clasif de la tabla, así que la
             rejilla era redundante. Se conserva SOLO en el Amazon MGM RA (_risk-matrix). Las
             variables $mtx* siguen calculándose porque las usan los chips de .riskrow. --}}
        @else
        <p class="desc">{{ __('reports.scouting_empty_risk_assessment') }}</p>
        @endif
      </section>

      {{-- RESUMEN EJECUTIVO --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M16 13H8"/><path d="M16 17H8"/></svg><h2>{{ __('reports.dsr_section_executive_summary') }}</h2></div>
        <p class="desc">{{ $report->exec_summary ?: __('reports.scouting_empty_exec_summary') }}</p>
      </section>

      {{-- PLAN DE EMERGENCIA --}}
      @php $hasEmergency = $report->nearest_hospital || $report->hospital_address || $report->hospital_eta || $report->emergency_access || $report->assembly_point || $report->ambulance_company || $report->has_ambulance !== null || $report->emergency_phone; @endphp
      @if($hasEmergency)
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2v2M5 2v2M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/></svg><h2>{{ __('reports.scouting_section_emergency') }}</h2></div>
        <div class="panel"><div class="facts">
          <div class="fact"><div class="k">{{ __('reports.label_hospital') }}</div><div class="v">{{ $report->nearest_hospital ?: '—' }}{{ $report->hospital_eta ? ' · ETA ' . $report->hospital_eta : '' }}</div></div>
          @if($report->hospital_address)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_address') }}</div><div class="v">{{ $report->hospital_address }}</div></div>
          @endif
          @if($report->has_ambulance !== null)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_has_ambulance') }}</div><div class="v">{{ $report->has_ambulance ? __('reports.scouting_yes') : __('reports.scouting_no') }}</div></div>
          @endif
          @if($report->ambulance_company)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_support') }}</div><div class="v">{{ $report->ambulance_company }}</div></div>
          @endif
          @if($report->emergency_phone)
          <div class="fact"><div class="k">{{ __('reports.label_phone') }}</div><div class="v mono">{{ $report->emergency_phone }}</div></div>
          @endif
          @if($report->assembly_point)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_assembly_point') }}</div><div class="v">{{ $report->assembly_point }}</div></div>
          @endif
          @if($report->emergency_access)
          <div class="fact"><div class="k">{{ __('reports.scouting_label_access') }}</div><div class="v">{{ $report->emergency_access }}</div></div>
          @endif
        </div></div>
      </section>
      @endif

      {{-- VIABILIDAD --}}
      @php $viab = is_array($report->viability_checklist) ? $report->viability_checklist : []; @endphp
      @if(count($viab))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg><h2>{{ __('reports.scouting_section_viability') }}</h2></div>
        <div class="panel" style="padding:4px 8px;overflow-x:auto">
          <table class="tbl">
            <thead><tr><th>{{ __('reports.scouting_th_area') }}</th><th>{{ __('reports.label_status') }}</th><th>{{ __('reports.label_responsible') }}</th><th>{{ __('reports.scouting_th_note') }}</th></tr></thead>
            <tbody>
              @foreach($viab as $v)
              @php $st = $v['status'] ?? ''; $vc = $st === 'OK' ? 'ok' : ($st === 'Pendiente' ? 'pend' : 'mid'); @endphp
              <tr>
                <td style="font-weight:700">{{ $v['area'] ?? '' }}</td>
                <td><span class="vstat {{ $vc }}">{{ $st ?: '—' }}</span></td>
                <td style="color:var(--muted)">{{ $v['responsible'] ?? '' }}</td>
                <td style="color:var(--muted)">{{ $v['note'] ?? '' }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
      @endif

      {{-- ACUERDOS --}}
      @php $agr = is_array($report->agreements) ? $report->agreements : []; @endphp
      @if(count($agr))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="8" y="2" rx="1"/><path d="m9 14 2 2 4-4"/></svg><h2>{{ __('reports.scouting_section_agreements') }}</h2></div>
        <div class="panel" style="padding:4px 8px;overflow-x:auto">
          <table class="tbl">
            <thead><tr><th>{{ __('reports.scouting_th_agreement') }}</th><th>{{ __('reports.label_responsible') }}</th><th>{{ __('reports.label_date') }}</th><th>{{ __('reports.label_status') }}</th></tr></thead>
            <tbody>
              @foreach($agr as $a)
              <tr>
                <td style="font-weight:700">{{ $a['item'] ?? '' }}</td>
                <td style="color:var(--muted)">{{ $a['responsible'] ?? '' }}</td>
                <td class="mono">{{ $a['date'] ?? '' }}</td>
                <td style="color:var(--muted)">{{ $a['status'] ?? '' }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
      @endif

      {{-- ACCIONES CORRECTIVAS (PDCA) --}}
      @if(Schema::hasTable('action_items') && $report->actionItems->count())
      @php $stL = ['open' => __('reports.status_open'), 'in_progress' => __('reports.status_in_progress'), 'closed' => __('reports.status_closed')]; @endphp
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg><h2>{{ __('reports.section_corrective_actions') }}</h2></div>
        <div style="display:flex;flex-direction:column;gap:8px">
          @foreach($report->actionItems as $item)
          @php $st = $item->status; $ov = method_exists($item, 'isOverdue') && $item->isOverdue(); @endphp
          <div class="panel" style="{{ $ov ? 'border-color:color-mix(in srgb,var(--danger) 45%,transparent)' : '' }}">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:start">
              <p style="margin:0;font-size:.86rem">{{ $item->description }}</p>
              <span class="chip {{ $st === 'closed' ? 'ok' : ($st === 'open' ? 'warn' : '') }}" style="flex:none">{{ isset($stL[$st]) ? $stL[$st] : $st }}</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:4px 18px;margin-top:6px;font-size:.72rem;color:var(--muted)">
              <span><strong>{{ __('reports.label_responsible') }}:</strong> {{ $item->owner ? $item->owner->name : '—' }}</span>
              <span style="{{ $ov ? 'color:var(--danger);font-weight:700' : '' }}"><strong>{{ __('reports.label_due') }}:</strong> {{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('d M Y') : '—' }}{{ $ov ? ' · ' . __('reports.label_overdue') : '' }}</span>
              @if($item->source)<span><strong>{{ __('reports.label_source') }}:</strong> {{ $item->source === 'auto' ? __('reports.label_auto_short') : __('reports.label_manual') }}</span>@endif
            </div>
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

      {{-- NOTAS OPERATIVAS --}}
      @if($report->operational_notes)
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg><h2>{{ __('reports.scouting_section_operational_notes') }}</h2></div>
        <p class="desc">{{ $report->operational_notes }}</p>
      </section>
      @endif

      {{-- NORMAS APLICABLES --}}
      @if(Schema::hasTable('standardables') && $report->standards->count())
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><h2>{{ __('reports.section_applicable_standards') }}</h2></div>
        <div class="chips">
          @foreach($report->standards as $std)
          <span class="chip"><span class="badge badge-{{ $std->regulation_badge }}">{{ $std->regulation_badge }}</span> {{ $std->category_name_localized }} <span style="color:var(--faint);font-family:var(--mono);font-size:.7rem">{{ $std->regulation_code }}</span></span>
          @endforeach
        </div>
      </section>
      @endif

      {{-- INVENTARIO, LOGÍSTICA Y EPP (gated por columnas del delta) --}}
      @if($showInventory)
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg><h2>{{ __('reports.scouting_section_inventory') }}</h2></div>
        <div class="invgrid">
          @if($hasHeadcount || $hasEeiData)
          <div class="invcard">
            <div class="h">{{ __('reports.scouting_label_headcount_equipment') }}</div>
            @if($hasHeadcount)<div class="row"><span class="k">{{ __('reports.scouting_label_max_headcount') }}</span><span class="v">{{ $maxHeadcount }}</span></div>@endif
            @if($eeiFire !== null && $eeiFire !== '')<div class="row"><span class="k">{{ __('reports.scouting_label_extinguishers') }}</span><span class="v">{{ $eeiFire }}</span></div>@endif
            @if($eeiKits !== null && $eeiKits !== '')<div class="row"><span class="k">{{ __('reports.scouting_label_first_aid_kits') }}</span><span class="v">{{ $eeiKits }}</span></div>@endif
            @if($hasEeiData)<div class="row"><span class="k">DEA / AED</span><span class="v {{ $eeiAed ? 'ok' : 'off' }}">{{ $eeiAed ? __('reports.label_yes') : __('reports.label_no') }}</span></div>@endif
          </div>
          @endif
          @if($hasLfData)
          <div class="invcard">
            <div class="h">{{ __('reports.scouting_label_facilities_logistics') }}</div>
            <div class="row"><span class="k">{{ __('reports.scouting_label_restrooms') }}</span><span class="v {{ $lfRest ? 'ok' : 'off' }}">{{ $lfRest ? __('reports.label_yes') : __('reports.label_no') }}</span></div>
            @if($lfHyd !== null && $lfHyd !== '')<div class="row"><span class="k">{{ __('reports.scouting_label_hydration') }}</span><span class="v">{{ $lfHyd }}</span></div>@endif
            <div class="row"><span class="k">{{ __('reports.scouting_label_shade') }}</span><span class="v {{ $lfShade ? 'ok' : 'off' }}">{{ $lfShade ? __('reports.label_yes') : __('reports.label_no') }}</span></div>
          </div>
          @endif
          @if($hasPpeData)
          <div class="invcard">
            <div class="h">{{ __('reports.label_required_ppe') }}</div>
            <div class="chips" style="margin-top:2px">@foreach($ppe as $p)<span class="chip">{{ $p }}</span>@endforeach</div>
          </div>
          @endif
        </div>
      </section>
      @endif

      {{-- EVIDENCIA FOTOGRÁFICA + MAPEO DE RIESGOS.
           Las imágenes marcadas como "mapeo de riesgos" (risk_map) se agrupan aparte;
           el resto va en Evidencia fotográfica junto con la imagen principal. --}}
      @php
        $riskPhotos = array_values(array_filter($gallery, function ($g) { return !empty($g['risk_map']); }));
        $photos = [];
        if ($report->main_image_path) { $photos[] = ['path' => $report->main_image_path, 'caption' => '']; }
        foreach ($gallery as $g) { if (empty($g['risk_map'])) { $photos[] = $g; } }
      @endphp
      @if(count($photos))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg><h2>{{ __('reports.injury_section_photo_evidence') }}</h2></div>
        <div class="photos">
          @foreach($photos as $i => $img)
          <div class="photo"><img src="{{ $img['path'] }}" loading="lazy" alt="{{ __('reports.injury_section_photo_evidence') }} {{ $i + 1 }}"><span class="cap">{{ !empty($img['caption']) ? $img['caption'] : __('reports.injury_section_photo_evidence') . ' ' . ($i + 1) }}</span></div>
          @endforeach
        </div>
      </section>
      @endif

      @if(count($riskPhotos))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><h2>Mapeo de riesgos</h2></div>
        <div class="photos">
          @foreach($riskPhotos as $i => $img)
          <div class="photo"><img src="{{ $img['path'] }}" loading="lazy" alt="Mapeo de riesgos {{ $i + 1 }}"><span class="cap">{{ !empty($img['caption']) ? $img['caption'] : 'Mapeo de riesgos ' . ($i + 1) }}</span></div>
          @endforeach
        </div>
      </section>
      @endif

      {{-- FIRMAS E INTEGRIDAD --}}
      {{-- (2026-07-22) El bloque .sign es el HUECO de la firma AUTÓGRAFA (módulo futuro): se
           imprime en blanco sobre una línea para firmar a mano. La cadena SHA/CFDI la pinta
           _seal-cfdi (mismo recuadro cotejable que Injury y DSR) — antes había un banner
           artesanal con el hash truncado a 12 chars (incotejable) y, sin sello, imprimía el
           UUID dentro de un chip que "se leía como si fuera el sello". Título corregido:
           antes reusaba label_risk_assessment ("Risk Assessment" por 2ª vez). --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg><h2>{{ __('reports.label_signatures_integrity') }}</h2><span class="line"></span></div>
        <div class="sign">
          <div class="sig"><div class="who">{{ $report->make_by ?: '—' }}</div><div class="role">{{ __('reports.label_prepared_by') }}</div></div>
          <div class="sig"><div class="who">{{ $report->make_date ? \Carbon\Carbon::parse($report->make_date)->format('d M Y') : '—' }}</div><div class="role">{{ __('reports.label_date') }}</div></div>
        </div>
        {{-- (2026-07-23) El sello es del acto de FINALIZAR: un 'final' lleva su cadena CFDI y su
             verificación; un borrador NO lleva sello vigente. Sin este gate, reabrir un final a
             'draft'/'revision' y editarlo dejaría una firma superada que _seal-cfdi leería como
             "ALTERADO" — una falsa alarma de manipulación sobre un documento marcado como borrador.
             Regla del owner: final = sellado, borrador = no. --}}
        @if($report->status === 'final')
          @include('componentes._seal-cfdi', ['doc' => $report, 'folio' => $folio, 'prefix' => 'CREWCARE-SCOUT'])
        @else
          <div class="seal none">@include('componentes._icon', ['name' => 'info'])<div><span class="h">{{ __('reports.seal_not_sealed') }}</span></div></div>
        @endif
      </section>
    </div>
    </td></tr></tbody>
    <tfoot><tr><td><div class="footer-spacer"></div></td></tr></tfoot>
    </table>

    @include('componentes._report-v2-foot', [
      'footPreparedName' => $report->make_by ?: '—',
      'footPreparedMeta' => __('reports.label_risk_assessment') . ($report->make_date ? ' · ' . \Carbon\Carbon::parse($report->make_date)->format('d M Y') : ''),
      'footUuid'         => $footUuid,
    ])

</body>
</html>
