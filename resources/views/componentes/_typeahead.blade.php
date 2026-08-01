{{--
    _typeahead.blade.php — Buscador (typeahead/combobox) por MEJORA PROGRESIVA para
    cualquier <select class="js-typeahead"> (2026-07-13).

    Convierte un <select> largo (p. ej. los 120 eventos del catálogo) en un campo de
    búsqueda ágil para MÓVIL: escribes 2-3 letras y filtra al instante (sin acentos).
    El <select> nativo sigue siendo el control REAL (su name se envía) y el fallback si
    no hay JS. Filtra respetando los <optgroup> (contextos).

    MODO RICO (OPT-IN por data-ta-rich="1" en el <select>): 2026-07-17.
      Sin el flag el comportamiento es BIT A BIT el de hoy (así el Scouting no cambia).
      Con el flag, cada <li> del combobox añade chips de color de sus marcos normativos
      (leídos de data-badges de cada <option>) y se habilita el FILTRO POR MARCO (faceta):
        window.CCTypeahead.setFacet(selectEl, ['CSATF','OSHA',...])
      El filtro por marco es un filtro de VISTA: se INTERSECTA con el buscador de texto y
      NUNCA muta sel.value (si el evento elegido queda fuera del filtro, el valor se
      conserva). setFacet tolera llamarse ANTES de que el typeahead termine de mejorar:
      guarda el deseo en el elemento (sel._ccPendingFacet) y lo aplica al construir.

    API global para contenido dinámico (filas clonadas del Scouting):
      window.CCTypeahead.enhance(selectEl)         → mejora un select
      window.CCTypeahead.enhanceAll(root)          → mejora todos los .js-typeahead bajo root
      window.CCTypeahead.setFacet(selectEl, marcos)→ restringe por marco (solo modo rico)

    Incluir UNA vez por página (host: _event-picker o el _form del Scouting). @once evita
    duplicar el <script>/<style> si se incluye más de una vez.
--}}
@once
@push('scripts')
<style>
    .cc-ta { position: relative; }
    .cc-ta-input { padding-right: 1.8rem; }
    .cc-ta-clear { position:absolute; top:50%; right:.4rem; transform:translateY(-50%); border:none; background:transparent; font-size:1.1rem; line-height:1; color:#9aa3af; cursor:pointer; padding:0 .2rem; display:none; }
    .cc-ta-clear:hover { color:#6b7280; }
    .cc-ta.has-val .cc-ta-clear { display:block; }
    .cc-ta-list { position:absolute; z-index:1080; left:0; right:0; top:calc(100% + 2px); margin:0; padding:.25rem 0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:.5rem; box-shadow:0 8px 24px rgba(15,23,42,.14); max-height:280px; overflow-y:auto; display:none; }
    .cc-ta.open .cc-ta-list { display:block; }
    .cc-ta-group { padding:.3rem .75rem .2rem; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; background:#f8fafc; position:sticky; top:0; }
    .cc-ta-opt { padding:.42rem .75rem; font-size:.9rem; color:#1f2937; cursor:pointer; }
    .cc-ta-opt:hover, .cc-ta-opt.active { background:#eef2ff; }
    .cc-ta-empty { padding:.5rem .75rem; font-size:.85rem; color:#9aa3af; }
    /* Modo rico (data-ta-rich): etiqueta recortada por CSS (line-clamp) + fila de chips
       de marco. Los colores .badge-XXX los aporta _badge-tokens (lo incluye el picker);
       aquí solo maquetamos. El <li> sigue siendo un tap-target ≥44px. */
    .cc-ta-opt-rich { display:flex; flex-direction:column; gap:.25rem; align-items:flex-start; }
    .cc-ta-opt-label { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; line-height:1.25; width:100%; }
    .cc-ta-opt-chips { display:flex; flex-wrap:wrap; gap:.25rem; }
    @media (prefers-color-scheme: dark) {
        .cc-ta-list { background:#1f2937; border-color:#374151; }
        .cc-ta-group { background:#111827; color:#9ca3af; }
        .cc-ta-opt { color:#e5e7eb; }
        .cc-ta-opt:hover, .cc-ta-opt.active { background:#374151; }
    }
    /* Tap targets ≥44px en táctil: las opciones son <li> (no las cubre el min-height
       global de la base, que apunta a button/input) y la ✕ es diminuta. */
    @media (pointer: coarse) {
        .cc-ta-opt { padding-top:.7rem; padding-bottom:.7rem; min-height:44px; }
        .cc-ta-clear { min-width:44px; min-height:44px; font-size:1.35rem; }
    }
</style>
<script>
(function () {
    if (window.CCTypeahead) { return; }

    // Enum BLANCO de marcos: solo estos valores se pintan como chip y se usan como sufijo
    // de clase .badge-XXX (defensa XSS: los data-badges son controlados pero se validan).
    var BADGE_ENUM  = { CSATF:1, OSHA:1, STPS:1, DOT:1, SCT:1, GENERAL:1, AMAZON:1 };
    // Orden de display: DOT junto a OSHA (federales EEUU); SCT junto a STPS (México).
    var BADGE_ORDER = { CSATF:0, OSHA:1, DOT:2, STPS:3, SCT:4, GENERAL:5, AMAZON:6 };

    function norm(s) {
        return (s || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    }

    // Item base = { value, label } (comportamiento de hoy) + campos ricos ADITIVOS que el
    // Scouting simplemente no trae (sus <option> no tienen data-*), así que quedan vacíos.
    function mkItem(o) {
        var badges = (o.dataset.badges || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var seen = {}, uniq = [];
        badges.forEach(function (b) { if (!seen[b]) { seen[b] = 1; uniq.push(b); } });
        return {
            value:    o.value,
            label:    o.textContent.trim(),
            badges:   uniq,
            codes:    o.dataset.codes || '',
            category: o.dataset.category || '',
            desc:     o.dataset.desc || '',
            l:        o.dataset.l || '',
            c:        o.dataset.c || ''
        };
    }

    // Fragmento de chips de marco (validados contra el enum, ordenados, texto por
    // textContent — nunca innerHTML con datos de BD).
    function badgeChips(badges) {
        var frag = document.createDocumentFragment();
        var arr = (badges || []).filter(function (b) { return BADGE_ENUM[b]; });
        arr.sort(function (a, b) { return BADGE_ORDER[a] - BADGE_ORDER[b]; });
        arr.forEach(function (b) {
            var s = document.createElement('span');
            s.className = 'badge badge-' + b; // b ∈ BADGE_ENUM
            s.textContent = b;
            frag.appendChild(s);
        });
        return frag;
    }

    function build(sel) {
        if (!sel || sel.getAttribute('data-ta') === '1') { return; }
        sel.setAttribute('data-ta', '1');
        var rich = sel.getAttribute('data-ta-rich') === '1';

        // Extrae las opciones reales (ignora el placeholder value="").
        var groups = [], flat = [];
        Array.prototype.forEach.call(sel.children, function (node) {
            if (node.tagName === 'OPTGROUP') {
                var items = [];
                Array.prototype.forEach.call(node.children, function (o) {
                    if (o.value === '') { return; }
                    var it = mkItem(o); items.push(it); flat.push(it);
                });
                if (items.length) { groups.push({ label: node.label, items: items }); }
            } else if (node.tagName === 'OPTION' && node.value !== '') {
                var it2 = mkItem(node);
                groups.push({ label: null, items: [it2] }); flat.push(it2);
            }
        });

        var placeholder = '';
        var ph = sel.querySelector('option[value=""]');
        if (ph) { placeholder = ph.textContent.trim(); }

        // El select oculto sigue enviando su value. Quita `required` del select oculto
        // (un select display:none con required rompe la validación nativa "no focusable");
        // el gate real es server-side (Form Request).
        var wasRequired = sel.required; sel.required = false;
        sel.style.display = 'none';
        sel.setAttribute('aria-hidden', 'true');
        sel.tabIndex = -1;

        var small = /form-select-sm|form-control-sm/.test(sel.className);
        var wrap = document.createElement('div');
        wrap.className = 'cc-ta';
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control cc-ta-input' + (small ? ' form-control-sm' : '');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-autocomplete', 'list');
        input.autocomplete = 'off';
        input.placeholder = placeholder || 'Buscar…';
        if (wasRequired) { input.setAttribute('data-req', '1'); }
        var clear = document.createElement('button');
        clear.type = 'button'; clear.className = 'cc-ta-clear'; clear.setAttribute('aria-label', 'Limpiar'); clear.innerHTML = '&times;';
        var list = document.createElement('ul');
        list.className = 'cc-ta-list'; list.setAttribute('role', 'listbox');

        wrap.appendChild(input); wrap.appendChild(clear); wrap.appendChild(list);
        sel.parentNode.insertBefore(wrap, sel);

        var activeIdx = -1, visible = [], curQ = '', facets = null;

        // Faceta: un item pasa si NO hay marcos activos, o si la intersección de sus
        // marcos con los activos es no vacía. En modo no-rico `facets` queda null → true.
        function facetOk(it) {
            if (!facets || !facets.size) { return true; }
            for (var i = 0; i < it.badges.length; i++) { if (facets.has(it.badges[i])) { return true; } }
            return false;
        }

        function setVal(value, label) {
            sel.value = value;
            input.value = label || '';
            wrap.classList.toggle('has-val', value !== '');
            // Dispara change en el select real (listeners existentes: scouting, preview).
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function render(q) {
            curQ = q;
            list.innerHTML = ''; visible = []; activeIdx = -1;
            var nq = norm(q), any = false;
            groups.forEach(function (g) {
                var matched = g.items.filter(function (it) {
                    if (!(nq === '' || norm(it.label).indexOf(nq) !== -1)) { return false; }
                    return facetOk(it); // INTERSECCIÓN texto ∩ marco
                });
                if (!matched.length) { return; }
                any = true;
                if (g.label) {
                    var gl = document.createElement('li'); gl.className = 'cc-ta-group'; gl.textContent = g.label; list.appendChild(gl);
                }
                matched.forEach(function (it) {
                    var li = document.createElement('li');
                    li.className = 'cc-ta-opt'; li.setAttribute('role', 'option');
                    li.dataset.value = it.value; li.dataset.label = it.label;
                    if (rich) {
                        li.classList.add('cc-ta-opt-rich');
                        var lbl = document.createElement('span');
                        lbl.className = 'cc-ta-opt-label'; lbl.textContent = it.label; // BD → textContent
                        li.appendChild(lbl);
                        if (it.badges.length) {
                            var chips = document.createElement('span');
                            chips.className = 'cc-ta-opt-chips';
                            chips.appendChild(badgeChips(it.badges));
                            if (chips.childNodes.length) { li.appendChild(chips); }
                        }
                    } else {
                        li.textContent = it.label; // comportamiento de hoy, intacto
                    }
                    li.addEventListener('mousedown', function (e) { e.preventDefault(); setVal(it.value, it.label); close(); });
                    list.appendChild(li); visible.push(li);
                });
            });
            if (!any) {
                var em = document.createElement('li'); em.className = 'cc-ta-empty'; em.textContent = 'Sin coincidencias'; list.appendChild(em);
            }
        }

        function open() { render(''); wrap.classList.add('open'); input.setAttribute('aria-expanded', 'true'); }
        function close() { wrap.classList.remove('open'); input.setAttribute('aria-expanded', 'false'); activeIdx = -1; }
        function highlight(i) {
            visible.forEach(function (li) { li.classList.remove('active'); });
            if (i >= 0 && i < visible.length) { visible[i].classList.add('active'); visible[i].scrollIntoView({ block: 'nearest' }); }
        }

        input.addEventListener('focus', function () { open(); });
        input.addEventListener('input', function () { render(input.value); wrap.classList.add('open'); input.setAttribute('aria-expanded', 'true'); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (!wrap.classList.contains('open')) { open(); } activeIdx = Math.min(activeIdx + 1, visible.length - 1); highlight(activeIdx); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); highlight(activeIdx); }
            else if (e.key === 'Enter') { if (wrap.classList.contains('open') && activeIdx >= 0) { e.preventDefault(); var li = visible[activeIdx]; setVal(li.dataset.value, li.dataset.label); close(); } }
            else if (e.key === 'Escape') { close(); }
        });
        clear.addEventListener('click', function () { setVal('', ''); input.focus(); render(''); });
        document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) { close(); } });

        // ── API de faceta por instancia (solo tiene efecto si el select es rico; en
        //    no-rico nadie llama setFacet, así que `facets` sigue null). Re-render con la
        //    MISMA query (curQ): filtrar NO toca sel.value ni cambia lo escrito. ──
        function applyFacet(arr) {
            if (!rich) { return; } // la faceta es parte del opt-in rico: en no-rico es NO-OP
            var next = new Set();
            (arr || []).forEach(function (m) { if (BADGE_ENUM[m]) { next.add(m); } });
            facets = next;
            render(curQ);
        }
        sel._ccta = { setFacet: applyFacet, rerender: function () { render(curQ); } };
        if (sel._ccPendingFacet) { applyFacet(sel._ccPendingFacet); sel._ccPendingFacet = null; }

        // Estado inicial: refleja la opción seleccionada del select (edición).
        var selOpt = sel.options[sel.selectedIndex];
        if (selOpt && selOpt.value !== '') { input.value = selOpt.textContent.trim(); wrap.classList.add('has-val'); }
    }

    window.CCTypeahead = {
        enhance: function (sel) { build(sel); },
        enhanceAll: function (root) {
            (root || document).querySelectorAll('select.js-typeahead').forEach(build);
        },
        // Restringe por marco. NO-OP seguro si se llama antes de build(): guarda el deseo
        // en el elemento y build() lo aplica al final. No muta sel.value en ningún caso.
        setFacet: function (sel, marcos) {
            if (!sel) { return; }
            if (sel._ccta && sel._ccta.setFacet) { sel._ccta.setFacet(marcos); }
            else { sel._ccPendingFacet = marcos; }
        }
    };

    document.addEventListener('DOMContentLoaded', function () { window.CCTypeahead.enhanceAll(document); });
})();
</script>
@endpush
@endonce
