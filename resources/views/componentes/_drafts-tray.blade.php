{{--
    _drafts-tray — BORRADORES MÚLTIPLES EN EL DISPOSITIVO (captura fluida · Paso A).
    Panel compartido: lista los borradores guardados de ESTE formulario, retoma cualquiera,
    empieza uno nuevo y los borra. Estado honesto: viven "en este dispositivo".

    Requiere public/js/cc-drafts.js (se carga aquí, @once) y que el <form> lleve
    data-cc-drafts="TIPO". Un formulario con filas dinámicas propias (la tabla de peligros
    del scouting) registra su reconstructor en window.CCDraftRehydrate['TIPO'] = fn(buckets, form).

    Params:
      $draftType  string   clave de agrupación; debe = data-cc-drafts del <form>
      $formSel    string?  selector CSS del <form> (default: form[data-cc-drafts])
--}}
@php $formSel = $formSel ?? 'form[data-cc-drafts]'; @endphp

<div class="cc-drafts" data-draft-type="{{ $draftType }}" data-form-sel="{{ $formSel }}" hidden>
    <div class="cc-drafts-bar">
        @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico'])
        <span class="cc-drafts-status" data-el="status">Borrador en este dispositivo</span>
        <span class="cc-drafts-spacer"></span>
        <button type="button" class="btn btn-sm btn-link cc-drafts-toggle" data-el="toggle" aria-expanded="false">
            Ver borradores (<span data-el="count">0</span>)
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-el="new">Nuevo</button>
    </div>
    <ul class="cc-drafts-list" data-el="list" hidden></ul>
</div>

@once
@push('styles')
<style>
    .cc-drafts { border:1px solid var(--border,#dee2e6); border-radius:12px; background:var(--surface-2,#f8f9fa);
        padding:.5rem .7rem; margin-bottom:1rem; }
    .cc-drafts-bar { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
    .cc-drafts-status { font-size:.85rem; color:var(--text-muted,#6c757d); font-weight:600; }
    .cc-drafts-spacer { flex:1 1 auto; }
    .cc-drafts-toggle { text-decoration:none; }
    .cc-drafts-list { list-style:none; margin:.5rem 0 0; padding:0; display:flex; flex-direction:column; gap:.25rem; }
    .cc-draft-item { display:flex; align-items:center; gap:.5rem; padding:.35rem .4rem; border-radius:8px; }
    .cc-draft-item + .cc-draft-item { border-top:1px solid var(--border,#eee); }
    .cc-draft-item.current { background:color-mix(in srgb, var(--brand-primary,#0e6f6c) 10%, transparent); }
    .cc-draft-open { flex:1 1 auto; min-width:0; text-align:left; background:transparent; border:0; color:var(--text,#14181f);
        font-size:.9rem; cursor:pointer; padding:.3rem .2rem; min-height:40px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cc-draft-open:hover { text-decoration:underline; }
    .cc-draft-when { font-size:.72rem; color:var(--text-muted,#6c757d); white-space:nowrap; }
    .cc-draft-del { background:transparent; border:0; color:var(--bs-danger,#c0392b); font-size:1.1rem; line-height:1;
        cursor:pointer; min-width:40px; min-height:40px; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/cc-drafts.js') }}"></script>
<script>
(function () {
    function fmt(ts) { try { return new Date(ts).toLocaleString(); } catch (e) { return ''; } }

    function initBox(box) {
        if (box.__ccDraftsInit) { return; }
        box.__ccDraftsInit = true;
        if (!window.CCDrafts || !window.CCDrafts.available) { return; }

        var type = box.getAttribute('data-draft-type');
        var form = document.querySelector(box.getAttribute('data-form-sel'));
        if (!form) { return; }
        var rehydrate = (window.CCDraftRehydrate || {})[type] || null;

        var elStatus = box.querySelector('[data-el="status"]');
        var elCount  = box.querySelector('[data-el="count"]');
        var elList   = box.querySelector('[data-el="list"]');
        var elToggle = box.querySelector('[data-el="toggle"]');
        var elNew    = box.querySelector('[data-el="new"]');

        var handle = window.CCDrafts.attach(form, {
            formType: type,
            title: function (f) {
                var t = f.querySelector('[data-draft-title]');
                return (t && t.value) ? t.value : '';
            },
            onStatus: function (st) { showStatus(st); refreshList(); }
        });
        if (!handle) { return; }

        function showStatus(st) {
            if (!elStatus) { return; }
            if (st && st.state === 'device') { elStatus.textContent = 'Guardado en este dispositivo · ' + fmt(st.at); }
            else if (st && st.state === 'error') { elStatus.textContent = 'No se pudo guardar en el dispositivo'; }
        }

        function isBlank(f) {
            var blank = true;
            Array.prototype.forEach.call(f.elements, function (el) {
                if (!el.name) { return; }
                var t = (el.type || '').toLowerCase();
                if (t === 'checkbox' || t === 'radio' || t === 'file' || t === 'hidden' ||
                    t === 'submit' || t === 'button' || t === 'reset') { return; }
                if (el.value && String(el.value).trim() !== '') { blank = false; }
            });
            return blank;
        }

        function openDraft(d) {
            window.CCDrafts.restore(form, d.values, rehydrate);
            handle.setId(d.id);
            showStatus({ state: 'device', at: d.updatedAt });
            elList.hidden = true;
            if (elToggle) { elToggle.setAttribute('aria-expanded', 'false'); }
            refreshList();
        }

        function refreshList() {
            window.CCDrafts.list(type).then(function (items) {
                if (elCount) { elCount.textContent = items.length; }
                // Muestra el panel si hay borradores O si ya se está escribiendo uno.
                box.hidden = (items.length === 0 && !handle.id());
                if (!elList) { return; }
                elList.innerHTML = '';
                items.forEach(function (d) {
                    var li = document.createElement('li');
                    li.className = 'cc-draft-item' + (d.id === handle.id() ? ' current' : '');

                    var open = document.createElement('button');
                    open.type = 'button'; open.className = 'cc-draft-open';
                    open.textContent = d.title ? d.title : 'Borrador sin título';

                    var when = document.createElement('span');
                    when.className = 'cc-draft-when'; when.textContent = fmt(d.updatedAt);

                    var del = document.createElement('button');
                    del.type = 'button'; del.className = 'cc-draft-del';
                    del.setAttribute('aria-label', 'Borrar borrador'); del.textContent = '×';

                    open.addEventListener('click', function () { openDraft(d); });
                    del.addEventListener('click', function () {
                        window.CCDrafts.remove(d.id).then(function () {
                            if (d.id === handle.id()) { handle.setId(null); }
                            refreshList();
                        });
                    });
                    li.appendChild(open); li.appendChild(when); li.appendChild(del);
                    elList.appendChild(li);
                });
            });
        }

        if (elToggle) {
            elToggle.addEventListener('click', function () {
                var open = !elList.hidden;
                elList.hidden = open;
                elToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            });
        }
        if (elNew) {
            // Empezar limpio y de forma fiable: recarga la forma con ?fresh=1 (DOM nuevo,
            // sin filas dinámicas colgando). El init de abajo NO auto-retoma cuando ve fresh=1.
            elNew.addEventListener('click', function () {
                if (/[?&]fresh=1\b/.test(location.search)) { location.reload(); }
                else { location.href = location.pathname + (location.search ? location.search + '&' : '?') + 'fresh=1'; }
            });
        }

        // Auto-retomar el más reciente si la forma está en blanco (continuidad: "al volver está").
        var fresh = /[?&]fresh=1\b/.test(location.search);
        window.CCDrafts.list(type).then(function (items) {
            if (!fresh && items.length && isBlank(form)) { openDraft(items[0]); }
            else { refreshList(); }
        });
    }

    function initAll() { document.querySelectorAll('.cc-drafts[data-draft-type]').forEach(initBox); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initAll); }
    else { initAll(); }
})();
</script>
@endpush
@endonce
