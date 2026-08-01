{{-- ============================================================================================
     PÓSTER MEDEVAC — "Protocolo general de activación de emergencias" (delta #46).
     Calcado al formato ENEG: Carta vertical, encabezado compacto, textos de las fases del
     original, contactos en 3 columnas + un SELLO SHA compacto al pie. UNA hoja, offline,
     window.print(). Se lee SÓLO del payload congelado; un dato vacío NO aparece.
============================================================================================ --}}
@php
    use App\Support\SealVerifier;
    use App\Support\Branding;

    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = $brand['brand_name'] ?? 'CrewCare';
    $primary   = $brand['primary_color'] ?? '#c62828';
    $ink       = $brand['secondary_color'] ?? '#12233b';
    $en        = app()->getLocale() === 'en';

    $p         = $poster;
    $bd        = $p->pdata('brand', []);
    $loc       = $p->pdata('location', []);
    $hosp      = $p->pdata('hospital', []);
    $contacts  = $p->pdata('contacts', []);
    $gps       = $p->pdata('gps', null);

    // MARCA = presentación EN VIVO, NO sellada (decisión del owner 2026-08-01). El nombre de
    // proyecto puede cambiar por confidencialidad (p.ej. HTLR → TRL) y debe reflejarse en TODO
    // documento, incluso ya emitido — como en los reportes ($heroProject = $brandName). El sello
    // SHA protege el CONTENIDO (locación/hospital/contactos/mapa/distancia), no el membrete.
    // (El payload aún congela `brand` como archivo, pero la vista lo ignora y lee en vivo.)
    $logo      = Branding::documentLogo();                                       // logo del cliente EN VIVO
    $company   = trim((string) (Branding::get('company_name', '') ?? '')) ?: $brandName;
    $project   = $brandName;                                                     // = nombre de proyecto de los reportes (brand_name)

    $locName   = trim((string) ($loc['name'] ?? ''));
    $locAddr   = trim((string) ($loc['address'] ?? ''));
    $distance  = trim((string) $p->pdata('distance_km', ''));
    $eta       = trim((string) $p->pdata('eta', ''));

    $hospName  = trim((string) ($hosp['name'] ?? ''));
    $hospAddr  = trim((string) ($hosp['address'] ?? ''));
    $hospMaps  = trim((string) ($hosp['maps_url'] ?? ''));   // RUTA locación → hospital (Google Maps dir)

    $assembly  = trim((string) $p->pdata('assembly_point', ''));
    $access    = trim((string) $p->pdata('emergency_access', ''));
    $emPhone   = trim((string) $p->pdata('emergency_phone', ''));
    $ambulance = trim((string) $p->pdata('ambulance_company', ''));

    $dateStr   = $p->issued_at ? \Carbon\Carbon::parse($p->issued_at)->format('d/m/Y') : '';
    $mapImg    = trim((string) $p->pdata('map_image', ''));   // data-URI congelado (o vacío)

    // FASES ANTE EMERGENCIAS — texto FIJO tomado del MEDEVAC ENEG (no cambia por locación).
    // [num, título, intro|null, viñetas[]]
    $fases = [
        ['01', 'DETECCIÓN', null, [
            'Cualquier persona que presencie o sufra un incidente deberá reportarlo inmediatamente al personal médico, Health & Safety o Producción.',
        ]],
        ['02', 'ALERTAMIENTO', 'Comunicar vía radio o teléfono:', [
            'Nombre de quien reporta.', 'Tipo de emergencia.', 'Ubicación exacta.',
            'Estado del paciente.', 'Riesgos adicionales presentes.',
        ]],
        ['03', 'RESPUESTA INICIAL', null, [
            'Médico en set acude al incidente.', 'Se moviliza equipo de primeros auxilios.',
            'Health & Safety asegura el área.', 'Producción suspende las actividades si es necesario.',
        ]],
        ['04', 'VALORACIÓN MÉDICA', null, [
            'El médico en set realiza una evaluación primaria del paciente, estabilización y determina si requiere traslado hospitalario.',
        ]],
        ['05', 'TRASLADO MÉDICO', null, [
            'Activar ambulancia o transporte designado.', 'Personal médico o H&S acompaña al paciente.',
            'Coordinación con hospital receptor.',
        ]],
        ['06', 'CONTROL Y CIERRE', null, [
            'Elaboración de reporte.', 'Seguimiento médico.', 'Liberación del área.',
        ]],
    ];

    // Sello SHA (compacto).
    $sig       = $p->signatures()->latest('id')->first();
    $verdict   = $p->verifyLatestSignature();   // true / false / null
    $verifyUrl = SealVerifier::urlFor($p);
    $qr        = $verifyUrl ? SealVerifier::qrSvg($verifyUrl, 96) : null;
    $identicon = $sig ? SealVerifier::identiconSvg($sig->document_hash, 44) : null;
    $sealedAt  = ($sig && $sig->signed_at) ? \Carbon\Carbon::parse($sig->signed_at)->format('d/m/Y H:i') : null;

    // Pie de MARCA (calca del footer de los reportes): elaborado por · POWERED BY CrewCare + UUID.
    // El UUID conserva la marca del cliente ($brandName) y la versión del documento, EXACTAMENTE con
    // la misma fórmula que DSR/scout/injury ({marca}-{TIPO}-{16210+id}-{ddmmaaaa} | VER x.x).
    $preparedName = trim((string) $p->issued_by_name) ?: '—';
    $footDate     = $p->issued_at ? \Carbon\Carbon::parse($p->issued_at)->format('d M Y') : '';
    $preparedMeta = __('reports.label_risk_assessment') . ($footDate !== '' ? ' · ' . $footDate : '');
    $footUuid     = 'UUID: ' . $brandName . '-MDVC-' . (16210 + (int) $p->id) . '-'
                  . ($p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('dmY') : '')
                  . ' | ' . config('crewcare.doc_version');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brandName }} · MEDEVAC · {{ $locName ?: 'Locación' }}</title>
<link rel="stylesheet" href="/fonts/reports/report-fonts.css">
<style>
    /* Tipografía de marca CrewCare (la misma que el logotipo del footer y el wordmark). */
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSCLight-Regular.ttf') format('truetype'); font-weight:300; font-style:normal; font-display:swap; }
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSC-Regular.ttf')      format('truetype'); font-weight:400; font-style:normal; font-display:swap; }
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSCBlack-Regular.ttf')  format('truetype'); font-weight:900; font-style:normal; font-display:swap; }
    :root{
        --mdv-primary: {{ $primary }};
        --mdv-ink: {{ $ink }};
        --mdv-wordmark: #14181f;   /* negro del wordmark MEDEVAC */
        --mdv-teal: #0e6f6c;       /* etiquetas hospital/link (acento ENEG) */
        --mdv-title: #8a909b;      /* gris del título con reglas finas */
        --mdv-line: #d7dde5;
        --mdv-muted: #5b6472;
        --mdv-font: 'Poppins', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        --mdv-cond: 'Roboto Condensed', var(--mdv-font);
        --mdv-mono: 'Roboto Mono', ui-monospace, Consolas, monospace;
        --mdv-brand: 'Aspire SC', var(--mdv-cond);   /* tipografía de marca CrewCare */
    }
    *{ box-sizing:border-box; }
    html,body{ margin:0; padding:0; }
    body{ background:#e9edf1; color:#1a1f2b; font-family:var(--mdv-font); font-size:11px; line-height:1.4;
        -webkit-print-color-adjust:exact; print-color-adjust:exact; }

    .mdv-toolbar{ position:sticky; top:0; z-index:5; display:flex; gap:10px; align-items:center;
        padding:10px 14px; background:#0e1726; }
    .mdv-toolbar a, .mdv-toolbar button{ font:inherit; font-size:12px; font-weight:600; cursor:pointer;
        border:1px solid rgba(255,255,255,.25); background:transparent; color:#e8edf4; border-radius:8px;
        padding:7px 12px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .mdv-toolbar button.primary{ background:var(--mdv-primary); border-color:var(--mdv-primary); color:#fff; }
    .mdv-toolbar .sp{ flex:1; }

    /* Hoja tamaño Carta */
    .mdv-sheet{ width:216mm; min-height:279mm; margin:14px auto; background:#fff; color:#1a1f2b;
        padding:13mm 13mm 10mm; box-shadow:0 10px 30px rgba(10,20,40,.18); }

    /* ENCABEZADO calcado del ENEG: wordmark MEDEVAC a la IZQUIERDA (Aspire SC, peso regular — NO
       bold); a la DERECHA, agrupados: logo(s) de la producción │ divisor │ bloque de proyecto. */
    .mdv-head{ display:flex; justify-content:space-between; align-items:center; gap:16px;
        padding-bottom:11px; }
    .mdv-head .hd-l .wordmark{ position:relative; display:inline-block; font-family:var(--mdv-brand);
        font-weight:400; font-size:35px; line-height:1; letter-spacing:.05em; color:var(--mdv-wordmark);
        text-transform:uppercase; }
    .mdv-head .hd-l .wordmark::after{ content:''; position:absolute; left:0; bottom:-4px; width:38%; height:3px;
        background:var(--mdv-wordmark); }
    /* Grupo derecho: logos │ divisor │ proyecto (pegados, con divisor entre logo y proyecto). */
    .mdv-head .hd-r{ display:flex; align-items:center; gap:15px; }
    .mdv-head .hd-r .logos{ text-align:center; }
    .mdv-head .hd-r .logos img{ max-height:46px; max-width:210px; display:block; }
    .mdv-head .hd-r .logos .co{ font-family:var(--mdv-cond); font-weight:700; font-size:18px; color:var(--mdv-ink);
        text-transform:uppercase; letter-spacing:.02em; }
    .mdv-head .hd-r .vdiv{ align-self:stretch; width:1px; min-height:44px; background:var(--mdv-line); }
    .mdv-head .hd-r .proj{ text-align:right; }
    .mdv-head .hd-r .proj .h-lbl{ font-size:8px; text-transform:uppercase; letter-spacing:.2em; font-weight:600; color:var(--mdv-muted); }
    .mdv-head .hd-r .proj .h-proj{ font-family:var(--mdv-brand); font-weight:400; text-transform:uppercase; letter-spacing:.02em;
        font-size:20px; line-height:1; color:var(--mdv-ink); margin-top:2px; }
    .mdv-head .hd-r .proj .h-rev{ font-size:8.5px; color:var(--mdv-primary); font-weight:700; margin-top:4px; letter-spacing:.06em; text-transform:uppercase; }
    .mdv-head .hd-r .proj .h-date{ font-size:8.5px; color:var(--mdv-muted); letter-spacing:.04em; margin-top:1px; }

    /* Título — gris, centrado, entre dos reglas finas (NO banda oscura). */
    .mdv-title{ font-family:var(--mdv-cond); font-weight:600; text-transform:uppercase; letter-spacing:.16em;
        color:var(--mdv-title); text-align:center; font-size:16px; padding:9px 4px; margin:2px 0 0;
        border-top:1px solid var(--mdv-line); border-bottom:1px solid var(--mdv-line); }

    /* Franja LOCACIÓN (izq) ····· DISTANCIA | TIEMPO (der) — línea limpia, sin caja. */
    .mdv-strip{ display:flex; justify-content:space-between; flex-wrap:wrap; gap:4px 22px; align-items:baseline;
        padding:9px 2px; margin:0; border-bottom:1px solid var(--mdv-line); }
    .mdv-strip .k{ font-weight:700; color:var(--mdv-ink); text-transform:uppercase; font-size:11px; letter-spacing:.03em; }
    .mdv-strip .v{ font-weight:700; font-size:13px; color:var(--mdv-wordmark); }
    .mdv-strip .big{ font-family:var(--mdv-cond); font-size:15px; }
    .mdv-strip .sep{ color:var(--mdv-muted); margin:0 4px; }

    /* Hospital / punto de reunión — filas limpias, etiquetas en teal (sin caja bordeada). */
    .mdv-cols2{ display:grid; grid-template-columns:1.4fr 1fr; gap:4px 26px; margin:10px 0 0; }
    .mdv-row{ display:flex; gap:10px; padding:2.5px 0; }
    .mdv-row .k{ flex:none; width:104px; font-weight:700; color:var(--mdv-teal); text-transform:uppercase; font-size:10px; letter-spacing:.03em; padding-top:1px; }
    .mdv-row .v{ font-size:11px; color:var(--mdv-wordmark); word-break:break-word; }
    .mdv-row a{ color:var(--mdv-teal); font-weight:600; word-break:break-all; }

    .mdv-map{ position:relative; margin-top:9px; border:1px solid var(--mdv-line); border-radius:3px; overflow:hidden; }
    .mdv-map img{ display:block; width:100%; max-height:46mm; object-fit:cover; }
    .mdv-map::after{ content:''; position:absolute; left:0; right:0; top:0; bottom:0; pointer-events:none;
        box-shadow: inset 0 11px 13px -7px rgba(10,18,30,.6), inset 0 -11px 13px -7px rgba(10,18,30,.6); }

    /* Encabezado de sección — negro bold, centrado, con subrayado corto (calca ENEG). */
    .mdv-sec-h{ font-family:var(--mdv-cond); font-weight:700; text-transform:uppercase; letter-spacing:.05em;
        color:var(--mdv-wordmark); text-align:center; font-size:19px; margin:15px 0 11px; padding:0; border:0; }
    .mdv-sec-h::after{ content:''; display:block; width:56px; height:3px; background:var(--mdv-wordmark); margin:5px auto 0; }

    /* FASES 3×2 */
    .mdv-fases{ display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
    .mdv-fase{ position:relative; overflow:hidden; border-radius:5px;
        padding:9px 11px 8px; min-height:106px; background:#fafbfc; }
    .mdv-fase .ph-num{ position:absolute; top:-6px; left:-1px; font-family:var(--mdv-cond);
        font-weight:900; font-size:56px; line-height:1; color:rgba(20,24,31,.07);
        z-index:0; pointer-events:none; }
    .mdv-fase .ph-in{ position:relative; z-index:1; }
    .mdv-fase .ph-title{ font-family:var(--mdv-cond); font-weight:700; text-transform:uppercase; letter-spacing:.02em;
        font-size:15px; line-height:1.05; color:var(--mdv-ink); margin-bottom:5px; }
    .mdv-fase .ph-intro{ font-size:10px; color:#1a1f2b; margin:0 0 3px; text-align:justify; }
    .mdv-fase .ph-list{ margin:0; padding-left:14px; }
    .mdv-fase .ph-list li{ font-size:10px; color:#26303f; margin-bottom:2px; text-align:justify; }
    .mdv-fase .ph-text{ font-size:10px; color:#26303f; margin:0; text-align:justify; }

    /* Contactos — 3 columnas limpias con divisores finos (calca ENEG), sin cajas pesadas. */
    .mdv-contacts{ display:grid; grid-template-columns:repeat(3,1fr); gap:0; align-items:stretch; }
    .mdv-contact{ padding:6px 8px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:54px; }
    .mdv-contact + .mdv-contact{ border-left:1px solid var(--mdv-line); }
    .mdv-contact .role{ font-weight:700; text-transform:uppercase; letter-spacing:.1em; font-size:9.5px; color:var(--mdv-muted); }
    .mdv-contact .cn{ font-weight:800; font-size:12.5px; color:var(--mdv-wordmark); margin-top:5px; text-transform:uppercase; letter-spacing:.02em; }
    .mdv-contact .cp{ font-family:var(--mdv-mono); font-size:11.5px; margin-top:3px; color:var(--mdv-ink); }
    .mdv-contact .cempty{ color:#9aa3b1; font-size:10px; margin-top:5px; }

    /* Sello SHA compacto */
    .mdv-seal{ display:flex; align-items:center; gap:12px; margin-top:11px; padding:9px 11px;
        border:1px solid var(--mdv-line); border-radius:3px; background:#f7f9fb; }
    .mdv-seal .qr{ flex:none; width:70px; height:70px; background:#fff; padding:3px; border:1px solid var(--mdv-line); border-radius:3px; }
    .mdv-seal .qr svg{ width:100%; height:100%; display:block; }
    .mdv-seal .idc{ flex:none; width:44px; height:44px; }
    .mdv-seal .sbody{ flex:1; min-width:0; }
    .mdv-seal .slbl{ font-weight:700; text-transform:uppercase; letter-spacing:.04em; font-size:9px; color:var(--mdv-ink); }
    .mdv-seal .smeta{ font-size:9px; color:var(--mdv-muted); }
    .mdv-seal .shash{ font-family:var(--mdv-mono); font-size:8.5px; color:#26303f; word-break:break-all; line-height:1.35; margin-top:2px; }
    .mdv-seal.bad{ border-left:5px solid #c0392b; }
    .mdv-seal .bad-tag{ color:#c0392b; font-weight:700; }

    /* Pie de MARCA — valores EXACTOS del footer establecido (_report-v2-foot, modo papel):
       gris tenue, monocromo, logotipo Crew·Care en Aspire SC (crew liviano / care normal, MISMO
       color; nada de rojo). Va DESPUÉS del sello SHA. */
    .mdv-foot{ display:flex; justify-content:space-between; align-items:flex-end; gap:20px;
        margin-top:11px; border-top:1px solid var(--mdv-line); padding-top:10px; }
    .mdv-foot .ft-r{ text-align:right; }
    .mdv-foot .ft-lbl{ font-size:6px; text-transform:uppercase; letter-spacing:.22em; font-weight:600;
        color:#aab1bb; margin-bottom:3px; }
    .mdv-foot .ft-name{ font-weight:600; text-transform:uppercase; font-size:10px; letter-spacing:.04em;
        color:#9aa3af; line-height:1.1; }
    .mdv-foot .ft-meta{ font-size:9px; color:#aab1bb; margin-top:2px; }
    .mdv-foot .cc{ font-family:var(--mdv-brand); text-transform:uppercase; letter-spacing:.08em;
        font-size:11.5px; line-height:1; color:#9aa3af; }
    .mdv-foot .cc .crew{ font-weight:300; }
    .mdv-foot .cc .care{ font-weight:400; color:inherit; }
    .mdv-foot .ft-uuid{ font-family:var(--mdv-mono); font-size:8px; color:#aab1bb; margin-top:4px; letter-spacing:.01em; }

    @media print{
        @page{ size: letter portrait; margin: 10mm; }
        html,body{ background:#fff; }
        .mdv-toolbar{ display:none !important; }
        .mdv-sheet{ width:auto; min-height:0; margin:0; padding:0; box-shadow:none; }
    }
</style>
</head>
<body>

<div class="mdv-toolbar">
    <a href="{{ route('scoutings.show', $p->scouting_report_id) }}">← Volver al scouting</a>
    <span class="sp"></span>
    <button type="button" class="primary" onclick="window.print()">Imprimir / PDF</button>
</div>

<div class="mdv-sheet">

    {{-- 1 · ENCABEZADO — MEDEVAC (izq) · [logo producción │ divisor │ Proyecto] agrupados (der) --}}
    <div class="mdv-head">
        <div class="hd-l"><span class="wordmark">MEDEVAC</span></div>
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
                <div class="h-rev">{{ $en ? 'Revision' : 'Revisión' }} {{ $p->revision }}</div>
                @if($dateStr)<div class="h-date">{{ $dateStr }}</div>@endif
            </div>
        </div>
    </div>

    {{-- 2 · TÍTULO --}}
    <div class="mdv-title">Protocolo general de activación de emergencias</div>

    {{-- 3 · FRANJA LOCACIÓN (izq) ····· DISTANCIA | TIEMPO (der) --}}
    <div class="mdv-strip">
        <span><span class="k">Locación:</span> <span class="v big">{{ $locName ?: '—' }}</span></span>
        <span>
            @if($distance !== '')<span class="k">Distancia:</span> <span class="v">{{ $distance }} km</span>@endif
            @if($distance !== '' && $eta !== '')<span class="sep">|</span>@endif
            @if($eta !== '')<span class="k">Tiempo:</span> <span class="v">{{ $eta }}</span>@endif
        </span>
    </div>

    {{-- 4 · HOSPITAL + LINK · PUNTO DE REUNIÓN / ACCESO --}}
    <div class="mdv-cols2">
        <div class="mdv-info">
            @if($hospName !== '')<div class="mdv-row"><span class="k">Hospital</span><span class="v">{{ $hospName }}</span></div>@endif
            @if($hospAddr !== '')<div class="mdv-row"><span class="k">Dirección</span><span class="v">{{ $hospAddr }}</span></div>@endif
            @if($hospMaps !== '')<div class="mdv-row"><span class="k">Link Google</span><span class="v"><a href="{{ $hospMaps }}" target="_blank" rel="noopener">Ruta locación → hospital (Google Maps)</a></span></div>@endif
            @if($ambulance !== '')<div class="mdv-row"><span class="k">Ambulancia</span><span class="v">{{ $ambulance }}</span></div>@endif
            @if($emPhone !== '')<div class="mdv-row"><span class="k">Tel. emergencia</span><span class="v">{{ $emPhone }}</span></div>@endif
        </div>
        <div class="mdv-info">
            @if($assembly !== '')<div class="mdv-row"><span class="k">Punto de reunión</span><span class="v">{{ $assembly }}</span></div>@endif
            @if($access !== '')<div class="mdv-row"><span class="k">Acceso emergencia</span><span class="v">{{ $access }}</span></div>@endif
            @if($assembly === '' && $access === '')<div class="mdv-row"><span class="v" style="color:#9aa3b1">Sin punto de reunión ni acceso registrados en el scouting.</span></div>@endif
        </div>
    </div>

    {{-- 5 · FASES ANTE EMERGENCIAS (texto fijo del ENEG) --}}
    <div class="mdv-sec-h">Fases ante emergencias</div>
    <div class="mdv-fases">
        @foreach($fases as $f)
        <div class="mdv-fase">
            <span class="ph-num" aria-hidden="true">{{ $f[0] }}</span>
            <div class="ph-in">
                <div class="ph-title">{{ $f[1] }}</div>
                @if($f[2])<p class="ph-intro">{{ $f[2] }}</p>@endif
                @if(count($f[3]) > 1 || $f[2])
                    <ul class="ph-list">@foreach($f[3] as $b)<li>{{ $b }}</li>@endforeach</ul>
                @else
                    <p class="ph-text">{{ $f[3][0] }}</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Mapa de la ruta (imagen que adjuntó el emisor, congelada en el sello) — ENTRE fases y
         contactos, como en el ENEG. Vacío = no aparece. --}}
    @if($mapImg !== '')
    <div class="mdv-map"><img src="{{ $mapImg }}" alt="Mapa de la ruta locación → hospital"></div>
    @endif

    {{-- 6 · CONTACTOS DE EMERGENCIA --}}
    <div class="mdv-sec-h">Contactos de emergencia</div>
    <div class="mdv-contacts">
        @foreach($contacts as $c)
            @php $cn = trim((string) ($c['name'] ?? '')); $cp = trim((string) ($c['phone'] ?? '')); @endphp
            <div class="mdv-contact">
                <div class="role">{{ $c['label'] }}</div>
                @if($cn !== '')
                    <div class="cn">{{ $cn }}</div>
                    @if($cp !== '')<div class="cp">{{ $cp }}</div>@endif
                @else
                    <div class="cempty">Por asignar en set</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- 7 · SELLO SHA (compacto) --}}
    <div class="mdv-seal {{ $verdict === false ? 'bad' : '' }}">
        @if($qr)<div class="qr">{!! $qr !!}</div>@endif
        <div class="sbody">
            <div class="slbl">Sello digital SHA-256
                @if($verdict === false)<span class="bad-tag">· documento alterado</span>
                @elseif($verdict === null)<span style="color:#9aa3b1">· sin sellar</span>@endif
            </div>
            <div class="smeta">Folio {{ $p->folio() }}@if($sealedAt) · sellado {{ $sealedAt }}@endif@if($p->uuid) · verifica escaneando el QR@endif</div>
            @if($sig)<div class="shash">{{ $sig->document_hash }}</div>@endif
        </div>
        @if($identicon)<div class="idc">{!! $identicon !!}</div>@endif
    </div>

    {{-- 8 · PIE DE MARCA (después del sello) — MISMO parcial compartido que los reportes v2. --}}
    <footer class="mdv-foot">
        @include('componentes._brand-foot-inner', ['ftName' => $preparedName, 'ftMeta' => $preparedMeta, 'ftUuid' => $footUuid])
    </footer>
</div>
</body>
</html>
