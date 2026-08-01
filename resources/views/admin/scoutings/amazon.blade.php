@extends('layouts.app')
@section('content')

{{-- ============================================================================================
     Documento en formato oficial "Amazon MGM Studios – Risk Assessment Form".
     Estilo de documento formal (bordes, tablas, negro sobre blanco) — distinto del póster
     "magazine" de show.blade.php. Bilingüe vía __('scouting.*', [], $lang); ?lang=es|en cambia
     SOLO este documento (locale global intacto).

     (2026-07-22) DECISIONES DEL OWNER aplicadas aquí:
       · AUTOCONTENIDO/OFFLINE: se eliminó `cdn.tailwindcss.com`. El documento que va al estudio
         NO puede depender de la red — sin internet salía sin estilos. Todo el maquetado vive en
         el <style> de abajo (mismo criterio que los gafetes). Sin utilidades Tailwind.
       · LIMPIO: el documento del estudio NO lleva sello ni hash visibles (la verificación SHA/CFDI
         vive en la versión estilizada de CrewCare, no aquí). Se quitó el pseudo-identificador
         "SCOUT-id-fecha" del bloque de firma, que se leía como si fuera un sello.
       · FIEL / SIN INVENTAR DATOS: lo vacío se queda vacío. Antes, una compañía vacía se sustituía
         por el nombre de MARCA (CrewCare) → un dato que nadie afirmó. Ya no. '—' es el marcador
         honesto de "sin dato", nunca un valor fabricado.
       · IMPRIME el desglose SB-132 (antes declaraba que había actividad especial y omitía cuál).
       · PAGINA: el encabezado va en <thead> → el motor de impresión lo repite en cada hoja.
       · FORMATO: papel CARTA (el del formulario original de Amazon MGM), no oficio.
============================================================================================ --}}

<style>
    :root { --brand-primary: {{ $branding['primary_color'] ?? '#ff9900' }}; }

    /* ---- Barra de acciones (no se imprime) ---- */
    .amz-toolbar{max-width:840px;margin:16px auto;display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center}
    .amz-btn{display:inline-flex;align-items:center;gap:6px;background:#fff;color:#374151;border:1px solid #d1d5db;padding:8px 16px;border-radius:6px;font-weight:600;font-size:14px;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,.05);cursor:pointer}
    .amz-btn:hover{background:#f3f4f6}
    .amz-btn-dark{background:#1f2937;color:#fff;border-color:#1f2937;font-weight:700}
    .amz-btn-dark:hover{background:#000}
    .amz-lang{display:inline-flex;border:1px solid #d1d5db;border-radius:6px;overflow:hidden;font-size:14px}
    .amz-lang a{padding:8px 12px;text-decoration:none}
    .amz-lang a.on{background:#1f2937;color:#fff}
    .amz-lang a.off{background:#fff;color:#374151}
    .amz-lang a.off:hover{background:#f3f4f6}

    /* ---- Documento ---- */
    .amz{font-family:Arial,Helvetica,sans-serif;color:#111}
    .amz-page{max-width:840px;margin:0 auto 40px;background:#fff;box-shadow:0 12px 40px rgba(0,0,0,.18);padding:32px}
    .amz-wrap{width:100%;border-collapse:collapse}
    .amz h2{font-weight:700}

    .amz-hdr{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;border-bottom:2px solid #000;padding-bottom:12px;margin-bottom:16px}
    .amz-hdr-fields{display:flex;flex-direction:column;gap:6px;flex:1}
    .amz-hdr-logo{text-align:right;flex:none}
    .amz-hdr-logo .sub{font-size:11px;letter-spacing:.2em;color:#6b7280;margin-top:8px;text-transform:uppercase}
    .hero-logo-plate{display:inline-flex;align-items:center;justify-content:center;padding:12px 20px}

    .amz-field{font-size:12px}
    .amz-field b{font-weight:700}

    .amz-tbl{width:100%;border-collapse:collapse;margin-bottom:12px}
    .amz-tbl th,.amz-tbl td{border:1px solid #333;padding:6px 8px;font-size:11.5px;vertical-align:top}
    .amz-tbl th{background:#f3f3f1;font-weight:700;text-align:left}

    .amz-sec{font-size:15px;font-weight:700;margin:22px 0 8px;padding-bottom:3px;border-bottom:2px solid #111}
    .amz-chip{display:inline-block;font-weight:700;border-radius:4px;padding:1px 7px;font-size:11px;-webkit-print-color-adjust:exact;print-color-adjust:exact}

    .amz-banner-red{background:#b91c1c;color:#fff;padding:8px 12px;border-radius:4px;font-weight:700;font-size:12px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .amz-overview{font-size:12px;line-height:1.6;border:1px solid #d1d5db;padding:12px;min-height:60px}
    .amz-list{font-size:12px;line-height:1.6;margin:0 0 16px}
    .amz-list.dec{list-style:decimal;padding-left:20px}
    .amz-list.pln{list-style:none;padding-left:4px}
    .amz-list li{margin-bottom:4px}

    .amz-firma{border-top:2px solid #000;padding-top:12px;display:flex;justify-content:space-between;align-items:flex-end}
    .amz-firma .lbl{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em}
    .amz-firma .name{font-weight:700;font-size:14px;text-transform:uppercase;text-align:right}

    /* Marca de BORRADOR (documento no-final): va en el <thead> → se repite por hoja. */
    .amz-draft{margin-top:10px;border:2px dashed #b91c1c;color:#b91c1c;background:#fdecec;
        padding:6px 12px;text-align:center;font-weight:700;font-size:12px;border-radius:4px;
        text-transform:uppercase;letter-spacing:.02em;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .amz-draft .n{display:block;font-weight:400;text-transform:none;letter-spacing:0;font-size:10.5px;color:#7f1d1d;margin-top:2px}

    /* Helpers (nombres propios, sin colisión con Bootstrap) */
    .amz .c{text-align:center}
    .amz .b7{font-weight:700}
    .amz .b6{font-weight:600}
    .amz .mut{color:#6b7280}
    .amz .it{font-style:italic}
    .amz .norm{display:block;font-size:9px;color:#6b7280;margin-top:2px}

    @media print{
        @page{size:letter portrait;margin:12mm}
        body{margin:0;background:#fff !important;-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important}
        .no-print,.navbar,.sidebar,#sidebar,.amz-toolbar{display:none !important}
        .amz-page{box-shadow:none !important;margin:0 !important;max-width:100% !important;padding:0 !important}
        /* Repetición del encabezado por hoja (motor nativo de <thead> en tablas). */
        .amz-wrap>thead{display:table-header-group}
        .amz-break{page-break-inside:avoid;break-inside:avoid}
    }
</style>

@php
    $L = function ($k) use ($lang) { return __('scouting.' . $k, [], $lang); };

    $ratingChip = function ($r) {
        $map = [
            'L' => 'background:#C0DD97;color:#173404;',
            'M' => 'background:#FAC775;color:#412402;',
            'H' => 'background:#F0997B;color:#4A1B0C;',
            'E' => 'background:#E24B4A;color:#ffffff;',
        ];
        return $map[$r] ?? 'background:#eee;color:#666;';
    };

    // Normaliza filas (tolerando el esquema viejo) — igual que show.
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
        ];
    }

    // FIDELIDAD (owner): la compañía SOLO muestra lo que se capturó. Sin fallback a nombre de marca.
    $company = trim((string) ($branding['company_name'] ?? ''));
    $office  = trim((string) ($branding['office_address'] ?? ''));
    $actions = ['E' => $L('act_E'), 'H' => $L('act_H'), 'M' => $L('act_M'), 'L' => $L('act_L')];
    $ratWord = ['E' => $L('r_E'), 'H' => $L('r_H'), 'M' => $L('r_M'), 'L' => $L('r_L')];

    // Desglose SB-132 (imprime QUÉ actividad especial se declaró, no solo que la hay).
    $sb = (isset($report->sb132_details) && is_array($report->sb132_details)) ? $report->sb132_details : [];
    $sbActLabels = [
        'armas' => $L('act_armas'), 'pirotecnia' => $L('act_pirotecnia'), 'stunts' => $L('act_stunts'),
        'aereo' => $L('act_aereo'), 'agua' => $L('act_agua'), 'off-road' => $L('act_offroad'),
        'fuego' => $L('act_fuego'), 'altura' => $L('act_altura'),
    ];
    $sbActs = [];
    foreach ((is_array($sb['activity_type'] ?? null) ? $sb['activity_type'] : []) as $a) {
        $sbActs[] = $sbActLabels[$a] ?? ucfirst(str_replace(['-', '_'], ' ', (string) $a));
    }
    $sbScene = trim((string) ($sb['scene_number'] ?? ''));
    $sbPersRaw = $sb['certified_personnel_required'] ?? '';
    $sbPers = is_bool($sbPersRaw) ? ($sbPersRaw ? '✓' : '') : trim((string) $sbPersRaw);
    $sbHasDetail = count($sbActs) || $sbScene !== '' || $sbPers !== '';

    $isDraft = ($report->status !== 'final');
@endphp

{{-- ===== Barra de acciones (no se imprime) ===== --}}
<div class="amz-toolbar no-print">
    <a href="{{ route('scoutings.show', $report->id) }}" class="amz-btn">&larr; {{ $L('btn_back') }}</a>
    <div style="display:flex;align-items:center;gap:8px">
        <div class="amz-lang">
            <a href="{{ route('scoutings.amazon', [$report->id, 'lang' => 'es']) }}" class="{{ $lang === 'es' ? 'on' : 'off' }}">ES</a>
            <a href="{{ route('scoutings.amazon', [$report->id, 'lang' => 'en']) }}" class="{{ $lang === 'en' ? 'on' : 'off' }}">EN</a>
        </div>
        <button onclick="window.print();" class="amz-btn amz-btn-dark">{{ $L('btn_print') }}</button>
    </div>
</div>

<div class="amz amz-page">
<table class="amz-wrap">

{{-- ===== ENCABEZADO (thead → se repite por hoja al imprimir) ===== --}}
<thead><tr><td>
    <div class="amz-hdr">
        <div class="amz-hdr-fields">
            <div class="amz-field"><b>{{ $L('f_production_title') }}:</b> {{ $report->production_name ?: $L('none') }}</div>
            <div class="amz-field"><b>{{ $L('f_company') }}:</b> {{ $company !== '' ? $company : $L('none') }}</div>
            <div class="amz-field"><b>{{ $L('f_office') }}:</b> {{ $office !== '' ? $office : $L('none') }}</div>
            <div class="amz-field"><b>{{ $L('f_manager') }}:</b> {{ $report->manager_name ?: $L('none') }}</div>
            <div class="amz-field"><b>{{ $L('f_safety_rep') }}:</b> {{ $report->safety_rep_name ?: $L('none') }}</div>
            <div class="amz-field"><b>{{ $L('f_type') }}:</b> {{ $report->production_type ?: $L('none') }}</div>
        </div>
        <div class="amz-hdr-logo">
            @include('componentes._doc-hero-logo', ['logoWidth' => 190])
            <p class="sub">{{ $L('hdr_sub') }}</p>
        </div>
    </div>
    @if($isDraft)
    <div class="amz-draft">{{ $L('draft') }}<span class="n">{{ $L('draft_note') }}</span></div>
    @endif
</td></tr></thead>

<tbody><tr><td>

    {{-- Locación / fechas --}}
    <table class="amz-tbl">
        <tr>
            <th style="width:50%">{{ $L('f_location') }}</th>
            <th style="width:25%">{{ $L('f_report_date') }}</th>
            <th style="width:25%">{{ $L('f_shoot_day') }}</th>
        </tr>
        <tr>
            {{-- FIDELIDAD: la celda es "Dirección de la locación". Si no hay dirección, sale '—';
                 NO se sustituye por location_name (un nombre bajo etiqueta de domicilio = dato
                 mal rotulado, la misma fabricación que se quitó en la compañía). --}}
            <td>{{ $report->location_address ?: $L('none') }}</td>
            <td>{{ optional($report->make_date)->format('d/m/Y') ?: $L('none') }}</td>
            <td>{{ optional($report->date_shoot)->format('d/m/Y') ?: $L('none') }}</td>
        </tr>
    </table>

    @if($report->requires_specific_ra)
        <div class="amz-break amz-banner-red">{{ $L('sb132') }}</div>

        {{-- Desglose SB-132 (imprime QUÉ actividad especial se declaró) --}}
        @if($sbHasDetail)
        <div class="amz-sec amz-break">{{ $L('s_sb132') }}</div>
        <table class="amz-tbl amz-break">
            <tr><th style="width:34%">{{ $L('sb132_activities') }}</th><td>{{ count($sbActs) ? implode(', ', $sbActs) : $L('none') }}</td></tr>
            <tr><th>{{ $L('sb132_scene') }}</th><td>{{ $sbScene !== '' ? $sbScene : $L('none') }}</td></tr>
            <tr><th>{{ $L('sb132_personnel') }}</th><td>{{ $sbPers !== '' ? $sbPers : $L('none') }}</td></tr>
        </table>
        @endif
    @endif

    {{-- ===== RISK ASSESSMENT: actividad + escenas ===== --}}
    <div class="amz-sec">{{ $L('s_risk_assessment') }}</div>
    <table class="amz-tbl">
        <tr><th style="width:50%">{{ $L('s_activity') }}</th><th style="width:50%">{{ $L('s_scenes') }}</th></tr>
        <tr>
            <td>{{ $report->exec_summary ?: $L('none') }}</td>
            <td>{{ $report->scene ?: $L('none') }}</td>
        </tr>
    </table>

    {{-- ===== TABLA DE PELIGROS ===== --}}
    <table class="amz-tbl">
        <thead>
            <tr>
                <th style="width:24%">{{ $L('t_hazard') }}</th>
                <th style="width:5%" class="c">{{ $L('t_l') }}</th>
                <th style="width:5%" class="c">{{ $L('t_c') }}</th>
                <th style="width:11%" class="c">{{ $L('t_rating') }}</th>
                <th style="width:24%">{{ $L('t_control') }}</th>
                <th style="width:9%" class="c">{{ $L('t_new') }}</th>
                <th style="width:12%">{{ $L('t_personnel') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr class="amz-break">
                    <td>
                        <span class="b6">{{ $row['hazard'] ?: $L('none') }}</span>
                        @if(!empty($row['badge']))
                            <span class="norm">{{ $row['badge'] }} · {{ $row['code'] }}</span>
                        @endif
                    </td>
                    <td class="c b7">{{ $row['likelihood'] ?: $L('none') }}</td>
                    <td class="c b7">{{ $row['consequence'] ?: $L('none') }}</td>
                    <td class="c">
                        @if($row['rating'])
                            <span class="amz-chip" style="{{ $ratingChip($row['rating']) }}">{{ $row['rating'] }}</span>
                        @else {{ $L('none') }} @endif
                    </td>
                    <td>{{ $row['control'] ?: $L('none') }}</td>
                    <td class="c">
                        @if($row['residual'])
                            <span class="amz-chip" style="{{ $ratingChip($row['residual']) }}">{{ $row['residual'] }}</span>
                        @else {{ $L('none') }} @endif
                    </td>
                    <td>{{ $row['personnel'] ?: $L('none') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="c mut it">{{ $L('t_none') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ===== OVERVIEW ===== --}}
    <div class="amz-sec">{{ $L('s_overview') }}</div>
    <div class="amz-overview">{{ $report->operational_notes ?: $L('overview_none') }}</div>

    {{-- ===== MATRIZ DE RIESGO ===== --}}
    <div class="amz-sec amz-break">{{ $L('s_matrix') }}</div>
    <div class="amz-break" style="margin-bottom:16px">
        @include('componentes._risk-matrix', ['lang' => $lang, 'interactive' => false, 'legend' => false])
    </div>

    {{-- ===== CLASIFICACIÓN → ACCIÓN REQUERIDA ===== --}}
    <div class="amz-sec amz-break">{{ $L('s_rating_action') }}</div>
    <table class="amz-tbl amz-break">
        @foreach(['E', 'H', 'M', 'L'] as $r)
            <tr>
                <td style="width:15%;white-space:nowrap"><span class="amz-chip" style="{{ $ratingChip($r) }}">{{ $ratWord[$r] }}</span></td>
                <td>{{ $actions[$r] }}</td>
            </tr>
        @endforeach
    </table>

    {{-- ===== JERARQUÍA DE CONTROL ===== --}}
    <div class="amz-sec amz-break">{{ $L('s_hierarchy') }}</div>
    <ol class="amz-list dec amz-break">
        @foreach(['hier_1', 'hier_2', 'hier_3', 'hier_4', 'hier_5', 'hier_6'] as $h)
            <li>{{ $L($h) }}</li>
        @endforeach
    </ol>

    {{-- ===== DEFINICIONES ===== --}}
    <div class="amz-sec amz-break">{{ $L('s_definitions') }}</div>
    <ul class="amz-list pln amz-break">
        @foreach(['def_hazard', 'def_risk', 'def_consequence', 'def_likelihood', 'def_rating'] as $d)
            <li>{{ $L($d) }}</li>
        @endforeach
    </ul>

    {{-- ===== FIRMA (sin sello ni hash: el documento del estudio sale limpio) ===== --}}
    <div class="amz-firma amz-break">
        <div class="lbl">{{ $L('compiled_by') }}</div>
        <div class="name">{{ $report->make_by ?: $L('none') }}</div>
    </div>

</td></tr></tbody>
</table>
</div>
@endsection
