@extends('layouts.app')
@section('content')
{{-- CONTRACT BUILDER · editor Word-lite: canvas WYSIWYG + CHIPS de datos/firmas (sin llaves a la
     vista) + barra de formato + "Añadir cláusula" numerada. Escotilla "Ver HTML" para el owner.
     El body se serializa a HTML con {{tokens}} al enviar; la vista previa usa datos de ejemplo. --}}
@php $isNew = ! $template->exists; @endphp
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1180px">

        <div class="mb-3">
            <a href="{{ route('contracts.templates.index') }}" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'arrow-left', 'label' => null]) {{ __('Plantillas') }}
            </a>
        </div>

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ $isNew ? __('Nueva plantilla') : $template->name }}</h1>
                <p class="text-muted mb-0 small">{{ __('Elige el formato, escribe el contrato e inserta datos y firmas desde la barra. La vista previa usa datos de ejemplo.') }}</p>
            </div>
        </div>

        {{-- Descargo legal — CrewCare no redacta ni asume responsabilidad legal (contract-builder-legal-boundary). --}}
        <div class="alert alert-warning small mb-3" role="note">
            <strong>{{ __('Responsabilidad legal de la productora.') }}</strong>
            {{ __('El contenido jurídico del contrato lo define y respalda la productora (su área legal). CrewCare solo ensambla los datos del trato, numera y estampa las firmas: no redacta contratos ni brinda asesoría legal.') }}
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ $isNew ? route('contracts.templates.store') : route('contracts.templates.update', $template) }}" id="tplForm">
            @csrf
            @unless($isNew)@method('PUT')@endunless
            <input type="hidden" name="body" id="tplBodyInput">
            <input type="hidden" name="language" value="{{ old('language', $template->language ?: 'es') }}">
            <input type="hidden" name="bilingual" value="0">

            <div class="row g-4">
                {{-- ── Editor ── --}}
                <div class="col-12 col-lg-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('Nombre') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold d-block">{{ __('Aplica a') }}</label>
                                @foreach($subtypes as $val => $label)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="applies_to[]" value="{{ $val }}"
                                               id="st_{{ $val }}" @checked(in_array($val, old('applies_to', $template->applies_to ?? []), true))>
                                        <label class="form-check-label" for="st_{{ $val }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>

                            {{-- FORMATO del contrato: define la forma + el andamiaje. --}}
                            <div class="mb-3">
                                <label class="form-label fw-semibold d-block">{{ __('Formato del contrato') }}</label>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <select name="architecture" id="tplArch" class="form-select form-select-sm" style="max-width:360px">
                                        @foreach($architectures as $key => $a)
                                            <option value="{{ $key }}" @selected(old('architecture', $template->architecture ?: 'caratula_numbered') === $key)>{{ $a['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" id="tplLoadScaffold" class="btn btn-sm btn-crew-soft">{{ __('Cargar andamiaje') }}</button>
                                </div>
                                <div class="form-text" id="tplArchDesc"></div>
                            </div>

                            {{-- BARRA de formato (WYSIWYG) --}}
                            <label class="form-label fw-semibold d-block">{{ __('Contrato') }}</label>
                            <div class="cc-toolbar" role="toolbar" aria-label="{{ __('Formato del texto') }}">
                                <button type="button" class="cc-tb" data-cmd="formatBlock" data-arg="h2" title="{{ __('Título de sección') }}"><strong>H</strong></button>
                                <button type="button" class="cc-tb" data-cmd="formatBlock" data-arg="p" title="{{ __('Texto normal') }}">¶</button>
                                <span class="cc-tb-sep"></span>
                                <button type="button" class="cc-tb" data-cmd="bold" title="{{ __('Negrita') }}"><strong>B</strong></button>
                                <button type="button" class="cc-tb" data-cmd="italic" title="{{ __('Cursiva') }}"><em>I</em></button>
                                <button type="button" class="cc-tb" data-cmd="insertUnorderedList" title="{{ __('Lista') }}">&bull;</button>
                                <span class="cc-tb-sep"></span>
                                <button type="button" class="cc-tb cc-tb--wide" id="tplAddClause">+ {{ __('Cláusula') }}</button>
                                <select class="form-select form-select-sm cc-tb-select" id="tplInsField" aria-label="{{ __('Insertar dato') }}">
                                    <option value="">+ {{ __('Insertar dato…') }}</option>
                                    @foreach($fields as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select class="form-select form-select-sm cc-tb-select" id="tplInsSig" aria-label="{{ __('Insertar firma') }}">
                                    <option value="">+ {{ __('Insertar firma…') }}</option>
                                    @foreach($anchors as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="cc-tb ms-auto" id="tplToggleHtml" title="{{ __('Ver / editar HTML') }}">&lt;/&gt;</button>
                            </div>

                            {{-- Canvas editable (Word-lite) --}}
                            <div id="tplCanvas" class="cc-canvas" contenteditable="true" spellcheck="true"></div>

                            {{-- Escotilla HTML (oculta por defecto; para el owner) --}}
                            <textarea id="tplHtml" class="form-control cc-html d-none" rows="16"
                                      style="font-family:ui-monospace,Consolas,monospace;font-size:.84rem;">{{ old('body', $template->body) }}</textarea>

                            <div class="form-text">{{ __('Inserta datos y firmas desde la barra: se ven como etiquetas y se rellenan al emitir el contrato.') }}</div>

                            <div class="d-flex align-items-center gap-3 mt-3">
                                <button class="btn btn-crew">{{ __('Guardar') }}</button>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                           @checked(old('is_active', $template->is_active))>
                                    <label class="form-check-label" for="is_active">{{ __('Activa') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Preview ── --}}
                <div class="col-12 col-lg-6">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <span class="fw-semibold">{{ __('Vista previa') }} <span class="text-muted small">({{ __('datos de ejemplo') }})</span></span>
                            <button type="button" id="tplRefresh" class="btn btn-sm btn-crew-soft">{{ __('Actualizar') }}</button>
                        </div>
                        <div class="card-body">
                            <iframe id="tplPreview" title="{{ __('Vista previa') }}" sandbox=""
                                    style="width:100%;height:560px;border:1px solid var(--border, #d7dce4);border-radius:10px;background:#fff;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
.cc-toolbar{display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:6px;border:1px solid var(--border,#d7dce4);border-bottom:0;border-radius:10px 10px 0 0;background:var(--surface-2,#f6f7f9)}
.cc-tb{min-width:32px;height:32px;padding:0 9px;border:1px solid var(--border,#d7dce4);border-radius:7px;background:var(--surface,#fff);color:var(--text,#1a1a1a);cursor:pointer;font-size:.9rem;line-height:1;transition:transform .12s ease, background .12s ease}
.cc-tb:hover{background:var(--surface-2,#eef1f5)}
.cc-tb:active{transform:scale(.96)}
.cc-tb--wide{width:auto;font-weight:600;color:var(--brand,#ff0046)}
.cc-tb-sep{width:1px;height:22px;background:var(--border,#d7dce4);margin:0 2px}
.cc-tb-select{max-width:168px;height:32px}
.cc-canvas{min-height:340px;max-height:60vh;overflow:auto;padding:16px 18px;border:1px solid var(--border,#d7dce4);border-radius:0 0 10px 10px;background:var(--surface,#fff);color:var(--text,#1a1a1a);font-family:Georgia,"Times New Roman",serif;line-height:1.6;font-size:.95rem}
.cc-canvas:focus{outline:2px solid color-mix(in srgb, var(--brand,#ff0046) 45%, transparent);outline-offset:-1px}
.cc-canvas h1{font-size:1.25rem;text-align:center}
.cc-canvas h2{font-size:1.02rem;border-bottom:1px solid var(--border,#e2e2e2);padding-bottom:3px;margin-top:1.1rem}
.cc-canvas table{width:100%;border-collapse:collapse}
.cc-canvas td{padding:5px 7px}
.cc-tok{display:inline-block;padding:1px 8px;margin:0 1px;border-radius:999px;background:color-mix(in srgb, var(--brand,#ff0046) 12%, var(--surface,#fff));border:1px solid color-mix(in srgb, var(--brand,#ff0046) 35%, transparent);color:var(--text,#10151f);font-family:system-ui,-apple-system,sans-serif;font-size:.8rem;white-space:nowrap;user-select:all;cursor:default}
.cc-tok--sig{background:color-mix(in srgb, #2563eb 14%, var(--surface,#fff));border-color:color-mix(in srgb, #2563eb 38%, transparent)}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var FIELDS   = @json($fields);
    var ANCHORS  = @json($anchors);
    var archMeta = @json($architectures);
    var starters = @json($starters);

    var canvas   = document.getElementById('tplCanvas');
    var htmlArea = document.getElementById('tplHtml');
    var bodyIn   = document.getElementById('tplBodyInput');
    var archSel  = document.getElementById('tplArch');
    var LB = '{' + '{', RB = '}' + '}';   // evita que Blade parsee llaves literales en este script

    function esc(s){ return String(s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

    // ── Chips (etiquetas visibles; se serializan a tokens) ───────────────
    function fieldChip(k){ return '<span class="cc-tok" contenteditable="false" data-field="'+esc(k)+'">'+esc(FIELDS[k]||k)+'</span>'; }
    function anchorChip(k){ return '<span class="cc-tok cc-tok--sig" contenteditable="false" data-anchor="'+esc(k)+'">✍ '+esc(ANCHORS[k]||k)+'</span>'; }

    // ── Hydrate: body con tokens -> canvas con chips ─────────────────────
    function hydrate(html){
        var out = String(html || '').replace(/\[\[firma:([a-z0-9_:\-]+)\]\]/gi, function(_, k){ return anchorChip(k.toLowerCase()); });
        out = out.replace(/\{\{\s*([a-z0-9_.]+)\s*\}\}/gi, function(_, k){ return fieldChip(k.toLowerCase()); });
        canvas.innerHTML = out;
    }

    // ── Serialize: canvas -> HTML limpio con tokens ──────────────────────
    var ALLOWED = {H1:'h1',H2:'h2',H3:'h3',P:'p',STRONG:'strong',EM:'em',U:'u',BR:'br',UL:'ul',OL:'ol',LI:'li',TABLE:'table',THEAD:'thead',TBODY:'tbody',TR:'tr',TD:'td',TH:'th',DIV:'p',B:'strong',I:'em'};
    var KEEP = {style:1,border:1,colspan:1,rowspan:1,align:1,class:1,cellpadding:1,cellspacing:1,width:1};
    function ser(node){
        if(node.nodeType === 3){ return esc(node.nodeValue); }
        if(node.nodeType !== 1){ return ''; }
        if(node.hasAttribute && node.hasAttribute('data-field')){ return LB + node.getAttribute('data-field') + RB; }
        if(node.hasAttribute && node.hasAttribute('data-anchor')){ return '[[firma:' + node.getAttribute('data-anchor') + ']]'; }
        var tag = ALLOWED[node.tagName];
        var inner = ''; node.childNodes.forEach(function(c){ inner += ser(c); });
        if(!tag){ return inner; }                 // etiqueta desconocida -> desenvolver
        if(tag === 'br'){ return '<br>'; }
        var at = ''; Array.prototype.forEach.call(node.attributes || [], function(a){ if(KEEP[a.name.toLowerCase()]){ at += ' ' + a.name + '="' + esc(a.value) + '"'; } });
        return '<' + tag + at + '>' + inner + '</' + tag + '>';
    }
    function serialize(){ var s = ''; canvas.childNodes.forEach(function(c){ s += ser(c); }); return s; }

    // ── Insertar HTML en el cursor (o al final si no hay foco) ───────────
    function insertAtCaret(html){
        canvas.focus();
        var sel = window.getSelection();
        if(!sel.rangeCount || !canvas.contains(sel.anchorNode)){
            canvas.insertAdjacentHTML('beforeend', html);
        } else {
            document.execCommand('insertHTML', false, html);
        }
        schedulePreview();
    }

    // ── Ordinales femeninos para "Añadir cláusula" ──────────────────────
    var UNI = ['', 'PRIMERA','SEGUNDA','TERCERA','CUARTA','QUINTA','SEXTA','SÉPTIMA','OCTAVA','NOVENA'];
    var DEC = ['', 'DÉCIMA','VIGÉSIMA','TRIGÉSIMA','CUADRAGÉSIMA'];
    function ordinal(n){
        if(n <= 0){ return 'PRIMERA'; }
        if(n < 10){ return UNI[n]; }
        if(n <= 40 && n % 10 === 0){ return DEC[n/10]; }
        if(n < 50){ return (DEC[Math.floor(n/10)] || '') + ' ' + (UNI[n % 10] || ''); }
        return n + 'A';
    }

    // ── Preview ─────────────────────────────────────────────────────────
    var frame = document.getElementById('tplPreview');
    var meta  = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.content : '';
    var url   = @json(route('contracts.templates.preview'));
    var htmlMode = false, pvTimer = null;
    function currentBody(){ return htmlMode ? htmlArea.value : serialize(); }
    function refresh(){
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': token },
            body: 'body=' + encodeURIComponent(currentBody()) + '&architecture=' + encodeURIComponent(archSel ? archSel.value : '')
        }).then(function(r){ return r.text(); }).then(function(h){ frame.srcdoc = h; });
    }
    function schedulePreview(){ clearTimeout(pvTimer); pvTimer = setTimeout(refresh, 450); }

    function updateArchDesc(){ var m = archSel && archMeta[archSel.value]; var el = document.getElementById('tplArchDesc'); if(el){ el.textContent = m ? m.desc : ''; } }

    // ── Barra de formato ────────────────────────────────────────────────
    document.querySelectorAll('.cc-tb[data-cmd]').forEach(function(b){
        b.addEventListener('click', function(){
            var cmd = b.getAttribute('data-cmd'), arg = b.getAttribute('data-arg');
            canvas.focus();
            document.execCommand(cmd, false, cmd === 'formatBlock' ? '<' + arg + '>' : (arg || null));
            schedulePreview();
        });
    });
    document.getElementById('tplAddClause').addEventListener('click', function(){
        var n = canvas.querySelectorAll('p.cc-clause').length + 1;
        insertAtCaret('<p class="cc-clause"><strong>' + ordinal(n) + '. ' + esc('[Título de la cláusula]') + '</strong> ' + esc('[Redacta aquí el contenido de la cláusula.]') + '</p>');
    });
    document.getElementById('tplInsField').addEventListener('change', function(){ if(this.value){ insertAtCaret(fieldChip(this.value) + ' '); this.value = ''; } });
    document.getElementById('tplInsSig').addEventListener('change', function(){ if(this.value){ insertAtCaret(anchorChip(this.value) + ' '); this.value = ''; } });

    // ── Cargar andamiaje del formato ────────────────────────────────────
    document.getElementById('tplLoadScaffold').addEventListener('click', function(){
        var s = archSel && starters[archSel.value]; if(s === undefined){ return; }
        var hasContent = htmlMode ? htmlArea.value.trim() : canvas.textContent.trim();
        if(hasContent && !window.confirm(@json(__('Esto reemplazará el contenido del contrato con el andamiaje de este formato. ¿Continuar?')))){ return; }
        if(htmlMode){ htmlArea.value = s; } else { hydrate(s); }
        schedulePreview();
    });

    // ── Ver / editar HTML (escotilla) ───────────────────────────────────
    document.getElementById('tplToggleHtml').addEventListener('click', function(){
        if(!htmlMode){ htmlArea.value = serialize(); htmlArea.classList.remove('d-none'); canvas.classList.add('d-none'); htmlMode = true; }
        else { hydrate(htmlArea.value); htmlArea.classList.add('d-none'); canvas.classList.remove('d-none'); htmlMode = false; }
    });

    // ── Cambios -> preview ──────────────────────────────────────────────
    canvas.addEventListener('input', schedulePreview);
    htmlArea.addEventListener('input', schedulePreview);
    if(archSel){ archSel.addEventListener('change', function(){ updateArchDesc(); schedulePreview(); }); }
    document.getElementById('tplRefresh').addEventListener('click', refresh);

    // ── Al enviar: vuelca el cuerpo serializado al input oculto ─────────
    document.getElementById('tplForm').addEventListener('submit', function(){ bodyIn.value = currentBody(); });

    // ── Init ────────────────────────────────────────────────────────────
    hydrate(htmlArea.value);
    updateArchDesc();
    refresh();
})();
</script>
@endpush
