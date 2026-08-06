{{-- ============================================================================
     MAPEO DE RIESGOS Y RECURSOS — documento (delta #50). HOMOLOGADO al chrome
     compartido de reportes v2 (Daily Safety Report): _report-v2-head (fuentes+CSS+
     motor de impresión) · _report-v2-toolbar · _doc-hero (hero de marca, se repite
     por hoja vía <thead>) · banda de datos · secciones .sec · _report-v2-foot.
     Contenido propio (scoped .rmd-*): lienzo con pines ABSOLUTOS por x_pct/y_pct
     (sin html2canvas), inventario, tabla de peligros, simbología y sello.
     El sello SHA se calcula sobre el DATO, no sobre este render.
============================================================================ --}}
@php
    use App\Support\SealVerifier;
    use App\Support\Branding;
    use App\Support\RiskSigns;
    use App\Models\RiskMapMarker;

    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = $brand['brand_name'] ?? 'CrewCare';
    $primary   = $brand['primary_color'] ?? '#ff9900';
    $en        = app()->getLocale() === 'en';

    $views   = $map->views;              // ordenadas
    $total   = $views->count();
    $sealed  = $map->isSealed();
    $folio   = $map->folio();
    $loc     = $map->locationName();
    $dateStr = optional($map->sealed_at ?: $map->created_at)->format('d/m/Y');

    // Sello.
    $sig       = $map->signatures()->latest('id')->first();
    $verdict   = $map->verifyLatestSignature();   // true / false / null
    $verifyUrl = SealVerifier::urlFor($map);
    $qr        = $verifyUrl ? SealVerifier::qrSvg($verifyUrl, 108) : null;
    $identicon = $sig ? SealVerifier::identiconSvg($sig->document_hash, 48) : null;
    $sealedAt  = ($sig && $sig->signed_at) ? \Carbon\Carbon::parse($sig->signed_at)->format('d/m/Y H:i') : null;

    // Pie de marca (misma fórmula que los reportes / MEDEVAC).
    $authorId   = $map->sealed_by ?: $map->created_by_id;
    $authorName = trim((string) optional(\App\Models\User::find($authorId))->name) ?: '—';
    $footMeta   = ($en ? 'Safety' : 'Safety') . ($dateStr ? ' · ' . $dateStr : '');
    $footUuid   = 'UUID: ' . $brandName . '-RMAP-' . (16210 + (int) $map->id) . '-'
                . ($map->created_at ? \Carbon\Carbon::parse($map->created_at)->format('dmY') : '')
                . ' | ' . config('crewcare.doc_version');

    $resLabels = RiskMapMarker::RESOURCE_TYPES;
    $inventory = $map->resourceInventory();
    $hazRows   = $map->hazardRows();
    $legend    = $map->legendItems();
    $hazCount  = count($hazRows);
    $resCount  = array_sum($inventory);

    // Hero homologado (_doc-hero): proyecto + locación/fecha; foto de fondo = 1ª vista.
    $heroProject = trim((string) optional($map->scouting)->production_name) ?: $brandName;
    $heroImage   = optional($views->first())->imageUrl() ?: null;
    $heroModule  = $en ? 'Risk & Resource Map' : 'Mapeo de Riesgos';
    $backRoute   = $sealed ? route('riskmaps.index') : route('riskmaps.edit', $map->id);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brandName }} · {{ $heroModule }} · {{ $loc }}</title>
@include('componentes._report-v2-head')
<style>
    /* ===== Contenido propio del Mapeo (scoped). Hereda los tokens del chrome
       compartido (--stroke/--text/--muted/--faint/--panel/--brand/--danger/--mono),
       así el documento tiene las DOS caras: vidrio en pantalla / papel al imprimir. ===== */

    /* Lienzo de la vista: pines ABSOLUTOS por x_pct/y_pct sobre el <img> (sin html2canvas). */
    .rmd-canvas{ position:relative; border:1px solid var(--stroke); border-radius:var(--radius-sm); overflow:hidden; }
    .rmd-canvas>img{ display:block; width:100%; max-height:150mm; object-fit:contain; background:#0b1220; }
    .rmd-leaders{ position:absolute; inset:0; width:100%; height:100%; pointer-events:none; z-index:1; }
    .rmd-pin{ position:absolute; transform:translate(-50%,-100%); z-index:2; }
    .rmd-drop{ width:var(--pin,32px); height:var(--pin,32px); border-radius:50% 50% 50% 0; transform:rotate(-45deg);
        display:flex; align-items:center; justify-content:center; color:#fff;
        box-shadow:0 2px 4px rgba(0,0,0,.4), inset 0 1.5px 1px rgba(255,255,255,.4); border:1.5px solid rgba(255,255,255,.95); }
    .rmd-drop svg{ width:calc(var(--pin,32px)*.62); height:calc(var(--pin,32px)*.62); transform:rotate(45deg); }
    /* Señal a color (ISO/hazmat/EPP/clima): sin gota, sobre PLATE blanco para resaltar. */
    .rmd-sign{ width:calc(var(--pin,32px)*1.35); height:calc(var(--pin,32px)*1.35); display:flex; align-items:center; justify-content:center;
        background:#fff; border-radius:7px; padding:3px; box-sizing:border-box; border:1.5px solid rgba(255,255,255,.95); box-shadow:0 2px 5px rgba(0,0,0,.5); }
    .rmd-sign img{ width:100%; height:100%; object-fit:contain; }
    .rm-sign{ width:100%; height:100%; object-fit:contain; display:block; }
    /* Etiqueta MOVIBLE del marcador (color coherente con la señal: neutra si es señal). */
    .rmd-lbl{ position:absolute; transform:translate(-50%,-50%); z-index:3; max-width:160px; overflow:hidden; text-overflow:ellipsis;
        white-space:nowrap; font-size:9px; font-weight:700; color:#fff; border-radius:6px; padding:2px 7px; line-height:1.3;
        box-shadow:0 1px 2px rgba(0,0,0,.35); text-transform:uppercase; letter-spacing:.02em; }

    /* Narrativa de la vista (el renglón vacío no aparece). */
    .rmd-narr{ margin:11px 0 0; display:grid; gap:5px; }
    .rmd-narr .row{ display:flex; gap:9px; }
    .rmd-narr .k{ flex:none; width:118px; font-weight:700; color:var(--brand); text-transform:uppercase; font-size:9px; letter-spacing:.03em; padding-top:1px; }
    .rmd-narr .v{ font-size:12px; color:var(--text); }

    /* Normas de la vista. */
    .rmd-normas{ margin:10px 0 0; padding:9px 11px; background:var(--panel); border:1px solid var(--stroke); border-radius:9px; }
    .rmd-normas h4{ font-weight:700; text-transform:uppercase; letter-spacing:.05em; font-size:10px; color:var(--danger); margin:0 0 5px; }
    .rmd-normas .item{ font-size:11px; color:var(--muted); margin-bottom:3px; }
    .rmd-normas .ev{ font-weight:700; color:var(--text); }
    .rmd-normas a{ color:var(--brand); word-break:break-all; }

    /* Inventario contado (los ceros se imprimen). */
    .rmd-inv{ display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
    .rmd-inv .cell{ border:1px solid var(--stroke); border-radius:9px; padding:8px 10px; display:flex; align-items:center; gap:9px; background:var(--panel); }
    .rmd-inv .ic{ flex:none; width:30px; height:30px; border-radius:50%; color:var(--brand);
        background:color-mix(in srgb, var(--brand) 12%, transparent); display:flex; align-items:center; justify-content:center; }
    .rmd-inv .ic svg{ width:17px; height:17px; }
    .rmd-inv .ic--sign{ background:transparent!important; padding:0; }
    .rmd-inv .ic img{ width:100%; height:100%; object-fit:contain; }
    .rmd-inv .num{ font-weight:800; font-size:20px; color:var(--text); line-height:1; }
    .rmd-inv .lbl{ font-size:9px; color:var(--faint); text-transform:uppercase; letter-spacing:.02em; }
    .rmd-inv .cell.zero{ opacity:.55; }

    /* Tabla de peligros. */
    table.rmd-haz{ width:100%; border-collapse:collapse; margin-top:2px; }
    table.rmd-haz th, table.rmd-haz td{ text-align:left; padding:6px 8px; border-bottom:1px solid var(--stroke); font-size:11px; vertical-align:top; }
    table.rmd-haz th{ text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-size:9px; }
    table.rmd-haz .ev{ font-weight:700; color:var(--text); }
    table.rmd-haz a{ color:var(--brand); word-break:break-all; }
    .rmd-empty{ color:var(--faint); font-size:11px; padding:6px 2px; }

    /* Simbología. */
    .rmd-legend{ display:grid; grid-template-columns:repeat(3,1fr); gap:7px 16px; }
    .rmd-legend .leg{ display:flex; align-items:center; gap:9px; font-size:11px; color:var(--text); }
    .rmd-legend .leg-ic{ flex:none; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; }
    .rmd-legend .leg-ic svg{ width:13px; height:13px; }
    .rmd-legend .leg-ic--sign{ background:transparent!important; }
    .rmd-legend .leg-ic img{ width:100%; height:100%; object-fit:contain; }

    /* Sello. */
    .rmd-seal{ display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid var(--stroke); border-radius:var(--radius-sm); background:var(--panel); }
    .rmd-seal .qr{ flex:none; width:82px; height:82px; background:#fff; padding:4px; border:1px solid var(--stroke); border-radius:6px; }
    .rmd-seal .qr svg{ width:100%; height:100%; display:block; }
    .rmd-seal .idc{ flex:none; width:48px; height:48px; }
    .rmd-seal .sbody{ flex:1; min-width:0; }
    .rmd-seal .slbl{ font-weight:700; text-transform:uppercase; letter-spacing:.04em; font-size:10px; color:var(--text); }
    .rmd-seal .smeta{ font-size:10px; color:var(--muted); margin-top:2px; }
    .rmd-seal .shash{ font-family:var(--mono); font-size:9px; color:var(--muted); word-break:break-all; line-height:1.35; margin-top:3px; }
    .rmd-seal.bad{ border-left:4px solid var(--danger); }
    .rmd-seal .bad-tag{ color:var(--danger); font-weight:700; }

    /* Cada vista arranca en su propia hoja al imprimir (el hero se repite arriba). */
    @media print{ .rmd-view{ break-before:page; } }
</style>
</head>
<body style="--pin: {{ $map->pinPx() }}px">

@include('componentes._report-v2-toolbar', [
    'backRoute'   => $backRoute,
    'backLabel'   => $sealed ? ($en ? 'Back' : 'Volver') : ($en ? 'Back to editor' : 'Volver al editor'),
    'exportLabel' => $en ? 'Export PDF' : 'Imprimir / PDF',
])

<div class="stage">
  <article class="sheet">
    {{-- Motor de paginación: <thead> (HERO _doc-hero) se REPITE por hoja impresa. --}}
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        'heroImage'    => $heroImage,
        'heroProject'  => $heroProject,
        'heroLocation' => $loc,
        'heroDate'     => $dateStr,
        'heroTime'     => null,
        'heroModule'   => $heroModule,
      ])
      @if(!$sealed)
      <div class="draft-flag">@include('componentes._icon', ['name' => 'alert-triangle']) {{ $en ? 'DRAFT' : 'BORRADOR' }} <span class="n">{{ $en ? 'Not sealed' : 'Sin sellar' }}</span></div>
      @endif
    </td></tr></thead>
    <tbody><tr><td>

    {{-- BANDA DE DATOS (como el DSR): locación + vistas/peligros/recursos. --}}
    <div class="band">
      <div class="lead">
        <span class="ic">@include('componentes._icon', ['name' => 'map-pin'])</span>
        <span class="who">
          <span class="lbl">{{ $en ? 'Location' : 'Locación' }}</span>
          <span class="val">{{ $loc ?: '—' }}</span>
          <span class="sub">{{ $folio }}@if($dateStr) · {{ $dateStr }}@endif</span>
        </span>
      </div>
      <div class="stats">
        <div class="cell"><span class="lbl">{{ $en ? 'Views' : 'Vistas' }}</span><span class="v">{{ $total }}</span></div>
        <div class="cell"><span class="lbl">{{ $en ? 'Hazards' : 'Peligros' }}</span><span class="v {{ $hazCount ? 'warn' : '' }}">{{ $hazCount }}</span></div>
        <div class="cell"><span class="lbl">{{ $en ? 'Resources' : 'Recursos' }}</span><span class="v ok">{{ $resCount }}</span></div>
      </div>
    </div>

    <div class="body">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — {{ $heroModule }} — {{ $loc }}</h1>

      {{-- ============ UNA SECCIÓN POR VISTA ============ --}}
      @foreach($views as $i => $v)
        @php
            $vHaz = [];
            foreach ($v->markers as $m) {
                if ($m->kind === 'hazard' && $m->event_id && ! isset($vHaz[$m->event_id])) {
                    $vHaz[$m->event_id] = $eligibleEvents->get((int) $m->event_id);
                }
            }
        @endphp
        <section class="sec {{ $i > 0 ? 'rmd-view' : '' }}">
          <div class="sec-h"><span class="bar"></span><h2>{{ $en ? 'View' : 'Vista' }} {{ $i + 1 }} · {{ $v->displayLabel() }}</h2><span class="line"></span></div>

          <div class="rmd-canvas">
            @if($v->imageUrl() !== '')
                <img src="{{ $v->imageUrl() }}" alt="{{ $v->displayLabel() }}">
            @endif
            <svg class="rmd-leaders" viewBox="0 0 100 100" preserveAspectRatio="none">
                @foreach($v->markers as $m)
                    @php
                        $lx = $m->label_x_pct !== null ? (float) $m->label_x_pct : max(5, min(95, (float) $m->x_pct + ((float) $m->x_pct > 55 ? -13 : 13)));
                        $ly = $m->label_y_pct !== null ? (float) $m->label_y_pct : max(5, min(95, (float) $m->y_pct - 12));
                        $lIcon = ($m->kind === 'hazard' && ($lev = $eligibleEvents->get((int) $m->event_id))) ? ($lev['icon'] ?? 'haz-warn') : $m->iconKey();
                        $lStroke = RiskSigns::has($lIcon) ? '#334155' : $m->color();
                    @endphp
                    <line x1="{{ $m->x_pct }}" y1="{{ $m->y_pct }}" x2="{{ $lx }}" y2="{{ $ly }}" stroke="{{ $lStroke }}" stroke-width="1.4" vector-effect="non-scaling-stroke"></line>
                @endforeach
            </svg>
            @foreach($v->markers as $m)
                @php
                    if ($m->kind === 'resource') { $mLabel = $m->resourceLabel(); $mShort = $mLabel; $mIcon = $m->iconKey(); }
                    else { $ev = $eligibleEvents->get((int) $m->event_id); $mLabel = $ev['name'] ?? ('#' . $m->event_id); $mShort = $ev['short'] ?? 'Peligro'; $mIcon = $ev['icon'] ?? 'haz-warn'; }
                    $mTitle = $mLabel . ($m->reference_text ? ' — ' . $m->reference_text : '');
                    $mColor = $m->color();
                    $mSign  = RiskSigns::has($mIcon);
                    $lx = $m->label_x_pct !== null ? (float) $m->label_x_pct : max(5, min(95, (float) $m->x_pct + ((float) $m->x_pct > 55 ? -13 : 13)));
                    $ly = $m->label_y_pct !== null ? (float) $m->label_y_pct : max(5, min(95, (float) $m->y_pct - 12));
                @endphp
                <div class="rmd-pin" style="left:{{ $m->x_pct }}%;top:{{ $m->y_pct }}%" title="{{ $mTitle }}">
                    @if($mSign)
                        <div class="rmd-sign">@include('componentes._rm-icon', ['key' => $mIcon, 'class' => ''])</div>
                    @else
                        <div class="rmd-drop" style="background:{{ $mColor }};color:{{ $m->ink() }}">@include('componentes._rm-icon', ['key' => $mIcon, 'class' => ''])</div>
                    @endif
                </div>
                <div class="rmd-lbl" style="left:{{ $lx }}%;top:{{ $ly }}%;background:{{ $mSign ? '#334155' : $mColor }}">{{ $mShort }}</div>
            @endforeach
          </div>

          @if(trim((string) $v->narrative_what) !== '' || trim((string) $v->narrative_decision) !== '' || trim((string) $v->narrative_action) !== '')
          <div class="rmd-narr">
            @if(trim((string) $v->narrative_what) !== '')<div class="row"><span class="k">{{ $en ? 'What' : 'Qué hay' }}</span><span class="v">{{ $v->narrative_what }}</span></div>@endif
            @if(trim((string) $v->narrative_decision) !== '')<div class="row"><span class="k">{{ $en ? 'Decision' : 'Decisión' }}</span><span class="v">{{ $v->narrative_decision }}</span></div>@endif
            @if(trim((string) $v->narrative_action) !== '')<div class="row"><span class="k">{{ $en ? 'Action' : 'Acción' }}</span><span class="v">{{ $v->narrative_action }}</span></div>@endif
          </div>
          @endif

          @if(count($vHaz))
          <div class="rmd-normas">
            <h4>{{ $en ? 'Applicable standards' : 'Normas aplicables' }}</h4>
            @foreach($vHaz as $ev)
              @if($ev)
              <div class="item"><span class="ev">{{ $ev['name'] }}:</span>
                @if(!empty($ev['norms']))@foreach($ev['norms'] as $n){{ $n['code'] }}@if(!empty($n['url'])) — <a href="{{ $n['url'] }}" target="_blank" rel="noopener">{{ $n['url'] }}</a>@endif{{ !$loop->last ? ' · ' : '' }}@endforeach
                @else<span style="color:var(--faint)">{{ $en ? 'No linked standard.' : 'Sin norma ligada.' }}</span>@endif
              </div>
              @endif
            @endforeach
          </div>
          @endif
        </section>
      @endforeach

      {{-- Inventario contado --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ $en ? 'Resource inventory' : 'Inventario de recursos' }}</h2><span class="line"></span></div>
        <div class="rmd-inv">
          @foreach($resLabels as $rt => $lbl)
            @php $c = (int) ($inventory[$rt] ?? 0); @endphp
            <div class="cell {{ $c === 0 ? 'zero' : '' }}">
              <div class="ic {{ RiskSigns::has($rt) ? 'ic--sign' : '' }}">@include('componentes._rm-icon', ['key' => $rt, 'class' => ''])</div>
              <div><div class="num">{{ $c }}</div><div class="lbl">{{ $lbl }}</div></div>
            </div>
          @endforeach
        </div>
      </section>

      {{-- Tabla de peligros --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ $en ? 'Identified hazards' : 'Peligros identificados' }}</h2><span class="line"></span></div>
        @if($hazCount)
        <table class="rmd-haz">
          <thead><tr><th style="width:34%">{{ $en ? 'Event' : 'Evento' }}</th><th style="width:20%">{{ $en ? 'View' : 'Vista' }}</th><th>{{ $en ? 'Standard · link' : 'Norma · liga' }}</th></tr></thead>
          <tbody>
          @foreach($hazRows as $row)
            <tr>
              <td class="ev">{{ $row['event'] }}</td>
              <td>{{ $row['view'] }}</td>
              <td>
                @if(!empty($row['norms']))@foreach($row['norms'] as $n){{ $n['code'] }}@if(!empty($n['url'])) — <a href="{{ $n['url'] }}" target="_blank" rel="noopener">{{ $n['url'] }}</a>@endif{{ !$loop->last ? ' · ' : '' }}@endforeach
                @else<span style="color:var(--faint)">{{ $en ? 'No linked standard.' : 'Sin norma ligada.' }}</span>@endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
        @else
          <div class="rmd-empty">{{ $en ? 'No hazard was marked on the map.' : 'No se marcó ningún peligro en el mapeo.' }}</div>
        @endif
      </section>

      {{-- Simbología --}}
      @if(count($legend))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ $en ? 'Legend' : 'Simbología' }}</h2><span class="line"></span></div>
        <div class="rmd-legend">
          @foreach($legend as $li)
            @php $legSign = RiskSigns::has($li['icon']); @endphp
            <div class="leg"><span class="leg-ic {{ $legSign ? 'leg-ic--sign' : '' }}" @if(!$legSign)style="background:{{ $li['color'] }};color:{{ $li['ink'] ?? '#fff' }}"@endif>@include('componentes._rm-icon', ['key' => $li['icon'], 'class' => ''])</span> {{ $li['label'] }}</div>
          @endforeach
        </div>
      </section>
      @endif

      {{-- Sello digital --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span><h2>{{ $en ? 'Digital seal' : 'Sello digital' }}</h2><span class="line"></span></div>
        <div class="rmd-seal {{ $verdict === false ? 'bad' : '' }}">
          @if($qr)<div class="qr">{!! $qr !!}</div>@endif
          <div class="sbody">
            <div class="slbl">{{ $en ? 'SHA-256 digital seal' : 'Sello digital SHA-256' }}
              @if($verdict === false)<span class="bad-tag">· {{ $en ? 'altered document' : 'documento alterado' }}</span>
              @elseif($verdict === null)<span style="color:var(--faint)">· {{ $en ? 'not sealed' : 'sin sellar' }}</span>@endif
            </div>
            <div class="smeta">{{ $en ? 'Folio' : 'Folio' }} {{ $folio }}@if($sealedAt) · {{ $en ? 'sealed' : 'sellado' }} {{ $sealedAt }}@endif @if($map->uuid && $sig)· {{ $en ? 'verify by scanning the QR' : 'verifica escaneando el QR' }}@endif</div>
            @if($sig)<div class="shash">{{ $sig->document_hash }}</div>@endif
          </div>
          @if($identicon)<div class="idc">{!! $identicon !!}</div>@endif
        </div>
      </section>

    </div>{{-- .body --}}

    </td></tr></tbody>
    </table>
@include('componentes._report-v2-foot', [
    'footPreparedName' => $authorName,
    'footPreparedMeta' => $footMeta,
    'footUuid'         => $footUuid,
])
</body>
</html>
