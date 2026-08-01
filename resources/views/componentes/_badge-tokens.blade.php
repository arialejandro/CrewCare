{{-- ============================================================================================
     BADGE TOKENS — colores de "marco regulatorio" (chips .badge-XXX). Fuente única de verdad.
     ---------------------------------------------------------------------------------------------
     FORMA: @once + <style> CRUDO (no @push). Se emite UNA vez in-place lo incluya quien lo incluya
       — el picker montado en el <body> o _report-v2-head en el <head> — sin depender de la
       posición de un @stack('styles'). @once deduplica en la página del DSR, que carga AMBOS
       (el _event-picker y _report-v2-head): el estilo se pinta una sola vez.
     COLOR DE TEXTO EXPLÍCITO: en las 4 vistas de CAPTURA (hazard/injury/unsafe/daily) estos chips
       conviven con el .badge de Bootstrap 5, que define su propio color y tamaño. Por eso cada
       modificador fija SU background Y SU color de texto: se lee igual aunque el .badge base no
       sea el de los reportes (que hereda color:#fff) sino el de Bootstrap.
     Marcos: CSATF/OSHA/STPS/GENERAL (hoy) + DOT/SCT (catálogo enriquecido) + AMAZON (legacy).
============================================================================================ --}}
@once
<style>
  .badge-STPS{background:#15803d;color:#fff}
  .badge-OSHA{background:#1d4ed8;color:#fff}
  .badge-CSATF{background:#b91c1c;color:#fff}
  .badge-AMAZON{background:#ff9900;color:#000}
  .badge-DOT{background:#1e40af;color:#fff}
  .badge-SCT{background:#047857;color:#fff}
  .badge-GENERAL{background:#6b7280;color:#fff}
</style>
@endonce
