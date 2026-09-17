/*
 * img-fallback.js — oculta <img data-hide-on-error> que fallan al cargar.
 *
 * Reemplaza el patrón viejo onerror="this.style.display='none'" (CSP: sin manejadores en atributo).
 * El evento `error` de un <img> NO burbujea, pero SÍ se puede capturar en la FASE DE CAPTURA a
 * nivel window. Este archivo es same-origin (script-src 'self' lo permite sin nonce) y se carga en
 * el <head> para registrar el listener ANTES de que el navegador cargue las imágenes del <body>.
 *
 * Red de seguridad: si una imagen ya falló antes de registrar el listener (caché/parseo), el pase
 * en DOMContentLoaded la oculta igual (img.complete && naturalWidth === 0).
 */
(function () {
    function hide(img) {
        if (img && img.tagName === 'IMG' && img.hasAttribute('data-hide-on-error')) {
            img.style.display = 'none';
        }
    }

    // Captura: corre para el error de carga de cualquier <img>, aunque no burbujee.
    window.addEventListener('error', function (e) { hide(e.target); }, true);

    // Backstop: imágenes que ya fallaron antes de que corriera el listener.
    document.addEventListener('DOMContentLoaded', function () {
        var imgs = document.querySelectorAll('img[data-hide-on-error]');
        for (var i = 0; i < imgs.length; i++) {
            if (imgs[i].complete && imgs[i].naturalWidth === 0) { hide(imgs[i]); }
        }
    });
})();
