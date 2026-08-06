{{-- ============================================================================================
     PAE — PLAN DE ATENCIÓN A EMERGENCIAS (2026-08-06). Documento SELLADO, uno por llamado.
     Cuerpo PROPIO (organigrama una sola vez + un bloque por locación, company move), sobre el
     marco de la familia (banda, sello SHA con QR, pie con UUID/versión). Carta vertical, offline,
     window.print(). Se lee SÓLO del payload congelado; un dato vacío NO aparece.
============================================================================================ --}}
@php
    use App\Support\SealVerifier;
    use App\Support\Branding;

    $en        = app()->getLocale() === 'en';
    $p         = $plan;

    // MARCA = presentación EN VIVO (misma doctrina que MEDEVAC): el sello protege el CONTENIDO
    // (organigrama/riesgos/hospital/mapa), no el membrete. El nombre de proyecto puede cambiar por
    // confidencialidad y debe reflejarse aunque el documento ya esté emitido.
    $brandName = Branding::get('brand_name', 'CrewCare') ?: 'CrewCare';
    $primary   = Branding::get('primary_color', '#c62828') ?: '#c62828';
    $ink       = Branding::get('secondary_color', '#12233b') ?: '#12233b';
    $logo      = Branding::documentLogo();
    $company   = trim((string) (Branding::get('company_name', '') ?? '')) ?: $brandName;

    $hd        = (array) $p->pdata('header', []);
    $org       = (array) $p->pdata('org', []);
    $crew      = (array) ($org['crew'] ?? []);
    $services  = (array) ($org['services'] ?? []);
    $move      = (array) $p->pdata('company_move', []);
    $isMove    = ! empty($move['is_move']);
    $moveTime  = trim((string) ($move['move_time'] ?? ''));
    $locations = (array) $p->pdata('locations', []);

    $shootDay  = $hd['shoot_day'] ?? null;
    $planDate  = trim((string) ($hd['date'] ?? ''));
    $project   = trim((string) ($hd['project'] ?? '')) ?: $brandName;
    $unit      = trim((string) ($hd['unit'] ?? ''));
    $dateHuman = $planDate !== '' ? \Carbon\Carbon::parse($planDate)->format('d/m/Y') : '';

    // Nivel de riesgo → etiqueta + color (H/E/M/L; se lee corriendo). DERIVADO, no sellado.
    $ratingMeta = function ($r) {
        $r = strtoupper(trim((string) $r));
        switch ($r) {
            case 'E': return ['Extremo', '#7b1fa2'];
            case 'H': return ['Alto',    '#c0392b'];
            case 'M': return ['Medio',   '#c98a00'];
            case 'L': return ['Bajo',    '#2e7d32'];
            default:  return [$r !== '' ? $r : '', '#5b6472'];
        }
    };

    // Sello SHA (compacto).
    $sig       = $p->signatures()->latest('id')->first();
    $verdict   = $p->verifyLatestSignature();   // true / false / null
    $verifyUrl = SealVerifier::urlFor($p);
    $qr        = $verifyUrl ? SealVerifier::qrSvg($verifyUrl, 96) : null;
    $identicon = $sig ? SealVerifier::identiconSvg($sig->document_hash, 44) : null;
    $sealedAt  = ($sig && $sig->signed_at) ? \Carbon\Carbon::parse($sig->signed_at)->format('d/m/Y H:i') : null;

    // Pie de MARCA (misma fórmula que MEDEVAC/reportes v2).
    $preparedName = trim((string) $p->issued_by_name) ?: '—';
    $footDate     = $p->issued_at ? \Carbon\Carbon::parse($p->issued_at)->format('d M Y') : '';
    $preparedMeta = ($en ? 'Safety' : 'Safety') . ($footDate !== '' ? ' · ' . $footDate : '');
    $footUuid     = 'UUID: ' . $brandName . '-PAE-' . (16210 + (int) $p->id) . '-'
                  . ($p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('dmY') : '')
                  . ' | ' . config('crewcare.doc_version');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brandName }} · PAE · {{ $shootDay ? ('Día ' . $shootDay) : ($dateHuman ?: 'Plan') }}</title>
<link rel="stylesheet" href="/fonts/reports/report-fonts.css">
<style>
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSCLight-Regular.ttf') format('truetype'); font-weight:300; font-style:normal; font-display:swap; }
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSC-Regular.ttf')      format('truetype'); font-weight:400; font-style:normal; font-display:swap; }
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSCBlack-Regular.ttf')  format('truetype'); font-weight:900; font-style:normal; font-display:swap; }
    :root{
        --pae-primary: {{ $primary }};
        --pae-ink: {{ $ink }};
        --pae-wordmark: #14181f;
        --pae-accent: #1d4e6f;      /* azul institucional del PAE (lo distingue del rojo MEDEVAC) */
        --pae-title: #8a909b;
        --pae-line: #d7dde5;
        --pae-muted: #5b6472;
        --pae-soft: #f4f7fa;
        --pae-font: 'Poppins', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        --pae-cond: 'Roboto Condensed', var(--pae-font);
        --pae-mono: 'Roboto Mono', ui-monospace, Consolas, monospace;
        --pae-brand: 'Aspire SC', var(--pae-cond);
    }
    *{ box-sizing:border-box; }
    html,body{ margin:0; padding:0; }
    body{ background:#e9edf1; color:#1a1f2b; font-family:var(--pae-font); font-size:11px; line-height:1.4;
        -webkit-print-color-adjust:exact; print-color-adjust:exact; }

    .pae-toolbar{ position:sticky; top:0; z-index:5; display:flex; gap:10px; align-items:center;
        padding:10px 14px; background:#0e1726; }
    .pae-toolbar a, .pae-toolbar button{ font:inherit; font-size:12px; font-weight:600; cursor:pointer;
        border:1px solid rgba(255,255,255,.25); background:transparent; color:#e8edf4; border-radius:8px;
        padding:7px 12px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .pae-toolbar button.primary{ background:var(--pae-primary); border-color:var(--pae-primary); color:#fff; }
    .pae-toolbar .sp{ flex:1; }

    .pae-sheet{ width:216mm; min-height:279mm; margin:14px auto; background:#fff; color:#1a1f2b;
        padding:13mm 13mm 10mm; box-shadow:0 10px 30px rgba(10,20,40,.18); }

    /* ENCABEZADO: wordmark PAE (izq) · [logo │ divisor │ proyecto/unidad/día] (der). */
    .pae-head{ display:flex; justify-content:space-between; align-items:center; gap:16px; padding-bottom:11px; }
    .pae-head .hd-l .wordmark{ position:relative; display:inline-block; font-family:var(--pae-brand);
        font-weight:400; font-size:35px; line-height:1; letter-spacing:.05em; color:var(--pae-wordmark);
        text-transform:uppercase; }
    .pae-head .hd-l .wordmark::after{ content:''; position:absolute; left:0; bottom:-4px; width:44%; height:3px; background:var(--pae-accent); }
    .pae-head .hd-l .wm-sub{ font-family:var(--pae-cond); font-weight:600; text-transform:uppercase; letter-spacing:.12em;
        font-size:9px; color:var(--pae-muted); margin-top:8px; }
    .pae-head .hd-r{ display:flex; align-items:center; gap:15px; }
    .pae-head .hd-r .logos{ text-align:center; }
    .pae-head .hd-r .logos img{ max-height:46px; max-width:210px; display:block; }
    .pae-head .hd-r .logos .co{ font-family:var(--pae-cond); font-weight:700; font-size:18px; color:var(--pae-ink); text-transform:uppercase; letter-spacing:.02em; }
    .pae-head .hd-r .vdiv{ align-self:stretch; width:1px; min-height:44px; background:var(--pae-line); }
    .pae-head .hd-r .proj{ text-align:right; }
    .pae-head .hd-r .proj .h-lbl{ font-size:8px; text-transform:uppercase; letter-spacing:.2em; font-weight:600; color:var(--pae-muted); }
    .pae-head .hd-r .proj .h-proj{ font-family:var(--pae-brand); font-weight:400; text-transform:uppercase; letter-spacing:.02em; font-size:20px; line-height:1; color:var(--pae-ink); margin-top:2px; }
    .pae-head .hd-r .proj .h-day{ font-size:8.5px; color:var(--pae-accent); font-weight:700; margin-top:4px; letter-spacing:.06em; text-transform:uppercase; }
    .pae-head .hd-r .proj .h-date{ font-size:8.5px; color:var(--pae-muted); letter-spacing:.04em; margin-top:1px; }

    .pae-title{ font-family:var(--pae-cond); font-weight:600; text-transform:uppercase; letter-spacing:.16em;
        color:var(--pae-title); text-align:center; font-size:16px; padding:9px 4px; margin:2px 0 0;
        border-top:1px solid var(--pae-line); border-bottom:1px solid var(--pae-line); }

    /* Aviso de COMPANY MOVE — franja bien visible (no leer el hospital equivocado a media jornada). */
    .pae-move{ display:flex; flex-wrap:wrap; gap:6px 16px; align-items:baseline; margin:10px 0 0;
        padding:8px 11px; border-left:4px solid var(--pae-accent); background:var(--pae-soft); border-radius:3px; }
    .pae-move .mv-tag{ font-family:var(--pae-cond); font-weight:700; text-transform:uppercase; letter-spacing:.06em; font-size:11px; color:var(--pae-accent); }
    .pae-move .mv-k{ font-weight:700; text-transform:uppercase; font-size:9px; letter-spacing:.04em; color:var(--pae-muted); }
    .pae-move .mv-v{ font-weight:700; font-size:12px; color:var(--pae-wordmark); }

    /* Encabezado de sección — negro bold, centrado, subrayado corto. */
    .pae-sec-h{ font-family:var(--pae-cond); font-weight:700; text-transform:uppercase; letter-spacing:.05em;
        color:var(--pae-wordmark); text-align:center; font-size:18px; margin:16px 0 11px; padding:0; border:0; }
    .pae-sec-h::after{ content:''; display:block; width:52px; height:3px; background:var(--pae-accent); margin:5px auto 0; }

    /* ORGANIGRAMA — 5 tarjetas (4 crew + 911). */
    .pae-org{ display:grid; grid-template-columns:repeat(5,1fr); gap:8px; }
    .pae-orgc{ border:1px solid var(--pae-line); border-radius:6px; padding:9px 9px 10px; text-align:center;
        display:flex; flex-direction:column; align-items:center; justify-content:flex-start; min-height:74px; background:#fff; }
    .pae-orgc.svc{ background:var(--pae-soft); border-color:#cdd8e2; }
    .pae-orgc .role{ font-family:var(--pae-cond); font-weight:700; text-transform:uppercase; letter-spacing:.06em; font-size:10px; color:var(--pae-accent); }
    .pae-orgc .cn{ font-weight:800; font-size:12px; color:var(--pae-wordmark); margin-top:5px; text-transform:uppercase; letter-spacing:.01em; line-height:1.15; }
    .pae-orgc .cp{ font-family:var(--pae-mono); font-size:12px; margin-top:4px; color:var(--pae-ink); }
    .pae-orgc .cempty{ color:#9aa3b1; font-size:9.5px; margin-top:6px; }
    .pae-orgc .svc-num{ font-family:var(--pae-cond); font-weight:900; font-size:22px; color:var(--pae-primary); margin-top:3px; }

    /* BLOQUE DE LOCACIÓN — pestaña numerada + filas. */
    .pae-loc{ margin-top:15px; border:1px solid var(--pae-line); border-radius:7px; overflow:hidden; }
    .pae-loc + .pae-loc{ margin-top:12px; }
    .pae-loc .loc-tab{ display:flex; align-items:center; gap:10px; padding:8px 12px; background:var(--pae-accent); color:#fff; }
    .pae-loc .loc-seq{ font-family:var(--pae-cond); font-weight:900; font-size:16px; background:rgba(255,255,255,.18);
        width:26px; height:26px; border-radius:5px; display:flex; align-items:center; justify-content:center; flex:none; }
    .pae-loc .loc-name{ font-family:var(--pae-cond); font-weight:700; text-transform:uppercase; letter-spacing:.03em; font-size:14px; }
    .pae-loc .loc-name small{ display:block; font-weight:400; text-transform:none; letter-spacing:0; font-size:9.5px; opacity:.85; }
    .pae-loc .loc-body{ padding:10px 12px 12px; }

    .pae-cols2{ display:grid; grid-template-columns:1.35fr 1fr; gap:4px 24px; }
    .pae-row{ display:flex; gap:10px; padding:2.5px 0; }
    .pae-row .k{ flex:none; width:110px; font-weight:700; color:var(--pae-accent); text-transform:uppercase; font-size:9.5px; letter-spacing:.03em; padding-top:1px; }
    .pae-row .v{ font-size:11px; color:var(--pae-wordmark); word-break:break-word; }
    .pae-row a{ color:var(--pae-accent); font-weight:600; word-break:break-all; }

    .pae-map{ position:relative; margin-top:9px; border:1px solid var(--pae-line); border-radius:3px; overflow:hidden; }
    .pae-map img{ display:block; width:100%; max-height:46mm; object-fit:cover; }
    .pae-map .cap{ font-size:8.5px; color:var(--pae-muted); padding:3px 6px; background:var(--pae-soft); }

    /* RIESGOS DEL DÍA — lista compacta con nivel y norma. */
    .pae-risks{ margin-top:11px; }
    .pae-risks .rk-h{ font-family:var(--pae-cond); font-weight:700; text-transform:uppercase; letter-spacing:.05em; font-size:11px; color:var(--pae-wordmark); margin:0 0 6px; }
    .pae-risk{ display:flex; align-items:flex-start; gap:9px; padding:5px 0; border-top:1px solid #eef2f6; }
    .pae-risk:first-of-type{ border-top:0; }
    .pae-risk .lvl{ flex:none; font-weight:800; font-size:8.5px; text-transform:uppercase; letter-spacing:.04em;
        color:#fff; border-radius:4px; padding:2px 7px; min-width:52px; text-align:center; }
    .pae-risk .rk-body{ flex:1; min-width:0; }
    .pae-risk .rk-name{ font-weight:700; font-size:11px; color:var(--pae-wordmark); }
    .pae-risk .rk-meta{ font-size:9.5px; color:var(--pae-muted); margin-top:1px; }
    .pae-risk .rk-meta a{ color:var(--pae-accent); font-weight:600; }
    .pae-risk .rk-cat{ display:inline-block; font-size:8.5px; font-weight:700; text-transform:uppercase; letter-spacing:.03em;
        color:var(--pae-accent); background:var(--pae-soft); border-radius:4px; padding:1px 6px; margin-right:6px; }
    .pae-norisk{ font-size:10px; color:#9aa3b1; padding:4px 0; }

    /* Referencia al mapa de riesgos (folio + QR) + vistas embebidas. */
    .pae-rmap{ display:flex; align-items:center; gap:12px; margin-top:11px; padding:8px 11px;
        border:1px dashed #b9c6d4; border-radius:6px; background:var(--pae-soft); }
    .pae-rmap .qr{ flex:none; width:58px; height:58px; background:#fff; padding:3px; border:1px solid var(--pae-line); border-radius:3px; }
    .pae-rmap .qr svg{ width:100%; height:100%; display:block; }
    .pae-rmap .rm-lbl{ font-family:var(--pae-cond); font-weight:700; text-transform:uppercase; letter-spacing:.04em; font-size:10px; color:var(--pae-accent); }
    .pae-rmap .rm-folio{ font-family:var(--pae-mono); font-weight:700; font-size:12px; color:var(--pae-wordmark); margin-top:2px; }
    .pae-rmap .rm-note{ font-size:9px; color:var(--pae-muted); margin-top:2px; }
    .pae-views{ margin-top:9px; display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .pae-view{ border:1px solid var(--pae-line); border-radius:4px; overflow:hidden; background:#fff; }
    .pae-view img{ display:block; width:100%; max-height:52mm; object-fit:cover; }
    .pae-view .cap{ font-size:8.5px; color:var(--pae-muted); padding:3px 6px; background:var(--pae-soft); }

    /* Sello SHA compacto */
    .pae-seal{ display:flex; align-items:center; gap:12px; margin-top:14px; padding:9px 11px;
        border:1px solid var(--pae-line); border-radius:3px; background:#f7f9fb; }
    .pae-seal .qr{ flex:none; width:70px; height:70px; background:#fff; padding:3px; border:1px solid var(--pae-line); border-radius:3px; }
    .pae-seal .qr svg{ width:100%; height:100%; display:block; }
    .pae-seal .idc{ flex:none; width:44px; height:44px; }
    .pae-seal .sbody{ flex:1; min-width:0; }
    .pae-seal .slbl{ font-weight:700; text-transform:uppercase; letter-spacing:.04em; font-size:9px; color:var(--pae-ink); }
    .pae-seal .smeta{ font-size:9px; color:var(--pae-muted); }
    .pae-seal .shash{ font-family:var(--pae-mono); font-size:8.5px; color:#26303f; word-break:break-all; line-height:1.35; margin-top:2px; }
    .pae-seal.bad{ border-left:5px solid #c0392b; }
    .pae-seal .bad-tag{ color:#c0392b; font-weight:700; }

    .pae-foot{ display:flex; justify-content:space-between; align-items:flex-end; gap:20px;
        margin-top:11px; border-top:1px solid var(--pae-line); padding-top:10px; }

    @media print{
        @page{ size: letter portrait; margin: 10mm; }
        html,body{ background:#fff; }
        .pae-toolbar{ display:none !important; }
        .pae-sheet{ width:auto; min-height:0; margin:0; padding:0; box-shadow:none; }
        .pae-loc{ break-inside:avoid; }
    }
</style>
</head>
<body>

<div class="pae-toolbar">
    <a href="{{ route('pae.index') }}">← {{ $en ? 'Emergency action plans' : 'Planes de emergencia' }}</a>
    <span class="sp"></span>
    <button type="button" class="primary" onclick="window.print()">{{ $en ? 'Print / PDF' : 'Imprimir / PDF' }}</button>
</div>

<div class="pae-sheet">

    {{-- 1 · ENCABEZADO --}}
    <div class="pae-head">
        <div class="hd-l">
            <span class="wordmark">PAE</span>
            <div class="wm-sub">{{ $en ? 'Emergency Action Plan' : 'Plan de Atención a Emergencias' }}</div>
        </div>
        <div class="hd-r">
            <div class="logos">
                @if($logo !== '')
                    <img src="{{ $logo }}" alt="{{ $company }}" onerror="this.style.display='none'">
                @else
                    <span class="co">{{ $company }}</span>
                @endif
            </div>
            <div class="vdiv"></div>
            <div class="proj">
                <div class="h-lbl">{{ $en ? 'Project' : 'Proyecto' }}</div>
                <div class="h-proj">{{ $project !== '' ? $project : '—' }}</div>
                @if($shootDay)<div class="h-day">{{ $en ? 'Shoot day' : 'Día de rodaje' }} {{ $shootDay }}</div>@endif
                @if($unit !== '')<div class="h-date">{{ $en ? 'Unit' : 'Unidad' }}: {{ $unit }}</div>@endif
                @if($dateHuman !== '')<div class="h-date">{{ $dateHuman }}</div>@endif
            </div>
        </div>
    </div>

    {{-- 2 · TÍTULO --}}
    <div class="pae-title">{{ $en ? 'Emergency Action Plan' : 'Plan de Atención a Emergencias' }}</div>

    {{-- Company move — aviso visible con el ORDEN y la HORA del movimiento --}}
    @if($isMove)
    <div class="pae-move">
        <span class="mv-tag">Company move</span>
        <span><span class="mv-k">{{ $en ? 'Locations' : 'Locaciones' }}:</span> <span class="mv-v">{{ count($locations) }}</span></span>
        @if($moveTime !== '')<span><span class="mv-k">{{ $en ? 'Estimated move' : 'Movimiento estimado' }}:</span> <span class="mv-v">{{ $moveTime }}</span></span>@endif
        @php
            $ord = [];
            foreach ($locations as $lx) { $nm = trim((string) ($lx['name'] ?? '')); if ($nm !== '') { $ord[] = $nm; } }
        @endphp
        @if(count($ord))<span><span class="mv-k">{{ $en ? 'Order' : 'Orden' }}:</span> <span class="mv-v">{{ implode(' → ', $ord) }}</span></span>@endif
    </div>
    @endif

    {{-- 3 · ORGANIGRAMA DE EMERGENCIA (una sola vez — mismo crew ese día) --}}
    <div class="pae-sec-h">{{ $en ? 'Emergency org chart' : 'Organigrama de emergencia' }}</div>
    <div class="pae-org">
        @foreach($crew as $c)
            @php $cn = trim((string) ($c['name'] ?? '')); $cp = trim((string) ($c['phone'] ?? '')); @endphp
            <div class="pae-orgc">
                <div class="role">{{ $c['label'] }}</div>
                @if($cn !== '')
                    <div class="cn">{{ $cn }}</div>
                    @if($cp !== '')<div class="cp">{{ $cp }}</div>@endif
                @else
                    <div class="cempty">{{ $en ? 'Assign on set' : 'Por asignar en set' }}</div>
                @endif
            </div>
        @endforeach
        @foreach($services as $svc)
            <div class="pae-orgc svc">
                <div class="role">{{ $svc['label'] ?? 'Emergencias' }}</div>
                <div class="svc-num">{{ $svc['phone'] ?? '911' }}</div>
            </div>
        @endforeach
    </div>

    {{-- 4 · UN BLOQUE POR LOCACIÓN (se repite) --}}
    @foreach($locations as $loc)
        @php
            $lname   = trim((string) ($loc['name'] ?? ''));
            $laddr   = trim((string) ($loc['address'] ?? ''));
            $seq     = (int) ($loc['seq'] ?? $loop->iteration);
            $hosp    = (array) ($loc['hospital'] ?? []);
            $hName   = trim((string) ($hosp['name'] ?? ''));
            $hAddr   = trim((string) ($hosp['address'] ?? ''));
            $hDist   = trim((string) ($hosp['distance_km'] ?? ''));
            $hEta    = trim((string) ($hosp['eta'] ?? ''));
            $hMaps   = trim((string) ($hosp['maps_url'] ?? ''));
            $assembly= trim((string) ($loc['assembly_point'] ?? ''));
            $access  = trim((string) ($loc['emergency_access'] ?? ''));
            $ambul   = trim((string) ($loc['ambulance_company'] ?? ''));
            $ephone  = trim((string) ($loc['emergency_phone'] ?? ''));
            $rmap    = trim((string) ($loc['route_map'] ?? ''));
            $risks   = (array) ($loc['risks'] ?? []);
            $rm      = $loc['riskmap'] ?? null;
        @endphp
        <div class="pae-loc">
            <div class="loc-tab">
                <span class="loc-seq">{{ $seq }}</span>
                <span class="loc-name">{{ $lname ?: ($en ? 'Location' : 'Locación') }}@if($laddr !== '')<small>{{ $laddr }}</small>@endif</span>
            </div>
            <div class="loc-body">

                {{-- Hospital + accesos --}}
                <div class="pae-cols2">
                    <div>
                        @if($hName !== '')<div class="pae-row"><span class="k">Hospital</span><span class="v">{{ $hName }}</span></div>@endif
                        @if($hAddr !== '')<div class="pae-row"><span class="k">{{ $en ? 'Address' : 'Dirección' }}</span><span class="v">{{ $hAddr }}</span></div>@endif
                        @if($hDist !== '' || $hEta !== '')
                        <div class="pae-row"><span class="k">{{ $en ? 'Distance / time' : 'Distancia / tiempo' }}</span><span class="v">{{ $hDist !== '' ? ($hDist . ' km') : '' }}@if($hDist !== '' && $hEta !== '') · @endif{{ $hEta }}</span></div>
                        @endif
                        @if($hMaps !== '')<div class="pae-row"><span class="k">Google Maps</span><span class="v"><a href="{{ $hMaps }}" target="_blank" rel="noopener">{{ $en ? 'Route to hospital' : 'Ruta al hospital' }}</a></span></div>@endif
                    </div>
                    <div>
                        @if($assembly !== '')<div class="pae-row"><span class="k">{{ $en ? 'Assembly' : 'Punto reunión' }}</span><span class="v">{{ $assembly }}</span></div>@endif
                        @if($access !== '')<div class="pae-row"><span class="k">{{ $en ? 'Emerg. access' : 'Acceso emerg.' }}</span><span class="v">{{ $access }}</span></div>@endif
                        @if($ambul !== '')<div class="pae-row"><span class="k">{{ $en ? 'Ambulance' : 'Ambulancia' }}</span><span class="v">{{ $ambul }}</span></div>@endif
                        @if($ephone !== '')<div class="pae-row"><span class="k">{{ $en ? 'Emerg. phone' : 'Tel. emerg.' }}</span><span class="v">{{ $ephone }}</span></div>@endif
                    </div>
                </div>

                @if($rmap !== '')
                <div class="pae-map">
                    <img src="{{ $rmap }}" alt="{{ $en ? 'Route to hospital' : 'Ruta al hospital' }}">
                    <div class="cap">{{ $en ? 'Route to the hospital' : 'Ruta a hospital' }}</div>
                </div>
                @endif

                {{-- RIESGOS DEL DÍA --}}
                <div class="pae-risks">
                    <div class="rk-h">{{ $en ? 'Risks of the day' : 'Riesgos del día' }}</div>
                    @forelse($risks as $rk)
                        @php
                            $rl = $ratingMeta($rk['rating'] ?? '');
                            $rkName = trim((string) ($rk['hazard'] ?? ''));
                            $rkCat  = trim((string) ($rk['category_label'] ?? ''));
                            $rkCode = trim((string) ($rk['code'] ?? ''));
                            $rkUrl  = trim((string) ($rk['url'] ?? ''));
                            $rkBadge= trim((string) ($rk['badge'] ?? ''));
                        @endphp
                        <div class="pae-risk">
                            <span class="lvl" style="background:{{ $rl[1] }}">{{ $rl[0] !== '' ? $rl[0] : '—' }}</span>
                            <div class="rk-body">
                                <div class="rk-name">{{ $rkName !== '' ? $rkName : ($rkCat !== '' ? $rkCat : '—') }}</div>
                                <div class="rk-meta">
                                    @if($rkCat !== '')<span class="rk-cat">{{ $rkCat }}</span>@endif
                                    @if($rkCode !== '')@if($rkUrl !== '')<a href="{{ $rkUrl }}" target="_blank" rel="noopener">{{ $rkBadge !== '' ? ($rkBadge . ' · ') : '' }}{{ $rkCode }}</a>@else {{ $rkBadge !== '' ? ($rkBadge . ' · ') : '' }}{{ $rkCode }}@endif @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="pae-norisk">{{ $en ? 'No hazards were assessed for this location in the scouting.' : 'El scouting de esta locación no registra peligros evaluados.' }}</div>
                    @endforelse
                </div>

                {{-- Referencia al mapa de riesgos sellado (+ vistas embebidas si se pidió) --}}
                @if(is_array($rm) && (trim((string) ($rm['folio'] ?? '')) !== '' || !empty($rm['views'])))
                    @php
                        $rmFolio = trim((string) ($rm['folio'] ?? ''));
                        $rmUrl   = trim((string) ($rm['verify_url'] ?? ''));
                        $rmViews = (array) ($rm['views'] ?? []);
                        $rmQr    = $rmUrl !== '' ? SealVerifier::qrSvg($rmUrl, 72) : null;
                    @endphp
                    <div class="pae-rmap">
                        @if($rmQr)<div class="qr">{!! $rmQr !!}</div>@endif
                        <div>
                            <div class="rm-lbl">{{ $en ? 'Risk & resource map' : 'Mapeo de riesgos y recursos' }}</div>
                            @if($rmFolio !== '')<div class="rm-folio">{{ $rmFolio }}</div>@endif
                            <div class="rm-note">{{ $en ? 'Sealed document — scan the QR to verify the full map.' : 'Documento sellado — escanea el QR para ver el mapa completo.' }}</div>
                        </div>
                    </div>
                    @if(count($rmViews))
                    <div class="pae-views">
                        @foreach($rmViews as $vw)
                            @php $vImg = trim((string) ($vw['image'] ?? '')); $vLbl = trim((string) ($vw['label'] ?? '')); @endphp
                            @if($vImg !== '')
                            <div class="pae-view">
                                <img src="{{ $vImg }}" alt="{{ $vLbl ?: 'Vista del mapa' }}">
                                @if($vLbl !== '')<div class="cap">{{ $vLbl }}</div>@endif
                            </div>
                            @endif
                        @endforeach
                    </div>
                    @endif
                @endif

            </div>
        </div>
    @endforeach

    {{-- 5 · SELLO SHA (compacto) --}}
    <div class="pae-seal {{ $verdict === false ? 'bad' : '' }}">
        @if($qr)<div class="qr">{!! $qr !!}</div>@endif
        <div class="sbody">
            <div class="slbl">{{ $en ? 'SHA-256 digital seal' : 'Sello digital SHA-256' }}
                @if($verdict === false)<span class="bad-tag">· {{ $en ? 'document altered' : 'documento alterado' }}</span>
                @elseif($verdict === null)<span style="color:#9aa3b1">· {{ $en ? 'unsealed' : 'sin sellar' }}</span>@endif
            </div>
            <div class="smeta">{{ $en ? 'Folio' : 'Folio' }} {{ $p->folio() }}@if($sealedAt) · {{ $en ? 'sealed' : 'sellado' }} {{ $sealedAt }}@endif @if($p->uuid)· {{ $en ? 'verify by scanning the QR' : 'verifica escaneando el QR' }}@endif</div>
            @if($sig)<div class="shash">{{ $sig->document_hash }}</div>@endif
        </div>
        @if($identicon)<div class="idc">{!! $identicon !!}</div>@endif
    </div>

    {{-- 6 · PIE DE MARCA --}}
    <footer class="pae-foot">
        @include('componentes._brand-foot-inner', ['ftName' => $preparedName, 'ftMeta' => $preparedMeta, 'ftUuid' => $footUuid])
    </footer>
</div>
</body>
</html>
