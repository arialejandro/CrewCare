{{-- ============================================================================================
     PIE DE MARCA — CONTENIDO (fuente ÚNICA). Lo comparten los reportes v2 (docfoot + print-foot)
     y el póster MEDEVAC, para NO reinventar el footer en cada documento. El CONTENEDOR y los
     ESTILOS los pone quien lo incluye (.docfoot / .print-foot / .mdv-foot).
     Params: $ftName (quién elaboró) · $ftMeta (rol · fecha) · $ftUuid (UUID del documento).
============================================================================================ --}}
<div class="ft-l">
  <div class="ft-lbl">{{ __('reports.label_prepared_by') }}</div>
  <div class="ft-name">{{ (isset($ftName) && trim((string) $ftName) !== '') ? $ftName : '—' }}</div>
  <div class="ft-meta">{{ $ftMeta ?? '' }}</div>
</div>
<div class="ft-r">
  <div class="ft-lbl">{{ __('reports.label_powered_by') }}</div>
  <div class="cc"><span class="crew">Crew</span><span class="care">Care</span></div>
  <div class="ft-uuid">{{ $ftUuid ?? '' }}</div>
</div>
