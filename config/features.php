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
    // (2026-08-01) 'aerial_mapping' RETIRADO: el mapeo de la locación (pines sobre
    // lienzos, incluido el aéreo de dron) ya está CONSTRUIDO como módulo real
    // (delta #48, /scoutings/{id}/mapeo), no como stub tras flag.
    'location_handover'   => false, // handover de responsabilidad Construcción→Rigging→Shooting

    // Contratos — cola de firmas a gran escala (50-60 contratos). APAGADAS por defecto:
    //  - queue_email:   manda el correo de "contrato firmado" en segundo plano (cola) en vez de
    //                   bloquear el request. Sin worker de cola corre inline igual (seguro por default).
    //  - batch_signing: deja a una figura interna (LP, Contador, Rep. Legal…) firmar varios de una vez.
    //                   Por defecto la firma es UNO A UNO (el firmante ve los datos de cada contrato).
    //  - queue_render:  al completarse un sobre, renderiza el CONTRATO FIRMADO (PDF con autógrafas) en
    //                   segundo plano (cola) en vez de bloquear la última firma. Sin worker corre inline
    //                   igual (seguro por default). El render usa Chrome headless (Browsershot).
    'contracts_queue_email'   => false,
    'contracts_batch_signing' => false,
    'contracts_queue_render'  => false,
];
