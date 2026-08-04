@extends('layouts.app')
@section('title', 'Editar mapeo · ' . $map->locationName() . ' - ' . ($branding['brand_name'] ?? 'CrewCare'))

@push('styles')
<style>
    .rm-ed-top{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.1rem}
    .rm-ed-top .titlewrap{flex:1 1 260px;min-width:0}
    .rm-ed-eyebrow{font-size:.66rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700;display:inline-flex;align-items:center;gap:.4rem}
    .rm-ed-eyebrow .cc-ico{width:14px;height:14px}
    .rm-ed-title{width:100%;background:transparent;border:1px solid transparent;border-radius:8px;color:var(--text);font-family:'Poppins',sans-serif;font-weight:800;font-size:clamp(1.2rem,2.2vw,1.6rem);padding:.15rem .4rem;margin:.2rem 0 0;letter-spacing:-.01em}
    .rm-ed-title:focus{outline:none;border-color:var(--stroke);background:var(--surface-3)}
    .rm-ed-actions{display:flex;gap:.5rem;flex-wrap:wrap}
    .rm-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.55rem .9rem;border-radius:10px;text-decoration:none;font-weight:600;font-size:.86rem;border:1px solid var(--stroke);color:var(--text);background:var(--surface-3);cursor:pointer}
    .rm-btn .cc-ico{width:15px;height:15px}
    .rm-btn--accent{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary)}

    .rm-ed-grid{display:grid;grid-template-columns:230px minmax(0,1fr) 260px;gap:1rem;align-items:start}
    @media (max-width:1100px){.rm-ed-grid{grid-template-columns:1fr}}

    .rm-panel{background:var(--glass-2);border:1px solid var(--stroke);border-radius:14px;padding:.85rem}
    .rm-panel h3{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);font-weight:700;margin:0 0 .6rem}

    /* Lista de vistas */
    .rm-ed-views{list-style:none;margin:0;padding:0;display:grid;gap:.5rem}
    .rm-ed-view{display:flex;align-items:center;gap:.5rem;border:1px solid var(--stroke);border-radius:10px;padding:.35rem;background:var(--surface-3);cursor:grab}
    .rm-ed-view.is-current{border-color:var(--brand-primary);box-shadow:0 0 0 1px var(--brand-primary)}
    .rm-ed-view.dragging{opacity:.5}
    .rm-ed-view__link{display:flex;align-items:center;gap:.5rem;flex:1;min-width:0;text-decoration:none;color:var(--text)}
    .rm-ed-view__link img{width:44px;height:34px;object-fit:cover;border-radius:6px;background:#0b1220;flex:none}
    .rm-ed-view__lbl{font-size:.82rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .rm-ed-view__del{border:none;background:transparent;color:var(--text-muted);font-size:1.1rem;line-height:1;cursor:pointer;padding:0 .3rem}
    .rm-addview-toggle{width:100%;margin-top:.6rem;justify-content:center}
    .rm-addview{margin-top:.6rem;display:none}
    .rm-addview.open{display:block}
    .rm-addview label{display:block;font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin:.5rem 0 .2rem}
    .rm-addview select,.rm-addview input[type=text],.rm-addview input[type=file]{width:100%;background:var(--surface-3);color:var(--text);border:1px solid var(--stroke);border-radius:8px;padding:.45rem .5rem;font:inherit}
    .rm-src-tabs{display:flex;gap:.3rem;margin-bottom:.4rem}
    .rm-src-tabs button{flex:1;padding:.4rem;border-radius:8px;border:1px solid var(--stroke);background:var(--surface-3);color:var(--text);font-size:.78rem;cursor:pointer}
    .rm-src-tabs button.active{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary)}
    .rm-photos{display:grid;grid-template-columns:repeat(3,1fr);gap:.35rem;max-height:210px;overflow:auto;padding:.15rem}
    .rm-photos figure{margin:0;position:relative;border:2px solid transparent;border-radius:8px;overflow:hidden;cursor:pointer}
    .rm-photos figure.sel{border-color:var(--brand-primary)}
    .rm-photos img{width:100%;height:52px;object-fit:cover;display:block}
    .rm-photos .flag{position:absolute;top:2px;left:2px;background:rgba(8,12,20,.75);color:#fff;font-size:.55rem;font-weight:700;padding:1px 4px;border-radius:4px;letter-spacing:.03em}

    /* Escenario */
    .rm-ed-armbar{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.6rem}
    .rm-ed-armbar select{background:var(--surface-3);color:var(--text);border:1px solid var(--stroke);border-radius:8px;padding:.45rem .55rem;font:inherit;font-size:.82rem}
    .rm-ed-armhint{font-size:.75rem;color:var(--brand-primary);font-weight:600;align-self:center}
    .rm-ed-canvas{position:relative;border:1px solid var(--stroke);border-radius:10px;overflow:hidden;background:#0b1220;user-select:none;touch-action:none}
    .rm-ed-canvas.armed{cursor:crosshair}
    .rm-ed-canvas img{display:block;width:100%;max-height:70vh;object-fit:contain}
    .rm-ed-empty{padding:3rem 1rem;text-align:center;color:var(--text-muted)}

    .rm-pin{position:absolute;transform:translate(-50%,-100%);z-index:2;cursor:grab}
    .rm-pin.sel{z-index:4}
    .rm-pin.sel .rm-pin__drop{outline:2px solid #fff;outline-offset:1px}
    .rm-pin__drop{width:var(--pin,32px);height:var(--pin,32px);border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.5);border:1.5px solid rgba(255,255,255,.95)}
    .rm-pin__drop svg{width:calc(var(--pin,32px)*.55);height:calc(var(--pin,32px)*.55);transform:rotate(45deg)}
    .rm-pin__chip{position:absolute;bottom:calc(var(--pin,32px)*.45);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:9px;font-weight:700;color:#fff;border-radius:6px;padding:2px 7px;line-height:1.3;box-shadow:0 1px 2px rgba(0,0,0,.3);text-transform:uppercase;letter-spacing:.02em}
    .rm-pin__chip--right{left:calc(var(--pin,32px)*.55)}
    .rm-pin__chip--left{right:calc(var(--pin,32px)*.55);text-align:right}
    .rm-size{display:flex;align-items:center;gap:.3rem}
    .rm-size select{background:var(--surface-3);color:var(--text);border:1px solid var(--stroke);border-radius:8px;padding:.5rem .55rem;font:inherit;font-size:.85rem}

    /* Propiedades */
    .rm-props label{display:block;font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin:.55rem 0 .2rem}
    .rm-props input[type=text],.rm-props textarea{width:100%;background:var(--surface-3);color:var(--text);border:1px solid var(--stroke);border-radius:8px;padding:.5rem;font:inherit;resize:vertical}
    .rm-side{display:flex;gap:.3rem}
    .rm-side button{flex:1;padding:.4rem;border-radius:8px;border:1px solid var(--stroke);background:var(--surface-3);color:var(--text);cursor:pointer;font-size:.8rem}
    .rm-side button.active{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary)}
    .rm-props .selname{display:flex;align-items:center;gap:.5rem;font-weight:700;color:var(--text)}
    .rm-props .selname svg{width:18px;height:18px}
    .rm-props .empty{color:var(--text-muted);font-size:.85rem}
    .rm-del{margin-top:.8rem;color:var(--danger);border-color:color-mix(in srgb,var(--danger) 34%,transparent)}
    .rm-count{font-size:.7rem;color:var(--text-muted);text-align:right}
</style>
@endpush

@section('content')
<div class="container-fluid mt-4 mb-5">

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="rm-ed-top">
        <div class="titlewrap">
            <div class="rm-ed-eyebrow">@include('componentes._icon', ['name' => 'map-pin']) <span>{{ $map->locationName() }}</span></div>
            <input type="text" id="rm-title" class="rm-ed-title" value="{{ $map->title }}" maxlength="160" aria-label="Título del mapeo">
        </div>
        <div class="rm-ed-actions">
            <div class="rm-size" title="Tamaño del pin">
                @include('componentes._icon', ['name' => 'maximize-2'])
                <select id="rm-pin-scale" aria-label="Tamaño del pin">
                    <option value="sm" {{ $map->pin_scale === 'sm' ? 'selected' : '' }}>Pin chico</option>
                    <option value="md" {{ $map->pin_scale === 'md' ? 'selected' : '' }}>Pin mediano</option>
                    <option value="lg" {{ $map->pin_scale === 'lg' ? 'selected' : '' }}>Pin grande</option>
                </select>
            </div>
            <a href="{{ route('riskmaps.document', $map->id) }}" class="rm-btn">@include('componentes._icon', ['name' => 'eye']) Vista previa</a>
            <form action="{{ route('riskmaps.seal', $map->id) }}" method="POST" onsubmit="return confirm('Sellar el mapeo lo vuelve INMUTABLE. ¿Continuar?');" style="display:inline">
                @csrf
                <button type="submit" class="rm-btn rm-btn--accent">@include('componentes._icon', ['name' => 'shield-check']) Sellar</button>
            </form>
        </div>
    </div>

    <div class="rm-ed-grid">

        {{-- IZQUIERDA: vistas --}}
        <div class="rm-panel">
            <h3>Vistas</h3>
            <ul class="rm-ed-views" id="rm-views">
                @foreach($views as $v)
                    <li class="rm-ed-view {{ $current && $current->id === $v->id ? 'is-current' : '' }}" draggable="true" data-id="{{ $v->id }}">
                        <a href="{{ route('riskmaps.edit', ['id' => $map->id, 'view' => $v->id]) }}" class="rm-ed-view__link">
                            <img src="{{ $v->imageUrl() }}" alt="">
                            <span class="rm-ed-view__lbl">{{ $v->displayLabel() }}</span>
                        </a>
                        <form action="{{ route('riskmaps.views.destroy', ['id' => $map->id, 'view' => $v->id]) }}" method="POST" onsubmit="return confirm('¿Quitar esta vista?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="rm-ed-view__del" title="Quitar">×</button>
                        </form>
                    </li>
                @endforeach
            </ul>

            <button type="button" class="rm-btn rm-addview-toggle" id="rm-addview-toggle">@include('componentes._icon', ['name' => 'plus']) Agregar vista</button>

            <form class="rm-addview" id="rm-addview" action="{{ route('riskmaps.views.store', $map->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="rm-src-tabs">
                    <button type="button" class="active" data-src="scouting_photo">Del scouting</button>
                    <button type="button" data-src="upload">Subir</button>
                </div>
                <input type="hidden" name="source" id="rm-src" value="scouting_photo">

                <div id="rm-src-photos">
                    @if(count($scoutingPhotos))
                        <input type="hidden" name="scouting_path" id="rm-scouting-path" value="">
                        <div class="rm-photos">
                            @foreach($scoutingPhotos as $ph)
                                <figure data-path="{{ $ph['path'] }}" title="{{ $ph['caption'] }}">
                                    <img src="{{ $ph['path'] }}" alt="">
                                    @if($ph['flagged'])<span class="flag">Mapeo</span>@endif
                                </figure>
                            @endforeach
                        </div>
                    @else
                        <p class="empty" style="color:var(--text-muted);font-size:.82rem;margin:.4rem 0">El scouting no tiene imágenes. Sube una.</p>
                    @endif
                </div>

                <div id="rm-src-upload" style="display:none">
                    <label>Imagen</label>
                    <input type="file" name="image" id="rm-file" accept="image/*" data-cc-photo>
                </div>

                <label>Tipo de vista</label>
                <select name="view_type" id="rm-view-type">
                    @foreach($viewTypes as $k => $lbl)<option value="{{ $k }}">{{ $lbl }}</option>@endforeach
                </select>

                <label>Etiqueta (opcional)</label>
                <input type="text" name="label" maxlength="160" placeholder="Se propone del tipo">

                <button type="submit" class="rm-btn rm-btn--accent" style="width:100%;justify-content:center;margin-top:.7rem">Agregar</button>
            </form>
        </div>

        {{-- CENTRO: escenario --}}
        <div class="rm-panel">
            @if($current)
                <div class="rm-ed-armbar">
                    <select id="rm-arm-res">
                        <option value="">+ Recurso…</option>
                        @foreach($resourceTypes as $k => $lbl)<option value="{{ $k }}">{{ $lbl }}</option>@endforeach
                    </select>
                    <select id="rm-arm-haz">
                        <option value="">+ Peligro…</option>
                        @foreach($eligibleEvents as $id => $ev)<option value="{{ $id }}">{{ $ev['name'] }}</option>@endforeach
                    </select>
                    <span class="rm-ed-armhint" id="rm-arm-hint"></span>
                </div>
                <div class="rm-ed-canvas" id="rm-canvas" style="--pin: {{ $map->pinPx() }}px">
                    <img src="{{ $current->imageUrl() }}" alt="{{ $current->displayLabel() }}" id="rm-canvas-img" draggable="false">
                </div>
                <p class="rm-count" id="rm-count"></p>
                @if($eligibleEvents->isEmpty())
                    <p style="color:var(--text-muted);font-size:.78rem;margin:.4rem 0 0">No hay peligros evaluados en el scouting para colocar.</p>
                @endif
            @else
                <div class="rm-ed-empty">
                    @include('componentes._icon', ['name' => 'image'])
                    <p>Agrega una vista para empezar.</p>
                </div>
            @endif
        </div>

        {{-- DERECHA: narrativa + propiedades --}}
        <div class="rm-panel">
            @if($current)
                <h3>Narrativa de la vista</h3>
                <div class="rm-props">
                    <label>Etiqueta</label>
                    <input type="text" id="rm-view-label" maxlength="160" value="{{ $current->label }}" placeholder="{{ $current->typeLabel() }}">
                    <label>Qué hay aquí</label>
                    <textarea id="rm-nar-what" rows="2" maxlength="280">{{ $current->narrative_what }}</textarea>
                    <label>Qué se decidió</label>
                    <textarea id="rm-nar-decision" rows="2" maxlength="280">{{ $current->narrative_decision }}</textarea>
                    <label>Qué debe hacer el crew</label>
                    <textarea id="rm-nar-action" rows="2" maxlength="280">{{ $current->narrative_action }}</textarea>
                </div>

                <hr style="border-color:var(--stroke);margin:1rem 0">

                <h3>Marcador seleccionado</h3>
                <div class="rm-props" id="rm-props">
                    <p class="empty" id="rm-props-empty">Toca un marcador para editarlo.</p>
                    <div id="rm-props-body" style="display:none">
                        <div class="selname" id="rm-sel-name"></div>
                        <label>Lado de la etiqueta</label>
                        <div class="rm-side">
                            <button type="button" id="rm-side-left">Izquierda</button>
                            <button type="button" id="rm-side-right">Derecha</button>
                        </div>
                        <button type="button" class="rm-btn rm-del" id="rm-del" style="width:100%;justify-content:center;margin-top:.8rem">Quitar marcador</button>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>

{{-- Datos para el editor (sin {{ }} dentro de <script>: van por JSON) --}}
@php
    $__iconKeys = array_merge(array_keys($resourceTypes), ['hazard', 'area']);
    $__icons = [];
    foreach ($__iconKeys as $k) { $__icons[$k] = trim(view('componentes._rm-icon', ['key' => $k])->render()); }
    $__markers = $current ? $current->markers->map(function ($m) use ($map) {
        if ($m->kind === 'resource') { $lbl = $m->resourceLabel(); $sh = $lbl; }
        else { $ev = $map->eligibleEvents()->get((int) $m->event_id); $lbl = $ev['name'] ?? ('#' . $m->event_id); $sh = $ev['short'] ?? 'Peligro'; }
        return [
            'id' => $m->id, 'kind' => $m->kind, 'resource_type' => $m->resource_type,
            'event_id' => $m->event_id, 'x_pct' => (float) $m->x_pct, 'y_pct' => (float) $m->y_pct,
            'label_side' => $m->label_side, 'reference_text' => $m->reference_text,
            'icon' => $m->iconKey(), 'label' => $lbl, 'short' => $sh, 'color' => $m->color(),
        ];
    })->values() : [];
    $__eligible = [];
    foreach ($eligibleEvents as $id => $ev) { $__eligible[(string) $id] = $ev['name']; }

    $__urls = $current ? [
        'markerStore' => route('riskmaps.markers.store', ['id' => $map->id, 'view' => $current->id]),
        'markerItem'  => route('riskmaps.markers.update', ['id' => $map->id, 'view' => $current->id, 'marker' => '__M__']),
        'viewUpdate'  => route('riskmaps.views.update', ['id' => $map->id, 'view' => $current->id]),
    ] : [];

    $__rmData = [
        'csrf'          => csrf_token(),
        'hasView'       => (bool) $current,
        'urls'          => $__urls,
        'reorder'       => route('riskmaps.views.reorder', $map->id),
        'metaUpdate'    => route('riskmaps.update', $map->id),
        'markers'       => $__markers,
        'eligible'      => (object) $__eligible,
        'resourceTypes' => (object) $resourceTypes,
        'icons'         => (object) $__icons,
    ];
@endphp
<script type="application/json" id="rm-data">@json($__rmData)</script>

<script src="/js/cc-photo.js"></script>
<script>
(function () {
    'use strict';
    var el = document.getElementById('rm-data');
    if (!el) { return; }
    var DATA = JSON.parse(el.textContent);

    function post(url, method, fields, done) {
        var fd = new FormData();
        fd.append('_token', DATA.csrf);
        if (method !== 'POST') { fd.append('_method', method); }
        Object.keys(fields).forEach(function (k) {
            if (fields[k] !== null && fields[k] !== undefined) { fd.append(k, fields[k]); }
        });
        fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) { if (done) { done(res); } })
            .catch(function () { if (done) { done({ ok: false, body: {} }); } });
    }

    /* ---- Título + narrativa: autosave ---- */
    (function () {
        var title = document.getElementById('rm-title');
        if (title) {
            title.addEventListener('blur', function () {
                var v = title.value.trim(); if (v === '') { return; }
                post(DATA.metaUpdate, 'PUT', { title: v });
            });
        }
        // Tamaño del pin: aplica en vivo (--pin en el lienzo) y persiste.
        var scaleSel = document.getElementById('rm-pin-scale');
        if (scaleSel) {
            var PIN_PX = { sm: 24, md: 32, lg: 42 };
            scaleSel.addEventListener('change', function () {
                var v = scaleSel.value, cv = document.getElementById('rm-canvas');
                if (cv && PIN_PX[v]) { cv.style.setProperty('--pin', PIN_PX[v] + 'px'); }
                post(DATA.metaUpdate, 'PUT', { pin_scale: v });
            });
        }
        if (!DATA.hasView) { return; }
        var map = {
            'rm-view-label': 'label', 'rm-nar-what': 'narrative_what',
            'rm-nar-decision': 'narrative_decision', 'rm-nar-action': 'narrative_action'
        };
        Object.keys(map).forEach(function (id) {
            var node = document.getElementById(id);
            if (!node) { return; }
            node.addEventListener('blur', function () {
                var f = {}; f[map[id]] = node.value; post(DATA.urls.viewUpdate, 'PUT', f);
            });
        });
    })();

    /* ---- Panel "Agregar vista" ---- */
    (function () {
        var toggle = document.getElementById('rm-addview-toggle');
        var panel = document.getElementById('rm-addview');
        if (toggle && panel) { toggle.addEventListener('click', function () { panel.classList.toggle('open'); }); }

        var tabs = document.querySelectorAll('.rm-src-tabs button');
        var srcInput = document.getElementById('rm-src');
        var photos = document.getElementById('rm-src-photos');
        var upload = document.getElementById('rm-src-upload');
        tabs.forEach(function (b) {
            b.addEventListener('click', function () {
                tabs.forEach(function (x) { x.classList.remove('active'); });
                b.classList.add('active');
                var src = b.getAttribute('data-src');
                if (srcInput) { srcInput.value = src; }
                if (photos) { photos.style.display = src === 'scouting_photo' ? '' : 'none'; }
                if (upload) { upload.style.display = src === 'upload' ? '' : 'none'; }
            });
        });

        var pathInput = document.getElementById('rm-scouting-path');
        document.querySelectorAll('.rm-photos figure').forEach(function (fig) {
            fig.addEventListener('click', function () {
                document.querySelectorAll('.rm-photos figure').forEach(function (x) { x.classList.remove('sel'); });
                fig.classList.add('sel');
                if (pathInput) { pathInput.value = fig.getAttribute('data-path'); }
            });
        });

        // Compresión del archivo subido (CCPhoto), como el resto de la app.
        var file = document.getElementById('rm-file');
        if (file && window.CCPhoto) {
            file.addEventListener('change', function () {
                if (!file.files || !file.files[0]) { return; }
                var f = file.files[0];
                if (!window.CCPhoto.isImage(f)) { return; }
                window.CCPhoto.process(f).then(function (out) {
                    try {
                        var dt = new DataTransfer();
                        dt.items.add(out);
                        file.files = dt.files;
                    } catch (e) { /* navegador sin DataTransfer: se sube el original */ }
                });
            });
        }
    })();

    /* ---- Reordenar vistas por arrastre ---- */
    (function () {
        var list = document.getElementById('rm-views');
        if (!list) { return; }
        var dragging = null;
        list.querySelectorAll('.rm-ed-view').forEach(function (li) {
            li.addEventListener('dragstart', function () { dragging = li; li.classList.add('dragging'); });
            li.addEventListener('dragend', function () {
                li.classList.remove('dragging'); dragging = null;
                var order = Array.prototype.map.call(list.querySelectorAll('.rm-ed-view'), function (x) { return x.getAttribute('data-id'); });
                var fd = new FormData(); fd.append('_token', DATA.csrf);
                order.forEach(function (id) { fd.append('order[]', id); });
                fetch(DATA.reorder, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            });
        });
        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragging) { return; }
            var after = null;
            list.querySelectorAll('.rm-ed-view:not(.dragging)').forEach(function (li) {
                var box = li.getBoundingClientRect();
                if (e.clientY - box.top - box.height / 2 < 0 && after === null) { after = li; }
            });
            if (after) { list.insertBefore(dragging, after); } else { list.appendChild(dragging); }
        });
    })();

    /* ================= MARCADORES ================= */
    if (!DATA.hasView) { return; }

    var canvas = document.getElementById('rm-canvas');
    var img = document.getElementById('rm-canvas-img');
    var countEl = document.getElementById('rm-count');
    var markers = DATA.markers || [];
    var selected = null;   // { data, el }
    var armed = null;      // { kind, resource_type?, event_id? }

    function refreshCount() {
        if (countEl) { countEl.textContent = markers.length + (markers.length === 1 ? ' marcador' : ' marcadores'); }
    }

    function iconFor(key) { return DATA.icons[key] || DATA.icons['area'] || ''; }

    function buildPin(m) {
        var pin = document.createElement('div');
        pin.className = 'rm-pin rm-pin--' + m.kind;
        pin.setAttribute('data-id', m.id);
        pin.style.left = m.x_pct + '%';
        pin.style.top = m.y_pct + '%';
        var drop = document.createElement('div');
        drop.className = 'rm-pin__drop';
        drop.style.background = m.color || '#c0392b';
        drop.innerHTML = iconFor(m.icon);
        var chip = document.createElement('div');
        chip.className = 'rm-pin__chip rm-pin__chip--' + (m.label_side === 'left' ? 'left' : 'right');
        chip.style.background = m.color || '#c0392b';
        chip.textContent = m.short || m.label || '';
        pin.title = (m.label || '') + (m.reference_text ? ' — ' + m.reference_text : '');
        pin.appendChild(drop);
        pin.appendChild(chip);
        attachPin(pin, m);
        return pin;
    }

    function renderAll() {
        canvas.querySelectorAll('.rm-pin').forEach(function (p) { p.remove(); });
        markers.forEach(function (m) { canvas.appendChild(buildPin(m)); });
        refreshCount();
    }

    function pct(e) {
        var box = img.getBoundingClientRect();
        var x = ((e.clientX - box.left) / box.width) * 100;
        var y = ((e.clientY - box.top) / box.height) * 100;
        return { x: Math.max(0, Math.min(100, x)), y: Math.max(0, Math.min(100, y)) };
    }

    /* Colocar un marcador nuevo al hacer clic (cuando hay algo armado) */
    canvas.addEventListener('click', function (e) {
        if (!armed) { return; }
        if (e.target.closest('.rm-pin')) { return; }
        var p = pct(e);
        var fields = { kind: armed.kind, x_pct: p.x.toFixed(3), y_pct: p.y.toFixed(3), label_side: (p.x > 60 ? 'left' : 'right') };
        if (armed.kind === 'resource') { fields.resource_type = armed.resource_type; }
        else { fields.event_id = armed.event_id; }
        post(DATA.urls.markerStore, 'POST', fields, function (res) {
            if (res.ok && res.body && res.body.marker) {
                markers.push(res.body.marker);
                var pin = buildPin(res.body.marker);
                canvas.appendChild(pin);
                selectMarker(res.body.marker, pin);
                refreshCount();
            } else if (res.body && res.body.error) {
                alert(res.body.error);
            }
            disarm();
        });
    });

    /* Arrastrar / seleccionar un pin */
    function attachPin(pin, m) {
        var start = null, moved = false;
        pin.addEventListener('pointerdown', function (e) {
            e.stopPropagation();
            start = { x: e.clientX, y: e.clientY };
            moved = false;
            pin.setPointerCapture(e.pointerId);
        });
        pin.addEventListener('pointermove', function (e) {
            if (!start) { return; }
            if (Math.abs(e.clientX - start.x) > 3 || Math.abs(e.clientY - start.y) > 3) { moved = true; }
            if (!moved) { return; }
            var p = pct(e);
            pin.style.left = p.x + '%';
            pin.style.top = p.y + '%';
            m.x_pct = p.x; m.y_pct = p.y;
        });
        pin.addEventListener('pointerup', function (e) {
            if (start) { try { pin.releasePointerCapture(e.pointerId); } catch (er) {} }
            start = null;
            if (moved) { saveMarker(m); }
            else { selectMarker(m, pin); }
        });
    }

    function selectMarker(m, pin) {
        selected = { data: m, el: pin };
        canvas.querySelectorAll('.rm-pin').forEach(function (p) { p.classList.remove('sel'); });
        pin.classList.add('sel');
        var body = document.getElementById('rm-props-body');
        var empty = document.getElementById('rm-props-empty');
        if (empty) { empty.style.display = 'none'; }
        if (body) { body.style.display = ''; }
        var name = document.getElementById('rm-sel-name');
        if (name) { name.innerHTML = iconFor(m.icon) + '<span>' + escapeHtml(m.label || '') + '</span>'; }
        var ref = document.getElementById('rm-ref');
        if (ref) { ref.value = m.reference_text || ''; }
        setSideButtons(m.label_side);
    }

    function setSideButtons(side) {
        var l = document.getElementById('rm-side-left'), r = document.getElementById('rm-side-right');
        if (l) { l.classList.toggle('active', side === 'left'); }
        if (r) { r.classList.toggle('active', side !== 'left'); }
    }

    function saveMarker(m) {
        var url = DATA.urls.markerItem.replace('__M__', m.id);
        var fields = {
            kind: m.kind, x_pct: (+m.x_pct).toFixed(3), y_pct: (+m.y_pct).toFixed(3),
            label_side: m.label_side || 'right', reference_text: m.reference_text || ''
        };
        if (m.kind === 'resource') { fields.resource_type = m.resource_type; }
        else { fields.event_id = m.event_id; }
        post(url, 'PUT', fields, function (res) {
            if (res.ok && res.body && res.body.marker && selected && selected.data.id === m.id) {
                Object.assign(m, res.body.marker);
            }
        });
    }

    /* Controles del panel de propiedades */
    (function () {
        var left = document.getElementById('rm-side-left');
        var right = document.getElementById('rm-side-right');
        if (left) { left.addEventListener('click', function () { if (!selected) { return; } selected.data.label_side = 'left'; applySide(); }); }
        if (right) { right.addEventListener('click', function () { if (!selected) { return; } selected.data.label_side = 'right'; applySide(); }); }
        function applySide() {
            var m = selected.data, chip = selected.el.querySelector('.rm-pin__chip');
            if (chip) { chip.className = 'rm-pin__chip rm-pin__chip--' + (m.label_side === 'left' ? 'left' : 'right'); }
            setSideButtons(m.label_side);
            saveMarker(m);
        }
        var ref = document.getElementById('rm-ref');
        if (ref) {
            ref.addEventListener('blur', function () {
                if (!selected) { return; }
                selected.data.reference_text = ref.value.trim();
                var chip = selected.el.querySelector('.rm-pin__chip');
                if (chip) {
                    chip.innerHTML = '';
                    chip.appendChild(document.createTextNode(selected.data.label || ''));
                    if (selected.data.reference_text) {
                        var s = document.createElement('span'); s.className = 'ref'; s.textContent = selected.data.reference_text; chip.appendChild(s);
                    }
                }
                saveMarker(selected.data);
            });
        }
        var del = document.getElementById('rm-del');
        if (del) {
            del.addEventListener('click', function () {
                if (!selected) { return; }
                var m = selected.data, url = DATA.urls.markerItem.replace('__M__', m.id);
                post(url, 'DELETE', {}, function () {});
                selected.el.remove();
                markers = markers.filter(function (x) { return x.id !== m.id; });
                selected = null;
                document.getElementById('rm-props-body').style.display = 'none';
                document.getElementById('rm-props-empty').style.display = '';
                refreshCount();
            });
        }
    })();

    /* Armar colocación desde los selects */
    function disarm() {
        armed = null;
        canvas.classList.remove('armed');
        var hint = document.getElementById('rm-arm-hint'); if (hint) { hint.textContent = ''; }
        var r = document.getElementById('rm-arm-res'), h = document.getElementById('rm-arm-haz');
        if (r) { r.value = ''; } if (h) { h.value = ''; }
    }
    var armRes = document.getElementById('rm-arm-res');
    var armHaz = document.getElementById('rm-arm-haz');
    if (armRes) {
        armRes.addEventListener('change', function () {
            if (!armRes.value) { disarm(); return; }
            if (armHaz) { armHaz.value = ''; }
            armed = { kind: 'resource', resource_type: armRes.value };
            canvas.classList.add('armed');
            var hint = document.getElementById('rm-arm-hint');
            if (hint) { hint.textContent = 'Toca la imagen para colocar'; }
        });
    }
    if (armHaz) {
        armHaz.addEventListener('change', function () {
            if (!armHaz.value) { disarm(); return; }
            if (armRes) { armRes.value = ''; }
            armed = { kind: 'hazard', event_id: armHaz.value };
            canvas.classList.add('armed');
            var hint = document.getElementById('rm-arm-hint');
            if (hint) { hint.textContent = 'Toca la imagen para colocar'; }
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    renderAll();
})();
</script>
@endsection
