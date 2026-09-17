{{-- ============================================================================================
     TECH SCOUT — UNA SOLA PANTALLA. La misma vista para crear y para trabajar.

     🪤 Antes eran dos: una pantalla mínima pedía la locación y luego aparecía el panel. El owner
     lo dijo sin rodeos —«son 2 pantallas nuevamente»— y tenía razón: partir la captura obliga a
     decidir qué es "lo mínimo" antes de dejar trabajar, y en campo eso es fricción pura.
     Lo único que no está hasta guardar son las NOTAS, porque una nota necesita un documento al
     que pertenecer.

     ── EL ORDEN DE LA PANTALLA ES UNA DECISIÓN ────────────────────────────────────────────────
     En un scouting ya creado, AGREGAR NOTA va ARRIBA de los paneles. El scouter llega, ve algo y
     lo anota: eso es el trabajo. Los permisos y las fechas se llenan después, sentado. Tenerlos
     primero obligaba a pasar tres tarjetas con el pulgar antes de poder capturar — y en un móvil
     eso es toda la pantalla. Los datos generales quedan debajo, plegados.

     ── MÓVIL ─────────────────────────────────────────────────────────────────────────────────
     El owner trabaja en iPad, pero los scouters traen SÓLO el teléfono. Esta pantalla se diseña
     para el teléfono: cintillo de una línea, paneles plegados, filas que se apilan y botones a
     todo el ancho para que se aticen con el pulgar.
============================================================================================ --}}
@extends('layouts.app')
@section('title', ($scout->exists ? $scout->location_name : 'Nuevo Tech Scout') . ' · Tech Scout')

@push('styles')
{{-- 🪤 Sin esto, las clases btn-crew-* no existen y los botones caen al `.btn` pelado de
     Bootstrap: texto oscuro sin fondo sobre el tema oscuro → INVISIBLES. Pasó el 2026-09-16. --}}
@include('componentes._crew-list-styles')
<style>
    /* ── CINTILLO ───────────────────────────────────────────────────────────────────────────
       Dos versiones anteriores de esta cabecera ocupaban media pantalla para decir el nombre de
       la locación. El owner: «sigue siendo muy grande, hazlo más compacto, incluso un cintillo
       funciona, no debe ser tan protagonista». Una línea, sin subtítulo y sin caja de estadísticas
       — la dirección ya está en el panel General, que es donde se consulta. En un teléfono esto
       es la diferencia entre ver una nota al abrir y no ver ninguna. */
    .ts-strip{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;
        padding:.5rem .75rem;margin-bottom:1rem;border:1px solid var(--stroke);border-radius:12px;background:var(--glass-2)}
    .ts-strip__ico{width:26px;height:26px;flex:none;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;
        background:color-mix(in srgb,var(--brand-primary) 14%,transparent);color:var(--brand-primary)}
    .ts-strip__ico .cc-ico{width:14px;height:14px}
    .ts-strip__main{flex:1 1 auto;min-width:0;display:flex;align-items:baseline;gap:.5rem;flex-wrap:wrap}
    .ts-strip__eyebrow{font-size:.58rem;letter-spacing:.18em;text-transform:uppercase;color:var(--brand-primary);font-weight:700;flex:none}
    .ts-strip__name{font-family:'Poppins',sans-serif;font-weight:700;font-size:.95rem;color:var(--text);line-height:1.2;margin:0;overflow-wrap:anywhere}
    .ts-strip__chip{flex:none;font-size:.7rem;font-weight:700;color:var(--text-muted);background:var(--glass-2);
        border:1px solid var(--stroke);border-radius:999px;padding:.12rem .55rem;white-space:nowrap}
    .ts-strip__chip b{color:var(--text)}
    .ts-strip__act{display:flex;gap:.4rem;flex:none}
    .ts-strip__act .btn{--bs-btn-padding-y:.25rem;--bs-btn-padding-x:.6rem;font-size:.78rem}

    /* Señal de vida del refresco. Invisible hasta que entra algo del otro scouter: un aviso
       permanente en pantalla se vuelve parte del decorado y deja de decir nada. */
    .ts-live{flex:none;font-size:.68rem;font-weight:700;color:var(--ok);opacity:0;transition:opacity .25s}
    .ts-live.is-on{opacity:1}

    /* Filas de viabilidad / acuerdos. En teléfono se APILAN: dos campos al 32%/68% de 360 px son
       dos ranuras donde no cabe ni "Permiso de filmación". */
    .ts-row{display:flex;gap:.5rem;margin-bottom:.5rem;align-items:flex-start}
    .ts-row .it{flex:0 0 32%}
    .ts-row .de{flex:1 1 auto}
    @media (max-width:575.98px){
        .ts-row{flex-direction:column;gap:.35rem;margin-bottom:.9rem}
        .ts-row .it{flex:1 1 auto;width:100%}
    }

    /* Botón principal a todo el ancho en teléfono: se atiza con el pulgar sin apuntar. */
    .ts-submit{width:100%}
    @media (min-width:768px){ .ts-submit{width:auto} }

    /* Rejilla de lo capturado: de un vistazo se ve el scouting entero y quién anotó qué.
       Las fotos van ENTERAS (alto automático): ni recortes ni franjas — lo aprendimos caro.
       `min(230px,100%)` y no 230px pelado: en un teléfono de 320 px, la columna mínima de 230
       más los márgenes desbordaba y aparecía barra horizontal. */
    .ts-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(230px,100%),1fr));gap:1rem;align-items:start}
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

<div class="container-fluid px-3 px-md-4 py-3 py-md-4" style="max-width:1100px"
     @unless ($nuevo) data-ts-refresh="{{ route('techscout.notes', $scout->id) }}" @endunless>

    <div class="ts-strip">
        <span class="ts-strip__ico">@include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico', 'label' => null])</span>
        <div class="ts-strip__main">
            <span class="ts-strip__eyebrow">Tech Scout</span>
            <h1 class="ts-strip__name">{{ $nuevo ? 'Nuevo' : $scout->location_name }}</h1>
        </div>
        @unless ($nuevo)
            <span class="ts-strip__chip"><b data-ts-count-slot>{{ $notas->count() }}</b> <span data-ts-count-label>{{ $notas->count() === 1 ? 'nota' : 'notas' }}</span></span>
            <span class="ts-live" data-ts-live aria-live="polite"></span>
        @endunless
        <div class="ts-strip__act">
            @unless ($nuevo)
                <a href="{{ route('techscout.document', $scout->id) }}" class="btn btn-crew-accent d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'file-text', 'label' => null])
                    <span>Documento</span>
                </a>
            @endunless
            <a href="{{ route('techscout.index') }}" class="btn btn-crew-soft">Volver</a>
        </div>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    {{-- ══ AGREGAR NOTA ══ Primero, y no por costumbre: es LA unidad de trabajo del módulo. En
         teléfono, todo lo que vaya antes de esto son centímetros de pulgar entre llegar a la
         locación y poder anotar lo que se ve. Formulario APARTE del panel a propósito: esto se
         AÑADE; el panel es cabecera compartida. Si fueran el mismo, guardar una nota arrastraría
         toda la cabecera y dos personas capturando a la vez se pisarían. --}}
    @unless ($nuevo)
        <div class="card p-3 p-md-4 mb-3">
            <h2 class="h6 mb-3" style="font-family:'Poppins',sans-serif;font-weight:700">Agregar nota</h2>
            <form action="{{ route('techscout.note.store', $scout->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('techscout._note-fields', ['lastLabel' => $lastLabel, 'submitLabel' => 'Agregar nota'])
            </form>
        </div>
    @endunless

    {{-- ══ PANELES ══ Plegables (tarjetas con .card-header + .card-body, que es lo que engancha
         _collapsible-sections) y arrancando contraídos en un scouting ya creado: lo que no se
         necesita no estorba, y en un teléfono "no estorbar" es literalmente caber en la pantalla.
         Al CREAR arrancan abiertos, que es cuando hay que llenarlos. --}}
    <form action="{{ $nuevo ? route('techscout.store') : route('techscout.update', $scout->id) }}"
          method="POST" enctype="multipart/form-data"
          @if(! $nuevo) data-cc-sections="closed" @else data-cc-sections @endif class="mb-4">
        @csrf
        @unless ($nuevo) @method('PUT') @endunless

        <div class="card mb-3">
            <h2 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">General</h2>
            <div class="card-body p-3 p-md-4">
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
                {{-- Las cuatro fechas de dos en dos en teléfono: apiladas ocupaban una pantalla
                     entera para cuatro campos que casi siempre van vacíos al principio. --}}
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold" for="date_prep">Prep</label>
                    <input type="date" name="date_prep" id="date_prep" class="form-control"
                           value="{{ old('date_prep', optional($scout->date_prep)->format('Y-m-d')) }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold" for="date_shoot">Rodaje</label>
                    <input type="date" name="date_shoot" id="date_shoot" class="form-control"
                           value="{{ old('date_shoot', optional($scout->date_shoot)->format('Y-m-d')) }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold" for="date_shoot_end">Último día</label>
                    <input type="date" name="date_shoot_end" id="date_shoot_end" class="form-control"
                           value="{{ old('date_shoot_end', optional($scout->date_shoot_end)->format('Y-m-d')) }}">
                    <small class="cc-muted d-block mt-1">Sólo si ocupa <strong>más de un día</strong>.</small>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold" for="date_wrap">Wrap</label>
                    <input type="date" name="date_wrap" id="date_wrap" class="form-control"
                           value="{{ old('date_wrap', optional($scout->date_wrap)->format('Y-m-d')) }}">
                </div>
            </div>
            </div>
        </div>

        <div class="card mb-3">
            <h2 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">Viabilidad</h2>
            <div class="card-body p-3 p-md-4">
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
        </div>

        <div class="card mb-3">
            <h2 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">Acuerdos</h2>
            <div class="card-body p-3 p-md-4">
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
        </div>

        {{-- 🪤 AL CREAR, la nota va DENTRO de este mismo formulario. El owner lo marcó: «se llena
             mucha información antes de poder emitir notas». En campo se llega, se ve algo que hay
             que resolver y se anota; los permisos y las fechas se rellenan después, sentado.
             Escribir la nota guarda también el scouting con lo que haya — como un borrador, esa
             primera capa ya no se pierde. Lo único obligatorio es la locación. --}}
        @if ($nuevo)
            <div class="card mb-3">
                <h2 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">Primera nota</h2>
                <div class="card-body p-3 p-md-4">
                    <p class="cc-muted mb-3" style="font-size:.82rem">Opcional. Si anotas algo aquí, se guarda junto con el scouting.</p>
                    @include('techscout._note-fields', ['lastLabel' => null])
                </div>
            </div>
        @endif

        <button type="submit" class="btn btn-crew-accent ts-submit">{{ $nuevo ? 'Crear Tech Scout' : 'Guardar datos' }}</button>
    </form>
    @include('componentes._collapsible-sections')

    @unless ($nuevo)
        {{-- La rejilla vive en su propia plantilla porque el refresco periódico la pide SOLA
             (GET /tech-scout/{id}/notas) y tiene que salir idéntica. --}}
        @include('techscout._notes-grid', ['scout' => $scout, 'notas' => $notas])
    @endunless

</div>
@endsection

{{-- $scout->exists y no $nuevo: esa variable nace dentro de @section y aquí ya estamos fuera. --}}
@if ($scout->exists)
@push('scripts')
{{-- Refresco periódico: dos scouters en la misma locación ven aparecer lo del otro sin recargar.
     Archivo externo (no <script> inline) para no depender del nonce de la CSP. --}}
<script src="{{ asset('js/cc-techscout-refresh.js') }}?v=1"></script>
@endpush
@endif
