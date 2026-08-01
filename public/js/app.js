/*
 * public/js/app.js — bundle propio de la app (provisto a mano, sin webpack/Mix).
 *
 * Por qué existe: el layout principal carga <script src="{{ asset('js/app.js') }}">
 * pero el build nunca se compiló, así que daba 404 en cada página. Nada del front
 * depende de un bundle compilado (no hay Vue ni axios en uso real; el JS vive en
 * scripts inline + jQuery por CDN), por lo que este archivo mínimo resuelve el 404
 * sin necesidad de `npm`. Ver ASSETS-JS-AUDIT.md.
 *
 * Si más adelante se reactiva Laravel Mix/Vite, este archivo se reemplaza por el
 * compilado de resources/js/app.js.
 */
(function () {
    'use strict';
    // Punto único para JS global de la app. Hoy intencionalmente vacío.
})();
