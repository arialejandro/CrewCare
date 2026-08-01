@extends('layouts.app')
@section('title', 'SDS / Consumibles SFX')

@feature('sds_sfx')
@push('styles')
<style>
    /* ── Índice glass · lenguaje Cinematic Dark Glass (tokens de _brand-theme) ── */
    .cc-idx-head{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.75rem}
    .cc-idx-headline{display:flex;align-items:center;gap:1rem;min-width:0}
    .cc-idx-icon{width:48px;height:48px;border-radius:14px;flex:none;display:inline-flex;align-items:center;justify-content:center;
        color:var(--danger);background:color-mix(in srgb,var(--danger) 14%,transparent);border:1px solid color-mix(in srgb,var(--danger) 26%,transparent)}
    .cc-idx-icon .cc-ico{width:22px;height:22px}
    .cc-idx-eyebrow{font-size:.66rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700}
    .cc-idx-title{margin:.1rem 0 .1rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:1.5rem;color:var(--text);line-height:1.05}
    .cc-idx-sub{margin:0;color:var(--text-muted);font-size:.86rem}
    .cc-idx-actions{display:flex;gap:.6rem;flex-wrap:wrap}
    .cc-idx-cta{display:inline-flex;align-items:center;gap:.5rem;padding:.62rem 1.05rem;border-radius:var(--radius-sm);text-decoration:none;font-weight:700;font-size:.88rem;background:var(--brand-primary);color:var(--brand-on-primary);border:1px solid var(--brand-primary);box-shadow:0 10px 26px -12px var(--brand-glow);transition:transform .18s var(--ease,cubic-bezier(.16,1,.3,1)),filter .18s}
    .cc-idx-cta:hover{transform:translateY(-1px);color:var(--brand-on-primary);filter:brightness(1.04)}
    .cc-idx-ghost{display:inline-flex;align-items:center;gap:.5rem;padding:.62rem 1.05rem;border-radius:var(--radius-sm);text-decoration:none;font-weight:600;font-size:.88rem;background:var(--glass);color:var(--text);border:1px solid var(--stroke);transition:background .18s,border-color .18s}
    .cc-idx-ghost:hover{background:var(--glass-2);border-color:var(--stroke-2);color:var(--text)}
    .cc-idx-cta .cc-ico,.cc-idx-ghost .cc-ico{width:16px;height:16px}

    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.28rem .58rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}
    .cc-chip-danger{color:var(--danger);background:color-mix(in srgb,var(--danger) 16%,transparent);border-color:color-mix(in srgb,var(--danger) 34%,transparent)}
    .cc-chip-neutral{color:var(--text-muted);background:var(--glass-2);border-color:var(--stroke)}

    /* ── Barra de filtros (cliente) ────────────────────────────────────────────
       Los controles reutilizan .form-control/.form-select de Bootstrap A PROPÓSITO:
       _brand-theme ya les da anillo de foco de marca y altura táctil ≥44px en
       punteros gruesos. Aquí solo se les pone la piel de vidrio. */
    .sds-filters{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem}
    .sds-filters .form-control,.sds-filters .form-select{background:var(--glass);border:1px solid var(--stroke);color:var(--text);border-radius:var(--radius-sm);font-size:.88rem;padding:.55rem .8rem}
    .sds-filters .form-control::placeholder{color:var(--text-muted);opacity:1}
    .sds-filters .form-control:focus,.sds-filters .form-select:focus{background:var(--glass-2);border-color:var(--brand-primary)}
    /* La flecha nativa del .form-select de Bootstrap es un SVG casi negro: invisible
       sobre vidrio oscuro. Se sustituye por un chevron gris neutro que lee en AMBOS temas. */
    .sds-filters .form-select{appearance:none;-webkit-appearance:none;-moz-appearance:none;padding-right:2.1rem;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
        background-repeat:no-repeat;background-position:right .7rem center;background-size:14px 14px}
    .sds-filters .form-select option{background:var(--bg-2);color:var(--text)}
    .sds-f-search{position:relative;flex:1 1 280px;min-width:0}
    .sds-f-search .form-control{padding-left:2.25rem}
    .sds-f-search .sds-f-ico{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none}
    .sds-f-sel{flex:0 1 230px;min-width:0}
    .sds-f-count{font-size:.78rem;color:var(--text-muted);font-weight:600;white-space:nowrap;margin-left:auto}
    .sds-f-count strong{color:var(--text)}
    .sds-f-clear{display:inline-flex;align-items:center;gap:.35rem;padding:.5rem .8rem;border-radius:var(--radius-sm);font-size:.8rem;font-weight:600;
        background:var(--glass);color:var(--text-muted);border:1px solid var(--stroke);cursor:pointer;transition:color .18s,border-color .18s}
    .sds-f-clear:hover{color:var(--text);border-color:var(--stroke-2)}
    .sds-f-clear .cc-ico{width:13px;height:13px}

    /* Tabla dentro de .card (vidrio): fondo transparente + tinta por tokens. */
    .sds-table{--bs-table-bg:transparent;color:var(--text);margin-bottom:0}
    .sds-table thead th{text-transform:uppercase;font-size:.7rem;letter-spacing:.05em;color:var(--text-muted);font-weight:700;border-bottom:1px solid var(--stroke)}
    .sds-table td,.sds-table th{border-color:var(--stroke)}
    .sds-table tbody tr:hover td{background:var(--glass-2)}
    /* El filtro esconde filas con [hidden]. En móvil .cc-stack le pone display:block a
       cada tr, así que se refuerza aquí para que ganar/perder no dependa del orden. */
    .sds-table tr[hidden]{display:none !important}
    .sds-name{font-weight:600;color:var(--text)}
    /* El NOMBRE es el enlace al detalle: es el primer gesto que la gente intenta y en
       móvil es un objetivo táctil grande y gratis. */
    a.sds-name{text-decoration:none;display:inline-block}
    a.sds-name:hover{color:var(--brand-primary);text-decoration:underline}
    .sds-meta{display:flex;flex-wrap:wrap;gap:.15rem .5rem;font-size:.74rem;color:var(--text-muted);margin-top:.2rem}
    .sds-meta span{white-space:nowrap}
    /* El white-space:nowrap que .cc-chip trae de fábrica le FIJA a cada columna un ancho
       mínimo igual al chip más largo. Medido a 1280px: la tabla pedía 1146px dentro de un
       área útil de 982px → scroll horizontal en un laptop corriente, y lo primero que se
       cortaba era la columna de Acciones. Dentro de esta tabla los chips sí pueden partirse. */
    .sds-table .cc-chip{white-space:normal}
    .sds-hazards{max-width:280px}
    .sds-hz-line{display:flex;align-items:flex-start;gap:.4rem;min-width:0;color:var(--text-muted);font-size:.82rem;line-height:1.35}
    .sds-hz-line + .sds-hz-line{margin-top:.3rem}
    .sds-hz-line .cc-ico{width:13px;height:13px;flex:none;margin-top:.16rem}
    .sds-hz-ico-danger{color:var(--danger)}
    .sds-hz-ico-ok{color:var(--ok)}
    .sds-hz-txt{min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .sds-ghs{display:flex;align-items:center;gap:.25rem;flex-wrap:wrap;margin-bottom:.35rem}
    .cc-ghs{width:26px;height:26px;flex:none;display:block}
    .sds-link{color:var(--brand-primary);text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;margin-top:.25rem}
    .sds-link:hover{text-decoration:underline}
    .sds-link .cc-ico{width:12px;height:12px}
    .sds-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:var(--radius-sm);border:1px solid var(--stroke);background:var(--glass);color:var(--text);transition:border-color .18s,background .18s,color .18s}
    .sds-btn .cc-ico{width:15px;height:15px}
    .sds-btn:hover{background:var(--glass-2);border-color:var(--stroke-2)}
    .sds-btn-danger:hover{color:var(--danger);border-color:color-mix(in srgb,var(--danger) 45%,transparent)}

    .cc-idx-empty{text-align:center;padding:3rem 1.5rem;color:var(--text-muted)}
    .cc-idx-empty .cc-ico{width:42px;height:42px;color:var(--text-muted);opacity:.55;margin-bottom:.7rem}
    .cc-idx-empty .fw-semibold{color:var(--text)}

    /* ── Móvil: la tabla se apila en tarjetas (.cc-stack, patrón global de _brand-theme).
       Cada td lleva su data-label y un único hijo .sds-cell para que la etiqueta quede a
       la izquierda y el contenido a la derecha sin pelearse. */
    @media (max-width:767px){
        .sds-hazards{max-width:none}
        /* MEDIDO en móvil real (375px): sin esta regla volvía el scroll horizontal que
           .cc-stack existe para matar. La celda es un ítem flex con min-width:auto, o sea
           que NO baja de su min-content, y ese min-content lo fijaba el chip más largo. */
        table.cc-stack .sds-cell{min-width:0}
        table.cc-stack .sds-ghs,table.cc-stack .sds-meta,table.cc-stack .sds-hz-line{justify-content:flex-end}
        table.cc-stack .sds-hz-line{align-items:flex-start;text-align:right;flex-direction:row-reverse}
        /* En tarjeta SÍ hay ancho para leer, y `title` no existe en táctil: en vez de
           truncar a una línea, se recorta a tres. */
        table.cc-stack .sds-hz-txt{white-space:normal;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;flex:0 1 auto}
        table.cc-stack .sds-f-count{margin-left:0}
    }
</style>
@endpush

@section('content')
{{-- Confirmación de los submits destructivos (verificar / eliminar) sin JS inline. --}}
@include('componentes._confirm-submit')
@php
    // Delta 4b (code/CAS/familia/pictogramas/sinónimos). Sin él, esas columnas no existen
    // en BD y las secciones que dependen de ellas simplemente no se pintan.
    $hasExt = \App\Models\Consumable::supportsExtendedSds();

    // ── Opciones de los <select>: SIEMPRE derivadas de los datos (hoy son 27 familias y
    // van a crecer; escribirlas a mano las condena a mentir en la siguiente importación).
    $familyOptions = $hasExt
        ? $consumables->pluck('material_family')->map(function ($f) { return trim((string) $f); })->filter()->unique()->sort()->values()
        : collect();

    // OJO (medido en la BD real): signal_word NO está normalizado. Conviven "Peligro"(6)
    // con "PELIGRO"(23) y "Atención"(6) con "ATENCIÓN"(6). Agrupar por el valor CRUDO
    // daría cuatro opciones y filtrar por "Peligro" escondería 23 de las 29 filas que sí
    // dicen Peligro. Se agrupa por la clave en minúsculas y se muestra UNA etiqueta
    // canónica, que además es la que se pinta en la fila (si el <select> dice "Peligro"
    // y la fila dice "PELIGRO", el usuario no sabe si el filtro funcionó).
    $signalOptions = $consumables->pluck('signal_word')
        ->map(function ($w) { return mb_strtolower(trim((string) $w)); })
        ->filter()->unique()->sort()
        ->mapWithKeys(function ($k) { return [$k => mb_convert_case($k, MB_CASE_TITLE, 'UTF-8')]; });

    $total = $consumables->count();
@endphp

<div class="container-fluid py-4 px-3 px-md-4">

    <div class="cc-idx-head">
        <div class="cc-idx-headline">
            <span class="cc-idx-icon">@include('componentes._icon', ['name' => 'droplet', 'label' => 'SDS'])</span>
            <div>
                <div class="cc-idx-eyebrow">Seguridad · SFX</div>
                <h1 class="cc-idx-title">SDS / Consumibles SFX</h1>
                <p class="cc-idx-sub">Hojas de datos de seguridad de materiales de efectos especiales.</p>
            </div>
        </div>
        <div class="cc-idx-actions">
            <a href="{{ route('sfx.index') }}" class="cc-idx-ghost">
                @include('componentes._icon', ['name' => 'flame']) Panel SFX
            </a>
            @can('sds.create')
                <a href="{{ route('consumables.create') }}" class="cc-idx-cta">
                    @include('componentes._icon', ['name' => 'plus']) Nuevo consumible
                </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
            @include('componentes._icon', ['name' => 'check-circle']) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
            @include('componentes._icon', ['name' => 'alert-triangle']) {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($total)
        {{-- Filtrado EN CLIENTE: hoy son ~53 fichas, caben todas en la página y el filtro
             es instantáneo sin tocar el backend (precedente: admin/idcardscrud.blade.php).
             UMBRAL: si este catálogo pasa de unos cientos de filas, hay que mover el filtro
             al SERVIDOR (where/like + índices) y paginar; a partir de ahí el HTML de todas
             las filas pesa más que la comodidad de no recargar. --}}
        <div class="sds-filters">
            <div class="sds-f-search">
                <label for="sdsSearch" class="visually-hidden">Buscar consumible por nombre, sinónimo, código, CAS o número UN</label>
                <span class="sds-f-ico">@include('componentes._icon', ['name' => 'search'])</span>
                <input type="search" id="sdsSearch" class="form-control" autocomplete="off"
                       placeholder="Buscar por nombre, sinónimo, código, CAS o UN…">
            </div>

            @if($familyOptions->isNotEmpty())
                <div class="sds-f-sel">
                    <label for="sdsFamily" class="visually-hidden">Filtrar por familia de material</label>
                    <select id="sdsFamily" class="form-select">
                        <option value="">Todas las familias</option>
                        @foreach($familyOptions as $f)
                            <option value="{{ $f }}">{{ $f }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($signalOptions->isNotEmpty())
                <div class="sds-f-sel">
                    <label for="sdsSignal" class="visually-hidden">Filtrar por palabra de advertencia</label>
                    <select id="sdsSignal" class="form-select">
                        <option value="">Toda advertencia</option>
                        {{-- OJO con el nombre de estas variables: un @@include compila a
                             make($vista, get_defined_vars()), o sea que TODA variable viva en el
                             ámbito de esta vista se le cuela a los parciales. componentes/_icon
                             hace `$label = $label ?? null` y trata $label como "este icono tiene
                             significado" → role="img" aria-label. Con `$label` de contador, al
                             acabar el bucle quedaba valiendo «Peligro» (la última clave ordenada)
                             y CADA @@include de _icon posterior que no pasara 'label' heredaba ese
                             aria-label: medido, 42 iconos de esta página se anunciaban «Peligro»,
                             incluso sobre el vidrio de azúcar (material alimenticio) y sobre el
                             botón «Limpiar». Prefijo `$__` = convención del repo para variables de
                             andamiaje de la vista (ver admin/consumables/show). No las renombres a
                             $label/$class/$name: son los props de _icon. --}}
                        @foreach($signalOptions as $__sigKey => $__sigLabel)
                            <option value="{{ $__sigKey }}">{{ $__sigLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <button type="button" id="sdsClear" class="sds-f-clear" hidden>
                @include('componentes._icon', ['name' => 'x']) Limpiar
            </button>

            {{-- aria-live: quien navega con lector de pantalla no ve las filas desaparecer,
                 así que el conteo tiene que anunciarse solo. --}}
            <span class="sds-f-count" id="sdsCount" role="status" aria-live="polite">
                <strong>{{ $total }}</strong> de {{ $total }}
            </span>
        </div>
    @endif

    <div class="card border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table sds-table cc-stack table-hover align-middle mb-0" id="sdsTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Consumible</th>
                            <th>Tipo</th>
                            <th>Advertencia</th>
                            <th>Peligros y precauciones</th>
                            <th>Estado</th>
                            {{-- «Ver» lo tiene CUALQUIERA que pueda abrir este índice (la ruta ya
                                 exige sds.view), así que la columna de acciones existe SIEMPRE.
                                 Antes la envolvía un @canany(['sds.create','sds.manage']): un lector
                                 de solo lectura (auditor) se quedaba sin ningún acceso al detalle, y
                                 el colspan del empty-state tenía que adivinar entre 5 y 6. --}}
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($consumables as $item)
                        @php
                            $signalKey = mb_strtolower(trim((string) $item->signal_word));
                            $family    = $hasExt ? trim((string) $item->material_family) : '';
                            $pictos    = ($hasExt && is_array($item->ghs_pictograms)) ? $item->ghs_pictograms : [];
                            $synonyms  = ($hasExt && is_array($item->synonyms)) ? $item->synonyms : [];
                            $sdsCode   = $hasExt ? $item->code : null;
                            $cas       = $hasExt ? $item->cas_number : null;

                            // Heno de búsqueda: minúsculas y SIN acentos, para casar con la consulta
                            // que el JS normaliza igual (quien teclea "atencion" espera "Atención").
                            // Se añade una copia de UN sin espacios porque en BD conviven "UN1978" y
                            // "UN 1170": teclear "UN1170" tiene que encontrar los dos.
                            $haystack = array_merge(
                                [$item->name, $family, $sdsCode, $cas, $item->un_number,
                                 str_replace(' ', '', (string) $item->un_number)],
                                $synonyms
                            );
                            $haystack = mb_strtolower(\Illuminate\Support\Str::ascii(implode(' ', array_filter($haystack))));

                            // `sds_url` NO es de confianza: su regla es `nullable|string|max:2048`
                            // (ConsumableController::validatedData), o sea que quien tiene sds.create
                            // puede guardar `javascript:…` y {{ }} no lo impide — escapa comillas, y un
                            // URI javascript: no las necesita. target=_blank/rel=noopener tampoco
                            // filtran esquemas. LISTA BLANCA: solo http/https se pintan como enlace.
                            // Medido: las 32 fichas con sds_url son 31 https + 1 http → no se pierde
                            // ninguna. Gemelo de admin/consumables/show, donde está el porqué largo.
                            $sdsHref   = null;
                            $sdsRaw    = trim((string) $item->sds_url);
                            if ($sdsRaw !== '') {
                                $sdsScheme = mb_strtolower((string) parse_url($sdsRaw, PHP_URL_SCHEME));
                                if ($sdsScheme === 'http' || $sdsScheme === 'https') { $sdsHref = $sdsRaw; }
                            }
                        @endphp
                        <tr data-search="{{ $haystack }}" data-family="{{ $family }}" data-signal="{{ $signalKey }}">
                            <td class="ps-4" data-label="Consumible">
                                <div class="sds-cell">
                                    <a class="sds-name" href="{{ route('consumables.show', $item->id) }}">{{ $item->name }}</a>
                                    @if($item->isPendingVerification())
                                        @php
                                            // El texto viejo decía «Capturado en set» para TODA ficha pendiente.
                                            // Medido: las 9 pendientes de hoy vienen del import del catálogo —
                                            // mentía 9 de 9. La procedencia se pregunta, no se supone.
                                            $captured = $item->isFieldCaptured();
                                        @endphp
                                        <div class="mt-1">
                                            <span class="cc-chip cc-chip-warn"
                                                  title="{{ $captured
                                                        ? 'Ficha capturada a mano en set. Sus datos aún no han sido validados por un responsable de SDS.'
                                                        : 'Ficha importada del catálogo SPFX. Sus datos aún no han sido validados por un responsable de SDS.' }}">
                                                @include('componentes._icon', ['name' => 'alert-triangle'])
                                                Pendiente · {{ $captured ? 'capturado en set' : 'importado del catálogo' }}
                                            </span>
                                        </div>
                                    @endif
                                    @if($sdsCode || $cas || $item->un_number)
                                        <div class="sds-meta">
                                            @if($sdsCode)<span>{{ $sdsCode }}</span>@endif
                                            @if($cas)<span>CAS {{ $cas }}</span>@endif
                                            @if($item->un_number)<span>{{ $item->un_number }}</span>@endif
                                        </div>
                                    @endif
                                    @if($sdsHref !== null)
                                        {{-- «SDS externa», no «Ver SDS»: ahora que la fila tiene un botón
                                             «Ver» al detalle interno, dos cosas llamadas «ver» se confunden. --}}
                                        <a href="{{ $sdsHref }}" target="_blank" rel="noopener" class="sds-link small"
                                           title="Abre la hoja de datos del fabricante en otra pestaña">
                                            @include('componentes._icon', ['name' => 'external-link']) SDS externa
                                        </a>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Tipo">
                                <span class="cc-chip cc-chip-neutral">{{ $item->type_label }}</span>
                            </td>
                            <td data-label="Advertencia">
                                {{-- Pictograma + palabra de advertencia van JUNTOS: es exactamente como
                                     el GHS los presenta en la etiqueta real del envase. --}}
                                <div class="sds-cell">
                                    @if(count($pictos))
                                        <div class="sds-ghs">
                                            @foreach($pictos as $p)
                                                @include('componentes._ghs-pictogram', ['code' => $p, 'class' => 'cc-ghs', 'label' => null])
                                            @endforeach
                                        </div>
                                    @endif
                                    @if($signalKey !== '')
                                        <span class="cc-chip {{ $signalKey === 'peligro' ? 'cc-chip-danger' : 'cc-chip-warn' }}">{{ $signalOptions[$signalKey] ?? $item->signal_word }}</span>
                                    @else
                                        <span class="cc-muted">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="sds-hazards" data-label="Peligros y precauciones">
                                <div class="sds-cell">
                                    <div class="sds-hz-line" title="{{ $item->hazards }}">
                                        @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico sds-hz-ico-danger', 'label' => 'Peligros'])
                                        <span class="sds-hz-txt">{{ $item->hazards ?: '—' }}</span>
                                    </div>
                                    @if(trim((string) $item->precautions) !== '')
                                        {{-- El peligro sin el control es media ficha: quien lee la lista
                                             necesita ver QUÉ hace con el material, no solo qué le puede pasar. --}}
                                        <div class="sds-hz-line" title="{{ $item->precautions }}">
                                            @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico sds-hz-ico-ok', 'label' => 'Precauciones'])
                                            <span class="sds-hz-txt">{{ $item->precautions }}</span>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Estado">
                                @if($item->is_active)
                                    <span class="cc-chip cc-chip-ok">Activo</span>
                                @else
                                    <span class="cc-chip cc-chip-neutral">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-2">
                                    <a class="sds-btn" href="{{ route('consumables.show', $item->id) }}" title="Ver ficha" aria-label="Ver ficha de {{ $item->name }}">
                                        @include('componentes._icon', ['name' => 'eye', 'label' => 'Ver ficha'])
                                    </a>
                                    @can('sds.manage')
                                        @if($item->isPendingVerification())
                                            {{-- data-confirm, NO onsubmit: el nombre es texto libre de quien
                                                 tiene sds.create y en un atributo JS rompería el literal de
                                                 cadena (apóstrofo → SyntaxError; comilla cerrada → inyección
                                                 en la sesión de quien verifica). Ver componentes/_confirm-submit. --}}
                                            <form method="POST" action="{{ route('consumables.verify', $item->id) }}"
                                                  data-confirm="¿Confirmas que los datos de «{{ $item->name }}» son correctos? Quedará registrada tu verificación.">
                                                @csrf
                                                <button type="submit" class="sds-btn" title="Verificar" aria-label="Verificar {{ $item->name }}">
                                                    @include('componentes._icon', ['name' => 'check', 'label' => 'Verificar'])
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('sds.create')
                                        <a class="sds-btn" href="{{ route('consumables.edit', $item->id) }}" title="Editar" aria-label="Editar {{ $item->name }}">
                                            @include('componentes._icon', ['name' => 'pencil', 'label' => 'Editar'])
                                        </a>
                                    @endcan
                                    @can('sds.manage')
                                        {{-- "Eliminar" = RETIRAR (soft delete, Paso 1b): la ficha se
                                             archiva y solo la ve un super-admin en la papelera; puede
                                             restaurarse. data-confirm, NO onsubmit: el nombre es texto
                                             libre y en un atributo JS rompería el literal de cadena
                                             (apóstrofo → SyntaxError; comilla → inyección). Ver
                                             componentes/_confirm-submit. --}}
                                        <form method="POST" action="{{ route('consumables.destroy', $item->id) }}"
                                              data-confirm="¿Desactivar «{{ $item->name }}»? Quedará archivada y solo la verá un super administrador; podrá restaurarse.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sds-btn sds-btn-danger" title="Desactivar" aria-label="Desactivar {{ $item->name }}">
                                                @include('componentes._icon', ['name' => 'trash-2', 'label' => 'Desactivar'])
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
                                <div class="cc-idx-empty">
                                    @include('componentes._icon', ['name' => 'droplet', 'label' => 'Sin consumibles'])
                                    <div class="fw-semibold">Aún no hay consumibles registrados.</div>
                                    <small>Crea el primero con el botón «Nuevo consumible».</small>
                                </div>
                            </td>
                        </tr>
                        @endforelse

                        {{-- Empty-state del FILTRO — distinto del de arriba: «no hay fichas» es un
                             catálogo vacío, «nada casa» es una búsqueda sin resultados y se sale
                             de él limpiando, no dando de alta. --}}
                        <tr id="sdsNoMatch" hidden>
                            <td colspan="6">
                                <div class="cc-idx-empty">
                                    @include('componentes._icon', ['name' => 'search', 'label' => 'Sin resultados'])
                                    <div class="fw-semibold">Ninguna ficha coincide con el filtro.</div>
                                    <small>Prueba con otro término o <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="sdsClear2">limpia los filtros</button>.</small>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Papelera · SOLO super-admin (Paso 1b, soft delete) ──────────────────────
         El controlador solo puebla $trashed para un super-admin; para el resto es null
         y esta sección ni se pinta. Las fichas de aquí están RETIRADAS: el global scope
         de SoftDeletes las oculta de la tabla de arriba y de todo el resto de la app.
         Desde aquí se restauran (vuelven al catálogo) o se destruyen en firme. --}}
    @isset($trashed)
        @if($trashed->isNotEmpty())
        <div class="card border-0 rounded-3 mt-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="cc-idx-icon" style="width:34px;height:34px;border-radius:10px;">
                        @include('componentes._icon', ['name' => 'trash-2', 'label' => 'Papelera'])
                    </span>
                    <h2 class="cc-idx-title mb-0" style="font-size:1.1rem;">Desactivados</h2>
                    <span class="cc-chip cc-chip-neutral">{{ $trashed->count() }}</span>
                </div>
                <p class="cc-idx-sub mb-3">
                    Fichas retiradas del catálogo. Solo tú (super administrador) las ves aquí.
                    Restáuralas para devolverlas al catálogo, o elimínalas en firme (no se puede deshacer).
                </p>
                <div class="table-responsive">
                    <table class="table sds-table cc-stack align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Consumible</th>
                                <th>Tipo</th>
                                <th>Desactivado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trashed as $item)
                            <tr>
                                <td class="ps-4" data-label="Consumible">
                                    <div class="sds-cell"><span class="sds-name">{{ $item->name }}</span></div>
                                </td>
                                <td data-label="Tipo">
                                    <span class="cc-chip cc-chip-neutral">{{ $item->type_label }}</span>
                                </td>
                                <td data-label="Desactivado">
                                    <span class="sds-meta">
                                        @if($item->deleted_at)
                                            <span>{{ $item->deleted_at->format('Y-m-d H:i') }}</span>
                                        @else
                                            <span>—</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-2">
                                        <form method="POST" action="{{ route('consumables.restore', $item->id) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="sds-btn" title="Restaurar" aria-label="Restaurar {{ $item->name }}">
                                                @include('componentes._icon', ['name' => 'rotate-ccw', 'label' => 'Restaurar'])
                                            </button>
                                        </form>
                                        {{-- Destrucción física definitiva: data-confirm (no onsubmit) por el
                                             nombre de texto libre, igual que en la lista de arriba. --}}
                                        <form method="POST" action="{{ route('consumables.forceDestroy', $item->id) }}"
                                              data-confirm="Eliminar DEFINITIVAMENTE «{{ $item->name }}»? Esta acción no se puede deshacer.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sds-btn sds-btn-danger" title="Eliminar definitivamente" aria-label="Eliminar definitivamente {{ $item->name }}">
                                                @include('componentes._icon', ['name' => 'trash-2', 'label' => 'Eliminar definitivamente'])
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    @endisset

</div>
@endsection

@push('scripts')
<script>
    (function () {
        var table = document.getElementById('sdsTable');
        if (!table) { return; }

        var q      = document.getElementById('sdsSearch');
        var fam    = document.getElementById('sdsFamily');
        var sig    = document.getElementById('sdsSignal');
        var clear  = document.getElementById('sdsClear');
        var clear2 = document.getElementById('sdsClear2');
        var count  = document.getElementById('sdsCount');
        var noHit  = document.getElementById('sdsNoMatch');
        var rows   = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-search]'));
        if (!rows.length) { return; }

        // Misma normalización que el heno del servidor (minúsculas + sin acentos): así
        // "atencion", "Atención" y "ATENCIÓN" son la misma búsqueda.
        function norm(s) {
            return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function apply() {
            var term  = norm(q ? q.value : '').trim();
            var fVal  = fam ? fam.value : '';
            var sVal  = sig ? sig.value : '';
            var shown = 0;

            rows.forEach(function (tr) {
                // Y lógico: la fila sobrevive solo si pasa los TRES filtros.
                var ok = (term === '' || tr.getAttribute('data-search').indexOf(term) !== -1)
                      && (fVal === '' || tr.getAttribute('data-family') === fVal)
                      && (sVal === '' || tr.getAttribute('data-signal') === sVal);
                tr.hidden = !ok;
                if (ok) { shown++; }
            });

            if (count) { count.innerHTML = '<strong>' + shown + '</strong> de ' + rows.length; }
            if (noHit) { noHit.hidden = shown !== 0; }
            // «Limpiar» solo aparece cuando hay algo que limpiar.
            if (clear) { clear.hidden = (term === '' && fVal === '' && sVal === ''); }
        }

        function reset() {
            if (q)   { q.value = ''; }
            if (fam) { fam.value = ''; }
            if (sig) { sig.value = ''; }
            apply();
            if (q) { q.focus(); }
        }

        // Sin debounce a propósito: esto filtra nodos ya en el DOM, no hace fetch.
        if (q)      { q.addEventListener('input', apply); }
        if (fam)    { fam.addEventListener('change', apply); }
        if (sig)    { sig.addEventListener('change', apply); }
        if (clear)  { clear.addEventListener('click', reset); }
        if (clear2) { clear2.addEventListener('click', reset); }

        apply();
    })();
</script>
@endpush
@endfeature
