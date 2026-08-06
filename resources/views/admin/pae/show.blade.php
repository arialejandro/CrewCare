{{-- ============================================================================================
     PAE — PLAN DE ATENCIÓN A EMERGENCIAS. Documento SELLADO (uno por llamado). HOMOLOGADO al
     chrome compartido de reportes v2 (familia Daily Safety Report): _report-v2-head/-toolbar/
     -foot + _doc-hero + banda de datos + secciones .sec. Estructura tomada del PAE estándar de
     rodaje: Mapeo de riesgos · Organigrama de emergencia · Procedimientos rápidos · Acuse de
     recepción. Se lee SÓLO del payload congelado; un dato vacío NO aparece. El sello SHA se
     calcula sobre el DATO, no sobre este render.
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
    $project   = trim((string) ($hd['project'] ?? '')) ?: $brandName;
    $unit      = trim((string) ($hd['unit'] ?? ''));
    $dateStr   = $planDate !== '' ? \Carbon\Carbon::parse($planDate)->format('d/m/Y') : optional($p->issued_at)->format('d/m/Y');

    // Coordinador de emergencia = primer slot del organigrama (Producción/UPM).
    $coordName = '';
    foreach ($crew as $c) { if (($c['key'] ?? '') === 'coordinador_emergencia') { $coordName = trim((string) ($c['name'] ?? '')); break; } }
    $preparedName = trim((string) $p->issued_by_name) ?: '—';

    // Locaciones (para el hero y la banda).
    $locNames = [];
    foreach ($locations as $lx) { $nm = trim((string) ($lx['name'] ?? '')); if ($nm !== '') { $locNames[] = $nm; } }
    $locLabel = $locNames ? implode(' · ', $locNames) : ($en ? 'Location' : 'Locación');

    $docVersion = config('crewcare.doc_version');   // "VER x.x" — misma versión que el DSR

    // Nivel de riesgo → etiqueta + color (H/E/M/L). DERIVADO, no sellado.
    $ratingMeta = function ($r) {
        $r = strtoupper(trim((string) $r));
        switch ($r) {
            case 'E': return ['Extremo', '#7b1fa2'];
            case 'H': return ['Alto',    '#c0392b'];
            case 'M': return ['Medio',   '#c98a00'];
            case 'L': return ['Bajo',    '#2e7d32'];
            default:  return [$r !== '' ? $r : '—', '#5b6472'];
        }
    };

    // Procedimientos rápidos de respuesta — texto FIJO del PAE estándar de rodaje.
    $procs = [
        ['Emergencia médica', 'Avisar por radio al Set Medic · Asegurar el área · Aplicar primeros auxilios si está capacitado · Trasladar al hospital de referencia si es necesario.'],
        ['Incendio', 'Activar alarma / avisar por radio · Cortar energía del área si es seguro · Evacuar hacia el punto de reunión · Usar extintor solo si es seguro hacerlo.'],
        ['Evacuación por clima severo', 'Suspender rodaje · Asegurar equipo suelto · Guiar al elenco y crew al refugio designado · Esperar indicación de Producción para reanudar.'],
        ['Persona extraviada / incidente SPFX', 'Reportar de inmediato al Coordinador de Seguridad · Delimitar el área si hay incidente con SPFX · Iniciar conteo de crew presente.'],
        ['Amenaza externa / intruso', 'No confrontar · Avisar a seguridad y Producción por radio · Resguardar a elenco/crew en zona segura · Llamar a la policía si es necesario.'],
    ];

    // Sello.
    $sig       = $p->signatures()->latest('id')->first();
    $verdict   = $p->verifyLatestSignature();   // true / false / null
    $verifyUrl = SealVerifier::urlFor($p);
    $qr        = $verifyUrl ? SealVerifier::qrSvg($verifyUrl, 108) : null;
    $identicon = $sig ? SealVerifier::identiconSvg($sig->document_hash, 48) : null;
    $sealedAt  = ($sig && $sig->signed_at) ? \Carbon\Carbon::parse($sig->signed_at)->format('d/m/Y H:i') : null;

    // Hero + pie (misma fórmula que el Mapa / MEDEVAC / reportes v2).
    $heroImage  = null;
    foreach ($locations as $lx) { $rm = trim((string) ($lx['route_map'] ?? '')); if ($rm !== '') { $heroImage = $rm; break; } }
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
    /* Contenido propio del PAE (scoped .paed-*). Hereda los tokens del chrome compartido. */
    .paed-move{ display:flex; flex-wrap:wrap; gap:5px 16px; align-items:baseline; margin:0 0 12px;
        padding:8px 11px; border-left:3px solid var(--brand); background:var(--panel); border-radius:var(--radius-sm); }
    .paed-move .tag{ font-weight:800; text-transform:uppercase; letter-spacing:.05em; font-size:10px; color:var(--brand); }
    .paed-move .k{ font-weight:700; text-transform:uppercase; font-size:9px; letter-spacing:.03em; color:var(--faint); }
    .paed-move .v{ font-weight:700; font-size:12px; color:var(--text); }

    /* Rejilla de datos del encabezado (Elaborado por / Coordinador / Unidad / Versión). */
    .paed-meta{ display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin:0 0 4px; }
    .paed-meta .cell{ border:1px solid var(--stroke); border-radius:9px; padding:7px 10px; background:var(--panel); }
    .paed-meta .lbl{ font-size:8.5px; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
    .paed-meta .val{ font-size:12px; font-weight:700; color:var(--text); margin-top:2px; word-break:break-word; }

    /* Bloque por locación. */
    .paed-loc + .paed-loc{ margin-top:16px; }
    .paed-loc-h{ display:flex; align-items:center; gap:9px; margin:0 0 8px; }
    .paed-loc-h .seq{ flex:none; width:22px; height:22px; border-radius:6px; background:var(--brand); color:#fff;
        font-weight:800; font-size:12px; display:flex; align-items:center; justify-content:center; }
    .paed-loc-h .nm{ font-weight:800; font-size:13px; color:var(--text); }
    .paed-loc-h .nm small{ font-weight:400; color:var(--muted); }

    .paed-logi{ display:grid; grid-template-columns:1.4fr 1fr; gap:2px 22px; margin:0 0 9px; }
    .paed-row{ display:flex; gap:9px; padding:2px 0; }
    .paed-row .k{ flex:none; width:110px; font-weight:700; color:var(--brand); text-transform:uppercase; font-size:9px; letter-spacing:.03em; padding-top:2px; }
    .paed-row .v{ font-size:11px; color:var(--text); word-break:break-word; }
    .paed-row a{ color:var(--brand); word-break:break-all; }

    table.paed-tbl{ width:100%; border-collapse:collapse; margin-top:2px; }
    table.paed-tbl th, table.paed-tbl td{ text-align:left; padding:6px 8px; border-bottom:1px solid var(--stroke); font-size:11px; vertical-align:top; }
    table.paed-tbl th{ text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-size:8.5px; }
    table.paed-tbl .ev{ font-weight:700; color:var(--text); }
    table.paed-tbl a{ color:var(--brand); word-break:break-all; }
    .paed-lvl{ display:inline-block; font-weight:800; font-size:8.5px; text-transform:uppercase; letter-spacing:.03em;
        color:#fff; border-radius:4px; padding:2px 7px; min-width:48px; text-align:center; }
    .paed-empty{ color:var(--faint); font-size:11px; padding:6px 2px; }

    .paed-map{ margin-top:8px; border:1px solid var(--stroke); border-radius:var(--radius-sm); overflow:hidden; }
    .paed-map img{ display:block; width:100%; max-height:56mm; object-fit:cover; }
    .paed-map .cap{ font-size:8.5px; color:var(--muted); padding:3px 7px; background:var(--panel); }

    .paed-rmap{ display:flex; align-items:center; gap:11px; margin-top:9px; padding:8px 11px;
        border:1px dashed var(--stroke); border-radius:var(--radius-sm); background:var(--panel); }
    .paed-rmap .qr{ flex:none; width:56px; height:56px; background:#fff; padding:3px; border:1px solid var(--stroke); border-radius:5px; }
    .paed-rmap .qr svg{ width:100%; height:100%; display:block; }
    .paed-rmap .lbl{ font-weight:700; text-transform:uppercase; letter-spacing:.04em; font-size:9.5px; color:var(--brand); }
    .paed-rmap .fol{ font-family:var(--mono); font-weight:700; font-size:12px; color:var(--text); margin-top:2px; }
    .paed-views{ margin-top:8px; display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .paed-view{ border:1px solid var(--stroke); border-radius:6px; overflow:hidden; }
    .paed-view img{ display:block; width:100%; max-height:56mm; object-fit:cover; }
    .paed-view .cap{ font-size:8.5px; color:var(--muted); padding:3px 7px; background:var(--panel); }

    /* Organigrama y procedimientos. */
    .paed-empty-cell{ color:var(--faint); }
    .paed-proc{ display:grid; gap:8px; }
    .paed-proc .pr{ border:1px solid var(--stroke); border-radius:9px; padding:8px 11px; background:var(--panel); }
    .paed-proc .pr-t{ font-weight:800; text-transform:uppercase; letter-spacing:.03em; font-size:10.5px; color:var(--brand); margin-bottom:3px; }
    .paed-proc .pr-b{ font-size:11px; color:var(--text); }

    /* Acuse de recepción (líneas en blanco para firmar en la copia impresa). */
    table.paed-sign{ width:100%; border-collapse:collapse; margin-top:4px; }
    table.paed-sign th, table.paed-sign td{ border:1px solid var(--stroke); padding:7px 8px; font-size:10.5px; }
    table.paed-sign th{ text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-size:8.5px; background:var(--panel); text-align:left; }
    table.paed-sign td{ height:26px; }

    /* Sello (calca del Mapa). */
    .paed-seal{ display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid var(--stroke); border-radius:var(--radius-sm); background:var(--panel); }
    .paed-seal .qr{ flex:none; width:82px; height:82px; background:#fff; padding:4px; border:1px solid var(--stroke); border-radius:6px; }
    .paed-seal .qr svg{ width:100%; height:100%; display:block; }
    .paed-seal .idc{ flex:none; width:48px; height:48px; }
    .paed-seal .sbody{ flex:1; min-width:0; }
    .paed-seal .slbl{ font-weight:700; text-transform:uppercase; letter-spacing:.04em; font-size:10px; color:var(--text); }
    .paed-seal .smeta{ font-size:10px; color:var(--muted); margin-top:2px; }
    .paed-seal .shash{ font-family:var(--mono); font-size:9px; color:var(--muted); word-break:break-all; line-height:1.35; margin-top:3px; }
    .paed-seal.bad{ border-left:4px solid var(--danger); }
    .paed-seal .bad-tag{ color:var(--danger); font-weight:700; }

    @media print{ .paed-loc{ break-inside:avoid; } table.paed-sign{ break-inside:avoid; } }
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
        'heroImage'    => $heroImage,
        'heroProject'  => $project,
        'heroLocation' => $locLabel,
        'heroDate'     => $dateStr,
        'heroTime'     => $shootDay ? (($en ? 'Shoot day ' : 'Día de rodaje ') . $shootDay) : null,
        'heroModule'   => $heroModule,
      ])
    </td></tr></thead>
    <tbody><tr><td>

    {{-- BANDA DE DATOS (como el DSR): plan + versión + locaciones + emergencias. --}}
    <div class="band">
      <div class="lead">
        <span class="ic">@include('componentes._icon', ['name' => 'ambulance'])</span>
        <span class="who">
          <span class="lbl">{{ $en ? 'Emergency Action Plan' : 'Plan de Atención a Emergencias' }}</span>
          <span class="val">{{ $project }}</span>
          <span class="sub">{{ $p->folio() }} · {{ $docVersion }}@if($shootDay) · {{ $en ? 'Shoot day' : 'Día' }} {{ $shootDay }}@endif @if($dateStr)· {{ $dateStr }}@endif</span>
        </span>
      </div>
      <div class="stats">
        <div class="cell"><span class="lbl">{{ $en ? 'Locations' : 'Locaciones' }}</span><span class="v">{{ count($locations) }}</span></div>
        <div class="cell"><span class="lbl">{{ $en ? 'Emergency' : 'Emergencias' }}</span><span class="v warn">{{ $services[0]['phone'] ?? '911' }}</span></div>
      </div>
    </div>

    <div class="body">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — {{ $heroModule }} — {{ $locLabel }}</h1>

      {{-- Encabezado del llamado: elaborado por / coordinador / unidad / versión --}}
      <div class="paed-meta">
        <div class="cell"><div class="lbl">{{ $en ? 'Prepared by' : 'Elaborado por' }}</div><div class="val">{{ $preparedName }}</div></div>
        <div class="cell"><div class="lbl">{{ $en ? 'Emergency coordinator' : 'Coordinador de emergencia' }}</div><div class="val">{{ $coordName !== '' ? $coordName : ($en ? 'Assign on set' : 'Por asignar en set') }}</div></div>
        <div class="cell"><div class="lbl">{{ $en ? 'Unit' : 'Unidad' }}</div><div class="val">{{ $unit !== '' ? $unit : '—' }}</div></div>
        <div class="cell"><div class="lbl">{{ $en ? 'Version' : 'Versión' }}</div><div class="val">{{ $docVersion }}</div></div>
      </div>

      @if($isMove)
      <div class="paed-move">
        <span class="tag">Company move</span>
        @if($moveTime !== '')<span><span class="k">{{ $en ? 'Estimated move' : 'Movimiento estimado' }}:</span> <span class="v">{{ $moveTime }}</span></span>@endif
        @if(count($locNames))<span><span class="k">{{ $en ? 'Order' : 'Orden' }}:</span> <span class="v">{{ implode(' → ', $locNames) }}</span></span>@endif
      </div>
      @endif

      {{-- ============ 1 · MAPEO DE RIESGOS (por locación) ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>1 · {{ $en ? 'Risk map' : 'Mapeo de riesgos' }}</h2><span class="line"></span></div>

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
          <div class="paed-loc">
            <div class="paed-loc-h">
              <span class="seq">{{ $seq }}</span>
              <span class="nm">{{ $lname ?: ($en ? 'Location' : 'Locación') }}@if($laddr !== '') <small>· {{ $laddr }}</small>@endif</span>
            </div>

            {{-- Hospital / puntos de reunión y rutas (secciones 5 y 4 del PAE, por locación) --}}
            <div class="paed-logi">
              <div>
                @if($hName !== '')<div class="paed-row"><span class="k">Hospital</span><span class="v">{{ $hName }}@if($hAddr !== '') — {{ $hAddr }}@endif</span></div>@endif
                @if($hDist !== '' || $hEta !== '')<div class="paed-row"><span class="k">{{ $en ? 'Distance / time' : 'Distancia / tiempo' }}</span><span class="v">{{ $hDist !== '' ? ($hDist . ' km') : '' }}@if($hDist !== '' && $hEta !== '') · @endif{{ $hEta }}</span></div>@endif
                @if($hMaps !== '')<div class="paed-row"><span class="k">Google Maps</span><span class="v"><a href="{{ $hMaps }}" target="_blank" rel="noopener">{{ $en ? 'Route to hospital' : 'Ruta al hospital' }}</a></span></div>@endif
              </div>
              <div>
                @if($assembly !== '')<div class="paed-row"><span class="k">{{ $en ? 'Assembly' : 'Punto reunión' }}</span><span class="v">{{ $assembly }}</span></div>@endif
                @if($access !== '')<div class="paed-row"><span class="k">{{ $en ? 'Evac. route' : 'Ruta evacuación' }}</span><span class="v">{{ $access }}</span></div>@endif
                @if($ambul !== '')<div class="paed-row"><span class="k">{{ $en ? 'Ambulance' : 'Ambulancia' }}</span><span class="v">{{ $ambul }}</span></div>@endif
                @if($ephone !== '')<div class="paed-row"><span class="k">{{ $en ? 'Emerg. phone' : 'Tel. emerg.' }}</span><span class="v">{{ $ephone }}</span></div>@endif
              </div>
            </div>

            {{-- Tabla de riesgos: Zona | Riesgo | Nivel | Medida de control | Responsable --}}
            @if(count($risks))
            <table class="paed-tbl">
              <thead><tr>
                <th style="width:16%">{{ $en ? 'Zone / area' : 'Zona / área' }}</th>
                <th style="width:30%">{{ $en ? 'Identified risk' : 'Riesgo identificado' }}</th>
                <th style="width:9%">{{ $en ? 'Level' : 'Nivel' }}</th>
                <th style="width:27%">{{ $en ? 'Control measure' : 'Medida de control' }}</th>
                <th style="width:18%">{{ $en ? 'Owner' : 'Responsable' }}</th>
              </tr></thead>
              <tbody>
                @foreach($risks as $rk)
                  @php
                    $rl = $ratingMeta($rk['rating'] ?? '');
                    $rkName = trim((string) ($rk['hazard'] ?? ''));
                    $rkZona = trim((string) ($rk['category_label'] ?? ''));
                    $rkCtrl = trim((string) ($rk['control'] ?? ''));
                    $rkResp = trim((string) ($rk['responsable'] ?? ''));
                    $rkCode = trim((string) ($rk['code'] ?? ''));
                    $rkUrl  = trim((string) ($rk['url'] ?? ''));
                    $rkBadge= trim((string) ($rk['badge'] ?? ''));
                  @endphp
                  <tr>
                    <td>{{ $rkZona !== '' ? $rkZona : '—' }}</td>
                    <td>
                      <span class="ev">{{ $rkName !== '' ? $rkName : '—' }}</span>
                      @if($rkCode !== '')<br><small>@if($rkUrl !== '')<a href="{{ $rkUrl }}" target="_blank" rel="noopener">{{ $rkBadge !== '' ? ($rkBadge . ' · ') : '' }}{{ $rkCode }}</a>@else {{ $rkBadge !== '' ? ($rkBadge . ' · ') : '' }}{{ $rkCode }}@endif</small>@endif
                    </td>
                    <td><span class="paed-lvl" style="background:{{ $rl[1] }}">{{ $rl[0] }}</span></td>
                    <td>{{ $rkCtrl !== '' ? $rkCtrl : '' }}</td>
                    <td>{{ $rkResp !== '' ? $rkResp : '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            @else
            <div class="paed-empty">{{ $en ? 'No hazards were assessed for this location in the scouting.' : 'El scouting de esta locación no registra peligros evaluados.' }}</div>
            @endif

            @if($rmap !== '')
            <div class="paed-map"><img src="{{ $rmap }}" alt="{{ $en ? 'Route to hospital' : 'Ruta al hospital' }}"><div class="cap">{{ $en ? 'Route to the hospital' : 'Ruta a hospital' }}</div></div>
            @endif

            @if(is_array($rm) && (trim((string) ($rm['folio'] ?? '')) !== '' || !empty($rm['views'])))
              @php
                $rmFolio = trim((string) ($rm['folio'] ?? ''));
                $rmUrl   = trim((string) ($rm['verify_url'] ?? ''));
                $rmViews = (array) ($rm['views'] ?? []);
                $rmQr    = $rmUrl !== '' ? SealVerifier::qrSvg($rmUrl, 72) : null;
              @endphp
              <div class="paed-rmap">
                @if($rmQr)<div class="qr">{!! $rmQr !!}</div>@endif
                <div>
                  <div class="lbl">{{ $en ? 'Risk & resource map' : 'Mapeo de riesgos y recursos' }}</div>
                  @if($rmFolio !== '')<div class="fol">{{ $rmFolio }}</div>@endif
                  <div style="font-size:9px;color:var(--muted);margin-top:2px">{{ $en ? 'Sealed document — scan the QR to verify.' : 'Documento sellado — escanea el QR para verificar.' }}</div>
                </div>
              </div>
              @if(count($rmViews))
              <div class="paed-views">
                @foreach($rmViews as $vw)
                  @php $vImg = trim((string) ($vw['image'] ?? '')); $vLbl = trim((string) ($vw['label'] ?? '')); @endphp
                  @if($vImg !== '')<div class="paed-view"><img src="{{ $vImg }}" alt="{{ $vLbl ?: 'Vista' }}">@if($vLbl !== '')<div class="cap">{{ $vLbl }}</div>@endif</div>@endif
                @endforeach
              </div>
              @endif
            @endif
          </div>
        @endforeach
      </section>

      {{-- ============ 2 · ORGANIGRAMA DE EMERGENCIA ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>2 · {{ $en ? 'Emergency org chart' : 'Organigrama de emergencia' }}</h2><span class="line"></span></div>
        <table class="paed-tbl">
          <thead><tr>
            <th style="width:40%">{{ $en ? 'Role' : 'Rol' }}</th>
            <th style="width:28%">{{ $en ? 'Name' : 'Nombre' }}</th>
            <th style="width:20%">{{ $en ? 'Phone' : 'Teléfono' }}</th>
            <th style="width:12%">{{ $en ? 'Radio' : 'Radio' }}</th>
          </tr></thead>
          <tbody>
            @foreach($crew as $c)
              @php $cn = trim((string) ($c['name'] ?? '')); $cp = trim((string) ($c['phone'] ?? '')); $cr = trim((string) ($c['radio'] ?? '')); @endphp
              <tr>
                <td class="ev">{{ $c['label'] ?? '' }}</td>
                <td>{{ $cn !== '' ? $cn : '' }}@if($cn === '')<span class="paed-empty-cell">{{ $en ? 'Assign on set' : 'Por asignar en set' }}</span>@endif</td>
                <td>{{ $cp }}</td>
                <td>{{ $cr }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </section>

      {{-- ============ 3 · PROCEDIMIENTOS RÁPIDOS DE RESPUESTA ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>3 · {{ $en ? 'Quick response procedures' : 'Procedimientos rápidos de respuesta' }}</h2><span class="line"></span></div>
        <div class="paed-proc">
          @foreach($procs as $pr)
            <div class="pr"><div class="pr-t">{{ $pr[0] }}</div><div class="pr-b">{{ $pr[1] }}</div></div>
          @endforeach
        </div>
      </section>

      {{-- ============ 4 · ACUSE DE RECEPCIÓN ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>4 · {{ $en ? 'Acknowledgement of receipt' : 'Acuse de recepción' }}</h2><span class="line"></span></div>
        <p style="font-size:11px;color:var(--muted);margin:0 0 6px">{{ $en ? 'Everyone on set must receive and sign this plan.' : 'Todo el equipo presente en el rodaje debe recibir y firmar de enterado este plan.' }}</p>
        <table class="paed-sign">
          <thead><tr>
            <th style="width:34%">{{ $en ? 'Name' : 'Nombre' }}</th>
            <th style="width:26%">{{ $en ? 'Position' : 'Puesto' }}</th>
            <th style="width:26%">{{ $en ? 'Signature' : 'Firma' }}</th>
            <th style="width:14%">{{ $en ? 'Date' : 'Fecha' }}</th>
          </tr></thead>
          <tbody>
            @for($i = 0; $i < 6; $i++)<tr><td></td><td></td><td></td><td></td></tr>@endfor
          </tbody>
        </table>
      </section>

      {{-- SELLO SHA --}}
      <div class="paed-seal {{ $verdict === false ? 'bad' : '' }}">
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
