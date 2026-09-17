@extends('layouts.app')
@section('title', 'Eventos de peligro')

{{-- NO @feature: el catálogo de eventos posibles es NÚCLEO (no una característica apagable),
     igual que su controlador (HazardEventController::guard() no checa ningún flag). --}}

@push('styles')
<style>
    /* ── Índice glass · lenguaje Cinematic Dark Glass (tokens de _brand-theme).
       Hermano de admin/standards/index: mismas piezas cc-idx-* / cc-chip-* / .epf-chip,
       adaptadas al catálogo de EVENTOS y AGRUPADAS por contexto (un <tbody> por grupo). ── */
    .cc-idx-head{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.75rem}
    .cc-idx-headline{display:flex;align-items:center;gap:1rem;min-width:0}
    .cc-idx-icon{width:48px;height:48px;border-radius:14px;flex:none;display:inline-flex;align-items:center;justify-content:center;
        color:var(--brand-primary);background:color-mix(in srgb,var(--brand-primary) 14%,transparent);border:1px solid color-mix(in srgb,var(--brand-primary) 26%,transparent)}
    .cc-idx-icon .cc-ico{width:22px;height:22px}
    .cc-idx-eyebrow{font-size:.66rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700}
    .cc-idx-title{margin:.1rem 0 .1rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:1.5rem;color:var(--text);line-height:1.05}
    .cc-idx-sub{margin:0;color:var(--text-muted);font-size:.86rem}
    .cc-idx-actions{display:flex;gap:.6rem;flex-wrap:wrap}
    .cc-idx-cta{display:inline-flex;align-items:center;gap:.5rem;padding:.62rem 1.05rem;border-radius:var(--radius-sm);text-decoration:none;font-weight:700;font-size:.88rem;background:var(--brand-primary);color:var(--brand-on-primary);border:1px solid var(--brand-primary);box-shadow:0 10px 26px -12px var(--brand-glow);transition:transform .18s var(--ease,cubic-bezier(.16,1,.3,1)),filter .18s}
    .cc-idx-cta:hover{transform:translateY(-1px);color:var(--brand-on-primary);filter:brightness(1.04)}
    .cc-idx-cta .cc-ico{width:16px;height:16px}

    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.28rem .58rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}
    .cc-chip-neutral{color:var(--text-muted);background:var(--glass-2);border-color:var(--stroke)}
    .cc-chip-pc{color:var(--text);background:var(--glass-2);border-color:var(--stroke);font-variant-numeric:tabular-nums}

    /* Chip de marco: sobre .badge de Bootstrap (padding/forma) + .badge-XXX (color). */
    .he-table .badge{white-space:normal;font-weight:700;letter-spacing:.02em}

    /* ── Barra de filtros (cliente): buscador + selects + toggle "solo pendientes". ── */
    .he-filters{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem}
    .he-filters .form-control,.he-filters .form-select{background:var(--glass);border:1px solid var(--stroke);color:var(--text);border-radius:var(--radius-sm);font-size:.88rem;padding:.55rem .8rem}
    .he-filters .form-control::placeholder{color:var(--text-muted);opacity:1}
    .he-filters .form-control:focus,.he-filters .form-select:focus{background:var(--glass-2);border-color:var(--brand-primary)}
    .he-f-search{position:relative;flex:1 1 260px;min-width:0}
    .he-f-search .form-control{padding-left:2.25rem}
    .he-f-search .he-f-ico{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none}
    .he-f-select{flex:0 1 200px;min-width:150px}
    .he-f-count{font-size:.78rem;color:var(--text-muted);font-weight:600;white-space:nowrap;margin-left:auto}
    .he-f-count strong{color:var(--text)}

    /* Toggle "Solo pendientes": misma piel que .epf-chip, con icono. */
    .he-toggle{display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--stroke);background:var(--glass);color:var(--text-muted);border-radius:999px;padding:.4rem .8rem;font-size:.74rem;font-weight:700;letter-spacing:.02em;line-height:1;cursor:pointer;transition:background .12s,border-color .12s,color .12s}
    .he-toggle .cc-ico{width:14px;height:14px}
    .he-toggle:hover{border-color:var(--stroke-2);color:var(--text)}
    .he-toggle[aria-pressed="true"]{background:color-mix(in srgb,var(--warn) 18%,transparent);border-color:color-mix(in srgb,var(--warn) 40%,transparent);color:var(--warn)}

    /* ── Fila de FACETAS por marco (mismo markup que _event-picker: .event-picker-facets
       / .epf-chip), themed por tokens + HANDLER propio (togglea filas de la tabla). ── */
    .event-picker-facets{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-bottom:.85rem}
    .event-picker-facets .epf-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)}
    .epf-chip{border:1px solid var(--stroke);background:var(--glass);color:var(--text-muted);border-radius:999px;padding:.35rem .75rem;font-size:.72rem;font-weight:700;letter-spacing:.03em;line-height:1;cursor:pointer;transition:background .12s,border-color .12s,color .12s}
    .epf-chip:hover{border-color:var(--stroke-2);color:var(--text)}
    .epf-chip[aria-pressed="true"]{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary)}

    /* Tabla dentro de .card (vidrio): fondo transparente + tinta por tokens. */
    .he-table{--bs-table-bg:transparent;color:var(--text);margin-bottom:0}
    .he-table thead th{text-transform:uppercase;font-size:.7rem;letter-spacing:.05em;color:var(--text-muted);font-weight:700;border-bottom:1px solid var(--stroke)}
    .he-table td,.he-table th{border-color:var(--stroke)}
    .he-table tbody tr:hover td{background:var(--glass-2)}
    /* El filtro esconde filas con [hidden]; en móvil .cc-stack las pone display:block,
       así que se refuerza para que hidden gane sin depender del orden. */
    .he-table tr[hidden]{display:none !important}
    .he-table tbody[hidden]{display:none !important}

    /* Fila cabecera de GRUPO (contexto): banda a lo ancho, no filtrable. */
    .he-grouprow th{background:var(--glass-2);color:var(--text);font-weight:800;letter-spacing:.02em;text-transform:none;font-size:.82rem;border-bottom:1px solid var(--stroke)}
    .he-grouprow .he-grp-count{font-weight:600;color:var(--text-muted);font-size:.74rem;margin-left:.4rem}

    .he-name{font-weight:600;color:var(--text)}
    a.he-name{text-decoration:none;display:inline-block}
    a.he-name:hover{color:var(--brand-primary);text-decoration:underline}
    .he-code{display:block;font-size:.72rem;color:var(--text-muted);font-weight:500;font-variant-numeric:tabular-nums;margin-top:.15rem}
    .he-chips{display:flex;flex-wrap:wrap;gap:.3rem}
    .he-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:var(--radius-sm);border:1px solid var(--stroke);background:var(--glass);color:var(--text);transition:border-color .18s,background .18s,color .18s}
    .he-btn .cc-ico{width:15px;height:15px}
    .he-btn:hover{background:var(--glass-2);border-color:var(--stroke-2)}
    .he-btn-danger:hover{color:var(--danger);border-color:color-mix(in srgb,var(--danger) 45%,transparent)}
    .he-btn-ok:hover{color:var(--ok);border-color:color-mix(in srgb,var(--ok) 45%,transparent)}
    .he-muted{color:var(--text-muted)}

    .cc-idx-empty{text-align:center;padding:3rem 1.5rem;color:var(--text-muted)}
    .cc-idx-empty .cc-ico{width:42px;height:42px;color:var(--text-muted);opacity:.55;margin-bottom:.7rem}
    .cc-idx-empty .fw-semibold{color:var(--text)}

    /* ── Paginador de DISPLAY (cliente): repagina la secuencia FILTRADA y PLANA (que ya viene
       agrupada por contexto), no el total. Reusa el lenguaje glass/.epf-chip. Se OCULTA con
       [hidden] cuando hay <=1 página; como .cc-pager fija display:flex, el [hidden] necesita
       !important para ganar (mismo truco que tr[hidden] de la tabla). ── */
    .cc-pager{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:1rem}
    .cc-pager[hidden]{display:none !important}
    .cc-pager-showing{font-size:.78rem;color:var(--text-muted);font-weight:600}
    .cc-pager-showing strong{color:var(--text)}
    .cc-pager-nav{display:flex;align-items:center;gap:.5rem;margin-left:auto}
    .cc-pager-page{font-size:.8rem;font-weight:700;color:var(--text);font-variant-numeric:tabular-nums;padding:0 .35rem;white-space:nowrap}
    .cc-pager-btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;min-height:44px;min-width:44px;
        border:1px solid var(--stroke);background:var(--glass);color:var(--text);border-radius:999px;padding:.5rem .9rem;
        font-size:.78rem;font-weight:700;letter-spacing:.02em;line-height:1;cursor:pointer;
        transition:background .12s,border-color .12s,color .12s}
    .cc-pager-btn .cc-ico{width:15px;height:15px}
    .cc-pager-btn:hover:not(:disabled){background:var(--glass-2);border-color:var(--stroke-2)}
    .cc-pager-btn:disabled{opacity:.45;cursor:not-allowed}

    /* ── Móvil: la tabla se apila en tarjetas (.cc-stack, patrón global de _brand-theme).
       Cada td lleva data-label; la banda de grupo se vuelve un banner de bloque. ── */
    @media (max-width:767px){
        table.cc-stack .he-grouprow th{display:block;text-align:left}
        table.cc-stack .he-chips{justify-content:flex-end}
        table.cc-stack .he-f-count{margin-left:0}
        /* Paginador centrado; los botones se quedan como sólo-flecha (área táctil 44px intacta). */
        .cc-pager{justify-content:center}
        .cc-pager-nav{margin-left:0}
        .cc-pager-btn-txt{display:none}
    }
</style>
@endpush

@section('content')
{{-- DENTRO de @section: _badge-tokens emite un <style> por ECHO directo (no @push); a nivel
     superior de un @extends saldría ANTES del <!doctype> y dispararía el modo Quirks. --}}
@include('componentes._badge-tokens')
{{-- Confirmación de los submits destructivos (verificar / retirar) SIN JS inline. --}}
@include('componentes._confirm-submit')
@php
    // Enum CERRADO del marco (fuente única de _badge-tokens: los 7 colores). Sirve para
    // (a) validar el badge de la norma antes de usarlo como clase CSS y (b) ordenar facetas.
    // Incluye AMAZON (legacy) porque una norma ligada puede traerlo y no debe caer a GENERAL.
    $badgeEnum = ['CSATF', 'OSHA', 'STPS', 'DOT', 'SCT', 'GENERAL', 'AMAZON'];

    $total = $events->count();

    // Agrupación por contexto. El controlador ya ordena is_active↓ → context → sort_order →
    // name_es, así que aquí solo se agrupa; para el ORDEN de los grupos se recorre el mapa
    // canónico contexts() (y al final cualquier contexto huérfano fuera del mapa).
    $grouped = $events->groupBy('context');
    $groupOrder = array_keys($contexts);
    foreach ($grouped->keys() as $k) {
        if (!in_array($k, $groupOrder, true)) { $groupOrder[] = $k; }
    }

    // Facetas: SOLO los marcos realmente presentes en las normas ligadas (sin chips muertos).
    $marcosPresent = [];
    foreach ($events as $__ev) {
        foreach ($__ev->standards as $__s) {
            $__b = in_array($__s->regulation_badge, $badgeEnum, true) ? $__s->regulation_badge : 'GENERAL';
            $marcosPresent[$__b] = true;
        }
    }
    $marcoChips = array_values(array_filter($badgeEnum, function ($m) use ($marcosPresent) {
        return isset($marcosPresent[$m]);
    }));
@endphp

<div class="container-fluid py-4 px-3 px-md-4">

    <div class="cc-idx-head">
        <div class="cc-idx-headline">
            <span class="cc-idx-icon">@include('componentes._icon', ['name' => 'shield-alert', 'label' => 'Eventos de peligro'])</span>
            <div>
                <div class="cc-idx-eyebrow">Seguridad · Catálogo</div>
                <h1 class="cc-idx-title">Eventos de peligro</h1>
                <p class="cc-idx-sub">Eventos posibles por contexto (locación, set, construcción…) y las normas que los respaldan.</p>
            </div>
        </div>
        <div class="cc-idx-actions">
            @can('hazardevents.create')
                <a href="{{ route('hazardevents.create') }}" class="cc-idx-cta">
                    @include('componentes._icon', ['name' => 'plus']) Nuevo evento
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

    @can('hazardevents.manage')
        {{-- (captura fluida · Paso 3) Medidas de control por hoja de cálculo: descarga, edita
             las columnas de medida en Excel y vuelve a subir. El archivo llega ordenado por los
             eventos que más se usan. Subir no borra lo ya escrito. --}}
        <div class="he-csv" style="border:1px solid var(--stroke,#dee2e6);border-radius:14px;padding:1rem 1.1rem;margin-bottom:1.5rem;background:var(--glass,rgba(0,0,0,.02));display:flex;gap:1rem;flex-wrap:wrap;align-items:center;">
            <div style="min-width:220px;flex:1 1 260px;">
                <div style="font-weight:800;font-size:.95rem;">Medidas de control por hoja de cálculo</div>
                <div style="color:var(--text-muted,#6c757d);font-size:.82rem;margin-top:.15rem;">
                    Descarga, edita las columnas de medida en Excel y vuelve a subir. Llega ordenado por los eventos más usados.
                </div>
            </div>
            <a href="{{ route('hazardevents.control.export') }}" class="btn btn-outline-primary">
                @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico me-1']) Descargar CSV
            </a>
            <form action="{{ route('hazardevents.control.import') }}" method="POST" enctype="multipart/form-data" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                @csrf
                <input type="file" name="csv" accept=".csv,text/csv" required class="form-control" style="max-width:240px;">
                <button type="submit" class="btn btn-primary">
                    @include('componentes._icon', ['name' => 'upload', 'class' => 'cc-ico me-1']) Subir
                </button>
            </form>
        </div>
    @endcan

    @if($total)
        {{-- Filtrado EN CLIENTE: el catálogo cabe entero en la página (hoy ~120 eventos) y el
             filtro es instantáneo sin tocar el backend. UMBRAL: si pasa de unos cientos de
             filas, mover el filtro al SERVIDOR (where/like + índices) y paginar. --}}
        <div class="he-filters">
            <div class="he-f-search">
                <label for="heSearch" class="visually-hidden">Buscar evento por nombre o categoría</label>
                <span class="he-f-ico">@include('componentes._icon', ['name' => 'search'])</span>
                <input type="search" id="heSearch" class="form-control" autocomplete="off"
                       placeholder="Buscar por nombre, código o categoría…">
            </div>

            <div class="he-f-select">
                <label for="heCtx" class="visually-hidden">Filtrar por contexto</label>
                <select id="heCtx" class="form-select">
                    <option value="">Todos los contextos</option>
                    @foreach($contexts as $ctxKey => $ctxLabel)
                        <option value="{{ $ctxKey }}">{{ $ctxLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div class="he-f-select">
                <label for="heCat" class="visually-hidden">Filtrar por categoría</label>
                <select id="heCat" class="form-select">
                    <option value="">Todas las categorías</option>
                    @foreach($categories as $catKey => $catLabel)
                        <option value="{{ $catKey }}">{{ $catLabel }}</option>
                    @endforeach
                </select>
            </div>

            <button type="button" class="he-toggle" id="hePending" aria-pressed="false">
                @include('componentes._icon', ['name' => 'alert-triangle']) Solo pendientes
            </button>

            <span class="he-f-count" id="heCount" role="status" aria-live="polite">
                <strong>{{ $total }}</strong> de {{ $total }}
            </span>
        </div>

        @if(count($marcoChips))
            <div class="event-picker-facets" role="group" aria-label="Filtrar por marco normativo">
                <span class="epf-label">Marco:</span>
                @foreach($marcoChips as $mk)
                    <button type="button" class="epf-chip" data-marco="{{ $mk }}" aria-pressed="false">{{ $mk }}</button>
                @endforeach
            </div>
        @endif
    @endif

    <div class="card border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table he-table cc-stack table-hover align-middle mb-0" id="heTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Evento</th>
                            <th>Contexto / Categoría</th>
                            <th>Prob·Cons</th>
                            <th>Marcos</th>
                            <th>Verificación</th>
                            <th>Vigencia</th>
                            {{-- «Ver» lo tiene cualquiera que abra este índice (la ruta ya exige
                                 hazardevents.view), así que la columna de acciones existe SIEMPRE. --}}
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>

                    @if($total)
                        @foreach($groupOrder as $ctxKey)
                            @if($grouped->has($ctxKey))
                            <tbody class="he-group" data-group="{{ $ctxKey }}">
                                <tr class="he-grouprow">
                                    <th colspan="7" class="ps-4">
                                        {{ $contexts[$ctxKey] ?? $ctxKey }}
                                        <span class="he-grp-count">· {{ $grouped[$ctxKey]->count() }}</span>
                                    </th>
                                </tr>
                                @foreach($grouped[$ctxKey] as $ev)
                                @php
                                    // Marcos ÚNICOS que tocan sus normas ligadas, validados contra el
                                    // enum (defensa de origen: una norma con badge fuera del enum se
                                    // pinta como GENERAL, nunca como clase CSS arbitraria).
                                    $rowMarcos = $ev->standards->pluck('regulation_badge')->filter()
                                        ->map(function ($b) use ($badgeEnum) {
                                            return in_array($b, $badgeEnum, true) ? $b : 'GENERAL';
                                        })->unique()->values();

                                    $pending = $ev->isPendingVerification();

                                    // Heno de búsqueda: minúsculas y SIN acentos (igual que el JS).
                                    $haystack = mb_strtolower(\Illuminate\Support\Str::ascii(implode(' ', array_filter([
                                        $ev->name_es,
                                        $ev->name_en,
                                        $ev->code,
                                        $ev->category_label,
                                        $ev->context_label,
                                        $rowMarcos->implode(' '),
                                    ]))));

                                    $pcLike = $ev->default_likelihood ? $ev->default_likelihood : null;
                                    $pcCons = $ev->default_consequence ? $ev->default_consequence : null;
                                @endphp
                                <tr data-search="{{ $haystack }}"
                                    data-marcos="{{ $rowMarcos->implode(' ') }}"
                                    data-context="{{ $ev->context }}"
                                    data-category="{{ $ev->category }}"
                                    data-pending="{{ $pending ? '1' : '0' }}">
                                    <td class="ps-4" data-label="Evento">
                                        <a class="he-name" href="{{ route('hazardevents.show', $ev->id) }}">{{ $ev->name_localized }}</a>
                                        <span class="he-code">{{ $ev->code }}</span>
                                    </td>
                                    <td data-label="Contexto / Categoría">
                                        <div class="he-chips">
                                            <span class="cc-chip cc-chip-neutral">{{ $ev->context_label }}</span>
                                            @if($ev->category_label)
                                                <span class="cc-chip cc-chip-neutral">{{ $ev->category_label }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-label="Prob·Cons">
                                        @if($pcLike !== null || $pcCons !== null)
                                            <span class="cc-chip cc-chip-pc">{{ $pcLike ?? '—' }}·{{ $pcCons ?? '—' }}</span>
                                        @else
                                            <span class="he-muted">—</span>
                                        @endif
                                    </td>
                                    <td data-label="Marcos">
                                        @if($rowMarcos->count())
                                            <div class="he-chips">
                                                @foreach($rowMarcos as $mk)
                                                    <span class="badge badge-{{ $mk }}">{{ $mk }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="he-muted">—</span>
                                        @endif
                                    </td>
                                    <td data-label="Verificación">
                                        @if($pending)
                                            <span class="cc-chip cc-chip-warn">
                                                @include('componentes._icon', ['name' => 'alert-triangle']) Pendiente
                                            </span>
                                        @elseif($ev->isVerified())
                                            <span class="cc-chip cc-chip-ok">
                                                @include('componentes._icon', ['name' => 'check']) Verificado
                                            </span>
                                        @else
                                            <span class="he-muted">—</span>
                                        @endif
                                    </td>
                                    <td data-label="Vigencia">
                                        @if($ev->isActive())
                                            <span class="cc-chip cc-chip-ok">Vigente</span>
                                        @else
                                            <span class="cc-chip cc-chip-neutral">Retirado</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex gap-2">
                                            <a class="he-btn" href="{{ route('hazardevents.show', $ev->id) }}" title="Ver evento" aria-label="Ver evento {{ $ev->code }}">
                                                @include('componentes._icon', ['name' => 'eye', 'label' => 'Ver evento'])
                                            </a>
                                            @can('hazardevents.manage')
                                                @if($pending)
                                                    {{-- data-confirm, NO onsubmit: el patrón del repo evita el XSS de
                                                         meter datos en contexto JS. Ver componentes/_confirm-submit. --}}
                                                    <form method="POST" action="{{ route('hazardevents.verify', $ev->id) }}"
                                                          data-confirm="¿Confirmas que el evento «{{ $ev->name_es }}» es correcto? Quedará registrada tu verificación.">
                                                        @csrf
                                                        <button type="submit" class="he-btn he-btn-ok" title="Verificar" aria-label="Verificar {{ $ev->code }}">
                                                            @include('componentes._icon', ['name' => 'check', 'label' => 'Verificar'])
                                                        </button>
                                                    </form>
                                                @endif
                                                {{-- OJO: Editar es hazardevents.MANAGE (no create): el safety-officer
                                                     AGREGA pero NO edita; solo un manage corrige/verifica/retira. --}}
                                                <a class="he-btn" href="{{ route('hazardevents.edit', $ev->id) }}" title="Editar" aria-label="Editar {{ $ev->code }}">
                                                    @include('componentes._icon', ['name' => 'pencil', 'label' => 'Editar'])
                                                </a>
                                                @if($ev->isActive())
                                                    {{-- Retirar = is_active=0 (NO borra): el evento sale de la captura pero
                                                         sigue vivo para el histórico. PUT + data-confirm. --}}
                                                    <form method="POST" action="{{ route('hazardevents.deactivate', $ev->id) }}"
                                                          data-confirm="¿Retirar «{{ $ev->name_es }}» del catálogo de captura? El histórico que lo referencia se sigue resolviendo.">
                                                        @csrf
                                                        @method('PUT')
                                                        <button type="submit" class="he-btn he-btn-danger" title="Retirar" aria-label="Retirar {{ $ev->code }}">
                                                            @include('componentes._icon', ['name' => 'x-circle', 'label' => 'Retirar'])
                                                        </button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('hazardevents.reactivate', $ev->id) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <button type="submit" class="he-btn he-btn-ok" title="Reactivar" aria-label="Reactivar {{ $ev->code }}">
                                                            @include('componentes._icon', ['name' => 'rotate-ccw', 'label' => 'Reactivar'])
                                                        </button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            @endif
                        @endforeach

                        {{-- Empty-state del FILTRO — distinto del catálogo vacío. --}}
                        <tbody>
                            <tr id="heNoMatch" hidden>
                                <td colspan="7">
                                    <div class="cc-idx-empty">
                                        @include('componentes._icon', ['name' => 'search', 'label' => 'Sin resultados'])
                                        <div class="fw-semibold">Ningún evento coincide con el filtro.</div>
                                        <small>Prueba con otro término o <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="heClear">limpia los filtros</button>.</small>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    @else
                        <tbody>
                            <tr>
                                <td colspan="7">
                                    <div class="cc-idx-empty">
                                        @include('componentes._icon', ['name' => 'shield-alert', 'label' => 'Sin eventos'])
                                        <div class="fw-semibold">Aún no hay eventos registrados.</div>
                                        @can('hazardevents.create')
                                            <small>Crea el primero con el botón «Nuevo evento».</small>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @if($total)
        {{-- Paginador de DISPLAY (cliente): repagina SOLO el resultado filtrado (secuencia plana
             ya agrupada por contexto). Nace oculto (hidden); el JS lo muestra sólo si el set
             filtrado supera 1 página. Las bandas de contexto se recomputan por página. --}}
        <nav class="cc-pager" id="hePager" aria-label="Paginación de eventos" hidden>
            <span class="cc-pager-showing" id="heShowing"></span>
            <div class="cc-pager-nav">
                <button type="button" class="cc-pager-btn" id="hePrev" aria-label="Página anterior">
                    @include('componentes._icon', ['name' => 'chevron-left'])
                    <span class="cc-pager-btn-txt">Anterior</span>
                </button>
                <span class="cc-pager-page" id="hePage" role="status" aria-live="polite">Página 1 de 1</span>
                <button type="button" class="cc-pager-btn" id="heNext" aria-label="Página siguiente">
                    <span class="cc-pager-btn-txt">Siguiente</span>
                    @include('componentes._icon', ['name' => 'chevron-right'])
                </button>
            </div>
        </nav>
    @endif

</div>
@endsection

@push('scripts')
<script>
    (function () {
        var table = document.getElementById('heTable');
        if (!table) { return; }

        var q       = document.getElementById('heSearch');
        var ctxSel  = document.getElementById('heCtx');
        var catSel  = document.getElementById('heCat');
        var pendBtn = document.getElementById('hePending');
        var count   = document.getElementById('heCount');
        var noHit   = document.getElementById('heNoMatch');
        var clear   = document.getElementById('heClear');
        var facetBtns = Array.prototype.slice.call(document.querySelectorAll('.event-picker-facets .epf-chip'));
        var groups  = Array.prototype.slice.call(table.querySelectorAll('tbody.he-group'));
        var rows    = Array.prototype.slice.call(table.querySelectorAll('tbody.he-group tr[data-search]'));
        if (!rows.length) { return; }

        // Paginador de DISPLAY (puede faltar si el catálogo está vacío: sin filas → sin nav).
        var pager   = document.getElementById('hePager');
        var prevBtn = document.getElementById('hePrev');
        var nextBtn = document.getElementById('heNext');
        var pageLbl = document.getElementById('hePage');
        var showLbl = document.getElementById('heShowing');

        // Tamaño de página responsivo: ~25 escritorio / ~10 móvil, atado al breakpoint .cc-stack.
        var mq = window.matchMedia('(max-width: 767px)');
        function pageSize() { return mq.matches ? 10 : 25; }

        // Página actual (0-indexada) SIEMPRE sobre el set FILTRADO, nunca sobre el total.
        var page = 0;

        // Misma normalización que el heno del servidor (minúsculas + sin acentos).
        function norm(s) {
            return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function activeMarcos() {
            var set = [];
            facetBtns.forEach(function (b) {
                if (b.getAttribute('aria-pressed') === 'true') { set.push(b.getAttribute('data-marco')); }
            });
            return set;
        }

        // PUNTO ÚNICO DE VERDAD: ¿esta fila casa TODOS los filtros actuales? Todas las
        // dimensiones INTERSECTAN (Y lógico); dentro del marco, UNIÓN de las facetas activas.
        // No toca visibilidad ni bandas: sólo decide.
        function rowMatches(tr, term, marcos, ctx, cat, pending) {
            var rowMarcos = (tr.getAttribute('data-marcos') || '').split(' ').filter(Boolean);
            var okText  = term === '' || tr.getAttribute('data-search').indexOf(term) !== -1;
            var okMarco = marcos.length === 0 || marcos.some(function (m) { return rowMarcos.indexOf(m) !== -1; });
            var okCtx   = ctx === '' || tr.getAttribute('data-context') === ctx;
            var okCat   = cat === '' || tr.getAttribute('data-category') === cat;
            var okPend  = !pending || tr.getAttribute('data-pending') === '1';
            return okText && okMarco && okCtx && okCat && okPend;
        }

        // Compone filtro (intersección búsqueda + facetas + selects + toggle) → lista ordenada
        // de matches (orden del DOM, ya agrupado por contexto) → slice de la página → pinta.
        function render() {
            var term    = norm(q ? q.value : '').trim();
            var marcos  = activeMarcos();
            var ctx     = ctxSel ? ctxSel.value : '';
            var cat     = catSel ? catSel.value : '';
            var pending = pendBtn ? pendBtn.getAttribute('aria-pressed') === 'true' : false;

            // 1) Secuencia FILTRADA y PLANA en orden del DOM (respeta el agrupado por contexto).
            var matches = [];
            rows.forEach(function (tr) {
                if (rowMatches(tr, term, marcos, ctx, cat, pending)) { matches.push(tr); }
            });
            var nMatch = matches.length;

            // 2) Paginación sobre el set FILTRADO: totalPages = ceil(nMatch / size), NO del total.
            var size = pageSize();
            var totalPages = Math.max(1, Math.ceil(nMatch / size));
            if (page > totalPages - 1) { page = totalPages - 1; }
            if (page < 0) { page = 0; }
            var start = page * size;
            var end   = start + size;

            // 3) Oculta TODA fila; muestra sólo el slice [start, end) del set filtrado; así un
            //    match de la "página 2" es alcanzable navegando, no se pierde fuera del inicio.
            rows.forEach(function (tr) { tr.hidden = true; });
            for (var i = start; i < end && i < nMatch; i++) { matches[i].hidden = false; }

            // 4) Bandas de contexto: visibles SÓLO si el grupo tiene >=1 fila visible EN ESTA
            //    página. Si sus filas se parten entre páginas, la banda reaparece — es correcto.
            groups.forEach(function (tb) {
                var vis = tb.querySelectorAll('tr[data-search]:not([hidden])').length;
                tb.hidden = vis === 0;
            });

            // 5) Contador aria-live (refleja nMatch) + empty-state del filtro (sólo números).
            if (count) { count.innerHTML = '<strong>' + nMatch + '</strong> de ' + rows.length; }
            if (noHit) { noHit.hidden = nMatch !== 0; }

            // 6) UI del paginador (texto por textContent). Oculto si 0 resultados o <=1 página.
            if (pager) {
                if (nMatch === 0 || totalPages <= 1) {
                    pager.hidden = true;
                } else {
                    pager.hidden = false;
                    var from = start + 1;
                    var to   = Math.min(end, nMatch);
                    if (showLbl) { showLbl.textContent = 'Mostrando ' + from + '–' + to + ' de ' + nMatch; }
                    if (pageLbl) { pageLbl.textContent = 'Página ' + (page + 1) + ' de ' + totalPages; }
                    if (prevBtn) { prevBtn.disabled = page <= 0; prevBtn.setAttribute('aria-disabled', page <= 0 ? 'true' : 'false'); }
                    if (nextBtn) { nextBtn.disabled = page >= totalPages - 1; nextBtn.setAttribute('aria-disabled', page >= totalPages - 1 ? 'true' : 'false'); }
                }
            }
        }

        // Cualquier cambio de filtro/búsqueda/select/faceta/toggle → vuelve a la página 1.
        function filterChanged() { page = 0; render(); }

        function reset() {
            if (q) { q.value = ''; }
            if (ctxSel) { ctxSel.value = ''; }
            if (catSel) { catSel.value = ''; }
            if (pendBtn) { pendBtn.setAttribute('aria-pressed', 'false'); }
            facetBtns.forEach(function (b) { b.setAttribute('aria-pressed', 'false'); });
            filterChanged();
            if (q) { q.focus(); }
        }

        // Sin debounce a propósito: filtra nodos ya en el DOM, no hace fetch.
        if (q) { q.addEventListener('input', filterChanged); }
        if (ctxSel) { ctxSel.addEventListener('change', filterChanged); }
        if (catSel) { catSel.addEventListener('change', filterChanged); }
        if (pendBtn) {
            pendBtn.addEventListener('click', function () {
                var on = pendBtn.getAttribute('aria-pressed') === 'true';
                pendBtn.setAttribute('aria-pressed', on ? 'false' : 'true');
                filterChanged();
            });
        }
        facetBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                var on = b.getAttribute('aria-pressed') === 'true';
                b.setAttribute('aria-pressed', on ? 'false' : 'true');
                filterChanged();
            });
        });
        if (clear) { clear.addEventListener('click', reset); }

        // Navegación: sólo cambia de página (NO resetea filtros) y recomputa el slice.
        if (prevBtn) { prevBtn.addEventListener('click', function () { if (page > 0) { page--; render(); } }); }
        if (nextBtn) { nextBtn.addEventListener('click', function () { page++; render(); }); }

        // Cambio de breakpoint → re-clamp de la página actual y recomputa (nuevo tamaño).
        function onMq() { render(); }
        if (mq.addEventListener) { mq.addEventListener('change', onMq); }
        else if (mq.addListener) { mq.addListener(onMq); }

        render();
    })();
</script>
@endpush
