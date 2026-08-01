{{--
    _wrap-chart.blade.php — GRÁFICAS DEL REPORTE DE WRAP, en SVG puro (2026-07-24).

    POR QUÉ SIN LIBRERÍA. La app ya carga Chart.js, pero por CDN: en un documento que se imprime y
    que debe verse completo SIN INTERNET, eso es una gráfica en blanco. Autoalojarlo sería traer
    ~200 KB para dibujar barras y una línea, y además canvas obliga a apagar la animación y a
    asegurarse de que terminó de pintar antes del print — un canvas a medio dibujar se imprime a
    medias, en silencio. El SVG lo pinta el navegador con el resto del documento: si el HTML llegó,
    la gráfica llegó. Mismo criterio con el que el identicon del sello se dibujó a mano.

    Las escalas se calculan AQUÍ, en PHP: no hay una sola línea de JS y el documento imprime igual
    con JavaScript desactivado.

    Parámetros:
      $titulo   (string)  encabezado de la gráfica.
      $tipo     (string)  'barras' (agrupadas) | 'lineas' (acumuladas) | 'barrasH' (horizontales).
      $ejeX     (array)   etiquetas del eje X (o de cada barra en 'barrasH').
      $series   (array)   [['label'=>..,'clase'=>'s1'..'s4','valores'=>[int,…]], …]
      $nota     (string)  opcional, pie de la gráfica.
      $alto     (int)     alto del viewBox (default 200).
--}}
@php
    $chTitulo = isset($titulo) ? $titulo : '';
    $chTipo   = isset($tipo) ? $tipo : 'barras';
    $chEjeX   = isset($ejeX) && is_array($ejeX) ? array_values($ejeX) : [];
    $chSeries = isset($series) && is_array($series) ? array_values($series) : [];
    $chNota   = isset($nota) ? $nota : null;
    $chAlto   = isset($alto) ? (int) $alto : 200;

    // Lienzo. Ancho fijo + viewBox: el SVG escala al contenedor y en papel sale vectorial, no
    // rasterizado — una gráfica de mapa de bits impresa a 300 ppp se ve borrosa.
    $chW = 720;
    $chH = $chAlto;
    $mL = 34; $mR = 8; $mT = 12; $mB = 30;
    $plotW = $chW - $mL - $mR;
    $plotH = $chH - $mT - $mB;

    // Máximo del eje Y. Nunca 0: dividir entre cero deja las barras en NaN y el SVG sale vacío
    // sin avisar de nada.
    $chMax = 0;
    foreach ($chSeries as $s) {
        foreach ((array) $s['valores'] as $v) { $chMax = max($chMax, (int) $v); }
    }
    if ($chMax <= 0) { $chMax = 1; }
    // Redondeo del techo a un número legible, para que las líneas guía caigan en enteros.
    $chPaso = $chMax <= 4 ? 1 : ($chMax <= 10 ? 2 : ($chMax <= 30 ? 5 : ($chMax <= 60 ? 10 : 25)));
    $chTecho = (int) (ceil($chMax / $chPaso) * $chPaso);

    $chN = count($chEjeX);
    // Con muchos días no cabe una etiqueta por barra: se rotula 1 de cada K. Truncar la serie
    // escondería días; lo que se ralea es el RÓTULO, no el dato.
    $chCadaK = $chN > 24 ? 4 : ($chN > 12 ? 2 : 1);
@endphp

<div class="wchart">
  @if($chTitulo !== '')<div class="wchart-h">{{ $chTitulo }}</div>@endif

  @if($chN === 0)
    <div class="wchart-empty">Sin datos en el periodo.</div>
  @else
  <svg class="wchart-svg" viewBox="0 0 {{ $chW }} {{ $chH }}" preserveAspectRatio="xMidYMid meet"
       role="img" aria-label="{{ $chTitulo }}">
    {{-- Guías horizontales + escala. --}}
    @for($g = 0; $g <= $chTecho; $g += $chPaso)
      @php $gy = $mT + $plotH - ($g / $chTecho) * $plotH; @endphp
      <line class="wc-grid" x1="{{ $mL }}" y1="{{ round($gy, 1) }}" x2="{{ $mL + $plotW }}" y2="{{ round($gy, 1) }}"/>
      <text class="wc-axis" x="{{ $mL - 6 }}" y="{{ round($gy + 3.5, 1) }}" text-anchor="end">{{ $g }}</text>
    @endfor

    @if($chTipo === 'lineas')
      @foreach($chSeries as $si => $s)
        @php
          $pts = [];
          foreach (array_values((array) $s['valores']) as $i => $v) {
              $x = $mL + ($chN > 1 ? ($i / ($chN - 1)) * $plotW : $plotW / 2);
              $y = $mT + $plotH - (((int) $v) / $chTecho) * $plotH;
              $pts[] = round($x, 1) . ',' . round($y, 1);
          }
        @endphp
        <polyline class="wc-line wc-{{ $s['clase'] }}" points="{{ implode(' ', $pts) }}"/>
        @foreach($pts as $pt)
          @php list($px, $py) = explode(',', $pt); @endphp
          <circle class="wc-dot wc-{{ $s['clase'] }}" cx="{{ $px }}" cy="{{ $py }}" r="2.4"/>
        @endforeach
      @endforeach

    @else
      {{-- Barras agrupadas: cada día reserva su ranura y las series se reparten dentro. --}}
      @php
        $slot = $plotW / max(1, $chN);
        $nS = max(1, count($chSeries));
        $bw = max(1.5, ($slot * 0.72) / $nS);
      @endphp
      @foreach($chSeries as $si => $s)
        @foreach(array_values((array) $s['valores']) as $i => $v)
          @php
            $v = (int) $v;
            $h = ($v / $chTecho) * $plotH;
            $x = $mL + $i * $slot + ($slot - $bw * $nS) / 2 + $si * $bw;
            $y = $mT + $plotH - $h;
          @endphp
          @if($v > 0)
          <rect class="wc-bar wc-{{ $s['clase'] }}" x="{{ round($x, 1) }}" y="{{ round($y, 1) }}"
                width="{{ round($bw, 1) }}" height="{{ round($h, 1) }}"
                rx="1"><title>{{ $s['label'] }} · {{ $chEjeX[$i] }}: {{ $v }}</title></rect>
          @endif
        @endforeach
      @endforeach
    @endif

    {{-- Eje X. --}}
    <line class="wc-axisline" x1="{{ $mL }}" y1="{{ $mT + $plotH }}" x2="{{ $mL + $plotW }}" y2="{{ $mT + $plotH }}"/>
    @foreach($chEjeX as $i => $lbl)
      @if($i % $chCadaK === 0)
        @php
          $slot2 = $plotW / max(1, $chN);
          $lx = $chTipo === 'lineas'
              ? $mL + ($chN > 1 ? ($i / ($chN - 1)) * $plotW : $plotW / 2)
              : $mL + $i * $slot2 + $slot2 / 2;
        @endphp
        <text class="wc-axis" x="{{ round($lx, 1) }}" y="{{ $mT + $plotH + 14 }}" text-anchor="middle">{{ $lbl }}</text>
      @endif
    @endforeach
  </svg>

  <div class="wchart-leg">
    @foreach($chSeries as $s)
      <span class="wc-key"><i class="wc-{{ $s['clase'] }}"></i>{{ $s['label'] }}</span>
    @endforeach
  </div>
  @endif

  @if($chNota)<div class="wchart-note">{{ $chNota }}</div>@endif
</div>
