{{--
    NORMAS APLICABLES de un hallazgo/reporte — chips de color con enlace al boletín.

    (2026-07-21) Nace con el paso de homologación del DSR. Los otros 4 reportes traen
    este bloque COPIADO A MANO 4 veces (hazard:225, unsafecond:227, injury:374,
    scouting:531) y en la migración a v2 los cuatro PERDIERON el enlace `reference_url`
    que sólo el DSR conserva. Copiar el bloque de ellos habría "homologado hacia abajo":
    el DSR habría perdido su enlace. Este parcial es la versión que NO pierde nada, para
    que los otros 4 puedan adoptarlo después (fuera del alcance de este paso: no se tocan).

    Parámetros:
      $standards     (Collection|array|null) normas del N:M (`standardables`). Puede venir vacía.
      $snapBadge     (string|null) marco del SNAPSHOT plano (fallback histórico).
      $snapCode      (string|null) código del SNAPSHOT plano.
      $snapUrl       (string|null) URL de boletín ya resuelta para el snapshot.
      $stdLayout     (string) 'stack' (columna compacta, tarjeta del DSR) | 'row' (fila de chips).

    DEGRADACIÓN — es la razón de ser del fallback, no un adorno: los 35 hallazgos que
    ya existen tienen `hazard_event_id` NULL (se capturaron antes del catálogo único),
    así que su N:M está VACÍO y no hay backfill posible. Si este parcial sólo pintara
    `$standards`, esos 35 hallazgos perderían su etiqueta normativa dentro de un
    documento firmable. Por eso: si hay N:M se pintan TODAS las normas; si no, se pinta
    el snapshot plano de siempre.
--}}
@php
    // Marcos con color definido en componentes/_badge-tokens. Cualquier otro valor cae
    // a GENERAL (gris) para que el chip SIEMPRE sea visible.
    //
    // ⚠ Esto arregla un defecto vivo: los logs INYECTADOS por el Master Hub (accidente /
    //   condición insegura / SFX) nunca pasan por applyHazardEvent(), así que se quedan
    //   con el DEFAULT 'NA' de la columna. 'NA' es truthy en PHP → hoy pintan
    //   <span class="badge badge-NA">, clase que NO existe en los tokens → chip sin
    //   fondo, texto blanco sobre blanco: invisible en papel.
    $stdBadgeEnum = ['STPS', 'OSHA', 'CSATF', 'AMAZON', 'DOT', 'SCT', 'GENERAL'];

    $stdList   = isset($standards) && $standards ? $standards : collect();
    $stdLayout = isset($stdLayout) ? $stdLayout : 'stack';

    $snapBadge = isset($snapBadge) ? trim((string) $snapBadge) : '';
    $snapCode  = isset($snapCode)  ? trim((string) $snapCode)  : '';
    $snapUrl   = isset($snapUrl)   ? $snapUrl : null;

    // 'NA' sin código es el marcador de "no se resolvió ninguna norma": no se pinta nada
    // en vez de un chip vacío que sólo ensucia el documento.
    $hasSnap = ($snapBadge !== '' && !($snapBadge === 'NA' && $snapCode === ''));

    // Normaliza a una lista uniforme [badge, code, categoria, url] venga del N:M o del snapshot.
    $stdRows = [];
    foreach ($stdList as $std) {
        // reference_url NO es de confianza para pintarse como enlace: lista blanca de
        // esquema (http/https) vía parse_url, mismo patrón que hazard-events/show.
        $href   = null;
        $rawUrl = trim((string) $std->reference_url);
        if ($rawUrl !== '') {
            $scheme = mb_strtolower((string) parse_url($rawUrl, PHP_URL_SCHEME));
            if ($scheme === 'http' || $scheme === 'https') { $href = $rawUrl; }
        }
        $stdRows[] = [
            'badge' => (string) $std->regulation_badge,
            'code'  => (string) $std->regulation_code,
            'cat'   => (string) $std->category_name_localized,
            'url'   => $href,
        ];
    }

    if (!count($stdRows) && $hasSnap) {
        $href = null;
        if ($snapUrl) {
            $scheme = mb_strtolower((string) parse_url(trim((string) $snapUrl), PHP_URL_SCHEME));
            if ($scheme === 'http' || $scheme === 'https') { $href = trim((string) $snapUrl); }
        }
        $stdRows[] = ['badge' => $snapBadge, 'code' => $snapCode, 'cat' => '', 'url' => $href];
    }
@endphp

@if(count($stdRows))
<div class="std-{{ $stdLayout }}">
  @foreach($stdRows as $row)
  @php $cls = in_array($row['badge'], $stdBadgeEnum, true) ? $row['badge'] : 'GENERAL'; @endphp
  <div class="std-one">
    <span class="badge badge-{{ $cls }}">{{ $row['badge'] }}</span>
    @if($row['code'] !== '')<span class="std-code">{{ $row['code'] }}</span>@endif
    @if($row['cat'] !== '')<span class="std-cat">{{ $row['cat'] }}</span>@endif
    @if($row['url'])<a href="{{ $row['url'] }}" target="_blank" rel="noopener" class="std-link no-print">{{ __('reports.label_view_bulletin') }}</a>@endif
  </div>
  @endforeach
</div>
@endif
