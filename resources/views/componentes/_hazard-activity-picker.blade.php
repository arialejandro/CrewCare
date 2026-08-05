{{--
    _hazard-activity-picker — SELECCIÓN POR ACTIVIDAD (captura fluida, Pasos 1-2).
    Fuente ÚNICA reutilizable en TODOS los formularios que agregan peligros del catálogo.

    El safety piensa por ACTIVIDAD ("hoy hay SFX y stunts"), no por 37 categorías.
    - Elige actividades → aparecen SUS peligros (agrupados, visibles para recorrer con la vista).
    - Escribe → la MISMA lista se filtra sobre el catálogo COMPLETO (para llegar a lo de fuera).
      No son dos modos: la lista siempre está, escribir la acorta.
    - Multi-selección sin cerrar; objetivo táctil grande; una mano.
    - ⚠ En móvil el campo NO toma el foco solo (abrir el teclado taparía la lista).

    Es host-agnóstico: al elegir un peligro dispara el evento DOM `cc:hazard-pick` sobre la
    raíz (id=$pickerId) con detail={event}. El formulario anfitrión decide qué hacer
    (scouting: agrega una fila con control/EPP/norma pre-propuestos).

    Params:
      - $hazardEvents  (Collection, con relación 'standards' cargada)
      - $pickerId      (string, único; default 'hzpick')
--}}
@php
    use App\Support\HazardActivities;
    $pickerId = $pickerId ?? 'hzpick';
    $lang = app()->getLocale() === 'en' ? 'en' : 'es';
    $activities = HazardActivities::list($lang);
    $ppayload = HazardActivities::catalogPayload($hazardEvents ?? collect(), $lang);
    $counts = [];
    foreach ($ppayload as $p) { $counts[$p['activity']] = ($counts[$p['activity']] ?? 0) + 1; }
    $otherKey = HazardActivities::OTHER_KEY;
@endphp

<div class="hzpick" id="{{ $pickerId }}" data-picker>
    <div class="hzpick-acts" role="group" aria-label="{{ $lang==='en'?'Activities':'Actividades' }}">
        @foreach($activities as $key => $label)
            @if(($counts[$key] ?? 0) > 0)
                <button type="button" class="hzpick-chip" data-act="{{ $key }}" aria-pressed="false">
                    {{ $label }} <span class="hzpick-n">{{ $counts[$key] }}</span>
                </button>
            @endif
        @endforeach
        @if(($counts[$otherKey] ?? 0) > 0)
            <button type="button" class="hzpick-chip hzpick-chip--other" data-act="{{ $otherKey }}" aria-pressed="false">
                {{ HazardActivities::label($otherKey, $lang) }} <span class="hzpick-n">{{ $counts[$otherKey] }}</span>
            </button>
        @endif
    </div>

    <div class="hzpick-searchwrap">
        {{-- type=search, SIN autofocus: en móvil el teclado taparía la lista. --}}
        <input type="search" class="form-control hzpick-search" data-el="search" autocomplete="off"
               enterkeyhint="search" placeholder="{{ $lang==='en'?'Type to find any hazard…':'Escribe para buscar cualquier peligro…' }}">
    </div>

    <div class="hzpick-hint" data-el="hint">
        {{ $lang==='en'
            ? 'Pick one or more activities, or type to search the full catalog.'
            : 'Elige una o más actividades, o escribe para buscar en el catálogo completo.' }}
    </div>

    <div class="hzpick-list" data-el="list" aria-live="polite"></div>
</div>

<script type="application/json" data-picker-data="{{ $pickerId }}">@json($ppayload)</script>

@once
@push('styles')
<style>
    .hzpick { border:1px solid var(--border,#dee2e6); border-radius:14px; padding:.8rem; background:var(--surface,#fff); }
    .hzpick-acts { display:flex; flex-wrap:wrap; gap:.4rem; }
    .hzpick-chip { border:1px solid var(--border,#dee2e6); background:var(--surface,#fff); color:var(--text,#14181f);
        border-radius:999px; padding:.5rem .85rem; font-size:.85rem; font-weight:600; cursor:pointer; min-height:44px; }
    .hzpick-chip:hover { border-color:var(--brand-primary,#0e6f6c); }
    .hzpick-chip[aria-pressed="true"] { background:color-mix(in srgb, var(--brand-primary,#0e6f6c) 16%, var(--surface,#fff));
        border-color:var(--brand-primary,#0e6f6c); box-shadow:0 0 0 2px color-mix(in srgb, var(--brand-primary,#0e6f6c) 25%, transparent); }
    .hzpick-chip--other { border-style:dashed; }
    .hzpick-n { font-size:.72rem; opacity:.65; font-weight:700; }
    .hzpick-searchwrap { margin:.7rem 0 .3rem; }
    .hzpick-search { min-height:44px; }
    .hzpick-hint { font-size:.8rem; color:var(--text-muted,#6c757d); margin-bottom:.4rem; }
    .hzpick-list { display:flex; flex-direction:column; gap:.15rem; max-height:340px; overflow-y:auto; }
    .hzpick-group-h { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
        color:var(--text-muted,#6c757d); padding:.5rem .2rem .2rem; position:sticky; top:0; background:var(--surface,#fff); }
    .hzpick-item { display:flex; align-items:flex-start; gap:.5rem; width:100%; text-align:left;
        -webkit-appearance:none; appearance:none; line-height:1.3;
        border:1px solid transparent; border-radius:10px; padding:.55rem .6rem; min-height:44px; cursor:pointer;
        background:transparent; color:var(--text,#14181f); }
    .hzpick-item:hover { background:var(--surface-2,#f5f6f8); border-color:var(--border,#dee2e6); }
    .hzpick-item-main { flex:1 1 auto; min-width:0; display:flex; flex-direction:column; gap:.2rem; }
    .hzpick-item-name { font-size:.9rem; line-height:1.3; overflow-wrap:anywhere; }
    .hzpick-item-norms { display:flex; gap:.25rem; flex-wrap:wrap; margin-left:0; }
    .hzpick-norm { font-size:.64rem; font-weight:700; letter-spacing:.02em; padding:.05rem .3rem; border-radius:6px;
        background:color-mix(in srgb, var(--brand-primary,#0e6f6c) 12%, transparent); color:var(--brand-primary,#0e6f6c); }
    .hzpick-item-add { font-size:.78rem; font-weight:700; color:var(--brand-primary,#0e6f6c); white-space:nowrap; align-self:center; flex:0 0 auto; }
    .hzpick-item.added { background:color-mix(in srgb, var(--brand-primary,#0e6f6c) 8%, transparent); }
    .hzpick-item.added .hzpick-item-add { color:#2e7d32; }
    .hzpick-empty { font-size:.85rem; color:var(--text-muted,#6c757d); padding:.6rem .2rem; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // Normaliza sin acentos, sin regex de rango Unicode (frágil en archivos): recorre por
    // code point y descarta los diacríticos combinantes (U+0300–U+036F) tras NFD.
    function norm(s) {
        s = (s == null ? '' : s.toString()).toLowerCase().normalize('NFD');
        var out = '';
        for (var i = 0; i < s.length; i++) {
            var cc = s.charCodeAt(i);
            if (cc < 0x300 || cc > 0x36f) { out += s.charAt(i); }
        }
        return out;
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    function initPicker(root) {
        if (root.__hzpickInit) return;
        root.__hzpickInit = true;
        var id = root.id;
        var sel = 'script[data-picker-data="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]';
        var dataEl = document.querySelector(sel);
        var EVENTS = dataEl ? JSON.parse(dataEl.textContent) : [];
        var listEl = root.querySelector('[data-el="list"]');
        var hintEl = root.querySelector('[data-el="hint"]');
        var searchEl = root.querySelector('[data-el="search"]');
        var actEls = root.querySelectorAll('.hzpick-chip');

        var selected = {};    // actividad => true
        var query = '';
        var addedCount = {};  // event id => veces agregado

        var actLabel = {};
        actEls.forEach(function (c) {
            var k = c.getAttribute('data-act');
            actLabel[k] = c.childNodes[0] ? c.childNodes[0].textContent.trim() : k;
        });

        function visibleEvents() {
            var q = norm(query);
            if (q) {
                // Escribir busca en el catálogo COMPLETO (para llegar a lo de fuera).
                return EVENTS.filter(function (e) {
                    return norm(e.name).indexOf(q) !== -1 || norm(e.code).indexOf(q) !== -1;
                });
            }
            var keys = Object.keys(selected).filter(function (k) { return selected[k]; });
            if (!keys.length) return null; // nada elegido y sin texto → mostrar hint
            return EVENTS.filter(function (e) { return selected[e.activity]; });
        }

        function render() {
            var evs = visibleEvents();
            listEl.innerHTML = '';
            if (evs === null) { hintEl.style.display = ''; return; }
            hintEl.style.display = 'none';
            if (!evs.length) {
                listEl.innerHTML = '<div class="hzpick-empty">Sin resultados. Prueba otra actividad o cambia lo escrito.</div>';
                return;
            }
            var groups = [], gmap = {};
            evs.forEach(function (e) {
                if (!gmap[e.activity]) { gmap[e.activity] = []; groups.push(e.activity); }
                gmap[e.activity].push(e);
            });
            var html = '';
            groups.forEach(function (gk) {
                html += '<div class="hzpick-group-h">' + esc(actLabel[gk] || gk) + '</div>';
                gmap[gk].forEach(function (e) {
                    var norms = (e.norms || []).slice(0, 3).map(function (n) {
                        return '<span class="hzpick-norm">' + esc(n.badge || n.code) + '</span>';
                    }).join('');
                    var n = addedCount[e.id] || 0;
                    var addTxt = n > 0 ? ('✓ agregado' + (n > 1 ? ' ×' + n : '')) : '+ Agregar';
                    html += '<button type="button" class="hzpick-item' + (n > 0 ? ' added' : '') + '" data-id="' + e.id + '">'
                          + '<span class="hzpick-item-main"><span class="hzpick-item-name">' + esc(e.name) + '</span>'
                          + (norms ? '<span class="hzpick-item-norms">' + norms + '</span>' : '')
                          + '</span><span class="hzpick-item-add">' + addTxt + '</span></button>';
                });
            });
            listEl.innerHTML = html;
        }

        // Multi-selección de actividades (toggle, no cierra).
        actEls.forEach(function (c) {
            c.addEventListener('click', function () {
                var k = c.getAttribute('data-act');
                selected[k] = !selected[k];
                c.setAttribute('aria-pressed', selected[k] ? 'true' : 'false');
                render();
            });
        });

        // Escribir filtra la MISMA lista (debounce ligero). NUNCA .focus() automático.
        var deb;
        searchEl.addEventListener('input', function () {
            clearTimeout(deb);
            deb = setTimeout(function () { query = searchEl.value; render(); }, 120);
        });

        // Elegir un peligro → avisar al anfitrión; NO cerrar; marcar agregado.
        listEl.addEventListener('click', function (ev) {
            var btn = ev.target && ev.target.closest ? ev.target.closest('.hzpick-item') : null;
            if (!btn) return;
            var pid = parseInt(btn.getAttribute('data-id'), 10);
            var e = EVENTS.filter(function (x) { return x.id === pid; })[0];
            if (!e) return;
            root.dispatchEvent(new CustomEvent('cc:hazard-pick', { bubbles: true, detail: { event: e } }));
            addedCount[pid] = (addedCount[pid] || 0) + 1;
            render();
        });

        render();
    }

    function initAll() {
        document.querySelectorAll('.hzpick[data-picker]').forEach(initPicker);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
</script>
@endpush
@endonce
