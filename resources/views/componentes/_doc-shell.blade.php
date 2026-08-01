{{--
    _doc-shell.blade.php — SANDBOX v2 (2026-07-15). Motor de PAGINACIÓN portado del Daily Safety
    Report (patrón de oro) a un parcial reutilizable, de modo que el HERO se REPITA en la pág. 2+
    de CUALQUIER reporte (los reportes v1 salvo el DSR no lo hacen).

    Mecánica (fiable en Chrome print): una <table class="report-wrap"> envolvente cuyo
      · <thead>  → se reimprime en CADA hoja  → HERO homologado (_doc-hero).
      · <tbody>  → el cuerpo del documento     → @yield('doc_body') (lo aporta la vista).
      · <tfoot>  → reserva el alto del pie      → footer-spacer.
    El pie visual va fijo (position:fixed; bottom:0) con @page margin:0 → pegado al fondo real de
    cada hoja, sin hueco. Idéntico al DSR.

    Uso desde la vista (Tailwind/reporte):
        @section('doc_body')  ...contenido...  @endsection
        @include('componentes._doc-shell', [ hero* + foot* ])
    OJO: define la @section('doc_body') ANTES de incluir este parcial.

    Requiere @include('componentes._doc-hero-styles') una vez en la vista.

    Parámetros hero (se pasan tal cual a _doc-hero):
      $heroImage, $heroProject, $heroLocation, $heroDate, $heroTime, $heroModule
    Parámetros pie:
      $footPreparedBy   (string)  nombre de quien preparó
      $footPreparedRole (string)  rótulo (p.ej. __('reports.label_risk_assessment'))
      $footPreparedDate (string|null) fecha ya formateada
      $footUuid         (string)  identificador del documento (pie)
--}}
@php
    $heroProject      = ($heroProject ?? null) ?: ($branding['brand_name'] ?? 'PROYECTO');
    $footPreparedBy   = $footPreparedBy   ?? '—';
    $footPreparedRole = $footPreparedRole ?? '';
    $footPreparedDate = $footPreparedDate ?? null;
    $footUuid         = $footUuid ?? '';
@endphp

<style>
    /* Tabla envolvente: su <thead> (hero) y <tfoot> (espaciador) se repiten en cada hoja impresa.
       En pantalla es un contenedor transparente de ancho completo. */
    .report-wrap { width:100%; border-collapse:collapse; }
    .report-wrap > thead > tr > td,
    .report-wrap > tbody > tr > td,
    .report-wrap > tfoot > tr > td { padding:0; border:0; }

    /* === PDF NATIVO (window.print) — OFICIO/LEGAL con hero + pie repetidos === */
    @media print {
        @page { size: legal portrait; margin: 0; }
        body {
            margin:0;
            background-color:white !important;
            -webkit-print-color-adjust:exact !important;
            print-color-adjust:exact !important;
        }
        .no-print, .navbar, .sidebar, #sidebar { display:none !important; }
        .max-w-4xl { max-width:100% !important; width:100% !important; box-shadow:none !important; margin:0 !important; }
        .shadow-lg, .shadow-2xl, .shadow-sm, .shadow { box-shadow:none !important; }

        /* Repetición por mecánica de tabla: HERO (thead) + espaciador de pie (tfoot). */
        .report-wrap > thead { display: table-header-group; }
        .report-wrap > tfoot { display: table-footer-group; }
        .break-inside-avoid, .grid > div, p, h1, h2, h3, h4, textarea {
            page-break-inside:avoid !important; break-inside:avoid !important;
        }

        /* El hero se repite en CADA hoja → en print lo compactamos para no comerse la página. */
        .doc-hero { height:162px !important; }
        .hero-logo-plate { background-color: rgba(255,255,255,0.34) !important;
            border:1px solid rgba(255,255,255,0.45) !important; box-shadow:0 6px 18px rgba(0,0,0,.35) !important; }

        /* PIE fijo al fondo real; el <tfoot>.footer-spacer le reserva el alto en el flujo. */
        .footer-spacer { height:118px; }
        .print-footer {
            position:fixed; bottom:0; left:0; right:0; width:100%;
            background-color:#f9fafb !important; border-top:1px solid #e5e7eb !important;
        }
    }
</style>

<div class="max-w-4xl mx-auto bg-white shadow-2xl min-h-screen overflow-hidden mb-10">
    <table class="report-wrap">
        {{-- HERO dentro de <thead> → se repite en CADA hoja impresa. --}}
        <thead><tr><td>
            @include('componentes._doc-hero', [
                'heroImage'    => $heroImage    ?? null,
                'heroProject'  => $heroProject,
                'heroLocation' => $heroLocation ?? '',
                'heroDate'     => $heroDate     ?? null,
                'heroTime'     => $heroTime     ?? null,
                'heroModule'   => $heroModule   ?? '',
            ])
        </td></tr></thead>

        <tbody><tr><td>
            @yield('doc_body')
        </td></tr></tbody>

        {{-- ESPACIADOR del pie: reserva el alto del pie fijo para que el contenido no lo tape. --}}
        <tfoot><tr><td><div class="footer-spacer"></div></td></tr></tfoot>
    </table>
</div>

{{-- PIE firmable (visual). En print = position:fixed; bottom:0 con @page margin:0 → pegado al
     fondo real de cada hoja. Logo PNG gris LOCAL (no SVG remoto: Chrome lo rasteriza borroso). --}}
<div class="mt-8 bg-gray-50 border-t border-gray-200 p-6 print-footer">
    <div class="flex justify-between items-end">
        <div>
            <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-4">{{ __('reports.label_prepared_by') }}</p>
            <div class="flex items-center gap-3">
                <div>
                    <p class="font-bold text-gray-800 text-sm uppercase">{{ $footPreparedBy }}</p>
                    <p class="text-xs text-gray-600">{{ $footPreparedRole }}{{ $footPreparedDate ? ' · ' . $footPreparedDate : '' }}</p>
                </div>
            </div>
        </div>
        <div class="text-right opacity-70">
            <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-1">{{ __('reports.label_powered_by') }}</p>
            <div class="flex items-center justify-end gap-2 mb-1">
                <img src="/img/logo-cc-gris.png" class="h-5 w-auto opacity-80" alt="{{ $branding['brand_name'] ?? 'CrewCare' }}">
            </div>
            <p class="text-[8px] text-gray-500 font-mono mt-1">{{ $footUuid }}</p>
        </div>
    </div>
</div>
