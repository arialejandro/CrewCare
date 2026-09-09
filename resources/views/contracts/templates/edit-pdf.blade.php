@extends('layouts.app')
@section('content')
{{-- PDF FILLABLE · editor de etiquetas. pdf.js (autoalojado, offline) dibuja las páginas del PDF
     subido; el redactor arrastra etiquetas de FIRMA (anclas de la ruta) y de DATO (se auto-llenan con
     el trato) y las coloca donde van. Se guarda field_map = [{page,x_pct,y_pct,w_pct,type,key}].
     CrewCare no redacta: solo ubica y estampa. Ver contract-builder-legal-boundary. --}}
<style>
    .ccpe-wrap { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 18px; align-items: start; }
    .ccpe-side { position: sticky; top: 12px; }
    .ccpe-pal { max-height: 46vh; overflow: auto; }
    .ccpe-group-h { font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted, #6b7280); margin: .5rem .1rem .35rem; }
    .ccpe-chip { display: flex; align-items: center; gap: .4rem; width: 100%; text-align: left; border: 1px solid var(--border, #d7dbe0);
                 background: var(--surface, #fff); color: var(--text, #1f2937); border-radius: 9px; padding: .38rem .55rem; margin-bottom: .3rem;
                 font-size: .82rem; cursor: pointer; transition: border-color .12s ease, background .12s ease; }
    .ccpe-chip:hover { border-color: var(--brand, #2563eb); }
    .ccpe-chip[data-armed="1"] { border-color: var(--brand, #2563eb); background: color-mix(in srgb, var(--brand, #2563eb) 12%, var(--surface, #fff)); font-weight: 600; }
    .ccpe-chip .ccpe-dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 auto; }
    .ccpe-dot--sign { background: #7c3aed; }
    .ccpe-dot--data { background: #0891b2; }
    .ccpe-hint { font-size: .78rem; color: var(--text-muted, #6b7280); min-height: 1.3rem; }
    .ccpe-doc { background: #525659; border-radius: 10px; padding: 16px; overflow: auto; }
    .ccpe-page { position: relative; margin: 0 auto 16px; box-shadow: 0 2px 10px rgba(0,0,0,.4); background: #fff; }
    .ccpe-page:last-child { margin-bottom: 0; }
    .ccpe-ov { position: absolute; inset: 0; cursor: crosshair; }
    .ccpe-ov[data-armed="1"] { background: rgba(37,99,235,.04); }
    .ccpe-mk { position: absolute; box-sizing: border-box; border-radius: 6px; font-size: 11px; line-height: 1.15;
               padding: 3px 16px 3px 6px; cursor: move; user-select: none; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .ccpe-mk--sign { border: 1.5px dashed #7c3aed; background: rgba(124,58,237,.12); color: #4c1d95; min-height: 30px; display: flex; align-items: center; }
    .ccpe-mk--data { border: 1.5px dashed #0891b2; background: rgba(8,145,178,.12); color: #0e4a5c; min-height: 20px; display: flex; align-items: center; }
    .ccpe-mk .ccpe-x { position: absolute; top: 1px; right: 2px; width: 13px; height: 13px; line-height: 12px; text-align: center;
                       border-radius: 3px; background: rgba(0,0,0,.28); color: #fff; font-size: 11px; cursor: pointer; }
    .ccpe-mk .ccpe-x:hover { background: #dc2626; }
    .ccpe-loading { color: #e5e7eb; text-align: center; padding: 40px 0; }
    @media (max-width: 860px) {
        .ccpe-wrap { grid-template-columns: 1fr; }
        .ccpe-side { position: static; }
        .ccpe-pal { max-height: none; }
    }
</style>

<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1200px">

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Colocar etiquetas') }}</h1>
                <p class="text-muted mb-0 small">
                    {{ __('PDF cargado:') }} <strong>{{ $template->pdf_original_name ?: __('documento') }}</strong>
                    · <a href="{{ route('contracts.templates.pdf_file', $template) }}" target="_blank" rel="noopener">{{ __('ver PDF') }}</a>
                </p>
            </div>
            <a href="{{ route('contracts.templates.index') }}" class="btn btn-crew-soft ms-auto">{{ __('Volver') }}</a>
        </div>

        <div class="alert alert-warning small mb-3" role="note">
            <strong>{{ __('Responsabilidad legal de la productora.') }}</strong>
            {{ __('CrewCare solo ubica las firmas y datos sobre tu PDF y los estampa conservando el texto: no redacta ni asesora.') }}
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ route('contracts.templates.update', $template) }}" id="ccpeForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="field_map" id="ccpeFieldMap" value="[]">

            {{-- Metadatos de la plantilla --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">{{ __('Nombre') }}</label>
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $template->name) }}" required maxlength="191">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">{{ __('Aplica a') }}</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach($subtypes as $val => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="applies_to[]" id="ap_{{ $val }}" value="{{ $val }}"
                                               @checked(in_array($val, old('applies_to', $template->applies_to ?? [])))>
                                        <label class="form-check-label small" for="ap_{{ $val }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" id="ccpeActive" name="is_active" value="1" @checked($template->is_active)>
                                <label class="form-check-label small" for="ccpeActive">{{ __('Activa') }}</label>
                            </div>
                        </div>
                    </div>

                    @php $cat = old('category', $template->category ?? 'contrato'); @endphp
                    <div class="row g-3 align-items-end mt-1">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">{{ __('Tipo de documento') }}</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="category" id="cat_contrato" value="contrato" @checked($cat === 'contrato')>
                                    <label class="form-check-label small" for="cat_contrato">{{ __('Contrato principal') }}</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="category" id="cat_anexo" value="anexo" @checked($cat === 'anexo')>
                                    <label class="form-check-label small" for="cat_anexo">{{ __('Anexo (documento adicional)') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">{{ __('Orden (anexos)') }}</label>
                            <input type="number" name="sort_order" min="0" max="9999" class="form-control form-control-sm" value="{{ old('sort_order', $template->sort_order ?? 0) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Editor: paleta + documento --}}
            <div class="ccpe-wrap">
                <div class="ccpe-side">
                    <div class="card">
                        <div class="card-body">
                            <div class="ccpe-hint mb-2" id="ccpeHint">{{ __('Elige una etiqueta y haz clic sobre el documento para colocarla.') }}</div>
                            <div class="ccpe-pal">
                                <div class="ccpe-group-h">{{ __('Firmas') }}</div>
                                @foreach($anchors as $key => $label)
                                    <button type="button" class="ccpe-chip" data-type="sign" data-key="{{ $key }}" data-label="{{ $label }}">
                                        <span class="ccpe-dot ccpe-dot--sign"></span><span>{{ $label }}</span>
                                    </button>
                                @endforeach

                                <div class="ccpe-group-h mt-2">{{ __('Datos (se auto-llenan)') }}</div>
                                @foreach($fields as $key => $label)
                                    <button type="button" class="ccpe-chip" data-type="data" data-key="{{ $key }}" data-label="{{ $label }}">
                                        <span class="ccpe-dot ccpe-dot--data"></span><span>{{ $label }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <hr class="my-3">
                            <div class="small text-muted mb-2" id="ccpeCount">—</div>
                            <button type="submit" class="btn btn-crew w-100">{{ __('Guardar etiquetas') }}</button>
                            <button type="button" id="ccpePreview" class="btn btn-crew-soft w-100 mt-2">{{ __('Ver cómo quedaría') }}</button>
                            <div class="form-text mt-1">{{ __('Arrastra una etiqueta para moverla; usa la × para quitarla. La vista previa usa datos y firmas de ejemplo.') }}</div>
                        </div>
                    </div>
                </div>

                <div class="ccpe-doc" id="ccpeDoc">
                    <div class="ccpe-loading" id="ccpeLoading">{{ __('Cargando documento…') }}</div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/pdfjs/pdf.min.js') }}"></script>
<script>
(function () {
    var PDF_URL     = @json(route('contracts.templates.pdf_file', $template));
    var WORKER      = @json(asset('js/vendor/pdfjs/pdf.worker.min.js'));
    var PREVIEW_URL = @json(route('contracts.templates.pdf_preview', $template));
    var CSRF        = @json(csrf_token());
    var EXISTING    = @json($template->placedFields());
    var T = {
        armed:   @json(__('Colocando')),
        clickDoc: @json(__('— haz clic en el documento (Esc para cancelar).')),
        pick:    @json(__('Elige una etiqueta y haz clic sobre el documento para colocarla.')),
        signs:   @json(__('firma(s)')),
        datas:   @json(__('dato(s)')),
        failed:  @json(__('No se pudo dibujar el PDF. Vuelve a subir el archivo sin protección/candado.'))
    };

    if (!window.pdfjsLib) { document.getElementById('ccpeLoading').textContent = T.failed; return; }
    pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER;

    var FIELDS = [];          // estado de trabajo: {uid, page, x_pct, y_pct, w_pct, type, key, label}
    var uidSeq = 1;
    var armed = null;         // etiqueta seleccionada por colocar
    var overlays = {};        // page number -> overlay element
    var LABELS = { sign: {}, data: {} };

    // Índice de etiquetas legibles (para pintar el marcador aunque venga de field_map guardado).
    document.querySelectorAll('.ccpe-chip').forEach(function (c) {
        LABELS[c.getAttribute('data-type')][c.getAttribute('data-key')] = c.getAttribute('data-label');
    });

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function labelFor(type, key) {
        return (LABELS[type] && LABELS[type][key]) ? LABELS[type][key] : key;
    }

    function syncMap() {
        var clean = FIELDS.map(function (f) {
            return { page: f.page, x_pct: f.x_pct, y_pct: f.y_pct, w_pct: f.w_pct, type: f.type, key: f.key };
        });
        document.getElementById('ccpeFieldMap').value = JSON.stringify(clean);
        var s = FIELDS.filter(function (f) { return f.type === 'sign'; }).length;
        var d = FIELDS.length - s;
        document.getElementById('ccpeCount').textContent = s + ' ' + T.signs + ' · ' + d + ' ' + T.datas;
    }

    function setArmed(chip) {
        document.querySelectorAll('.ccpe-chip').forEach(function (c) { c.removeAttribute('data-armed'); });
        Object.keys(overlays).forEach(function (p) { overlays[p].removeAttribute('data-armed'); });
        if (!chip) {
            armed = null;
            document.getElementById('ccpeHint').textContent = T.pick;
            return;
        }
        armed = { type: chip.getAttribute('data-type'), key: chip.getAttribute('data-key'), label: chip.getAttribute('data-label') };
        chip.setAttribute('data-armed', '1');
        Object.keys(overlays).forEach(function (p) { overlays[p].setAttribute('data-armed', '1'); });
        document.getElementById('ccpeHint').textContent = T.armed + ': ' + armed.label + ' ' + T.clickDoc;
    }

    document.querySelectorAll('.ccpe-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            if (armed && armed.key === chip.getAttribute('data-key') && armed.type === chip.getAttribute('data-type')) {
                setArmed(null);              // segundo clic en la misma = deseleccionar
            } else {
                setArmed(chip);
            }
        });
    });

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setArmed(null); } });

    // Pinta un marcador en su overlay a partir de un field.
    function renderMarker(f) {
        var ov = overlays[f.page];
        if (!ov) { return; }
        var mk = document.createElement('div');
        mk.className = 'ccpe-mk ccpe-mk--' + f.type;
        mk.style.left = f.x_pct + '%';
        mk.style.top = f.y_pct + '%';
        mk.style.width = f.w_pct + '%';
        mk.setAttribute('data-uid', f.uid);
        mk.innerHTML = '<span>' + esc(f.label) + '</span><span class="ccpe-x" title="Quitar">&times;</span>';
        ov.appendChild(mk);

        mk.querySelector('.ccpe-x').addEventListener('click', function (e) {
            e.stopPropagation();
            FIELDS = FIELDS.filter(function (x) { return x.uid !== f.uid; });
            mk.remove();
            syncMap();
        });

        // Arrastre para reposicionar (pointer events + captura).
        mk.addEventListener('pointerdown', function (e) {
            if (e.target.classList.contains('ccpe-x')) { return; }
            e.preventDefault();
            e.stopPropagation();
            var rect = ov.getBoundingClientRect();
            var startX = e.clientX, startY = e.clientY;
            var baseL = f.x_pct, baseT = f.y_pct;
            mk.setPointerCapture(e.pointerId);
            function move(ev) {
                var dx = (ev.clientX - startX) / rect.width * 100;
                var dy = (ev.clientY - startY) / rect.height * 100;
                f.x_pct = Math.max(0, Math.min(100, baseL + dx));
                f.y_pct = Math.max(0, Math.min(100, baseT + dy));
                mk.style.left = f.x_pct + '%';
                mk.style.top = f.y_pct + '%';
            }
            function up(ev) {
                mk.releasePointerCapture(e.pointerId);
                mk.removeEventListener('pointermove', move);
                mk.removeEventListener('pointerup', up);
                f.x_pct = Math.round(f.x_pct * 1000) / 1000;
                f.y_pct = Math.round(f.y_pct * 1000) / 1000;
                syncMap();
            }
            mk.addEventListener('pointermove', move);
            mk.addEventListener('pointerup', up);
        });
    }

    // Clic en el overlay: coloca la etiqueta armada en ese punto.
    function onOverlayClick(page, e, ov) {
        if (!armed || e.target !== ov) { return; }
        var rect = ov.getBoundingClientRect();
        var x = (e.clientX - rect.left) / rect.width * 100;
        var y = (e.clientY - rect.top) / rect.height * 100;
        var f = {
            uid: uidSeq++,
            page: page,
            x_pct: Math.round(x * 1000) / 1000,
            y_pct: Math.round(y * 1000) / 1000,
            w_pct: (armed.type === 'sign') ? 24 : 28,
            type: armed.type,
            key: armed.key,
            label: armed.label
        };
        FIELDS.push(f);
        renderMarker(f);
        syncMap();
    }

    // Dibuja una página y su overlay.
    function drawPage(pdf, n, done) {
        pdf.getPage(n).then(function (page) {
            var host = document.getElementById('ccpeDoc');
            var maxW = Math.min(host.clientWidth - 32, 900);
            var base = page.getViewport({ scale: 1 });
            var scale = maxW / base.width;
            var viewport = page.getViewport({ scale: scale });
            var dpr = window.devicePixelRatio || 1;

            var wrap = document.createElement('div');
            wrap.className = 'ccpe-page';
            wrap.style.width = viewport.width + 'px';
            wrap.style.height = viewport.height + 'px';

            var canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width * dpr);
            canvas.height = Math.floor(viewport.height * dpr);
            canvas.style.width = viewport.width + 'px';
            canvas.style.height = viewport.height + 'px';
            wrap.appendChild(canvas);

            var ov = document.createElement('div');
            ov.className = 'ccpe-ov';
            wrap.appendChild(ov);
            overlays[n] = ov;
            if (armed) { ov.setAttribute('data-armed', '1'); }
            ov.addEventListener('click', function (e) { onOverlayClick(n, e, ov); });

            host.appendChild(wrap);

            page.render({
                canvasContext: canvas.getContext('2d'),
                viewport: viewport,
                transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null
            }).promise.then(function () { done(); });
        });
    }

    pdfjsLib.getDocument(PDF_URL).promise.then(function (pdf) {
        var loading = document.getElementById('ccpeLoading');
        if (loading) { loading.remove(); }
        var n = 1;
        function next() {
            if (n > pdf.numPages) {
                // Ya están todas las páginas: pinta las etiquetas guardadas.
                (EXISTING || []).forEach(function (f) {
                    var item = {
                        uid: uidSeq++, page: parseInt(f.page, 10) || 1,
                        x_pct: +f.x_pct || 0, y_pct: +f.y_pct || 0,
                        w_pct: +f.w_pct || (f.type === 'sign' ? 24 : 28),
                        type: (f.type === 'sign' ? 'sign' : 'data'), key: String(f.key || ''),
                        label: labelFor(f.type === 'sign' ? 'sign' : 'data', String(f.key || ''))
                    };
                    if (overlays[item.page]) { FIELDS.push(item); renderMarker(item); }
                });
                syncMap();
                return;
            }
            drawPage(pdf, n, function () { n++; next(); });
        }
        next();
    }).catch(function () {
        var loading = document.getElementById('ccpeLoading');
        if (loading) { loading.textContent = T.failed; }
    });

    // Al enviar, asegura el JSON más reciente.
    document.getElementById('ccpeForm').addEventListener('submit', syncMap);

    // "Ver cómo quedaría": estampa el PDF con las etiquetas ACTUALES + datos/firmas de ejemplo, en una
    // pestaña nueva (POST directo del field_map, sin necesidad de guardar antes).
    document.getElementById('ccpePreview').addEventListener('click', function () {
        syncMap();
        var f = document.createElement('form');
        f.method = 'POST'; f.action = PREVIEW_URL; f.target = '_blank';
        var t = document.createElement('input'); t.type = 'hidden'; t.name = '_token'; t.value = CSRF;
        var m = document.createElement('input'); m.type = 'hidden'; m.name = 'field_map';
        m.value = document.getElementById('ccpeFieldMap').value;
        f.appendChild(t); f.appendChild(m);
        document.body.appendChild(f);
        f.submit();
        document.body.removeChild(f);
    });
})();
</script>
@endpush
