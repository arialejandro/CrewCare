/**
 * cc-drafts.js — BORRADORES MÚLTIPLES EN EL DISPOSITIVO (captura fluida · Paso A).
 * Autoalojado (como cc-photo.js / crewcare-geo.js). Sin dependencias.
 *
 * POR QUÉ: un scouting se llena CAMINANDO, a veces sin señal. El borrador no es una
 * red de seguridad: es cómo se llena esto de verdad. Hoy el autoguardado vive en
 * localStorage con UN SOLO slot por formulario (se sobrescribe) y además COLAPSA los
 * campos repetibles name[] (solo sobrevive la última fila). Esto lo reemplaza:
 *
 *   - VARIOS borradores a la vez, por tipo de formulario, y se retoma cualquiera.
 *   - Vive en IndexedDB (sobrevive al cierre del navegador y a la falta de red).
 *   - Serializa el ORDEN del DOM y CONSERVA los duplicados de name[] (filas repetibles).
 *   - Al restaurar, deja que el formulario anfitrión RECONSTRUYA sus filas dinámicas
 *     (hook `rehydrate`) ANTES de asignar valores.
 *
 * ALCANCE (lo dijo el owner): que el FORMULARIO no se pierda. El envío offline de un
 * reporte COMPLETO (vía /api/sync/up) es OTRA pieza y se reporta aparte — este archivo
 * NO habla con el servidor. El estado honesto de hoy es "en este dispositivo".
 *
 * API pública (window.CCDrafts):
 *   .available                      -> bool (IndexedDB utilizable)
 *   .list(formType)                 -> Promise<[{id,title,updatedAt,...}]>  (recientes primero)
 *   .load(id)                       -> Promise<draft|null>
 *   .remove(id)                     -> Promise
 *   .serialize(formEl)              -> [{n,t,v,c}]  (orden del DOM)
 *   .restore(formEl, values, rehydrate)  aplica valores (reconstruye filas vía hook)
 *   .attach(formEl, opts)           -> handle {id(), saveNow(), setId(id)}
 *        opts: { formType, draftId, title(form)->string, onStatus(st), rehydrate(byName,form) }
 */
(function (w, d) {
    'use strict';

    var DB_NAME = 'crewcare-drafts';
    var STORE   = 'drafts';
    var VERSION = 1;
    var _dbp = null;

    function idbOK() {
        try { return !!(w.indexedDB); } catch (e) { return false; }
    }

    function openDB() {
        if (_dbp) { return _dbp; }
        _dbp = new Promise(function (resolve, reject) {
            var rq = w.indexedDB.open(DB_NAME, VERSION);
            rq.onupgradeneeded = function (e) {
                var db = e.target.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    var os = db.createObjectStore(STORE, { keyPath: 'id' });
                    os.createIndex('form', 'form', { unique: false });
                }
            };
            rq.onsuccess = function () { resolve(rq.result); };
            rq.onerror   = function () { reject(rq.error); };
        });
        return _dbp;
    }

    function put(rec) {
        return openDB().then(function (db) {
            return new Promise(function (resolve, reject) {
                var t = db.transaction(STORE, 'readwrite');
                t.objectStore(STORE).put(rec);
                t.oncomplete = function () { resolve(rec); };
                t.onerror    = function () { reject(t.error); };
                t.onabort    = function () { reject(t.error); };
            });
        });
    }

    function getById(id) {
        return openDB().then(function (db) {
            return new Promise(function (resolve, reject) {
                var r = db.transaction(STORE).objectStore(STORE).get(id);
                r.onsuccess = function () { resolve(r.result || null); };
                r.onerror   = function () { reject(r.error); };
            });
        });
    }

    function removeById(id) {
        return openDB().then(function (db) {
            return new Promise(function (resolve, reject) {
                var t = db.transaction(STORE, 'readwrite');
                t.objectStore(STORE).delete(id);
                t.oncomplete = function () { resolve(true); };
                t.onerror    = function () { reject(t.error); };
            });
        });
    }

    function listByForm(formType) {
        return openDB().then(function (db) {
            return new Promise(function (resolve, reject) {
                var out = [];
                var idx = db.transaction(STORE).objectStore(STORE).index('form');
                var cr  = idx.openCursor(IDBKeyRange.only(formType));
                cr.onsuccess = function (e) {
                    var c = e.target.result;
                    if (c) { out.push(c.value); c.continue(); }
                    else {
                        out.sort(function (a, b) { return (b.updatedAt || 0) - (a.updatedAt || 0); });
                        resolve(out);
                    }
                };
                cr.onerror = function () { reject(cr.error); };
            });
        });
    }

    // --- Serialización (orden del DOM; conserva duplicados de name[]) -------------

    function savable(el) {
        if (!el.name || el.name === '_token') { return false; }
        var t = (el.type || '').toLowerCase();
        if (t === 'file' || t === 'password' || t === 'submit' || t === 'button' || t === 'reset') { return false; }
        if (el.disabled) { return false; }
        return true;
    }

    function serialize(form) {
        var out = [];
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!savable(el)) { return; }
            var t = (el.type || '').toLowerCase();
            if (t === 'checkbox' || t === 'radio') {
                out.push({ n: el.name, t: t, v: el.value, c: el.checked ? 1 : 0 });
            } else if (el.tagName === 'SELECT' && el.multiple) {
                var vals = Array.prototype.filter.call(el.options, function (o) { return o.selected; })
                    .map(function (o) { return o.value; });
                out.push({ n: el.name, t: 'select-multiple', v: vals });
            } else {
                out.push({ n: el.name, t: t, v: el.value });
            }
        });
        return out;
    }

    // ¿El formulario trae contenido de texto? (para no guardar un borrador vacío).
    function hasContent(values) {
        return values.some(function (p) {
            if (p.t === 'checkbox' || p.t === 'radio') { return false; }
            if (Array.isArray(p.v)) { return p.v.length > 0; }
            return p.v && String(p.v).trim() !== '';
        });
    }

    // Reconstruye los grupos [data-cc-repeat] (viabilidad, acuerdos…) creando tantas
    // filas como valores guardados haya, ANTES de asignar. Convención compartida con
    // componentes/_repeatable-rows: [data-cc-repeat] > [data-cc-repeat-body] + template.
    function rebuildRepeatables(form, buckets) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-cc-repeat]'), function (group) {
            var body = group.querySelector('[data-cc-repeat-body]');
            var tpl  = group.querySelector('template[data-cc-repeat-tpl]');
            if (!body || !tpl || !tpl.content) { return; }
            // Cuántas filas hacen falta = mayor longitud entre los name[] del template.
            var need = 0;
            Array.prototype.forEach.call(tpl.content.querySelectorAll('[name]'), function (el) {
                if (el.name && buckets[el.name]) { need = Math.max(need, buckets[el.name].length); }
            });
            var have = body.querySelectorAll('[data-cc-repeat-row]').length;
            for (var i = have; i < need; i++) { body.appendChild(tpl.content.cloneNode(true)); }
        });
    }

    function restore(form, values, rehydrate) {
        if (!values || !values.length) { return; }

        // Agrupa los pares guardados por name (conserva el orden guardado).
        var buckets = {};
        values.forEach(function (p) { (buckets[p.n] = buckets[p.n] || []).push(p); });

        // 1a) Reconstruye grupos repetibles genéricos (convención [data-cc-repeat]).
        rebuildRepeatables(form, buckets);

        // 1b) El anfitrión reconstruye SUS filas dinámicas propias (p.ej. tabla de
        //     peligros del scouting) ANTES de asignar (hook opcional).
        if (typeof rehydrate === 'function') {
            try { rehydrate(buckets, form); } catch (e) { /* best-effort */ }
        }

        // Pre-clasifica cada name de checkbox/radio:
        //   - 'pos' : todas las entradas comparten value (una casilla por FILA, p.ej. via_ok[]="1")
        //             → se consumen por POSICIÓN, porque el value no distingue las filas.
        //   - 'val' : valores distintos (un GRUPO por valor, p.ej. activity_type[] armas/pirotecnia)
        //             → se emparejan por value.
        var cbMode = {};
        Object.keys(buckets).forEach(function (name) {
            var list = buckets[name];
            if (!list.some(function (p) { return p.t === 'checkbox' || p.t === 'radio'; })) { return; }
            var seen = {};
            list.forEach(function (p) { seen[String(p.v)] = true; });
            cbMode[name] = (Object.keys(seen).length <= 1) ? 'pos' : 'val';
        });

        // 2) Asigna por name en orden del DOM, consumiendo la lista guardada en secuencia.
        //    Así el i-ésimo <input name="x[]"> recibe el i-ésimo valor guardado.
        var cursor = {};
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!savable(el)) { return; }
            var name = el.name;
            var list = buckets[name];
            if (!list) { return; }
            var t = (el.type || '').toLowerCase();

            if (t === 'checkbox' || t === 'radio') {
                if (cbMode[name] === 'pos') {
                    var ci = cursor[name] || 0;
                    if (ci < list.length) { el.checked = !!list[ci].c; cursor[name] = ci + 1; }
                } else {
                    for (var i = 0; i < list.length; i++) {
                        if (String(list[i].v) === String(el.value)) { el.checked = !!list[i].c; break; }
                    }
                }
            } else {
                var idx = cursor[name] || 0;
                if (idx < list.length) {
                    var pair = list[idx];
                    cursor[name] = idx + 1;
                    if (t === 'select-multiple' && Array.isArray(pair.v)) {
                        Array.prototype.forEach.call(el.options, function (o) {
                            o.selected = pair.v.indexOf(o.value) !== -1;
                        });
                    } else {
                        el.value = pair.v;
                    }
                }
            }
            // Notifica a listeners (typeahead, previews, contadores, auto Prob/Cons).
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function genId(formType) {
        return formType + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
    }

    function attach(form, opts) {
        opts = opts || {};
        if (!idbOK()) { return null; }

        var formType  = opts.formType || form.getAttribute('data-cc-drafts') || 'form';
        var currentId = opts.draftId || null;
        var onStatus  = opts.onStatus || function () {};
        var titleFn   = opts.title || function () { return null; };

        function ensureId() { if (!currentId) { currentId = genId(formType); } return currentId; }

        function doSave() {
            var vals = serialize(form);
            // Sin contenido y sin borrador ya iniciado → no crear basura.
            if (!hasContent(vals) && !currentId) { return; }
            var rec = {
                id: ensureId(),
                form: formType,
                title: String(titleFn(form) || '').slice(0, 120),
                updatedAt: Date.now(),
                values: vals
            };
            put(rec).then(function () {
                onStatus({ state: 'device', at: rec.updatedAt, id: rec.id });
            }).catch(function () {
                onStatus({ state: 'error' });
            });
        }

        var deb = null;
        form.addEventListener('input', function () {
            if (deb) { clearTimeout(deb); }
            deb = setTimeout(doSave, 400);
        });
        form.addEventListener('change', function () {
            if (deb) { clearTimeout(deb); }
            deb = setTimeout(doSave, 150);
        });
        // Al enviar, el borrador cumplió su función → se elimina (optimista). Si la
        // validación rebota, el server repuebla con old() y se formará uno nuevo al editar.
        form.addEventListener('submit', function () {
            if (currentId) { removeById(currentId); }
        });

        return {
            id: function () { return currentId; },
            saveNow: doSave,
            setId: function (id) { currentId = id; }
        };
    }

    w.CCDrafts = {
        available: idbOK(),
        list: listByForm,
        load: getById,
        remove: removeById,
        serialize: serialize,
        restore: restore,
        attach: attach
    };
})(window, document);
