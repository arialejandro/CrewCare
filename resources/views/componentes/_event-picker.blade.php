{{--
    _event-picker.blade.php — Selector ÚNICO de "evento posible" (2026-07-13 · rico 2026-07-17).

    Reemplaza las listas solapadas de los 5 reportes (category_name + standards[] +
    hz_key del Scouting) por UN control agrupado por CONTEXTO. Al elegir un evento,
    el controlador auto-etiqueta su norma (snapshot badge/code) y adjunta sus normas
    al pivote `standardables`. Esta vista solo pinta el <select> + un preview de las
    normas mapeadas (JS progresivo, no obligatorio).

    MEJORA RICA (2026-07-17): como el typeahead OCULTA el <select> nativo y pinta su
    propio combobox, marcamos el <select> con data-ta-rich="1" para que cada <li> del
    buscador muestre chips de color de sus marcos, y añadimos una FILA DE FILTRO por marco
    (faceta) encima del control. El filtro es de VISTA: se intersecta con el texto y nunca
    cambia el valor elegido. El preview del evento se reescribe como chips de color +
    descripción + Prob./Cons.

    Uso (Bootstrap 5, vistas de CREAR):
      @include('componentes._event-picker', [
          'hazardEvents' => $hazardEvents,   // HazardEvent::active()->with('standards')->orderBy('context')->orderBy('sort_order')->get()
          'name'         => 'hazard_event_id',
          'selected'     => old('hazard_event_id', $report->hazard_event_id ?? null),
          'required'     => false,
          'id'           => 'hazard_event_id',
      ])

    Degradación: si $hazardEvents viene vacío (p. ej. PROD sin sembrar), el select
    queda con solo la opción neutra → el formulario sigue funcionando.
--}}
@php
    $hazardEvents = ($hazardEvents ?? collect());
    $name         = $name ?? 'hazard_event_id';
    $fieldId      = $id ?? $name;
    $selected     = $selected ?? old($name);
    $required     = $required ?? false;
    // El loop de <option> (contextos, agrupación y la regla de locale de la
    // descripción) vive ahora en el sub-parcial compartido _event-options.
@endphp

{{-- CSS de color de los marcos (.badge-XXX). @once deduplica en el DSR (que también lo
     carga vía _report-v2-head). Al incluirlo aquí, las 4 vistas de CAPTURA reciben el
     color por el simple hecho de montar el picker. --}}
@include('componentes._badge-tokens')

{{-- Fila de FILTRO por marco (faceta). Botones reales, aria-pressed, táctil ≥44px.
     "Ninguno activo" = mostrar todo. DOT junto a OSHA y SCT junto a STPS. --}}
@if($hazardEvents->count())
    <div class="event-picker-facets" data-facet-for="{{ $fieldId }}" role="group" aria-label="Filtrar por marco normativo">
        <span class="epf-label">Filtrar:</span>
        @foreach(['CSATF', 'OSHA', 'DOT', 'STPS', 'SCT'] as $mk)
            <button type="button" class="epf-chip" data-marco="{{ $mk }}" aria-pressed="false">{{ $mk }}</button>
        @endforeach
    </div>
@endif

<select name="{{ $name }}" id="{{ $fieldId }}"
        class="form-select event-picker js-typeahead"
        data-ta-rich="1"
        data-preview="{{ $fieldId }}-preview"
        {{ $required ? 'required' : '' }}>
    <option value="">{{ $required ? 'Selecciona el evento…' : '— Sin evento / No aplica —' }}</option>
    {{-- Loop de opciones extraído al sub-parcial COMPARTIDO (verdadero 5×1). Emite
         data-* byte-idénticos + texto "categoría · nombre" que antes vivían aquí. --}}
    @include('componentes._event-options', ['hazardEvents' => $hazardEvents, 'selectedValue' => $selected])
</select>

<div id="{{ $fieldId }}-preview" class="event-picker-preview small text-muted mt-1"></div>

{{-- Buscador (typeahead) por mejora progresiva sobre el <select> de arriba. --}}
@include('componentes._typeahead')

@once
@push('scripts')
<style>
    .event-picker-facets { display:flex; flex-wrap:wrap; align-items:center; gap:.4rem; margin-bottom:.5rem; }
    .event-picker-facets .epf-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; }
    .epf-chip { border:1px solid #cbd5e1; background:#fff; color:#475569; border-radius:999px; padding:.35rem .75rem; font-size:.72rem; font-weight:700; letter-spacing:.03em; line-height:1; cursor:pointer; transition:background .12s, border-color .12s, color .12s; }
    .epf-chip:hover { border-color:#94a3b8; color:#1f2937; }
    .epf-chip[aria-pressed="true"] { background:#1f2937; border-color:#1f2937; color:#fff; }
    .event-picker-preview .epp-chips { display:flex; flex-wrap:wrap; gap:.3rem; margin-bottom:.3rem; }
    .event-picker-preview .epp-pc { background:#475569; color:#fff; }
    .event-picker-preview .epp-codes { color:#64748b; font-size:.8rem; margin-bottom:.2rem; }
    .event-picker-preview .epp-desc { color:#475569; font-size:.82rem; line-height:1.35; }
    @media (pointer: coarse) { .epf-chip { min-height:44px; } }
    @media (prefers-color-scheme: dark) {
        .event-picker-facets .epf-label { color:#9ca3af; }
        .epf-chip { background:#1f2937; border-color:#374151; color:#cbd5e1; }
        .epf-chip:hover { border-color:#4b5563; color:#f3f4f6; }
        .epf-chip[aria-pressed="true"] { background:#e5e7eb; border-color:#e5e7eb; color:#111827; }
        .event-picker-preview .epp-codes { color:#94a3b8; }
        .event-picker-preview .epp-desc { color:#cbd5e1; }
    }
</style>
<script>
(function () {
    // Enum BLANCO de marcos (misma defensa que el typeahead): solo estos se pintan como
    // chip y se usan como sufijo .badge-XXX. Orden: DOT junto a OSHA, SCT junto a STPS.
    var BADGE_ENUM  = { CSATF:1, OSHA:1, STPS:1, DOT:1, SCT:1, GENERAL:1, AMAZON:1 };
    var BADGE_ORDER = { CSATF:0, OSHA:1, DOT:2, STPS:3, SCT:4, GENERAL:5, AMAZON:6 };

    // ── PREVIEW del evento elegido (delegado; soporta múltiples selects/filas). ──
    // TODO lo que viene de la BD (desc/codes/prob/cons) se pinta con createElement +
    // textContent; los chips de marco se validan contra el enum antes de usar su nombre
    // como sufijo de clase. Cero innerHTML con datos de BD = cero XSS de DOM.
    document.addEventListener('change', function (e) {
        var sel = e.target;
        if (!sel || !sel.classList || !sel.classList.contains('event-picker')) { return; }
        var previewId = sel.getAttribute('data-preview');
        var box = previewId ? document.getElementById(previewId) : (sel.parentNode ? sel.parentNode.querySelector('.event-picker-preview') : null);
        if (!box) { return; }
        while (box.firstChild) { box.removeChild(box.firstChild); }
        if (!sel.value) { return; }
        var opt = sel.options[sel.selectedIndex];
        if (!opt) { return; }

        var raw   = opt.getAttribute('data-badges') || '';
        var codes = opt.getAttribute('data-codes') || '';
        var desc  = opt.getAttribute('data-desc') || '';
        var l     = opt.getAttribute('data-l') || '';
        var c     = opt.getAttribute('data-c') || '';

        var marcos = raw.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s && BADGE_ENUM[s]; });
        var seen = {}, uniq = [];
        marcos.forEach(function (m) { if (!seen[m]) { seen[m] = 1; uniq.push(m); } });
        uniq.sort(function (a, b) { return BADGE_ORDER[a] - BADGE_ORDER[b]; });

        if (uniq.length || (l && c)) {
            var chips = document.createElement('div');
            chips.className = 'epp-chips';
            uniq.forEach(function (m) {
                var sp = document.createElement('span');
                sp.className = 'badge badge-' + m; // m ∈ BADGE_ENUM
                sp.textContent = m;
                chips.appendChild(sp);
            });
            if (l && c) {
                var pc = document.createElement('span');
                pc.className = 'badge epp-pc';
                pc.textContent = 'Prob. ' + l + ' · Cons. ' + c; // números de BD → textContent
                chips.appendChild(pc);
            }
            box.appendChild(chips);
        }
        if (codes) {
            var cd = document.createElement('div');
            cd.className = 'epp-codes';
            cd.textContent = codes;
            box.appendChild(cd);
        }
        if (desc) {
            var d = document.createElement('div');
            d.className = 'epp-desc';
            d.textContent = desc;
            box.appendChild(d);
        }
    });

    // ── FILA DE FILTRO por marco → API de faceta del typeahead de ESTE picker. ──
    // Delegado y montado una sola vez: tolera montar antes de que el typeahead mejore
    // (setFacet guarda el deseo y lo aplica al construir). Alternar un chip recalcula el
    // set activo y lo
    // pasa a setFacet; el typeahead intersecta con el texto y NUNCA muta sel.value.
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) { return; }
        var chip = t.closest('.epf-chip');
        if (!chip) { return; }
        var row = chip.closest('.event-picker-facets');
        if (!row) { return; }
        var pressed = chip.getAttribute('aria-pressed') === 'true';
        chip.setAttribute('aria-pressed', pressed ? 'false' : 'true');
        var active = [];
        row.querySelectorAll('.epf-chip[aria-pressed="true"]').forEach(function (ch) {
            active.push(ch.getAttribute('data-marco'));
        });
        var selId = row.getAttribute('data-facet-for');
        var sel = selId ? document.getElementById(selId) : null;
        if (sel && window.CCTypeahead && window.CCTypeahead.setFacet) {
            window.CCTypeahead.setFacet(sel, active);
        }
    });
})();
</script>
@endpush
@endonce
