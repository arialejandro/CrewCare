/*
 * cc-scouting-draft.js — BORRADOR DE SCOUTING EN SERVIDOR.
 *
 * ── POR QUÉ EXISTE ───────────────────────────────────────────────────────────────────────────
 * El 2026-09-14, con la producción rodando, se perdió un scouting con más de 20 fotos al cerrarse
 * la vista por error. El borrador local (cc-drafts.js, IndexedDB) guardaba el TEXTO, pero su
 * propia cabecera lo advierte: «el borrador NO captura <input type=file>». Las fotos vivían sólo
 * en la memoria de esa pestaña; al cerrarla, se fueron.
 *
 * Aquí las fotos dejan de depender de la pestaña: se suben EN CUANTO se capturan. Si el navegador
 * se cierra, se recarga o se queda sin batería, el trabajo ya está en el servidor.
 *
 * ── EL OTRO EFECTO, QUE TAMBIÉN SE PIDIÓ ────────────────────────────────────────────────────
 * Antes las 20 fotos viajaban TODAS JUNTAS al dar guardar: ~30 MB de golpe, con la barra clavada
 * cerca de un minuto. Subiéndolas mientras se camina, el guardado final sólo manda texto y rutas.
 * Y van EN LOTE, no una por una: con la red de un set, una petición por foto multiplica la espera.
 *
 * ── LA RED DEBAJO DE LA RED ─────────────────────────────────────────────────────────────────
 * Si la subida falla (sin señal), NO se pierde nada ni se interrumpe la captura: la foto se queda
 * en el formulario y se reintenta sola al volver la red. Y si nunca vuelve, viaja por el camino
 * normal al guardar, como siempre. Los dos caminos terminan en el mismo sitio; nunca en ninguno.
 *
 * Sin dependencias, autoalojado y CSP-safe (archivo externo, sin handlers en línea).
 */
(function (w, d) {
    'use strict';

    var SAVE_URL    = '/scoutings/draft';
    var PHOTOS_URL  = '/scoutings/draft/photos';
    var DISCARD_URL = '/scoutings/draft/discard';
    var KEY_STORE   = 'cc-scouting-draft-key';

    function token() {
        var m = d.querySelector('meta[name=csrf-token]');
        return m ? m.getAttribute('content') : '';
    }

    /* ---------------------------------------------------------------- *
     *  Clave del borrador
     *
     *  Identifica ESTA captura entre las varias que alguien puede llevar
     *  a la vez en un día de scouting. Vive en localStorage para que
     *  recargar (o que se caiga el navegador) siga apuntando al MISMO
     *  borrador — que es justo lo que permite recuperar las fotos.
     *  El servidor siempre la acota por autor, así que adivinarla no
     *  da acceso al borrador de nadie más.
     * ---------------------------------------------------------------- */
    function newKey() {
        return 'sd-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    function key() {
        var k = null;
        try { k = w.localStorage.getItem(KEY_STORE); } catch (e) { /* modo privado */ }
        if (!k) {
            k = newKey();
            try { w.localStorage.setItem(KEY_STORE, k); } catch (e) { /* sin persistencia: sirve igual en esta sesión */ }
        }
        return k;
    }

    function resetKey() {
        try { w.localStorage.removeItem(KEY_STORE); } catch (e) { /* nada que limpiar */ }
    }

    /* ---------------------------------------------------------------- *
     *  Autoguardado de los CAMPOS (el texto; las fotos van aparte)
     * ---------------------------------------------------------------- */
    function serialize(form) {
        if (w.CCDrafts && typeof w.CCDrafts.serialize === 'function') {
            return w.CCDrafts.serialize(form);   // misma serialización que el borrador local
        }
        return [];
    }

    /**
     * Fotos que ya viajaron y siguen esperando en el borrador de esta persona.
     *
     * 🪤 Sin esto, subir las fotos era una TRAMPA en vez de una red: el 2026-09-16 se capturaron
     * 27 que subieron bien y no había forma de que volvieran a un scouting. Se guardó vacío y las
     * fotos quedaron vivas en disco, sin dueño. Guardar sin recuperar es peor que no guardar,
     * porque promete una protección que no existe.
     */
    function restore() {
        return fetch(SAVE_URL + '?client_key=' + encodeURIComponent(key()), {
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (j) { return (j && j.photos) || []; })
          .catch(function () { return []; });
    }

    function save(form) {
        return fetch(SAVE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token(), 'Accept': 'application/json' },
            body: JSON.stringify({ client_key: key(), values: serialize(form) })
        }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
    }

    /* ---------------------------------------------------------------- *
     *  Subida de fotos EN LOTE
     *
     *  Devuelve {saved:[{path,...}], failed:n}. Las que fallen NO se dan
     *  por perdidas: quien llama las conserva y se reintentan.
     * ---------------------------------------------------------------- */
    function upload(files) {
        var list = Array.prototype.slice.call(files || []);
        if (!list.length) { return Promise.resolve({ saved: [], failed: 0 }); }

        var fd = new FormData();
        fd.append('client_key', key());
        fd.append('_token', token());
        list.forEach(function (f) { fd.append('photos[]', f, f.name || 'foto.jpg'); });

        return fetch(PHOTOS_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json' },
            body: fd
        }).then(function (r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.json();
        });
    }

    function discard() {
        var k = key();
        resetKey();
        return fetch(DISCARD_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token(), 'Accept': 'application/json' },
            body: JSON.stringify({ client_key: k })
        }).catch(function () { /* si no se pudo avisar, el borrador caduca solo */ });
    }

    /* ---------------------------------------------------------------- *
     *  Cola de reintento (en memoria)
     *
     *  Deliberadamente NO persiste los blobs: si la pestaña muere antes
     *  de que suban, esas fotos concretas se pierden igual que hoy — pero
     *  TODAS las anteriores ya están a salvo, que es la diferencia entre
     *  perder una foto y perder la jornada. Persistirlas sería duplicar
     *  IndexedDB de cc-drafts; se hará ahí si hace falta.
     * ---------------------------------------------------------------- */
    var queue = [];
    var draining = false;

    function enqueue(item) {
        queue.push(item);           // {files:[File], onDone(saved), onFail()}
        scheduleDrain(4000);
    }

    function scheduleDrain(ms) {
        if (draining) { return; }
        draining = true;
        setTimeout(function () { draining = false; drain(); }, ms);
    }

    function drain() {
        if (!queue.length) { return; }
        if (w.navigator && w.navigator.onLine === false) { scheduleDrain(8000); return; }

        var item = queue.shift();
        upload(item.files).then(function (res) {
            if (item.onDone) { item.onDone(res.saved || []); }
            if (queue.length) { scheduleDrain(500); }
        }).catch(function () {
            queue.unshift(item);       // sigue pendiente, no se descarta
            if (item.onFail) { item.onFail(); }
            scheduleDrain(10000);
        });
    }

    // Al recuperar la red, reintenta enseguida en vez de esperar al siguiente ciclo.
    w.addEventListener('online', function () { scheduleDrain(300); });

    w.CCScoutDraft = {
        key: key,
        resetKey: resetKey,
        restore: restore,
        save: save,
        upload: upload,
        enqueue: enqueue,
        discard: discard,
        pending: function () { return queue.length; }
    };
})(window, document);
