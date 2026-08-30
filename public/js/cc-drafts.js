/**
 * cc-drafts.js — BORRADORES MÚLTIPLES EN EL DISPOSITIVO + ENVÍO DIFERIDO (captura fluida).
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
 * ENVÍO DIFERIDO (Camino A · 2026-08-29): si el <form> se marca con deferOffline y se
 * envía SIN RED, el borrador se guarda en cola y, al reconectar, se REPRODUCE por la
 * MISMA ruta store() del formulario en línea (fetch a form.action). No es un protocolo
 * de sync: es un envío diferido. La validación, el ensamblado, las imágenes y el SELLO
 * corren UNA SOLA VEZ, por el único camino que existe. Idempotencia: cada borrador manda
 * su llave (X-Idempotency-Key = id del borrador) para que un reintento NO duplique; el
 * server la respalda con el middleware IdempotentReplay. Un envío que falla validación
 * conserva el borrador con su error a la vista. Los pares {n,v} guardados YA son el
 * payload del formulario (conservan los name[] y su orden) — se reproducen tal cual.
 *
 * OJO (imágenes): el borrador NO captura <input type=file>. Un envío diferido llega SIN
 * las fotos; si la forma las exige, rebota en validación (422) y el borrador se conserva
 * para completarlo con red. Al encolar una forma con archivos seleccionados se avisa.
 *
 * API pública (window.CCDrafts):
 *   .available                      -> bool (IndexedDB utilizable)
 *   .list(formType)                 -> Promise<[{id,title,updatedAt,status,...}]>  (recientes primero)
 *   .load(id)                       -> Promise<draft|null>
 *   .remove(id)                     -> Promise
 *   .serialize(formEl)              -> [{n,t,v,c}]  (orden del DOM)
 *   .restore(formEl, values, rehydrate)  aplica valores (reconstruye filas vía hook)
 *   .attach(formEl, opts)           -> handle {id(), saveNow(), setId(id)}
 *        opts: { formType, draftId, deferOffline, title(form)->string, onStatus(st), rehydrate(byName,form) }
 *   .pending()                      -> Promise<[draft]>  (los que esperan envío: status='queued')
 *   .flush()                        -> Promise  (intenta enviar la cola si hay red)
 *   .send(draft)                    -> Promise<result>  (envía uno)
 *
 * Eventos en window: 'cc-drafts:sent' {id}, 'cc-drafts:error' {id,error}, 'cc-drafts:auth' {id}.
 */
(function (w, d) {
    'use strict';

    if (w.CCDrafts) { return; }   // ya inicializado (doble include) → no re-enganchar listeners/cola.

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

    // Todos los borradores (cualquier tipo), recientes primero. Lo usa la cola de envío.
    function listAll() {
        return openDB().then(function (db) {
            return new Promise(function (resolve, reject) {
                var out = [];
                var cr = db.transaction(STORE).objectStore(STORE).openCursor();
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

    // ===== ENVÍO DIFERIDO (Camino A) ==========================================
    // Los pares {n,t,v,c} guardados YA son el payload del formulario. Aquí se reproducen
    // a x-www-form-urlencoded EXACTAMENTE como los mandaría el navegador (casillas no
    // marcadas se omiten, select-multiple manda un par por opción, name[] se conservan).

    function csrfToken() {
        var m = d.querySelector('meta[name="csrf-token"]');
        return m ? (m.getAttribute('content') || '') : '';
    }

    function hasSelectedFiles(form) {
        var yes = false;
        Array.prototype.forEach.call(form.querySelectorAll('input[type=file]'), function (inp) {
            if (inp.files && inp.files.length) { yes = true; }
        });
        return yes;
    }

    function payloadFrom(values) {
        var p = new URLSearchParams();
        (values || []).forEach(function (pair) {
            var t = pair.t;
            if (t === 'checkbox' || t === 'radio') {
                if (pair.c) { p.append(pair.n, pair.v == null ? '' : pair.v); }  // solo las marcadas
                return;
            }
            if (t === 'select-multiple' && Array.isArray(pair.v)) {
                pair.v.forEach(function (v) { p.append(pair.n, v); });
                return;
            }
            p.append(pair.n, pair.v == null ? '' : pair.v);
        });
        return p;
    }

    function flattenErrors(errors) {
        if (!errors || typeof errors !== 'object') { return ''; }
        var msgs = [];
        Object.keys(errors).forEach(function (k) {
            var v = errors[k];
            if (Array.isArray(v)) { msgs.push(v[0]); }
            else if (v) { msgs.push(String(v)); }
        });
        return msgs.join(' · ').slice(0, 400);
    }

    function emit(name, detail) {
        try { w.dispatchEvent(new CustomEvent(name, { detail: detail || {} })); } catch (e) { /* noop */ }
    }

    // Aviso mínimo y discreto (por si la cola se vacía fuera de una página de captura,
    // donde no hay bandeja escuchando). No compite con el sistema de toasts de la app.
    var _toastBox = null;
    function toast(msg, kind) {
        try {
            if (!_toastBox) {
                _toastBox = d.createElement('div');
                _toastBox.setAttribute('aria-live', 'polite');
                _toastBox.style.cssText = 'position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:2147483000;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
                d.body.appendChild(_toastBox);
            }
            var el = d.createElement('div');
            var bg = kind === 'error' ? '#8a1c1c' : (kind === 'warn' ? '#7a5b00' : '#123f3d');
            el.style.cssText = 'pointer-events:auto;max-width:88vw;background:' + bg + ';color:#fff;padding:.55rem .8rem;border-radius:10px;font:600 13px/1.35 system-ui,sans-serif;box-shadow:0 6px 20px rgba(0,0,0,.25);opacity:0;transition:opacity .2s ease;';
            el.textContent = msg;
            _toastBox.appendChild(el);
            requestAnimationFrame(function () { el.style.opacity = '1'; });
            setTimeout(function () {
                el.style.opacity = '0';
                setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 250);
            }, 4200);
        } catch (e) { /* noop */ }
    }

    // Envía UN borrador por su ruta normal. Interpreta el resultado y actualiza el borrador.
    function sendDraft(rec) {
        if (!rec || !rec.url) { return Promise.resolve({ ok: false, skip: true }); }
        var body = payloadFrom(rec.values);
        body.append('_token', csrfToken());
        return fetch(rec.url, {
            method: (rec.method || 'POST'),
            credentials: 'same-origin',
            redirect: 'manual',   // el store() redirige al show al crear; NO lo seguimos (el show puede pedir otro permiso).
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Idempotency-Key': rec.key || rec.id,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: body
        }).then(function (res) {
            // Éxito: la redirección 3xx del store (opaqueredirect con redirect:'manual') o un 2xx
            // (duplicado 200 del middleware idempotente incluido) → el reporte quedó creado y sellado.
            if (res.type === 'opaqueredirect' || res.ok) {
                return removeById(rec.id).then(function () { emit('cc-drafts:sent', { id: rec.id }); return { ok: true, id: rec.id }; });
            }
            // Sesión caída (Accept:json → 401/419) → conservar y avisar.
            if (res.status === 401 || res.status === 419) {
                rec.status = 'queued';
                return put(rec).then(function () { emit('cc-drafts:auth', { id: rec.id }); return { ok: false, auth: true, id: rec.id }; });
            }
            // Validación → conserva el borrador CON su error a la vista.
            if (res.status === 422) {
                return res.json().catch(function () { return {}; }).then(function (bodyJson) {
                    rec.status = 'error';
                    rec.error = flattenErrors(bodyJson && bodyJson.errors) || 'El reporte necesita corrección.';
                    rec.updatedAt = Date.now();
                    return put(rec).then(function () { emit('cc-drafts:error', { id: rec.id, error: rec.error }); return { ok: false, validation: true, id: rec.id, error: rec.error }; });
                });
            }
            // 5xx u otro → conserva en cola, reintenta luego.
            rec.status = 'queued';
            return put(rec).then(function () { return { ok: false, id: rec.id, httpStatus: res.status }; });
        }).catch(function () {
            // Fallo de red (seguimos offline) → sigue en cola, sin ruido.
            rec.status = 'queued';
            return put(rec).then(function () { return { ok: false, offline: true, id: rec.id }; });
        });
    }

    var _flushing = null;
    function flushPending() {
        if (!idbOK() || !w.navigator || !w.navigator.onLine) { return Promise.resolve(); }
        if (_flushing) { return _flushing; }
        var sent = 0, failed = 0;
        _flushing = listAll().then(function (all) {
            var queued = all.filter(function (r) { return r && r.status === 'queued' && r.url; });
            return queued.reduce(function (chain, rec) {
                return chain.then(function () {
                    return sendDraft(rec).then(function (r) {
                        if (r && r.ok) { sent++; }
                        else if (r && r.validation) { failed++; }
                    });
                });
            }, Promise.resolve());
        }).then(function () {
            if (sent > 0) { toast(sent === 1 ? 'Reporte pendiente enviado.' : (sent + ' reportes pendientes enviados.')); }
            if (failed > 0) { toast('Un reporte pendiente necesita corrección.', 'error'); }
        }).then(function () { _flushing = null; }, function () { _flushing = null; });
        return _flushing;
    }

    // Registro de forms con envío diferido + UN listener de captura a nivel documento,
    // para interceptar el submit OFFLINE ANTES de que corran los handlers propios del form.
    var _deferForms = [];
    function findDefer(form) {
        for (var i = 0; i < _deferForms.length; i++) { if (_deferForms[i].form === form) { return _deferForms[i]; } }
        return null;
    }
    var _submitHooked = false;
    function hookSubmitOnce() {
        if (_submitHooked) { return; }
        _submitHooked = true;
        d.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.nodeType !== 1 || form.getAttribute('data-cc-defer') !== '1') { return; }
            if (!(w.navigator && w.navigator.onLine === false)) { return; }   // en línea → envío normal, no tocamos nada.
            var entry = findDefer(form);
            if (!entry) { return; }
            e.preventDefault();
            e.stopPropagation();   // corta los handlers propios del form (compresión de fotos, etc.): offline no se envía.
            entry.enqueue();
        }, true);   // captura a nivel documento → gana ANTES de los listeners del form.
    }

    function attach(form, opts) {
        opts = opts || {};
        if (!idbOK()) { return null; }

        var formType  = opts.formType || form.getAttribute('data-cc-drafts') || 'form';
        var currentId = opts.draftId || null;
        var onStatus  = opts.onStatus || function () {};
        var titleFn   = opts.title || function () { return null; };
        var defer     = !!opts.deferOffline;

        function ensureId() { if (!currentId) { currentId = genId(formType); } return currentId; }
        function destUrl()  { return form.getAttribute('action') || w.location.href; }
        function destMethod() { return (form.getAttribute('method') || 'POST').toUpperCase(); }

        function baseRec(status, vals) {
            var rec = {
                id: ensureId(),
                form: formType,
                title: String(titleFn(form) || '').slice(0, 120),
                updatedAt: Date.now(),
                values: vals || serialize(form)
            };
            if (defer) {
                rec.url = destUrl();
                rec.method = destMethod();
                rec.key = rec.id;           // llave de idempotencia = id del borrador (estable).
                rec.status = status || 'device';
            }
            return rec;
        }

        function doSave() {
            var vals = serialize(form);
            // Sin contenido y sin borrador ya iniciado → no crear basura.
            if (!hasContent(vals) && !currentId) { return; }
            var rec = baseRec('device', vals);
            put(rec).then(function () {
                onStatus({ state: 'device', at: rec.updatedAt, id: rec.id });
            }).catch(function () {
                onStatus({ state: 'error' });
            });
        }

        // OFFLINE submit: encola con destino + llave, sin borrar. Al reconectar, la cola lo envía.
        function enqueue() {
            var files = hasSelectedFiles(form);
            var rec = baseRec('queued');
            put(rec).then(function () {
                onStatus({ state: 'queued', at: rec.updatedAt, id: rec.id, hasFiles: files });
                if (w.navigator && w.navigator.onLine) { flushPending(); }   // por si 'offline' fue un falso negativo.
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
        // Envío EN LÍNEA: comportamiento previo intacto — el borrador cumplió su función y se
        // elimina (optimista); si la validación rebota, el server repuebla con old(). El caso
        // OFFLINE lo intercepta hookSubmitOnce() ANTES (captura + stopPropagation), así que este
        // listener no corre offline.
        form.addEventListener('submit', function () {
            if (defer && w.navigator && w.navigator.onLine === false) { return; }
            if (currentId) { removeById(currentId); }
        });

        if (defer) {
            form.setAttribute('data-cc-defer', '1');
            _deferForms.push({ form: form, enqueue: enqueue });
            hookSubmitOnce();
        }

        return {
            id: function () { return currentId; },
            saveNow: doSave,
            setId: function (id) { currentId = id; }
        };
    }

    // Vaciar la cola al recuperar red y al cargar (por si quedó algo de una sesión previa).
    w.addEventListener('online', function () { flushPending(); });
    function bootFlush() { if (idbOK() && w.navigator && w.navigator.onLine) { flushPending(); } }
    if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', bootFlush); }
    else { bootFlush(); }

    w.CCDrafts = {
        available: idbOK(),
        list: listByForm,
        load: getById,
        remove: removeById,
        serialize: serialize,
        restore: restore,
        attach: attach,
        pending: function () { return listAll().then(function (all) { return all.filter(function (r) { return r && r.status === 'queued'; }); }); },
        flush: flushPending,
        send: sendDraft
    };
})(window, document);
