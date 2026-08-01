{{-- ============================================================================================
     Injury Report v2 — ALIAS (2026-07-16). El diseño v2 YA es la vista canónica
     admin/injuryreport.blade.php (promovido). Esta ruta de diseño /accident/{id}/v2
     (injury_reports.show_v2) se conserva por compatibilidad y re-renderiza la canónica con las
     MISMAS variables ($injuryReport, $standardUrl). Ghost del diseño v1: admin/injuryreport-legacy.blade.php
     (revertir = renombrar legacy → injuryreport).
============================================================================================ --}}
@include('admin.injuryreport')
