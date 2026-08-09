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
    /* Oficio con respiro arriba (13mm) y sitio para el pie fijo abajo (16mm). Gana sobre el
       @page{margin:0} del chrome por orden de fuente (este parcial va después de _report-v2-head). */
    @page{size:216mm 340mm;margin:13mm 0 16mm 0}
    .doc-body{padding:6mm 12mm 0}
    /* Las secciones LARGAS pueden partirse entre hojas; los bloques atómicos de adentro
       (.cfdi/.panel/.wkpi/.wloc/.photo/…) ya traen su propio break-inside:avoid. */
    .doc-body .sec{break-inside:auto!important;page-break-inside:auto!important}
  }
</style>
