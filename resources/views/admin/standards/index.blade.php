@extends('layouts.app')
@section('title', 'Normas / Compliance')

{{-- NO @feature: el catálogo normativo es NÚCLEO (no una característica apagable), igual
     que su controlador (SafetyStandardController::guard() no checa ningún flag). --}}

@push('styles')
<style>
    /* ── Índice glass · lenguaje Cinematic Dark Glass (tokens de _brand-theme).
       Hermano de admin/consumables/index: mismas piezas cc-idx-* / cc-chip-* y una
       barra de filtros cliente, adaptadas al catálogo normativo. ── */
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

    /* Chip de marco: sobre .badge de Bootstrap (padding/forma) + .badge-XXX (color).
       Se le deja envolver dentro de la tabla para no fijar un ancho mínimo. */
    .std-table .badge{white-space:normal;font-weight:700;letter-spacing:.02em}

    /* ── Barra de filtros (cliente). Los controles reutilizan .form-control de Bootstrap
       a propósito: _brand-theme ya les da anillo de foco y alto táctil; aquí solo la piel
       de vidrio. ── */
    .std-filters{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem}
    .std-filters .form-control{background:var(--glass);border:1px solid var(--stroke);color:var(--text);border-radius:var(--radius-sm);font-size:.88rem;padding:.55rem .8rem}
    .std-filters .form-control::placeholder{color:var(--text-muted);opacity:1}
    .std-filters .form-control:focus{background:var(--glass-2);border-color:var(--brand-primary)}
    .std-f-search{position:relative;flex:1 1 280px;min-width:0}
    .std-f-search .form-control{padding-left:2.25rem}
    .std-f-search .std-f-ico{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none}
    .std-f-count{font-size:.78rem;color:var(--text-muted);font-weight:600;white-space:nowrap;margin-left:auto}
    .std-f-count strong{color:var(--text)}

    /* ── Fila de FACETAS por marco (mismo markup que _event-picker: .event-picker-facets
       / .epf-chip), pero themed por tokens para leer sobre el vidrio oscuro y con un
       HANDLER propio (togglea filas de la tabla; setFacet del typeahead no aplica aquí). ── */
    .event-picker-facets{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-bottom:.85rem}
    .event-picker-facets .epf-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)}
    .epf-chip{border:1px solid var(--stroke);background:var(--glass);color:var(--text-muted);border-radius:999px;padding:.35rem .75rem;font-size:.72rem;font-weight:700;letter-spacing:.03em;line-height:1;cursor:pointer;transition:background .12s,border-color .12s,color .12s}
    .epf-chip:hover{border-color:var(--stroke-2);color:var(--text)}
    .epf-chip[aria-pressed="true"]{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary)}

    /* Tabla dentro de .card (vidrio): fondo transparente + tinta por tokens. */
    .std-table{--bs-table-bg:transparent;color:var(--text);margin-bottom:0}
    .std-table thead th{text-transform:uppercase;font-size:.7rem;letter-spacing:.05em;color:var(--text-muted);font-weight:700;border-bottom:1px solid var(--stroke)}
    .std-table td,.std-table th{border-color:var(--stroke)}
    .std-table tbody tr:hover td{background:var(--glass-2)}
    /* El filtro esconde filas con [hidden]; en móvil .cc-stack les pone display:block,
       así que se refuerza aquí para que hidden gane sin depender del orden. */
    .std-table tr[hidden]{display:none !important}
    .std-code{font-variant-numeric:tabular-nums;font-weight:600;color:var(--text)}
    .std-cat{font-weight:600;color:var(--text)}
    /* El nombre de categoría es el enlace al detalle: primer gesto que la gente intenta
       y en móvil es un objetivo táctil grande y gratis. */
    a.std-cat{text-decoration:none;display:inline-block}
    a.std-cat:hover{color:var(--brand-primary);text-decoration:underline}
    .std-cat-en{display:block;font-size:.74rem;color:var(--text-muted);font-weight:500;margin-top:.15rem}
    .std-state{display:flex;flex-wrap:wrap;gap:.3rem}
    .std-link{color:var(--brand-primary);text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
    .std-link:hover{text-decoration:underline}
    .std-link .cc-ico{width:13px;height:13px}
    .std-muted{color:var(--text-muted)}
    .std-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:var(--radius-sm);border:1px solid var(--stroke);background:var(--glass);color:var(--text);transition:border-color .18s,background .18s,color .18s}
    .std-btn .cc-ico{width:15px;height:15px}
    .std-btn:hover{background:var(--glass-2);border-color:var(--stroke-2)}
    .std-btn-danger:hover{color:var(--danger);border-color:color-mix(in srgb,var(--danger) 45%,transparent)}
    .std-btn-ok:hover{color:var(--ok);border-color:color-mix(in srgb,var(--ok) 45%,transparent)}

    .cc-idx-empty{text-align:center;padding:3rem 1.5rem;color:var(--text-muted)}
    .cc-idx-empty .cc-ico{width:42px;height:42px;color:var(--text-muted);opacity:.55;margin-bottom:.7rem}
    .cc-idx-empty .fw-semibold{color:var(--text)}

    /* ── Paginador de DISPLAY (cliente): repagina el set YA FILTRADO, no el total.
       Reusa el lenguaje glass/.epf-chip. Se OCULTA con [hidden] cuando hay <=1 página;
       como .cc-pager fija display:flex, el [hidden] necesita !important para ganar
       (mismo truco que tr[hidden] de la tabla). ── */
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
       Cada td lleva data-label y un único hijo .std-cell para alinear etiqueta/valor. ── */
    @media (max-width:767px){
        table.cc-stack .std-cell{min-width:0}
        table.cc-stack .std-state{justify-content:flex-end}
        table.cc-stack .std-f-count{margin-left:0}
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
    // Enum cerrado del marco (espejo de la regla in: del controlador). Fuente única para
    // (a) validar el badge antes de usarlo como clase CSS y (b) ordenar las facetas.
    $badgeEnum = ['CSATF', 'OSHA', 'STPS', 'DOT', 'SCT', 'GENERAL'];

    $total = $standards->count();

    // Facetas: SOLO los marcos realmente presentes (nada de chips muertos). Se recorre el
    // enum para conservar su orden canónico y se marca cuál aparece (ya validado a GENERAL
    // si el dato trajera algo fuera del enum).
    $marcosPresent = [];
    foreach ($standards as $__s) {
        $__b = in_array($__s->regulation_badge, $badgeEnum, true) ? $__s->regulation_badge : 'GENERAL';
        $marcosPresent[$__b] = true;
    }
    $marcoChips = array_values(array_filter($badgeEnum, function ($m) use ($marcosPresent) {
        return isset($marcosPresent[$m]);
    }));
@endphp

<div class="container-fluid py-4 px-3 px-md-4">

    <div class="cc-idx-head">
        <div class="cc-idx-headline">
            <span class="cc-idx-icon">@include('componentes._icon', ['name' => 'shield', 'label' => 'Normas'])</span>
            <div>
                <div class="cc-idx-eyebrow">Seguridad · Catálogo</div>
                <h1 class="cc-idx-title">Normas / Compliance</h1>
                <p class="cc-idx-sub">Catálogo normativo (CSATF, OSHA, STPS…) que respalda los reportes de seguridad.</p>
            </div>
        </div>
        <div class="cc-idx-actions">
            @can('standards.create')
                <a href="{{ route('standards.create') }}" class="cc-idx-cta">
                    @include('componentes._icon', ['name' => 'plus']) Nueva norma
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
        {{-- Filtrado EN CLIENTE: el catálogo normativo cabe entero en la página (hoy ~78
             filas) y el filtro es instantáneo sin tocar el backend. UMBRAL: si pasa de unos
             cientos de filas, mover el filtro al SERVIDOR (where/like + índices) y paginar. --}}
        <div class="std-filters">
            <div class="std-f-search">
                <label for="stdSearch" class="visually-hidden">Buscar norma por código, categoría o marco</label>
                <span class="std-f-ico">@include('componentes._icon', ['name' => 'search'])</span>
                <input type="search" id="stdSearch" class="form-control" autocomplete="off"
                       placeholder="Buscar por código, categoría o marco…">
            </div>
            <span class="std-f-count" id="stdCount" role="status" aria-live="polite">
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
                <table class="table std-table cc-stack table-hover align-middle mb-0" id="stdTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Marco</th>
                            <th>Código</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Fuente</th>
                            {{-- «Ver» lo tiene cualquiera que pueda abrir este índice (la ruta ya
                                 exige standards.view), así que la columna de acciones existe SIEMPRE. --}}
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($standards as $standard)
                        @php
                            // El badge del dato NO se usa a ciegas como clase CSS: se valida
                            // contra el enum y cae a GENERAL si no coincide (defensa de origen
                            // aunque la regla in: del controlador ya lo acota).
                            $badgeClass = in_array($standard->regulation_badge, $badgeEnum, true)
                                ? $standard->regulation_badge
                                : 'GENERAL';

                            // Heno de búsqueda: minúsculas y SIN acentos, igual que la normalización
                            // del JS (quien teclea "prevencion" espera "Prevención").
                            $haystack = mb_strtolower(\Illuminate\Support\Str::ascii(implode(' ', array_filter([
                                $standard->regulation_code,
                                $standard->category_name,
                                $standard->category_name_en,
                                $standard->regulation_badge,
                            ]))));

                            // reference_url NO es de confianza para pintarse como enlace: lista
                            // blanca de esquema (http/https) vía parse_url, igual que consumables.
                            // La regla server es nullable|url|max:500, pero `url` acepta también
                            // ftp:/otros; aquí solo se enlazan http/https.
                            $refHref   = null;
                            $refRaw    = trim((string) $standard->reference_url);
                            if ($refRaw !== '') {
                                $refScheme = mb_strtolower((string) parse_url($refRaw, PHP_URL_SCHEME));
                                if ($refScheme === 'http' || $refScheme === 'https') { $refHref = $refRaw; }
                            }
                        @endphp
                        <tr data-search="{{ $haystack }}" data-marco="{{ $badgeClass }}">
                            <td class="ps-4" data-label="Marco">
                                <span class="badge badge-{{ $badgeClass }}">{{ $standard->regulation_badge }}</span>
                            </td>
                            <td data-label="Código">
                                <span class="std-code">{{ $standard->regulation_code }}</span>
                            </td>
                            <td data-label="Categoría">
                                <div class="std-cell">
                                    <a class="std-cat" href="{{ route('standards.show', $standard->id) }}">{{ $standard->category_name_localized }}</a>
                                    @if(filled($standard->category_name_en) && $standard->category_name_en !== $standard->category_name_localized)
                                        <span class="std-cat-en">{{ $standard->category_name_en }}</span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Estado">
                                <div class="std-state">
                                    @if($standard->isActive())
                                        <span class="cc-chip cc-chip-ok">Vigente</span>
                                    @else
                                        <span class="cc-chip cc-chip-neutral">Retirada</span>
                                    @endif
                                    @if($standard->isPendingVerification())
                                        <span class="cc-chip cc-chip-warn">
                                            @include('componentes._icon', ['name' => 'alert-triangle']) Pendiente
                                        </span>
                                    @elseif($standard->isVerified())
                                        <span class="cc-chip cc-chip-ok">
                                            @include('componentes._icon', ['name' => 'check']) Verificada
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Fuente">
                                @if($refHref !== null)
                                    <a href="{{ $refHref }}" target="_blank" rel="noopener" class="std-link small"
                                       title="Abre la fuente oficial en otra pestaña">
                                        @include('componentes._icon', ['name' => 'external-link']) Ver fuente
                                    </a>
                                @else
                                    <span class="std-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-2">
                                    <a class="std-btn" href="{{ route('standards.show', $standard->id) }}" title="Ver norma" aria-label="Ver norma {{ $standard->regulation_code }}">
                                        @include('componentes._icon', ['name' => 'eye', 'label' => 'Ver norma'])
                                    </a>
                                    @can('standards.manage')
                                        @if($standard->isPendingVerification())
                                            {{-- data-confirm, NO onsubmit: el patrón del repo evita el XSS de
                                                 meter datos en contexto JS. Ver componentes/_confirm-submit. --}}
                                            <form method="POST" action="{{ route('standards.verify', $standard->id) }}"
                                                  data-confirm="¿Confirmas que la norma «{{ $standard->regulation_code }}» es correcta? Quedará registrada tu verificación.">
                                                @csrf
                                                <button type="submit" class="std-btn std-btn-ok" title="Verificar" aria-label="Verificar {{ $standard->regulation_code }}">
                                                    @include('componentes._icon', ['name' => 'check', 'label' => 'Verificar'])
                                                </button>
                                            </form>
                                        @endif
                                        {{-- OJO: Editar es standards.MANAGE (no create): el safety-officer
                                             AGREGA pero NO edita; solo un manage corrige/verifica/retira. --}}
                                        <a class="std-btn" href="{{ route('standards.edit', $standard->id) }}" title="Editar" aria-label="Editar {{ $standard->regulation_code }}">
                                            @include('componentes._icon', ['name' => 'pencil', 'label' => 'Editar'])
                                        </a>
                                        @if($standard->isActive())
                                            {{-- Retirar = is_active=0 (NO borra): la norma sale de la captura pero
                                                 sigue viva para el histórico. PUT + data-confirm. --}}
                                            <form method="POST" action="{{ route('standards.deactivate', $standard->id) }}"
                                                  data-confirm="¿Retirar «{{ $standard->regulation_code }}» del catálogo de captura? El histórico que la referencia se sigue resolviendo.">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="std-btn std-btn-danger" title="Retirar" aria-label="Retirar {{ $standard->regulation_code }}">
                                                    @include('componentes._icon', ['name' => 'x-circle', 'label' => 'Retirar'])
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('standards.reactivate', $standard->id) }}">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="std-btn std-btn-ok" title="Reactivar" aria-label="Reactivar {{ $standard->regulation_code }}">
                                                    @include('componentes._icon', ['name' => 'rotate-ccw', 'label' => 'Reactivar'])
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
                                <div class="cc-idx-empty">
                                    @include('componentes._icon', ['name' => 'shield', 'label' => 'Sin normas'])
                                    <div class="fw-semibold">Aún no hay normas registradas.</div>
                                    @can('standards.create')
                                        <small>Crea la primera con el botón «Nueva norma».</small>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @endforelse

                        {{-- Empty-state del FILTRO — distinto del catálogo vacío: «no hay normas»
                             es un catálogo vacío, «nada casa» es una búsqueda sin resultados. --}}
                        <tr id="stdNoMatch" hidden>
                            <td colspan="6">
                                <div class="cc-idx-empty">
                                    @include('componentes._icon', ['name' => 'search', 'label' => 'Sin resultados'])
                                    <div class="fw-semibold">Ninguna norma coincide con el filtro.</div>
                                    <small>Prueba con otro término o <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="stdClear">limpia los filtros</button>.</small>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($total)
        {{-- Paginador de DISPLAY (cliente): repagina SOLO el resultado filtrado. Nace oculto
             (hidden) y el JS lo muestra únicamente cuando el set filtrado supera 1 página. --}}
        <nav class="cc-pager" id="stdPager" aria-label="Paginación de normas" hidden>
            <span class="cc-pager-showing" id="stdShowing"></span>
            <div class="cc-pager-nav">
                <button type="button" class="cc-pager-btn" id="stdPrev" aria-label="Página anterior">
                    @include('componentes._icon', ['name' => 'chevron-left'])
                    <span class="cc-pager-btn-txt">Anterior</span>
                </button>
                <span class="cc-pager-page" id="stdPage" role="status" aria-live="polite">Página 1 de 1</span>
                <button type="button" class="cc-pager-btn" id="stdNext" aria-label="Página siguiente">
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
        var table = document.getElementById('stdTable');
        if (!table) { return; }

        var q     = document.getElementById('stdSearch');
        var count = document.getElementById('stdCount');
        var noHit = document.getElementById('stdNoMatch');
        var clear = document.getElementById('stdClear');
        var facetBtns = Array.prototype.slice.call(document.querySelectorAll('.event-picker-facets .epf-chip'));
        var rows  = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-search]'));
        if (!rows.length) { return; }

        // Paginador de DISPLAY (puede faltar si el catálogo está vacío: sin filas → sin nav).
        var pager   = document.getElementById('stdPager');
        var prevBtn = document.getElementById('stdPrev');
        var nextBtn = document.getElementById('stdNext');
        var pageLbl = document.getElementById('stdPage');
        var showLbl = document.getElementById('stdShowing');

        // Tamaño de página responsivo: ~25 escritorio / ~10 móvil, atado al breakpoint .cc-stack.
        var mq = window.matchMedia('(max-width: 767px)');
        function pageSize() { return mq.matches ? 10 : 25; }

        // Página actual (0-indexada) SIEMPRE sobre el set FILTRADO, nunca sobre el total.
        var page = 0;

        // Misma normalización que el heno del servidor (minúsculas + sin acentos): así
        // "prevencion", "Prevención" y "PREVENCIÓN" son la misma búsqueda.
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

        // PUNTO ÚNICO DE VERDAD: ¿esta fila casa los filtros actuales? (texto Y marco; dentro
        // del marco, unión de las facetas activas). No toca visibilidad: sólo decide.
        function rowMatches(tr, term, marcos) {
            var okText  = term === '' || tr.getAttribute('data-search').indexOf(term) !== -1;
            var okMarco = marcos.length === 0 || marcos.indexOf(tr.getAttribute('data-marco')) !== -1;
            return okText && okMarco;
        }

        // Compone filtro → lista ordenada de matches → slice de la página → pinta.
        function render() {
            var term   = norm(q ? q.value : '').trim();
            var marcos = activeMarcos();

            // 1) Secuencia FILTRADA en orden del DOM.
            var matches = [];
            rows.forEach(function (tr) {
                if (rowMatches(tr, term, marcos)) { matches.push(tr); }
            });
            var nMatch = matches.length;

            // 2) Paginación sobre el set FILTRADO: totalPages = ceil(nMatch / size), NO del total.
            var size = pageSize();
            var totalPages = Math.max(1, Math.ceil(nMatch / size));
            if (page > totalPages - 1) { page = totalPages - 1; }
            if (page < 0) { page = 0; }
            var start = page * size;
            var end   = start + size;

            // 3) Oculta TODO y muestra sólo el slice [start, end) del set filtrado; así un match
            //    de la "página 2" es alcanzable navegando, no se pierde por caer fuera del inicio.
            rows.forEach(function (tr) { tr.hidden = true; });
            for (var i = start; i < end && i < nMatch; i++) { matches[i].hidden = false; }

            // 4) Contador aria-live (refleja nMatch) + empty-state del filtro (sólo números).
            if (count) { count.innerHTML = '<strong>' + nMatch + '</strong> de ' + rows.length; }
            if (noHit) { noHit.hidden = nMatch !== 0; }

            // 5) UI del paginador (texto por textContent). Oculto si 0 resultados o <=1 página.
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

        // Cualquier cambio de filtro/búsqueda/faceta → vuelve a la página 1 y recomputa.
        function filterChanged() { page = 0; render(); }

        function reset() {
            if (q) { q.value = ''; }
            facetBtns.forEach(function (b) { b.setAttribute('aria-pressed', 'false'); });
            filterChanged();
            if (q) { q.focus(); }
        }

        // Sin debounce a propósito: filtra nodos ya en el DOM, no hace fetch.
        if (q) { q.addEventListener('input', filterChanged); }
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
