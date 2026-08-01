{{--
    _collapsible-sections — SECCIONES PLEGABLES + ESTADO POR SECCIÓN (captura fluida, Paso 7).

    Enfundadura NO invasiva: no reescribe las tarjetas. Se marca el <form> con
    `data-cc-sections` y este comportamiento, por mejora progresiva:
      - Añade a cada tarjeta-sección (hija DIRECTA del form) un BOTÓN chevron real
        (aria-expanded / aria-controls + etiqueta para lector). El encabezado conserva
        su rol de heading; además todo el encabezado pliega al hacer clic con el ratón/dedo.
      - Pinta un CHIP DE ESTADO por sección: Revisar (rojo, hay .is-invalid) /
        Falta (ámbar, requerido vacío) / Listo (verde, requeridos completos) /
        Con datos / Sin datos. Se recalcula al escribir.
      - Barra superior: Expandir todo · Contraer todo · resumen de pendientes.
      - Al enviar: si un campo requerido inválido cae en una sección CONTRAÍDA, la
        expande ANTES de que el navegador intente enfocarlo (captura del evento
        `invalid`), evitando el "invalid form control is not focusable".

    Sin JS igual funciona: las secciones quedan abiertas (nada se oculta con CSS solo).

    Arranque: por defecto TODAS abiertas (nada se esconde por sorpresa). Para arrancar
    contraídas —dejando abierta la 1ª y las que traen error— usa data-cc-sections="collapsed".

    Uso:  <form ... data-cc-sections> … </form>   +   @include('componentes._collapsible-sections')
--}}
@once
@push('styles')
<style>
    .cc-sec-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; margin-bottom:1rem; }
    .cc-sec-toolbar .btn { --bs-btn-padding-y:.25rem; --bs-btn-padding-x:.6rem; font-size:.8rem; }
    .cc-sec-summary { font-size:.8rem; color:var(--text-muted,#6c757d); margin-left:auto; display:inline-flex; align-items:center; gap:.35rem; }
    .cc-sec-summary .cc-sec-dot { width:.55rem; height:.55rem; border-radius:50%; background:currentColor; display:inline-block; }
    .cc-sec-summary[data-pend="0"] { color:var(--bs-success,#198754); }
    .cc-sec-summary[data-pend]:not([data-pend="0"]) { color:var(--bs-warning,#b8860b); }

    /* Encabezado plegable: mantiene su semántica de heading; el clic de ratón pliega. */
    .cc-sec-head { cursor:pointer; }
    .cc-sec-head.d-flex, .cc-sec-head { align-items:center; }

    /* Botón chevron REAL (foco de teclado va aquí, no en el heading). */
    .cc-sec-caret { flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center;
        width:1.5rem; height:1.5rem; padding:0; border:none; background:transparent; color:inherit; cursor:pointer; }
    .cc-sec-caret::before { content:""; width:0; height:0; border-left:6px solid currentColor;
        border-top:5px solid transparent; border-bottom:5px solid transparent; opacity:.9;
        transition:transform .15s ease; }
    .card:not(.cc-sec-collapsed) > .cc-sec-head .cc-sec-caret::before { transform:rotate(90deg); }
    .cc-sec-caret:focus-visible { outline:2px solid #fff; outline-offset:1px; border-radius:6px; }
    .cc-sec-collapsed > .card-body { display:none !important; }

    /* Chip de estado (sobre encabezados de color: texto claro + fondo translúcido) */
    .cc-sec-status { margin-left:auto; display:inline-flex; align-items:center; gap:.35rem; white-space:nowrap;
        font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em;
        padding:.2rem .55rem; border-radius:999px; background:rgba(255,255,255,.18); color:#fff; }
    .cc-sec-status .cc-sec-dot { width:.5rem; height:.5rem; border-radius:50%; background:currentColor; }
    .cc-sec-status[data-state="error"]   { background:rgba(220,53,69,.95); color:#fff; }
    .cc-sec-status[data-state="pending"] { background:rgba(255,193,7,.95); color:#3a2f00; }
    .cc-sec-status[data-state="ok"]      { background:rgba(25,135,84,.95); color:#fff; }
    .cc-sec-status[data-state="data"]    { background:rgba(255,255,255,.24); color:#fff; }
    .cc-sec-status[data-state="empty"]   { background:rgba(255,255,255,.12); color:rgba(255,255,255,.85); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var COLLAPSED = 'cc-sec-collapsed';
    var LBL = { error: 'Revisar', pending: 'Falta', ok: 'Listo', data: 'Con datos', empty: 'Sin datos' };
    var uid = 0;

    function isField(el) {
        var t = (el.type || '').toLowerCase();
        return t !== 'hidden' && t !== 'submit' && t !== 'button' && t !== 'reset' && t !== 'image';
    }
    function filled(el) {
        if (el.disabled) return false;
        var t = (el.type || '').toLowerCase();
        if (t === 'checkbox' || t === 'radio') return el.checked;
        if (t === 'file') return !!(el.files && el.files.length);
        return el.value != null && String(el.value).trim() !== '';
    }
    function fieldsOf(body) {
        return Array.prototype.slice.call(body.querySelectorAll('input, select, textarea')).filter(isField);
    }
    function stateOf(body) {
        if (body.querySelector('.is-invalid')) return 'error';
        var fields = fieldsOf(body);
        var req = fields.filter(function (el) { return el.required; });
        if (req.length) {
            var ok = req.filter(filled).length;
            return ok < req.length ? 'pending' : 'ok';
        }
        return fields.some(filled) ? 'data' : 'empty';
    }

    function initForm(form) {
        if (form.__ccSections) return;
        form.__ccSections = true;
        var startCollapsed = (form.getAttribute('data-cc-sections') || '') === 'collapsed';

        // Tarjetas-sección: hijas DIRECTAS con encabezado y cuerpo propios.
        var cards = Array.prototype.filter.call(form.children, function (c) {
            return c.classList && c.classList.contains('card')
                && c.querySelector(':scope > .card-header')
                && c.querySelector(':scope > .card-body');
        });
        if (!cards.length) return;

        var sections = [];

        cards.forEach(function (card, idx) {
            var header = card.querySelector(':scope > .card-header');
            var body = card.querySelector(':scope > .card-body');
            if (!body.id) body.id = 'ccsec-body-' + (++uid);

            // Botón chevron REAL (antes) — recibe el foco de teclado y controla el cuerpo.
            var caret = document.createElement('button');
            caret.type = 'button';
            caret.className = 'cc-sec-caret';
            caret.setAttribute('aria-controls', body.id);
            caret.innerHTML = '<span class="visually-hidden">Plegar o expandir la sección</span>';
            header.insertBefore(caret, header.firstChild);

            // Chip de estado (después).
            var status = document.createElement('span');
            status.className = 'cc-sec-status';
            status.innerHTML = '<span class="cc-sec-dot"></span><span class="cc-sec-status-t"></span>';
            header.appendChild(status);

            header.classList.add('cc-sec-head');

            var sec = { card: card, header: header, body: body, status: status };
            sections.push(sec);

            function refresh() {
                var st = stateOf(body);
                status.setAttribute('data-state', st);
                status.querySelector('.cc-sec-status-t').textContent = LBL[st];
                sec.state = st;
            }
            function setOpen(open) {
                card.classList.toggle(COLLAPSED, !open);
                caret.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            sec.refresh = refresh;
            sec.setOpen = setOpen;
            sec.isOpen = function () { return !card.classList.contains(COLLAPSED); };

            // Enter/Espacio sobre un <button> ya disparan click: sólo enganchamos click.
            caret.addEventListener('click', function (e) {
                e.preventDefault();
                setOpen(!sec.isOpen());
            });
            // Ratón/dedo: clic en cualquier parte del encabezado pliega, salvo controles reales
            // (el propio chevron y, p. ej., "Ver matriz" en la tabla de peligros).
            header.addEventListener('click', function (e) {
                if (e.target.closest('button, a, input, select, textarea, label, .form-check')) return;
                setOpen(!sec.isOpen());
            });

            // Estado inicial de apertura.
            var hasError = !!body.querySelector('.is-invalid');
            var open = !startCollapsed || idx === 0 || hasError;
            setOpen(open);
            refresh();
        });

        // Resumen de pendientes (secciones en error o falta).
        var summary = null;
        function updateSummary() {
            if (!summary) return;
            var pend = sections.filter(function (s) { return s.state === 'error' || s.state === 'pending'; }).length;
            summary.setAttribute('data-pend', String(pend));
            summary.querySelector('.cc-sec-summary-t').textContent = pend === 0
                ? 'Sin pendientes'
                : (pend + (pend === 1 ? ' sección por revisar' : ' secciones por revisar'));
        }

        // Barra: expandir / contraer todo + resumen.
        var bar = document.createElement('div');
        bar.className = 'cc-sec-toolbar';
        bar.innerHTML =
            '<button type="button" class="btn btn-outline-secondary" data-cc-expand>Expandir todo</button>' +
            '<button type="button" class="btn btn-outline-secondary" data-cc-collapse>Contraer todo</button>' +
            '<span class="cc-sec-summary" data-pend="0"><span class="cc-sec-dot"></span><span class="cc-sec-summary-t"></span></span>';
        form.insertBefore(bar, form.firstChild);
        summary = bar.querySelector('.cc-sec-summary');
        bar.querySelector('[data-cc-expand]').addEventListener('click', function () {
            sections.forEach(function (s) { s.setOpen(true); });
        });
        bar.querySelector('[data-cc-collapse]').addEventListener('click', function () {
            sections.forEach(function (s) { s.setOpen(false); });
        });

        // Recalcular al escribir (delegado) el estado de la sección afectada.
        function recalcFrom(target) {
            for (var i = 0; i < sections.length; i++) {
                if (sections[i].body.contains(target)) { sections[i].refresh(); break; }
            }
            updateSummary();
        }
        form.addEventListener('input', function (e) { recalcFrom(e.target); });
        form.addEventListener('change', function (e) { recalcFrom(e.target); });

        // Al validar el navegador: expande la sección del campo inválido para que sea
        // enfocable (captura, antes del enfoque nativo).
        form.addEventListener('invalid', function (e) {
            var card = e.target.closest ? e.target.closest('.card') : null;
            for (var i = 0; i < sections.length; i++) {
                if (sections[i].card === card) { sections[i].setOpen(true); break; }
            }
        }, true);

        updateSummary();
    }

    function initAll() { document.querySelectorAll('form[data-cc-sections]').forEach(initForm); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initAll); }
    else { initAll(); }
})();
</script>
@endpush
@endonce
