// PWA · registro del Service Worker (hand-rolled).
// -----------------------------------------------------------------------------
// Reemplaza al registro que traía el paquete silviolleite/laravelpwa (retirado en
// el upgrade de PHP: era sólo el cascarón). Desde entonces NADIE registraba el SW,
// así que la app perdió el precache de app-shell y la navegación sin red. Este
// archivo ya estaba enlazado desde layouts/app.blade.php (estaba vacío); ahora hace
// el registro a mano = cero dependencia nueva.
//
// Seguridad / anti-"SW viejo clavado":
//   · updateViaCache:'none'  → el navegador NO cachea el propio serviceworker.js;
//     en cada carga vuelve a pedirlo y, si cambió, instala la versión nueva. Es la
//     salvaguarda contra quedar servido por un SW viejo cacheado.
//   · reg.update()           → fuerza esa comprobación en cada carga (barato: el
//     navegador hace byte-compare del script).
//   · NO forzamos reload al activar un SW nuevo: la versión fresca entra en la
//     siguiente navegación (el SW usa skipWaiting pero no clients.claim()), evitando
//     recargas sorpresa o bucles de recarga.
//
// El SW (public/serviceworker.js) sirve navegaciones NETWORK-FIRST (cache-miss →
// red) y sólo hace cache-first del app-shell precacheado + /offline. Los borradores
// de captura viven en IndexedDB (cc-drafts) y son INDEPENDIENTES del SW: no se tocan.
(function () {
    'use strict';
    if (!('serviceWorker' in navigator)) {
        return; // navegador sin soporte → la app funciona igual, sin offline
    }
    window.addEventListener('load', function () {
        navigator.serviceWorker
            .register('/serviceworker.js', { scope: '/', updateViaCache: 'none' })
            .then(function (reg) {
                try { reg.update(); } catch (e) { /* no-op */ }
            })
            .catch(function () {
                /* silencioso: registrar el SW es best-effort */
            });
    });
})();
