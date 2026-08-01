@extends('layouts.app')
@section('content')
{{-- (2026-07-13) MÓDULO 14 — i18n del documento vía ?lang=en|es SIN tocar rutas/middleware.
     __() lee el locale al renderizar, así que sólo las etiquetas estáticas ya migradas a
     __('reports.*') cambian de idioma; los datos y la UI operativa (no-print) siguen igual. --}}
@php if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); } @endphp
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { corePlugins: { preflight: false } };</script>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,300;0,400;0,700;1,900&family=Roboto:wght@400;700&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">

<style>
    /* Fuente de marca de los documentos. "Aspire SC" (small caps): Light/Regular/Black. */
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSCLight-Regular.ttf') format('truetype'); font-weight:300; font-style:normal; font-display:swap; }
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSC-Regular.ttf')      format('truetype'); font-weight:400; font-style:normal; font-display:swap; }
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSCBlack-Regular.ttf')  format('truetype'); font-weight:900; font-style:normal; font-display:swap; }

    :root { --brand-primary: {{ $branding['primary_color'] ?? '#ff9900' }}; --brand-secondary: {{ $branding['secondary_color'] ?? '#1f2937' }}; --brand-accent: {{ $branding['accent_color'] ?? '#0ea5e9' }}; }
    .font-poster { font-family: 'Roboto Condensed', sans-serif; font-weight: 900; font-style: italic; text-transform: uppercase; }
    .font-slug { font-family: 'Courier Prime', monospace; }
    .badge { display: inline-flex; align-items: center; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800; color: white; letter-spacing: 0.05em; height: 18px;}
    .badge-STPS { background-color: #15803d; }
    .badge-OSHA { background-color: #1d4ed8; }
    .badge-CSATF { background-color: #b91c1c; }
    .badge-AMAZON { background-color: #ff9900; color: black; }
    .brand-corner { position: absolute; bottom: 0; right: 0; width: 0; height: 0; border-style: solid; border-width: 0 0 30px 30px; border-color: transparent transparent var(--brand-primary) transparent; }

    /* ===== HERO (rediseño 2026-07-08) ===== */
    .doc-hero { position: relative; width: 100%; height: 200px; overflow: hidden; background:#0f141c; }
    .doc-hero .hero-bg { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .doc-hero .hero-veil { position:absolute; inset:0; background: linear-gradient(90deg, rgba(6,10,16,.55) 0%, rgba(6,10,16,.12) 38%, rgba(6,10,16,.10) 60%, rgba(6,10,16,.60) 100%); }
    .doc-hero .hero-orange { position:absolute; left:0; right:0; bottom:0; height:4px; background: var(--brand-primary); z-index:3; }
    /* Nombre del proyecto (auto-ajustado al ancho de la caja) */
    .hero-side { position:absolute; top:14px; right:20px; z-index:3; width:360px; text-align:right; }
    .hero-project { font-family:'Aspire SC', sans-serif; font-weight:400; color:#fff; line-height:1.05; letter-spacing:.01em;
        white-space:nowrap; overflow:visible; padding-top:2px; text-shadow:0 3px 14px rgba(0,0,0,.6); }
    .hero-callbox { display:inline-block; background:#000; padding:5px 10px; margin-top:8px; text-align:center; }
    .hero-callbox .cl-loc { font-family:'Courier Prime', monospace; font-weight:700; color:#fff; font-size:12px; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap; overflow:hidden; }
    .hero-callbox .cl-date { font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; color:#e7e7e7; font-size:10px; letter-spacing:.08em; margin-top:2px; text-transform:uppercase; }
    /* CrewCare + módulo (pie del hero): blanco esmerilado (backdrop-filter en pantalla; el blanco .80
       sólido es lo que se ve en PDF, alineado) + letras NEGRAS + esquinas SUPERIORES redondeadas. */
    .hero-brand { position:absolute; left:50%; bottom:0; transform:translateX(-50%); z-index:2; text-align:center;
        padding:2px 14px 3px; border-radius:8px 8px 0 0; background:rgba(255,255,255,.55); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); }
    .hero-brand .cc { font-family:'Aspire SC', sans-serif; color:#0f141c; font-size:12px; letter-spacing:.03em; line-height:1; text-transform:uppercase; }
    .hero-brand .cc .crew { font-weight:300; }
    .hero-brand .cc .care { font-weight:400; }
    .hero-brand .mod { font-family:'Roboto', sans-serif; font-weight:700; color:#374151; font-size:6px; letter-spacing:.28em; margin-top:1px; text-transform:uppercase; }
    /* Logo (placa esmerilada) top-left */
    .hero-logo { position:absolute; top:16px; left:16px; z-index:3; }

    /* ===== BANDA DE STATS (bajo el hero, solo pág. 1) ===== */
    .stats-band { display:flex; height:70px; background:#0b0f16; border-top:0; }
    .stats-band .col { flex:1 1 0; display:flex; align-items:center; justify-content:space-around; padding:0 14px; border-right:1px solid #1f2733; }
    .stats-band .wx  { width:26%; min-width:190px; display:flex; align-items:center; justify-content:center; gap:12px; background:#151b26; padding:0 16px; }
    .stats-band .lbl { display:block; font-size:9px; color:#8b93a3; text-transform:uppercase; letter-spacing:.18em; }
    .stats-band .val { font-size:22px; font-weight:700; color:#fff; line-height:1; }
    .stats-band .val.ok { color:#22c55e; }

    /* Tabla envolvente: su <thead> (hero) se repite en cada página impresa. En pantalla es un
       contenedor transparente de ancho completo. */
    .report-wrap { width:100%; border-collapse:collapse; }
    .report-wrap > thead > tr > td, .report-wrap > tbody > tr > td, .report-wrap > tfoot > tr > td { padding:0; border:0; }

    /* ===== BITÁCORA (tabla anidada: thead=título repetible en print) ===== */
    .logs-table { width:100%; border-collapse:collapse; }
    .logs-table .log-head { padding:8px 32px 0; }   /* 8px arriba = holgura del título bajo el hero en pág.2+ */
    td.log-cell-wrap { vertical-align:top; padding:0 32px 24px 32px; }
    /* Simetría robusta (pantalla + PRINT): flex con align-items:stretch → las 2 tarjetas de una
       fila igualan su altura a la más alta. Más fiable que height:100% en celda de tabla, que
       Chrome IGNORA al imprimir. El par entero es una .log-row <tr> con break-inside:avoid. */
    .log-flex { display:flex; gap:24px; align-items:stretch; }
    .log-flex > * { flex:1 1 0; min-width:0; }

   /* === PDF NATIVO (window.print) — OFICIO/LEGAL con hero y firma repetidos === */
    @media print {
        /* Legal (8.5x14). HERO repetido = <thead> de .report-wrap; PIE repetido = <tfoot> (ambos
           por mecánica de tabla, fiable). Margen 0: el diseño es full-bleed y el pie ya no es fixed,
           así que NO necesita margen inferior reservado (eso era lo que dejaba el hueco). */
        @page { size: legal portrait; margin: 0; }

        body {
            margin: 0;
            background-color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .no-print, .navbar, .sidebar, #sidebar { display: none !important; }
        .max-w-4xl { max-width: 100% !important; width: 100% !important; box-shadow:none !important; margin:0 !important; }

        /* Sombras fuera en impresión (evita bloques grises en algunos visores; el borde basta). */
        .shadow-lg, .shadow-2xl, .shadow-sm, .shadow { box-shadow: none !important; }

        /* <thead>/<tfoot> se reimprimen en cada hoja: hero (thead) + pie (tfoot) del report-wrap,
           y el título de la bitácora (thead de la logs-table anidada). */
        .report-wrap > thead { display: table-header-group; }
        .report-wrap > tfoot { display: table-footer-group; }
        .logs-table thead { display: table-header-group; }
        .log-row { page-break-inside: avoid !important; break-inside: avoid !important; }
        .break-inside-avoid, p, h1, h2, h3, h4, textarea { page-break-inside: avoid !important; break-inside: avoid !important; }

        /* COMPACTAR PARA IMPRESIÓN: el hero se repite en CADA hoja, así que en print lo reducimos
           (y la foto de cada log) → caben más logs por página → menos hojas (meta: ~8 logs = 2 pág). */
        .doc-hero { height: 162px !important; }   /* no bajar más: el cuadro del llamado se recorta */
        .stats-band { height: 54px !important; }
        /* En print no hay backdrop-filter: subimos el panel a un blanco esmerilado VISIBLE pero
           translúcido (deja ver la foto REAL debajo → ALINEADO, no una zona equivocada) + borde
           tipo vidrio + sombra para separarlo. */
        .hero-logo-plate { background-color: rgba(255,255,255,0.34) !important;
            border: 1px solid rgba(255,255,255,0.45) !important;
            box-shadow: 0 6px 18px rgba(0,0,0,.35) !important; }
        .log-photo { height: 122px !important; }
        .log-flex { gap: 16px !important; }
        td.log-cell-wrap { padding-bottom: 12px !important; }

        /* PIE = position:fixed; bottom:0 con @page margin:0 → pegado al fondo REAL de CADA hoja
           (incluida la última parcial), SIN hueco. El <tfoot>.footer-spacer reserva su alto en flujo
           para que el contenido nunca quede debajo del pie. */
        .footer-spacer { height: 118px; }
        .print-footer {
            position: fixed; bottom: 0; left: 0; right: 0; width: 100%;
            background-color: #f9fafb !important; border-top: 1px solid #e5e7eb !important;
        }
    }
</style>

{{-- flex-wrap (2026-07, fix responsive): la barra de acciones (no-print, no afecta el PDF)
     desbordaba en ~375px con 4 botones; ahora envuelven a una segunda línea. --}}
<div class="max-w-4xl mx-auto mb-4 flex flex-wrap gap-2 justify-between items-center no-print mt-4">
    <a href="{{ route('daily_reports.index') }}" class="inline-flex items-center gap-1 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded font-semibold shadow-sm hover:bg-gray-100 no-underline">&larr; Volver</a>
    <div class="flex gap-2">
        <button type="button" class="bg-yellow-500 text-black px-4 py-2 rounded font-bold shadow hover:bg-yellow-400" data-bs-toggle="modal" data-bs-target="#editReportModal">
            <i class="fas fa-edit"></i> Cierre de Día
        </button>

        <button type="button" class="bg-green-600 text-white px-4 py-2 rounded font-bold shadow hover:bg-green-700" data-bs-toggle="modal" data-bs-target="#addLogModal">
            <i class="fas fa-camera"></i> + Hallazgo
        </button>

        <button onclick="window.print();" class="bg-red-600 text-white px-4 py-2 rounded font-bold shadow hover:bg-red-700">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
    </div>
</div>

{{-- Feedback de acciones (flash + errores de validación). no-print: NO sale en el PDF.
     Sin esto, un fallo al guardar un hallazgo (foto muy pesada, candado 24h, etc.) era invisible. --}}
@if(session('success') || session('error') || $errors->any() || $isLocked)
<div class="max-w-4xl mx-auto mb-4 no-print space-y-2">
    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded flex items-start gap-2">
            <i class="fas fa-check-circle mt-0.5"></i><span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded flex items-start gap-2">
            <i class="fas fa-exclamation-triangle mt-0.5"></i><span>{{ session('error') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded">
            <div class="font-bold flex items-center gap-2 mb-1"><i class="fas fa-exclamation-triangle"></i> No se pudo guardar</div>
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif
    @if($isLocked)
        <div class="bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 rounded flex items-start gap-2">
            <i class="fas fa-lock mt-0.5"></i>
            <span><strong>Reporte sellado.</strong> Por cumplimiento normativo no admite cambios después de 24 h de su creación.</span>
        </div>
    @endif
</div>
@endif

<div class="max-w-4xl mx-auto bg-white shadow-2xl min-h-screen overflow-hidden mb-10">
  <table class="report-wrap">
  <thead><tr><td>

    {{-- ===== HERO (dentro de <thead> → se repite en CADA página impresa) ===== --}}
    <div class="doc-hero">
        @if($report->hero_image_path)
            <img src="{{ $report->hero_image_path }}" class="hero-bg" alt="">
        @endif
        <div class="hero-veil"></div>

        {{-- Logo (placa de vidrio esmerilado: backdrop-filter en pantalla; panel esmerilado en print). --}}
        <div class="hero-logo">@include('componentes._doc-hero-logo', ['logoWidth' => 250])</div>

        {{-- Nombre del proyecto + cuadro del llamado (auto-ajustados al ancho de la caja) --}}
        <div class="hero-side">
            <div class="hero-project" id="heroProject" style="font-size:46px;">{{ $branding['brand_name'] ?? 'PROYECTO' }}</div>
            <div class="hero-callbox">
                <div class="cl-loc" id="heroCall">{{ $report->slug_setting }} - {{ $report->location_name }} - {{ $report->slug_time }}</div>
                <div class="cl-date">{{ \Carbon\Carbon::parse($report->report_date)->format('d M Y') }} | CALL {{ $report->call_time ? \Carbon\Carbon::parse($report->call_time)->format('H:i') : '--:--' }} HRS.</div>
            </div>
        </div>

        {{-- CrewCare + módulo (pie del hero): blanco esmerilado + esquinas superiores redondeadas + negro. --}}
        <div class="hero-brand">
            <div class="cc"><span class="crew">Crew</span><span class="care">Care</span></div>
            <div class="mod">{{ __('reports.dsr_module') }}</div>
        </div>

        <div class="hero-orange"></div>
    </div>

  </td></tr></thead>
  <tbody>

    {{-- FILA 1 (solo pág. 1): stats + franja + resumen + título, como <tr> DIRECTO (no anidado). --}}
    <tr class="page1-block"><td>
    <div class="doc-body">

        {{-- ===== BANDA DE STATS (solo pág. 1) ===== --}}
        <div class="stats-band">
            <div class="col">
                <div class="text-center"><span class="lbl">{{ __('reports.dsr_shoot_day') }}</span><span class="val">{{ \App\Support\ProductionCalendar::labelForReport($report) }}</span></div>
                <div class="text-center"><span class="lbl">{{ __('reports.dsr_crew') }}</span><span class="val">{{ $report->crew_count }}</span></div>
                <div class="text-center"><span class="lbl">{{ __('reports.dsr_logs') }}</span><span class="val ok">{{ $report->logs->count() }}</span></div>
            </div>
            <div class="wx">
                {{-- Mapeo value->emoji: cubre las condiciones ampliadas del create (storm/hail/snow/
                     windy/extreme_heat), no sólo sunny/cloudy/rainy. Fallback = nublado. --}}
                @php
                    $wxIcons = [
                        'sunny' => '☀️', 'cloudy' => '☁️', 'rainy' => '🌧️',
                        'storm' => '⛈️', 'hail' => '🌨️', 'snow' => '❄️',
                        'windy' => '💨', 'extreme_heat' => '🥵',
                    ];
                    $wxEmoji = isset($wxIcons[$report->weather_condition]) ? $wxIcons[$report->weather_condition] : '☁️';
                @endphp
                <span style="font-size:28px;">{{ $wxEmoji }}</span>
                <div class="text-white leading-none">
                    <div style="font-size:9px; color:#8b93a3; text-transform:uppercase; letter-spacing:.14em; margin-bottom:2px;">{{ __('reports.dsr_min_max') }}</div>
                    <div style="font-family:monospace;">
                        <span style="font-size:18px; font-weight:700; color:#93c5fd;">{{ $report->weather_min_temp }}°</span>
                        <span style="color:#6b7280;"> / </span>
                        <span style="font-size:20px; font-weight:700; color:#fca5a5;">{{ $report->weather_max_temp }}°C</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== FRANJA: safety / heatmap / medical (solo pág. 1) ===== --}}
        <div class="bg-white border-b border-gray-200 relative z-20 shadow-sm">
            <div class="flex divide-x divide-gray-100">
                <div class="w-1/3 p-3 flex items-start gap-3">
                    <div class="bg-blue-50 p-2 rounded text-blue-600 shrink-0">📢</div>
                    <div>
                        <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.dsr_safety_meeting') }}</h4>
                        <p class="text-xs font-bold text-gray-800">{{ $report->safety_meeting_time ? \Carbon\Carbon::parse($report->safety_meeting_time)->format('H:i') . ' HRS' : '—' }}</p>
                        <p class="text-[10px] text-gray-500 leading-tight">{{ $report->safety_meeting_topics }}</p>
                    </div>
                </div>

                <div class="w-1/3 p-3 flex flex-col items-center justify-center">
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider mb-1">{{ __('reports.dsr_risk_heatmap') }}</h4>
                    <div class="flex gap-4">
                        <div class="text-center"><span class="text-xs block">⚡</span><div class="w-1.5 h-1.5 rounded-full mx-auto {{ $heatmap['electrical'] ? 'bg-orange-500' : 'bg-gray-300' }} mt-1"></div></div>
                        <div class="text-center"><span class="text-xs block">🚗</span><div class="w-1.5 h-1.5 rounded-full mx-auto {{ $heatmap['traffic'] ? 'bg-red-500' : 'bg-gray-300' }} mt-1"></div></div>
                        <div class="text-center"><span class="text-xs block">🏗️</span><div class="w-1.5 h-1.5 rounded-full mx-auto {{ $heatmap['heights'] ? 'bg-orange-500' : 'bg-gray-300' }} mt-1"></div></div>
                        <div class="text-center"><span class="text-xs block">🔥</span><div class="w-1.5 h-1.5 rounded-full mx-auto {{ $heatmap['fire'] ? 'bg-red-500' : 'bg-gray-300' }} mt-1"></div></div>
                    </div>
                </div>

                <div class="w-1/3 p-3 flex items-start gap-3 bg-red-50/30">
                    <div class="bg-red-50 p-2 rounded text-red-600 shrink-0">🏥</div>
                    <div>
                        <h4 class="text-[9px] uppercase font-bold text-red-400 tracking-wider">{{ __('reports.dsr_medical_support') }}</h4>
                        <p class="text-xs font-bold text-gray-800">{{ $report->nearest_hospital }}</p>
                        <p class="text-[10px] text-gray-500">{{ $report->ambulance_company }} | {{ __('reports.dsr_medic_abbr') }}: {{ $report->medic_name }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== CONTENIDO (solo pág. 1: resumen ejecutivo) ===== --}}
        <div class="p-8 pb-2">
            <div>
                <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.dsr_section_executive_summary') }}
                </h3>
                <div class="text-sm text-gray-600 leading-relaxed text-justify border-l-4 border-gray-100 pl-4">
                    <p>{{ $report->executive_summary ?: __('reports.dsr_empty_executive_summary') }}</p>
                </div>
            </div>
        </div>{{-- /p-8 --}}

        {{-- ===== AMBIENTALES · EPP · FACTORES DE RIESGO · FIRMA (cimientos módulos 6-9) =====
             Todo gated defensivamente: en prod (sin el SQL) estas columnas/tabla no existen,
             así que se comprueba hasColumn/hasTable antes de leer y la sección solo aparece si
             hay algo real que mostrar. --}}
        @php
            $ccHasHumidity = \Illuminate\Support\Facades\Schema::hasColumn('daily_reports', 'humidity');
            $ccHasWind     = \Illuminate\Support\Facades\Schema::hasColumn('daily_reports', 'wind_speed');
            $ccHasHeat     = \Illuminate\Support\Facades\Schema::hasColumn('daily_reports', 'heat_index');
            $ccHasPpe      = \Illuminate\Support\Facades\Schema::hasColumn('daily_reports', 'required_ppe');
            $ccHasRisk     = \Illuminate\Support\Facades\Schema::hasColumn('daily_reports', 'day_risk_factors');

            $ccHumidity = $ccHasHumidity ? $report->humidity  : null;
            $ccWind     = $ccHasWind     ? $report->wind_speed : null;
            $ccHeat     = $ccHasHeat     ? $report->heat_index : null;
            $ccPpe  = ($ccHasPpe  && is_array($report->required_ppe))     ? array_filter($report->required_ppe)     : [];
            $ccRisk = ($ccHasRisk && is_array($report->day_risk_factors)) ? array_filter($report->day_risk_factors) : [];

            $ccSigValid = \Illuminate\Support\Facades\Schema::hasTable('digital_signatures') ? $report->verifyLatestSignature() : null;

            $ccHasEnv = ($ccHumidity !== null) || ($ccWind !== null) || ($ccHeat !== null);
            $ccShow   = $ccHasEnv || count($ccPpe) > 0 || count($ccRisk) > 0 || $ccSigValid !== null;
        @endphp
        @if($ccShow)
        <div class="px-8 pb-4 break-inside-avoid">
            <div class="flex justify-between items-end border-b border-gray-200 pb-2 mb-4">
                <h3 class="font-poster text-xl text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.dsr_section_environmental_ppe') }}
                </h3>
                @if($ccSigValid === true)
                    <span class="inline-flex items-center gap-1 bg-green-50 border border-green-300 text-green-800 text-[10px] font-bold px-2 py-1 rounded">
                        <i class="fas fa-shield-alt"></i> {{ __('reports.dsr_signed_intact') }}
                    </span>
                @elseif($ccSigValid === false)
                    <span class="inline-flex items-center gap-1 bg-red-50 border border-red-300 text-red-800 text-[10px] font-bold px-2 py-1 rounded">
                        <i class="fas fa-exclamation-triangle"></i> {{ __('reports.dsr_signature_mismatch') }}
                    </span>
                @endif
            </div>

            @if($ccHasEnv)
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-gray-50 border border-gray-200 rounded p-3 text-center">
                    <div class="text-[9px] uppercase tracking-widest text-gray-400 font-bold">{{ __('reports.label_humidity') }}</div>
                    <div class="text-lg font-bold text-gray-800">{{ $ccHumidity !== null ? $ccHumidity . '%' : '—' }}</div>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded p-3 text-center">
                    <div class="text-[9px] uppercase tracking-widest text-gray-400 font-bold">{{ __('reports.label_wind') }}</div>
                    <div class="text-lg font-bold text-gray-800">{{ $ccWind !== null ? $ccWind . ' km/h' : '—' }}</div>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded p-3 text-center">
                    <div class="text-[9px] uppercase tracking-widest text-gray-400 font-bold">{{ __('reports.label_heat_index') }}</div>
                    <div class="text-lg font-bold {{ ($ccHeat !== null && (float) $ccHeat >= 39) ? 'text-red-600' : 'text-gray-800' }}">{{ $ccHeat !== null ? $ccHeat . '°C' : '—' }}</div>
                </div>
            </div>
            @endif

            @if(count($ccRisk) > 0)
            <div class="mb-3">
                <div class="text-[9px] uppercase tracking-widest text-gray-400 font-bold mb-1">{{ __('reports.label_day_risk_factors') }}</div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($ccRisk as $factor)
                        <span class="inline-flex items-center bg-amber-50 border border-amber-300 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded">{{ $factor }}</span>
                    @endforeach
                </div>
            </div>
            @endif

            @if(count($ccPpe) > 0)
            <div class="mb-1">
                <div class="text-[9px] uppercase tracking-widest text-gray-400 font-bold mb-1">{{ __('reports.label_required_ppe') }}</div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($ccPpe as $ppe)
                        <span class="inline-flex items-center gap-1 bg-blue-50 border border-blue-300 text-blue-800 text-[10px] font-bold px-2 py-0.5 rounded">🦺 {{ $ppe }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>{{-- /doc-body --}}
    </td></tr>{{-- /page1-block --}}

    {{-- BITÁCORA en tabla ANIDADA: su <thead> (título) se REPITE en cada hoja (pág.1 tras el resumen,
         pág.2+ arriba). Es un detalle estético que el owner pidió; el anidado empaqueta un pelín
         menos que filas directas, pero con la compactación de print sigue entrando en 2 páginas. --}}
    <tr><td>
    @php $stdUrls = $standards->pluck('reference_url', 'regulation_code'); @endphp
    <table class="logs-table">
        <thead><tr><th class="log-head">
            <div class="flex justify-between items-end border-b border-gray-200 pb-2 mb-4">
                <h3 class="font-poster text-xl text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.dsr_section_log_compliance') }}
                </h3>
            </div>
        </th></tr></thead>
        <tbody>
    @forelse($report->logs->chunk(2) as $pair)
        <tr class="log-row">
            <td class="log-cell-wrap">
                <div class="log-flex">
                                    @foreach($pair as $log)
                                        <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden group">
                                            <div class="relative h-48 overflow-hidden bg-gray-100 log-photo">
                                                @if($log->photo_path)
                                                    <img src="{{ $log->photo_path }}" class="w-full h-full object-cover">
                                                @endif
                                                <div class="absolute top-2 left-2 bg-black/60 backdrop-blur text-white text-[9px] font-bold px-2 py-0.5 rounded">
                                                    {{ \Carbon\Carbon::parse($log->log_time)->format('H:i') }} HRS
                                                </div>
                                                {{-- (Ola A · Pilar 2) Marca de log INYECTADO: se generó automáticamente por un
                                                     evento crítico (accidente/condición insegura/SFX/consulta ligada), no a mano.
                                                     is_injected = !empty(sourceable_type) (accesor del modelo; null-safe en prod). --}}
                                                @if($log->is_injected)
                                                <div class="absolute top-2 right-2 bg-purple-600/80 backdrop-blur text-white text-[9px] font-bold px-2 py-0.5 rounded flex items-center gap-1" title="Registro inyectado automáticamente por un evento crítico">⚙ Auto</div>
                                                @endif
                                                <div class="brand-corner"></div>
                                            </div>
                                            <div class="p-3">
                                                <div class="flex justify-between items-start mb-2">
                                                    <h4 class="font-bold text-gray-800 text-xs w-3/4 leading-tight">{{ $log->description }}</h4>
                                                    <div class="text-right">
                                                        <span class="badge badge-{{ $log->regulation_badge }}">{{ $log->regulation_badge }}</span>
                                                        <div class="text-[8px] text-gray-400 font-mono mt-0.5">{{ $log->regulation_code }}</div>
                                                        @php $stdUrl = $stdUrls[$log->regulation_code] ?? null; @endphp
                                                        @if($stdUrl)
                                                            <a href="{{ $stdUrl }}" target="_blank" rel="noopener" class="no-print inline-block text-[8px] font-bold text-red-700 hover:underline mt-0.5">📄 Ver boletín</a>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if($log->action_taken)
                                                <div class="bg-green-50 border-l-2 border-green-500 p-1.5 rounded-r mt-2">
                                                    <p class="text-[10px] text-green-800"><strong>{{ __('reports.dsr_log_action') }}:</strong> {{ $log->action_taken }}</p>
                                                </div>
                                                @endif

                                                {{-- PDCA por hallazgo: el ActionItem que genera la Acción Correctiva del log
                                                     (dueño + fecha límite + estado). Guard hasTable → invisible en PROD sin el SQL.
                                                     Sin esto el motor PDCA corría pero no se veía en el documento. --}}
                                                @if(\Illuminate\Support\Facades\Schema::hasTable('action_items') && $log->actionItems->count())
                                                <div class="mt-2 pt-2 border-t border-gray-100 space-y-1">
                                                    @foreach($log->actionItems as $ai)
                                                        @php
                                                            $aiClosed  = $ai->status === 'closed';
                                                            $aiOverdue = method_exists($ai, 'isOverdue') && $ai->isOverdue();
                                                            if ($aiClosed) {
                                                                $aiChip = 'bg-green-100 text-green-800'; $aiLabel = __('reports.status_closed');
                                                            } elseif ($ai->status === 'in_progress') {
                                                                $aiChip = 'bg-blue-100 text-blue-800'; $aiLabel = __('reports.status_in_progress');
                                                            } elseif ($aiOverdue) {
                                                                $aiChip = 'bg-red-100 text-red-700'; $aiLabel = __('reports.status_open');
                                                            } else {
                                                                $aiChip = 'bg-amber-100 text-amber-800'; $aiLabel = __('reports.status_open');
                                                            }
                                                        @endphp
                                                        <div class="flex items-center justify-between gap-2 text-[9px] leading-tight">
                                                            <span class="inline-flex items-center gap-1 text-gray-500 min-w-0">
                                                                <span class="text-purple-500">📋</span>
                                                                <span class="uppercase font-bold text-gray-400 tracking-wider">PDCA</span>
                                                                <span class="truncate">{{ $ai->owner ? $ai->owner->name : '—' }}</span>
                                                                @if($ai->due_date)
                                                                    <span class="text-gray-300">·</span>
                                                                    <span class="{{ $aiOverdue ? 'text-red-600 font-bold' : 'text-gray-500' }}">{{ $ai->due_date->format('d/m/Y') }}</span>
                                                                    @if($aiOverdue)<span class="text-red-600 font-bold uppercase">{{ __('reports.label_overdue') }}</span>@endif
                                                                @endif
                                                            </span>
                                                            <span class="shrink-0 inline-block font-bold px-1.5 py-0.5 rounded-full {{ $aiChip }}">{{ $aiLabel }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                    {{-- Fila impar: hueco flex para que la tarjeta sola quede a media columna. --}}
                                    @if($pair->count() === 1)<div aria-hidden="true"></div>@endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td><p class="text-gray-500 text-sm italic px-8">{{ __('reports.dsr_empty_logs') }}</p></td></tr>
                    @endforelse
        </tbody>
    </table>{{-- /logs-table anidada --}}
    </td></tr>
  </tbody>

  {{-- ESPACIADOR del pie: <tfoot> que se repite al fondo de CADA hoja SOLO para RESERVAR el alto
       del pie (así el contenido nunca lo tapa). El pie visual va aparte, fijo al fondo real. --}}
  <tfoot><tr><td><div class="footer-spacer"></div></td></tr></tfoot>
  </table>
</div>

{{-- PIE firmable (visual). En print = position:fixed; bottom:0 con @page margin:0 → pegado al fondo
     REAL de CADA hoja (incluida la última parcial), SIN hueco (el <tfoot> de arriba le reserva el
     espacio para que el contenido no lo tape). Diseño del pie intacto. --}}
<div class="mt-8 bg-gray-50 border-t border-gray-200 p-6 print-footer">
            <div class="flex justify-between items-end">

                <div>
                    <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-4">{{ __('reports.label_prepared_by') }}</p>
                    <div class="flex items-center gap-3">

                        <div>
                            <p class="font-bold text-gray-800 text-sm uppercase">{{ $report->author_name }}</p>
                            <p class="text-xs text-gray-500">{{ __('reports.label_risk_assessment') }}</p>
                        </div>
                    </div>
                </div>

                <div class="text-right opacity-70">
                    <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-1">{{ __('reports.label_powered_by') }}</p>
                    <div class="flex items-center justify-end gap-2 mb-1">
                        {{-- PNG gris local de alta resolución (2611×817). Antes: SVG remoto + filtro CSS
                             grayscale, que Chrome rasteriza a baja resolución en print → borroso.
                             Ruta RAÍZ-RELATIVA (no asset(): APP_URL='127.0.0.1' está mal y genera URL rota). --}}
                        <img src="/img/logo-cc-gris.png" class="h-5 w-auto opacity-80" alt="CrewCare Logo">
                    </div>
                    <p class="text-[8px] text-gray-400 font-mono mt-1">
                        UUID: {{ $branding['brand_name'] ?? 'CrewCare' }}-{{ 16210 + $report->id }}-{{ \Carbon\Carbon::parse($report->created_at)->format('dmY') }} | VER 2.1
                    </p>
                </div>

            </div>
        </div>

<!-- MODALES
========================================== -->

<div class="modal fade no-print" id="addLogModal" tabindex="-1" aria-labelledby="addLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-bold" id="addLogModalLabel">Registrar Nuevo Hallazgo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('daily_logs.store', $report->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body bg-light text-start p-4">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-sm">Hora del Evento *</label>
                            <input type="time" name="log_time" class="form-control" value="{{ date('H:i') }}" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold text-sm">Evento / peligro (catálogo) <span class="text-danger">*</span></label>
                            @include('componentes._event-picker', [
                                'hazardEvents' => $hazardEvents ?? collect(),
                                'name'         => 'hazard_event_id',
                                'selected'     => old('hazard_event_id'),
                                'required'     => true,
                            ])
                            <div class="form-text text-xs">Al elegir el evento se etiqueta automáticamente su norma (CSATF/STPS/OSHA).</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-sm">Descripción de la Observación *</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Ej: Personal cruzando cerca de la grúa en movimiento..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-sm text-success">Acción Correctiva (Mitigación)</label>
                        <input type="text" name="action_taken" class="form-control bg-success bg-opacity-10 border-success" placeholder="Ej: Se detiene maniobra y se reubica al personal.">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-sm">Foto Evidencia</label>
                        <input type="file" name="photo" class="form-control" accept="image/*" capture="environment">
                        <div class="form-text text-xs text-muted">Abre la cámara directo en tu celular.</div>
                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold">Guardar Hallazgo</button>
                </div>
            </form>

        </div>
    </div>
</div>


<div class="modal fade no-print" id="editReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title font-bold"><i class="fas fa-pen"></i> Cierre de Día (Wrap)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form action="{{ route('daily_reports.update', $report->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body bg-light text-start p-4">

                    <div class="mb-3">
                        <label class="form-label fw-bold text-sm">Resumen Ejecutivo de Seguridad</label>
                        <textarea name="executive_summary" class="form-control" rows="4" placeholder="Describe cómo se desarrolló el día, si hubo incidentes notables o si todo transcurrió con normalidad...">{{ $report->executive_summary }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-sm">Hero Image (Foto de Portada)</label>
                        <input type="file" name="hero_image" class="form-control" accept="image/*">
                        <div class="form-text text-xs text-muted">Esta imagen reemplazará el fondo negro de la cabecera.</div>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold">Actualizar Reporte</button>
                </div>
            </form>

        </div>
    </div>
</div>

{{-- Auto-ajuste del nombre del proyecto y del llamado al ancho de su caja (encoge, nunca desborda).
     Corre en pantalla; el PDF (window.print) reusa el layout ya calculado. --}}
<script>
(function () {
    function fit(el, maxPx, minPx) {
        if (!el) return;
        var size = maxPx;
        el.style.fontSize = size + 'px';
        while (el.scrollWidth > el.clientWidth && size > minPx) { size -= 1; el.style.fontSize = size + 'px'; }
    }
    function run() {
        fit(document.getElementById('heroProject'), 46, 18);
        fit(document.getElementById('heroCall'), 12, 8);
    }
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(run); }
    window.addEventListener('load', run);
    run();
})();
</script>
@endsection
