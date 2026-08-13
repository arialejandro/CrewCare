@extends('layouts.app')

{{-- CSRF: the cropper AJAX (public/js/inicio.js) reads this `_token` meta by name.
     The layout exposes a differently-named `csrf-token` meta; to avoid any risk we
     KEEP the original `_token` meta here and the JS keeps reading it (decision
     documented in the rebuild report). Both metas hold the same csrf_token(). --}}
@push('styles')
    <meta name="_token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/inicio.css') }}">
    {{-- Cropper.js styles (load-bearing: profile-photo cropper, crew branch). --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.6/cropper.css"/>

    {{-- Tailwind por CDN — igual que las vistas de reporte. Se carga en <head> (no en el
         body) para REDUCIR el FOUC/CLS: los estilos están disponibles antes del primer
         paint del contenido. preflight OFF para no pisar Bootstrap del resto de la app. --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { corePlugins: { preflight: false } };</script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,400;0,700;0,900;1,900&display=swap" rel="stylesheet">
    <style>
        .font-poster { font-family: 'Roboto Condensed', sans-serif; font-weight: 900; text-transform: uppercase; letter-spacing: .02em; }

        /* ===== Paneles de VIDRIO (tokens del layer glass → dark mode real) ===== */
        .cc-panel {
            background: var(--glass); border: 1px solid var(--stroke);
            border-radius: var(--radius); box-shadow: var(--shadow);
        }
        @media screen {
            .cc-panel { -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat)); backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat)); }
        }
        @supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
            .cc-panel { background: var(--surface-2); }
        }
        .cc-panel__title { color: var(--text); }
        .cc-panel__note  { color: var(--text-muted); }
        .cc-accent-bar   { width: 8px; height: 20px; background: var(--brand-primary); display: inline-block; transform: skewX(-12deg); border-radius: 2px; }

        /* ===== Hero de bienvenida (vidrio + glow de marca) ===== */
        .cc-hero {
            background: var(--glass); color: var(--text);
            border: 1px solid var(--stroke); box-shadow: 0 12px 34px -12px var(--brand-glow), var(--shadow);
        }
        @media screen {
            .cc-hero { -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat)); backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat)); }
        }
        @supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
            .cc-hero { background: var(--surface-2); }
        }
        .cc-hero__glow { background: rgba(var(--brand-primary-rgb), .30); }
        .cc-hero__eyebrow { color: var(--brand-primary); }
        .cc-hero__sub { color: var(--text-muted); }
        .cc-hero__cta {
            background: linear-gradient(150deg, var(--brand-primary), var(--brand-accent));
            color: var(--brand-on-primary);
            box-shadow: 0 12px 30px -10px var(--brand-glow);
            transition: transform .2s var(--ease), box-shadow .2s var(--ease), filter .2s var(--ease);
        }
        .cc-hero__cta:hover { transform: translateY(-2px); box-shadow: 0 18px 40px -10px var(--brand-glow); color: var(--brand-on-primary); }

        /* ===== Tarjeta KPI de VIDRIO (consumida por componentes/_dashboard-card) ===== */
        .cc-kpi {
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; gap: .2rem;
            background: var(--glass); border: 1px solid var(--stroke);
            border-radius: var(--radius); box-shadow: var(--shadow);
            padding: 1rem 1rem 1.1rem; text-decoration: none; min-height: 44px;
            transition: transform .2s var(--ease), box-shadow .2s var(--ease), border-color .2s var(--ease), background .2s var(--ease);
        }
        @media screen {
            .cc-kpi { -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat)); backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat)); }
        }
        @supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
            .cc-kpi { background: var(--surface-2); }
        }
        a.cc-kpi:hover { transform: translateY(-4px); border-color: var(--stroke-2); background: var(--glass-2); }
        .cc-kpi__stripe { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--kpi-accent); }
        .cc-kpi__top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .55rem; }
        .cc-kpi__ico {
            width: 40px; height: 40px; border-radius: 12px; flex: none;
            display: grid; place-items: center; color: var(--kpi-accent);
            background: #eef2f7; /* fallback sin color-mix */
            background: color-mix(in srgb, var(--kpi-accent) 15%, transparent);
        }
        .cc-kpi__ico-svg { width: 22px; height: 22px; }
        .cc-kpi__pill {
            font-size: .6rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
            padding: 3px 8px; border-radius: 20px; color: var(--kpi-accent);
            background: color-mix(in srgb, var(--kpi-accent) 16%, transparent);
        }
        .cc-kpi__value {
            font-size: clamp(1.5rem, 4vw, 1.9rem); font-weight: 800; line-height: 1;
            color: var(--text); font-variant-numeric: tabular-nums;
        }
        .cc-kpi__label {
            font-size: .8125rem; /* 13px — legible, ≥12-13px */ font-weight: 600;
            color: var(--text-muted); line-height: 1.25; margin-top: .15rem;
        }
        .cc-kpi__spark { position: absolute; left: 0; right: 0; bottom: 0; width: 100%; height: 28px; opacity: .8; pointer-events: none; }

        /* ===== Empty state honesto (actividad reciente) ===== */
        .cc-empty { color: var(--text-muted); }
        .cc-empty__ico { color: var(--text-muted); opacity: .7; }
    </style>
@endpush

@section('content')

{{-- Tamaños de icono explícitos (.cc-ico-NN): antes varios _icon caían en clases
     Tailwind/undefined (p.ej. cc-nav-ico) y el SVG se inflaba. El kit los fija. --}}
@include('componentes._form-kit')

{{-- GATE tablero ⇄ tarjeta de perfil.
     (2026-07-24) Antes era `users.view`, y ése es el permiso de administrar CREW, no el de
     tener tablero: el Médico de Set no lo tiene, así que entraba a /home y se encontraba su
     propia foto y su cuestionario — exactamente lo que ya le da /perfil. Ahora decide
     canSeePanel(), la misma fuente única que abre el sidebar: si tienes panel, tienes tablero. --}}
@if(auth()->user()->canSeePanel())

{{-- ============================================================================
     DASHBOARD ADMIN — SHELL FLEXIBLE Y DATA-DRIVEN.
     Los KPIs son un arreglo PHP ($widgets) recorrido con el parcial reutilizable
     componentes/_dashboard-card, así agregar/quitar/reordenar paneles luego es
     trivial (esta info NO es la definitiva: crecerá a producción/dirección/
     transportación). Tokens semánticos → dark mode real. Grid 2→3→6 conservado.
     Se conservan el id del canvas (weeklyUnsafeTrendChart) y las variables del
     controlador. --}}
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

    {{-- ===== Banner de bienvenida ===== --}}
    <div class="cc-hero relative overflow-hidden rounded-2xl p-5 sm:p-7 mb-6 shadow-lg">
        <div class="cc-hero__glow absolute -right-8 -top-10 w-48 h-48 rounded-full blur-2xl"></div>
        <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="cc-hero__eyebrow text-[11px] uppercase tracking-[0.3em] font-bold mb-1">{{ __('dashboard.banner_eyebrow') }}</p>
                <h1 class="font-poster text-2xl sm:text-3xl leading-none">{{ __('dashboard.greeting') }}, {{ auth()->user()->name }}</h1>
                <p class="cc-hero__sub text-sm mt-2">
                    @if(auth()->user()->encuestadiaria)
                        {{ __('dashboard.survey_thanks') }}
                    @else
                        {{ __('dashboard.survey_pending') }}
                    @endif
                </p>
            </div>
            @unless(auth()->user()->encuestadiaria)
                <a href="/dailyreport" class="cc-hero__cta shrink-0 inline-flex items-center justify-center gap-2 font-bold text-sm px-5 py-3 rounded-xl shadow transition">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-16', 'label' => null])
                    {{ __('dashboard.survey_answer_btn') }}
                </a>
            @endunless
        </div>
    </div>

    {{-- ===== Grid de KPIs (2 → 3 → 6) — DATA-DRIVEN =====
         Cada widget: icono (_icon), etiqueta i18n, valor, ruta (hover con sentido) y
         tono semántico. Para agregar/quitar un panel, edita SOLO este arreglo. --}}
    @php
        // (2026-07-24) DOS cambios respecto al shell de ejemplo:
        //  · Los 'spark' ya NO son series inventadas. Vienen del controlador, calculados sobre las
        //    últimas 8 semanas reales; cuando una serie sale toda en cero, el controlador manda []
        //    y la tarjeta simplemente no dibuja línea. Un adorno que se parece a un dato es peor
        //    que no tener adorno.
        //  · Cada tarjeta va tras el permiso de la pantalla que abre. Antes se pintaban las 6 a
        //    todo el que tuviera users.view; ahora un médico ve las suyas y no tarjetas que al
        //    hacer clic le devolverían un 403.
        $widgets = [];
        if (!empty($canSee['injury'])) {
            $widgets[] = ['icon' => 'shield',         'label' => __('dashboard.kpi_days_no_accidents'), 'value' => $daysSinceLastAccident, 'route' => url('/accidents'),            'tone' => 'ok',     'pill' => __('dashboard.kpi_pill_safe')];
        }
        if (!empty($canSee['loc'])) {
            $widgets[] = ['icon' => 'map-pin',        'label' => __('dashboard.kpi_locations_checked'), 'value' => $totalLocationReports,  'route' => route('scoutings.index'),     'tone' => 'brand',  'spark' => $sparkLocations];
        }
        if (!empty($canSee['dsr'])) {
            $widgets[] = ['icon' => 'calendar',       'label' => __('dashboard.kpi_production_days'),   'value' => $productionDays,        'route' => route('daily_reports.index'), 'tone' => 'accent'];
            $widgets[] = ['icon' => 'camera',         'label' => __('dashboard.kpi_shooting_days'),     'value' => $shootingDays,          'route' => route('daily_reports.index'), 'tone' => 'accent', 'spark' => $sparkShooting];
        }
        if (!empty($canSee['injury'])) {
            $widgets[] = ['icon' => 'alert-triangle', 'label' => __('dashboard.kpi_total_accidents'),   'value' => $totalAccidents,        'route' => url('/accidents'),            'tone' => 'danger', 'pill' => __('dashboard.kpi_pill_attention'), 'spark' => $sparkAccidents];
        }
        if (!empty($canSee['medical'])) {
            $widgets[] = ['icon' => 'stethoscope',    'label' => __('dashboard.kpi_total_consults'),    'value' => $totalMedicalConsults,  'route' => route('medicocrud'),          'tone' => 'info',   'spark' => $sparkConsults];
        }
        // El número de columnas SIGUE al número de tarjetas: una rejilla de 6 con 3 tarjetas deja
        // media fila vacía y se lee como si algo no hubiera cargado.
        $cols = count($widgets) >= 6 ? 'xl:grid-cols-6' : (count($widgets) >= 4 ? 'xl:grid-cols-4' : '');
    @endphp
    {{-- (2026-07-24) Cintillo de calendario: en qué día vamos y cuánto falta para el wrap.
         Va ARRIBA de los KPIs porque es lo que ubica todo lo demás: un "3 accidentes" significa
         una cosa en el día 2 y otra muy distinta en el día 15. --}}
    @if(!empty($calendario))
        @include('componentes._dashboard-calendar', ['cal' => $calendario])
    @endif

    @if(count($widgets))
    <div class="grid grid-cols-2 md:grid-cols-3 {{ $cols }} gap-3 sm:gap-4 mb-6">
        @foreach($widgets as $w)
            @include('componentes._dashboard-card', ['w' => $w])
        @endforeach
    </div>
    @endif

    {{-- ===== Fila inferior: gráfica + panel médico / actividad =====
         El reparto depende de lo que la persona pueda ver. Para un médico no hay gráfica de
         actos inseguros (no tiene hazards.view), así que su panel médico ocupa el ancho
         completo en vez de dejar dos tercios en blanco. --}}
    @php
        $hayGrafica = !empty($canSee['haz']);
        $hayMedico  = $medical !== null;
    @endphp
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        @if($hayGrafica)
        {{-- Gráfica de tendencia (card, alto fluido) --}}
        <div class="cc-panel p-4 sm:p-5 lg:col-span-2">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-1 mb-4">
                <h2 class="cc-panel__title font-poster text-base sm:text-lg flex items-center gap-2">
                    <span class="cc-accent-bar"></span>
                    {{ __('dashboard.trend_title') }}
                </h2>
                <span class="cc-panel__note text-[11px]">{{ __('dashboard.trend_note') }}</span>
            </div>
            <div class="relative w-full" style="height: clamp(260px, 42vh, 440px);">
                <canvas id="weeklyUnsafeTrendChart"></canvas>
            </div>
        </div>
        @endif

        @if($hayMedico)
            @include('componentes._dashboard-medical', [
                'm'     => $medical,
                'ancho' => ! $hayGrafica,
            ])
        @else
        {{-- Slot de "actividad reciente" — placeholder honesto (aún sin feed). Cuando
             exista el feed, este bloque se llena; la estructura ya está lista. --}}
        <div class="cc-panel p-4 sm:p-5">
            <h2 class="cc-panel__title font-poster text-base sm:text-lg flex items-center gap-2 mb-4">
                <span class="cc-accent-bar"></span>
                {{ __('dashboard.activity_title') }}
            </h2>
            <div class="cc-empty flex flex-col items-center justify-center text-center gap-2 py-8">
                <span class="cc-empty__ico">
                    @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-ico-24', 'label' => null])
                </span>
                <p class="text-sm font-semibold">{{ __('dashboard.activity_empty') }}</p>
                <p class="text-xs max-w-[24ch]">{{ __('dashboard.activity_empty_hint') }}</p>
            </div>
        </div>
        @endif
    </div>

</div>

@else
{{-- CREW branch: profile card + photo-upload form + (modal below, shared). --}}
<div class="container py-4">
    <div class="row g-4">
        <div class="col-md-4">
            {{-- Profile photo upload form. The real flow is the AJAX cropper
                 (public/js/inicio.js); this HTML form is a fallback. Input file con
                 <label> asociado (accesibilidad). --}}
            <form action="{{ route('uploadCropImage') }}" enctype="multipart/form-data" method="POST">
                {{ csrf_field() }}
                {{ method_field('post') }}
                <label for="imgperfil" class="form-label d-inline-flex align-items-center gap-2 fw-semibold" style="color: var(--text);">
                    @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico-18', 'label' => null])
                    {{ __('dashboard.change_photo') }}
                </label>
                <input type="file" id="imgperfil" name="imgperfil" class="form-control image" accept="image/*,.heic,.heif">
            </form>
            <hr style="border-color: var(--border);">
            @include('componentes._profile-badge', ['user' => auth()->user()])
        </div>

        <div class="col-lg-8 col-md-8 col-12">
            @if(auth()->user()->encuestadiaria)
                <h1 class="display-5 content-wr" style="color: var(--text);">{{ __('dashboard.crew_survey_thanks') }}</h1>
            @else
                <h1 class="display-5 content-wr" style="color: var(--text);">{{ __('dashboard.crew_survey_pending') }}</h1>
                <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2 mt-2" href="/dailyreport">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-18', 'label' => null])
                    {{ __('dashboard.crew_survey_answer_btn') }}
                </a>
            @endif
        </div>
    </div>
</div>
@endif

{{-- Profile-photo cropper modal (shared; the .image trigger only exists in the
     crew branch). MIGRATED Bootstrap 4 -> Bootstrap 5:
       data-dismiss  -> data-bs-dismiss
       <button class="close"><span>&times;</span></button>  ->  <button class="btn-close">
     JS show/hide ported in public/js/inicio.js. --}}
<div class="modal fade" id="modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">{{ __('dashboard.crop_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('dashboard.crop_cancel') }}"></button>
            </div>
            <div class="modal-body">
                <div class="img-container">
                    <div class="row">
                        <div class="col-md-8 imgs">
                            <img class="imgs" id="image" src="https://avatars0.githubusercontent.com/u/3456749">
                        </div>
                        <div class="col-md-4">
                            <div class="preview"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('dashboard.crop_cancel') }}</button>
                <button type="button" class="btn btn-primary" id="crop">{{ __('dashboard.crop_update') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    {{-- Load-bearing libs for this page (dossier §8):
         - Cropper.js: profile-photo cropper widget (crew branch).
         - chartjs-plugin-trendline: trend line on the admin chart (Chart.js core
           comes from the layout).
         Bootstrap 4 CDN CSS/JS + Popper 1.x were REMOVED (modal ported to BS5,
         which the layout already provides). The duplicate jQuery was REMOVED
         (the layout already loads jQuery). --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.6/cropper.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-trendline"></script>

    {{-- Expose the upload route to the external JS (which can't use Blade). --}}
    <script>
        window.uploadCropImageUrl = "{{ route('uploadCropImage') }}";
    </script>
    <script src="{{ asset('js/inicio.js') }}"></script>

    {{-- Weekly unsafe-reports trend chart (admin branch). Data + textos i18n desde el
         servidor; colores de ejes/rejilla/leyenda leídos de los TOKENS para adaptarse
         a claro/oscuro. Locale de fechas = app()->getLocale(). --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const weeklyUnsafeTrendChartCanvas = document.getElementById('weeklyUnsafeTrendChart');
            if (!weeklyUnsafeTrendChartCanvas) { return; }
            const ctxWeeklyTrend = weeklyUnsafeTrendChartCanvas.getContext('2d');

            // Colores del tema (tokens semánticos) → gráfica legible en claro y oscuro.
            const css = getComputedStyle(document.documentElement);
            const textColor  = (css.getPropertyValue('--text') || '#1f2937').trim();
            const mutedColor = (css.getPropertyValue('--text-muted') || '#566072').trim();
            const gridColor  = (css.getPropertyValue('--border') || '#e2e6ea').trim();

            const locale = @json(app()->getLocale());
            const monthFmt = new Intl.DateTimeFormat(locale, { month: 'short' });

            const weeklyData = @json($weeklyUnsafeReports);
            const labels = weeklyData.map(function (item) {
                const startDate = new Date(item['week_start']);
                const endDate   = new Date(item['week_end']);
                return startDate.getDate() + ' ' + monthFmt.format(startDate) + ' - ' +
                       endDate.getDate() + ' ' + monthFmt.format(endDate);
            });

            new Chart(ctxWeeklyTrend, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: @json(__('dashboard.chart_acts')),
                            data: weeklyData.map(function (i) { return i['acts_count']; }),
                            borderColor: 'rgba(37, 99, 235, 1)',
                            backgroundColor: 'rgba(37, 99, 235, 0.2)',
                            fill: false, tension: 0.4, pointRadius: 3, pointHoverRadius: 5,
                            trendline: { style: 'rgba(37, 99, 235, 0.8)', lineStyle: 'dotted', width: 2 }
                        },
                        {
                            label: @json(__('dashboard.chart_conds')),
                            data: weeklyData.map(function (i) { return i['conds_count']; }),
                            borderColor: 'rgba(217, 119, 6, 1)',
                            backgroundColor: 'rgba(217, 119, 6, 0.2)',
                            fill: false, tension: 0.4, pointRadius: 3, pointHoverRadius: 5,
                            trendline: { style: 'rgba(217, 119, 6, 0.8)', lineStyle: 'dotted', width: 2 }
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 1000, easing: 'easeInOutQuad' },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: @json(__('dashboard.chart_y_axis')), color: mutedColor },
                            ticks: { stepSize: 1, precision: 0, color: mutedColor },
                            grid: { color: gridColor }
                        },
                        x: {
                            title: { display: true, text: @json(__('dashboard.chart_x_axis')), color: mutedColor },
                            ticks: { autoSkip: true, maxRotation: 45, minRotation: 0, maxTicksLimit: 10, color: mutedColor },
                            grid: { color: gridColor }
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'bottom', labels: { color: textColor, usePointStyle: true, padding: 16 } },
                        tooltip: { mode: 'index', intersect: false }
                    }
                }
            });
        });
    </script>

    {{-- Sparklines de los KPIs (canvas). Leen data-spark + data-tone de cada tarjeta; el color
         se resuelve de los TOKENS (se adapta a claro/oscuro) y se redibujan al cambiar de
         tamaño y al alternar el tema (MutationObserver).
         (2026-07-24) Las series ya NO son de adorno: son los conteos reales de las últimas 8
         semanas que calcula HomeController. Cuando una serie sale toda en cero, el controlador
         devuelve [] y la tarjeta no dibuja nada — dibujar una línea plana en el piso parecería
         una tendencia y no lo es. --}}
    <script>
        (function () {
            'use strict';
            var TONE = { ok: '--ok', danger: '--danger', warn: '--warn', brand: '--brand-primary', accent: '--brand-accent', info: '--brand-accent' };

            function cssVar(name) { return getComputedStyle(document.documentElement).getPropertyValue(name).trim(); }

            function hexA(color, alpha) {
                // Devuelve el color con alfa si es #rrggbb; si no, usa el color tal cual (fallback sin transparencia).
                if (/^#([0-9a-f]{6})$/i.test(color)) {
                    var r = parseInt(color.substr(1, 2), 16), g = parseInt(color.substr(3, 2), 16), b = parseInt(color.substr(5, 2), 16);
                    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
                }
                return color;
            }

            function drawSpark(canvas) {
                var raw = canvas.getAttribute('data-spark') || '';
                var data = raw.split(',').map(function (n) { return parseFloat(n); }).filter(function (n) { return !isNaN(n); });
                if (data.length < 2) { return; }
                var tone = canvas.getAttribute('data-tone') || 'brand';
                var color = cssVar(TONE[tone] || '--brand-primary') || '#888';

                var dpr = window.devicePixelRatio || 1;
                var w = canvas.clientWidth, h = canvas.clientHeight;
                if (!w || !h) { return; }
                canvas.width = w * dpr; canvas.height = h * dpr;
                var x = canvas.getContext('2d');
                x.setTransform(dpr, 0, 0, dpr, 0, 0);
                x.clearRect(0, 0, w, h);

                var mx = Math.max.apply(null, data), mn = Math.min.apply(null, data), pad = 4;
                function px(i) { return (i / (data.length - 1)) * w; }
                function py(v) { return h - pad - ((v - mn) / ((mx - mn) || 1)) * (h - pad * 2); }

                // Relleno degradado bajo la línea.
                x.beginPath();
                data.forEach(function (v, i) { i ? x.lineTo(px(i), py(v)) : x.moveTo(px(i), py(v)); });
                x.lineTo(w, h); x.lineTo(0, h); x.closePath();
                var g = x.createLinearGradient(0, 0, 0, h);
                g.addColorStop(0, hexA(color, .33)); g.addColorStop(1, hexA(color, 0));
                x.fillStyle = g; x.fill();

                // Línea.
                x.beginPath();
                data.forEach(function (v, i) { i ? x.lineTo(px(i), py(v)) : x.moveTo(px(i), py(v)); });
                x.strokeStyle = color; x.lineWidth = 2; x.lineJoin = 'round'; x.stroke();
            }

            function drawAll() {
                Array.prototype.forEach.call(document.querySelectorAll('.cc-kpi__spark'), drawSpark);
            }

            document.addEventListener('DOMContentLoaded', function () {
                drawAll();
                var t = null;
                window.addEventListener('resize', function () { if (t) { clearTimeout(t); } t = setTimeout(drawAll, 150); });
                // Redibuja al alternar el tema (el botón estampa data-theme en <html>).
                try {
                    new MutationObserver(function () { drawAll(); })
                        .observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
                } catch (e) {}
            });
        })();
    </script>
@endpush
