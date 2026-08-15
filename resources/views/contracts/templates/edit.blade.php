@extends('layouts.app')
@section('content')
{{-- CONTRACT BUILDER · editor MODO DOCUMENTO: la superficie de edición es una HOJA (tamaño real,
     márgenes, sombra) sobre un escritorio; CHIPS de datos/firmas (sin llaves), barra de formato,
     "Añadir cláusula" numerada y "Salto de página" (corte real en el PDF). "Vista con datos" es un
     toggle, no un split permanente. El body se serializa a HTML con {{tokens}} al enviar. --}}
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
                <p class="text-muted mb-0 small">{{ __('Escribe el contrato como un documento. Inserta datos y firmas desde la barra; controla los saltos de página.') }}</p>
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

            {{-- ── Ajustes de la plantilla ── --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">{{ __('Nombre') }}</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold d-block">{{ __('Aplica a') }}</label>
                            @foreach($subtypes as $val => $label)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="applies_to[]" value="{{ $val }}"
                                           id="st_{{ $val }}" @checked(in_array($val, old('applies_to', $template->applies_to ?? []), true))>
                                    <label class="form-check-label" for="st_{{ $val }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold d-block">{{ __('Formato del contrato') }}</label>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <select name="architecture" id="tplArch" class="form-select form-select-sm" style="max-width:340px">
                                    @foreach($architectures as $key => $a)
                                        <option value="{{ $key }}" @selected(old('architecture', $template->architecture ?: 'caratula_numbered') === $key)>{{ $a['label'] }}</option>
                                    @endforeach
                                </select>
                                <button type="button" id="tplLoadScaffold" class="btn btn-sm btn-crew-soft">{{ __('Cargar andamiaje') }}</button>
                            </div>
                            <div class="form-text" id="tplArchDesc"></div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold d-block">{{ __('Tamaño de página') }}</label>
                            <select name="page_size" id="tplPageSize" class="form-select form-select-sm">
                                @foreach($pageSizes as $k => $p)
                                    <option value="{{ $k }}" @selected(old('page_size', $template->page_size ?: 'carta') === $k)>{{ $p['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="initials_each_page" value="1" id="tplInitials"
                                       @checked(old('initials_each_page', $template->initials_each_page))>
                                <label class="form-check-label small" for="tplInitials">{{ __('Rúbrica del contratado en cada página') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── El documento ── --}}
            <div class="cc-toolbar" role="toolbar" aria-label="{{ __('Formato del texto') }}">
                <button type="button" class="cc-tb" data-cmd="formatBlock" data-arg="h2" title="{{ __('Título de sección') }}"><strong>H</strong></button>
                <button type="button" class="cc-tb" data-cmd="formatBlock" data-arg="p" title="{{ __('Texto normal') }}">¶</button>
                <span class="cc-tb-sep"></span>
                <button type="button" class="cc-tb" data-cmd="bold" title="{{ __('Negrita') }}"><strong>B</strong></button>
                <button type="button" class="cc-tb" data-cmd="italic" title="{{ __('Cursiva') }}"><em>I</em></button>
                <button type="button" class="cc-tb" data-cmd="insertUnorderedList" title="{{ __('Lista') }}">&bull;</button>
                <span class="cc-tb-sep"></span>
                <button type="button" class="cc-tb cc-tb--wide" id="tplAddClause">+ {{ __('Cláusula') }}</button>
                <button type="button" class="cc-tb cc-tb--wide" id="tplPageBreak">⤶ {{ __('Salto de página') }}</button>
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

            <div class="cc-desk">
                <div id="tplCanvas" class="cc-page" contenteditable="true" spellcheck="true"></div>
            </div>

            {{-- Escotilla HTML (oculta por defecto; para el owner) --}}
            <textarea id="tplHtml" class="form-control cc-html d-none mt-2" rows="16"
                      style="font-family:ui-monospace,Consolas,monospace;font-size:.84rem;">{{ old('body', $template->body) }}</textarea>

            <div class="form-text mt-2">{{ __('Inserta datos y firmas desde la barra: se ven como etiquetas y se rellenan al emitir el contrato.') }}</div>

            <div class="d-flex align-items-center flex-wrap gap-3 mt-3">
                <button class="btn btn-crew">{{ __('Guardar') }}</button>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           @checked(old('is_active', $template->is_active))>
                    <label class="form-check-label" for="is_active">{{ __('Activa') }}</label>
                </div>
                <button type="button" id="tplTogglePreview" class="btn btn-sm btn-crew-soft ms-auto">{{ __('Vista con datos') }}</button>
            </div>

            {{-- ── Vista previa con datos de ejemplo (toggle) ── --}}
            <div id="tplPreviewWrap" class="card mt-3 d-none">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold">{{ __('Vista previa') }} <span class="text-muted small">({{ __('datos de ejemplo') }})</span></span>
                    <button type="button" id="tplRefresh" class="btn btn-sm btn-crew-soft">{{ __('Actualizar') }}</button>
                </div>
                <div class="card-body">
                    <iframe id="tplPreview" title="{{ __('Vista previa') }}" sandbox=""
                            style="width:100%;height:600px;border:1px solid var(--border, #d7dce4);border-radius:10px;background:#fff;"></iframe>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
.cc-toolbar{position:sticky;top:0;z-index:3;display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:6px;border:1px solid var(--border,#d7dce4);border-bottom:0;border-radius:10px 10px 0 0;background:var(--surface-2,#f6f7f9)}
.cc-tb{min-width:32px;height:32px;padding:0 9px;border:1px solid var(--border,#d7dce4);border-radius:7px;background:var(--surface,#fff);color:var(--text,#1a1a1a);cursor:pointer;font-size:.9rem;line-height:1;transition:transform .12s ease, background .12s ease}
.cc-tb:hover{background:var(--surface-2,#eef1f5)}
.cc-tb:active{transform:scale(.96)}
.cc-tb--wide{width:auto;font-weight:600;color:var(--brand,#ff0046)}
.cc-tb-sep{width:1px;height:22px;background:var(--border,#d7dce4);margin:0 2px}
.cc-tb-select{max-width:164px;height:32px}
.cc-desk{padding:22px;border:1px solid var(--border,#d7dce4);border-radius:0 0 10px 10px;background:var(--surface-2,#e9edf2);max-height:74vh;overflow:auto}
.cc-page{--pg-w:216mm;--pg-h:279mm;--pg-m:25mm;width:var(--pg-w);min-height:var(--pg-h);padding:var(--pg-m);margin:0 auto;background:#fff;color:#1a1a1a;box-shadow:0 3px 16px rgba(0,0,0,.20);font-family:Georgia,"Times New Roman",serif;line-height:1.6;font-size:12pt}
.cc-page:focus{outline:none}
.cc-page h1{font-size:1.4rem;text-align:center}
.cc-page h2{font-size:1.05rem;border-bottom:1px solid #dddddd;padding-bottom:3px;margin-top:1.1rem}
.cc-page table{width:100%;border-collapse:collapse}
.cc-page td{padding:5px 7px;vertical-align:top}
.cc-page .cc-pb{height:0;margin:22px 0;border:0;border-top:2px dashed var(--brand,#ff0046);position:relative}
.cc-page .cc-pb::before{content:"⤶ Salto de página";position:absolute;left:50%;top:-9px;transform:translateX(-50%);background:#fff;color:var(--brand,#ff0046);font:600 .66rem system-ui,-apple-system,sans-serif;padding:0 8px;letter-spacing:.02em;white-space:nowrap}
.cc-tok{display:inline-block;padding:1px 8px;margin:0 1px;border-radius:999px;background:color-mix(in srgb, var(--brand,#ff0046) 12%, #fff);border:1px solid color-mix(in srgb, var(--brand,#ff0046) 35%, transparent);color:#10151f;font-family:system-ui,-apple-system,sans-serif;font-size:.78rem;white-space:nowrap;user-select:all;cursor:default}
.cc-tok--sig{background:color-mix(in srgb, #2563eb 14%, #fff);border-color:color-mix(in srgb, #2563eb 38%, transparent)}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var FIELDS   = @json($fields);
    var ANCHORS  = @json($anchors);
    var archMeta = @json($architectures);
    var starters = @json($starters);
    var PAGES    = @json($pageSizes);

    var canvas   = document.getElementById('tplCanvas');   // .cc-page (la hoja)
    var htmlArea = document.getElementById('tplHtml');
    var bodyIn   = document.getElementById('tplBodyInput');
    var archSel  = document.getElementById('tplArch');
    var pageSel  = document.getElementById('tplPageSize');
    var initialsChk = document.getElementById('tplInitials');
    var LB = '{' + '{', RB = '}' + '}';   // evita que Blade parsee llaves literales en este script

    // Rúbrica de muestra (SVG cursivo) para la vista paginada.
    function rubricaSvg(){
        return '<div class="sheet-rubrica"><svg xmlns="http://www.w3.org/2000/svg" width="140" height="40">'
            + '<text x="6" y="27" font-family="Segoe Script,Brush Script MT,cursive" font-size="22" font-style="italic" fill="#0f1115">M. G. Ríos</text>'
            + '</svg><span>Rúbrica</span></div>';
    }

    function esc(s){ return String(s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

    // ── Chips ────────────────────────────────────────────────────────────
    function fieldChip(k){ return '<span class="cc-tok" contenteditable="false" data-field="'+esc(k)+'">'+esc(FIELDS[k]||k)+'</span>'; }
    function anchorChip(k){ return '<span class="cc-tok cc-tok--sig" contenteditable="false" data-anchor="'+esc(k)+'">✍ '+esc(ANCHORS[k]||k)+'</span>'; }

    // ── Hydrate: body con tokens -> hoja con chips ───────────────────────
    function hydrate(html){
        var out = String(html || '').replace(/\[\[firma:([a-z0-9_:\-]+)\]\]/gi, function(_, k){ return anchorChip(k.toLowerCase()); });
        out = out.replace(/\{\{\s*([a-z0-9_.]+)\s*\}\}/gi, function(_, k){ return fieldChip(k.toLowerCase()); });
        canvas.innerHTML = out;
        canvas.querySelectorAll('.cc-pb').forEach(function(el){ el.setAttribute('contenteditable', 'false'); });
    }

    // ── Serialize: hoja -> HTML limpio con tokens ────────────────────────
    var ALLOWED = {H1:'h1',H2:'h2',H3:'h3',P:'p',STRONG:'strong',EM:'em',U:'u',BR:'br',UL:'ul',OL:'ol',LI:'li',TABLE:'table',THEAD:'thead',TBODY:'tbody',TR:'tr',TD:'td',TH:'th',DIV:'p',B:'strong',I:'em'};
    var KEEP = {style:1,border:1,colspan:1,rowspan:1,align:1,class:1,cellpadding:1,cellspacing:1,width:1};
    function ser(node){
        if(node.nodeType === 3){ return esc(node.nodeValue); }
        if(node.nodeType !== 1){ return ''; }
        if(node.hasAttribute && node.hasAttribute('data-field')){ return LB + node.getAttribute('data-field') + RB; }
        if(node.hasAttribute && node.hasAttribute('data-anchor')){ return '[[firma:' + node.getAttribute('data-anchor') + ']]'; }
        var tag = ALLOWED[node.tagName];
        var inner = ''; node.childNodes.forEach(function(c){ inner += ser(c); });
        if(!tag){ return inner; }
        if(tag === 'br'){ return '<br>'; }
        var at = ''; Array.prototype.forEach.call(node.attributes || [], function(a){ if(KEEP[a.name.toLowerCase()]){ at += ' ' + a.name + '="' + esc(a.value) + '"'; } });
        return '<' + tag + at + '>' + inner + '</' + tag + '>';
    }
    function serialize(){ var s = ''; canvas.childNodes.forEach(function(c){ s += ser(c); }); return s; }

    // ── Insertar HTML en el cursor ───────────────────────────────────────
    function insertAtCaret(html){
        canvas.focus();
        var sel = window.getSelection();
        if(!sel.rangeCount || !canvas.contains(sel.anchorNode)){
            canvas.insertAdjacentHTML('beforeend', html);
        } else {
            document.execCommand('insertHTML', false, html);
        }
        canvas.querySelectorAll('.cc-pb').forEach(function(el){ el.setAttribute('contenteditable', 'false'); });
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

    // ── Tamaño de página: aplica a la hoja ───────────────────────────────
    function applyPageSize(){
        var d = pageSel && PAGES[pageSel.value]; if(!d){ return; }
        canvas.style.setProperty('--pg-w', d.w + 'mm');
        canvas.style.setProperty('--pg-h', d.h + 'mm');
        canvas.style.setProperty('--pg-m', d.margin + 'mm');
    }

    // ── Preview PAGINADA (toggle "Vista con datos") — inc.3b ─────────────
    var frame = document.getElementById('tplPreview');
    var wrap  = document.getElementById('tplPreviewWrap');
    var meta  = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.content : '';
    var url   = @json(route('contracts.templates.preview'));
    var htmlMode = false, previewOn = false, pvTimer = null;
    var PXMM = 96 / 25.4;   // px por mm (referencia CSS)
    function currentBody(){ return htmlMode ? htmlArea.value : serialize(); }

    // Reparte bloques en páginas (PURO, testeable): items=[{h,br}] + alto de página -> [[idx...],...].
    function splitPages(items, contentH){
        var pages = [[]], h = 0;
        for(var i = 0; i < items.length; i++){
            if(items[i].br){ if(pages[pages.length-1].length){ pages.push([]); } h = 0; continue; }
            if(h > 0 && h + items[i].h > contentH){ pages.push([]); h = 0; }
            pages[pages.length-1].push(i); h += items[i].h;
        }
        if(pages.length > 1 && pages[pages.length-1].length === 0){ pages.pop(); }
        return pages;
    }

    function sheetCss(d){
        return 'body{background:#e9edf2;margin:0;padding:16px;font-family:Georgia,"Times New Roman",serif}'
            + '.sheet{width:' + d.w + 'mm;min-height:' + d.h + 'mm;box-sizing:border-box;padding:' + d.margin + 'mm;margin:0 auto 16px;background:#fff;color:#1a1a1a;box-shadow:0 2px 12px rgba(0,0,0,.22);position:relative;font-size:12pt;line-height:1.6}'
            + '.sheet-foot{position:absolute;bottom:' + (d.margin/2) + 'mm;right:' + d.margin + 'mm;font-size:9pt;color:#8a93a2}'
            + '.sheet-rubrica{position:absolute;bottom:' + (d.margin/2) + 'mm;left:' + d.margin + 'mm;text-align:left}'
            + '.sheet-rubrica svg{height:26px;display:block}.sheet-rubrica span{font-size:7pt;color:#888}'
            + 'h1{font-size:1.3rem;text-align:center}h2{font-size:1.02rem;border-bottom:1px solid #ddd;padding-bottom:3px;margin-top:1.1rem}'
            + 'table{width:100%;border-collapse:collapse}td{padding:5px 7px;vertical-align:top}';
    }

    // Mide el contenido en un iframe AISLADO (sin CSS de la app) y arma las hojas reales.
    function paginate(inner){
        var d = (pageSel && PAGES[pageSel.value]) || { w: 216, h: 279, margin: 25 };
        var contentW = (d.w - 2 * d.margin) * PXMM;
        var contentH = (d.h - 2 * d.margin) * PXMM;
        var ifr = document.createElement('iframe');
        ifr.style.cssText = 'position:absolute;left:-99999px;top:0;width:' + contentW + 'px;height:10px;border:0;visibility:hidden';
        document.body.appendChild(ifr);
        var doc = ifr.contentDocument;
        doc.open();
        doc.write('<!doctype html><meta charset="utf-8"><style>body{margin:0;width:' + contentW + 'px;font-family:Georgia,"Times New Roman",serif;font-size:12pt;line-height:1.6}h1{font-size:1.3rem;text-align:center}h2{font-size:1.02rem;border-bottom:1px solid #ddd;padding-bottom:3px;margin-top:1.1rem}table{width:100%;border-collapse:collapse}td{padding:5px 7px}</style>' + inner);
        doc.close();
        var kids = Array.prototype.slice.call(doc.body.children);
        var items = kids.map(function(el){
            var cs = ifr.contentWindow.getComputedStyle(el);
            var mh = parseFloat(cs.marginTop || 0) + parseFloat(cs.marginBottom || 0);
            return { h: el.offsetHeight + mh, br: el.classList.contains('cc-pb'), html: el.outerHTML };
        });
        document.body.removeChild(ifr);
        var pages = splitPages(items, contentH);
        var total = pages.length;
        var rub = (initialsChk && initialsChk.checked) ? rubricaSvg() : '';
        var sheets = pages.map(function(idxs, i){
            var b = idxs.map(function(j){ return items[j].html; }).join('');
            return '<div class="sheet">' + b + rub + '<div class="sheet-foot">Página ' + (i + 1) + ' de ' + total + '</div></div>';
        }).join('');
        return '<!doctype html><meta charset="utf-8"><style>' + sheetCss(d) + '</style>' + sheets;
    }

    function refresh(){
        if(!previewOn){ return; }
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': token },
            body: 'body=' + encodeURIComponent(currentBody())
                + '&architecture=' + encodeURIComponent(archSel ? archSel.value : '')
                + '&page_size=' + encodeURIComponent(pageSel ? pageSel.value : '')
                + '&fragment=1'
        }).then(function(r){ return r.text(); }).then(function(inner){ frame.srcdoc = paginate(inner); });
    }
    function schedulePreview(){ if(!previewOn){ return; } clearTimeout(pvTimer); pvTimer = setTimeout(refresh, 450); }

    function updateArchDesc(){ var m = archSel && archMeta[archSel.value]; var el = document.getElementById('tplArchDesc'); if(el){ el.textContent = m ? m.desc : ''; } }

    // ── Barra de formato ─────────────────────────────────────────────────
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
    document.getElementById('tplPageBreak').addEventListener('click', function(){ insertAtCaret('<p class="cc-pb" contenteditable="false"></p><p><br></p>'); });
    document.getElementById('tplInsField').addEventListener('change', function(){ if(this.value){ insertAtCaret(fieldChip(this.value) + ' '); this.value = ''; } });
    document.getElementById('tplInsSig').addEventListener('change', function(){ if(this.value){ insertAtCaret(anchorChip(this.value) + ' '); this.value = ''; } });

    // ── Cargar andamiaje ─────────────────────────────────────────────────
    document.getElementById('tplLoadScaffold').addEventListener('click', function(){
        var s = archSel && starters[archSel.value]; if(s === undefined){ return; }
        var hasContent = htmlMode ? htmlArea.value.trim() : canvas.textContent.trim();
        if(hasContent && !window.confirm(@json(__('Esto reemplazará el contenido del contrato con el andamiaje de este formato. ¿Continuar?')))){ return; }
        if(htmlMode){ htmlArea.value = s; } else { hydrate(s); }
        schedulePreview();
    });

    // ── Ver / editar HTML ────────────────────────────────────────────────
    document.getElementById('tplToggleHtml').addEventListener('click', function(){
        if(!htmlMode){ htmlArea.value = serialize(); htmlArea.classList.remove('d-none'); canvas.parentElement.classList.add('d-none'); htmlMode = true; }
        else { hydrate(htmlArea.value); htmlArea.classList.add('d-none'); canvas.parentElement.classList.remove('d-none'); htmlMode = false; }
    });

    // ── Toggle "Vista con datos" ─────────────────────────────────────────
    document.getElementById('tplTogglePreview').addEventListener('click', function(){
        previewOn = ! previewOn;
        wrap.classList.toggle('d-none', ! previewOn);
        this.classList.toggle('btn-crew', previewOn);
        this.classList.toggle('btn-crew-soft', ! previewOn);
        if(previewOn){ refresh(); }
    });

    // ── Cambios -> preview ───────────────────────────────────────────────
    canvas.addEventListener('input', schedulePreview);
    htmlArea.addEventListener('input', schedulePreview);
    if(archSel){ archSel.addEventListener('change', function(){ updateArchDesc(); schedulePreview(); }); }
    if(pageSel){ pageSel.addEventListener('change', function(){ applyPageSize(); schedulePreview(); }); }
    if(initialsChk){ initialsChk.addEventListener('change', schedulePreview); }
    document.getElementById('tplRefresh').addEventListener('click', function(){ previewOn = true; refresh(); });

    // ── Al enviar: vuelca el cuerpo serializado ──────────────────────────
    document.getElementById('tplForm').addEventListener('submit', function(){ bodyIn.value = currentBody(); });

    // ── Init ─────────────────────────────────────────────────────────────
    applyPageSize();
    hydrate(htmlArea.value);
    updateArchDesc();
})();
</script>
@endpush
