@extends('layouts.app')
@section('content')

@push('styles')
<style>
    /* ===== Mapeo de la locación (delta #48) — pines sobre lienzos ===== */
    .cvs-page { max-width: 1000px; }
    .cvs-head-bar { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem; }
    .cvs-title { font-weight:700; margin:0; }
    .cvs-sub { color:var(--text-muted, #6c757d); font-size:.9rem; }

    .cvs-panel {
        background:var(--surface, #fff); border:1px solid var(--border, #dee2e6);
        border-radius:14px; padding:1rem; margin-bottom:1.25rem;
    }
    .cvs-panel h2 { font-size:1rem; font-weight:700; margin:0 0 .5rem; display:flex; align-items:center; gap:.5rem; }

    .cvs-type-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:.6rem; margin:.5rem 0; }
    .cvs-type-opt {
        border:1px solid var(--border,#dee2e6); border-radius:10px; padding:.55rem .7rem; cursor:pointer;
        display:flex; gap:.55rem; align-items:flex-start;
    }
    .cvs-type-opt input { margin-top:.2rem; }
    .cvs-type-opt.sel { border-color:var(--brand-primary,#0e6f6c); box-shadow:0 0 0 2px color-mix(in srgb, var(--brand-primary,#0e6f6c) 25%, transparent); }
    .cvs-type-opt b { display:block; }
    .cvs-type-opt small { color:var(--text-muted,#6c757d); }

    /* Lienzos */
    .cvs-card { border:1px solid var(--border,#dee2e6); border-radius:14px; overflow:hidden; margin-bottom:1.25rem; background:var(--surface,#fff); }
    .cvs-card-head { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; padding:.6rem .8rem; border-bottom:1px solid var(--border,#dee2e6); }
    .cvs-type-badge { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
        background:color-mix(in srgb, var(--brand-primary,#0e6f6c) 15%, transparent); color:var(--brand-primary,#0e6f6c);
        padding:.15rem .5rem; border-radius:20px; }
    .cvs-name { font-weight:600; }
    .cvs-head-actions { margin-left:auto; display:flex; gap:.4rem; flex-wrap:wrap; }

    /* Lienzo + pines */
    .cvs-stage { position:relative; line-height:0; background:#0b0e12; touch-action:manipulation; }
    .cvs-stage img { width:100%; height:auto; display:block; user-select:none; -webkit-user-drag:none; }
    .cvs-stage.placing { cursor:crosshair; }
    .cvs-stage.placing::after { content:''; position:absolute; inset:0; box-shadow:inset 0 0 0 3px color-mix(in srgb, var(--brand-primary,#0e6f6c) 60%, transparent); pointer-events:none; }

    .cvs-pin { position:absolute; width:0; height:0; }
    .cvs-dot { position:absolute; left:0; top:0; width:30px; height:30px; margin:-15px 0 0 -15px;
        border-radius:50%; border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.55); cursor:grab; touch-action:none; }
    .cvs-dot::after { content:''; position:absolute; inset:-10px; border-radius:50%; } /* hit-area ~50px (táctil) */
    .cvs-dot.dragging { cursor:grabbing; }
    .cvs-flag { position:absolute; left:20px; top:-14px; white-space:nowrap; font-size:.72rem; font-weight:600;
        background:rgba(255,255,255,.94); color:#14181f; padding:.08rem .35rem; border-radius:6px;
        box-shadow:0 1px 3px rgba(0,0,0,.35); line-height:1.3; max-width:180px; overflow:hidden; text-overflow:ellipsis; }
    .cvs-flag.haz { background:#fff3cd; }
    .cvs-flag .cvs-photo-dot { display:inline-block; width:6px; height:6px; border-radius:50%; background:#0e6f6c; margin-left:4px; vertical-align:middle; }

    /* Editor */
    .cvs-editor { padding:.75rem .8rem; }
    .cvs-tools { display:flex; gap:1.2rem; flex-wrap:wrap; }
    .cvs-tools-group { flex:1 1 260px; }
    .cvs-tools-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted,#6c757d); }
    .cvs-res-chips { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.4rem; }
    .cvs-chip { border:1px solid var(--border,#dee2e6); background:var(--surface,#fff); color:var(--text,#14181f);
        border-radius:20px; padding:.4rem .7rem; font-size:.85rem; cursor:pointer; min-height:40px; }
    .cvs-chip:hover { border-color:var(--brand-primary,#0e6f6c); }
    .cvs-chip.res::before { content:''; display:inline-block; width:10px; height:10px; border-radius:50%; background:#0e6f6c; margin-right:.35rem; vertical-align:middle; }
    .cvs-haz-select { width:100%; margin-top:.4rem; min-height:40px; }
    .cvs-haz-place { margin-top:.4rem; }
    .cvs-none { color:var(--text-muted,#6c757d); font-size:.85rem; margin-top:.4rem; }

    .cvs-place-banner { margin-top:.7rem; padding:.5rem .7rem; border-radius:10px; display:flex; align-items:center; gap:.6rem; flex-wrap:wrap;
        background:color-mix(in srgb, var(--brand-primary,#0e6f6c) 12%, transparent); border:1px solid color-mix(in srgb, var(--brand-primary,#0e6f6c) 35%, transparent); }
    .cvs-place-banner .cvs-geo-wrap { display:flex; align-items:center; gap:.3rem; font-size:.85rem; }
    .cvs-hint { margin-top:.6rem; font-size:.8rem; color:var(--text-muted,#6c757d); }
    .cvs-count { font-size:.8rem; color:var(--text-muted,#6c757d); }

    /* Bottom-sheet de edición de pin (una mano, en set) */
    #cvs-pop { position:fixed; left:0; right:0; bottom:0; z-index:1080; background:var(--surface,#fff);
        border-top:1px solid var(--border,#dee2e6); box-shadow:0 -6px 24px rgba(0,0,0,.25);
        padding:1rem; border-radius:16px 16px 0 0; }
    #cvs-pop .cvs-pop-inner { max-width:640px; margin:0 auto; }
    #cvs-pop .cvs-pop-label { font-weight:700; margin-bottom:.5rem; }
    #cvs-pop textarea { width:100%; }
    #cvs-pop .cvs-pop-actions { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.6rem; }
    #cvs-pop .cvs-pop-actions .spacer { flex:1; }
    .cvs-pop-photo-prev img { max-height:120px; border-radius:8px; margin-top:.4rem; }
    .cvs-pop-geo { font-size:.8rem; color:var(--text-muted,#6c757d); margin-top:.4rem; }

    .cvs-toast { position:fixed; left:50%; bottom:16px; transform:translateX(-50%); z-index:1090;
        background:#14181f; color:#fff; padding:.55rem .9rem; border-radius:10px; font-size:.85rem; box-shadow:0 4px 16px rgba(0,0,0,.35); }

    @media (max-width:575px){ .cvs-head-actions{ width:100%; margin-left:0; } }
</style>
@endpush

<div class="container py-3 cvs-page">

    <div class="cvs-head-bar">
        <a href="{{ route('scoutings.edit', $scouting->id) }}" class="btn btn-sm btn-outline-secondary">&larr; Volver a la locación</a>
        <div>
            <h1 class="cvs-title h4">Mapeo de la locación</h1>
            <div class="cvs-sub">{{ $scouting->production_name ?: 'Locación' }} · localiza peligros y recursos de emergencia sobre una imagen. Es el insumo del PAE.</div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ============ NUEVO LIENZO ============ --}}
    <div class="cvs-panel">
        <h2>@include('componentes._icon', ['name' => 'upload', 'class' => 'cc-ico']) <span>Agregar lienzo</span></h2>
        <p class="cvs-sub mb-2">Un lienzo es una imagen con sus pines. Un scouting puede tener varios y cada uno conserva los suyos. El plano y el aéreo son opcionales.</p>

        <form method="POST" action="{{ route('scoutings.mapping.canvas.store', $scouting->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="cvs-type-grid">
                <label class="cvs-type-opt sel">
                    <input type="radio" name="type" value="satelital" checked>
                    <span><b>Satelital</b><small>Exteriores: accesos, ambulancia, punto de reunión, estacionamiento.</small></span>
                </label>
                <label class="cvs-type-opt">
                    <input type="radio" name="type" value="foto">
                    <span><b>Foto</b><small>Interiores (gran angular). Puede haber una por área o piso.</small></span>
                </label>
                <label class="cvs-type-opt">
                    <input type="radio" name="type" value="plano">
                    <span><b>Plano</b><small>Opcional. Súbelo si lo tienes; no bloquea nada.</small></span>
                </label>
                <label class="cvs-type-opt">
                    <input type="radio" name="type" value="aereo">
                    <span><b>Aéreo</b><small>Foto de dron del scouting, si la hubo. Opcional.</small></span>
                </label>
            </div>

            @if($scouting->latitude && $scouting->longitude)
                <p class="cvs-sub mb-2" data-role="sat-hint">
                    Para el satelital puedes tomar una captura desde
                    <a href="https://www.google.com/maps/@{{ $scouting->latitude }},{{ $scouting->longitude }},18z/data=!3m1!1e3" target="_blank" rel="noopener">Google Maps en las coordenadas de la locación</a>.
                </p>
            @endif

            <div class="row g-2 align-items-end">
                <div class="col-sm-5">
                    <label class="form-label mb-1">Nombre del lienzo <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" maxlength="120" required placeholder="Ej. Planta baja, Acceso norte" value="{{ old('name') }}">
                </div>
                <div class="col-sm-5">
                    <label class="form-label mb-1">Imagen <span class="text-danger">*</span></label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                </div>
                <div class="col-sm-2">
                    <button type="submit" class="btn btn-primary w-100">Agregar</button>
                </div>
            </div>
        </form>
    </div>

    {{-- ============ LIENZOS EXISTENTES (render por JS, camino único) ============ --}}
    <div id="cvs-app" data-base="{{ url('/scoutings/'.$scouting->id.'/mapeo') }}">
        <div id="cvs-list"></div>
        <div id="cvs-empty" class="cvs-panel text-center" hidden>
            <p class="mb-1">Esta locación aún no tiene lienzos.</p>
            <p class="cvs-sub mb-0">Agrega uno arriba para empezar a ubicar peligros y recursos. Un scouting sin lienzos es válido.</p>
        </div>
    </div>
</div>

{{-- Plantilla de tarjeta de lienzo (se clona por JS) --}}
<template id="cvs-card-tpl">
    <div class="cvs-card" data-canvas>
        <div class="cvs-card-head">
            <span class="cvs-type-badge" data-el="badge"></span>
            <span class="cvs-name" data-el="name"></span>
            <span class="cvs-count" data-el="count"></span>
            <div class="cvs-head-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-el="download">Descargar imagen compuesta</button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-el="delcanvas">Eliminar lienzo</button>
            </div>
        </div>
        <div class="cvs-stage" data-el="stage"><img data-el="img" alt=""></div>
        <div class="cvs-editor">
            <div class="cvs-tools">
                <div class="cvs-tools-group">
                    <div class="cvs-tools-title">Recursos de emergencia</div>
                    <div class="cvs-res-chips" data-el="reschips"></div>
                </div>
                <div class="cvs-tools-group">
                    <div class="cvs-tools-title">Peligros (ya evaluados)</div>
                    <select class="form-select form-select-sm cvs-haz-select" data-el="hazselect"></select>
                    <button type="button" class="btn btn-sm btn-outline-danger cvs-haz-place" data-el="hazplace">Colocar peligro</button>
                    <div class="cvs-none" data-el="haznone" hidden>No hay peligros evaluados en esta locación. Agrégalos en la evaluación de riesgos para poder ubicarlos.</div>
                </div>
            </div>
            <div class="cvs-place-banner" data-el="banner" hidden>
                <span>Colocando: <b data-el="placelabel"></b> · toca la imagen</span>
                <label class="cvs-geo-wrap" data-el="geowrap" hidden><input type="checkbox" data-el="geo"> Adjuntar mi GPS</label>
                <button type="button" class="btn btn-sm btn-secondary" data-el="placedone">Listo</button>
            </div>
            <div class="cvs-hint">Toca un tipo y luego la imagen para colocar. Arrastra un pin para moverlo; tócalo para editar nota/foto o borrarlo.</div>
        </div>
    </div>
</template>

{{-- Bottom-sheet de edición de un pin --}}
<div id="cvs-pop" hidden>
    <div class="cvs-pop-inner">
        <div class="cvs-pop-label"></div>
        <textarea class="form-control cvs-pop-note" rows="2" maxlength="300" placeholder="Nota corta (opcional)"></textarea>
        <label class="form-label mt-2 mb-1">Foto de cerca (opcional)</label>
        <input type="file" class="form-control form-control-sm cvs-pop-photo" accept="image/*">
        <div class="cvs-pop-photo-prev"></div>
        <div class="cvs-pop-geo"></div>
        <div class="cvs-pop-actions">
            <button type="button" class="btn btn-sm btn-primary cvs-pop-save">Guardar</button>
            <button type="button" class="btn btn-sm btn-outline-danger cvs-pop-del">Borrar pin</button>
            <span class="spacer"></span>
            <button type="button" class="btn btn-sm btn-outline-secondary cvs-pop-close">Cerrar</button>
        </div>
    </div>
</div>

<script type="application/json" id="cvs-bootstrap">@json(['canvases' => $payload, 'hazards' => $hazards, 'resources' => $resources])</script>

@push('scripts')
<script src="{{ asset('js/vendor/html2canvas.min.js') }}"></script>
<script>
(function () {
    var appEl = document.getElementById('cvs-app');
    if (!appEl) return;
    var BASE = appEl.dataset.base;
    var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var BOOT = JSON.parse(document.getElementById('cvs-bootstrap').textContent);
    var RESOURCES = BOOT.resources || {};
    var HAZARDS = BOOT.hazards || [];

    var RES_COLOR = '#0e6f6c';
    var RATING_COLORS = { E: '#b02a37', H: '#d9534f', M: '#f0ad4e', L: '#6c757d' };
    function hazColor(r) { return RATING_COLORS[(r || '').toUpperCase()] || '#d9534f'; }
    function pinColor(p) { return p.family === 'recurso' ? RES_COLOR : hazColor(p.rating); }

    function toast(msg) {
        var t = document.createElement('div');
        t.className = 'cvs-toast'; t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 3200);
    }

    // --- fetch helper: POST con method-spoof para PUT/DELETE (soporta archivos + CSRF) ---
    function api(url, method, fd) {
        var body = fd || new FormData();
        if (method !== 'POST') { body.append('_method', method); }
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: body
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (!r.ok) { throw new Error(j.message || ('Error ' + r.status)); }
                return j;
            });
        });
    }

    function pctFromClient(imgEl, cx, cy) {
        var rc = imgEl.getBoundingClientRect();
        var x = (cx - rc.left) / rc.width * 100;
        var y = (cy - rc.top) / rc.height * 100;
        return { x: Math.max(0, Math.min(100, x)), y: Math.max(0, Math.min(100, y)) };
    }

    // ============ Bottom-sheet de edición de pin ============
    var pop = document.getElementById('cvs-pop');
    var popState = null; // {card, pinEl, pin}
    function openPop(card, pinEl, pin) {
        popState = { card: card, pinEl: pinEl, pin: pin };
        pop.querySelector('.cvs-pop-label').textContent = pin.label + (pin.rating ? ' · riesgo ' + pin.rating : '');
        pop.querySelector('.cvs-pop-note').value = pin.note || '';
        pop.querySelector('.cvs-pop-photo').value = '';
        var prev = pop.querySelector('.cvs-pop-photo-prev');
        prev.innerHTML = pin.photo_url ? '<img src="' + pin.photo_url + '" alt="">' : '';
        var geo = pop.querySelector('.cvs-pop-geo');
        geo.textContent = (pin.geo_lat != null && pin.geo_lng != null) ? ('GPS: ' + pin.geo_lat + ', ' + pin.geo_lng) : '';
        pop.hidden = false;
    }
    function closePop() { pop.hidden = true; popState = null; }
    pop.querySelector('.cvs-pop-close').addEventListener('click', closePop);
    pop.querySelector('.cvs-pop-save').addEventListener('click', function () {
        if (!popState) return;
        var fd = new FormData();
        fd.append('note', pop.querySelector('.cvs-pop-note').value);
        var f = pop.querySelector('.cvs-pop-photo').files[0];
        if (f) { fd.append('photo', f); }
        var url = BASE + '/lienzos/' + popState.card._canvasId + '/pines/' + popState.pin.id;
        api(url, 'PUT', fd).then(function (j) {
            Object.assign(popState.pin, j.pin);
            redrawPin(popState.pinEl, popState.pin);
            closePop();
            toast('Pin actualizado.');
        }).catch(function (e) { toast(e.message); });
    });
    pop.querySelector('.cvs-pop-del').addEventListener('click', function () {
        if (!popState) return;
        var url = BASE + '/lienzos/' + popState.card._canvasId + '/pines/' + popState.pin.id;
        api(url, 'DELETE').then(function () {
            popState.pinEl.remove();
            var card = popState.card;
            closePop();
            updateCount(card);
            toast('Pin borrado.');
        }).catch(function (e) { toast(e.message); });
    });

    // ============ Render de un pin (camino ÚNICO) ============
    function buildPin(card, pin) {
        var el = document.createElement('div');
        el.className = 'cvs-pin';
        el.innerHTML = '<span class="cvs-dot"></span><span class="cvs-flag"></span>';
        redrawPin(el, pin);
        wirePin(card, el, pin);
        return el;
    }
    function redrawPin(el, pin) {
        el.style.left = pin.x_pct + '%';
        el.style.top = pin.y_pct + '%';
        el.querySelector('.cvs-dot').style.background = pinColor(pin);
        var flag = el.querySelector('.cvs-flag');
        flag.className = 'cvs-flag' + (pin.family === 'peligro' ? ' haz' : '');
        flag.textContent = pin.label;
        if (pin.photo_url) { flag.insertAdjacentHTML('beforeend', '<span class="cvs-photo-dot"></span>'); }
        el._pin = pin;
    }

    // ============ Interacción del pin: arrastrar (mover) o tocar (editar) ============
    function wirePin(card, el, pin) {
        var dot = el.querySelector('.cvs-dot');
        var start = null, moved = false;
        dot.addEventListener('pointerdown', function (ev) {
            ev.stopPropagation(); // no dispares "colocar" del stage
            ev.preventDefault();
            start = { x: ev.clientX, y: ev.clientY };
            moved = false;
            dot.classList.add('dragging');
            try { dot.setPointerCapture(ev.pointerId); } catch (e) {}
        });
        dot.addEventListener('pointermove', function (ev) {
            if (!start) return;
            if (Math.abs(ev.clientX - start.x) > 5 || Math.abs(ev.clientY - start.y) > 5) { moved = true; }
            if (!moved) return;
            var img = card.querySelector('[data-el="img"]');
            var p = pctFromClient(img, ev.clientX, ev.clientY);
            el.style.left = p.x + '%'; el.style.top = p.y + '%';
        });
        dot.addEventListener('pointerup', function (ev) {
            dot.classList.remove('dragging');
            try { dot.releasePointerCapture(ev.pointerId); } catch (e) {}
            if (!start) return;
            start = null;
            if (moved) {
                var img = card.querySelector('[data-el="img"]');
                var p = pctFromClient(img, ev.clientX, ev.clientY);
                var fd = new FormData(); fd.append('x_pct', p.x.toFixed(3)); fd.append('y_pct', p.y.toFixed(3));
                api(BASE + '/lienzos/' + card._canvasId + '/pines/' + pin.id, 'PUT', fd)
                    .then(function (j) { Object.assign(pin, j.pin); redrawPin(el, pin); })
                    .catch(function (e) { toast(e.message); });
            } else {
                openPop(card, el, pin);
            }
        });
        dot.addEventListener('pointercancel', function () { start = null; moved = false; dot.classList.remove('dragging'); });
    }

    function updateCount(card) {
        var n = card.querySelectorAll('.cvs-pin').length;
        card.querySelector('[data-el="count"]').textContent = n === 0 ? 'Sin pines' : (n + (n === 1 ? ' pin' : ' pines'));
    }

    // ============ GPS opcional (mi ubicación actual) ============
    function maybeGeo(card) {
        var chk = card.querySelector('[data-el="geo"]');
        if (!chk || !chk.checked) { return Promise.resolve(null); }
        if (!navigator.geolocation) { toast('Este navegador no da ubicación; se coloca a mano.'); return Promise.resolve(null); }
        return new Promise(function (resolve) {
            navigator.geolocation.getCurrentPosition(
                function (pos) { resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }); },
                function () { toast('Ubicación no disponible; el pin se coloca igual.'); resolve(null); },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        });
    }

    // ============ Colocar un pin ============
    function placePin(card, tool, x, y) {
        maybeGeo(card).then(function (geo) {
            var fd = new FormData();
            fd.append('family', tool.family);
            fd.append('x_pct', x.toFixed(3));
            fd.append('y_pct', y.toFixed(3));
            if (tool.family === 'recurso') { fd.append('resource_type', tool.key); }
            else { fd.append('hazard_event_id', tool.event_id); }
            if (geo) { fd.append('geo_lat', geo.lat); fd.append('geo_lng', geo.lng); }
            api(BASE + '/lienzos/' + card._canvasId + '/pines', 'POST', fd).then(function (j) {
                var el = buildPin(card, j.pin);
                card.querySelector('[data-el="stage"]').appendChild(el);
                updateCount(card);
            }).catch(function (e) { toast(e.message); });
        });
    }

    // ============ Init de una tarjeta de lienzo ============
    var tpl = document.getElementById('cvs-card-tpl');
    function initCard(data) {
        var card = tpl.content.firstElementChild.cloneNode(true);
        card._canvasId = data.id;
        card._tool = null;
        card.querySelector('[data-el="badge"]').textContent = data.type_label;
        card.querySelector('[data-el="name"]').textContent = data.name;
        var img = card.querySelector('[data-el="img"]');
        img.src = data.image_url; img.alt = data.name;
        var stage = card.querySelector('[data-el="stage"]');
        var banner = card.querySelector('[data-el="banner"]');
        var geowrap = card.querySelector('[data-el="geowrap"]');

        // Chips de recursos
        var chips = card.querySelector('[data-el="reschips"]');
        Object.keys(RESOURCES).forEach(function (key) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'cvs-chip res'; b.textContent = RESOURCES[key];
            b.addEventListener('click', function () { setTool(card, { family: 'recurso', key: key, label: RESOURCES[key] }); });
            chips.appendChild(b);
        });

        // Selector de peligros ya evaluados
        var sel = card.querySelector('[data-el="hazselect"]');
        var hazPlace = card.querySelector('[data-el="hazplace"]');
        if (HAZARDS.length === 0) {
            sel.hidden = true; hazPlace.hidden = true;
            card.querySelector('[data-el="haznone"]').hidden = false;
        } else {
            HAZARDS.forEach(function (h) {
                var o = document.createElement('option');
                o.value = h.event_id;
                o.textContent = h.label + (h.rating ? ' [' + h.rating + ']' : '');
                sel.appendChild(o);
            });
            hazPlace.addEventListener('click', function () {
                var h = HAZARDS.filter(function (x) { return String(x.event_id) === String(sel.value); })[0];
                if (h) { setTool(card, { family: 'peligro', event_id: h.event_id, label: h.label, rating: h.rating }); }
            });
        }

        // Banner "Listo"
        card.querySelector('[data-el="placedone"]').addEventListener('click', function () { setTool(card, null); });

        // Colocar al tocar el stage (sólo si hay herramienta y no fue sobre un pin)
        stage.addEventListener('click', function (ev) {
            if (!card._tool) return;
            if (ev.target.closest('.cvs-pin')) return;
            var p = pctFromClient(img, ev.clientX, ev.clientY);
            placePin(card, card._tool, p.x, p.y);
        });

        // Descargar imagen compuesta (lo que consumirá el PAE)
        card.querySelector('[data-el="download"]').addEventListener('click', function () { composite(card, data.name); });

        // Eliminar lienzo
        card.querySelector('[data-el="delcanvas"]').addEventListener('click', function () {
            if (!confirm('¿Eliminar este lienzo y todos sus pines? No se puede deshacer.')) return;
            api(BASE + '/lienzos/' + card._canvasId, 'DELETE').then(function () {
                card.remove(); refreshEmpty();
                toast('Lienzo eliminado.');
            }).catch(function (e) { toast(e.message); });
        });

        function setTool(cardEl, tool) {
            cardEl._tool = tool;
            if (tool) {
                banner.hidden = false;
                card.querySelector('[data-el="placelabel"]').textContent = tool.label;
                geowrap.hidden = !(data.uses_geo && tool);
                stage.classList.add('placing');
            } else {
                banner.hidden = true;
                stage.classList.remove('placing');
            }
        }

        // Pines iniciales (mismo render que los nuevos)
        (data.pins || []).forEach(function (pin) {
            stage.appendChild(buildPin(card, pin));
        });
        updateCount(card);
        return card;
    }

    // ============ Imagen compuesta (pines quemados encima) vía html2canvas ============
    function composite(card, name) {
        var stage = card.querySelector('[data-el="stage"]');
        var img = card.querySelector('[data-el="img"]');
        if (typeof html2canvas !== 'function') { toast('No se pudo cargar el compositor.'); return; }
        var scale = (img.naturalWidth && stage.clientWidth) ? (img.naturalWidth / stage.clientWidth) : 2;
        scale = Math.max(1, Math.min(4, scale));
        toast('Generando imagen…');
        html2canvas(stage, { backgroundColor: '#ffffff', scale: scale, useCORS: true, logging: false }).then(function (canvas) {
            var a = document.createElement('a');
            a.href = canvas.toDataURL('image/png');
            a.download = 'mapeo-' + (name || 'lienzo').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.png';
            document.body.appendChild(a); a.click(); a.remove();
        }).catch(function () { toast('No se pudo generar la imagen compuesta.'); });
    }

    // ============ Montaje ============
    var list = document.getElementById('cvs-list');
    function refreshEmpty() {
        document.getElementById('cvs-empty').hidden = list.querySelectorAll('.cvs-card').length > 0;
    }
    (BOOT.canvases || []).forEach(function (data) { list.appendChild(initCard(data)); });
    refreshEmpty();

    // Resalte visual de la opción de tipo elegida en el form de nuevo lienzo
    document.querySelectorAll('.cvs-type-opt input').forEach(function (r) {
        r.addEventListener('change', function () {
            document.querySelectorAll('.cvs-type-opt').forEach(function (o) { o.classList.remove('sel'); });
            if (r.checked) { r.closest('.cvs-type-opt').classList.add('sel'); }
        });
    });
})();
</script>
@endpush
@endsection
