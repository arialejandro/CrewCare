{{-- ============================================================================================
     TECH SCOUT — UNA SOLA PANTALLA. La misma vista para crear y para trabajar.

     🪤 Antes eran dos: una pantalla mínima pedía la locación y luego aparecía el panel. El owner
     lo dijo sin rodeos —«son 2 pantallas nuevamente»— y tenía razón: partir la captura obliga a
     decidir qué es "lo mínimo" antes de dejar trabajar, y en campo eso es fricción pura.
     Lo único que no está hasta guardar son las NOTAS, porque una nota necesita un documento al
     que pertenecer.
============================================================================================ --}}
@extends('layouts.app')
@section('title', ($scout->exists ? $scout->location_name : 'Nuevo Tech Scout') . ' · Tech Scout')

@push('styles')
{{-- 🪤 Sin esto, las clases btn-crew-* no existen y los botones caen al `.btn` pelado de
     Bootstrap: texto oscuro sin fondo sobre el tema oscuro → INVISIBLES. Pasó el 2026-09-16. --}}
@include('componentes._crew-list-styles')
<style>
    /* 🪤 `align-items:center` + `flex:1 1 260px` en el main estiraban esta banda hasta ocupar media
       pantalla para tres líneas de texto. Va compacta: alto por contenido y sin crecer. */
    .ts-band{display:flex;align-items:center;gap:1rem;flex-wrap:nowrap;padding:.85rem 1.1rem;margin-bottom:1.25rem}
    .ts-band__ico{width:38px;height:38px;flex:none;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;
        background:color-mix(in srgb,var(--brand-primary) 14%,transparent);border:1px solid color-mix(in srgb,var(--brand-primary) 30%,transparent);color:var(--brand-primary)}
    .ts-band__ico .cc-ico{width:18px;height:18px}
    .ts-band__main{flex:1 1 auto;min-width:0}
    .ts-band__lbl{font-size:.6rem;letter-spacing:.18em;text-transform:uppercase;color:var(--text-muted);font-weight:700}
    .ts-band__val{font-family:'Poppins',sans-serif;font-weight:800;font-size:clamp(1rem,1.7vw,1.25rem);color:var(--text);line-height:1.15;margin:.05rem 0 0;overflow-wrap:anywhere}
    .ts-band__sub{font-size:.78rem;color:var(--text-muted);margin:.1rem 0 0;overflow-wrap:anywhere}
    .ts-band__stats{display:flex;gap:1.4rem;flex:none}
    .ts-band__stat{text-align:center}
    .ts-band__stat .n{display:block;font-family:'Poppins',sans-serif;font-weight:800;font-size:1.3rem;color:var(--text);line-height:1}
    .ts-band__stat .k{font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);font-weight:700}

    .ts-row{display:flex;gap:.5rem;margin-bottom:.5rem;align-items:flex-start}
    .ts-row .it{flex:0 0 32%}
    .ts-row .de{flex:1 1 auto}

    /* Rejilla de lo capturado: de un vistazo se ve el scouting entero y quién anotó qué.
       Las fotos van ENTERAS (alto automático): ni recortes ni franjas — lo aprendimos caro. */
    .ts-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:1rem;align-items:start}
    .ts-cell{border:1px solid var(--stroke);border-radius:12px;overflow:hidden;background:var(--glass-2);display:flex;flex-direction:column}
    .ts-cell img{width:100%;height:auto;display:block}
    .ts-cell__bd{padding:.7rem .8rem;display:flex;flex-direction:column;gap:.4rem}
    .ts-cell__tag{align-self:flex-start;font-size:.6rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--brand-primary);
        background:color-mix(in srgb,var(--brand-primary) 12%,transparent);border:1px solid color-mix(in srgb,var(--brand-primary) 30%,transparent);border-radius:999px;padding:.15rem .5rem}
    .ts-cell__tx{font-size:.86rem;line-height:1.5;color:var(--text);white-space:pre-wrap;margin:0;overflow-wrap:anywhere}
    /* Quién y cuándo, discreto y DEBAJO. En la app SÍ se ve el autor —para que entre ustedes
       sepan quién reportó qué—; en el PDF que va a arte, no. */
    .ts-cell__meta{font-size:.68rem;color:var(--text-muted);display:flex;gap:.55rem;flex-wrap:wrap;align-items:center}
    .ts-cell__meta .ed{font-style:italic}

    .ts-empty{text-align:center;padding:2.5rem 1rem;color:var(--text-muted)}
    /* Editar en línea: discreto hasta que se abre, para que la rejilla siga siendo de consulta. */
    .ts-edit > summary{font-size:.7rem;color:var(--text-muted);cursor:pointer;list-style:none;margin-top:.15rem}
    .ts-edit > summary::-webkit-details-marker{display:none}
    .ts-edit > summary:hover{color:var(--brand-primary)}
    .ts-edit[open] > summary{color:var(--brand-primary)}
</style>
@endpush

@section('content')
@php
    $nuevo = ! $scout->exists;
    // Siempre una fila en blanco al final para poder seguir escribiendo sin botones.
    $viabRows = $nuevo ? [] : $scout->rows('viability_checklist'); $viabRows[] = ['item' => '', 'detail' => ''];
    $agrRows  = $nuevo ? [] : $scout->rows('agreements');          $agrRows[]  = ['item' => '', 'detail' => ''];
    $notas    = $nuevo ? collect() : $scout->notes;
@endphp

<div class="container-fluid px-3 px-md-4 py-4" style="max-width:1100px">

    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <span style="font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700">Tech Scout</span>
        <div class="d-flex gap-2">
            @unless ($nuevo)
                <a href="{{ route('techscout.document', $scout->id) }}" class="btn btn-crew-accent d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'file-text', 'label' => null])
                    <span>Ver documento</span>
                </a>
            @endunless
            <a href="{{ route('techscout.index') }}" class="btn btn-crew-soft">Volver</a>
        </div>
    </div>

    <div class="card ts-band">
        <span class="ts-band__ico">@include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico', 'label' => null])</span>
        <div class="ts-band__main">
            <span class="ts-band__lbl">Locación</span>
            <h1 class="ts-band__val">{{ $nuevo ? 'Nuevo Tech Scout' : $scout->location_name }}</h1>
            <p class="ts-band__sub">{{ $nuevo ? 'Llena los datos y guarda para empezar a capturar notas.' : ($scout->location_address ?: 'Sin dirección capturada') }}</p>
        </div>
        @unless ($nuevo)
            <div class="ts-band__stats">
                <div class="ts-band__stat">
                    <span class="n">{{ $notas->count() }}</span>
                    <span class="k">{{ $notas->count() === 1 ? 'nota' : 'notas' }}</span>
                </div>
            </div>
        @endunless
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    {{-- ══ PANELES ══ Plegables y arrancando contraídos (salvo al crear, donde hay que llenarlos):
         lo que no se necesita no estorba, y cada panel muestra su chip de estado.
         Formulario APARTE del de notas a propósito: esto es cabecera compartida (cambia poco);
         las notas se AÑADEN. Si fueran el mismo, guardar una nota arrastraría toda la cabecera y
         dos personas capturando a la vez se pisarían — justo lo que este módulo evita. --}}
    <form action="{{ $nuevo ? route('techscout.store') : route('techscout.update', $scout->id) }}"
          method="POST" enctype="multipart/form-data"
          @if(! $nuevo) data-cc-sections="collapsed" @else data-cc-sections @endif class="mb-4">
        @csrf
        @unless ($nuevo) @method('PUT') @endunless

        <div class="card p-3 p-md-4 mb-3">
            <h2 class="h6 mb-3" style="font-family:'Poppins',sans-serif;font-weight:700">General</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="location_name">Locación <span class="text-danger">*</span></label>
                    <input type="text" name="location_name" id="location_name" class="form-control" required
                           maxlength="255" value="{{ old('location_name', $scout->location_name) }}"
                           placeholder="Como la conocen en producción">
                </div>
                <div class="col-md-6">
                    @include('componentes._geo-capture', [
                        'mode'         => 'address',
                        'label'        => 'Dirección',
                        'addressValue' => old('location_address', $scout->location_address),
                        'latValue'     => old('latitude', $scout->latitude),
                        'lngValue'     => old('longitude', $scout->longitude),
                        'auto'         => $nuevo,
                    ])
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="hero_image">Imagen de portada</label>
                    @if ($scout->hero_image_path)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img src="{{ $scout->hero_image_path }}" alt="Portada actual" class="rounded border"
                                 style="height:54px;width:auto;max-width:150px">
                            <small class="cc-muted">Portada actual — sube otra para <strong>reemplazarla</strong>.</small>
                        </div>
                    @endif
                    <input type="file" name="hero_image" id="hero_image" class="form-control"
                           accept="image/*,.heic,.heif" data-cc-photo>
                    {{-- La banda del documento es ancha y baja: la foto se ve como una FRANJA. Por eso
                         la portada se elige a mano en vez de tomar la primera nota. --}}
                    <small class="cc-muted d-block mt-1">Abre el documento. Usa un <strong>plano general</strong>: la banda es ancha y baja, y un detalle se recorta.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="loc_setting">Tipo de locación</label>
                    <select name="loc_setting" id="loc_setting" class="form-select">
                        <option value="">—</option>
                        {{-- Lenguaje universal de producción: Int. / Ext. / Int.-Ext. "Mixto" era
                             invención nuestra; en set nadie lo dice así. --}}
                        @foreach(['Interior', 'Exterior', 'Int./Ext.'] as $ls)
                            <option value="{{ $ls }}" @selected(old('loc_setting', $scout->loc_setting) === $ls)>{{ $ls }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="shoot_time">Horario</label>
                    <input type="text" name="shoot_time" id="shoot_time" class="form-control" maxlength="60"
                           value="{{ old('shoot_time', $scout->shoot_time) }}" placeholder="Ej. «Día» / «Noche» / «07:00–19:00»">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="date_prep">Prep</label>
                    <input type="date" name="date_prep" id="date_prep" class="form-control"
                           value="{{ old('date_prep', optional($scout->date_prep)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="date_shoot">Rodaje</label>
                    <input type="date" name="date_shoot" id="date_shoot" class="form-control"
                           value="{{ old('date_shoot', optional($scout->date_shoot)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="date_shoot_end">Último día</label>
                    <input type="date" name="date_shoot_end" id="date_shoot_end" class="form-control"
                           value="{{ old('date_shoot_end', optional($scout->date_shoot_end)->format('Y-m-d')) }}">
                    <small class="cc-muted d-block mt-1">Sólo si ocupa <strong>más de un día</strong>.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="date_wrap">Wrap</label>
                    <input type="date" name="date_wrap" id="date_wrap" class="form-control"
                           value="{{ old('date_wrap', optional($scout->date_wrap)->format('Y-m-d')) }}">
                </div>
            </div>
        </div>

        <div class="card p-3 p-md-4 mb-3">
            <h2 class="h6 mb-1" style="font-family:'Poppins',sans-serif;font-weight:700">Viabilidad</h2>
            <p class="cc-muted mb-3" style="font-size:.82rem">Permisos y solicitudes especiales para poder filmar aquí.</p>
            @foreach ($viabRows as $r)
                <div class="ts-row">
                    <input type="text" name="viability[{{ $loop->index }}][item]" class="form-control it"
                           maxlength="255" value="{{ $r['item'] }}" placeholder="Permiso / gestión">
                    <input type="text" name="viability[{{ $loop->index }}][detail]" class="form-control de"
                           maxlength="2000" value="{{ $r['detail'] }}" placeholder="Detalle, quién lo tramita, estado…">
                </div>
            @endforeach
        </div>

        <div class="card p-3 p-md-4 mb-3">
            <h2 class="h6 mb-1" style="font-family:'Poppins',sans-serif;font-weight:700">Acuerdos</h2>
            <p class="cc-muted mb-3" style="font-size:.82rem">Lo pactado entre departamentos y con la locación.</p>
            @foreach ($agrRows as $r)
                <div class="ts-row">
                    <input type="text" name="agreements[{{ $loop->index }}][item]" class="form-control it"
                           maxlength="255" value="{{ $r['item'] }}" placeholder="Con quién / qué">
                    <input type="text" name="agreements[{{ $loop->index }}][detail]" class="form-control de"
                           maxlength="2000" value="{{ $r['detail'] }}" placeholder="Qué se acordó">
                </div>
            @endforeach
        </div>

        {{-- 🪤 AL CREAR, la nota va DENTRO de este mismo formulario. El owner lo marcó: «se llena
             mucha información antes de poder emitir notas». En campo se llega, se ve algo que hay
             que resolver y se anota; los permisos y las fechas se rellenan después, sentado.
             Escribir la nota guarda también el scouting con lo que haya — como un borrador, esa
             primera capa ya no se pierde. Lo único obligatorio es la locación. --}}
        @if ($nuevo)
            <div class="card p-3 p-md-4 mb-3">
                <h2 class="h6 mb-1" style="font-family:'Poppins',sans-serif;font-weight:700">Primera nota</h2>
                <p class="cc-muted mb-3" style="font-size:.82rem">Opcional. Si anotas algo aquí, se guarda junto con el scouting.</p>
                @include('techscout._note-fields', ['lastLabel' => null])
            </div>
        @endif

        <button type="submit" class="btn btn-crew-accent">{{ $nuevo ? 'Crear Tech Scout' : 'Guardar datos' }}</button>
    </form>
    @include('componentes._collapsible-sections')

    @unless ($nuevo)
        <div class="card p-3 p-md-4 mb-4">
            <h2 class="h6 mb-3" style="font-family:'Poppins',sans-serif;font-weight:700">Agregar nota</h2>
            <form action="{{ route('techscout.note.store', $scout->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('techscout._note-fields', ['lastLabel' => $lastLabel, 'submitLabel' => 'Agregar nota'])
            </form>
        </div>
    @endunless

    @unless ($nuevo)
        @if (! $notas->count())
            <div class="card ts-empty">Todavía no hay notas. Agrega la primera arriba.</div>
        @else
            <div class="ts-grid">
                @foreach ($notas as $n)
                    <div class="ts-cell">
                        @if ($n->photo_path)<img src="{{ $n->photo_path }}" alt="" loading="lazy">@endif
                        <div class="ts-cell__bd">
                            @if ($n->story_label)<span class="ts-cell__tag">{{ $n->story_label }}</span>@endif
                            @if ($n->note)<p class="ts-cell__tx">{{ $n->note }}</p>@endif
                            <div class="ts-cell__meta">
                                <span>{{ $n->created_at->format('d/m H:i') }}</span>
                                <span>{{ $n->authorName() }}</span>
                                @if ($n->wasEdited())<span class="ed">editada</span>@endif
                            </div>

                            {{-- EDITAR EN LÍNEA. El owner lo pidió para no tener que volver a
                                 fotografiar lo mismo y saturar el documento de información
                                 repetida. Se usa <details>, que es HTML nativo: no necesita
                                 JavaScript, no rompe la CSP y funciona sin red.
                                 Sólo el AUTOR: cada quien corrige lo suyo. Y editar deja marca —
                                 es lo que sostiene el "si no está en las notas, no se pidió". --}}
                            @if ((int) $n->created_by_id === (int) auth()->id())
                                <details class="ts-edit">
                                    <summary>Editar</summary>
                                    <form action="{{ route('techscout.note.update', [$scout->id, $n->id]) }}" method="POST" class="mt-2">
                                        @csrf
                                        @method('PUT')
                                        <textarea name="note" class="form-control form-control-sm" rows="3"
                                                  maxlength="2000">{{ $n->note }}</textarea>
                                        <input type="text" name="story_label" class="form-control form-control-sm mt-1"
                                               maxlength="120" value="{{ $n->story_label }}" placeholder="Nombre en la historia">
                                        <button type="submit" class="btn btn-crew-accent btn-sm mt-2">Guardar</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endunless

</div>
@endsection
