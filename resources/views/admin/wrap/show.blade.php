{{-- ============================================================================================
     REPORTE FINAL DE WRAP — v2 "Cinematic Dark Glass". Documento STANDALONE (no layout).
     Ruta: GET /wrap/{id} → wrap.show → WrapReportController@show   ·   borrador: GET /wrap/borrador
     Chrome compartido: _report-v2-head (fuentes+CSS+motor impresión) · _report-v2-toolbar · _report-v2-foot.
     PHP 7.4 (sin match/enums/nullsafe/promoción de constructor).

     ── LA VISTA NO CALCULA NADA ────────────────────────────────────────────────────────────────
     Todo sale de $payload, que WrapReportBuilder congeló al emitirse. Aquí no hay una sola
     consulta a los 5 reportes. Es a propósito y es la garantía del documento: lo que se imprime
     hoy es lo mismo que se imprimió el día de la entrega, aunque después se haya corregido un DSR.

     ── CERO NOMBRES DE PERSONAS ────────────────────────────────────────────────────────────────
     El payload ya viene sin ellos (el builder convierte todo a departamento antes de salir), y
     esta vista no consulta usuarios por ningún lado. El sello se emite como SISTEMA, así que el
     recuadro CFDI tampoco imprime un firmante. La firma autógrafa va en el bloque .sign, en
     blanco, igual que en los otros seis documentos.

     GRÁFICAS: SVG puro en _wrap-chart, sin JS ni librerías. Ver el porqué en ese parcial.
============================================================================================ --}}
@php
    use Illuminate\Support\Facades\Schema;
    if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); }

    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $primary   = isset($brand['primary_color']) ? $brand['primary_color'] : '#ff9900';
    $esBorrador = isset($borrador) ? (bool) $borrador : false;

    // Membrete VIVO: el nombre del proyecto se toma de la Marca en CADA visita, nunca del payload
    // congelado. El proyecto puede renombrarse (p.ej. QMAS → QPCS → QAAS) y eso debe reflejarse en
    // TODO documento, incluso ya emitido — igual que los demás reportes ($heroProject = $brandName).
    // El sello protege el CONTENIDO (las cifras del payload), no el membrete. Misma nota en
    // admin/medevac/show.blade.php.
    $brandName = isset($brand['brand_name']) && $brand['brand_name'] !== '' ? $brand['brand_name'] : 'CrewCare';
    // ¿Hay una tira superior (borrador o mensaje de sesión)? Si la hay, la hoja de abajo no repite
    // el respiro de 74px que deja para el toolbar (lo puso ya la tira).
    $hasTop = $esBorrador || session('status');

    $P  = is_array($payload) ? $payload : [];
    $s1 = isset($P['s1_alcance'])      ? $P['s1_alcance']      : [];
    $s2 = isset($P['s2_anticipado'])   ? $P['s2_anticipado']   : [];
    $s3 = isset($P['s3_ocurrido'])     ? $P['s3_ocurrido']     : [];
    $s4 = isset($P['s4_contraste'])    ? $P['s4_contraste']    : [];
    $s5 = isset($P['s5_cronologia'])   ? $P['s5_cronologia']   : [];
    $s6 = isset($P['s6_cumplimiento']) ? $P['s6_cumplimiento'] : [];
    $s7 = isset($P['s7_tendencias'])   ? $P['s7_tendencias']   : [];
    $s8 = isset($P['s8_continuidad'])  ? $P['s8_continuidad']  : [];

    // Lector defensivo: un payload emitido por una versión anterior del builder no tiene por qué
    // traer todas las claves. El documento existe para poder abrirse años después; que le falte
    // una cifra es un guion, no una pantalla en blanco.
    $v = function ($arr, $k, $def = null) { return isset($arr[$k]) ? $arr[$k] : $def; };
    $num = function ($n) { return $n === null ? '—' : number_format((float) $n, (floor((float) $n) == (float) $n) ? 0 : 2); };

    // --- Bloque 2: elecciones del editor CONGELADAS en el payload (omitir apartados / notas). ---
    // En el BORRADOR el payload aún no las trae → se ven todos los apartados y las notas se
    // previsualizan EN VIVO por JS. En el SELLADO, el payload manda: los apartados omitidos se
    // ocultan con un <style> generado (display:none = fuera del PDF, omisión "silenciosa") y las
    // notas frozen se pintan dentro de su apartado. Al emitir, el controlador las sella.
    $editor  = $v($P, 'editor', []);
    $omit    = array_values(array_filter((array) $v($editor, 'omit', [])));
    $notes   = (array) $v($editor, 'notes', []);
    $secNote = function ($k) use ($notes) { return isset($notes[$k]) ? trim((string) $notes[$k]) : ''; };
    // Apartados y si son OMITIBLES (s1 y el sello son fijos). Rótulos para el panel del borrador.
    $sectionMeta = [
        's1' => ['1 · Identificación y alcance', false],
        's2' => ['2 · Lo que se anticipó',       true],
        's3' => ['3 · Lo que realmente pasó',    true],
        's4' => ['4 · Predicho vs. real',        true],
        's5' => ['5 · Desglose cronológico',     true],
        's6' => ['6 · Cumplimiento',             true],
        's7' => ['7 · Tendencias',               true],
        's8' => ['8 · Continuidad',              true],
    ];

    $folio = $wrap->exists ? $wrap->folio() : 'BORRADOR';
    $periodoIni = $v($s1, 'periodo_desde');
    $periodoFin = $v($s1, 'periodo_hasta');
    $fmt = function ($f) { return $f ? \Carbon\Carbon::parse($f)->format('d M Y') : '—'; };

    $nivelClase = ['Bajo' => 'n1', 'Medio' => 'n2', 'Alto' => 'n3', 'Extremo' => 'n4'];
    $nivelNombre = [1 => 'Bajo', 2 => 'Medio', 3 => 'Alto', 4 => 'Extremo'];

    $heroDate = $fmt($periodoIni) . ' — ' . $fmt($periodoFin);
    $footUuid = $wrap->uuid ? mb_strtoupper(mb_substr((string) $wrap->uuid, 0, 8)) : '—';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $folio }} · Reporte final de wrap</title>
@include('componentes._report-v2-head')
@include('componentes._doc-hero-styles')
<style>
  /* ---- Estilos propios del WRAP. El motor de impresión y los componentes base ya viven en
     _report-v2-head; aquí sólo va lo que este documento añade. --------------------------------- */

  /* Cintillo de BORRADOR. Se pinta arriba del todo y también se repite en la cabecera de cada
     hoja: un borrador que pierde su marca al fotocopiarse deja de ser un borrador. */
  .wdraft{display:flex;align-items:center;gap:10px;margin:14px 0 0;padding:11px 16px;border-radius:11px;
    border:1px dashed var(--warn);background:color-mix(in srgb,var(--warn) 12%,transparent);color:var(--warn);
    font:700 .84rem/1.4 var(--font)}
  .wdraft .n{font-weight:500;color:var(--muted)}

  /* Rejilla de cifras grandes: la lectura de un vistazo que pide la casa productora. */
  .wkpi{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:4px 0 14px}
  .wkpi .k{padding:12px 14px;border:1px solid var(--stroke);border-radius:var(--radius-sm);background:var(--panel)}
  .wkpi .k .n{font:800 1.5rem/1.1 var(--poster);color:var(--text);font-variant-numeric:tabular-nums}
  .wkpi .k .l{margin-top:3px;font:600 .68rem/1.3 var(--font);color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
  .wkpi .k .s{margin-top:2px;font:500 .7rem/1.3 var(--font);color:var(--faint)}
  .wkpi .k.warn .n{color:var(--warn)}
  .wkpi .k.danger .n{color:var(--danger)}

  /* Nota de honestidad: el bloque donde el documento declara sus propios límites. Se distingue
     del cuerpo para que no se lea como relleno, pero NO se pinta como alerta: no es un error,
     es parte de lo que el reporte afirma. */
  .wnote{margin:10px 0;padding:11px 14px;border-left:3px solid var(--stroke-2);background:var(--panel);
    border-radius:0 var(--radius-sm) var(--radius-sm) 0;font:500 .78rem/1.55 var(--font);color:var(--muted)}
  .wnote b{color:var(--text)}
  .wnote.strong{border-left-color:var(--warn)}

  /* Barras de proporción (distribución por nivel) — CSS puro, sin SVG: son una sola dimensión. */
  .wbars{display:flex;flex-direction:column;gap:7px;margin:6px 0}
  .wbar{display:grid;grid-template-columns:78px 1fr 44px;align-items:center;gap:10px;font:600 .74rem/1 var(--font)}
  .wbar .lbl{color:var(--muted)}
  .wbar .track{height:14px;border-radius:7px;background:var(--panel);border:1px solid var(--stroke);overflow:hidden}
  .wbar .fill{height:100%;border-radius:6px}
  .wbar .val{text-align:right;color:var(--text);font-variant-numeric:tabular-nums}
  .n1 .fill,i.n1{background:var(--r-1)} .n2 .fill,i.n2{background:var(--r-3)}
  .n3 .fill,i.n3{background:var(--r-4)} .n4 .fill,i.n4{background:var(--r-5)}

  /* Cruce predicho-vs-real, por locación. */
  .wloc{border:1px solid var(--stroke);border-radius:var(--radius-sm);padding:12px 14px;margin:0 0 9px;background:var(--panel)}
  .wloc h4{margin:0 0 8px;font:700 .92rem/1.2 var(--font);color:var(--text)}
  .wloc .sum{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:8px;font:600 .72rem/1 var(--font);color:var(--muted)}
  .wloc .sum b{color:var(--text);font-variant-numeric:tabular-nums}
  .wev{display:flex;align-items:flex-start;gap:8px;padding:5px 0;border-top:1px dashed var(--stroke);font:500 .76rem/1.45 var(--font)}
  .wev:first-of-type{border-top:0}
  .wev .tag{flex:none;min-width:74px;padding:2px 7px;border-radius:6px;text-align:center;
    font:700 .62rem/1.5 var(--font);text-transform:uppercase;letter-spacing:.05em}
  .wev .tag.hit{background:color-mix(in srgb,var(--ok) 16%,transparent);color:var(--ok)}
  .wev .tag.miss{background:color-mix(in srgb,var(--danger) 16%,transparent);color:var(--danger)}
  .wev .txt{color:var(--text)}
  .wev .txt em{font-style:normal;color:var(--muted)}

  /* Calibración: delta predicho → real. */
  .wcal td.d{font-variant-numeric:tabular-nums;font-weight:700}
  .wcal td.d.up{color:var(--danger)} .wcal td.d.dn{color:var(--ok)} .wcal td.d.eq{color:var(--muted)}

  /* Cronología: cada asunto con su conclusión. */
  .wtl{display:flex;flex-direction:column;gap:0}
  .wtli{display:grid;grid-template-columns:96px 1fr;gap:12px;padding:11px 0;border-top:1px solid var(--stroke)}
  .wtli:first-child{border-top:0}
  .wtli .when{font:700 .72rem/1.45 var(--font);color:var(--muted)}
  .wtli .when .d{display:block;color:var(--text);font-size:.8rem}
  .wtli .what h5{margin:0 0 4px;font:700 .84rem/1.3 var(--font);color:var(--text);display:flex;flex-wrap:wrap;gap:7px;align-items:center}
  .wtli .what .meta{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:5px}
  .wtli .what p{margin:0 0 4px;font:500 .78rem/1.5 var(--font);color:var(--muted)}
  .wtli .what p b{color:var(--text);font-weight:700}

  /* Recomendaciones de continuidad, cada una con su evidencia. */
  .wreco{border:1px solid var(--stroke);border-left:3px solid var(--brand);border-radius:0 var(--radius-sm) var(--radius-sm) 0;
    padding:11px 14px;margin:0 0 8px;background:var(--panel)}
  .wreco h5{margin:0 0 4px;font:700 .88rem/1.3 var(--font);color:var(--text)}
  .wreco .ev{margin:0 0 5px;font:600 .74rem/1.45 var(--font);color:var(--brand)}
  .wreco .ac{margin:0;font:500 .78rem/1.5 var(--font);color:var(--muted)}
  .wplazo{display:inline-block;padding:2px 8px;border-radius:6px;border:1px solid var(--stroke-2);
    font:700 .6rem/1.6 var(--font);text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-left:6px}

  /* --- Gráficas (_wrap-chart) --- */
  .wchart{margin:0 0 14px}
  .wchart-h{font:700 .8rem/1.3 var(--font);color:var(--text);margin-bottom:6px}
  .wchart-svg{width:100%;height:auto;display:block;overflow:visible}
  .wchart-empty{padding:16px;text-align:center;font:600 .78rem/1 var(--font);color:var(--faint);
    border:1px dashed var(--stroke);border-radius:var(--radius-sm)}
  .wchart-leg{display:flex;flex-wrap:wrap;gap:12px;margin-top:6px}
  .wc-key{display:inline-flex;align-items:center;gap:5px;font:600 .7rem/1 var(--font);color:var(--muted)}
  .wc-key i{width:10px;height:10px;border-radius:3px;flex:none}
  .wchart-note{margin-top:5px;font:500 .7rem/1.45 var(--font);color:var(--faint)}
  .wc-grid{stroke:var(--stroke);stroke-width:1}
  .wc-axisline{stroke:var(--stroke-2);stroke-width:1}
  .wc-axis{fill:var(--faint);font:600 9px var(--font)}
  .wc-line{fill:none;stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
  /* Paleta de series. Se declara por CLASE y no con fill= en el SVG para que el tema de
     impresión (:root[data-view="print"]) pueda retonarla igual que al resto del documento. */
  .wc-s1{fill:var(--brand);stroke:var(--brand)}
  .wc-s2{fill:var(--r-4);stroke:var(--r-4)}
  .wc-s3{fill:var(--r-5);stroke:var(--r-5)}
  .wc-s4{fill:#5E6AD2;stroke:#5E6AD2}
  i.wc-s1{background:var(--brand)} i.wc-s2{background:var(--r-4)}
  i.wc-s3{background:var(--r-5)}   i.wc-s4{background:#5E6AD2}

  /* Tira de operación (emitir / anexo). No se imprime. */
  .wops{width:100%;max-width:860px;margin:0 auto 12px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between}
  .wops form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0}
  /* Emisión bloqueada (aún no pasa la fecha de fin): candado + motivo, en lugar del botón. */
  .wlock{display:flex;align-items:flex-start;gap:9px;max-width:560px;padding:10px 14px;border-radius:11px;
    border:1px dashed var(--stroke-2);background:var(--panel);color:var(--muted);font:600 .78rem/1.5 var(--font)}
  .wlock svg{width:17px;height:17px;flex:none;margin-top:1px;color:var(--warn)}

  @media print{
    /* Una sección nunca se parte a la mitad si cabe entera; las gráficas y las fichas de
       locación, jamás: media gráfica en una hoja y media en la siguiente no se puede leer. */
    .wchart,.wloc,.wreco,.wkpi,.wnote,.wtli{break-inside:avoid;page-break-inside:avoid}
    .wops{display:none !important}
    /* Las barras de proporción son fondos de color: sin esto Chrome las imprime en blanco y la
       distribución por nivel de riesgo sale vacía. El chrome ya pide print-color-adjust:exact
       en body; se repite aquí por si el navegador lo acota al elemento. */
    .wbar .fill,.wc-key i{-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important}
  }
  @media (max-width:760px){
    .wkpi{grid-template-columns:repeat(2,1fr)}
    .wtli{grid-template-columns:1fr}
  }

  /* ===== CUERPO EN FLUJO DE BLOQUES (no tabla) — reemplaza el motor `report-wrap` =============
     El cuerpo vivía en UNA celda <td> del tbody, y Chrome IGNORA break-inside dentro de una celda
     que pagina → cortaba texto/gráficas a media hoja en documentos largos. En FLUJO NORMAL (divs)
     Chrome SÍ respeta break-inside:avoid, así que los saltos caen ENTRE bloques, nunca dentro.
     Costo: el hero ya no se repite por hoja (dependía del <thead> de la tabla); sale en la 1ª.
     El pie fijo (.print-foot) SÍ se repite y el @page da respiro arriba y sitio abajo. ============ */
  .doc-body{padding:var(--pad)}
  /* Tira superior (borrador / mensaje): NO es un .stage de 100vh — antes empujaba el documento una
     pantalla completa hacia abajo y parecía que "no se renderizaba nada". Es compacta. */
  .wrap-controls{padding:86px 20px 0}
  .stage.below-controls{padding-top:16px}

  @media print{
    /* Oficio con respiro arriba (13mm) y sitio para el pie fijo abajo (16mm). */
    @page{size:216mm 340mm;margin:13mm 0 16mm 0}
    .doc-body{padding:6mm 12mm 0}
    /* Las secciones LARGAS (cronología, predicho-vs-real) SÍ pueden partirse entre hojas; lo que
       nunca se parte es cada bloque atómico de adentro (.wtli/.wloc/.wkpi/.wchart/.wnote, ya
       protegidos arriba). Sin esto, una sección más alta que una hoja se recorta. */
    .sec{break-inside:auto!important;page-break-inside:auto!important}
  }

  @media (max-width:720px){
    .wrap-controls{padding:78px 14px 0}
    /* Tablas anchas: desplazables en horizontal en vez de desbordar la hoja (una sola tabla, las
       columnas siguen alineadas; nowrap conserva su ancho y activa el scroll). */
    .doc-body .tbl{display:block;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch}
  }
  @media (max-width:640px){
    /* Hero en móvil: la caja del proyecto tiene ancho definido (56vw) para que el auto-ajuste del
       nombre lo encoja al ancho en vez de recortarlo, y no desborde el logo. */
    .doc-hero{height:160px}
    .doc-hero .hero-side{width:56vw;left:auto;right:12px;top:12px}
  }

  /* ===== Panel de ajuste del BORRADOR (Bloque 2): omitir apartados + notas por apartado ========
     No se imprime (vive en .wrap-controls .no-print). El <details> colapsa sin JS. ============= */
  .weditor{max-width:860px;margin:10px auto 0;border:1px solid var(--stroke);border-radius:var(--radius-sm);background:var(--panel)}
  .weditor-h{cursor:pointer;list-style:none;padding:11px 14px;font:700 .82rem/1.2 var(--font);color:var(--text);display:flex;align-items:center;gap:8px}
  .weditor-h::-webkit-details-marker{display:none}
  .weditor-h svg{width:15px;height:15px;color:var(--brand)}
  .weditor[open] .weditor-h{border-bottom:1px solid var(--stroke)}
  .weditor-grid{display:flex;flex-direction:column;gap:8px;padding:12px 14px}
  .weditor-row{display:grid;grid-template-columns:minmax(160px,230px) 1fr;gap:10px;align-items:center}
  .weditor-inc{display:flex;align-items:center;gap:8px;font:600 .8rem/1.2 var(--font);color:var(--text);cursor:pointer;min-width:0}
  .weditor-inc input{width:16px;height:16px;flex:none;accent-color:var(--brand)}
  .weditor-inc.fixed{color:var(--muted);cursor:default}
  .weditor-inc.fixed .dot{width:8px;height:8px;border-radius:50%;background:var(--stroke-2);flex:none}
  .weditor-inc em{font-style:normal;font-size:.58rem;text-transform:uppercase;letter-spacing:.08em;color:var(--faint)}
  .weditor-note-in{width:100%;height:36px}
  .weditor-foot{padding:0 14px 12px;font:500 .72rem/1.45 var(--font);color:var(--faint)}
  @media (max-width:620px){ .weditor-row{grid-template-columns:1fr;gap:5px} }

  /* Nota del editor por apartado: cinta discreta bajo el encabezado de su sección (imprimible). El
     rótulo "Nota del editor —" es ::before para que el JS del borrador sobrescriba sólo el texto. */
  .wsec-note{margin:0 0 12px;padding:9px 13px;border-left:3px solid var(--brand);border-radius:0 var(--radius-sm) var(--radius-sm) 0;
    background:color-mix(in srgb,var(--brand) 7%,var(--panel));font:500 .82rem/1.5 var(--font);color:var(--text);
    white-space:pre-wrap;overflow-wrap:break-word;break-inside:avoid}
  .wsec-note::before{content:"Nota del editor — ";font-weight:700;color:var(--brand)}
  .wsec-note.is-empty{display:none}
</style>
@if(!empty($omit))
{{-- Omisión SILENCIOSA (elección del owner): los apartados omitidos se ocultan por CSS → no salen
     en el PDF exportado. No se @unless en el markup para no reordenar la vista; el efecto entregado
     (el PDF) es idéntico: el apartado no existe en el papel. --}}
<style>@foreach($omit as $wk)[data-sec-block="{{ $wk }}"]{display:none!important}@endforeach</style>
@endif
</head>
<body>

<div class="ambient"><div class="b b1"></div><div class="b b2"></div></div>

@include('componentes._report-v2-toolbar', ['backRoute' => route('wrap.index')])

@if(session('status'))
<div class="wrap-controls no-print">
  <div class="alert ok">@include('componentes._icon', ['name' => 'check-circle']) {{ session('status') }}</div>
</div>
@endif

@if($esBorrador)
@php $emitBloqueado = isset($emitBloqueado) ? $emitBloqueado : null; @endphp
<div class="wrap-controls no-print">
  {{-- El panel de ajuste + el botón viven en el MISMO form para que los checkbox/notas viajen al
       emitir. Si la ventana está cerrada no hay form (no se puede emitir), pero el panel sí se
       muestra para previsualizar en vivo. --}}
  @if(! $emitBloqueado)
  <form method="POST" action="{{ route('wrap.store') }}" id="wrapEmit">
    @csrf
    <input type="hidden" name="desde" value="{{ request('desde') }}">
    <input type="hidden" name="hasta" value="{{ request('hasta') }}">
  @endif

    <div class="wops">
      <div class="ops-note">Borrador en vivo. Debajo se ve el documento completo, tal como se emitirá. Ajusta qué apartados incluir y añade notas; al emitir se congela y se sella.</div>
      @if($emitBloqueado)
        {{-- Ventana CERRADA: sin botón (no se puede congelar antes de la fecha de finalización); se
             explica el porqué y hasta cuándo, en vez de un botón muerto que invite a insistir. --}}
        <div class="wlock">@include('componentes._icon', ['name' => 'lock']) <span>{{ $emitBloqueado }}</span></div>
      @else
        <button class="btn brand" type="submit">@include('componentes._icon', ['name' => 'shield']) Emitir y sellar</button>
      @endif
    </div>

    <details class="weditor" open>
      <summary class="weditor-h">@include('componentes._icon', ['name' => 'list']) Ajustar documento (opcional)</summary>
      <div class="weditor-grid">
        @foreach($sectionMeta as $key => $meta)
          <div class="weditor-row">
            @if($meta[1])
              <label class="weditor-inc"><input type="checkbox" name="include[]" value="{{ $key }}" checked data-sec="{{ $key }}"><span>{{ $meta[0] }}</span></label>
            @else
              <span class="weditor-inc fixed"><span class="dot"></span><span>{{ $meta[0] }}</span> <em>fijo</em></span>
            @endif
            <input type="text" class="field weditor-note-in" name="note[{{ $key }}]" data-note="{{ $key }}" maxlength="500" autocomplete="off" placeholder="Nota para este apartado (opcional)…">
          </div>
        @endforeach
      </div>
      <div class="weditor-foot">Los apartados desmarcados no aparecerán en el documento emitido. Las notas se sellan junto con el documento.</div>
    </details>

  @if(! $emitBloqueado)
  </form>
  @endif
</div>

{{-- Previsualización EN VIVO del panel (sólo borrador): ocultar/mostrar apartados y volcar notas. --}}
<script>
(function(){
  var panel = document.querySelector('.weditor'); if (!panel) return;
  panel.addEventListener('change', function(e){
    var cb = e.target.closest('input[type=checkbox][data-sec]'); if (!cb) return;
    var b = document.querySelector('[data-sec-block="' + cb.value + '"]');
    if (b) b.style.display = cb.checked ? '' : 'none';
  });
  panel.addEventListener('input', function(e){
    var inp = e.target.closest('[data-note]'); if (!inp) return;
    var out = document.querySelector('[data-note-out="' + inp.getAttribute('data-note') + '"]');
    if (!out) return;
    var val = (inp.value || '').trim();
    out.textContent = val;
    out.classList.toggle('is-empty', val === '');
  });
})();
</script>
@endif

<div class="stage {{ $hasTop ? 'below-controls' : '' }}">
  <article class="sheet">
    {{-- CUERPO EN FLUJO DE BLOQUES (no tabla): el hero sale en la 1ª hoja; el pie fijo se repite
         (ver la nota de CSS arriba). Sin el <td> que recortaba, los saltos caen entre bloques. --}}
    @include('componentes._doc-hero', [
      'heroImage'    => null,
      'heroProject'  => $brandName,
      'heroLocation' => $v($s1, 'casa_productora') ?: 'Reporte final de producción',
      'heroDate'     => $heroDate,
      'heroTime'     => null,
      'heroModule'   => $wrap->isAddendum() ? 'Anexo al reporte de wrap' : 'Wrap Report',
    ])
    @if($esBorrador)
    <div class="wdraft">@include('componentes._icon', ['name' => 'alert-triangle'])
      <div><b>BORRADOR — sin emitir</b> <span class="n">Las cifras se recalculan en cada visita y el documento no está sellado. No entregar en este estado.</span></div>
    </div>
    @endif

    {{-- ══ BANDA DE LECTURA RÁPIDA ═══════════════════════════════════════════════════════ --}}
    <div class="band">
      <div class="lead">
        <span class="ic">@include('componentes._icon', ['name' => 'clipboard-check'])</span>
        <div class="who">
          <div class="lbl">Documento</div>
          <div class="val">{{ $folio }}</div>
          <div class="sub">{{ $wrap->isAddendum() ? 'Anexo por regrabación' : 'Cierre de producción' }} · {{ $fmt($periodoIni) }} a {{ $fmt($periodoFin) }}</div>
        </div>
      </div>
      <div class="stats">
        <div class="cell"><div class="lbl">Días trabajados</div><div class="v">{{ $num($v($s1, 'dias_trabajados')) }}</div></div>
        <div class="cell"><div class="lbl">Eventos</div><div class="v">{{ $num($v($s3, 'total_eventos')) }}</div></div>
        <div class="cell"><div class="lbl">Registrables</div><div class="v {{ (int) $v($s3, 'registrables', 0) > 0 ? 'warn' : '' }}">{{ $num($v($s3, 'registrables')) }}</div></div>
      </div>
    </div>

    <div class="doc-body">

    {{-- ══ 1 · IDENTIFICACIÓN Y ALCANCE ═════════════════════════════════════════════════ --}}
    <section class="sec" data-sec-block="s1">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'film'])<h2>1 · Identificación y alcance</h2><span class="line"></span></div>
      @include('admin.wrap._editor-note', ['wnKey' => 's1', 'wnTxt' => $secNote('s1')])

      <div class="facts">
        <div class="fact"><div class="k">Producción</div><div class="v">{{ $brandName }}</div></div>
        <div class="fact"><div class="k">Clave</div><div class="v mono">{{ $v($s1, 'codigo') ?: '—' }}</div></div>
        <div class="fact"><div class="k">Casa productora</div><div class="v">{{ $v($s1, 'casa_productora') ?: 'No registrada' }}</div></div>
        <div class="fact"><div class="k">Periodo cubierto</div><div class="v">{{ $fmt($periodoIni) }} — {{ $fmt($periodoFin) }}</div></div>
        <div class="fact"><div class="k">Primer día de rodaje</div><div class="v">{{ $fmt($v($s1, 'primer_dia_rodaje')) }}</div></div>
        <div class="fact"><div class="k">Último día de rodaje</div><div class="v">{{ $fmt($v($s1, 'ultimo_dia_rodaje')) }}</div></div>
      </div>

      <div class="wkpi">
        <div class="k"><div class="n">{{ $num($v($s1, 'dias_prep')) }}</div><div class="l">Días de prep</div><div class="s">Lunes a sábado</div></div>
        <div class="k"><div class="n">{{ $num($v($s1, 'dias_rodaje')) }}</div><div class="l">Días de rodaje</div><div class="s">Con reporte diario</div></div>
        <div class="k"><div class="n">{{ $num($v($s1, 'dias_trabajados')) }}</div><div class="l">Días trabajados</div><div class="s">{{ $num($v($s1, 'dias_naturales')) }} naturales</div></div>
        <div class="k"><div class="n">{{ $num(isset($s1['crew']['maximo']) ? $s1['crew']['maximo'] : null) }}</div><div class="l">Crew máximo</div><div class="s">Promedio {{ $num(isset($s1['crew']['promedio']) ? $s1['crew']['promedio'] : null) }}</div></div>
      </div>

      @if($v($s1, 'nota_dias'))<div class="wnote">{{ $v($s1, 'nota_dias') }}</div>@endif

      <div class="facts">
        <div class="fact"><div class="k">Locaciones evaluadas</div><div class="v">{{ $num($v($s1, 'locaciones_evaluadas')) }}</div></div>
        <div class="fact"><div class="k">Locaciones filmadas</div><div class="v">{{ $num($v($s1, 'locaciones_filmadas')) }}</div></div>
        <div class="fact"><div class="k">Reportes diarios emitidos</div><div class="v">{{ $num($v($s1, 'dsr_emitidos')) }}</div></div>
        <div class="fact"><div class="k">Suma persona-día</div><div class="v">{{ $num(isset($s1['crew']['persona_dia']) ? $s1['crew']['persona_dia'] : null) }}</div></div>
      </div>

      {{-- El dato faltante se DECLARA. Nunca se estima: una tasa calculada sobre un crew inventado
           es una cifra falsa con apariencia de medición. --}}
      @if(isset($s1['crew']['nota']) && $s1['crew']['nota'])
        <div class="wnote strong">@include('componentes._icon', ['name' => 'alert-triangle']) <b>Dato incompleto:</b> {{ $s1['crew']['nota'] }}</div>
      @endif
      @if((int) $v($s1, 'locaciones_filmadas_sin_scouting', 0) > 0)
        <div class="wnote strong"><b>{{ $v($s1, 'locaciones_filmadas_sin_scouting') }} locaciones</b> aparecen en los reportes diarios sin un scouting que las evalúe. Lo que ocurrió ahí no tiene predicción contra la cual contrastarse y queda fuera de la sección 4.</div>
      @endif
    </section>

    {{-- ══ 2 · LO QUE SE ANTICIPÓ ═══════════════════════════════════════════════════════ --}}
    <section class="sec" data-sec-block="s2">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'map-pin'])<h2>2 · Lo que se anticipó</h2><span class="line"></span></div>
      @include('admin.wrap._editor-note', ['wnKey' => 's2', 'wnTxt' => $secNote('s2')])

      <div class="wkpi">
        <div class="k"><div class="n">{{ $num($v($s2, 'scoutings')) }}</div><div class="l">Scoutings</div></div>
        <div class="k"><div class="n">{{ $num($v($s2, 'peligros')) }}</div><div class="l">Peligros identificados</div></div>
        <div class="k"><div class="n">{{ $num($v($s2, 'con_evento')) }}</div><div class="l">Ligados al catálogo</div><div class="s">Contrastables</div></div>
        <div class="k {{ ((int) $v($s2, 'sin_clasificar', 0) + (int) $v($s2, 'sin_marca', 0)) > 0 ? 'warn' : '' }}">
          <div class="n">{{ $num((int) $v($s2, 'sin_clasificar', 0) + (int) $v($s2, 'sin_marca', 0)) }}</div>
          <div class="l">Sin clasificar</div><div class="s">{{ $num($v($s2, 'sin_clasificar')) }} declarados</div></div>
      </div>

      @if($v($s2, 'nota_clasificacion'))<div class="wnote">{{ $v($s2, 'nota_clasificacion') }}</div>@endif

      <h3 class="sec-sub" style="margin:12px 0 6px;font:700 .82rem/1 var(--font);color:var(--muted)">Distribución por nivel de riesgo previsto</h3>
      @php $pn = $v($s2, 'por_nivel', []); $pnTot = max(1, array_sum(array_map('intval', (array) $pn))); @endphp
      <div class="wbars">
        @foreach([4, 3, 2, 1] as $nv)
          @php $cnt = (int) (isset($pn[$nv]) ? $pn[$nv] : 0); @endphp
          <div class="wbar {{ 'n' . $nv }}">
            <span class="lbl">{{ $nivelNombre[$nv] }}</span>
            <span class="track"><span class="fill" style="width:{{ round(($cnt / $pnTot) * 100, 1) }}%"></span></span>
            <span class="val">{{ $cnt }}</span>
          </div>
        @endforeach
      </div>
      @if((int) $v($s2, 'sin_nivel', 0) > 0)
        <div class="wchart-note">{{ $v($s2, 'sin_nivel') }} peligros no traen nivel de riesgo evaluable (formato de scouting anterior a la matriz 5×5).</div>
      @endif

      @if($v($s2, 'locaciones'))
      <table class="tbl" style="margin-top:12px">
        <thead><tr><th>Locación evaluada</th><th>Peligros</th><th>Con evento</th><th>Sin clasificar</th></tr></thead>
        <tbody>
        @foreach($v($s2, 'locaciones', []) as $loc)
          <tr>
            <td>{{ $v($loc, 'nombre', '—') }}</td>
            <td class="mono">{{ $v($loc, 'peligros', 0) }}</td>
            <td class="mono">{{ $v($loc, 'con_evento', 0) }}</td>
            <td class="mono">{{ (int) $v($loc, 'sin_clasificar', 0) + (int) $v($loc, 'sin_marca', 0) }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
      @endif

      @if($v($s2, 'sb132'))
      <h3 style="margin:14px 0 5px;font:700 .82rem/1 var(--font);color:var(--muted)">Actividades especiales declaradas (SB-132)</h3>
      <div class="chips">@foreach($v($s2, 'sb132', []) as $a)<span class="chip">{{ $a }}</span>@endforeach</div>
      @endif

      @if($v($s2, 'normas'))
      <h3 style="margin:14px 0 5px;font:700 .82rem/1 var(--font);color:var(--muted)">Marcos normativos invocados ({{ count($v($s2, 'normas', [])) }})</h3>
      <div class="chips">@foreach($v($s2, 'normas', []) as $n)<span class="chip">{{ $n }}</span>@endforeach</div>
      @endif
    </section>

    {{-- ══ 3 · LO QUE REALMENTE PASÓ ════════════════════════════════════════════════════ --}}
    <section class="sec" data-sec-block="s3">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'activity'])<h2>3 · Lo que realmente pasó</h2><span class="line"></span></div>
      @include('admin.wrap._editor-note', ['wnKey' => 's3', 'wnTxt' => $secNote('s3')])

      <div class="wkpi">
        <div class="k"><div class="n">{{ $num($v($s3, 'hallazgos_dsr')) }}</div><div class="l">Hallazgos de bitácora</div></div>
        <div class="k"><div class="n">{{ $num((int) $v($s3, 'actos', 0) + (int) $v($s3, 'condiciones', 0)) }}</div><div class="l">Actos y condiciones</div>
          <div class="s">{{ $num($v($s3, 'actos')) }} actos · {{ $num($v($s3, 'condiciones')) }} condiciones</div></div>
        <div class="k {{ (int) $v($s3, 'registrables', 0) > 0 ? 'danger' : '' }}"><div class="n">{{ $num($v($s3, 'lesiones')) }}</div>
          <div class="l">Accidentes</div><div class="s">{{ $num($v($s3, 'registrables')) }} registrables OSHA</div></div>
        <div class="k"><div class="n">{{ $num($v($s3, 'consultas')) }}</div><div class="l">Consultas médicas</div>
          <div class="s">{{ $num($v($s3, 'sfx')) }} efectos especiales</div></div>
      </div>

      <div class="facts">
        <div class="fact"><div class="k">Días de ausencia</div><div class="v">{{ $num($v($s3, 'dias_ausencia')) }}</div></div>
        <div class="fact"><div class="k">Días de trabajo restringido</div><div class="v">{{ $num($v($s3, 'dias_restringido')) }}</div></div>
        @php $t = $v($s3, 'tasas', []); @endphp
        <div class="fact"><div class="k">Eventos / 100 persona-día</div><div class="v mono">{{ isset($t['eventos']) && $t['eventos'] !== null ? $t['eventos'] : 'sin dato' }}</div></div>
        <div class="fact"><div class="k">Registrables / 100 persona-día</div><div class="v mono">{{ isset($t['registrables']) && $t['registrables'] !== null ? $t['registrables'] : 'sin dato' }}</div></div>
      </div>

      @if($v($s3, 'nota_tasa'))<div class="wnote">{{ $v($s3, 'nota_tasa') }}</div>@endif

      @if($v($s3, 'departamentos'))
      <h3 style="margin:14px 0 5px;font:700 .82rem/1 var(--font);color:var(--muted)">Departamentos involucrados</h3>
      {{-- DEPARTAMENTO, nunca la persona. Es el nivel al que la casa productora puede actuar
           (reforzar, capacitar) sin señalar a nadie — la misma promesa del Acto Inseguro. --}}
      <div class="chips">
        @foreach($v($s3, 'departamentos', []) as $dep => $cnt)<span class="chip">{{ $dep }} · {{ $cnt }}</span>@endforeach
      </div>
      @endif

      @if($v($s3, 'manejo_clinico'))
      <h3 style="margin:14px 0 5px;font:700 .82rem/1 var(--font);color:var(--muted)">Conducta clínica en consulta</h3>
      <div class="chips">
        @foreach($v($s3, 'manejo_clinico', []) as $m => $cnt)<span class="chip">{{ $m }} · {{ $cnt }}</span>@endforeach
      </div>
      <div class="wchart-note">Sólo la conducta tomada. Ningún diagnóstico, medicamento ni dato clínico de una persona entra en este documento.</div>
      @endif

      @if($v($s3, 'sfx_detalle'))
      <h3 style="margin:14px 0 5px;font:700 .82rem/1 var(--font);color:var(--muted)">Efectos especiales operados</h3>
      <table class="tbl">
        <thead><tr><th>Efecto</th><th>Inicio</th><th>Estado</th></tr></thead>
        <tbody>
        @foreach($v($s3, 'sfx_detalle', []) as $e)
          <tr><td>{{ $v($e, 'efecto', '—') }}</td><td class="mono">{{ $fmt($v($e, 'inicio')) }}</td><td>{{ $v($e, 'estado') === 'closed' ? 'Concluido' : 'Abierto' }}</td></tr>
        @endforeach
        </tbody>
      </table>
      @endif
    </section>

    {{-- ══ 4 · PREDICHO vs. REAL ════════════════════════════════════════════════════════ --}}
    <section class="sec" data-sec-block="s4">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'git-compare'])<h2>4 · Lo que se predijo contra lo que pasó</h2><span class="line"></span></div>
      @include('admin.wrap._editor-note', ['wnKey' => 's4', 'wnTxt' => $secNote('s4')])

      {{-- HONESTIDAD OBLIGATORIA: el tamaño de muestra va ARRIBA de los porcentajes, no al pie.
           Un lector que ve primero "87.5 %" y después la advertencia ya se formó la conclusión. --}}
      @php $mu = $v($s4, 'muestra', []); @endphp
      <div class="wnote {{ $v($mu, 'fuerza') === 'suficiente' ? '' : 'strong' }}">
        @include('componentes._icon', ['name' => 'info'])
        <b>Tamaño de muestra:</b> {{ $v($mu, 'texto', '—') }}
      </div>

      <div class="wkpi">
        <div class="k"><div class="n">{{ $num($v($s4, 'anticipados_que_ocurrieron')) }}</div><div class="l">Anticipados que ocurrieron</div></div>
        <div class="k {{ (int) $v($s4, 'no_anticipados', 0) > 0 ? 'danger' : '' }}"><div class="n">{{ $num($v($s4, 'no_anticipados')) }}</div><div class="l">Ocurrieron sin anticiparse</div></div>
        <div class="k"><div class="n">{{ $num($v($s4, 'anticipados_sin_ocurrir')) }}</div><div class="l">Anticipados que no ocurrieron</div></div>
        <div class="k {{ (int) $v($s4, 'eventos_sin_locacion', 0) > 0 ? 'warn' : '' }}"><div class="n">{{ $num($v($s4, 'eventos_sin_locacion')) }}</div>
          <div class="l">Sin locación evaluada</div><div class="s">No cruzables</div></div>
      </div>

      @if($v($s4, 'nota_no_materializados'))<div class="wnote">{{ $v($s4, 'nota_no_materializados') }}</div>@endif

      {{-- (a) POR LOCACIÓN --}}
      <h3 style="margin:14px 0 8px;font:700 .86rem/1 var(--font);color:var(--text)">a · Por locación</h3>
      @forelse($v($s4, 'por_locacion', []) as $loc)
        <div class="wloc">
          <h4>{{ $v($loc, 'locacion', '—') }}</h4>
          <div class="sum">
            <span>Peligros previstos con evento: <b>{{ $v($loc, 'predichos', 0) }}</b></span>
            <span>Se materializaron: <b>{{ count((array) $v($loc, 'anticipados_que_ocurrieron', [])) }}</b></span>
            <span>Sorpresas: <b>{{ count((array) $v($loc, 'no_anticipados', [])) }}</b></span>
            <span>Previstos sin ocurrir: <b>{{ $v($loc, 'anticipados_sin_ocurrir', 0) }}</b></span>
          </div>
          @foreach((array) $v($loc, 'anticipados_que_ocurrieron', []) as $ev)
            <div class="wev"><span class="tag hit">Previsto</span><span class="txt">{{ $v($ev, 'evento') }} <em>· se evaluó como {{ $v($ev, 'predicho', 'sin nivel') }}</em></span></div>
          @endforeach
          @foreach((array) $v($loc, 'no_anticipados', []) as $ev)
            <div class="wev"><span class="tag miss">Sorpresa</span><span class="txt">{{ $v($ev, 'evento') }} <em>· el scouting de esta locación no lo identificó</em></span></div>
          @endforeach
          @if(! count((array) $v($loc, 'anticipados_que_ocurrieron', [])) && ! count((array) $v($loc, 'no_anticipados', [])))
            <div class="wev"><span class="txt"><em>No se registraron eventos atribuibles a esta locación en el periodo.</em></span></div>
          @endif
        </div>
      @empty
        <div class="wnote">No hay locaciones evaluadas en el periodo, así que no hay predicción que contrastar.</div>
      @endforelse

      {{-- (b) CALIBRACIÓN --}}
      @php $cal = $v($s4, 'calibracion', []); $pares = (array) $v($cal, 'pares', []); @endphp
      <h3 style="margin:16px 0 8px;font:700 .86rem/1 var(--font);color:var(--text)">b · Calibración de la severidad</h3>

      <div class="wkpi">
        <div class="k {{ (int) $v($cal, 'subestimados', 0) > 0 ? 'danger' : '' }}"><div class="n">{{ $num($v($cal, 'subestimados')) }}</div><div class="l">Peor de lo previsto</div></div>
        <div class="k"><div class="n">{{ $num($v($cal, 'acertados')) }}</div><div class="l">Igual a lo previsto</div></div>
        <div class="k"><div class="n">{{ $num($v($cal, 'sobreestimados')) }}</div><div class="l">Más leve de lo previsto</div></div>
        <div class="k"><div class="n">{{ count($pares) }}</div><div class="l">Comparaciones posibles</div></div>
      </div>

      @if($pares)
      <table class="tbl wcal">
        <thead><tr><th>Locación</th><th>Evento</th><th>Se evaluó</th><th>Resultó</th><th>Δ</th></tr></thead>
        <tbody>
        @foreach($pares as $p)
          @php $d = (int) $v($p, 'delta', 0); @endphp
          <tr>
            <td>{{ $v($p, 'locacion') }}</td>
            <td>{{ $v($p, 'evento') }}</td>
            <td>{{ isset($nivelNombre[$v($p, 'predicho')]) ? $nivelNombre[$v($p, 'predicho')] : '—' }}</td>
            <td>{{ isset($nivelNombre[$v($p, 'real')]) ? $nivelNombre[$v($p, 'real')] : '—' }}</td>
            <td class="d {{ $d > 0 ? 'up' : ($d < 0 ? 'dn' : 'eq') }}">{{ $d > 0 ? '+' . $d : $d }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
      @else
        <div class="wnote">Ningún evento con nivel de riesgo propio coincidió con un peligro previsto que también tuviera nivel. Sin ese par no se puede medir la calibración.</div>
      @endif

      {{-- Materialización por nivel: la pregunta directa — ¿lo calificado Alto produjo algo? --}}
      @php $mat = (array) $v($cal, 'materializacion', []); @endphp
      @if($mat)
      <h4 style="margin:14px 0 6px;font:700 .8rem/1 var(--font);color:var(--muted)">¿Se materializó lo que se calificó como grave?</h4>
      <table class="tbl">
        <thead><tr><th>Nivel evaluado</th><th>Peligros previstos</th><th>Se materializaron</th><th>Proporción</th></tr></thead>
        <tbody>
        @foreach([4, 3, 2, 1] as $nv)
          @php $m = isset($mat[$nv]) ? $mat[$nv] : (isset($mat[(string) $nv]) ? $mat[(string) $nv] : null); @endphp
          @if($m)
          <tr>
            <td>{{ $nivelNombre[$nv] }}</td>
            <td class="mono">{{ $v($m, 'predichos', 0) }}</td>
            <td class="mono">{{ $v($m, 'ocurrieron', 0) }}</td>
            <td class="mono">{{ $v($m, 'tasa') === null ? '—' : $v($m, 'tasa') . ' %' }}</td>
          </tr>
          @endif
        @endforeach
        </tbody>
      </table>
      <div class="wchart-note">Se lee de arriba abajo: si los niveles altos no producen nada y los bajos sí, la evaluación estaría mal calibrada. Con la muestra declarada arriba, esta tabla orienta una revisión caso por caso; no concluye.</div>
      @endif
    </section>

    {{-- ══ 5 · DESGLOSE CRONOLÓGICO ═════════════════════════════════════════════════════ --}}
    <section class="sec" data-sec-block="s5">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'list'])<h2>5 · Desglose cronológico y sus conclusiones</h2><span class="line"></span></div>
      @include('admin.wrap._editor-note', ['wnKey' => 's5', 'wnTxt' => $secNote('s5')])
      <div class="wchart-note" style="margin-bottom:10px">{{ $num($v($s5, 'total')) }} asuntos registrados, en orden. Se identifica el departamento involucrado; nunca a la persona.</div>

      <div class="wtl">
      @forelse((array) $v($s5, 'entradas', []) as $e)
        <div class="wtli">
          <div class="when">{{ $fmt($v($e, 'fecha')) }}<span class="d">{{ $v($e, 'dia', '—') }}</span></div>
          <div class="what">
            <h5>{{ $v($e, 'tipo') }}
              @if($v($e, 'nivel'))<span class="badge">{{ $v($e, 'nivel') }}</span>@endif
              @if($v($e, 'estado'))<span class="chip {{ $v($e, 'estado') === 'cerrado' ? 'ok' : 'warn' }}">{{ ucfirst($v($e, 'estado')) }}</span>@endif
            </h5>
            <div class="meta">
              @if($v($e, 'locacion'))<span class="chip">{{ $v($e, 'locacion') }}</span>@endif
              @if($v($e, 'departamento'))<span class="chip">{{ $v($e, 'departamento') }}</span>@endif
              @if($v($e, 'evento'))<span class="chip">{{ $v($e, 'evento') }}</span>@endif
              @if($v($e, 'norma'))<span class="chip">{{ $v($e, 'norma') }}</span>@endif
            </div>
            @if($v($e, 'hallazgo'))<p><b>Qué se encontró:</b> {{ $v($e, 'hallazgo') }}</p>@endif
            @if($v($e, 'causa'))<p><b>Por qué ocurrió:</b> {{ $v($e, 'causa') }}</p>@endif
            @if($v($e, 'decision'))<p><b>Qué se decidió:</b> {{ $v($e, 'decision') }}</p>@endif
            @if($v($e, 'repercusion'))<p><b>Repercusión:</b> {{ $v($e, 'repercusion') }}</p>@endif
          </div>
        </div>
      @empty
        <div class="wnote">No se registraron asuntos en el periodo.</div>
      @endforelse
      </div>
    </section>

    {{-- ══ 6 · CUMPLIMIENTO ═════════════════════════════════════════════════════════════ --}}
    <section class="sec" data-sec-block="s6">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'shield-check'])<h2>6 · Cumplimiento</h2><span class="line"></span></div>
      @include('admin.wrap._editor-note', ['wnKey' => 's6', 'wnTxt' => $secNote('s6')])

      @php $ju = $v($s6, 'juntas', []); $ac = $v($s6, 'acciones', []); @endphp

      <h3 style="margin:0 0 6px;font:700 .82rem/1 var(--font);color:var(--muted)">Juntas de seguridad</h3>
      {{-- LOS TRES ESTADOS, separados. "Sin declarar" no es "no se hizo": fundirlos acusaría a la
           producción de algo que quizá sí hizo, o taparía un hueco de registro. --}}
      <div class="wkpi">
        <div class="k"><div class="n">{{ $num($v($ju, 'realizadas')) }}</div><div class="l">Realizadas</div></div>
        <div class="k {{ (int) $v($ju, 'no_realizadas', 0) > 0 ? 'warn' : '' }}"><div class="n">{{ $num($v($ju, 'no_realizadas')) }}</div><div class="l">No realizadas</div><div class="s">Declarado expresamente</div></div>
        <div class="k {{ (int) $v($ju, 'sin_declarar', 0) > 0 ? 'warn' : '' }}"><div class="n">{{ $num($v($ju, 'sin_declarar')) }}</div><div class="l">Sin declarar</div><div class="s">No es lo mismo que no realizada</div></div>
        <div class="k"><div class="n">{{ $num($v($ju, 'dias')) }}</div><div class="l">Días con reporte</div></div>
      </div>
      @if($v($ju, 'temas'))
        <div class="chips">@foreach($v($ju, 'temas', []) as $tema => $cnt)<span class="chip">{{ $tema }} · {{ $cnt }}</span>@endforeach</div>
      @elseif((int) $v($ju, 'realizadas', 0) > 0)
        <div class="wnote strong">Se declararon {{ $v($ju, 'realizadas') }} juntas realizadas, pero ninguna dejó registrado su tema. La junta sin tema prueba que hubo reunión, no de qué se habló.</div>
      @endif

      <h3 style="margin:16px 0 6px;font:700 .82rem/1 var(--font);color:var(--muted)">Acciones correctivas</h3>
      <div class="wkpi">
        <div class="k"><div class="n">{{ $num($v($ac, 'total')) }}</div><div class="l">Registradas</div></div>
        <div class="k"><div class="n">{{ $num($v($ac, 'cerradas')) }}</div><div class="l">Cerradas</div>
          <div class="s">{{ $v($ac, 'dias_cierre_promedio') !== null ? $v($ac, 'dias_cierre_promedio') . ' días en promedio' : 'sin dato de cierre' }}</div></div>
        <div class="k {{ (int) $v($ac, 'abiertas', 0) > 0 ? 'warn' : '' }}"><div class="n">{{ $num($v($ac, 'abiertas')) }}</div><div class="l">Abiertas</div></div>
        <div class="k {{ (int) $v($ac, 'vencidas', 0) > 0 ? 'danger' : '' }}"><div class="n">{{ $num($v($ac, 'vencidas')) }}</div><div class="l">Vencidas</div><div class="s">Al cierre del periodo</div></div>
      </div>

      <h3 style="margin:16px 0 6px;font:700 .82rem/1 var(--font);color:var(--muted)">Notificaciones a la autoridad</h3>
      @if($v($s6, 'notificaciones_autoridad'))
      <table class="tbl">
        <thead><tr><th>Fecha</th><th>Autoridad / nota</th></tr></thead>
        <tbody>
        @foreach($v($s6, 'notificaciones_autoridad', []) as $n)
          <tr><td class="mono">{{ $fmt($v($n, 'fecha')) }}</td><td>{{ $v($n, 'nota') ?: '—' }}</td></tr>
        @endforeach
        </tbody>
      </table>
      @else
        <div class="wnote">No se registraron notificaciones a la autoridad en el periodo.</div>
      @endif
      @if((int) $v($s6, 'lesiones_pendientes_notificar', 0) > 0)
        <div class="wnote strong">@include('componentes._icon', ['name' => 'alert-triangle'])
          <b>{{ $v($s6, 'lesiones_pendientes_notificar') }} accidentes registrables</b> no tienen registrada la notificación a la autoridad. Puede tratarse de un hueco de captura o de una notificación no realizada; este documento no puede distinguirlos y por eso lo señala en vez de suponer.</div>
      @endif
    </section>

    {{-- ══ 7 · TENDENCIAS ═══════════════════════════════════════════════════════════════ --}}
    <section class="sec" data-sec-block="s7">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'trending-up'])<h2>7 · Tendencias</h2><span class="line"></span></div>
      @include('admin.wrap._editor-note', ['wnKey' => 's7', 'wnTxt' => $secNote('s7')])

      @php
        $serie = (array) $v($s7, 'serie_dias', []);
        $ejeDias = array_map(function ($d) { return isset($d['dia']) ? str_replace('Día ', '', $d['dia']) : '—'; }, $serie);
        $col = function ($clave) use ($serie) {
            return array_map(function ($d) use ($clave) { return isset($d[$clave]) ? (int) $d[$clave] : 0; }, $serie);
        };
        $curva = (array) $v($s7, 'curva_acciones', []);
      @endphp

      @include('componentes._wrap-chart', [
        'titulo' => 'Eventos por día trabajado',
        'tipo'   => 'barras',
        'ejeX'   => $ejeDias,
        'series' => [
          ['label' => 'Hallazgos de bitácora', 'clase' => 's1', 'valores' => $col('hallazgos')],
          ['label' => 'Actos y condiciones',   'clase' => 's2', 'valores' => $col('gemelos')],
          ['label' => 'Accidentes',            'clase' => 's3', 'valores' => $col('lesiones')],
        ],
        'nota' => 'El eje son DÍAS TRABAJADOS con reporte, no días de calendario: un hueco de domingo insinuaría un día sin vigilancia que nadie trabajó.',
      ])

      @include('componentes._wrap-chart', [
        'titulo' => 'Consultas médicas por día trabajado',
        'tipo'   => 'barras',
        'ejeX'   => $ejeDias,
        'series' => [['label' => 'Consultas', 'clase' => 's4', 'valores' => $col('consultas')]],
      ])

      @if($curva)
      @include('componentes._wrap-chart', [
        'titulo' => 'Acciones correctivas: apertura contra cierre (acumulado)',
        'tipo'   => 'lineas',
        'ejeX'   => array_map(function ($c) { return isset($c['dia']) ? str_replace('Día ', '', $c['dia']) : '—'; }, $curva),
        'series' => [
          ['label' => 'Abiertas acumuladas', 'clase' => 's2', 'valores' => array_map(function ($c) { return isset($c['abiertas']) ? (int) $c['abiertas'] : 0; }, $curva)],
          ['label' => 'Cerradas acumuladas', 'clase' => 's1', 'valores' => array_map(function ($c) { return isset($c['cerradas']) ? (int) $c['cerradas'] : 0; }, $curva)],
        ],
        'nota' => 'La distancia entre las dos líneas es la deuda de seguridad que se acumula: cuanto más se abren, más se separan.',
      ])
      @endif

      <h3 style="margin:14px 0 6px;font:700 .82rem/1 var(--font);color:var(--muted)">Riesgo real observado, por nivel</h3>
      @php $rn = (array) $v($s7, 'por_nivel', []); $rnTot = max(1, array_sum(array_map('intval', $rn))); @endphp
      <div class="wbars">
        @foreach([4, 3, 2, 1] as $nv)
          @php $cnt = (int) (isset($rn[$nv]) ? $rn[$nv] : (isset($rn[(string) $nv]) ? $rn[(string) $nv] : 0)); @endphp
          <div class="wbar {{ 'n' . $nv }}">
            <span class="lbl">{{ $nivelNombre[$nv] }}</span>
            <span class="track"><span class="fill" style="width:{{ round(($cnt / $rnTot) * 100, 1) }}%"></span></span>
            <span class="val">{{ $cnt }}</span>
          </div>
        @endforeach
      </div>
      <div class="wchart-note">Sólo se grafican los eventos que traen nivel propio (actos, condiciones y accidentes). Los hallazgos de bitácora no lo llevan y se excluyen para no atribuirles la severidad genérica del catálogo.</div>
    </section>

    {{-- ══ 8 · CONTINUIDAD ══════════════════════════════════════════════════════════════ --}}
    <section class="sec" data-sec-block="s8">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'compass'])<h2>8 · Continuidad</h2><span class="line"></span></div>
      @include('admin.wrap._editor-note', ['wnKey' => 's8', 'wnTxt' => $secNote('s8')])
      <div class="wchart-note" style="margin-bottom:10px">Cada recomendación nace de una cifra de este reporte y la cita. No son buenas intenciones: son conclusiones con evidencia detrás.</div>

      @php
        $plazoTxt = ['corto' => 'Corto plazo', 'medio' => 'Mediano plazo', 'largo' => 'Largo plazo'];
      @endphp
      @foreach(['corto', 'medio', 'largo'] as $pz)
        @php $lista = array_filter((array) $v($s8, 'recomendaciones', []), function ($r) use ($pz) { return isset($r['plazo']) && $r['plazo'] === $pz; }); @endphp
        @if($lista)
          @foreach($lista as $r)
            <div class="wreco">
              <h5>{{ $v($r, 'titulo') }}<span class="wplazo">{{ $plazoTxt[$pz] }}</span></h5>
              <p class="ev">{{ $v($r, 'evidencia') }}</p>
              <p class="ac">{{ $v($r, 'accion') }}</p>
            </div>
          @endforeach
        @endif
      @endforeach
    </section>

    {{-- ══ ANEXOS Y SELLO ═══════════════════════════════════════════════════════════════ --}}
    @if($wrap->exists && $wrap->isAddendum() && isset($P['anexo_de']))
    <section class="sec">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'link'])<h2>Anexo a un reporte anterior</h2><span class="line"></span></div>
      <div class="wnote"><b>Este documento NO reemplaza al cierre original.</b> Complementa al {{ $v($P['anexo_de'], 'folio', '—') }} y cubre únicamente el periodo declarado arriba. El documento original conserva su folio, su sello y su código de verificación intactos.
        @if($wrap->reason)<br><b>Motivo:</b> {{ $wrap->reason }}@endif
      </div>
      <div class="facts">
        <div class="fact"><div class="k">Documento complementado</div><div class="v mono">{{ $v($P['anexo_de'], 'folio', '—') }}</div></div>
        <div class="fact"><div class="k">UUID del original</div><div class="v mono">{{ $v($P['anexo_de'], 'uuid', '—') }}</div></div>
      </div>
    </section>
    @endif

    @if($wrap->exists && $wrap->addendums && count($wrap->addendums))
    <section class="sec">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'files'])<h2>Anexos emitidos</h2><span class="line"></span></div>
      <table class="tbl">
        <thead><tr><th>Folio</th><th>Periodo</th><th>Motivo</th></tr></thead>
        <tbody>
        @foreach($wrap->addendums as $ax)
          <tr><td class="mono">{{ $ax->folio() }}</td>
              <td>{{ $fmt($ax->period_start) }} — {{ $fmt($ax->period_end) }}</td>
              <td>{{ $ax->reason ?: '—' }}</td></tr>
        @endforeach
        </tbody>
      </table>
      <div class="wchart-note">Cada anexo tiene su propio sello y se verifica por separado.</div>
    </section>
    @endif

    <section class="sec">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'file-check'])<h2>Validación del documento</h2><span class="line"></span></div>

      {{-- FIRMA AUTÓGRAFA: el hueco en blanco. El sello de abajo es de INTEGRIDAD (dice que el
           documento no cambió); esta línea es la conformidad de una persona, que es otra cosa y
           se pone a mano sobre el papel. No se imprime ningún nombre: quien firme, escribe el suyo. --}}
      <div class="sign">
        <div class="sig"><div class="who"></div><div class="role">Safety Advisor / Coordinador de Seguridad</div></div>
        <div class="sig"><div class="who"></div><div class="role">Por la casa productora</div></div>
      </div>

      @if($esBorrador)
        <div class="seal none">@include('componentes._icon', ['name' => 'info'])
          <div><span class="h">Borrador sin emitir: este documento todavía no tiene sello ni código de verificación.</span></div></div>
      @else
        @include('componentes._seal-cfdi', ['doc' => $wrap, 'folio' => $folio, 'prefix' => 'CREWCARE-WRAP'])
      @endif

      @if($v($P, 'avisos'))
      <div class="wchart-note" style="margin-top:10px">
        Límites declarados de este cálculo: {{ implode(' · ', array_map(function ($a) {
            $m = ['crew_incompleto' => 'faltan días con número de crew',
                  'locaciones_sin_scouting' => 'se filmó en locaciones sin scouting',
                  'peligros_sin_clasificar' => 'hay peligros sin evento del catálogo',
                  'eventos_sin_locacion' => 'hay eventos que no se pudieron atribuir a una locación evaluada'];
            return isset($m[$a]) ? $m[$a] : $a;
        }, (array) $v($P, 'avisos', []))) }}.
      </div>
      @endif
    </section>

    </div>{{-- /.doc-body --}}

    {{-- El pie NO lleva nombre de persona: este documento lo calcula la app desde reportes que ya
         venían sellados, no lo redacta alguien. Atribuirlo a quien apretó el botón sería falso. --}}
    @include('componentes._report-v2-foot', [
      'footPreparedName' => 'CrewCare · cálculo automático',
      'footPreparedMeta' => 'Derivado de los reportes sellados de la producción'
          . (isset($P['meta']['generado_en']) ? ' · ' . \Carbon\Carbon::parse($P['meta']['generado_en'])->format('d M Y H:i') : ''),
      'footUuid'         => $footUuid,
    ])

</body>
</html>
