{{-- ============================================================================
     MAPEO DE RIESGOS Y RECURSOS — documento (delta #50). Carta vertical, UNA hoja
     por vista + una página final derivada. Pines = divs ABSOLUTOS por x_pct/y_pct
     sobre el <img> (sin html2canvas: lo posiciona el servidor y lo imprime el
     navegador). El sello SHA se calcula sobre el DATO, no sobre este render.
============================================================================ --}}
@php
    use App\Support\SealVerifier;
    use App\Support\Branding;
    use App\Models\RiskMapMarker;

    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = $brand['brand_name'] ?? 'CrewCare';
    $primary   = $brand['primary_color'] ?? '#c62828';
    $ink       = $brand['secondary_color'] ?? '#12233b';

    $logo    = Branding::documentLogo();
    $company = trim((string) (Branding::get('company_name', '') ?? '')) ?: $brandName;

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
    $authorId     = $map->sealed_by ?: $map->created_by_id;
    $authorName   = trim((string) optional(\App\Models\User::find($authorId))->name) ?: '—';
    $footMeta     = 'Safety' . ($dateStr ? ' · ' . $dateStr : '');
    $footUuid     = 'UUID: ' . $brandName . '-RMAP-' . (16210 + (int) $map->id) . '-'
                  . ($map->created_at ? \Carbon\Carbon::parse($map->created_at)->format('dmY') : '')
                  . ' | ' . config('crewcare.doc_version');

    $resLabels = RiskMapMarker::RESOURCE_TYPES;
    $inventory = $map->resourceInventory();
    $hazRows   = $map->hazardRows();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brandName }} · Mapeo de riesgos · {{ $loc }}</title>
<link rel="stylesheet" href="/fonts/reports/report-fonts.css">
<style>
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSC-Regular.ttf') format('truetype'); font-weight:400; font-style:normal; font-display:swap; }
    :root{
        --rmr-primary: {{ $primary }};
        --rmr-ink: {{ $ink }};
        --rmr-wordmark:#14181f; --rmr-line:#d7dde5; --rmr-muted:#5b6472; --rmr-title:#8a909b;
        --rmr-res:#0e7a3d; --rmr-haz:#c0392b; --rmr-area:#2c6fbf;
        --rmr-font:'Poppins',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
        --rmr-cond:'Roboto Condensed',var(--rmr-font);
        --rmr-mono:'Roboto Mono',ui-monospace,Consolas,monospace;
        --rmr-brand:'Aspire SC',var(--rmr-cond);
    }
    *{ box-sizing:border-box; }
    html,body{ margin:0; padding:0; }
    body{ background:#e9edf1; color:#1a1f2b; font-family:var(--rmr-font); font-size:11px; line-height:1.45;
        -webkit-print-color-adjust:exact; print-color-adjust:exact; }

    .rmr-toolbar{ position:sticky; top:0; z-index:5; display:flex; gap:10px; align-items:center;
        padding:10px 14px; background:#0e1726; }
    .rmr-toolbar a, .rmr-toolbar button{ font:inherit; font-size:12px; font-weight:600; cursor:pointer;
        border:1px solid rgba(255,255,255,.25); background:transparent; color:#e8edf4; border-radius:8px;
        padding:7px 12px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .rmr-toolbar button.primary{ background:var(--rmr-primary); border-color:var(--rmr-primary); color:#fff; }
    .rmr-toolbar .sp{ flex:1; }

    .rmr-sheet{ position:relative; width:216mm; min-height:279mm; margin:14px auto; background:#fff;
        color:#1a1f2b; padding:12mm 12mm 10mm; box-shadow:0 10px 30px rgba(10,20,40,.18); }
    .rmr-sheet + .rmr-sheet{ margin-top:22px; }

    /* Cabecera compacta (cada hoja se identifica sola) */
    .rmr-head{ display:flex; justify-content:space-between; align-items:center; gap:14px;
        border-bottom:1px solid var(--rmr-line); padding-bottom:9px; }
    .rmr-head .hd-l{ display:flex; align-items:center; gap:11px; min-width:0; }
    .rmr-head .hd-l img{ max-height:34px; max-width:150px; display:block; }
    .rmr-head .hd-l .co{ font-family:var(--rmr-cond); font-weight:700; font-size:15px; color:var(--rmr-ink); text-transform:uppercase; }
    .rmr-head .ttl{ font-family:var(--rmr-cond); font-weight:700; text-transform:uppercase; letter-spacing:.1em;
        font-size:13px; color:var(--rmr-wordmark); text-align:center; }
    .rmr-head .hd-r{ text-align:right; }
    .rmr-head .hd-r .loc{ font-weight:700; font-size:12px; color:var(--rmr-ink); }
    .rmr-head .hd-r .sub{ font-size:8.5px; color:var(--rmr-muted); letter-spacing:.03em; text-transform:uppercase; }

    /* Chip de la vista */
    .rmr-chip{ display:inline-flex; align-items:center; gap:7px; margin:11px 0 8px; padding:5px 12px;
        border-radius:999px; background:#f2f5f8; border:1px solid var(--rmr-line);
        font-family:var(--rmr-cond); font-weight:700; text-transform:uppercase; letter-spacing:.06em;
        font-size:12px; color:var(--rmr-ink); }
    .rmr-chip .n{ color:var(--rmr-muted); font-weight:600; }

    /* Lienzo con pines absolutos */
    .rmr-canvas{ position:relative; border:1px solid var(--rmr-line); border-radius:3px; overflow:hidden; }
    .rmr-canvas img{ display:block; width:100%; max-height:150mm; object-fit:contain; background:#0b1220; }
    .rmr-pin{ position:absolute; transform:translate(-50%,-100%); z-index:2; }
    .rmr-pin__drop{ width:26px; height:26px; border-radius:50% 50% 50% 0; transform:rotate(-45deg);
        display:flex; align-items:center; justify-content:center; color:#fff;
        box-shadow:0 1px 3px rgba(0,0,0,.4); border:1.5px solid rgba(255,255,255,.9); }
    .rmr-pin__drop svg{ width:15px; height:15px; transform:rotate(45deg); }
    .rmr-pin--resource .rmr-pin__drop{ background:var(--rmr-res); }
    .rmr-pin--hazard   .rmr-pin__drop{ background:var(--rmr-haz); }
    .rmr-pin--area     .rmr-pin__drop{ background:var(--rmr-area); }
    .rmr-pin__chip{ position:absolute; bottom:12px; white-space:nowrap; font-size:9.5px; font-weight:700;
        color:#12233b; background:rgba(255,255,255,.92); border:1px solid var(--rmr-line);
        border-radius:6px; padding:2px 6px; line-height:1.25; box-shadow:0 1px 2px rgba(0,0,0,.15); }
    .rmr-pin__chip .ref{ display:block; font-weight:500; color:var(--rmr-muted); font-size:8.5px; }
    .rmr-pin__chip--right{ left:16px; }
    .rmr-pin__chip--left{ right:16px; text-align:right; }

    /* Narrativa (tres renglones; el vacío no aparece) */
    .rmr-narr{ margin:11px 0 0; display:grid; gap:5px; }
    .rmr-narr .row{ display:flex; gap:9px; }
    .rmr-narr .k{ flex:none; width:120px; font-weight:700; color:var(--rmr-primary); text-transform:uppercase;
        font-size:9px; letter-spacing:.03em; padding-top:1px; }
    .rmr-narr .v{ font-size:11px; color:var(--rmr-wordmark); }

    /* Normas de la vista */
    .rmr-normas{ margin:10px 0 0; padding:8px 10px; background:#f7f9fb; border:1px solid var(--rmr-line); border-radius:3px; }
    .rmr-normas h4{ font-family:var(--rmr-cond); font-weight:700; text-transform:uppercase; letter-spacing:.05em;
        font-size:10px; color:var(--rmr-haz); margin:0 0 5px; }
    .rmr-normas .item{ font-size:10px; color:#26303f; margin-bottom:3px; }
    .rmr-normas .ev{ font-weight:700; }
    .rmr-normas a{ color:var(--rmr-area); word-break:break-all; }

    /* Página final */
    .rmr-sec{ font-family:var(--rmr-cond); font-weight:700; text-transform:uppercase; letter-spacing:.05em;
        color:var(--rmr-wordmark); font-size:15px; margin:16px 0 9px; }
    .rmr-inv{ display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
    .rmr-inv .cell{ border:1px solid var(--rmr-line); border-radius:4px; padding:8px 10px; display:flex; align-items:center; gap:9px; }
    .rmr-inv .ic{ flex:none; width:30px; height:30px; border-radius:50%; background:color-mix(in srgb,var(--rmr-res) 12%,#fff);
        color:var(--rmr-res); display:flex; align-items:center; justify-content:center; }
    .rmr-inv .ic svg{ width:17px; height:17px; }
    .rmr-inv .num{ font-family:var(--rmr-cond); font-weight:800; font-size:20px; color:var(--rmr-ink); line-height:1; }
    .rmr-inv .lbl{ font-size:9px; color:var(--rmr-muted); text-transform:uppercase; letter-spacing:.02em; }
    .rmr-inv .cell.zero{ opacity:.6; }
    .rmr-inv .cell.zero .ic{ background:#f1f3f5; color:#9aa3b1; }

    table.rmr-haz{ width:100%; border-collapse:collapse; margin-top:2px; }
    table.rmr-haz th, table.rmr-haz td{ text-align:left; padding:6px 8px; border-bottom:1px solid var(--rmr-line); font-size:10px; vertical-align:top; }
    table.rmr-haz th{ font-family:var(--rmr-cond); text-transform:uppercase; letter-spacing:.04em; color:var(--rmr-muted); font-size:9px; }
    table.rmr-haz .ev{ font-weight:700; color:var(--rmr-wordmark); }
    table.rmr-haz a{ color:var(--rmr-area); word-break:break-all; }
    .rmr-empty{ color:#9aa3b1; font-size:10px; padding:6px 2px; }

    /* Sello */
    .rmr-seal{ display:flex; align-items:center; gap:12px; margin-top:14px; padding:10px 12px;
        border:1px solid var(--rmr-line); border-radius:3px; background:#f7f9fb; }
    .rmr-seal .qr{ flex:none; width:78px; height:78px; background:#fff; padding:3px; border:1px solid var(--rmr-line); border-radius:3px; }
    .rmr-seal .qr svg{ width:100%; height:100%; display:block; }
    .rmr-seal .idc{ flex:none; width:48px; height:48px; }
    .rmr-seal .sbody{ flex:1; min-width:0; }
    .rmr-seal .slbl{ font-weight:700; text-transform:uppercase; letter-spacing:.04em; font-size:9.5px; color:var(--rmr-ink); }
    .rmr-seal .smeta{ font-size:9px; color:var(--rmr-muted); }
    .rmr-seal .shash{ font-family:var(--rmr-mono); font-size:8.5px; color:#26303f; word-break:break-all; line-height:1.35; margin-top:2px; }
    .rmr-seal.bad{ border-left:5px solid var(--rmr-haz); }
    .rmr-seal .bad-tag{ color:var(--rmr-haz); font-weight:700; }

    .rmr-foot{ display:flex; justify-content:space-between; align-items:flex-end; gap:20px;
        margin-top:11px; border-top:1px solid var(--rmr-line); padding-top:10px; }
    .rmr-foot .ft-r{ text-align:right; }
    .rmr-foot .ft-lbl{ font-size:6px; text-transform:uppercase; letter-spacing:.22em; font-weight:600; color:#aab1bb; margin-bottom:3px; }
    .rmr-foot .ft-name{ font-weight:600; text-transform:uppercase; font-size:10px; letter-spacing:.04em; color:#9aa3af; line-height:1.1; }
    .rmr-foot .ft-meta{ font-size:9px; color:#aab1bb; margin-top:2px; }
    .rmr-foot .cc{ font-family:var(--rmr-brand); text-transform:uppercase; letter-spacing:.08em; font-size:11.5px; line-height:1; color:#9aa3af; }
    .rmr-foot .ft-uuid{ font-family:var(--rmr-mono); font-size:8px; color:#aab1bb; margin-top:4px; }

    .rmr-pagefoot{ margin-top:12px; padding-top:7px; border-top:1px solid var(--rmr-line);
        display:flex; justify-content:space-between; font-size:8.5px; color:#9aa3b1; letter-spacing:.03em; }

    /* Marca de borrador */
    .is-draft::before{ content:'BORRADOR'; position:absolute; top:44%; left:50%;
        transform:translate(-50%,-50%) rotate(-24deg); font-family:var(--rmr-cond); font-weight:800;
        font-size:96px; letter-spacing:.1em; color:rgba(192,57,43,.08); pointer-events:none; z-index:0; }

    @media print{
        @page{ size: letter portrait; margin: 8mm; }
        html,body{ background:#fff; }
        .rmr-toolbar{ display:none !important; }
        .rmr-sheet{ width:auto; min-height:0; margin:0; padding:0; box-shadow:none; }
        .rmr-sheet + .rmr-sheet{ break-before:page; margin-top:0; }
    }
</style>
</head>
<body>

<div class="rmr-toolbar">
    @if($sealed)
        <a href="{{ route('riskmaps.index') }}">← Volver</a>
    @else
        <a href="{{ route('riskmaps.edit', $map->id) }}">← Volver al editor</a>
    @endif
    <span class="sp"></span>
    <button type="button" class="primary" onclick="window.print()">Imprimir / PDF</button>
</div>

@php $pageHead = false; @endphp

{{-- ============ UNA HOJA POR VISTA ============ --}}
@foreach($views as $i => $v)
    @php
        // Peligros de esta vista (para su lista de normas).
        $vHaz = [];
        foreach ($v->markers as $m) {
            if ($m->kind === 'hazard' && $m->event_id && ! isset($vHaz[$m->event_id])) {
                $vHaz[$m->event_id] = $eligibleEvents->get((int) $m->event_id);
            }
        }
    @endphp
    <div class="rmr-sheet {{ $sealed ? '' : 'is-draft' }}">
        <div class="rmr-head">
            <div class="hd-l">
                @if($logo !== '')<img src="{{ $logo }}" alt="{{ $company }}" onerror="this.style.display='none'">@else<span class="co">{{ $company }}</span>@endif
            </div>
            <div class="ttl">Mapeo de riesgos<br>y recursos</div>
            <div class="hd-r">
                <div class="loc">{{ $loc }}</div>
                <div class="sub">{{ $folio }}@if($dateStr) · {{ $dateStr }}@endif</div>
            </div>
        </div>

        <div class="rmr-chip">@include('componentes._rm-icon', ['key' => $v->view_type, 'class' => '']) {{ $v->displayLabel() }} <span class="n">· Vista {{ $i + 1 }} de {{ $total }}</span></div>

        <div class="rmr-canvas">
            @if($v->imageUrl() !== '')
                <img src="{{ $v->imageUrl() }}" alt="{{ $v->displayLabel() }}">
            @endif
            @foreach($v->markers as $m)
                @php
                    if ($m->kind === 'resource') { $mLabel = $m->resourceLabel(); }
                    else { $ev = $eligibleEvents->get((int) $m->event_id); $mLabel = $ev['name'] ?? ('#' . $m->event_id); }
                    $side = $m->label_side === 'left' ? 'left' : 'right';
                @endphp
                <div class="rmr-pin rmr-pin--{{ $m->kind }}" style="left:{{ $m->x_pct }}%;top:{{ $m->y_pct }}%">
                    <div class="rmr-pin__drop">@include('componentes._rm-icon', ['key' => $m->iconKey(), 'class' => ''])</div>
                    <div class="rmr-pin__chip rmr-pin__chip--{{ $side }}">{{ $mLabel }}@if($m->reference_text)<span class="ref">{{ $m->reference_text }}</span>@endif</div>
                </div>
            @endforeach
        </div>

        {{-- Narrativa: los tres renglones; el vacío no aparece --}}
        @if(trim((string)$v->narrative_what) !== '' || trim((string)$v->narrative_decision) !== '' || trim((string)$v->narrative_action) !== '')
        <div class="rmr-narr">
            @if(trim((string)$v->narrative_what) !== '')<div class="row"><div class="k">Qué hay aquí</div><div class="v">{{ $v->narrative_what }}</div></div>@endif
            @if(trim((string)$v->narrative_decision) !== '')<div class="row"><div class="k">Qué se decidió</div><div class="v">{{ $v->narrative_decision }}</div></div>@endif
            @if(trim((string)$v->narrative_action) !== '')<div class="row"><div class="k">Qué debe hacer el crew</div><div class="v">{{ $v->narrative_action }}</div></div>@endif
        </div>
        @endif

        {{-- Normas de los peligros de esta vista --}}
        @if(count($vHaz))
        <div class="rmr-normas">
            <h4>Normas aplicables</h4>
            @foreach($vHaz as $eid => $ev)
                <div class="item">
                    <span class="ev">{{ $ev['name'] ?? ('#' . $eid) }}:</span>
                    @if(!empty($ev['norms']))
                        @foreach($ev['norms'] as $n)
                            {{ $n['code'] }}@if(!empty($n['url'])) <a href="{{ $n['url'] }}" target="_blank" rel="noopener">{{ $n['url'] }}</a>@endif{{ !$loop->last ? ' · ' : '' }}
                        @endforeach
                    @else
                        <span style="color:#9aa3b1">Sin norma ligada.</span>
                    @endif
                </div>
            @endforeach
        </div>
        @endif

        <div class="rmr-pagefoot"><span>{{ $folio }} · {{ $loc }}</span><span>Vista {{ $i + 1 }} / {{ $total }}</span></div>
    </div>
@endforeach

{{-- ============ PÁGINA FINAL (derivada) ============ --}}
<div class="rmr-sheet {{ $sealed ? '' : 'is-draft' }}">
    <div class="rmr-head">
        <div class="hd-l">
            @if($logo !== '')<img src="{{ $logo }}" alt="{{ $company }}" onerror="this.style.display='none'">@else<span class="co">{{ $company }}</span>@endif
        </div>
        <div class="ttl">Mapeo de riesgos<br>y recursos</div>
        <div class="hd-r">
            <div class="loc">{{ $loc }}</div>
            <div class="sub">{{ $folio }}@if($dateStr) · {{ $dateStr }}@endif</div>
        </div>
    </div>

    {{-- Inventario contado (los ceros se imprimen) --}}
    <div class="rmr-sec">Inventario de recursos</div>
    <div class="rmr-inv">
        @foreach($resLabels as $rt => $lbl)
            @php $c = (int) ($inventory[$rt] ?? 0); @endphp
            <div class="cell {{ $c === 0 ? 'zero' : '' }}">
                <div class="ic">@include('componentes._rm-icon', ['key' => $rt, 'class' => ''])</div>
                <div><div class="num">{{ $c }}</div><div class="lbl">{{ $lbl }}</div></div>
            </div>
        @endforeach
    </div>

    {{-- Tabla de peligros --}}
    <div class="rmr-sec">Peligros identificados</div>
    @if(count($hazRows))
    <table class="rmr-haz">
        <thead><tr><th style="width:34%">Evento</th><th style="width:20%">Vista</th><th>Norma · liga</th></tr></thead>
        <tbody>
        @foreach($hazRows as $row)
            <tr>
                <td class="ev">{{ $row['event'] }}</td>
                <td>{{ $row['view'] }}</td>
                <td>
                    @if(!empty($row['norms']))
                        @foreach($row['norms'] as $n)
                            {{ $n['code'] }}@if(!empty($n['url'])) — <a href="{{ $n['url'] }}" target="_blank" rel="noopener">{{ $n['url'] }}</a>@endif{{ !$loop->last ? ' · ' : '' }}
                        @endforeach
                    @else
                        <span style="color:#9aa3b1">Sin norma ligada.</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @else
        <div class="rmr-empty">No se marcó ningún peligro en el mapeo.</div>
    @endif

    {{-- Sello --}}
    <div class="rmr-seal {{ $verdict === false ? 'bad' : '' }}">
        @if($qr)<div class="qr">{!! $qr !!}</div>@endif
        <div class="sbody">
            <div class="slbl">Sello digital SHA-256
                @if($verdict === false)<span class="bad-tag">· documento alterado</span>
                @elseif($verdict === null)<span style="color:#9aa3b1">· sin sellar</span>@endif
            </div>
            <div class="smeta">Folio {{ $folio }}@if($sealedAt) · sellado {{ $sealedAt }}@endif@if($map->uuid && $sig) · verifica escaneando el QR@endif</div>
            @if($sig)<div class="shash">{{ $sig->document_hash }}</div>@endif
        </div>
        @if($identicon)<div class="idc">{!! $identicon !!}</div>@endif
    </div>

    <footer class="rmr-foot">
        @include('componentes._brand-foot-inner', ['ftName' => $authorName, 'ftMeta' => $footMeta, 'ftUuid' => $footUuid])
    </footer>
</div>

</body>
</html>
