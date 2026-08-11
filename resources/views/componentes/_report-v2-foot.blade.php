{{-- ============================================================================================
     REPORT v2 — PIE DE MARCA (sello) + SCRIPT COMPARTIDO.
     CONTRATO DE MAQUETADO: la vista abre  <div class="stage"><article class="sheet">
     y el <table class="report-wrap">…</table>, y NO cierra ni el article ni el stage.
     Este parcial emite el docfoot (dentro del sheet), CIERRA </article></div>, y luego el
     print-foot (fixed, repetido por hoja), el caption opcional y el <script>. La vista termina
     después del @include con </body></html>.
     Params OPCIONALES del scope:
       · $footPreparedName (string) → quién elaboró.  · $footPreparedMeta (string) → rol · fecha.
       · $footUuid (string) → sello UUID.             · $reportSandbox (bool) → muestra el caption.
============================================================================================ --}}
@php
    $ftName    = isset($footPreparedName) && $footPreparedName !== '' ? $footPreparedName : '—';
    $ftMeta    = isset($footPreparedMeta) ? $footPreparedMeta : '';
    $ftUuid    = isset($footUuid) ? $footUuid : '';
    $ftSandbox = isset($reportSandbox) ? (bool) $reportSandbox : false;
@endphp
    {{-- Pie de marca (pantalla, una vez al fondo de la hoja). Contenido = parcial compartido. --}}
    <footer class="docfoot">
      @include('componentes._brand-foot-inner', ['ftName' => $ftName, 'ftMeta' => $ftMeta, 'ftUuid' => $ftUuid])
    </footer>
  </article>
</div>

{{-- Pie de marca de IMPRESIÓN (position:fixed) — se repite al fondo de CADA hoja del PDF. --}}
<div class="print-foot" aria-hidden="true">
  @include('componentes._brand-foot-inner', ['ftName' => $ftName, 'ftMeta' => $ftMeta, 'ftUuid' => $ftUuid])
</div>

@if($ftSandbox)
<p class="caption">
  @include('componentes._icon', ['name' => 'info', 'class' => 'w-4 h-4'])
  <span><b style="color:var(--muted)">Sandbox de diseño v2.</b> En pantalla es cinematográfico; al <b style="color:var(--muted)">Exportar PDF</b> (o Ctrl/⌘+P) se transforma en documento blanco firmable. Usa <b style="color:var(--muted)">“Vista impresión”</b> para ver el papel sin imprimir.</span>
</p>
@endif

<script>
(function(){
  "use strict";
  var root = document.documentElement;
  var iconSun = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/></svg>';
  var iconMoon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>';
  var themeBtn = document.getElementById('themeBtn');
  function isDark(){ var t = root.getAttribute('data-theme'); if(t) return t === 'dark'; return !window.matchMedia('(prefers-color-scheme: light)').matches; }
  function paint(){ if(themeBtn) themeBtn.innerHTML = isDark() ? iconSun : iconMoon; }
  if(themeBtn){ themeBtn.addEventListener('click', function(){ root.setAttribute('data-theme', isDark() ? 'light' : 'dark'); paint(); }); paint(); }

  var manualPrint = false, viewBtn = document.getElementById('viewBtn'), viewLbl = document.getElementById('viewLbl');
  var lblPrint = viewBtn ? (viewBtn.getAttribute('data-print') || 'Vista impresión') : '';
  var lblScreen = viewBtn ? (viewBtn.getAttribute('data-screen') || 'Vista pantalla') : '';
  if(viewBtn){ viewBtn.addEventListener('click', function(){
    manualPrint = !manualPrint;
    root.setAttribute('data-view', manualPrint ? 'print' : 'screen');
    if(viewLbl) viewLbl.textContent = manualPrint ? lblScreen : lblPrint;
  }); }
  function beforeP(){ root.setAttribute('data-view', 'print'); }
  function afterP(){ root.setAttribute('data-view', manualPrint ? 'print' : 'screen'); }
  var pdfBtn = document.getElementById('pdfBtn');
@isset($pdfUrl)
  {{-- Descarga server-side (Browsershot): idéntica a window.print() pero de un clic. --}}
  if(pdfBtn){ pdfBtn.addEventListener('click', function(){ window.location.href = '{{ $pdfUrl }}'; }); }
@else
  if(pdfBtn){ pdfBtn.addEventListener('click', function(){ beforeP(); window.print(); }); }
@endisset
  window.addEventListener('beforeprint', beforeP);
  window.addEventListener('afterprint', afterP);
})();
</script>
