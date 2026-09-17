{{-- ============================================================================================
     TECH SCOUT — DOCUMENTO. Standalone (sin layout), mismo chrome v2 que el resto de reportes.
     Ruta: GET /tech-scout/{id}/documento  ·  ?pdf=1 lo baja por Browsershot (Chrome headless).

     ESTE es el entregable que sustituye el Word: hoy los scouters descargan las fotos, las pegan
     a mano y reescriben lo de sus libretas. Aquí sale en un clic, con las notas en el orden en que
     se caminó la locación.

     ── DECISIONES DEL OWNER QUE NO SE TOCAN ──────────────────────────────────────────────────
     · El pie lo firma **Locaciones**, el DEPARTAMENTO, no una persona. Se omite la línea de
       puesto/rol que sí llevan los reportes de H&S: aquí no aporta. Quedan el UUID y el
       "Powered by CrewCare" — es un documento generado en CrewCare y debe verse.
     · NO se imprime quién puso cada nota. El autor es dato INTERNO: en la app sirve para que los
       scouters se entiendan entre ellos; a arte le llega el documento del departamento.
     · La fecha y hora van DEBAJO de la foto, discretas, NUNCA superpuestas. Encimar algo sobre la
       imagen estropearía justo lo que este documento existe para mostrar.
     · Orden CRONOLÓGICO: es el del recorrido, y el único que vale igual en una casa que en una
       bodega. Se descartó agrupar por área física por eso mismo.
============================================================================================ --}}
@php
    if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); }
    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = $brand['brand_name'] ?? 'CrewCare';
    $primary   = $brand['primary_color'] ?? '#ff9900';

    $notas = $scout->notes;
    $desde = $notas->first() ? $notas->first()->created_at : $scout->created_at;
    $hasta = $notas->last()  ? $notas->last()->created_at  : null;

    // Rango del recorrido para la cabecera. Si empezó y terminó el mismo día, una sola fecha.
    $heroDate = $desde->translatedFormat('d M Y');
    if ($hasta && ! $hasta->isSameDay($desde)) {
        $heroDate = $desde->translatedFormat('d M') . ' – ' . $hasta->translatedFormat('d M Y');
    }
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brandName }} · Tech Scout · {{ $scout->location_name }}</title>
@include('componentes._report-v2-head')
<style>
  /* Sólo lo propio del Tech Scout. El motor de impresión vive en _report-v2-head. */

  /* Una nota = foto + lo que hay que resolver. La foto manda: ancho generoso y SIN recorte —
     es evidencia de lo que hay que cambiar, no decoración. */
  .tsn{display:flex;gap:14px;padding:12px 0;border-bottom:1px solid var(--stroke);break-inside:avoid;page-break-inside:avoid}
  .tsn:last-child{border-bottom:0}
  .tsn-ph{flex:0 0 46%;max-width:46%}
  .tsn-ph img{width:100%;height:auto;display:block;border-radius:6px;border:1px solid var(--stroke)}
  .tsn-bd{flex:1 1 auto;min-width:0;display:flex;flex-direction:column}
  .tsn-tag{align-self:flex-start;font-size:.6rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;
    color:var(--brand);border:1px solid var(--brand);border-radius:999px;padding:2px 8px;margin-bottom:6px}
  .tsn-tx{font-size:.86rem;line-height:1.55;color:var(--text);white-space:pre-wrap;margin:0}
  /* Hora: pequeña, gris y ABAJO. Buena práctica de trazabilidad, pero no puede competir con la foto. */
  .tsn-when{margin-top:auto;padding-top:8px;font-size:.6rem;color:var(--faint);letter-spacing:.04em}
  .tsn-when .ed{font-style:italic}

  .tsn-none{padding:26px 0;text-align:center;color:var(--muted);font-size:.85rem}

  :root[data-view="print"] .tsn{border-bottom-color:rgba(0,0,0,.12)}
  :root[data-view="print"] .tsn-ph img{border-color:rgba(0,0,0,.15)}
  :root[data-view="print"] .tsn-tx{color:#111}
  :root[data-view="print"] .tsn-when{color:#666}
</style>
</head>
<body>

@include('componentes._report-v2-toolbar', ['backRoute' => route('techscout.show', $scout->id)])

<div class="stage">
  <article class="sheet">

    {{-- Motor de paginación: el <thead> se repite en cada hoja impresa. --}}
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        {{-- Portada ELEGIDA a mano (no la primera nota): la banda es ancha y baja, así que una foto
             se ve como una franja. Quien arma el documento decide cuál aguanta ese encuadre —
             normalmente un plano general de la locación. Sin portada, la banda queda con la marca
             sola, que también es una salida digna. --}}
        'heroImage'       => $scout->hero_image_path,
        'heroProject'     => $brandName,
        'heroLocation'    => $scout->location_name,
        'heroDate'        => $heroDate,
        'heroTime'        => null,
        'heroMeta'        => $scout->location_address ?: null,
        'heroHideCallLoc' => true,
        'heroModule'      => 'Tech Scout',
      ])
    </td></tr></thead>
    <tbody><tr><td>

      {{-- ── DATOS DEL RECORRIDO ── Mismos campos y nombres que el Scouting H&S: quien lee los dos
           documentos no debería tener que traducir. Sólo se imprime lo que está lleno — un dato
           vacío en un documento que va a arte es ruido. --}}
      @php
        $general = array_filter([
            'Producción'        => $scout->production_type,
            'Gerente de prod.'  => $scout->manager_name,
            'Tipo de locación'  => $scout->loc_setting,
            'Horario'           => $scout->shoot_time,
            'Prep'              => optional($scout->date_prep)->format('d/m/Y'),
            'Rodaje'            => $scout->date_shoot
                ? ($scout->hasShootRange()
                    ? $scout->date_shoot->format('d/m/Y') . ' – ' . $scout->date_shoot_end->format('d/m/Y')
                    : $scout->date_shoot->format('d/m/Y'))
                : null,
            'Wrap'              => optional($scout->date_wrap)->format('d/m/Y'),
            'Dirección'         => $scout->location_address,
        ]);
        $viab = $scout->rows('viability_checklist');
        $agr  = $scout->rows('agreements');
      @endphp

      @if ($general)
        <div class="sec-h"><span class="bar"></span><h2>Datos del recorrido</h2></div>
        <table class="tbl" style="margin-bottom:14px">
          <tbody>
            @foreach ($general as $k => $v)
              <tr><th style="width:26%">{{ $k }}</th><td>{{ $v }}</td></tr>
            @endforeach
          </tbody>
        </table>
      @endif

      @if ($viab)
        <div class="sec-h"><span class="bar"></span><h2>Viabilidad</h2></div>
        <table class="tbl" style="margin-bottom:14px">
          <thead><tr><th style="width:32%">Permiso / gestión</th><th>Detalle</th></tr></thead>
          <tbody>
            @foreach ($viab as $r)<tr><td>{{ $r['item'] }}</td><td>{{ $r['detail'] }}</td></tr>@endforeach
          </tbody>
        </table>
      @endif

      @if ($agr)
        <div class="sec-h"><span class="bar"></span><h2>Acuerdos</h2></div>
        <table class="tbl" style="margin-bottom:14px">
          <thead><tr><th style="width:32%">Con quién / qué</th><th>Qué se acordó</th></tr></thead>
          <tbody>
            @foreach ($agr as $r)<tr><td>{{ $r['item'] }}</td><td>{{ $r['detail'] }}</td></tr>@endforeach
          </tbody>
        </table>
      @endif

      <div class="sec-h">
        <span class="bar"></span>
        <h2>Recorrido · {{ $notas->count() }} {{ $notas->count() === 1 ? 'nota' : 'notas' }}</h2>
      </div>

      @if (! $notas->count())
        <div class="tsn-none">Este recorrido todavía no tiene notas.</div>
      @else
        @foreach ($notas as $n)
          <div class="tsn">
            @if ($n->photo_path)
              <div class="tsn-ph"><img src="{{ $n->photo_path }}" alt="" data-hide-on-error></div>
            @endif
            <div class="tsn-bd">
              @if ($n->story_label)<span class="tsn-tag">{{ $n->story_label }}</span>@endif
              @if ($n->note)<p class="tsn-tx">{{ $n->note }}</p>@endif
              {{-- Quién la puso NO se imprime: el documento lo firma el departamento. --}}
              <div class="tsn-when">
                {{ $n->created_at->translatedFormat('d M Y · H:i') }}
                @if ($n->wasEdited())<span class="ed">· editada {{ $n->edited_at->translatedFormat('d M Y · H:i') }}</span>@endif
              </div>
            </div>
          </div>
        @endforeach
      @endif

    </td></tr></tbody>
    </table>

    @include('componentes._report-v2-foot', [
      {{-- El DEPARTAMENTO, no la persona (decisión del owner). Y sin línea de puesto/rol: la que
           llevan los reportes de H&S aquí no aporta nada. Quedan UUID y "Powered by CrewCare". --}}
      'footPreparedName' => 'Locaciones',
      'footPreparedMeta' => '',
      'footUuid'         => 'UUID: ' . ($scout->uuid ?: '—'),
    ])

</body>
</html>
