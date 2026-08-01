{{--
    _catalog-tags — CAMPO DE ETIQUETAS con catálogo (captura fluida).
    Fuente ÚNICA reutilizable: un input de texto que muestra un catálogo (teclea para
    filtrar), agrega lo elegido como CHIP, y —si se permite— acepta texto libre. Con
    botón "Agregar" y chips quitables. Reemplaza rejillas de casillas dispersas.

    - Teclear FILTRA el catálogo (la lista siempre está, escribir la acorta).
    - Multi-selección sin cerrar; objetivo táctil grande; una mano.
    - ⚠ NO auto-foca (en móvil abriría el teclado de golpe). El menú abre al tocar el campo.
    - Post: inputs ocultos name[] por chip (mismo contrato que las casillas de antes).
    - Hook externo: dispara `cc:catalog-add` {value} sobre la raíz para agregar por código.

    Params:
      - $name        (string)  base del POST, p.ej. 'safety_meeting_topics'
      - $options     (array)   catálogo value => label
      - $groups      (array|null) opcional: etiquetaGrupo => [values] para agrupar el menú
      - $selected    (array)   valores preseleccionados (old/edición)
      - $allowCustom (bool)    permitir texto libre fuera del catálogo (default false)
      - $placeholder (string)
      - $tagsId      (string)  id único
--}}
@php
    $tagsId = $tagsId ?? ('ctags-' . \Illuminate\Support\Str::random(6));
    $options = $options ?? [];
    $groups = $groups ?? null;
    $selected = array_values(array_filter((array) ($selected ?? []), function ($v) { return $v !== null && $v !== ''; }));
    $allowCustom = $allowCustom ?? false;
    $placeholder = $placeholder ?? 'Escribe o elige…';
@endphp

<div class="ctags" id="{{ $tagsId }}" data-catalog-tags data-name="{{ $name }}">
    <div class="ctags-chips" data-el="chips">
        @foreach($selected as $val)
            @php $lbl = $options[$val] ?? $val; @endphp
            <span class="ctags-chip">
                <span class="ctags-chip-t">{{ $lbl }}</span>
                <button type="button" class="ctags-chip-x" aria-label="Quitar">&times;</button>
                <input type="hidden" name="{{ $name }}[]" value="{{ $val }}">
            </span>
        @endforeach
    </div>
    <div class="ctags-inputrow">
        <input type="text" class="form-control ctags-search" data-el="search" autocomplete="off" placeholder="{{ $placeholder }}">
        <button type="button" class="btn btn-outline-secondary ctags-add" data-el="add">Agregar</button>
    </div>
    <div class="ctags-menu" data-el="menu" hidden></div>
</div>

<script type="application/json" data-catalog-tags-data="{{ $tagsId }}">@json(['options' => $options, 'groups' => $groups, 'allowCustom' => (bool) $allowCustom])</script>

@once
@push('styles')
<style>
    .ctags { position:relative; }
    .ctags-chips { display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:.4rem; }
    .ctags-chip { display:inline-flex; align-items:center; gap:.3rem; background:color-mix(in srgb,var(--brand-primary,#0e6f6c) 12%,var(--surface,#fff));
        color:var(--text,#14181f); border:1px solid color-mix(in srgb,var(--brand-primary,#0e6f6c) 30%,transparent);
        border-radius:999px; padding:.25rem .3rem .25rem .6rem; font-size:.82rem; }
    .ctags-chip-x { border:none; background:transparent; color:var(--text-muted,#6c757d); cursor:pointer; font-size:1.05rem; line-height:1; padding:0 .25rem; }
    .ctags-chip-x:hover { color:#b02a37; }
    .ctags-inputrow { display:flex; gap:.4rem; }
    .ctags-search { min-height:44px; }
    .ctags-add { min-height:44px; white-space:nowrap; }
    .ctags-menu { position:absolute; z-index:30; left:0; right:0; margin-top:.2rem; background:var(--surface,#fff);
        border:1px solid var(--border,#dee2e6); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.15);
        max-height:300px; overflow-y:auto; padding:.25rem; }
    .ctags-grp { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted,#6c757d);
        padding:.4rem .5rem .2rem; position:sticky; top:0; background:var(--surface,#fff); }
    .ctags-opt { display:block; width:100%; text-align:left; border:none; background:transparent; color:var(--text,#14181f);
        border-radius:8px; padding:.5rem .55rem; min-height:40px; cursor:pointer; font-size:.88rem; }
    .ctags-opt:hover { background:var(--surface-2,#f5f6f8); }
    .ctags-empty { padding:.55rem .55rem; font-size:.82rem; color:var(--text-muted,#6c757d); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    function norm(s) {
        s = (s == null ? '' : s.toString()).toLowerCase().normalize('NFD');
        var o = ''; for (var i = 0; i < s.length; i++) { var c = s.charCodeAt(i); if (c < 0x300 || c > 0x36f) { o += s.charAt(i); } }
        return o;
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    function initTags(root) {
        if (root.__ctagsInit) return;
        root.__ctagsInit = true;
        var name = root.dataset.name;
        var sel = 'script[data-catalog-tags-data="' + (window.CSS && CSS.escape ? CSS.escape(root.id) : root.id) + '"]';
        var cfgEl = document.querySelector(sel);
        var cfg = cfgEl ? JSON.parse(cfgEl.textContent) : {};
        var OPTIONS = cfg.options || {};
        var GROUPS = cfg.groups || null;
        var ALLOW = !!cfg.allowCustom;
        var chips = root.querySelector('[data-el="chips"]');
        var search = root.querySelector('[data-el="search"]');
        var addBtn = root.querySelector('[data-el="add"]');
        var menu = root.querySelector('[data-el="menu"]');

        function has(val) {
            var ins = chips.querySelectorAll('input');
            for (var i = 0; i < ins.length; i++) { if (ins[i].value === String(val)) return true; }
            return false;
        }
        function addChip(val, label) {
            if (val == null || val === '' || has(val)) return;
            label = label || (OPTIONS[val] != null ? OPTIONS[val] : val);
            var chip = document.createElement('span');
            chip.className = 'ctags-chip';
            chip.innerHTML = '<span class="ctags-chip-t"></span><button type="button" class="ctags-chip-x" aria-label="Quitar">&times;</button><input type="hidden">';
            chip.querySelector('.ctags-chip-t').textContent = label;
            var inp = chip.querySelector('input'); inp.name = name + '[]'; inp.value = val;
            chips.appendChild(chip);
        }
        chips.addEventListener('click', function (e) {
            var x = e.target && e.target.closest ? e.target.closest('.ctags-chip-x') : null;
            if (x) { var c = x.closest('.ctags-chip'); if (c) c.remove(); }
        });

        function optRow(val, q) {
            var lbl = OPTIONS[val] != null ? OPTIONS[val] : val;
            if (has(val)) return '';
            if (q && norm(lbl).indexOf(q) === -1 && norm(val).indexOf(q) === -1) return '';
            return '<button type="button" class="ctags-opt" data-val="' + esc(val) + '">' + esc(lbl) + '</button>';
        }
        function renderMenu() {
            var q = norm(search.value);
            var html = '';
            if (GROUPS) {
                Object.keys(GROUPS).forEach(function (g) {
                    var rows = (GROUPS[g] || []).map(function (v) { return optRow(v, q); }).join('');
                    if (rows) { html += '<div class="ctags-grp">' + esc(g) + '</div>' + rows; }
                });
            } else {
                html = Object.keys(OPTIONS).map(function (v) { return optRow(v, q); }).join('');
            }
            if (!html) {
                var t = search.value.trim();
                html = (ALLOW && t !== '')
                    ? '<div class="ctags-empty">Toca “Agregar” para añadir “' + esc(t) + '”.</div>'
                    : '<div class="ctags-empty">Sin coincidencias.</div>';
            }
            menu.innerHTML = html; menu.hidden = false;
        }
        function commit() {
            var t = search.value.trim();
            if (t === '') return;
            var match = null;
            Object.keys(OPTIONS).forEach(function (v) { if (match === null && norm(OPTIONS[v]) === norm(t)) match = v; });
            if (match !== null) { addChip(match); }
            else if (ALLOW) { addChip(t, t); }
            else { return; }
            search.value = ''; renderMenu();
        }

        search.addEventListener('focus', renderMenu);
        search.addEventListener('input', renderMenu);
        search.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); commit(); } });
        addBtn.addEventListener('click', commit);
        menu.addEventListener('click', function (e) {
            var b = e.target && e.target.closest ? e.target.closest('.ctags-opt') : null;
            if (!b) return;
            addChip(b.getAttribute('data-val'));
            search.value = ''; renderMenu();
        });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) { menu.hidden = true; } });

        // Hook externo (p.ej. sugerencias por locación del DSR).
        root.addEventListener('cc:catalog-add', function (e) { if (e.detail && e.detail.value != null) { addChip(e.detail.value); } });
    }
    function initAll() { document.querySelectorAll('[data-catalog-tags]').forEach(initTags); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initAll); }
    else { initAll(); }
})();
</script>
@endpush
@endonce
