@extends('layouts.app')
@section('content')
{{-- CONTRACT BUILDER · editor MODO DOCUMENTO. Estructura estilo DocuSign: IZQ = "Insertar" (lista
     CATEGORIZADA y etiquetada de datos/firmas/elementos, sin submenús); CENTRO = barra de acciones +
     barra de FORMATO (formato/estilo/alineación/tipografía) ENCIMA de la hoja; DER = Ajustes del
     documento. Los saltos de página son AUTOMÁTICOS (guía roja medida). El body se serializa a HTML
     con {{tokens}} al enviar. --}}
@php $isNew = ! $template->exists; @endphp
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1360px">

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

        {{-- El descargo de responsabilidad legal se traslada al Contrato de Uso de la app (EULA) que se
             firma con el cliente (decisión del owner 2026-08-15): estorbaba la experiencia en el editor.
             El gating (contracts.author + representante-legal) y el cero clausulado de fábrica siguen. --}}

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ $isNew ? route('contracts.templates.store') : route('contracts.templates.update', $template) }}" id="tplForm">
            @csrf
            @unless($isNew)@method('PUT')@endunless
            <input type="hidden" name="body" id="tplBodyInput">
            <input type="hidden" name="language" value="{{ old('language', $template->language ?: 'es') }}">
            <input type="hidden" name="bilingual" value="0">

            <div class="cc-editor-grid">

                {{-- ── IZQUIERDA · Insertar (lista categorizada, sin submenús) ── --}}
                {{-- La clase cc-toolbar se conserva como ancla del test de la vista; el aspecto lo da .cc-panel. --}}
                <div class="cc-panel cc-toolbar">
                    <div class="cc-panel-h"><h3>{{ __('Insertar') }}</h3></div>
                    <div class="cc-panel-b cc-ins-list">
                        <div class="cc-ins-group" data-open="1">
                            <button type="button" class="cc-ins-cat" aria-expanded="true"><span>{{ __('Datos del trato') }}</span><svg class="cc-ins-chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></button>
                            <div class="cc-ins-rows">
                                @foreach($fields as $key => $label)
                                    <button type="button" class="cc-ins-row is-data" data-field="{{ $key }}">
                                        <span class="cc-ins-ic"><svg viewBox="0 0 24 24"><path d="M8 4H7a2 2 0 0 0-2 2v3a2 2 0 0 1-2 2 2 2 0 0 1 2 2v3a2 2 0 0 0 2 2h1M16 4h1a2 2 0 0 1 2 2v3a2 2 0 0 0 2 2 2 2 0 0 0-2 2v3a2 2 0 0 1-2 2h-1"/></svg></span>
                                        <span class="cc-ins-tx">{{ $label }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="cc-ins-group" data-open="1">
                            <button type="button" class="cc-ins-cat" aria-expanded="true"><span>{{ __('Firmas') }}</span><svg class="cc-ins-chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></button>
                            <div class="cc-ins-rows">
                                @foreach($anchors as $key => $label)
                                    <button type="button" class="cc-ins-row is-sig" data-anchor="{{ $key }}">
                                        <span class="cc-ins-ic"><svg viewBox="0 0 24 24"><path d="M3 19s3-1 6-1 6 2 9 1M4 15c3-8 6-9 7-5s2 6 4 3"/></svg></span>
                                        <span class="cc-ins-tx">{{ $label }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="cc-ins-group" data-open="1">
                            <button type="button" class="cc-ins-cat" aria-expanded="true"><span>{{ __('Elementos') }}</span><svg class="cc-ins-chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></button>
                            <div class="cc-ins-rows">
                                <button type="button" class="cc-ins-row is-el" id="tplAddClause">
                                    <span class="cc-ins-ic"><svg viewBox="0 0 24 24"><path d="M7 4h12M7 9h12M7 15h12M7 20h8M3 4h.01M3 9h.01M3 15h.01M3 20h.01"/></svg></span>
                                    <span class="cc-ins-tx">{{ __('Cláusula') }}</span>
                                </button>
                                <button type="button" class="cc-ins-row is-el" id="tplInsTable">
                                    <span class="cc-ins-ic"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="1"/><path d="M3 10h18M9 4v16"/></svg></span>
                                    <span class="cc-ins-tx">{{ __('Tabla') }}</span>
                                </button>
                            </div>
                        </div>

                        <p class="cc-ins-note">{{ __('Un clic inserta donde está el cursor.') }}</p>
                    </div>
                </div>

                {{-- ── CENTRO · Acciones + barra de formato + hoja ── --}}
                <div class="cc-center">

                    {{-- Barra de acciones (SIEMPRE visible, no al fondo del documento) --}}
                    <div class="cc-actionbar">
                        <button class="btn btn-crew" type="submit">{{ __('Guardar') }}</button>
                        <button type="button" id="tplTogglePreview" class="cc-act-btn">{{ __('Vista con datos') }}</button>
                    </div>

                    {{-- Barra de FORMATO (encima del documento): formato · estilo · alineación · tipografía --}}
                    <div class="cc-formatbar" id="tplFormatbar" role="toolbar" aria-label="{{ __('Formato') }}">
                        <div class="cc-tb-group">
                            <button type="button" class="cc-tb-btn" data-fmt="h1" title="{{ __('Título del contrato') }}">{{ __('Título') }}</button>
                            <button type="button" class="cc-tb-btn" data-fmt="h2" title="{{ __('Encabezado de sección') }}">{{ __('Sección') }}</button>
                            <button type="button" class="cc-tb-btn" data-fmt="p" title="{{ __('Párrafo normal') }}">{{ __('Texto') }}</button>
                            <button type="button" class="cc-tb-btn" data-fmt="ul" title="{{ __('Lista con viñetas') }}">{{ __('Lista') }}</button>
                        </div>
                        <span class="cc-tb-sep"></span>
                        <div class="cc-tb-group">
                            <button type="button" class="cc-tb-btn cc-mark-btn" data-cmd="bold" title="{{ __('Negrita') }} (Ctrl+B)"><span style="font-weight:800">B</span></button>
                            <button type="button" class="cc-tb-btn cc-mark-btn" data-cmd="italic" title="{{ __('Cursiva') }} (Ctrl+I)"><span style="font-style:italic;font-family:Georgia,serif">I</span></button>
                            <button type="button" class="cc-tb-btn cc-mark-btn" data-cmd="underline" title="{{ __('Subrayado') }} (Ctrl+U)"><span style="text-decoration:underline">U</span></button>
                        </div>
                        <span class="cc-tb-sep"></span>
                        <div class="cc-tb-group">
                            <button type="button" class="cc-tb-btn" data-align="justifyLeft" title="{{ __('Izquierda') }}"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h13"/></svg></button>
                            <button type="button" class="cc-tb-btn" data-align="justifyCenter" title="{{ __('Centrar') }}"><svg viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M6 18h12"/></svg></button>
                            <button type="button" class="cc-tb-btn" data-align="justifyRight" title="{{ __('Derecha') }}"><svg viewBox="0 0 24 24"><path d="M4 6h16M10 12h10M7 18h13"/></svg></button>
                            <button type="button" class="cc-tb-btn" data-align="justifyFull" title="{{ __('Justificar') }}"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                        </div>
                        <span class="cc-tb-sep"></span>
                        <div class="cc-tb-group">
                            <select class="cc-tb-select" name="font_family" id="tplFont" title="{{ __('Tipografía') }}" aria-label="{{ __('Tipografía') }}">
                                @foreach($fonts as $k => $f)
                                    <option value="{{ $k }}" data-stack="{{ $f['stack'] }}" style="font-family:{{ $f['stack'] }}" @selected(old('font_family', $fontFamily) === $k)>{{ $f['label'] }}</option>
                                @endforeach
                            </select>
                            <select class="cc-tb-select" name="font_size" id="tplSize" title="{{ __('Tamaño de letra') }}" aria-label="{{ __('Tamaño de letra') }}">
                                @foreach($fontSizes as $k => $label)
                                    <option value="{{ $k }}" @selected(old('font_size', $fontSize) === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="cc-desk" id="tplDesk">
                        <div class="cc-page-wrap">
                            <div id="tplCanvas" class="cc-page" contenteditable="true" spellcheck="true"></div>
                            <div id="tplGuides" class="cc-guides" aria-hidden="true"></div>
                        </div>
                    </div>

                    {{-- Escotilla HTML (oculta por defecto; para el owner) --}}
                    <textarea id="tplHtml" class="form-control cc-html d-none mt-2" rows="16"
                              style="font-family:ui-monospace,Consolas,monospace;font-size:.84rem;">{{ old('body', $template->body) }}</textarea>

                    {{-- ── Vista previa con datos de ejemplo (toggle) ── --}}
                    <div id="tplPreviewWrap" class="card mt-3 d-none">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <span class="fw-semibold">{{ __('Vista previa') }} <span class="text-muted small">({{ __('datos de ejemplo') }})</span></span>
                            <button type="button" id="tplRefresh" class="cc-act-btn">{{ __('Actualizar') }}</button>
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
                            <div class="cc-chips">
                                @foreach($subtypes as $val => $label)
                                    <input type="checkbox" class="cc-chip-input" name="applies_to[]" value="{{ $val }}"
                                           id="st_{{ $val }}" @checked(in_array($val, old('applies_to', $template->applies_to ?? []), true))>
                                    <label class="cc-chip" for="st_{{ $val }}">{{ $label }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="cc-field">
                            <label class="form-label fw-semibold d-block">{{ __('Formato del contrato') }}</label>
                            <select name="architecture" id="tplArch" class="form-select form-select-sm mb-2">
                                @foreach($architectures as $key => $a)
                                    <option value="{{ $key }}" @selected(old('architecture', $template->architecture ?: 'caratula_numbered') === $key)>{{ $a['label'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" id="tplLoadScaffold" class="btn btn-sm btn-crew-soft w-100">{{ __('Precargar formato') }}</button>
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
                            <label class="form-label fw-semibold d-block">{{ __('Estado') }}</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                       @checked(old('is_active', $template->is_active))>
                                <label class="form-check-label" for="is_active">{{ __('Plantilla activa') }}</label>
                            </div>
                            <div class="cc-hint">{{ __('Si está activa, se usa para los contratos de este tipo.') }}</div>
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
/* ── Rejilla del editor: INSERTAR · centro · AJUSTES ── */
/* La hoja Carta mide 816px: para que NO se corte en 3 columnas, el contenedor va ancho y, por debajo
   de ~1310px, las columnas se APILAN (hoja a ancho completo). */
.cc-editor-grid{display:grid;grid-template-columns:206px minmax(0,1fr) 240px;gap:14px;align-items:start}
@media (max-width:1309px){.cc-editor-grid{grid-template-columns:1fr}}
.cc-panel{background:var(--surface,#fff);border:1px solid var(--border,#d7dce4);border-radius:14px;box-shadow:0 1px 2px rgba(16,20,30,.04),0 8px 24px rgba(16,20,30,.06)}
.cc-panel-h{padding:14px 16px 10px}
.cc-panel-h h3{margin:0;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted,#6b7482)}
.cc-panel-b{padding:6px 14px 16px}
/* .cc-toolbar: clase-ancla que conserva el test de la vista; su aspecto lo da .cc-panel */
/* Botón "suave" DENTRO de un panel (fondo --surface): --surface-2 + borde para que no se funda (legible en oscuro) */
.cc-panel .btn-crew-soft{background:var(--surface-2,#f6f7f9);border:1px solid var(--border,#d7dce4);color:var(--text,#1a1a1a)}
.cc-panel .btn-crew-soft:hover,.cc-panel .btn-crew-soft:focus{background:var(--surface-3,#eceef3);color:var(--text,#1a1a1a)}

/* Insertar: lista categorizada + etiquetada (estilo DocuSign: todo a la mano, iconos chicos) */
.cc-ins-list{max-height:74vh;overflow:auto}
/* Secciones COLAPSABLES: el encabezado es un botón; su grupo se pliega con data-open */
.cc-ins-cat{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;margin:8px 0 2px;padding:6px 7px;border:none;background:none;font:inherit;font-size:.66rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted,#6b7482);cursor:pointer;border-radius:8px}
.cc-ins-group:first-child .cc-ins-cat{margin-top:2px}
.cc-ins-cat:hover{background:var(--surface-2,#f6f7f9);color:var(--text,#1a1a1a)}
.cc-ins-chev{width:13px;height:13px;flex:0 0 auto;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;transition:transform .18s ease}
.cc-ins-group[data-open="0"] .cc-ins-chev{transform:rotate(-90deg)}
.cc-ins-group[data-open="0"] .cc-ins-rows{display:none}
.cc-ins-row{display:flex;align-items:center;gap:9px;width:100%;padding:7px 8px;border-radius:9px;border:1px solid transparent;background:none;color:var(--text,#1a1a1a);font:inherit;text-align:left;cursor:pointer;transition:background .13s ease,border-color .13s ease}
.cc-ins-row:hover{background:var(--surface-2,#f6f7f9);border-color:var(--border,#d7dce4)}
.cc-ins-row:active{transform:scale(.99)}
.cc-ins-tx{font-size:.8rem;font-weight:600;line-height:1.2}
.cc-ins-ic{flex:0 0 auto;width:26px;height:26px;border-radius:7px;display:grid;place-items:center}
.cc-ins-ic svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.cc-ins-row.is-data .cc-ins-ic{background:color-mix(in srgb,#7c3aed 16%,var(--surface,#fff));color:#7c3aed}
.cc-ins-row.is-sig  .cc-ins-ic{background:color-mix(in srgb,#2563eb 16%,var(--surface,#fff));color:#2563eb}
.cc-ins-row.is-el   .cc-ins-ic{background:var(--surface-3,#eceef3);color:var(--muted,#6b7482)}
.cc-ins-note{margin:12px 2px 0;font-size:.72rem;color:var(--muted,#6b7482);line-height:1.5}

/* Barra de acciones (siempre visible) */
.cc-actionbar{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.cc-actionbar .btn-crew{font-weight:700}
.cc-act-btn{border:1px solid var(--border,#d7dce4);background:var(--surface,#fff);color:var(--text,#1a1a1a);border-radius:9px;padding:6px 13px;font-size:.83rem;font-weight:600;cursor:pointer;transition:background .14s ease,border-color .14s ease}
.cc-act-btn:hover{background:var(--surface-2,#f6f7f9);border-color:var(--brand,#ff0046)}
.cc-act-btn[aria-pressed="true"]{background:color-mix(in srgb,var(--brand,#ff0046) 12%,var(--surface,#fff));border-color:var(--brand,#ff0046);color:var(--brand,#ff0046)}

/* Barra de formato (encima del documento) */
.cc-formatbar{display:flex;flex-wrap:wrap;align-items:center;gap:6px;padding:8px 10px;margin-bottom:10px;background:var(--surface,#fff);border:1px solid var(--border,#d7dce4);border-radius:12px;box-shadow:0 1px 2px rgba(16,20,30,.04)}
.cc-tb-group{display:flex;align-items:center;gap:4px}
.cc-tb-sep{width:1px;align-self:stretch;margin:2px 4px;background:var(--border,#d7dce4)}
.cc-tb-btn{display:inline-flex;align-items:center;justify-content:center;height:32px;min-width:32px;padding:0 10px;border:1px solid var(--border,#d7dce4);border-radius:8px;background:var(--surface-2,#f6f7f9);color:var(--text,#1a1a1a);font-size:.82rem;font-weight:600;cursor:pointer;transition:background .14s ease,border-color .14s ease,color .14s ease,transform .1s ease}
.cc-tb-btn:hover{background:var(--surface,#fff);border-color:var(--brand,#ff0046)}
.cc-tb-btn:active{transform:scale(.96)}
.cc-tb-btn[aria-pressed="true"]{background:color-mix(in srgb,var(--brand,#ff0046) 14%,var(--surface,#fff));border-color:var(--brand,#ff0046);color:var(--brand,#ff0046)}
.cc-tb-btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}
.cc-tb-select{height:32px;border:1px solid var(--border,#d7dce4);border-radius:8px;background:var(--surface-2,#f6f7f9);color:var(--text,#1a1a1a);font-size:.82rem;font-weight:600;padding:0 8px;cursor:pointer}

/* Chips-píldora (Aplica a) */
.cc-field{margin:0 0 14px}
.cc-field > .form-label{font-size:.7rem;letter-spacing:.05em;text-transform:uppercase;color:var(--muted,#6b7482);margin-bottom:6px}
.cc-hint{margin-top:5px;font-size:.72rem;color:var(--muted,#6b7482);line-height:1.4}
.cc-chips{display:flex;flex-wrap:wrap;gap:6px}
.cc-chip-input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.cc-chip{display:inline-flex;align-items:center;padding:6px 13px;border-radius:999px;border:1px solid var(--border,#d7dce4);background:var(--surface-2,#f6f7f9);color:var(--muted,#6b7482);font-size:.8rem;font-weight:600;cursor:pointer;transition:background .15s ease,border-color .15s ease,color .15s ease}
.cc-chip:hover{border-color:color-mix(in srgb,var(--brand,#ff0046) 40%,transparent);color:var(--text,#1a1a1a)}
.cc-chip-input:checked + .cc-chip{background:color-mix(in srgb,var(--brand,#ff0046) 13%,var(--surface,#fff));border-color:var(--brand,#ff0046);color:var(--brand,#ff0046)}
.cc-chip-input:focus-visible + .cc-chip{outline:2px solid var(--brand,#ff0046);outline-offset:2px}

/* Escritorio + hoja */
.cc-desk{padding:18px;border:1px solid var(--border,#d7dce4);border-radius:12px;background:var(--surface-2,#e9edf2);max-height:72vh;overflow:auto}
.cc-page-wrap{position:relative;width:-moz-fit-content;width:fit-content;margin:0 auto}
.cc-page{--pg-w:216mm;--pg-h:279mm;--pg-m:25mm;position:relative;width:var(--pg-w);min-height:var(--pg-h);padding:var(--pg-m);margin:0;background:#fff;color:#1a1a1a;box-shadow:0 3px 16px rgba(0,0,0,.20);font-family:"Courier New",Courier,monospace;line-height:1.15;font-size:9pt}
.cc-page:focus{outline:none}
.cc-guides{position:absolute;inset:0;pointer-events:none;overflow:hidden;z-index:2}
.cc-guide{position:absolute;left:0;right:0;border-top:2px dashed color-mix(in srgb, var(--brand,#ff0046) 45%, transparent)}
.cc-guide span{position:absolute;right:8px;top:-9px;background:var(--surface-2,#e9edf2);color:var(--brand,#ff0046);font:600 .62rem system-ui,-apple-system,sans-serif;padding:0 6px;letter-spacing:.02em}
.cc-page h1{font-size:1.3rem;text-align:center}
.cc-page h2{font-size:1.02rem;border-bottom:1px solid #dddddd;padding-bottom:3px;margin-top:1.1rem}
.cc-page table{width:100%;border-collapse:collapse}
.cc-page td{padding:5px 7px;vertical-align:top}
.cc-page .cc-pb{height:0;margin:22px 0;border:0;border-top:2px dashed var(--brand,#ff0046);position:relative}
.cc-tok{display:inline-block;padding:1px 8px;margin:0 1px;border-radius:999px;background:color-mix(in srgb, var(--brand,#ff0046) 12%, #fff);border:1px solid color-mix(in srgb, var(--brand,#ff0046) 35%, transparent);color:#10151f;font-family:system-ui,-apple-system,sans-serif;font-size:.78rem;white-space:nowrap;user-select:all;cursor:default}
.cc-tok--sig{background:color-mix(in srgb, #2563eb 14%, #fff);border-color:color-mix(in srgb, #2563eb 38%, transparent)}
.cc-tok--rub{cursor:move;touch-action:none}
.cc-tok--rub:hover{box-shadow:0 0 0 2px color-mix(in srgb, #2563eb 40%, transparent)}
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
    var fontSel  = document.getElementById('tplFont');     // <select> tipografía (barra de formato)
    var sizeSel  = document.getElementById('tplSize');     // <select> tamaño
    var guides   = document.getElementById('tplGuides');
    var deskEl   = document.getElementById('tplDesk');
    var formatbar = document.getElementById('tplFormatbar');
    var insList  = document.querySelector('.cc-ins-list');

    // Familia y tamaño de la tipografía elegida (los mismos en hoja, medidor y PDF → salto al píxel).
    function fontStack(){
        var o = fontSel && fontSel.selectedOptions && fontSel.selectedOptions[0];
        return o ? o.getAttribute('data-stack') : '"Courier New",Courier,monospace';
    }
    function fontSizePt(){ return (sizeSel ? sizeSel.value : '9') + 'pt'; }
    function applyCanvasFont(){ if(canvas){ canvas.style.fontFamily = fontStack(); canvas.style.fontSize = fontSizePt(); } }
    var gTimer = null;
    var LB = '{' + '{', RB = '}' + '}';   // evita que Blade parsee llaves literales en este script

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
                + '&font_family=' + encodeURIComponent(fontSel ? fontSel.value : '')
                + '&font_size=' + encodeURIComponent(sizeSel ? sizeSel.value : '')
                + '&fragment=1'
        }).then(function(r){ return r.text(); }).then(function(inner){ frame.srcdoc = paginate(inner); });
    }
    function schedulePreview(){ if(!previewOn){ return; } clearTimeout(pvTimer); pvTimer = setTimeout(refresh, 450); }

    // ── Salto de página AUTOMÁTICO: guía MEDIDA con el CSS de impresión (cae donde realmente cae) ──
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

    // ── Bloques tocados por la selección (para formato/alineación) ───────
    function selectedBlocks(){
        var sel = window.getSelection();
        if(!sel || !sel.rangeCount){ return []; }
        var range = sel.getRangeAt(0);
        var blocks = Array.prototype.slice.call(canvas.children).filter(function(el){
            try{ return el.nodeType === 1 && range.intersectsNode(el); }catch(e){ return false; }
        });
        if(!blocks.length){   // selección colapsada: sube hasta el hijo directo del canvas
            var n = range.startContainer;
            while(n && n.parentNode !== canvas){ n = n.parentNode; }
            if(n && n.nodeType === 1){ blocks = [n]; }
        }
        return blocks;
    }
    // "Regresar a ese estado": quita estilos en línea/clases para que mande el CSS canónico de la hoja.
    // Solo toca títulos/párrafos (no tablas ni listas, para no borrar sus atributos).
    function cleanBlock(b){
        if(!b || b.nodeType !== 1 || !/^(P|H1|H2|H3)$/.test(b.tagName)){ return; }
        b.removeAttribute('style'); b.removeAttribute('align');
        b.classList.remove('cc-clause');
        if(!b.getAttribute('class')){ b.removeAttribute('class'); }
    }
    function applyFormat(tag){
        canvas.focus();
        document.execCommand('formatBlock', false, '<' + tag + '>');
        selectedBlocks().forEach(cleanBlock);
        schedulePreview(); scheduleGuides();
    }

    // ── PRESERVAR LA SELECCIÓN: el botón NO debe robar el foco al contenteditable ──
    // Sin esto, el mousedown del botón colapsa la selección ANTES del click → B/I/U no marcan nada.
    // (Los <select> NO se tocan: no son <button>, así se siguen abriendo.)
    function keepSelection(container){
        if(!container){ return; }
        container.addEventListener('mousedown', function(e){ if(e.target.closest('button')){ e.preventDefault(); } });
    }
    keepSelection(formatbar);
    keepSelection(insList);

    // ── Barra de formato: formato de bloque ──
    formatbar.querySelectorAll('.cc-tb-btn[data-fmt]').forEach(function(b){
        b.addEventListener('click', function(){
            var f = b.getAttribute('data-fmt');
            if(f === 'ul'){ canvas.focus(); document.execCommand('insertUnorderedList', false, null); schedulePreview(); scheduleGuides(); }
            else { applyFormat(f); }
        });
    });

    // ── Negrita / Cursiva / Subrayado (modo ETIQUETA, styleWithCSS=false) ──
    function reflectMarks(){
        formatbar.querySelectorAll('.cc-mark-btn').forEach(function(b){
            var on = false; try{ on = document.queryCommandState(b.getAttribute('data-cmd')); }catch(e){}
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }
    formatbar.querySelectorAll('.cc-mark-btn').forEach(function(b){
        b.addEventListener('click', function(){
            canvas.focus();
            document.execCommand(b.getAttribute('data-cmd'), false, null);
            reflectMarks(); schedulePreview(); scheduleGuides();
        });
    });
    document.addEventListener('selectionchange', function(){ if(document.activeElement === canvas){ reflectMarks(); } });

    // ── Alineación: directo sobre el/los bloque(s) → fiable en todo el contenido ──
    var ALIGN = { justifyLeft:'left', justifyCenter:'center', justifyRight:'right', justifyFull:'justify' };
    formatbar.querySelectorAll('.cc-tb-btn[data-align]').forEach(function(b){
        b.addEventListener('click', function(){
            canvas.focus();
            var css = ALIGN[b.getAttribute('data-align')] || 'left';
            selectedBlocks().forEach(function(el){ if(el.nodeType === 1){ el.style.textAlign = css; } });
            formatbar.querySelectorAll('.cc-tb-btn[data-align]').forEach(function(x){ x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
            schedulePreview(); scheduleGuides();
        });
    });

    // ── Insertar (lista izquierda): datos, firmas y elementos ──
    document.querySelectorAll('.cc-ins-row[data-field]').forEach(function(b){
        b.addEventListener('click', function(){ insertAtCaret(fieldChip(b.getAttribute('data-field')) + ' '); });
    });
    document.querySelectorAll('.cc-ins-row[data-anchor]').forEach(function(b){
        b.addEventListener('click', function(){ insertAtCaret(anchorChip(b.getAttribute('data-anchor')) + ' '); });
    });
    // Secciones colapsables de INSERTAR (encabezado plega/despliega su grupo)
    document.querySelectorAll('.cc-ins-cat').forEach(function(h){
        h.addEventListener('click', function(){
            var g = h.closest('.cc-ins-group'); if(!g){ return; }
            var willOpen = g.getAttribute('data-open') === '0';
            g.setAttribute('data-open', willOpen ? '1' : '0');
            h.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });
    document.getElementById('tplAddClause').addEventListener('click', function(){
        var n = canvas.querySelectorAll('p.cc-clause').length + 1;
        insertAtCaret('<p class="cc-clause"><strong>' + ordinal(n) + '. ' + esc('[Título de la cláusula]') + '</strong> ' + esc('[Redacta aquí el contenido de la cláusula.]') + '</p>');
    });
    document.getElementById('tplInsTable').addEventListener('click', function(){
        insertAtCaret('<table border="1" cellpadding="6" style="border-collapse:collapse;width:100%"><tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></table><p><br></p>');
    });

    // ── Cargar andamiaje ─────────────────────────────────────────────────
    document.getElementById('tplLoadScaffold').addEventListener('click', function(){
        var s = archSel && starters[archSel.value]; if(s === undefined){ return; }
        var hasContent = htmlMode ? htmlArea.value.trim() : canvas.textContent.trim();
        if(hasContent && !window.confirm(@json(__('Esto reemplazará el contenido con la estructura base de este formato. ¿Continuar?')))){ return; }
        if(htmlMode){ htmlArea.value = s; } else { hydrate(s); }
        schedulePreview();
        scheduleGuides();
    });

    // ── Toggle "Vista con datos" ─────────────────────────────────────────
    document.getElementById('tplTogglePreview').addEventListener('click', function(){
        previewOn = ! previewOn;
        wrap.classList.toggle('d-none', ! previewOn);
        this.setAttribute('aria-pressed', previewOn ? 'true' : 'false');
        if(previewOn){ refresh(); }
    });

    // ── Arrastre LIBRE de la rúbrica ─────────────────────────────────────
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
    // Tipografía base (barra de formato): al cambiar, re-aplica a la hoja + re-mide (salto al píxel).
    if(fontSel){ fontSel.addEventListener('change', function(){ applyCanvasFont(); schedulePreview(); scheduleGuides(); }); }
    if(sizeSel){ sizeSel.addEventListener('change', function(){ applyCanvasFont(); schedulePreview(); scheduleGuides(); }); }
    document.getElementById('tplRefresh').addEventListener('click', function(){ previewOn = true; refresh(); });

    // ── Al enviar: vuelca el cuerpo serializado ──────────────────────────
    document.getElementById('tplForm').addEventListener('submit', function(){ bodyIn.value = currentBody(); });

    // ── Init ─────────────────────────────────────────────────────────────
    try{ document.execCommand('styleWithCSS', false, false); }catch(e){}   // B/I/U como <b>/<i>/<u>, no spans con style
    applyPageSize();
    applyCanvasFont();
    hydrate(htmlArea.value);
    scheduleGuides();
})();
</script>
@endpush
