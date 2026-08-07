{{-- ============================================================================================
     PAE — PLAN DE ATENCIÓN A EMERGENCIAS. Documento SELLADO (uno por llamado). HERMANO del Daily
     Safety Report: MISMO chrome (_report-v2-head/-toolbar/-foot + _doc-hero + banda + .sec) y los
     MISMOS tokens; lo único propio del PAE es el título del hero y las secciones .pae-*.

     ESTRUCTURA (rediseño 2026-08-06, feedback del owner):
       HOJA DE ACTIVACIÓN — se repite POR LOCACIÓN (company move):
         · Franja de activación (911 · ambulancia · teléfono de emergencia, números display).
         · Locación y traslado médico (ETA display + km · hospital · reunión/acceso · mapa).
       RESTO — UNA sola vez por llamado (mismo crew):
         · Cadena de mando (TARJETAS: 3 grandes + 4 chicas).
         · Qué decir al reportar (guion de radio, 5 columnas fijas).
         · Fases ante una emergencia (3×2, 01–06; texto fijo del MEDEVAC).
         · Riesgos del día (TARJETAS con filo por nivel + badges de norma del MISMO _standards-chips).
         · Procedimientos rápidos (2 columnas; evacuación total desde datos reales de la locación).
         · Sello SHA.
       NO va acuse de recepción (100+ personas por locación: esa firma no ocurre; el sello se conserva).

     HONESTIDAD: se lee SÓLO del payload congelado; un dato vacío NO se imprime. El nivel/orden de
     los riesgos y las normas ya vienen resueltos y CONGELADOS por el builder — aquí sólo se pintan.
     El sello SHA se calcula sobre el DATO, no sobre este render: cambiar la vista NO altera el hash.
============================================================================================ --}}
@php
    use App\Support\SealVerifier;
    use App\Support\Branding;

    $en        = app()->getLocale() === 'en';
    $p         = $plan;

    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = ($brand['brand_name'] ?? Branding::get('brand_name', 'CrewCare')) ?: 'CrewCare';
    $primary   = $brand['primary_color'] ?? (Branding::get('primary_color', '#ff9900') ?: '#ff9900');

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
    $unit      = trim((string) ($hd['unit'] ?? ''));
    $mainImage = trim((string) ($hd['main_image'] ?? ''));
    $dateStr   = $planDate !== '' ? \Carbon\Carbon::parse($planDate)->format('d/m/Y') : optional($p->issued_at)->format('d/m/Y');

    // Nombre de proyecto EN VIVO = brand_name (misma convención que MEDEVAC y reportes: la marca
    // es presentación, no dato sellado). Ya NO se lee header.project (era production_name libre → "CrewCare").
    $project   = $brandName;

    // Qué celdas del encabezado imprimir (el emisor las eligió; default = todas).
    $show      = (array) ($hd['show'] ?? []);
    $showCell  = function ($k) use ($show) { return ! array_key_exists($k, $show) || ! empty($show[$k]); };

    // Crew indexado por slot para armar las tarjetas de la cadena de mando.
    $crewByKey = [];
    foreach ($crew as $c) { $crewByKey[(string) ($c['key'] ?? '')] = $c; }
    $slot = function ($key) use ($crewByKey) {
        return $crewByKey[$key] ?? ['label' => '', 'name' => '', 'phone' => '', 'radio' => ''];
    };
    $coord     = $slot('coordinador_emergencia');
    $coordName = trim((string) ($coord['name'] ?? ''));

    $preparedName = trim((string) $p->issued_by_name) ?: '—';

    $locNames = [];
    foreach ($locations as $lx) { $nm = trim((string) ($lx['name'] ?? '')); if ($nm !== '') { $locNames[] = $nm; } }
    $locLabel = $locNames ? implode(' · ', $locNames) : ($en ? 'Location' : 'Locación');

    $docVersion = config('crewcare.doc_version');

    // Nivel de riesgo → etiqueta + color + rango de orden (E>H>M>L). DERIVADO, no sellado.
    $ratingMeta = function ($r) {
        switch (strtoupper(trim((string) $r))) {
            case 'E': return ['Extremo', '#7b1fa2', 0];
            case 'H': return ['Alto',    '#c0392b', 1];
            case 'M': return ['Medio',   '#c98a00', 2];
            case 'L': return ['Bajo',    '#2e7d32', 3];
            default:  return [trim((string) $r) !== '' ? strtoupper(trim((string) $r)) : '—', '#5b6472', 4];
        }
    };

    // FASES ante una emergencia — texto FIJO tomado del MEDEVAC vigente (no cambia por locación).
    $fases = [
        ['01', 'Detección', ['Cualquier persona que presencie o sufra un incidente lo reporta de inmediato al personal médico, Health & Safety o Producción.']],
        ['02', 'Alertamiento', ['Comunicar por radio o teléfono: quién reporta, tipo de emergencia, ubicación exacta, estado del paciente y riesgos adicionales presentes.']],
        ['03', 'Respuesta inicial', ['El médico en set acude al incidente.', 'Se moviliza el equipo de primeros auxilios.', 'Health & Safety asegura el área.', 'Producción suspende actividades si es necesario.']],
        ['04', 'Valoración médica', ['El médico en set realiza la evaluación primaria, estabiliza y determina si se requiere traslado hospitalario.']],
        ['05', 'Traslado médico', ['Activar ambulancia o transporte designado.', 'Personal médico o H&S acompaña al paciente.', 'Coordinación con el hospital receptor.']],
        ['06', 'Control y cierre', ['Elaboración del reporte.', 'Seguimiento médico.', 'Liberación del área.']],
    ];

    // GUION DE RADIO — "Qué decir al reportar". Bloque FIJO (no depende de datos).
    $sayScript = [
        ['Quién habla',              'Tu nombre y puesto. «Aquí [nombre], soy [puesto].»'],
        ['Qué pasó',                 'El tipo de emergencia en una frase: caída, quemadura, incendio, choque, descarga.'],
        ['Dónde exactamente',        'Locación y punto preciso: set, piso, acceso o referencia visible.'],
        ['Cuántos y cómo',           'Número de afectados y su estado: consciente, no responde, sangra, atrapado.'],
        ['Qué riesgos siguen activos','Peligros aún presentes: fuego, energía, tránsito, altura, químicos.'],
    ];

    // PROCEDIMIENTOS RÁPIDOS — pasos numerados en línea. Los cinco primeros son fijos; la
    // EVACUACIÓN TOTAL se arma con los datos REALES de la(s) locación(es). Si ninguna trae
    // punto de reunión ni acceso de emergencia, ese procedimiento NO se imprime (nada inventado).
    $procs = [
        ['Emergencia médica', ['Avisa por radio al Set Medic', 'Asegura el área', 'Aplica primeros auxilios si estás capacitado', 'Traslada al hospital de referencia si es necesario']],
        ['Incendio', ['Activa la alarma / avisa por radio', 'Corta la energía del área si es seguro', 'Evacúa hacia el punto de reunión', 'Usa el extintor sólo si es seguro']],
        ['Clima severo', ['Suspende el rodaje', 'Asegura el equipo suelto', 'Guía a elenco y crew al refugio', 'Espera la indicación de Producción']],
        ['Persona extraviada / incidente SPFX', ['Reporta al Coordinador de Seguridad', 'Delimita el área si hay incidente con SPFX', 'Inicia el conteo del crew presente']],
        ['Amenaza externa / intruso', ['No confrontes', 'Avisa a seguridad y Producción por radio', 'Resguarda a elenco y crew en zona segura', 'Llama a la policía si es necesario']],
    ];
    $evacSteps = [];
    foreach ($locations as $lx) {
        $ln = trim((string) ($lx['name'] ?? ''));
        $ap = trim((string) ($lx['assembly_point'] ?? ''));
        $ea = trim((string) ($lx['emergency_access'] ?? ''));
        if ($ap === '' && $ea === '') { continue; }
        $parts = [];
        if ($ea !== '') { $parts[] = 'sal por ' . $ea; }
        if ($ap !== '') { $parts[] = 'reúne en ' . $ap; }
        $evacSteps[] = ($ln !== '' ? $ln . ': ' : '') . implode(', ', $parts);
    }
    if (count($evacSteps)) {
        $procs[] = ['Evacuación total', $evacSteps];
    }

    // Sello.
    $sig       = $p->signatures()->latest('id')->first();
    $verdict   = $p->verifyLatestSignature();   // true / false / null
    $verifyUrl = SealVerifier::urlFor($p);
    $qr        = $verifyUrl ? SealVerifier::qrSvg($verifyUrl, 108) : null;
    $identicon = $sig ? SealVerifier::identiconSvg($sig->document_hash, 48) : null;
    $sealedAt  = ($sig && $sig->signed_at) ? \Carbon\Carbon::parse($sig->signed_at)->format('d/m/Y H:i') : null;

    // Hero + pie (misma fórmula que MEDEVAC / reportes v2). heroTime = NULL a propósito: el PAE es
    // de todo el día, no de una hora de llamado; el día de rodaje vive en el encabezado, no en el hero.
    $heroModule = $en ? 'Emergency Action Plan' : 'Plan de Atención a Emergencias';
    $footMeta   = 'Safety' . ($dateStr ? ' · ' . $dateStr : '');
    $footUuid   = 'UUID: ' . $brandName . '-PAE-' . (16210 + (int) $p->id) . '-'
                . ($p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('dmY') : '')
                . ' | ' . $docVersion;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brandName }} · {{ $heroModule }} · {{ $shootDay ? ('Día ' . $shootDay) : $dateStr }}</title>
@include('componentes._report-v2-head')
<style>
    /* Contenido propio del PAE (scoped .pae-*). Hereda TODOS los tokens del chrome (claro/oscuro/print). */

    /* Encabezado tabular del llamado (celdas que el emisor eligió imprimir). */
    .pae-meta{ display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin:0 0 14px; }
    .pae-meta .cell{ border:1px solid var(--stroke); border-radius:10px; padding:8px 11px; background:var(--panel); }
    .pae-meta .lbl{ font-size:8.5px; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); font-weight:800; }
    .pae-meta .val{ font-size:12.5px; font-weight:700; color:var(--text); margin-top:3px; word-break:break-word; }

    .pae-move{ display:flex; flex-wrap:wrap; gap:5px 16px; align-items:baseline; margin:0 0 14px;
        padding:9px 12px; border-left:3px solid var(--brand); background:var(--panel); border-radius:var(--radius-sm); }
    .pae-move .tag{ font-weight:800; text-transform:uppercase; letter-spacing:.06em; font-size:10px; color:var(--brand); }
    .pae-move .k{ font-weight:700; text-transform:uppercase; font-size:9px; letter-spacing:.03em; color:var(--faint); }
    .pae-move .v{ font-weight:700; font-size:12px; color:var(--text); }

    /* ===== HOJA DE ACTIVACIÓN (por locación) — unidad que NO se parte ===== */
    .pae-act{ border:1px solid var(--stroke); border-radius:14px; overflow:hidden; margin:0 0 16px; background:var(--panel); }
    .pae-act-eye{ display:flex; align-items:center; gap:9px; padding:8px 14px; border-bottom:1px solid var(--stroke); }
    .pae-act-eye .seq{ flex:none; width:22px; height:22px; border-radius:7px; background:var(--brand); color:#fff;
        font-weight:800; font-size:12px; display:flex; align-items:center; justify-content:center; }
    .pae-act-eye .nm{ font-weight:800; font-size:13px; color:var(--text); }
    .pae-act-eye .nm small{ font-weight:400; color:var(--muted); }

    /* Franja de activación: 3 celdas grandes en una línea (números display). */
    .pae-strip{ display:grid; grid-template-columns:repeat(3,1fr); }
    .pae-strip .cell{ padding:12px 14px; border-right:1px solid var(--stroke); min-width:0; }
    .pae-strip .cell:last-child{ border-right:0; }
    .pae-strip .k{ font-size:8.5px; text-transform:uppercase; letter-spacing:.12em; color:var(--faint); font-weight:800; }
    .pae-strip .big{ font-size:30px; font-weight:800; line-height:1.02; color:var(--text); margin-top:3px; letter-spacing:-.01em; word-break:break-word; }
    .pae-strip .big.call{ color:var(--danger); }
    .pae-strip .mid{ font-size:16px; font-weight:800; line-height:1.15; color:var(--text); margin-top:5px; word-break:break-word; }
    .pae-strip .sub{ font-size:10.5px; color:var(--muted); margin-top:4px; line-height:1.3; word-break:break-word; }
    .pae-strip .none{ font-size:12px; color:var(--faint); font-style:italic; margin-top:6px; }

    /* Locación y traslado médico. */
    .pae-trans{ padding:12px 14px; border-top:1px solid var(--stroke); }
    .pae-trans-h{ font-weight:800; font-size:12.5px; color:var(--text); }
    .pae-trans-h small{ font-weight:400; color:var(--muted); }
    .pae-trans-grid{ display:grid; grid-template-columns:1.25fr 1fr; gap:12px 22px; margin-top:10px; }
    .pae-eta{ display:flex; align-items:baseline; gap:8px; margin-bottom:8px; }
    .pae-eta .n{ font-size:34px; font-weight:800; line-height:1; color:var(--brand); }
    .pae-eta .u{ font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
    .pae-eta .km{ font-size:12px; color:var(--muted); }
    .pae-row{ display:flex; gap:9px; padding:2px 0; }
    .pae-row .k{ flex:none; width:104px; font-weight:700; color:var(--brand); text-transform:uppercase; font-size:9px; letter-spacing:.03em; padding-top:2px; }
    .pae-row .v{ font-size:11px; color:var(--text); word-break:break-word; }
    .pae-row a{ color:var(--brand); word-break:break-all; }
    .pae-map{ margin-top:10px; border:1px solid var(--stroke); border-radius:var(--radius-sm); overflow:hidden; }
    .pae-map img{ display:block; width:100%; max-height:52mm; object-fit:cover; }
    .pae-map .cap{ font-size:8.5px; color:var(--muted); padding:3px 8px; background:var(--panel); }

    /* ===== CADENA DE MANDO — tarjetas (3 grandes + 4 chicas) ===== */
    .pae-cmd-big{ display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
    .pae-cmd-sm{ display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-top:8px; }
    .pae-card{ border:1px solid var(--stroke); border-radius:12px; padding:11px 12px; background:var(--panel); min-width:0; }
    .pae-card.big{ border-top:3px solid var(--brand); }
    .pae-card .role{ font-size:8.5px; text-transform:uppercase; letter-spacing:.08em; color:var(--faint); font-weight:800; line-height:1.2; }
    .pae-card .name{ font-weight:800; font-size:12.5px; color:var(--text); margin-top:6px; word-break:break-word; }
    .pae-card.big .name{ font-size:13.5px; }
    .pae-card .phone{ font-family:var(--mono); font-weight:700; color:var(--text); margin-top:4px; word-break:break-word; }
    .pae-card.big .phone{ font-size:20px; }
    .pae-card:not(.big) .phone{ font-size:12.5px; }
    .pae-card .radio{ display:inline-block; margin-top:6px; font-size:9px; font-weight:700; color:var(--muted);
        border:1px solid var(--stroke); border-radius:20px; padding:2px 9px; }
    .pae-card .empty{ color:var(--faint); font-style:italic; font-size:11px; margin-top:6px; }

    /* ===== GUION DE RADIO — 5 columnas numeradas ===== */
    .pae-say{ display:grid; grid-template-columns:repeat(5,1fr); gap:8px; }
    .pae-say .col{ border:1px solid var(--stroke); border-radius:11px; padding:10px 11px; background:var(--panel); min-width:0; }
    .pae-say .n{ width:22px; height:22px; border-radius:50%; background:var(--brand); color:#fff; font-weight:800;
        font-size:11px; display:flex; align-items:center; justify-content:center; }
    .pae-say .h{ font-weight:800; font-size:10.5px; color:var(--text); text-transform:uppercase; letter-spacing:.02em; margin:7px 0 4px; line-height:1.15; }
    .pae-say .b{ font-size:10.5px; color:var(--muted); line-height:1.35; }

    /* ===== FASES 3×2 (número fantasma detrás) ===== */
    .pae-fases{ display:grid; grid-template-columns:repeat(3,1fr); gap:9px; }
    .pae-fase{ position:relative; overflow:hidden; border:1px solid var(--stroke); border-radius:11px;
        padding:10px 12px 9px; min-height:104px; background:var(--panel); }
    .pae-fase .ph-num{ position:absolute; top:-10px; right:2px; font-weight:800; font-size:58px; line-height:1;
        color:color-mix(in srgb, var(--text) 7%, transparent); z-index:0; pointer-events:none; }
    .pae-fase .ph-in{ position:relative; z-index:1; }
    .pae-fase .ph-title{ font-weight:800; text-transform:uppercase; letter-spacing:.02em; font-size:12px; color:var(--brand); margin-bottom:5px; }
    .pae-fase ul{ margin:0; padding-left:15px; }
    .pae-fase li{ font-size:10.5px; color:var(--text); margin-bottom:2px; line-height:1.3; }
    .pae-fase p{ font-size:10.5px; color:var(--text); margin:0; line-height:1.35; }

    /* ===== RIESGOS DEL DÍA — tarjetas con filo por nivel ===== */
    .pae-risk-loc{ font-weight:800; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:12px 0 8px; }
    .pae-risk-loc:first-child{ margin-top:0; }
    .pae-risks{ display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
    .pae-risk{ border:1px solid var(--stroke); border-left:4px solid var(--rk, #5b6472); border-radius:10px;
        padding:10px 12px; background:var(--panel); min-width:0; }
    .pae-risk-top{ display:flex; align-items:flex-start; gap:8px; }
    .pae-lvl{ flex:none; display:inline-block; font-weight:800; font-size:8.5px; text-transform:uppercase; letter-spacing:.03em;
        color:#fff; border-radius:5px; padding:3px 8px; min-width:52px; text-align:center; }
    .pae-risk .rk-nm{ font-weight:800; font-size:12px; color:var(--text); line-height:1.25; }
    .pae-risk .rk-cat{ font-size:9px; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-weight:700; margin-top:2px; }
    .pae-risk .rk-row{ display:flex; gap:8px; margin-top:7px; }
    .pae-risk .rk-row .k{ flex:none; width:80px; font-weight:700; color:var(--brand); text-transform:uppercase; font-size:8.5px; letter-spacing:.03em; padding-top:1px; }
    .pae-risk .rk-row .v{ font-size:10.5px; color:var(--text); word-break:break-word; }
    .pae-risk .rk-std{ margin-top:8px; }
    .pae-risk .rk-more{ font-size:9px; color:var(--faint); margin-top:4px; font-style:italic; }
    .pae-empty{ color:var(--faint); font-size:11px; padding:4px 2px; font-style:italic; }

    /* ===== PROCEDIMIENTOS RÁPIDOS — 2 columnas, pasos numerados en línea ===== */
    .pae-proc-grid{ display:grid; grid-template-columns:repeat(2,1fr); gap:9px; }
    .pae-proc{ border:1px solid var(--stroke); border-radius:11px; padding:10px 12px; background:var(--panel); }
    .pae-proc-t{ font-weight:800; text-transform:uppercase; letter-spacing:.02em; font-size:11px; color:var(--brand); margin-bottom:6px; }
    .pae-proc-steps{ display:flex; flex-wrap:wrap; gap:5px 4px; align-items:baseline; }
    .pae-proc-steps .s{ font-size:10.5px; color:var(--text); line-height:1.35; }
    .pae-proc-steps .s .i{ display:inline-flex; align-items:center; justify-content:center; width:15px; height:15px;
        border-radius:50%; background:color-mix(in srgb, var(--brand) 16%, transparent); color:var(--brand);
        font-size:8.5px; font-weight:800; margin-right:3px; vertical-align:middle; }

    /* Sello (calca del Mapa / MEDEVAC). */
    .pae-seal{ display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid var(--stroke); border-radius:var(--radius-sm); background:var(--panel); }
    .pae-seal .qr{ flex:none; width:82px; height:82px; background:#fff; padding:4px; border:1px solid var(--stroke); border-radius:6px; }
    .pae-seal .qr svg{ width:100%; height:100%; display:block; }
    .pae-seal .idc{ flex:none; width:48px; height:48px; }
    .pae-seal .sbody{ flex:1; min-width:0; }
    .pae-seal .slbl{ font-weight:700; text-transform:uppercase; letter-spacing:.04em; font-size:10px; color:var(--text); }
    .pae-seal .smeta{ font-size:10px; color:var(--muted); margin-top:2px; }
    .pae-seal .shash{ font-family:var(--mono); font-size:9px; color:var(--muted); word-break:break-all; line-height:1.35; margin-top:3px; }
    .pae-seal.bad{ border-left:4px solid var(--danger); }
    .pae-seal .bad-tag{ color:var(--danger); font-weight:700; }

    @media print{
        .pae-act{ break-inside:avoid; }
        .pae-cmd-big, .pae-cmd-sm, .pae-say, .pae-fase, .pae-risk, .pae-proc{ break-inside:avoid; }
    }
    @media (max-width:720px){
        .pae-meta{ grid-template-columns:repeat(2,1fr); }
        .pae-strip{ grid-template-columns:1fr; }
        .pae-strip .cell{ border-right:0; border-bottom:1px solid var(--stroke); }
        .pae-strip .cell:last-child{ border-bottom:0; }
        .pae-trans-grid{ grid-template-columns:1fr; }
        .pae-cmd-big, .pae-cmd-sm, .pae-say, .pae-fases, .pae-risks, .pae-proc-grid{ grid-template-columns:1fr; }
    }
</style>
</head>
<body>

@include('componentes._report-v2-toolbar', [
    'backRoute'   => route('pae.index'),
    'backLabel'   => $en ? 'Back' : 'Volver',
    'exportLabel' => $en ? 'Export PDF' : 'Imprimir / PDF',
])

<div class="stage">
  <article class="sheet">
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        'heroImage'    => $mainImage !== '' ? $mainImage : null,
        'heroProject'  => $project,
        'heroLocation' => $locLabel,
        'heroDate'     => $dateStr,
        'heroTime'     => null,
        'heroModule'   => $heroModule,
      ])
    </td></tr></thead>
    <tbody><tr><td>

    {{-- BANDA (como el DSR): identidad del documento + un vistazo rápido. --}}
    <div class="band">
      <div class="lead">
        <span class="ic">@include('componentes._icon', ['name' => 'ambulance'])</span>
        <span class="who">
          <span class="lbl">{{ $en ? 'Emergency Action Plan' : 'Plan de Atención a Emergencias' }}</span>
          <span class="val">{{ $project }}</span>
          <span class="sub">{{ $p->folio() }} · {{ $docVersion }}</span>
        </span>
      </div>
      <div class="stats">
        <div class="cell"><span class="lbl">{{ $en ? 'Locations' : 'Locaciones' }}</span><span class="v">{{ count($locations) }}</span></div>
        <div class="cell"><span class="lbl">{{ $en ? 'Emergency' : 'Emergencias' }}</span><span class="v warn">{{ $services[0]['phone'] ?? '911' }}</span></div>
      </div>
    </div>

    <div class="body">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — {{ $heroModule }} — {{ $locLabel }}</h1>

      {{-- ENCABEZADO DEL LLAMADO (celdas que el emisor eligió imprimir + metadatos fijos) --}}
      <div class="pae-meta">
        @if($showCell('project'))
        <div class="cell"><div class="lbl">{{ $en ? 'Project' : 'Proyecto' }}</div><div class="val">{{ $project }}</div></div>
        @endif
        @if($showCell('shoot_day') && $shootDay)
        <div class="cell"><div class="lbl">{{ $en ? 'Shoot day' : 'Día de rodaje' }}</div><div class="val">{{ $shootDay }}</div></div>
        @endif
        @if($showCell('date') && $dateStr)
        <div class="cell"><div class="lbl">{{ $en ? 'Date' : 'Fecha' }}</div><div class="val">{{ $dateStr }}</div></div>
        @endif
        @if($showCell('unit') && $unit !== '')
        <div class="cell"><div class="lbl">{{ $en ? 'Unit' : 'Unidad' }}</div><div class="val">{{ $unit }}</div></div>
        @endif
        <div class="cell"><div class="lbl">{{ $en ? 'Prepared by' : 'Elaborado por' }}</div><div class="val">{{ $preparedName }}</div></div>
        <div class="cell"><div class="lbl">{{ $en ? 'Emergency coordinator' : 'Coordinador de emergencia' }}</div><div class="val">{{ $coordName !== '' ? $coordName : ($en ? 'Assign on set' : 'Por asignar en set') }}</div></div>
        <div class="cell"><div class="lbl">{{ $en ? 'Version' : 'Versión' }}</div><div class="val">{{ $docVersion }}</div></div>
      </div>

      @if($isMove)
      <div class="pae-move">
        <span class="tag">Company move</span>
        @if($moveTime !== '')<span><span class="k">{{ $en ? 'Estimated move' : 'Movimiento estimado' }}:</span> <span class="v">{{ $moveTime }}</span></span>@endif
        @if(count($locNames))<span><span class="k">{{ $en ? 'Order' : 'Orden' }}:</span> <span class="v">{{ implode(' → ', $locNames) }}</span></span>@endif
      </div>
      @endif

      {{-- ============ HOJA DE ACTIVACIÓN (una por locación) ============ --}}
      @foreach($locations as $loc)
        @php
          $lname    = trim((string) ($loc['name'] ?? ''));
          $laddr    = trim((string) ($loc['address'] ?? ''));
          $seq      = (int) ($loc['seq'] ?? $loop->iteration);
          $hosp     = (array) ($loc['hospital'] ?? []);
          $hName    = trim((string) ($hosp['name'] ?? ''));
          $hAddr    = trim((string) ($hosp['address'] ?? ''));
          $hDist    = trim((string) ($hosp['distance_km'] ?? ''));
          $hEta     = trim((string) ($hosp['eta'] ?? ''));
          $hMaps    = trim((string) ($hosp['maps_url'] ?? ''));
          $assembly = trim((string) ($loc['assembly_point'] ?? ''));
          $access   = trim((string) ($loc['emergency_access'] ?? ''));
          $ambul    = trim((string) ($loc['ambulance_company'] ?? ''));
          $ephone   = trim((string) ($loc['emergency_phone'] ?? ''));
          $rmap     = trim((string) ($loc['route_map'] ?? ''));
          // Teléfono de emergencia + quién contesta (coordinador de emergencia).
          $callPhone = $ephone !== '' ? $ephone : trim((string) ($coord['phone'] ?? ''));
          $callRadio = trim((string) ($coord['radio'] ?? ''));
        @endphp
        <div class="pae-act">
          <div class="pae-act-eye">
            <span class="seq">{{ $seq }}</span>
            <span class="nm">{{ $lname !== '' ? $lname : ($en ? 'Location' : 'Locación') }}@if($laddr !== '') <small>· {{ $laddr }}</small>@endif</span>
          </div>

          {{-- Franja de activación: 911 · ambulancia · teléfono de emergencia (números display) --}}
          <div class="pae-strip">
            <div class="cell">
              <div class="k">{{ $en ? 'Emergency · dial' : 'Emergencias · marca' }}</div>
              <div class="big call">911</div>
            </div>
            <div class="cell">
              <div class="k">{{ $en ? 'Assigned ambulance' : 'Ambulancia asignada' }}</div>
              @if($ambul !== '')<div class="mid">{{ $ambul }}</div>@else<div class="none">{{ $en ? 'Assign on set' : 'Por asignar en set' }}</div>@endif
            </div>
            <div class="cell">
              <div class="k">{{ $en ? 'Emergency phone' : 'Teléfono de emergencia' }}</div>
              @if($callPhone !== '')
                <div class="big">{{ $callPhone }}</div>
                @if($coordName !== '')<div class="sub">{{ $coordName }} · {{ $coord['label'] ?? ($en ? 'Emergency coordinator' : 'Coordinador de emergencia') }}@if($callRadio !== '') · {{ $en ? 'Radio' : 'Radio' }} {{ $callRadio }}@endif</div>@endif
              @else
                <div class="none">{{ $en ? 'Assign on set' : 'Por asignar en set' }}</div>
              @endif
            </div>
          </div>

          {{-- Locación y traslado médico: ETA display + km · hospital · reunión/acceso · mapa --}}
          <div class="pae-trans">
            <div class="pae-trans-h">{{ $lname !== '' ? $lname : ($en ? 'Location' : 'Locación') }}@if($laddr !== '') <small>· {{ $laddr }}</small>@endif</div>
            <div class="pae-trans-grid">
              <div>
                @if($hEta !== '' || $hDist !== '')
                <div class="pae-eta">
                  @if($hEta !== '')<span class="n">{{ $hEta }}</span><span class="u">{{ $en ? 'to hospital' : 'al hospital' }}</span>@endif
                  @if($hDist !== '')<span class="km">· {{ $hDist }} km</span>@endif
                </div>
                @endif
                @if($hName !== '')<div class="pae-row"><span class="k">Hospital</span><span class="v">{{ $hName }}@if($hAddr !== '') — {{ $hAddr }}@endif</span></div>@endif
                @if($hMaps !== '')<div class="pae-row"><span class="k">Google Maps</span><span class="v"><a href="{{ $hMaps }}" target="_blank" rel="noopener">{{ $en ? 'Route to hospital' : 'Ruta al hospital' }}</a></span></div>@endif
              </div>
              <div>
                @if($assembly !== '')<div class="pae-row"><span class="k">{{ $en ? 'Assembly' : 'Punto reunión' }}</span><span class="v">{{ $assembly }}</span></div>@endif
                @if($access !== '')<div class="pae-row"><span class="k">{{ $en ? 'Emergency access' : 'Acceso emerg.' }}</span><span class="v">{{ $access }}</span></div>@endif
                @if($ambul !== '')<div class="pae-row"><span class="k">{{ $en ? 'Ambulance' : 'Ambulancia' }}</span><span class="v">{{ $ambul }}</span></div>@endif
                @if($assembly === '' && $access === '' && $ambul === '')<div class="pae-row"><span class="v" style="color:var(--faint);font-style:italic">{{ $en ? 'No assembly point or access recorded in the scouting.' : 'El scouting no registra punto de reunión ni acceso.' }}</span></div>@endif
              </div>
            </div>
            @if($rmap !== '')
            <div class="pae-map"><img src="{{ $rmap }}" alt="{{ $en ? 'Route to hospital' : 'Ruta al hospital' }}"><div class="cap">{{ $en ? 'Route to the hospital' : 'Ruta a hospital' }}</div></div>
            @endif
          </div>
        </div>
      @endforeach

      {{-- ============ CADENA DE MANDO EN EMERGENCIA (una vez — mismo crew) ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ $en ? 'Emergency chain of command' : 'Cadena de mando en emergencia' }}</h2><span class="line"></span></div>
        @php
          $bigSlots = ['coordinador_emergencia', 'set_medic', 'safety'];
          $smSlots  = ['locaciones_transporte', 'spfx_stunts', 'brigada_incendios', 'extras_background'];
        @endphp
        <div class="pae-cmd-big">
          @foreach($bigSlots as $k)
            @php $c = $slot($k); $cn = trim((string) ($c['name'] ?? '')); $cp = trim((string) ($c['phone'] ?? '')); $cr = trim((string) ($c['radio'] ?? '')); @endphp
            <div class="pae-card big">
              <div class="role">{{ $c['label'] ?? '' }}</div>
              @if($cn !== '')
                <div class="name">{{ $cn }}</div>
                @if($cp !== '')<div class="phone">{{ $cp }}</div>@endif
                @if($cr !== '')<span class="radio">{{ $en ? 'Radio' : 'Radio' }} {{ $cr }}</span>@endif
              @else
                <div class="empty">{{ $en ? 'Assign on set' : 'Por asignar en set' }}</div>
              @endif
            </div>
          @endforeach
        </div>
        <div class="pae-cmd-sm">
          @foreach($smSlots as $k)
            @php $c = $slot($k); $cn = trim((string) ($c['name'] ?? '')); $cp = trim((string) ($c['phone'] ?? '')); $cr = trim((string) ($c['radio'] ?? '')); @endphp
            <div class="pae-card">
              <div class="role">{{ $c['label'] ?? '' }}</div>
              @if($cn !== '')
                <div class="name">{{ $cn }}</div>
                @if($cp !== '')<div class="phone">{{ $cp }}</div>@endif
                @if($cr !== '')<span class="radio">{{ $en ? 'Radio' : 'Radio' }} {{ $cr }}</span>@endif
              @else
                <div class="empty">{{ $en ? 'Assign on set' : 'Por asignar en set' }}</div>
              @endif
            </div>
          @endforeach
        </div>
      </section>

      {{-- ============ QUÉ DECIR AL REPORTAR (guion de radio, fijo) ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ $en ? 'What to say when reporting' : 'Qué decir al reportar' }}</h2><span class="line"></span></div>
        <div class="pae-say">
          @foreach($sayScript as $i => $s)
            <div class="col">
              <span class="n">{{ $i + 1 }}</span>
              <div class="h">{{ $s[0] }}</div>
              <div class="b">{{ $s[1] }}</div>
            </div>
          @endforeach
        </div>
      </section>

      {{-- ============ FASES ANTE UNA EMERGENCIA (3×2, fijo) ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ $en ? 'Emergency phases' : 'Fases ante una emergencia' }}</h2><span class="line"></span></div>
        <div class="pae-fases">
          @foreach($fases as $f)
            <div class="pae-fase">
              <span class="ph-num" aria-hidden="true">{{ $f[0] }}</span>
              <div class="ph-in">
                <div class="ph-title">{{ $f[1] }}</div>
                @if(count($f[2]) > 1)
                  <ul>@foreach($f[2] as $b)<li>{{ $b }}</li>@endforeach</ul>
                @else
                  <p>{{ $f[2][0] }}</p>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </section>

      {{-- ============ RIESGOS DEL DÍA (tarjetas con filo por nivel + badges de norma) ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ $en ? 'Risks of the day' : 'Riesgos del día' }}</h2><span class="line"></span></div>
        @foreach($locations as $loc)
          @php
            $lname = trim((string) ($loc['name'] ?? ''));
            $risks = (array) ($loc['risks'] ?? []);
            // Ordenar por nivel (E>H>M>L). Sólo render — no toca el payload ni el hash.
            usort($risks, function ($a, $b) use ($ratingMeta) {
                return $ratingMeta($a['rating'] ?? '')[2] <=> $ratingMeta($b['rating'] ?? '')[2];
            });
          @endphp
          @if(count($locations) > 1)
          <div class="pae-risk-loc">{{ $lname !== '' ? $lname : ($en ? 'Location' : 'Locación') }}</div>
          @endif
          @if(count($risks))
          <div class="pae-risks">
            @foreach($risks as $rk)
              @php
                $rl     = $ratingMeta($rk['rating'] ?? '');
                $rkName = trim((string) ($rk['hazard'] ?? ''));
                $rkCat  = trim((string) ($rk['category_label'] ?? ''));
                $rkCtrl = trim((string) ($rk['control'] ?? ''));
                $rkResp = trim((string) ($rk['responsable'] ?? ''));
                $rkMore = (int) ($rk['standards_more'] ?? 0);
                // Normas CONGELADAS del evento → objetos para el MISMO parcial que el DSR.
                $stdObjs = collect((array) ($rk['standards'] ?? []))->map(function ($s) {
                    $o = new \stdClass();
                    $o->regulation_badge = (string) ($s['badge'] ?? '');
                    $o->regulation_code  = (string) ($s['code'] ?? '');
                    $o->reference_url    = (string) ($s['url'] ?? '');
                    $o->category_name_localized = '';
                    return $o;
                });
              @endphp
              <div class="pae-risk" style="--rk:{{ $rl[1] }}">
                <div class="pae-risk-top">
                  <span class="pae-lvl" style="background:{{ $rl[1] }}">{{ $rl[0] }}</span>
                  <div>
                    <div class="rk-nm">{{ $rkName !== '' ? $rkName : '—' }}</div>
                    @if($rkCat !== '')<div class="rk-cat">{{ $rkCat }}</div>@endif
                  </div>
                </div>
                @if($rkCtrl !== '')<div class="rk-row"><span class="k">{{ $en ? 'Control' : 'Control' }}</span><span class="v">{{ $rkCtrl }}</span></div>@endif
                @if($rkResp !== '')<div class="rk-row"><span class="k">{{ $en ? 'Owner' : 'Responsable' }}</span><span class="v">{{ $rkResp }}</span></div>@endif
                @if($stdObjs->count())
                <div class="rk-std">
                  @include('componentes._standards-chips', [
                    'standards' => $stdObjs,
                    'snapBadge' => '',
                    'snapCode'  => '',
                    'snapUrl'   => null,
                    'stdLayout' => 'row',
                  ])
                  @if($rkMore > 0)<div class="rk-more">+{{ $rkMore }} {{ $en ? 'more standards' : 'normas más' }}</div>@endif
                </div>
                @endif
              </div>
            @endforeach
          </div>
          @else
          <div class="pae-empty">{{ $en ? 'No hazards were assessed for this location in the scouting.' : 'El scouting de esta locación no registra peligros evaluados.' }}</div>
          @endif
        @endforeach
      </section>

      {{-- ============ PROCEDIMIENTOS RÁPIDOS (2 columnas, pasos numerados en línea) ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ $en ? 'Quick response procedures' : 'Procedimientos rápidos de respuesta' }}</h2><span class="line"></span></div>
        <div class="pae-proc-grid">
          @foreach($procs as $pr)
            <div class="pae-proc">
              <div class="pae-proc-t">{{ $pr[0] }}</div>
              <div class="pae-proc-steps">
                @foreach($pr[1] as $si => $step)<span class="s"><span class="i">{{ $si + 1 }}</span>{{ $step }}</span>@endforeach
              </div>
            </div>
          @endforeach
        </div>
      </section>

      {{-- ============ SELLO SHA ============ --}}
      <div class="pae-seal {{ $verdict === false ? 'bad' : '' }}">
        @if($qr)<div class="qr">{!! $qr !!}</div>@endif
        <div class="sbody">
          <div class="slbl">{{ $en ? 'SHA-256 digital seal' : 'Sello digital SHA-256' }}
            @if($verdict === false)<span class="bad-tag">· {{ $en ? 'altered document' : 'documento alterado' }}</span>
            @elseif($verdict === null)<span style="color:var(--faint)">· {{ $en ? 'not sealed' : 'sin sellar' }}</span>@endif
          </div>
          <div class="smeta">{{ $en ? 'Folio' : 'Folio' }} {{ $p->folio() }}@if($sealedAt) · {{ $en ? 'sealed' : 'sellado' }} {{ $sealedAt }}@endif @if($p->uuid && $sig)· {{ $en ? 'verify by scanning the QR' : 'verifica escaneando el QR' }}@endif</div>
          @if($sig)<div class="shash">{{ $sig->document_hash }}</div>@endif
        </div>
        @if($identicon)<div class="idc">{!! $identicon !!}</div>@endif
      </div>

    </div>{{-- .body --}}

    </td></tr></tbody>
    </table>
@include('componentes._report-v2-foot', [
    'footPreparedName' => $preparedName,
    'footPreparedMeta' => $footMeta,
    'footUuid'         => $footUuid,
])
</body>
</html>
