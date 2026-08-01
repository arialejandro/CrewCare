<?php

/*
|--------------------------------------------------------------------------
| Feature Flags — modularidad (Pilar 5)
|--------------------------------------------------------------------------
| Defaults de las features conmutables. La tabla `feature_flags` (BD) SOBRESCRIBE
| estos valores (ver App\Support\Features). Así Producción/Admin puede encender o
| apagar módulos por proyecto (desde un comercial chico hasta una película de
| estudio) sin tocar código.
|
| Convención: features "de plataforma" (siempre útiles) en true; features "de gran
| escala / futuras" en false por defecto y ocultas en la UI hasta que se enciendan.
*/

return [
    // Pilar 1 — captura ágil en 2 fases + cierre de acciones por link.
    'progressive_capture' => true,
    'magic_links'         => true,

    // Pilar 2 — inyección de eventos críticos al Daily Safety Report.
    'dsr_injection'       => true,

    // Pilar 3 — módulo SDS/consumibles + dinámica de efectos especiales (SFX).
    'sds_sfx'             => true,

    // Pilar 4 — addendums médicos (cambios de diagnóstico posteriores).
    'medical_addendum'    => true,

    // Pilar 5 — features de gran escala, APAGADAS por defecto y ocultas hasta que
    // Producción/Admin las encienda según la logística del proyecto.
    'aerial_mapping'      => false, // overlay de marcadores sobre foto de dron (Scouting)
    'location_handover'   => false, // handover de responsabilidad Construcción→Rigging→Shooting
];
