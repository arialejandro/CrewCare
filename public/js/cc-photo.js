/*
 * cc-photo.js — MODELO ÚNICO DE FOTO (captura fluida, Paso 9).
 *
 * Un solo procesado de imagen en el navegador para TODOS los caminos de foto de la app
 * (imagen principal y adicionales del scouting, lienzos y pines del mapeo…). Antes esta
 * lógica vivía inline y duplicada; aquí es la fuente única.
 *
 * Qué hace window.CCPhoto.process(file) -> Promise<File>:
 *   - Redimensiona a un lado máximo (MAX) y recomprime a JPEG (calidad Q).
 *   - Respeta la orientación EXIF (imageOrientation:'from-image') porque el servidor usa GD,
 *     que IGNORA el EXIF: si no se hornea aquí, la foto sale girada.
 *   - HEIC/HEIF (formato nativo de iPhone): el servidor (GD, sin libheif) NO lo lee. Safari
 *     SÍ decodifica HEIC vía createImageBitmap -> se reencoda a JPEG aquí y queda resuelto.
 *     Chrome/Firefox NO lo decodifican; si el owner autoaloja `window.heic2any` lo usamos,
 *     y si no, se devuelve el original y la UI avisa (CCPhoto.unconverted()).
 *   - Best-effort: ante CUALQUIER problema devuelve el archivo ORIGINAL — nunca rompe la subida.
 *
 * NO depende de nada externo. Idempotente: recargarlo sólo reasigna window.CCPhoto.
 */
(function (w) {
    'use strict';

    var MAX = 2200;   // lado máximo en px (igual al valor histórico del scouting)
    var Q = 0.82;     // calidad JPEG
    var HEIC_MIME = { 'image/heic': 1, 'image/heif': 1, 'image/heic-sequence': 1, 'image/heif-sequence': 1 };

    function isHeic(file) {
        if (!file) return false;
        if (file.type && HEIC_MIME[String(file.type).toLowerCase()]) return true;
        return /\.(heic|heif)$/i.test(file.name || '');
    }
    function isImage(file) {
        if (!file) return false;
        return (file.type && String(file.type).indexOf('image/') === 0) || isHeic(file);
    }
    function jpegName(file) {
        return String(file && file.name ? file.name : 'imagen').replace(/\.[^.]+$/, '') + '.jpg';
    }
    function toFile(blob, srcFile) {
        try { return new File([blob], jpegName(srcFile), { type: 'image/jpeg', lastModified: srcFile.lastModified }); }
        catch (e) { return null; }
    }

    // Dibuja un bitmap ya decodificado a un JPEG con tope de tamaño. Devuelve File o null.
    function bitmapToJpeg(bmp, srcFile) {
        return new Promise(function (resolve) {
            try {
                var w0 = bmp.width, h0 = bmp.height;
                var big = Math.max(w0, h0) > MAX;
                var scale = big ? MAX / Math.max(w0, h0) : 1;
                var nw = Math.round(w0 * scale), nh = Math.round(h0 * scale);
                var canvas = document.createElement('canvas');
                canvas.width = nw; canvas.height = nh;
                canvas.getContext('2d').drawImage(bmp, 0, 0, nw, nh);
                if (bmp.close) bmp.close();
                canvas.toBlob(function (blob) {
                    resolve(blob ? toFile(blob, srcFile) : null);
                }, 'image/jpeg', Q);
            } catch (e) { resolve(null); }
        });
    }

    // Camino opcional para navegadores que NO decodifican HEIC (Chrome/Firefox): si el owner
    // autoaloja heic2any (window.heic2any), lo usamos. Si no existe, devuelve null (fallback+aviso).
    function viaHeic2any(file) {
        if (typeof w.heic2any !== 'function') { return Promise.resolve(null); }
        try {
            return w.heic2any({ blob: file, toType: 'image/jpeg', quality: Q }).then(function (out) {
                var blob = Array.isArray(out) ? out[0] : out;
                return blob ? toFile(blob, file) : null;
            }).catch(function () { return null; });
        } catch (e) { return Promise.resolve(null); }
    }

    function process(file) {
        return new Promise(function (resolve) {
            // No-imagen o GIF (posible animación) → sin tocar.
            if (!isImage(file) || file.type === 'image/gif') { return resolve(file); }

            if (typeof createImageBitmap !== 'function') {
                if (isHeic(file)) { return viaHeic2any(file).then(function (r) { resolve(r || file); }); }
                return resolve(file);
            }

            var p;
            try { p = createImageBitmap(file, { imageOrientation: 'from-image' }); }
            catch (e) { p = createImageBitmap(file); } // navegadores sin la opción de orientación

            Promise.resolve(p).then(function (bmp) {
                var big = Math.max(bmp.width, bmp.height) > MAX;
                // Ya chica, liviana y NO-HEIC → no vale la pena recomprimir. (El HEIC SIEMPRE se
                // reencoda a JPEG aunque sea chico, porque el servidor no lo puede leer.)
                if (!big && !isHeic(file) && file.size <= 2 * 1024 * 1024) {
                    if (bmp.close) bmp.close();
                    return resolve(file);
                }
                bitmapToJpeg(bmp, file).then(function (out) {
                    if (!out) { return resolve(file); }
                    // No-HEIC: si no mejoró el peso, conserva el original. HEIC: siempre el JPEG.
                    if (!isHeic(file) && out.size >= file.size) { return resolve(file); }
                    resolve(out);
                });
            }, function () {
                // createImageBitmap no decodificó (típico: HEIC fuera de Safari).
                if (isHeic(file)) { return viaHeic2any(file).then(function (r) { resolve(r || file); }); }
                resolve(file);
            });
        });
    }

    w.CCPhoto = {
        MAX: MAX,
        process: process,
        isHeic: isHeic,
        isImage: isImage,
        // ¿El resultado sigue siendo HEIC sin convertir? (para que la UI avise, sin romper la subida)
        unconverted: function (original, result) { return isHeic(original) && isHeic(result); }
    };
})(window);
