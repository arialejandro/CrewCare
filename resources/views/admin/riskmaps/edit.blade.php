@extends('layouts.app')
@section('title', 'Editar mapeo · ' . $map->locationName() . ' - ' . ($branding['brand_name'] ?? 'CrewCare'))
@include('componentes._confirm-submit')

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
    @media (max-width:1100px){
        .rm-ed-grid{grid-template-columns:1fr}
        /* Táctil: el lienzo (foto) manda; herramientas arriba, listas debajo */
        .rm-panel--stage{order:1}
        .rm-panel--props{order:2}
        .rm-panel--views{order:3}
        .rm-ed-canvas-wrap{max-height:82vh}
        .rm-ed-canvas img{max-height:78vh}
        /* Blancos de toque >=44px */
        .rm-btn,.rm-side button,.rm-src-tabs button{min-height:44px}
        .rm-zoom__btn{min-height:44px;min-width:44px}
        .rm-ed-armbar select{flex:1 1 42%}
        .rm-ed-armbar select,.rm-size select,.rm-addview select,.rm-addview input[type=text],.rm-addview input[type=file]{min-height:44px}
        .rm-ed-view__del{min-width:44px;min-height:44px}
        .rm-ed-view__link img{width:52px;height:40px}
        /* Evita el zoom automático de iOS al enfocar (>=16px) */
        .rm-props input[type=text],.rm-props textarea,.rm-addview select,.rm-addview input[type=text],.rm-ed-armbar select,.rm-size select{font-size:16px}
    }

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
    /* Contenedor con scroll para el zoom (imagen + pines escalan JUNTOS) */
    .rm-ed-canvas-wrap{overflow:auto;-webkit-overflow-scrolling:touch;position:relative;border-radius:10px;max-height:80vh}
    .rm-ed-canvas-sizer{position:relative}
    .rm-ed-canvas{position:relative;border:1px solid var(--stroke);border-radius:10px;overflow:hidden;background:#0b1220;user-select:none;touch-action:none;transform-origin:0 0}
    .rm-ed-canvas.armed{cursor:crosshair}
    /* Con zoom, el dedo puede desplazar (los pines conservan touch-action:none y se arrastran) */
    .rm-ed-canvas.is-zoomed{touch-action:pan-x pan-y}
    .rm-ed-canvas img{display:block;width:100%;max-height:70vh;object-fit:contain}
    .rm-ed-empty{padding:3rem 1rem;text-align:center;color:var(--text-muted)}

    .rm-pin{position:absolute;transform:translate(-50%,-100%);z-index:2;cursor:grab;touch-action:none}
    .rm-pin.sel{z-index:5}
    .rm-pin.sel .rm-pin__drop{outline:2px solid #fff;outline-offset:1px}
    .rm-pin.sel .rm-pin__sign{outline:2px solid #fff;outline-offset:1px;border-radius:7px}
    /* área de toque ampliada (>=44px) para arrastrar en iPad sin precisión */
    .rm-pin::before,.rm-chip::before{content:'';position:absolute;inset:-9px}
    .rm-pin__drop{width:var(--pin,32px);height:var(--pin,32px);border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 2px 5px rgba(0,0,0,.45),inset 0 1.5px 1px rgba(255,255,255,.4);border:1.5px solid rgba(255,255,255,.95)}
    .rm-pin__drop svg{width:calc(var(--pin,32px)*.62);height:calc(var(--pin,32px)*.62);transform:rotate(45deg)}
    /* Señal a color (ISO/hazmat/EPP/clima): upright, sin gota, sobre PLATE blanco para
       que resalte sobre la foto (el contorno/línea-arte se pierde sin fondo). */
    .rm-pin__sign{width:calc(var(--pin,32px)*1.35);height:calc(var(--pin,32px)*1.35);display:flex;align-items:center;justify-content:center;background:#fff;border-radius:7px;padding:3px;box-sizing:border-box;border:1.5px solid rgba(255,255,255,.95);box-shadow:0 2px 5px rgba(0,0,0,.5)}
    .rm-pin__sign img,.rm-sign{width:100%;height:100%;object-fit:contain;display:block}
    .rm-chip{position:absolute;transform:translate(-50%,-50%);z-index:3;cursor:grab;touch-action:none;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:9px;font-weight:700;color:#fff;border-radius:6px;padding:3px 8px;line-height:1.3;box-shadow:0 1px 3px rgba(0,0,0,.35);text-transform:uppercase;letter-spacing:.02em}
    .rm-chip.sel{outline:2px solid #fff;outline-offset:1px;z-index:6}
    .rm-leaders{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:1}
    .rm-hint{font-size:.72rem;color:var(--text-muted);margin:.5rem 0 .4rem}
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

    /* Ajuste fino (nudge) del pin seleccionado — precisión con el dedo */
    .rm-nudge{margin:.7rem 0 .2rem}
    .rm-nudge__lbl{display:block;font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin:0 0 .4rem}
    .rm-nudge__pad{display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:repeat(3,1fr);gap:.3rem;max-width:168px}
    .rm-nudge__btn{min-width:44px;min-height:44px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;line-height:1;border:1px solid var(--stroke);background:var(--surface-3);color:var(--text);border-radius:10px;cursor:pointer;-webkit-user-select:none;user-select:none;touch-action:manipulation}
    .rm-nudge__btn:active{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary)}
    .rm-nudge__u{grid-column:2;grid-row:1}
    .rm-nudge__l{grid-column:1;grid-row:2}
    .rm-nudge__r{grid-column:3;grid-row:2}
    .rm-nudge__d{grid-column:2;grid-row:3}

    /* Zoom del lienzo */
    .rm-zoom{display:inline-flex;align-items:center;gap:.25rem;margin-left:auto}
    .rm-zoom__btn{min-width:40px;min-height:36px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--stroke);background:var(--surface-3);color:var(--text);border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;line-height:1;padding:0 .4rem;touch-action:manipulation}
    .rm-zoom__btn:active{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary)}
    #rm-zoom-lvl{min-width:54px;font-size:.78rem;font-weight:600}
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

    {{-- Pines de peligro COLGANTES: el scouting quitó ese peligro después de mapearlo.
         Se avisa aquí (antes del sello) para que el safety decida; el sellado queda
         bloqueado hasta resolverlos. NO se borran pines automáticamente. --}}
    @if(!empty($orphanMarkers) && $orphanMarkers->count())
        <div class="alert alert-warning" role="alert" style="border-left:4px solid var(--warning,#d97706)">
            <strong>Atención:</strong> {{ $orphanMarkers->count() }}
            {{ $orphanMarkers->count() === 1 ? 'señal de peligro ya no está' : 'señales de peligro ya no están' }}
            evaluada{{ $orphanMarkers->count() === 1 ? '' : 's' }} en el scouting de origen
            @php
                $orphanViews = collect($orphanMarkers)
                    ->map(fn($m) => optional($views->firstWhere('id', $m->view_id))->displayLabel())
                    ->filter()->unique()->values();
            @endphp
            @if($orphanViews->count())
                (en: {{ $orphanViews->implode(', ') }})
            @endif.
            Corrige la evaluación del scouting o retira {{ $orphanMarkers->count() === 1 ? 'ese pin' : 'esos pines' }}
            antes de sellar. <strong>No se sellará</strong> mientras haya peligros colgantes.
        </div>
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
            <form action="{{ route('riskmaps.seal', $map->id) }}" method="POST" data-confirm="Sellar el mapeo lo vuelve INMUTABLE. ¿Continuar?" style="display:inline">
                @csrf
                <button type="submit" class="rm-btn rm-btn--accent">@include('componentes._icon', ['name' => 'shield-check']) Sellar</button>
            </form>
        </div>
    </div>

    <div class="rm-ed-grid">

        {{-- IZQUIERDA: vistas --}}
        <div class="rm-panel rm-panel--views">
            <h3>Vistas</h3>
            <ul class="rm-ed-views" id="rm-views">
                @foreach($views as $v)
                    <li class="rm-ed-view {{ $current && $current->id === $v->id ? 'is-current' : '' }}" draggable="true" data-id="{{ $v->id }}">
                        <a href="{{ route('riskmaps.edit', ['id' => $map->id, 'view' => $v->id]) }}" class="rm-ed-view__link">
                            <img src="{{ $v->imageUrl() }}" alt="">
                            <span class="rm-ed-view__lbl">{{ $v->displayLabel() }}</span>
                        </a>
                        <form action="{{ route('riskmaps.views.destroy', ['id' => $map->id, 'view' => $v->id]) }}" method="POST" data-confirm="¿Quitar esta vista?">
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
                    <input type="file" name="image" id="rm-file" accept="image/*,.heic,.heif" data-cc-photo data-cc-noauto>
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
        <div class="rm-panel rm-panel--stage">
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
                    <div class="rm-zoom" role="group" aria-label="Zoom del lienzo">
                        <button type="button" class="rm-zoom__btn" id="rm-zoom-out" aria-label="Alejar">&minus;</button>
                        <button type="button" class="rm-zoom__btn" id="rm-zoom-lvl" aria-label="Restablecer zoom a 100%">100%</button>
                        <button type="button" class="rm-zoom__btn" id="rm-zoom-in" aria-label="Acercar">+</button>
                    </div>
                </div>
                <div class="rm-ed-canvas-wrap" id="rm-canvas-wrap">
                    <div class="rm-ed-canvas-sizer" id="rm-canvas-sizer">
                        <div class="rm-ed-canvas" id="rm-canvas" style="--pin: {{ $map->pinPx() }}px">
                            <img src="{{ $current->imageUrl() }}" alt="{{ $current->displayLabel() }}" id="rm-canvas-img" draggable="false">
                        </div>
                    </div>
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
        <div class="rm-panel rm-panel--props">
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
                        <p class="rm-hint">Arrastra el pin o su etiqueta para moverlos, o usa el ajuste fino.</p>
                        <div class="rm-nudge" role="group" aria-label="Ajuste fino de la posición del pin">
                            <span class="rm-nudge__lbl">Ajuste fino del pin</span>
                            <div class="rm-nudge__pad" id="rm-nudge">
                                <button type="button" class="rm-nudge__btn rm-nudge__u" data-nudge="up" aria-label="Mover el pin hacia arriba">&uarr;</button>
                                <button type="button" class="rm-nudge__btn rm-nudge__l" data-nudge="left" aria-label="Mover el pin a la izquierda">&larr;</button>
                                <button type="button" class="rm-nudge__btn rm-nudge__r" data-nudge="right" aria-label="Mover el pin a la derecha">&rarr;</button>
                                <button type="button" class="rm-nudge__btn rm-nudge__d" data-nudge="down" aria-label="Mover el pin hacia abajo">&darr;</button>
                            </div>
                        </div>
                        <button type="button" class="rm-btn" id="rm-lbl-reset" style="width:100%;justify-content:center">Reubicar etiqueta</button>
                        <button type="button" class="rm-btn rm-del" id="rm-del" style="width:100%;justify-content:center;margin-top:.5rem">Quitar marcador</button>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>

{{-- Datos para el editor (sin {{ }} dentro de <script>: van por JSON) --}}
@php
    $__iconKeys = \App\Models\RiskMap::ICON_KEYS; // claves dibujadas (peligros/recursos) o el pin caía a 'area'
    // + señales realmente alcanzables: el icono de cada evento elegible (incluye un
    //   override risk_icon que apunte a un slug de la biblioteca, p. ej. 'adr_3b').
    foreach ($eligibleEvents as $__ev) { $__iconKeys[] = $__ev['icon']; }
    $__iconKeys = array_values(array_unique($__iconKeys));
    $__icons = [];
    foreach ($__iconKeys as $k) { $__icons[$k] = trim(view('componentes._rm-icon', ['key' => $k])->render()); }
    $__markers = $current ? $current->markers->map(function ($m) use ($map) {
        if ($m->kind === 'resource') { $lbl = $m->resourceLabel(); $sh = $lbl; $ic = $m->iconKey(); }
        else { $ev = $map->eligibleEvents()->get((int) $m->event_id); $lbl = $ev['name'] ?? ('#' . $m->event_id); $sh = $ev['short'] ?? 'Peligro'; $ic = $ev['icon'] ?? 'haz-warn'; }
        return [
            'id' => $m->id, 'kind' => $m->kind, 'resource_type' => $m->resource_type,
            'event_id' => $m->event_id, 'x_pct' => (float) $m->x_pct, 'y_pct' => (float) $m->y_pct,
            'label_x' => $m->label_x_pct !== null ? (float) $m->label_x_pct : null,
            'label_y' => $m->label_y_pct !== null ? (float) $m->label_y_pct : null,
            'label_side' => $m->label_side, 'reference_text' => $m->reference_text,
            'icon' => $ic, 'label' => $lbl, 'short' => $sh, 'color' => $m->color(), 'ink' => $m->ink(),
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
    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
    function svgEl(t) { return document.createElementNS('http://www.w3.org/2000/svg', t); }

    // Capa de líneas guía (pin -> etiqueta), en coords 0..100 = % de la imagen.
    var leaders = svgEl('svg');
    leaders.setAttribute('class', 'rm-leaders');
    leaders.setAttribute('viewBox', '0 0 100 100');
    leaders.setAttribute('preserveAspectRatio', 'none');
    canvas.insertBefore(leaders, img.nextSibling);

    // Posición del chip: la guardada, o una por defecto a un lado del pin.
    function labelPos(m) {
        if (m.label_x !== null && m.label_x !== undefined) { return { x: +m.label_x, y: +m.label_y }; }
        var dir = m.x_pct > 55 ? -1 : 1;
        return { x: clamp(m.x_pct + dir * 13, 5, 95), y: clamp(m.y_pct - 12, 5, 95) };
    }

    function updateLine(m) {
        if (!m._line) { return; }
        var lp = labelPos(m);
        m._line.setAttribute('x1', m.x_pct); m._line.setAttribute('y1', m.y_pct);
        m._line.setAttribute('x2', lp.x); m._line.setAttribute('y2', lp.y);
        if (m._chip) { m._chip.style.left = lp.x + '%'; m._chip.style.top = lp.y + '%'; }
    }

    function renderMarker(m) {
        var pin = document.createElement('div');
        pin.className = 'rm-pin';
        pin.setAttribute('data-id', m.id);
        pin.style.left = m.x_pct + '%';
        pin.style.top = m.y_pct + '%';
        var html = iconFor(m.icon);
        var isSign = html.indexOf('rm-sign') !== -1; // señal a color → sin gota
        // Coherencia: si el pin es señal a color, la etiqueta/guía van NEUTRAS (no el ámbar
        // de peligro, que peleaba con el tono de la señal); si es glifo, color semántico.
        var lblColor = isSign ? '#334155' : (m.color || '#c0392b');
        var drop = document.createElement('div');
        if (isSign) {
            drop.className = 'rm-pin__sign';
        } else {
            drop.className = 'rm-pin__drop';
            drop.style.background = m.color || '#c0392b';
            drop.style.color = m.ink || '#fff';
        }
        drop.innerHTML = html;
        pin.appendChild(drop);
        pin.title = (m.label || '') + (m.reference_text ? ' — ' + m.reference_text : '');

        var lp = labelPos(m);
        var chip = document.createElement('div');
        chip.className = 'rm-chip';
        chip.setAttribute('data-id', m.id);
        chip.style.left = lp.x + '%';
        chip.style.top = lp.y + '%';
        chip.style.background = lblColor;
        chip.textContent = m.short || m.label || '';

        var line = svgEl('line');
        line.setAttribute('vector-effect', 'non-scaling-stroke');
        line.setAttribute('stroke', lblColor);
        line.setAttribute('stroke-width', '1.4');
        line.setAttribute('x1', m.x_pct); line.setAttribute('y1', m.y_pct);
        line.setAttribute('x2', lp.x); line.setAttribute('y2', lp.y);
        leaders.appendChild(line);

        m._pin = pin; m._chip = chip; m._line = line;
        canvas.appendChild(pin);
        canvas.appendChild(chip);
        attachDrag(pin, m, false);
        attachDrag(chip, m, true);
    }

    function renderAll() {
        canvas.querySelectorAll('.rm-pin, .rm-chip').forEach(function (p) { p.remove(); });
        while (leaders.firstChild) { leaders.removeChild(leaders.firstChild); }
        markers.forEach(function (m) { renderMarker(m); });
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
        if (e.target.closest('.rm-pin') || e.target.closest('.rm-chip')) { return; }
        var p = pct(e);
        var fields = { kind: armed.kind, x_pct: p.x.toFixed(3), y_pct: p.y.toFixed(3) };
        if (armed.kind === 'resource') { fields.resource_type = armed.resource_type; }
        else { fields.event_id = armed.event_id; }
        post(DATA.urls.markerStore, 'POST', fields, function (res) {
            if (res.ok && res.body && res.body.marker) {
                markers.push(res.body.marker);
                renderMarker(res.body.marker);
                selectMarker(res.body.marker);
                refreshCount();
            } else if (res.body && res.body.error) {
                alert(res.body.error);
            }
            disarm();
        });
    });

    /* Arrastrar el pin (mueve el peligro) o el chip (mueve la etiqueta); clic = seleccionar */
    function attachDrag(el, m, isChip) {
        var start = null, moved = false;
        el.addEventListener('pointerdown', function (e) {
            e.stopPropagation();
            start = { x: e.clientX, y: e.clientY };
            moved = false;
            el.setPointerCapture(e.pointerId);
        });
        el.addEventListener('pointermove', function (e) {
            if (!start) { return; }
            if (Math.abs(e.clientX - start.x) > 3 || Math.abs(e.clientY - start.y) > 3) { moved = true; }
            if (!moved) { return; }
            var p = pct(e);
            el.style.left = p.x + '%'; el.style.top = p.y + '%';
            if (isChip) {
                m.label_x = p.x; m.label_y = p.y;
                if (m._line) { m._line.setAttribute('x2', p.x); m._line.setAttribute('y2', p.y); }
            } else {
                m.x_pct = p.x; m.y_pct = p.y;
                updateLine(m);
            }
        });
        el.addEventListener('pointerup', function (e) {
            if (start) { try { el.releasePointerCapture(e.pointerId); } catch (er) {} }
            start = null;
            if (moved) { saveMarker(m); }
            else { selectMarker(m); }
        });
    }

    function selectMarker(m) {
        selected = m;
        canvas.querySelectorAll('.rm-pin, .rm-chip').forEach(function (p) { p.classList.remove('sel'); });
        if (m._pin) { m._pin.classList.add('sel'); }
        if (m._chip) { m._chip.classList.add('sel'); }
        var body = document.getElementById('rm-props-body');
        var empty = document.getElementById('rm-props-empty');
        if (empty) { empty.style.display = 'none'; }
        if (body) { body.style.display = ''; }
        var name = document.getElementById('rm-sel-name');
        if (name) { name.innerHTML = iconFor(m.icon) + '<span>' + escapeHtml(m.label || '') + '</span>'; }
    }

    function saveMarker(m) {
        var url = DATA.urls.markerItem.replace('__M__', m.id);
        var fields = {
            kind: m.kind, x_pct: (+m.x_pct).toFixed(3), y_pct: (+m.y_pct).toFixed(3),
            reference_text: m.reference_text || '',
            label_x_pct: (m.label_x !== null && m.label_x !== undefined) ? (+m.label_x).toFixed(3) : '',
            label_y_pct: (m.label_y !== null && m.label_y !== undefined) ? (+m.label_y).toFixed(3) : ''
        };
        if (m.kind === 'resource') { fields.resource_type = m.resource_type; }
        else { fields.event_id = m.event_id; }
        post(url, 'PUT', fields, function (res) {
            if (res.ok && res.body && res.body.marker) {
                var pin = m._pin, chip = m._chip, line = m._line;
                Object.assign(m, res.body.marker);
                m._pin = pin; m._chip = chip; m._line = line;
            }
        });
    }

    /* Panel de propiedades: reubicar etiqueta (auto) + quitar */
    (function () {
        var reset = document.getElementById('rm-lbl-reset');
        if (reset) {
            reset.addEventListener('click', function () {
                if (!selected) { return; }
                selected.label_x = null; selected.label_y = null;
                updateLine(selected);
                saveMarker(selected);
            });
        }
        var del = document.getElementById('rm-del');
        if (del) {
            del.addEventListener('click', function () {
                if (!selected) { return; }
                var m = selected, url = DATA.urls.markerItem.replace('__M__', m.id);
                post(url, 'DELETE', {}, function () {});
                if (m._pin) { m._pin.remove(); }
                if (m._chip) { m._chip.remove(); }
                if (m._line) { m._line.remove(); }
                markers = markers.filter(function (x) { return x.id !== m.id; });
                selected = null;
                document.getElementById('rm-props-body').style.display = 'none';
                document.getElementById('rm-props-empty').style.display = '';
                refreshCount();
            });
        }

        /* Ajuste fino (nudge): mueve el pin +-0.5% y persiste por el MISMO
           camino que un arrastre (saveMarker). Solo POSICION (x_pct/y_pct). */
        var nudgePad = document.getElementById('rm-nudge');
        if (nudgePad) {
            var NUDGE = 0.5;
            var VEC = { up: [0, -NUDGE], down: [0, NUDGE], left: [-NUDGE, 0], right: [NUDGE, 0] };
            var nudgeTimer = null;
            var scheduleNudgeSave = function (m) {
                if (nudgeTimer) { clearTimeout(nudgeTimer); }
                nudgeTimer = setTimeout(function () { nudgeTimer = null; saveMarker(m); }, 350);
            };
            nudgePad.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-nudge]');
                if (!btn || !selected) { return; }
                var v = VEC[btn.getAttribute('data-nudge')];
                if (!v) { return; }
                var m = selected;
                m.x_pct = clamp((+m.x_pct) + v[0], 0, 100);
                m.y_pct = clamp((+m.y_pct) + v[1], 0, 100);
                if (m._pin) { m._pin.style.left = m.x_pct + '%'; m._pin.style.top = m.y_pct + '%'; }
                updateLine(m);
                scheduleNudgeSave(m);
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

    /* ---- Zoom del lienzo (escala uniforme; x_pct/y_pct NO cambian) ----
       Se escala el CONTENEDOR completo (.rm-ed-canvas: imagen + capa de pines)
       con la MISMA transform, así que ambos comparten el espacio de coordenadas.
       pct(e) usa img.getBoundingClientRect(), que refleja la escala y el scroll:
       la fracción de un punto sobre la foto es idéntica a cualquier zoom. Un
       "sizer" con tamaño real (bw*z x bh*z) provee el area de scroll. */
    (function () {
        var wrap = document.getElementById('rm-canvas-wrap');
        var sizer = document.getElementById('rm-canvas-sizer');
        if (!wrap || !sizer) { return; }
        var zoom = 1, MINZ = 1, MAXZ = 4, STEP = 0.5;
        var lvl = document.getElementById('rm-zoom-lvl');
        function apply(z) {
            z = clamp(Math.round(z * 2) / 2, MINZ, MAXZ); // pasos de 0.5
            // Medir el tamaño base (escala 1) con transform y sizer neutros.
            canvas.style.transform = 'none';
            sizer.style.width = ''; sizer.style.height = '';
            var r = canvas.getBoundingClientRect();
            var bw = r.width, bh = r.height;
            if (z <= 1.001) {
                zoom = 1;
                canvas.style.transform = '';
                canvas.classList.remove('is-zoomed');
            } else {
                zoom = z;
                canvas.style.transform = 'scale(' + z + ')';
                sizer.style.width = (bw * z) + 'px';
                sizer.style.height = (bh * z) + 'px';
                canvas.classList.add('is-zoomed');
            }
            if (lvl) { lvl.textContent = Math.round(zoom * 100) + '%'; }
        }
        var zin = document.getElementById('rm-zoom-in');
        var zout = document.getElementById('rm-zoom-out');
        if (zin) { zin.addEventListener('click', function () { apply(zoom + STEP); }); }
        if (zout) { zout.addEventListener('click', function () { apply(zoom - STEP); }); }
        if (lvl) { lvl.addEventListener('click', function () { apply(1); }); }
        // Reajustar el area de scroll si cambia el tamaño/orientación.
        window.addEventListener('resize', function () { if (zoom > 1) { apply(zoom); } });
    })();

    renderAll();
})();
</script>
@endsection
