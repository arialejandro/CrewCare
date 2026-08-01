{{-- ============================================================================================
     REPORT v2 — TOOLBAR COMPARTIDO (flotante, glass). No se imprime.
     Params OPCIONALES del scope que lo incluye:
       · $backRoute  (string|null) → si viene, muestra botón "Volver" a esa URL.
       · $backLabel  (string)      → texto del botón volver (default localizado).
       · $exportLabel (string)     → texto del botón Exportar PDF (default localizado).
     IDs #pdfBtn / #viewBtn / #viewLbl / #themeBtn los cablea el script de _report-v2-foot.
============================================================================================ --}}
@php
    $tbBack    = isset($backRoute) ? $backRoute : null;
    $tbEn      = app()->getLocale() === 'en';
    $tbBackL   = isset($backLabel) ? $backLabel : ($tbEn ? 'Back' : 'Volver');
    $tbExportL = isset($exportLabel) ? $exportLabel : ($tbEn ? 'Export PDF' : 'Exportar PDF');
    $tbViewL   = $tbEn ? 'Print view' : 'Vista impresión';
@endphp
<div class="toolbar">
  @if($tbBack)
  <a class="tb" href="{{ $tbBack }}">@include('componentes._icon', ['name' => 'chevron-left', 'class' => 'w-4 h-4']) {{ $tbBackL }}</a>
  @endif
  <button class="tb primary" id="pdfBtn">@include('componentes._icon', ['name' => 'download', 'class' => 'w-4 h-4', 'label' => 'PDF']) {{ $tbExportL }}</button>
  <button class="tb" id="viewBtn" data-print="{{ $tbViewL }}" data-screen="{{ $tbEn ? 'Screen view' : 'Vista pantalla' }}"><span id="viewLbl">{{ $tbViewL }}</span></button>
  <button class="tb ico" id="themeBtn" aria-label="{{ $tbEn ? 'Theme' : 'Tema' }}"></button>
</div>
