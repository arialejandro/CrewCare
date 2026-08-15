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

            <div class="cc-editor-grid">

                {{-- ── IZQUIERDA · Insertar (paleta de mosaicos) ── --}}
                {{-- La clase cc-toolbar se conserva como ancla del test de la vista; el aspecto lo da .cc-panel. --}}
                <div class="cc-panel cc-toolbar">
                    <div class="cc-panel-h"><h3>{{ __('Insertar') }}</h3></div>
                    <div class="cc-panel-b">
                        <div class="cc-palette">
                            <button type="button" class="cc-tile is-struct" id="tplInsTitle">
                                <span class="cc-tile-ic"><svg viewBox="0 0 24 24"><path d="M4 7V5h16v2M9 5v14M7 19h4"/></svg></span><span>{{ __('Título') }}</span>
                            </button>
                            <button type="button" class="cc-tile is-struct" id="tplInsText">
                                <span class="cc-tile-ic"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h11"/></svg></span><span>{{ __('Texto') }}</span>
                            </button>
                            <div class="cc-ins">
                                <button type="button" class="cc-tile is-data" id="tplInsFieldBtn" aria-haspopup="true" aria-expanded="false">
                                    <span class="cc-tile-ic"><svg viewBox="0 0 24 24"><path d="M8 4H7a2 2 0 0 0-2 2v3a2 2 0 0 1-2 2 2 2 0 0 1 2 2v3a2 2 0 0 0 2 2h1M16 4h1a2 2 0 0 1 2 2v3a2 2 0 0 0 2 2 2 2 0 0 0-2 2v3a2 2 0 0 1-2 2h-1"/></svg></span><span>{{ __('Dato') }}</span>
                                </button>
                                <div class="cc-pop" id="tplFieldPop" role="menu" hidden>
                                    @foreach($fields as $key => $label)
                                        <button type="button" class="cc-pop-item" data-field="{{ $key }}" role="menuitem">{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="cc-ins">
                                <button type="button" class="cc-tile is-sig" id="tplInsSigBtn" aria-haspopup="true" aria-expanded="false">
                                    <span class="cc-tile-ic"><svg viewBox="0 0 24 24"><path d="M3 19s3-1 6-1 6 2 9 1M4 15c3-8 6-9 7-5s2 6 4 3"/></svg></span><span>{{ __('Firma') }}</span>
                                </button>
                                <div class="cc-pop" id="tplSigPop" role="menu" hidden>
                                    @foreach($anchors as $key => $label)
                                        <button type="button" class="cc-pop-item cc-pop-item--sig" data-anchor="{{ $key }}" role="menuitem">{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <button type="button" class="cc-tile is-sig" id="tplInsRubrica">
                                <span class="cc-tile-ic"><svg viewBox="0 0 24 24"><path d="M5 18c4 0 3-9 6-9s2 5 4 5"/><path d="M4 21h16"/></svg></span><span>{{ __('Rúbrica') }}</span>
                            </button>
                            <button type="button" class="cc-tile is-struct" id="tplAddClause">
                                <span class="cc-tile-ic"><svg viewBox="0 0 24 24"><path d="M7 4h12M7 9h12M7 15h12M7 20h8M3 4h.01M3 9h.01M3 15h.01M3 20h.01"/></svg></span><span>{{ __('Cláusula') }}</span>
                            </button>
                            <button type="button" class="cc-tile is-struct" id="tplInsList">
                                <span class="cc-tile-ic"><svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></span><span>{{ __('Lista') }}</span>
                            </button>
                            <button type="button" class="cc-tile is-struct" id="tplInsTable">
                                <span class="cc-tile-ic"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="1"/><path d="M3 10h18M9 4v16"/></svg></span><span>{{ __('Tabla') }}</span>
                            </button>
                            <button type="button" class="cc-tile is-struct" id="tplPageBreak">
                                <span class="cc-tile-ic"><svg viewBox="0 0 24 24"><path d="M4 9h16M4 15h5m4 0h7"/><path d="M6 5l-2 4 2 4"/></svg></span><span>{{ __('Salto') }}</span>
                            </button>
                            <button type="button" class="cc-tile is-struct" disabled title="{{ __('Próximamente') }}">
                                <span class="cc-tile-ic"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></span><span>{{ __('Imagen') }}</span>
                            </button>
                        </div>
                        <p class="cc-palette-note">{{ __('Un clic inserta el bloque donde está el cursor.') }}</p>
                    </div>
                </div>

                {{-- ── CENTRO · La hoja ── --}}
                <div class="cc-center">
                    <div class="cc-desk" id="tplDesk">
                        <div class="cc-page-wrap">
                            <div id="tplCanvas" class="cc-page" contenteditable="true" spellcheck="true"></div>
                            <div id="tplGuides" class="cc-guides" aria-hidden="true"></div>
                        </div>
                    </div>

                    {{-- Escotilla HTML (oculta por defecto; para el owner) --}}
                    <textarea id="tplHtml" class="form-control cc-html d-none mt-2" rows="16"
                              style="font-family:ui-monospace,Consolas,monospace;font-size:.84rem;">{{ old('body', $template->body) }}</textarea>

                    <div class="d-flex align-items-center flex-wrap gap-3 mt-3">
                        <button class="btn btn-crew">{{ __('Guardar') }}</button>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                   @checked(old('is_active', $template->is_active))>
                            <label class="form-check-label" for="is_active">{{ __('Activa') }}</label>
                        </div>
                        <button type="button" id="tplTogglePreview" class="btn btn-sm btn-crew-soft ms-auto">{{ __('Vista con datos') }}</button>
                        <button type="button" id="tplToggleHtml" class="btn btn-sm btn-crew-soft" title="{{ __('Ver / editar HTML') }}">{{ __('Ver HTML') }}</button>
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
                </div>{{-- /cc-center --}}

                {{-- ── DERECHA · Ajustes del documento ── --}}
                <div class="cc-panel">
                    <div class="cc-panel-h"><h3>{{ __('Ajustes del documento') }}</h3></div>
                    <div class="cc-panel-b">
                        <div class="cc-field">
                            <label class="form-label fw-semibold">{{ __('Nombre') }}</label>
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $template->name) }}" required>
                        </div>
                        <div class="cc-field">
                            <label class="form-label fw-semibold d-block">{{ __('Aplica a') }}</label>
                            @foreach($subtypes as $val => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="applies_to[]" value="{{ $val }}"
                                           id="st_{{ $val }}" @checked(in_array($val, old('applies_to', $template->applies_to ?? []), true))>
                                    <label class="form-check-label" for="st_{{ $val }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="cc-field">
                            <label class="form-label fw-semibold d-block">{{ __('Formato del contrato') }}</label>
                            <select name="architecture" id="tplArch" class="form-select form-select-sm mb-2">
                                @foreach($architectures as $key => $a)
                                    <option value="{{ $key }}" @selected(old('architecture', $template->architecture ?: 'caratula_numbered') === $key)>{{ $a['label'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" id="tplLoadScaffold" class="btn btn-sm btn-crew-soft w-100">{{ __('Cargar andamiaje') }}</button>
                        </div>
                        <div class="cc-field">
                            <label class="form-label fw-semibold d-block">{{ __('Tamaño de página') }}</label>
                            <select name="page_size" id="tplPageSize" class="form-select form-select-sm">
                                @foreach($pageSizes as $k => $p)
                                    <option value="{{ $k }}" @selected(old('page_size', $template->page_size ?: 'carta') === $k)>{{ $p['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="cc-field">
                            <label class="form-label fw-semibold d-block">{{ __('Tipografía') }}</label>
                            <input type="hidden" name="font_family" id="tplFontInput" value="{{ old('font_family', $template->font_family ?: 'mono') }}">
                            <div class="cc-seg mb-2" role="group" id="tplFont" aria-label="{{ __('Familia de letra') }}">
                                @foreach($fonts as $k => $f)
                                    <button type="button" class="cc-seg-btn" data-font="{{ $k }}" data-stack="{{ $f['stack'] }}"
                                            style="font-family:{{ $f['stack'] }}">{{ $f['label'] }}</button>
                                @endforeach
                            </div>
                            <input type="hidden" name="font_size" id="tplSizeInput" value="{{ old('font_size', $template->font_size ?: '11') }}">
                            <div class="cc-seg" role="group" id="tplSize" aria-label="{{ __('Tamaño de letra') }}">
                                @foreach($fontSizes as $k => $label)
                                    <button type="button" class="cc-seg-btn" data-size="{{ $k }}">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="cc-field">
                            <label class="form-label fw-semibold d-block">{{ __('Alineación') }}</label>
                            <div class="cc-align" role="group" aria-label="{{ __('Alineación') }}">
                                <button type="button" class="cc-align-btn" data-align="justifyLeft" title="{{ __('Izquierda') }}"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h13"/></svg></button>
                                <button type="button" class="cc-align-btn" data-align="justifyCenter" title="{{ __('Centrar') }}"><svg viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M6 18h12"/></svg></button>
                                <button type="button" class="cc-align-btn" data-align="justifyRight" title="{{ __('Derecha') }}"><svg viewBox="0 0 24 24"><path d="M4 6h16M10 12h10M7 18h13"/></svg></button>
                                <button type="button" class="cc-align-btn" data-align="justifyFull" title="{{ __('Justificar') }}"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- /cc-editor-grid --}}
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
/* ── Rejilla del editor: INSERTAR · hoja · AJUSTES ── */
.cc-editor-grid{display:grid;grid-template-columns:230px minmax(0,1fr) 268px;gap:16px;align-items:start}
@media (max-width:980px){.cc-editor-grid{grid-template-columns:1fr}}
.cc-panel{background:var(--surface,#fff);border:1px solid var(--border,#d7dce4);border-radius:14px;box-shadow:0 1px 2px rgba(16,20,30,.04),0 8px 24px rgba(16,20,30,.06)}
.cc-panel-h{padding:14px 16px 10px}
.cc-panel-h h3{margin:0;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted,#6b7482)}
.cc-panel-b{padding:6px 14px 16px}
/* .cc-toolbar: clase-ancla que conserva el test de la vista; su aspecto lo da .cc-panel */

/* Paleta de mosaicos (INSERTAR) */
.cc-palette{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.cc-tile{display:flex;flex-direction:column;align-items:center;gap:7px;width:100%;padding:14px 8px;border-radius:12px;border:1px solid var(--border,#d7dce4);background:var(--surface-2,#f6f7f9);color:var(--text,#1a1a1a);font:inherit;cursor:pointer;transition:transform .16s ease,background .16s ease,border-color .16s ease,box-shadow .16s ease}
.cc-tile:hover{background:var(--surface,#fff);border-color:var(--brand,#ff0046);box-shadow:0 8px 22px rgba(16,20,30,.10);transform:translateY(-2px)}
.cc-tile:active{transform:translateY(0) scale(.97)}
.cc-tile:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
.cc-tile span{font-size:.75rem;font-weight:600}
.cc-tile-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:color-mix(in srgb,var(--brand,#ff0046) 12%,var(--surface,#fff));color:var(--brand,#ff0046)}
.cc-tile-ic svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}
.cc-tile.is-data .cc-tile-ic{background:color-mix(in srgb,#7c3aed 14%,var(--surface,#fff));color:#7c3aed}
.cc-tile.is-sig .cc-tile-ic{background:color-mix(in srgb,#2563eb 14%,var(--surface,#fff));color:#2563eb}
.cc-tile.is-struct .cc-tile-ic{background:var(--surface-3,#eceef3);color:var(--muted,#6b7482)}
.cc-palette-note{margin:12px 2px 0;font-size:.72rem;color:var(--muted,#6b7482);line-height:1.5}

/* Campos y alineación (AJUSTES) */
.cc-field{margin:0 0 14px}
.cc-field > .form-label{font-size:.7rem;letter-spacing:.05em;text-transform:uppercase;color:var(--muted,#6b7482);margin-bottom:6px}
.cc-align{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}
.cc-align-btn{display:grid;place-items:center;height:36px;border:1px solid var(--border,#d7dce4);border-radius:9px;background:var(--surface-2,#f6f7f9);color:var(--text,#1a1a1a);cursor:pointer;transition:background .16s ease,border-color .16s ease,transform .12s ease}
.cc-align-btn:hover{background:var(--surface,#fff);border-color:var(--brand,#ff0046)}
.cc-align-btn:active{transform:scale(.95)}
.cc-align-btn svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}
.cc-desk{padding:22px;border:1px solid var(--border,#d7dce4);border-radius:12px;background:var(--surface-2,#e9edf2);max-height:74vh;overflow:auto}
.cc-page-wrap{position:relative;width:-moz-fit-content;width:fit-content;margin:0 auto}
.cc-page{--pg-w:216mm;--pg-h:279mm;--pg-m:25mm;position:relative;width:var(--pg-w);min-height:var(--pg-h);padding:var(--pg-m);margin:0;background:#fff;color:#1a1a1a;box-shadow:0 3px 16px rgba(0,0,0,.20);font-family:Georgia,"Times New Roman",serif;line-height:1.15;font-size:12pt}
.cc-page:focus{outline:none}
.cc-guides{position:absolute;inset:0;pointer-events:none;overflow:hidden;z-index:2}
.cc-guide{position:absolute;left:0;right:0;border-top:2px dashed color-mix(in srgb, var(--brand,#ff0046) 45%, transparent)}
.cc-guide span{position:absolute;right:8px;top:-9px;background:var(--surface-2,#e9edf2);color:var(--brand,#ff0046);font:600 .62rem system-ui,-apple-system,sans-serif;padding:0 6px;letter-spacing:.02em}
.cc-page h1{font-size:1.3rem;text-align:center}
.cc-page h2{font-size:1.02rem;border-bottom:1px solid #dddddd;padding-bottom:3px;margin-top:1.1rem}
.cc-page table{width:100%;border-collapse:collapse}
.cc-page td{padding:5px 7px;vertical-align:top}
.cc-page .cc-pb{height:0;margin:22px 0;border:0;border-top:2px dashed var(--brand,#ff0046);position:relative}
.cc-page .cc-pb::before{content:"⤶ Salto de página";position:absolute;left:50%;top:-9px;transform:translateX(-50%);background:#fff;color:var(--brand,#ff0046);font:600 .66rem system-ui,-apple-system,sans-serif;padding:0 8px;letter-spacing:.02em;white-space:nowrap}
.cc-tok{display:inline-block;padding:1px 8px;margin:0 1px;border-radius:999px;background:color-mix(in srgb, var(--brand,#ff0046) 12%, #fff);border:1px solid color-mix(in srgb, var(--brand,#ff0046) 35%, transparent);color:#10151f;font-family:system-ui,-apple-system,sans-serif;font-size:.78rem;white-space:nowrap;user-select:all;cursor:default}
.cc-tok--sig{background:color-mix(in srgb, #2563eb 14%, #fff);border-color:color-mix(in srgb, #2563eb 38%, transparent)}
.cc-tok--rub{cursor:move;touch-action:none}
.cc-tok--rub:hover{box-shadow:0 0 0 2px color-mix(in srgb, #2563eb 40%, transparent)}
.cc-seg{display:flex;gap:2px;background:var(--surface-2,#f6f7f9);border:1px solid var(--border,#d7dce4);border-radius:9px;padding:3px}
.cc-seg-btn{flex:1;min-width:0;border:none;background:none;color:var(--muted,#6b7482);font-size:.8rem;font-weight:600;padding:5px 4px;border-radius:6px;cursor:pointer;transition:background .16s ease,color .16s ease,box-shadow .16s ease,transform .12s ease;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cc-seg-btn[aria-pressed="true"]{background:var(--surface,#fff);color:var(--text,#1a1a1a);box-shadow:0 1px 3px rgba(0,0,0,.14)}
.cc-seg-btn:active{transform:scale(.97)}
.cc-ins{position:relative;display:block}
.cc-pop{position:absolute;top:calc(100% + 6px);left:0;z-index:30;min-width:300px;max-height:340px;overflow:auto;background:var(--surface,#fff);border:1px solid var(--border,#d7dce4);border-radius:12px;box-shadow:0 10px 34px rgba(0,0,0,.20);padding:8px;display:grid;grid-template-columns:1fr 1fr;gap:6px}
.cc-pop[hidden]{display:none}
.cc-pop-item{text-align:left;border:1px solid var(--border,#e2e5ec);background:var(--surface-2,#f6f7f9);color:var(--text,#1a1a1a);border-radius:9px;padding:9px 11px;font-size:.8rem;font-weight:600;cursor:pointer;transition:background .14s ease,border-color .14s ease,transform .1s ease}
.cc-pop-item:hover{background:var(--surface,#fff);border-color:color-mix(in srgb,var(--brand,#ff0046) 45%,transparent)}
.cc-pop-item:active{transform:scale(.97)}
.cc-pop-item--sig:hover{border-color:color-mix(in srgb,#2563eb 45%,transparent)}
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
    var fontInput = document.getElementById('tplFontInput');
    var sizeInput = document.getElementById('tplSizeInput');
    var initialsChk = document.getElementById('tplInitials');
    var guides = document.getElementById('tplGuides');
    // Familia y tamaño de la tipografía elegida (los mismos en hoja, medidor y PDF → salto al píxel).
    function fontStack(){
        var b = document.querySelector('#tplFont .cc-seg-btn[aria-pressed="true"]')
             || document.querySelector('#tplFont .cc-seg-btn[data-font="' + (fontInput ? fontInput.value : 'mono') + '"]');
        return b ? b.getAttribute('data-stack') : 'ui-monospace,Consolas,monospace';
    }
    function fontSizePt(){
        var b = document.querySelector('#tplSize .cc-seg-btn[aria-pressed="true"]')
             || document.querySelector('#tplSize .cc-seg-btn[data-size="' + (sizeInput ? sizeInput.value : '11') + '"]');
        return (b ? b.getAttribute('data-size') : '11') + 'pt';
    }
    function applyCanvasFont(){ if(canvas){ canvas.style.fontFamily = fontStack(); canvas.style.fontSize = fontSizePt(); } }
    var gTimer = null;
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
    // La rúbrica lleva desplazamiento libre (data-x/data-y en px) → se arrastra; el resto de firmas no.
    function anchorChip(k, dx, dy){
        var isRub = (k === 'rubrica');
        var x = isRub ? (parseInt(dx, 10) || 0) : 0, y = isRub ? (parseInt(dy, 10) || 0) : 0;
        var cls = 'cc-tok cc-tok--sig' + (isRub ? ' cc-tok--rub' : '');
        var da  = isRub ? ' data-x="' + x + '" data-y="' + y + '"' : '';
        var st  = (x || y) ? ' style="transform:translate(' + x + 'px,' + y + 'px)"' : '';
        var tip = isRub ? ' ✥' : '';   // manija: la rúbrica se arrastra libremente
        return '<span class="' + cls + '" contenteditable="false" data-anchor="' + esc(k) + '"' + da + st + '>✍ ' + esc(ANCHORS[k]||k) + tip + '</span>';
    }

    // ── Hydrate: body con tokens -> hoja con chips ───────────────────────
    function hydrate(html){
        var out = String(html || '').replace(/\[\[firma:([a-z0-9_:\-]+)(?:\|(-?\d+),(-?\d+))?\]\]/gi, function(_, k, dx, dy){ return anchorChip(k.toLowerCase(), dx, dy); });
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
        if(node.hasAttribute && node.hasAttribute('data-anchor')){
            var ak = node.getAttribute('data-anchor');
            if(ak === 'rubrica'){
                var rx = parseInt(node.getAttribute('data-x') || '0', 10) || 0, ry = parseInt(node.getAttribute('data-y') || '0', 10) || 0;
                return (rx || ry) ? '[[firma:rubrica|' + rx + ',' + ry + ']]' : '[[firma:rubrica]]';
            }
            return '[[firma:' + ak + ']]';
        }
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
        scheduleGuides();
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
        return 'body{background:#e9edf2;margin:0;padding:16px;font-family:' + fontStack() + '}'
            + '.sheet{width:' + d.w + 'mm;min-height:' + d.h + 'mm;box-sizing:border-box;padding:' + d.margin + 'mm;margin:0 auto 16px;background:#fff;color:#1a1a1a;box-shadow:0 2px 12px rgba(0,0,0,.22);position:relative;font-size:' + fontSizePt() + ';line-height:1.15}'
            + '.sheet-foot{position:absolute;bottom:' + (d.margin/2) + 'mm;right:' + d.margin + 'mm;font-size:9pt;color:#8a93a2}'
            + '.sheet-rubrica{position:absolute;bottom:' + (d.margin/2) + 'mm;left:' + d.margin + 'mm;text-align:left}'
            + '.sheet-rubrica svg{height:26px;display:block}.sheet-rubrica span{font-size:7pt;color:#888}'
            + 'h1{font-size:1.3rem;text-align:center}h2{font-size:1.02rem;border-bottom:1px solid #ddd;padding-bottom:3px;margin-top:1.1rem}'
            + 'table{width:100%;border-collapse:collapse}td{padding:5px 7px;vertical-align:top}';
    }

    // CSS CANÓNICO de impresión — IDÉNTICO a ContractTemplateRenderer::page(): la MISMA tipografía en el
    // medidor y en el PDF ⇒ el salto de página se calcula al PÍXEL real. Si cambias una medida acá,
    // cámbiala también en page() (y en `.cc-page`).
    function PRINT_CSS(contentW){
        return 'body{margin:0;width:' + contentW + 'px;font-family:' + fontStack() + ';font-size:' + fontSizePt() + ';line-height:1.15}'
            + 'h1{font-size:1.3rem;text-align:center}h2{font-size:1.02rem;border-bottom:1px solid #ddd;padding-bottom:3px;margin-top:1.1rem}'
            + 'table{width:100%;border-collapse:collapse}td{padding:5px 7px;vertical-align:top}';
    }
    // Alto/ancho ÚTIL de la hoja en px (una Carta/Oficio tiene tamaño físico fijo → nº de px conocido).
    function contentBox(){
        var d = (pageSel && PAGES[pageSel.value]) || { w: 216, h: 279, margin: 25 };
        return { d: d, w: (d.w - 2 * d.margin) * PXMM, h: (d.h - 2 * d.margin) * PXMM };
    }
    // Mide cada bloque de nivel superior en un iframe AISLADO con el CSS de impresión (alturas fieles).
    function measureItems(html, contentW){
        var ifr = document.createElement('iframe');
        ifr.style.cssText = 'position:absolute;left:-99999px;top:0;width:' + contentW + 'px;height:10px;border:0;visibility:hidden';
        document.body.appendChild(ifr);
        var doc = ifr.contentDocument;
        doc.open();
        doc.write('<!doctype html><meta charset="utf-8"><style>' + PRINT_CSS(contentW) + '</style>' + html);
        doc.close();
        var items = Array.prototype.slice.call(doc.body.children).map(function(el){
            var cs = ifr.contentWindow.getComputedStyle(el);
            return { h: el.offsetHeight + parseFloat(cs.marginTop || 0) + parseFloat(cs.marginBottom || 0),
                     br: el.classList.contains('cc-pb'), html: el.outerHTML };
        });
        document.body.removeChild(ifr);
        return items;
    }

    // Reparte el contenido en HOJAS reales (vista "con datos").
    function paginate(inner){
        var box = contentBox();
        var items = measureItems(inner, box.w);
        var pages = splitPages(items, box.h);
        var total = pages.length;
        var sheets = pages.map(function(idxs, i){
            var b = idxs.map(function(j){ return items[j].html; }).join('');
            return '<div class="sheet">' + b + '<div class="sheet-foot">Página ' + (i + 1) + ' de ' + total + '</div></div>';
        }).join('');
        return '<!doctype html><meta charset="utf-8"><style>' + sheetCss(box.d) + '</style>' + sheets;
    }

    function refresh(){
        if(!previewOn){ return; }
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': token },
            body: 'body=' + encodeURIComponent(currentBody())
                + '&architecture=' + encodeURIComponent(archSel ? archSel.value : '')
                + '&page_size=' + encodeURIComponent(pageSel ? pageSel.value : '')
                + '&font_family=' + encodeURIComponent(fontInput ? fontInput.value : '')
                + '&font_size=' + encodeURIComponent(sizeInput ? sizeInput.value : '')
                + '&fragment=1'
        }).then(function(r){ return r.text(); }).then(function(inner){ frame.srcdoc = paginate(inner); });
    }
    function schedulePreview(){ if(!previewOn){ return; } clearTimeout(pvTimer); pvTimer = setTimeout(refresh, 450); }

    // ── Salto de página EN el editor: guía MEDIDA con el CSS de impresión (cae donde realmente cae) ──
    // Mide el cuerpo serializado (tokens como texto, sin las pastillas) con la MISMA rutina que la vista
    // paginada, y ancla la línea al mismo bloque en el canvas. Así la guía coincide con el salto real.
    function drawGuides(){
        if(!guides){ return; }
        guides.innerHTML = '';
        var ckids = Array.prototype.slice.call(canvas.children);
        if(!ckids.length){ return; }
        var box = contentBox();
        var items = measureItems(serialize(), box.w);
        var pages = splitPages(items, box.h);
        for(var p = 0; p < pages.length - 1; p++){
            var idxs = pages[p]; if(!idxs.length){ continue; }
            var el = ckids[idxs[idxs.length - 1]]; if(!el){ continue; }   // mismo bloque, ya en el canvas
            var g = document.createElement('div');
            g.className = 'cc-guide';
            g.style.top = (el.offsetTop + el.offsetHeight) + 'px';
            g.innerHTML = '<span>Página ' + (p + 2) + '</span>';
            guides.appendChild(g);
        }
    }
    function scheduleGuides(){ clearTimeout(gTimer); gTimer = setTimeout(drawGuides, 250); }


    // ── Paleta INSERTAR: cada mosaico reusa las MISMAS funciones del editor ──
    function tileFormat(tag){ canvas.focus(); document.execCommand('formatBlock', false, '<' + tag + '>'); schedulePreview(); scheduleGuides(); }
    var elTitle = document.getElementById('tplInsTitle');
    if(elTitle){ elTitle.addEventListener('click', function(){ tileFormat('h2'); }); }
    var elText = document.getElementById('tplInsText');
    if(elText){ elText.addEventListener('click', function(){ tileFormat('p'); }); }
    var elList = document.getElementById('tplInsList');
    if(elList){ elList.addEventListener('click', function(){ canvas.focus(); document.execCommand('insertUnorderedList', false, null); schedulePreview(); scheduleGuides(); }); }
    var elRub = document.getElementById('tplInsRubrica');
    if(elRub){ elRub.addEventListener('click', function(){ insertAtCaret(anchorChip('rubrica') + ' '); }); }
    var elTable = document.getElementById('tplInsTable');
    if(elTable){ elTable.addEventListener('click', function(){ insertAtCaret('<table border="1" cellpadding="6" style="border-collapse:collapse;width:100%"><tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></table><p><br></p>'); }); }

    // ── Alineación (panel de ajustes) ──
    document.querySelectorAll('.cc-align-btn').forEach(function(b){
        b.addEventListener('click', function(){
            canvas.focus();
            document.execCommand(b.getAttribute('data-align'), false, null);
            schedulePreview();
            scheduleGuides();
        });
    });
    document.getElementById('tplAddClause').addEventListener('click', function(){
        var n = canvas.querySelectorAll('p.cc-clause').length + 1;
        insertAtCaret('<p class="cc-clause"><strong>' + ordinal(n) + '. ' + esc('[Título de la cláusula]') + '</strong> ' + esc('[Redacta aquí el contenido de la cláusula.]') + '</p>');
    });
    document.getElementById('tplPageBreak').addEventListener('click', function(){ insertAtCaret('<p class="cc-pb" contenteditable="false"></p><p><br></p>'); });
    // ── Paleta visual: "Dato" / "Firma" abren un menú de mosaicos (en vez del dropdown) ──
    function closeAllPops(){
        document.querySelectorAll('.cc-pop').forEach(function(p){ p.setAttribute('hidden', ''); });
        document.querySelectorAll('.cc-ins [aria-haspopup]').forEach(function(b){ b.setAttribute('aria-expanded', 'false'); });
    }
    function wirePalette(btnId, popId, attr, chipFn){
        var btn = document.getElementById(btnId), pop = document.getElementById(popId);
        if(!btn || !pop){ return; }
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            var wasOpen = ! pop.hasAttribute('hidden');
            closeAllPops();
            if(! wasOpen){ pop.removeAttribute('hidden'); btn.setAttribute('aria-expanded', 'true'); }
        });
        pop.addEventListener('click', function(e){ e.stopPropagation(); });
        pop.querySelectorAll('.cc-pop-item').forEach(function(it){
            it.addEventListener('click', function(){ insertAtCaret(chipFn(it.getAttribute(attr)) + ' '); closeAllPops(); });
        });
    }
    wirePalette('tplInsFieldBtn', 'tplFieldPop', 'data-field', fieldChip);
    wirePalette('tplInsSigBtn', 'tplSigPop', 'data-anchor', function(k){ return anchorChip(k); });
    document.addEventListener('click', closeAllPops);

    // ── Cargar andamiaje ─────────────────────────────────────────────────
    document.getElementById('tplLoadScaffold').addEventListener('click', function(){
        var s = archSel && starters[archSel.value]; if(s === undefined){ return; }
        var hasContent = htmlMode ? htmlArea.value.trim() : canvas.textContent.trim();
        if(hasContent && !window.confirm(@json(__('Esto reemplazará el contenido del contrato con el andamiaje de este formato. ¿Continuar?')))){ return; }
        if(htmlMode){ htmlArea.value = s; } else { hydrate(s); }
        schedulePreview();
        scheduleGuides();
    });

    // ── Ver / editar HTML ────────────────────────────────────────────────
    var deskEl = document.getElementById('tplDesk');
    document.getElementById('tplToggleHtml').addEventListener('click', function(){
        if(!htmlMode){ htmlArea.value = serialize(); htmlArea.classList.remove('d-none'); if(deskEl){ deskEl.classList.add('d-none'); } htmlMode = true; }
        else { hydrate(htmlArea.value); htmlArea.classList.add('d-none'); if(deskEl){ deskEl.classList.remove('d-none'); } htmlMode = false; scheduleGuides(); }
    });

    // ── Toggle "Vista con datos" ─────────────────────────────────────────
    document.getElementById('tplTogglePreview').addEventListener('click', function(){
        previewOn = ! previewOn;
        wrap.classList.toggle('d-none', ! previewOn);
        this.classList.toggle('btn-crew', previewOn);
        this.classList.toggle('btn-crew-soft', ! previewOn);
        if(previewOn){ refresh(); }
    });

    // ── Arrastre LIBRE de la rúbrica ─────────────────────────────────────
    // Mueve la inicial por transform:translate SIN sacarla del flujo → sigue cayendo en su página al
    // paginar; el offset (data-x/data-y) se serializa como [[firma:rubrica|dx,dy]] y se estampa igual
    // en el PDF. No afecta la medición del salto de página (transform no toca el layout).
    (function(){
        var drag = null;
        canvas.addEventListener('pointerdown', function(e){
            var chip = e.target && e.target.closest ? e.target.closest('.cc-tok--rub') : null;
            if(!chip){ return; }
            e.preventDefault();
            drag = { chip: chip, sx: e.clientX, sy: e.clientY,
                     x0: parseInt(chip.getAttribute('data-x') || '0', 10) || 0,
                     y0: parseInt(chip.getAttribute('data-y') || '0', 10) || 0 };
        });
        document.addEventListener('pointermove', function(e){
            if(!drag){ return; }
            var x = drag.x0 + (e.clientX - drag.sx), y = drag.y0 + (e.clientY - drag.sy);
            drag.chip.setAttribute('data-x', x); drag.chip.setAttribute('data-y', y);
            drag.chip.style.transform = 'translate(' + x + 'px,' + y + 'px)';
        });
        function endDrag(){ if(drag){ drag = null; schedulePreview(); scheduleGuides(); } }
        document.addEventListener('pointerup', endDrag);
        document.addEventListener('pointercancel', endDrag);
    })();

    // ── Cambios -> preview ───────────────────────────────────────────────
    canvas.addEventListener('input', function(){ schedulePreview(); scheduleGuides(); });
    htmlArea.addEventListener('input', schedulePreview);
    if(archSel){ archSel.addEventListener('change', function(){ schedulePreview(); scheduleGuides(); }); }
    if(pageSel){ pageSel.addEventListener('change', function(){ applyPageSize(); schedulePreview(); scheduleGuides(); }); }
    // Tipografía base: marca la activa y, al cambiar, re-aplica a la hoja + re-mide (salto al píxel).
    document.querySelectorAll('#tplFont .cc-seg-btn').forEach(function(b){
        b.setAttribute('aria-pressed', b.getAttribute('data-font') === (fontInput ? fontInput.value : 'mono') ? 'true' : 'false');
        b.addEventListener('click', function(){
            document.querySelectorAll('#tplFont .cc-seg-btn').forEach(function(x){ x.setAttribute('aria-pressed', 'false'); });
            this.setAttribute('aria-pressed', 'true');
            if(fontInput){ fontInput.value = this.getAttribute('data-font'); }
            applyCanvasFont(); schedulePreview(); scheduleGuides();
        });
    });
    document.querySelectorAll('#tplSize .cc-seg-btn').forEach(function(b){
        b.setAttribute('aria-pressed', b.getAttribute('data-size') === (sizeInput ? sizeInput.value : '11') ? 'true' : 'false');
        b.addEventListener('click', function(){
            document.querySelectorAll('#tplSize .cc-seg-btn').forEach(function(x){ x.setAttribute('aria-pressed', 'false'); });
            this.setAttribute('aria-pressed', 'true');
            if(sizeInput){ sizeInput.value = this.getAttribute('data-size'); }
            applyCanvasFont(); schedulePreview(); scheduleGuides();
        });
    });
    if(initialsChk){ initialsChk.addEventListener('change', schedulePreview); }
    document.getElementById('tplRefresh').addEventListener('click', function(){ previewOn = true; refresh(); });

    // ── Al enviar: vuelca el cuerpo serializado ──────────────────────────
    document.getElementById('tplForm').addEventListener('submit', function(){ bodyIn.value = currentBody(); });

    // ── Init ─────────────────────────────────────────────────────────────
    applyPageSize();
    applyCanvasFont();
    hydrate(htmlArea.value);
    scheduleGuides();
})();
</script>
@endpush
