{{-- ============================================================================================
     PAGINACIÓN EN FLUJO DE BLOQUES — estilos para documentos LARGOS que sacaron el cuerpo de la
     tabla `report-wrap` a un `<div class="doc-body">`. Incluir en el <head> DESPUÉS de
     `_report-v2-head`, SÓLO en los documentos convertidos.

     ── POR QUÉ ────────────────────────────────────────────────────────────────────────────────
     El motor `report-wrap` mete todo el cuerpo en UNA celda `<td>`, y Chrome IGNORA `break-inside`
     dentro de una celda que pagina → parte el texto a media línea en documentos largos. En FLUJO
     NORMAL (divs) Chrome SÍ respeta `break-inside:avoid`, así que los saltos caen ENTRE bloques.
     Costo: el hero deja de repetirse por hoja (dependía del `<thead>`); sale en la 1ª. El pie fijo
     (`.print-foot`) SÍ se repite (position:fixed + margen inferior del `@page`), y el `@page` da
     respiro superior. Ver [[doc-hero-band-homologation]] gotcha #2.

     SÓLO afecta a documentos con `.doc-body`; los que siguen en `report-wrap` usan `.body` y
     conservan el `@page{margin:0}` del chrome (hero full-bleed repetido) → intactos.
============================================================================================ --}}
<style>
  .doc-body{padding:var(--pad)}
  @media print{
    /* CARTA (Letter). Gana sobre el @page{margin:0} del chrome por orden de fuente (este parcial
       va después de _report-v2-head).
       ⚠ EL PIE ES position:fixed y un @page margin-bottom NO le reserva espacio de flujo: el pie de
       ~15mm se pintaba ENCIMA de las últimas filas de CADA hoja y las ocultaba/cortaba (verificado
       con PDF real: apagando el pie, el contenido oculto reaparece). Fix verificado: margen inferior
       18mm y BAJAR el pie a la zona de margen (bottom:-16mm) → el pie queda DEBAJO de la caja de
       contenido, no lo tapa, y sólo deja ~2mm de blanco real bajo él. Arriba 8mm (seguro para
       impresora). Antes: margin 12/15mm + padding 6mm → banda superior grande y corte inferior. */
    @page{size:letter;margin:8mm 0 18mm 0}
    .doc-body{padding:4mm 12mm 0}
    /* !important porque en window.print() real el JS pone data-view="print" y _report-v2-head trae
       `:root[data-view="print"] .print-foot{bottom:0}` (más específico) que si no, gana. */
    .print-foot{bottom:-16mm!important}
    /* Las secciones LARGAS pueden partirse entre hojas; los bloques atómicos de adentro
       (.cfdi/.panel/.wkpi/.wloc/.photo/…) ya traen su propio break-inside:avoid. */
    .doc-body .sec{break-inside:auto!important;page-break-inside:auto!important}
  }
</style>
