@extends('layouts.app')
@section('title', $scout->location_name . ' · Tech Scout')

@push('styles')
{{-- 🪤 Sin esto, las clases btn-crew-* no existen y los botones caen al `.btn` pelado de
     Bootstrap: texto oscuro sin fondo sobre el tema oscuro → INVISIBLES. Pasó el 2026-09-16. --}}
@include('componentes._crew-list-styles')
<style>
    /* ── CINTILLO DE LOCACIÓN — mismo lenguaje que la banda del Scouting H&S. Es lo primero que se
       mira al abrir y lo que confirma que estás capturando en la locación correcta. ── */
    .ts-band{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;padding:1rem 1.15rem;margin-bottom:1.25rem}
    .ts-band__ico{width:42px;height:42px;flex:none;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;
        background:color-mix(in srgb,var(--brand-primary) 14%,transparent);border:1px solid color-mix(in srgb,var(--brand-primary) 30%,transparent);color:var(--brand-primary)}
    .ts-band__ico .cc-ico{width:20px;height:20px}
    .ts-band__main{flex:1 1 260px;min-width:0}
    .ts-band__lbl{font-size:.62rem;letter-spacing:.18em;text-transform:uppercase;color:var(--text-muted);font-weight:700}
    .ts-band__val{font-family:'Poppins',sans-serif;font-weight:800;font-size:clamp(1.05rem,2vw,1.4rem);color:var(--text);line-height:1.15;margin:.1rem 0 .15rem;overflow-wrap:anywhere}
    .ts-band__sub{font-size:.82rem;color:var(--text-muted);margin:0;overflow-wrap:anywhere}
    .ts-band__stats{display:flex;gap:1.4rem;flex:none}
    .ts-band__stat{text-align:center}
    .ts-band__stat .n{display:block;font-family:'Poppins',sans-serif;font-weight:800;font-size:1.3rem;color:var(--text);line-height:1}
    .ts-band__stat .k{font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);font-weight:700}

    /* Filas de viabilidad / acuerdos: se auto-agrega una en blanco al escribir en la última. */
    .ts-row{display:flex;gap:.5rem;margin-bottom:.5rem;align-items:flex-start}
    .ts-row .it{flex:0 0 32%}
    .ts-row .de{flex:1 1 auto}

    /* ── REJILLA DE LO YA CAPTURADO ──
       Rejilla y no lista: de un vistazo se ve el recorrido entero y quién anotó qué.
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
</style>
@endpush

@section('content')
@php
    // Siempre una fila en blanco al final para que se pueda seguir escribiendo sin botones.
    $viabRows = $scout->rows('viability_checklist'); $viabRows[] = ['item' => '', 'detail' => ''];
    $agrRows  = $scout->rows('agreements');          $agrRows[]  = ['item' => '', 'detail' => ''];
@endphp

<div class="container-fluid px-3 px-md-4 py-4" style="max-width:1100px">

    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <span style="font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700">Tech Scout</span>
        <div class="d-flex gap-2">
            <a href="{{ route('techscout.document', $scout->id) }}" class="btn btn-crew-accent d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'file-text', 'label' => null])
                <span>Ver documento</span>
            </a>
            <a href="{{ route('techscout.index') }}" class="btn btn-crew-soft">Volver</a>
        </div>
    </div>

    <div class="card ts-band">
        <span class="ts-band__ico">@include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico', 'label' => null])</span>
        <div class="ts-band__main">
            <span class="ts-band__lbl">Locación</span>
            <h1 class="ts-band__val">{{ $scout->location_name }}</h1>
            <p class="ts-band__sub">{{ $scout->location_address ?: 'Sin dirección capturada' }}</p>
        </div>
        <div class="ts-band__stats">
            <div class="ts-band__stat">
                <span class="n">{{ $scout->notes->count() }}</span>
                <span class="k">{{ $scout->notes->count() === 1 ? 'nota' : 'notas' }}</span>
            </div>
        </div>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    {{-- ══ PANELES DEL RECORRIDO ══════════════════════════════════════════════════════════════
         `data-cc-sections="collapsed"` arranca contraído: lo que no se necesita llenar no estorba,
         y cada panel muestra su propio chip de estado. Formulario APARTE del de notas a propósito:
         esto es la cabecera compartida (cambia poco); las notas se AÑADEN. Si fueran el mismo
         formulario, guardar una nota arrastraría toda la cabecera y dos personas capturando a la
         vez se pisarían — justo lo que este módulo evita por diseño. --}}
    <form action="{{ route('techscout.update', $scout->id) }}" method="POST" enctype="multipart/form-data"
          data-cc-sections="collapsed" class="mb-4">
        @csrf
        @method('PUT')

        <div class="card p-3 p-md-4 mb-3">
            <h2 class="h6 mb-3" style="font-family:'Poppins',sans-serif;font-weight:700">General</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="location_name">Locación <span class="text-danger">*</span></label>
                    <input type="text" name="location_name" id="location_name" class="form-control" required
                           maxlength="255" value="{{ old('location_name', $scout->location_name) }}">
                </div>
                <div class="col-md-6">
                    @include('componentes._geo-capture', [
                        'mode'         => 'address',
                        'label'        => 'Dirección',
                        'addressValue' => old('location_address', $scout->location_address),
                        'latValue'     => old('latitude', $scout->latitude),
                        'lngValue'     => old('longitude', $scout->longitude),
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
                         la portada se elige a mano en vez de tomar la primera nota — funciona un plano
                         general de la locación, no un detalle. --}}
                    <small class="cc-muted d-block mt-1">Abre el documento. Usa un <strong>plano general</strong>: la banda es ancha y baja, y un detalle se recorta.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="production_type">Tipo de producción</label>
                    <select name="production_type" id="production_type" class="form-select">
                        <option value="">—</option>
                        @foreach(['TV', 'Película', 'Comercial', 'Game Show', 'Documental', 'Otro'] as $pt)
                            <option value="{{ $pt }}" @selected(old('production_type', $scout->production_type) === $pt)>{{ $pt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="manager_name">Gerente de producción</label>
                    <input type="text" name="manager_name" id="manager_name" class="form-control"
                           maxlength="255" value="{{ old('manager_name', $scout->manager_name) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="loc_setting">Tipo de locación</label>
                    <select name="loc_setting" id="loc_setting" class="form-select">
                        <option value="">—</option>
                        @foreach(['Interior', 'Exterior', 'Mixto'] as $ls)
                            <option value="{{ $ls }}" @selected(old('loc_setting', $scout->loc_setting) === $ls)>{{ $ls }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="shoot_time">Horario</label>
                    <input type="text" name="shoot_time" id="shoot_time" class="form-control" maxlength="60"
                           value="{{ old('shoot_time', $scout->shoot_time) }}" placeholder="Ej. «Día» / «Noche» / «07:00–19:00»">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="date_prep">Prep</label>
                    <input type="date" name="date_prep" id="date_prep" class="form-control"
                           value="{{ old('date_prep', optional($scout->date_prep)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="date_shoot">Rodaje</label>
                    <input type="date" name="date_shoot" id="date_shoot" class="form-control"
                           value="{{ old('date_shoot', optional($scout->date_shoot)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="date_shoot_end">Último día de rodaje</label>
                    <input type="date" name="date_shoot_end" id="date_shoot_end" class="form-control"
                           value="{{ old('date_shoot_end', optional($scout->date_shoot_end)->format('Y-m-d')) }}">
                    <small class="cc-muted d-block mt-1">Sólo si ocupa <strong>más de un día</strong>.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="date_wrap">Wrap</label>
                    <input type="date" name="date_wrap" id="date_wrap" class="form-control"
                           value="{{ old('date_wrap', optional($scout->date_wrap)->format('Y-m-d')) }}">
                </div>
            </div>
        </div>

        {{-- Permisos y solicitudes especiales: lo que hay que gestionar para poder filmar aquí. --}}
        <div class="card p-3 p-md-4 mb-3">
            <h2 class="h6 mb-1" style="font-family:'Poppins',sans-serif;font-weight:700">Viabilidad</h2>
            <p class="cc-muted mb-3" style="font-size:.82rem">Permisos y solicitudes especiales para poder filmar aquí.</p>
            <div class="js-autorows">
                @foreach ($viabRows as $r)
                    <div class="ts-row js-autorow">
                        <input type="text" name="viability[{{ $loop->index }}][item]" class="form-control it"
                               maxlength="255" value="{{ $r['item'] }}" placeholder="Permiso / gestión">
                        <input type="text" name="viability[{{ $loop->index }}][detail]" class="form-control de"
                               maxlength="2000" value="{{ $r['detail'] }}" placeholder="Detalle, quién lo tramita, estado…">
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Lo pactado con otros departamentos y con la locación. Escrito aquí, deja de vivir en
             la memoria de quien estuvo en la junta. --}}
        <div class="card p-3 p-md-4 mb-3">
            <h2 class="h6 mb-1" style="font-family:'Poppins',sans-serif;font-weight:700">Acuerdos</h2>
            <p class="cc-muted mb-3" style="font-size:.82rem">Lo pactado entre departamentos y con la locación.</p>
            <div class="js-autorows">
                @foreach ($agrRows as $r)
                    <div class="ts-row js-autorow">
                        <input type="text" name="agreements[{{ $loop->index }}][item]" class="form-control it"
                               maxlength="255" value="{{ $r['item'] }}" placeholder="Con quién / qué">
                        <input type="text" name="agreements[{{ $loop->index }}][detail]" class="form-control de"
                               maxlength="2000" value="{{ $r['detail'] }}" placeholder="Qué se acordó">
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn btn-crew-accent">Guardar datos del recorrido</button>
    </form>
    @include('componentes._collapsible-sections')

    {{-- ══ NOTAS ══════════════════════════════════════════════════════════════════════════════
         Una a una, y debajo la rejilla de lo ya capturado. --}}
    <div class="card p-3 p-md-4 mb-4">
        <h2 class="h6 mb-3" style="font-family:'Poppins',sans-serif;font-weight:700">Agregar nota</h2>
        <form action="{{ route('techscout.note.store', $scout->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold" for="photo">Foto</label>
                    {{-- SIN `capture`: en set hacen falta las dos opciones, cámara y galería. --}}
                    <input type="file" name="photo" id="photo" class="form-control"
                           accept="image/*,.heic,.heif" data-cc-photo>
                </div>
                <div class="col-md-7">
                    <label class="form-label fw-semibold" for="note">Qué hay que resolver</label>
                    <textarea name="note" id="note" class="form-control" rows="3" maxlength="2000"
                              placeholder="Ej. «Quitar las cortinas de esta ventana»">{{ old('note') }}</textarea>
                </div>
                <div class="col-md-7">
                    <label class="form-label fw-semibold" for="story_label">Nombre en la historia <span class="cc-muted fw-normal">(opcional)</span></label>
                    <input type="text" name="story_label" id="story_label" class="form-control"
                           value="{{ old('story_label', $lastLabel) }}" maxlength="120" placeholder="Ej. «Depa Pablo»">
                    <small class="cc-muted d-block mt-1">Se mantiene para las siguientes. Bórralo al cambiar de espacio.</small>
                </div>
                <div class="col-md-5 d-flex align-items-start">
                    <button type="submit" class="btn btn-crew-accent mt-md-4">Agregar nota</button>
                </div>
            </div>
        </form>
    </div>

    @if (! $scout->notes->count())
        <div class="card ts-empty">Todavía no hay notas. Agrega la primera arriba.</div>
    @else
        <div class="ts-grid">
            @foreach ($scout->notes as $n)
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
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
