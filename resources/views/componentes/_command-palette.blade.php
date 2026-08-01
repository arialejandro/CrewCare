{{-- =============================================================================
     COMMAND PALETTE (⌘K / Ctrl-K) — "Cinematic Dark Glass".
     Overlay frosted que RECOLECTA los enlaces YA renderizados del sidebar de
     escritorio (así respeta el RBAC automáticamente: si un enlace no se renderizó
     por permisos, tampoco aparece aquí). Los agrupa por sección, filtra al escribir,
     navega con ↑/↓, abre con Enter (window.location = href) y cierra con Esc.
     Se abre con ⌘K/Ctrl-K o cualquier botón con la clase .cc-cmd-open (sidebar/topbar).
     Se incluye UNA vez en el layout maestro, al final del <body>.
     no-print: no aparece en los reportes imprimibles.
     ============================================================================ --}}
<div class="cc-cmdk no-print" id="ccCmdk" role="dialog" aria-modal="true" aria-label="{{ __('nav.search') }}">
    <div class="cc-cmdk__box" role="document">
        <div class="cc-cmdk__in">
            @include('componentes._icon', ['name' => 'search', 'class' => 'cc-cmdk__in-ico', 'label' => null])
            <input id="ccCmdInput" type="text" placeholder="{{ __('nav.search_placeholder') }}" autocomplete="off" spellcheck="false" aria-controls="ccCmdList" aria-label="{{ __('nav.search') }}">
            <span class="cc-cmdk__esc" aria-hidden="true">ESC</span>
        </div>
        <div class="cc-cmdk__list" id="ccCmdList" role="listbox"></div>
        <div class="cc-cmdk__foot">
            <span><b>↑↓</b> {{ __('nav.search_nav') }}</span>
            <span><b>↵</b> {{ __('nav.search_open') }}</span>
            <span><b>esc</b> {{ __('nav.search_close') }}</span>
        </div>
    </div>
</div>

<style>
    .cc-cmdk {
        position: fixed; inset: 0; z-index: 1100; display: none;
        align-items: flex-start; justify-content: center; padding-top: 12vh;
        background: rgba(5, 8, 14, .55);
        -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
    }
    .cc-cmdk.is-open { display: flex; }
    .cc-cmdk__box {
        width: min(620px, 92vw); background: var(--bg-2);
        border: 1px solid var(--stroke-2); border-radius: 18px;
        box-shadow: 0 40px 80px -20px rgba(0, 0, 0, .7); overflow: hidden;
        animation: cc-cmdk-pop .22s var(--ease);
    }
    @media screen {
        .cc-cmdk__box { -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat)); backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat)); }
    }
    @keyframes cc-cmdk-pop { from { opacity: 0; transform: translateY(-10px) scale(.98); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) { .cc-cmdk__box { animation: none; } }

    .cc-cmdk__in { display: flex; align-items: center; gap: .75rem; padding: 1rem 1.1rem; border-bottom: 1px solid var(--stroke); }
    .cc-cmdk__in-ico { width: 20px; height: 20px; color: var(--text-muted); flex: none; }
    .cc-cmdk__in input { flex: 1; background: none; border: 0; outline: 0; color: var(--text); font-size: 1.02rem; font-family: inherit; }
    .cc-cmdk__in input::placeholder { color: var(--text-muted); }
    .cc-cmdk__esc { font-family: ui-monospace, Consolas, monospace; font-size: .66rem; color: var(--text-muted); border: 1px solid var(--stroke); border-radius: 6px; padding: 3px 7px; }

    .cc-cmdk__list { max-height: 52vh; overflow-y: auto; padding: .5rem; }
    .cc-cmdk__group { font-size: .64rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--text-muted); padding: .6rem .75rem .3rem; }
    .cc-cmdk__opt { display: flex; align-items: center; gap: .75rem; padding: .6rem .75rem; border-radius: 10px; cursor: pointer; color: var(--text-muted); }
    .cc-cmdk__opt svg { width: 17px; height: 17px; flex: none; color: var(--text-muted); }
    .cc-cmdk__opt-t { color: var(--text); font-size: .88rem; font-weight: 500; }
    .cc-cmdk__opt-s { margin-left: auto; font-size: .7rem; color: var(--text-muted); }
    .cc-cmdk__opt.is-sel { background: color-mix(in srgb, var(--brand-primary) 15%, transparent); }
    .cc-cmdk__opt.is-sel { background: color-mix(in srgb, var(--brand-primary) 15%, var(--bg-2)); } /* fallback opaco */
    .cc-cmdk__opt.is-sel svg, .cc-cmdk__opt.is-sel .cc-cmdk__opt-s { color: var(--brand-primary); }
    .cc-cmdk__empty { padding: 1.6rem; text-align: center; color: var(--text-muted); font-size: .86rem; }
    .cc-cmdk__foot { display: flex; gap: 1rem; padding: .65rem 1rem; border-top: 1px solid var(--stroke); font-size: .7rem; color: var(--text-muted); }
    .cc-cmdk__foot b { color: var(--text); font-family: ui-monospace, Consolas, monospace; font-weight: 500; }
</style>

<script>
    (function () {
        'use strict';
        var cmdk  = document.getElementById('ccCmdk');
        var input = document.getElementById('ccCmdInput');
        var list  = document.getElementById('ccCmdList');
        if (!cmdk || !input || !list) { return; }

        // ---- Recolecta enlaces del sidebar de ESCRITORIO (ya filtrados por RBAC) ----
        var FLAT = [];
        function harvest() {
            FLAT = [];
            var secs = document.querySelectorAll('.sidebar-expanded .cc-sb--desktop .cc-sec');
            Array.prototype.forEach.call(secs, function (sec) {
                var labelEl = sec.querySelector('.cc-sec-head__label');
                var secLabel = labelEl ? labelEl.textContent.trim() : '';
                var items = sec.querySelectorAll('.cc-sec-body__inner .cc-item');
                Array.prototype.forEach.call(items, function (a) {
                    var txtEl = a.querySelector('span');
                    var svgEl = a.querySelector('svg');
                    FLAT.push({
                        t: txtEl ? txtEl.textContent.trim() : (a.textContent || '').trim(),
                        href: a.getAttribute('href') || '#',
                        sec: secLabel,
                        ico: svgEl ? svgEl.outerHTML : ''
                    });
                });
            });
        }

        var sel = 0, shown = [];
        var EMPTY = @json(__('nav.search_empty'));

        function esc(s) { return (s || '').replace(/[&<>"]/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c];
        }); }

        function render(q) {
            q = (q || '').toLowerCase().trim();
            shown = FLAT.filter(function (o) {
                return !q || o.t.toLowerCase().indexOf(q) >= 0 || o.sec.toLowerCase().indexOf(q) >= 0;
            });
            sel = 0;
            if (!shown.length) {
                list.innerHTML = '<div class="cc-cmdk__empty">' + esc(EMPTY) + (q ? ' — “' + esc(q) + '”' : '') + '</div>';
                return;
            }
            var groups = {}, order = [];
            shown.forEach(function (o) {
                if (!groups[o.sec]) { groups[o.sec] = []; order.push(o.sec); }
                groups[o.sec].push(o);
            });
            var html = '', idx = 0;
            order.forEach(function (g) {
                html += '<div class="cc-cmdk__group">' + esc(g) + '</div>';
                groups[g].forEach(function (o) {
                    html += '<div class="cc-cmdk__opt" role="option" data-i="' + idx + '" data-href="' + esc(o.href) + '">' +
                        (o.ico || '') +
                        '<span class="cc-cmdk__opt-t">' + esc(o.t) + '</span>' +
                        '<span class="cc-cmdk__opt-s">' + esc(o.sec) + '</span></div>';
                    idx++;
                });
            });
            list.innerHTML = html;
            highlight();
            Array.prototype.forEach.call(list.querySelectorAll('.cc-cmdk__opt'), function (n) {
                n.addEventListener('mousemove', function () { sel = +n.getAttribute('data-i'); highlight(); });
                n.addEventListener('click', function () { go(n.getAttribute('data-href')); });
            });
        }

        function highlight() {
            var opts = list.querySelectorAll('.cc-cmdk__opt');
            Array.prototype.forEach.call(opts, function (n, i) {
                var on = (i === sel);
                n.classList.toggle('is-sel', on);
                n.setAttribute('aria-selected', on ? 'true' : 'false');
                if (on) { n.scrollIntoView({ block: 'nearest' }); }
            });
        }

        function go(href) {
            if (href && href !== '#') { window.location.href = href; }
        }

        function open() {
            harvest();
            cmdk.classList.add('is-open');
            input.value = '';
            render('');
            setTimeout(function () { input.focus(); }, 20);
        }
        function close() { cmdk.classList.remove('is-open'); }

        input.addEventListener('input', function () { render(input.value); });

        document.addEventListener('keydown', function (e) {
            var k = (e.key || '').toLowerCase();
            if ((e.metaKey || e.ctrlKey) && k === 'k') {
                e.preventDefault();
                cmdk.classList.contains('is-open') ? close() : open();
                return;
            }
            if (!cmdk.classList.contains('is-open')) { return; }
            if (k === 'escape') { e.preventDefault(); close(); }
            else if (k === 'arrowdown') { e.preventDefault(); sel = Math.min(sel + 1, shown.length - 1); highlight(); }
            else if (k === 'arrowup') { e.preventDefault(); sel = Math.max(sel - 1, 0); highlight(); }
            else if (k === 'enter') {
                e.preventDefault();
                if (shown[sel]) { go(shown[sel].href); }
            }
        });

        // Disparadores en sidebar/topbar.
        Array.prototype.forEach.call(document.querySelectorAll('.cc-cmd-open'), function (btn) {
            btn.addEventListener('click', function (e) { e.preventDefault(); open(); });
        });

        // Click en el backdrop cierra.
        cmdk.addEventListener('click', function (e) { if (e.target === cmdk) { close(); } });
    })();
</script>
