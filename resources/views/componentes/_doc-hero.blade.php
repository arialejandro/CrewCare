{{--
    HERO homologado de documentos (patrón: Daily Safety Report). Úsalo en las vistas de impresión
    de Acto Inseguro, Condición Insegura y Accidente para que TODAS compartan la MISMA cabecera:
      · logo esmerilado (top-left)
      · nombre del proyecto + locación / fecha / hora (top-right, como el "llamado" del DSR)
      · "CrewCare <Módulo>" (pie del hero) — p.ej. "CrewCare Injury Report"
      · línea naranja de marca.
    NO lleva título de reporte (se eliminó por homologación): el módulo del pie ya lo nombra.

    Requiere @include('componentes._doc-hero-styles') UNA vez en la vista.

    Parámetros:
      $heroImage    (string|null)  foto de fondo (main_image_path)
      $heroProject  (string)       nombre del proyecto  [default: branding.brand_name]
      $heroLocation (string)       locación donde se identificó el evento
      $heroDate     (string|null)  fecha YA formateada (d M Y)
      $heroTime     (string|null)  hora YA formateada (H:i) — opcional
      $heroModule   (string)       etiqueta del módulo (p.ej. __('reports.injury_module'))
      $logoWidth    (int)          opcional (default 250)
--}}
@php
    $heroProject  = ($heroProject ?? null) ?: ($branding['brand_name'] ?? 'PROYECTO');
    $heroLocation = trim((string) ($heroLocation ?? ''));
    $logoWidth    = $logoWidth ?? 250;
    // $logoSrc OPCIONAL: se reenvía al parcial del logo para que los documentos sellados puedan
    // pintar el logo CONGELADO en su payload en vez del vivo de Marca. Null = logo vivo (default).
    $logoSrc      = $logoSrc ?? null;
    // $heroMeta OPCIONAL: sub-línea del "llamado" (tipo Int./Ext. · día/noche · escenas · fecha de
    // rodaje). Sólo la usa hoy el PAE; si no se pasa, no se pinta (retrocompatible con todos los docs).
    $heroMeta     = $heroMeta ?? null;
    // $heroHideCallbox OPCIONAL: oculta el recuadro negro de locación/fecha. Lo usa el acta de
    // ambulancia (la fecha vive en la banda y el hero destaca al proveedor). Default: se muestra.
    $heroHideCallbox = $heroHideCallbox ?? false;
@endphp
<div class="doc-hero">
    @if(!empty($heroImage))
        <img src="{{ $heroImage }}" class="hero-bg" alt="">
    @endif
    <div class="hero-veil"></div>

    {{-- Logo (placa de vidrio esmerilado: backdrop-filter en pantalla; panel esmerilado en print). --}}
    <div class="hero-logo">@include('componentes._doc-hero-logo', ['logoWidth' => $logoWidth, 'logoSrc' => $logoSrc])</div>

    {{-- Nombre del proyecto + cuadro de locación/fecha/hora (auto-ajustados al ancho de la caja) --}}
    <div class="hero-side">
        <div class="hero-project" id="heroProject" style="font-size:46px;">{{ $heroProject }}</div>
        @unless($heroHideCallbox)
        <div class="hero-callbox">
            <div class="cl-loc" id="heroCall">{{ $heroLocation !== '' ? $heroLocation : '—' }}</div>
            <div class="cl-date">{{ $heroDate ?: 'S/F' }}{{ !empty($heroTime) ? ' | ' . $heroTime . ' HRS' : '' }}</div>
            @if(!empty($heroMeta))<div class="cl-meta" style="font-size:11px;color:rgba(255,255,255,.82);margin-top:5px;letter-spacing:.02em;line-height:1.35;">{{ $heroMeta }}</div>@endif
        </div>
        @endunless
    </div>

    {{-- CrewCare + módulo (pie del hero): blanco esmerilado + esquinas superiores redondeadas + negro. --}}
    <div class="hero-brand">
        <div class="cc"><span class="crew">Crew</span><span class="care">Care</span></div>
        <div class="mod">{{ $heroModule ?? '' }}</div>
    </div>

    <div class="hero-orange"></div>
</div>
