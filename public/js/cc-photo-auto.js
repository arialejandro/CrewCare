/*
 * cc-photo-auto.js — auto-cableado del MODELO ÚNICO DE FOTO (cc-photo.js) para los formularios
 * que capturan fotos en set desde iPad/iPhone (DSR, accidentes, peligros, actos inseguros,
 * materialidad, mitigación pública, MEDEVAC, gafete…).
 *
 * PORQUÉ: el servidor no decodifica HEIC (GD; sin Imagick+libheif). Safari (los equipos del set)
 * SÍ lo decodifica, así que la conversión a JPEG ocurre en el NAVEGADOR antes de subir. Este
 * archivo aplica esa conversión a cualquier <input type=file data-cc-photo> — incluidos los que
 * se crean dinámicamente (imágenes adicionales) — mediante DELEGACIÓN de eventos.
 *
 * REGLA DE ORO: sólo interviene cuando hay una foto HEIC. Un JPEG o un PNG NO se tocan, así el
 * comportamiento de lo que hoy funciona no cambia. Ante cualquier problema deja el archivo
 * original (el servidor entonces lo rechaza con un mensaje que dice qué hacer) — nunca rompe el
 * envío del formulario.
 *
 * (2026-08-24) GLOBAL: ahora se carga en el layout (todas las páginas) y engancha CUALQUIER
 * <input type=file accept*=image>. IDEMPOTENTE: registrar dos veces (layout + include viejo por
 * página) no duplica el listener. Los flujos con cableado MANUAL (scouting/riskmap) marcan sus
 * inputs con `data-cc-noauto` para que este archivo NO los toque (evita doble procesado).
 */
(function (w, d) {
    'use strict';

    if (!w.CCPhoto || typeof w.CCPhoto.process !== 'function') {
        return; // la librería base no cargó → no-op (el servidor sigue validando/rechazando)
    }

    // Idempotente: si ya se cableó (otra copia del script en la misma página), no re-registrar.
    if (w.__ccPhotoAutoWired) { return; }
    w.__ccPhotoAutoWired = true;

    var BUSY = '__ccPhotoBusy';

    function canDataTransfer() {
        try { var t = new DataTransfer(); void t.items; return true; } catch (e) { return false; }
    }

    function warn(input, on) {
        var anchor = input.parentNode;
        if (!anchor) { return; }
        var note = anchor.querySelector('.cc-heic-note');
        if (on && !note) {
            note = d.createElement('div');
            note.className = 'cc-heic-note alert alert-warning py-1 px-2 small mt-2 mb-0';
            note.textContent = 'Una imagen HEIC (formato de iPhone) no se pudo convertir en este navegador. '
                + 'Ábrela desde un iPhone/Safari, o vuelve a subirla como JPG o PNG.';
            anchor.appendChild(note);
        } else if (!on && note) {
            note.parentNode.removeChild(note);
        }
    }

    function handle(input) {
        if (input[BUSY]) { return; }
        if (!input.files || !input.files.length) { return; }

        var files = Array.prototype.slice.call(input.files);

        // Sólo actuamos si hay AL MENOS una HEIC. Si son todas JPG/PNG, no tocamos nada
        // (mismo comportamiento de siempre para las imágenes que ya funcionan).
        var hasHeic = files.some(function (f) { return w.CCPhoto.isHeic(f); });
        if (!hasHeic) { warn(input, false); return; }

        // Sin DataTransfer no se puede reconstruir el FileList → dejamos el original; el
        // servidor lo rechazará con el aviso accionable (no se pierde nada en silencio).
        if (!canDataTransfer()) { return; }

        input[BUSY] = true;
        Promise.all(files.map(function (f) {
            return w.CCPhoto.isImage(f) ? w.CCPhoto.process(f) : Promise.resolve(f);
        })).then(function (outs) {
            var dt = new DataTransfer();
            var stillHeic = false;
            outs.forEach(function (out, i) {
                dt.items.add(out);
                if (w.CCPhoto.unconverted(files[i], out)) { stillHeic = true; }
            });
            try { input.files = dt.files; } catch (e) { /* dejamos el original */ }
            warn(input, stillHeic);
            input[BUSY] = false;
        }, function () {
            input[BUSY] = false;
        });
    }

    // Delegación en captura: alcanza inputs presentes al cargar Y los creados después
    // (imágenes adicionales que se agregan con JS). El evento 'change' burbujea.
    d.addEventListener('change', function (e) {
        var t = e.target;
        // GLOBAL: cualquier input de imagen, salvo los que se cablean a mano (data-cc-noauto).
        // Se sigue aceptando el opt-in histórico data-cc-photo (que trae accept*=image igualmente).
        if (t && t.matches && t.matches('input[type="file"][accept*="image"]:not([data-cc-noauto])')) {
            handle(t);
        }
    }, true);
})(window, document);
