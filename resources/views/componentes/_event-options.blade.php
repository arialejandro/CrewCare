{{--
    _event-options.blade.php — Sub-parcial COMPARTIDO (verdadero 5×1) del loop de
    <option> del catálogo de "eventos posibles", agrupado por CONTEXTO.

    Lo consumen (vía @include):
      · componentes/_event-picker.blade.php   → los 4 reportes de CAPTURA (modo rico).
      · admin/scoutings/_hazard-row.blade.php  → la fila de la tabla de peligros (rico).

    Emite SOLO <optgroup>/<option>. NO abre/cierra el <select>, NO pinta el <option>
    placeholder y NO incluye _badge-tokens: un <style> dentro de un <select> es HTML
    inválido; el color de los chips .badge-XXX lo carga el HOST a nivel de página.

    Parámetros:
      · $hazardEvents   Collection|array de HazardEvent (con ->standards ya cargado).
      · $selectedValue  escalar a comparar contra cada $ev->id (default '').

    Cada <option> emite los MISMOS data-* de siempre (byte-idénticos a los que ya
    emitían el picker y la fila): data-badges, data-codes, data-category, data-desc
    (EN si locale=en y description_en no vacío; si no, ES), data-l, data-c, y selected.
    Texto = "category_label · name_localized" (o solo name_localized si la categoría
    viene vacía). Todo por escape Blade {{ }} (HTML de atributo/optgroup, seguro).
--}}
@php
    $__evs         = collect($hazardEvents ?? []);
    $selectedValue = $selectedValue ?? '';
    $__ctxs        = \App\Models\HazardEvent::contexts();
    $__grouped     = $__evs->groupBy('context');
    // Regla de locale para la descripción — MISMA que _event-picker y que
    // HazardEvent::getNameLocalizedAttribute(): en + description_en no vacío → EN; si no, ES.
    $__en          = app()->getLocale() === 'en';
@endphp
@foreach($__ctxs as $__ctxKey => $__ctxLabel)
    @php $__items = $__grouped->get($__ctxKey); @endphp
    @if($__items && $__items->count())
        <optgroup label="{{ $__ctxLabel }}">
            @foreach($__items as $ev)
                @php
                    $__desc  = ($__en && !empty($ev->description_en)) ? $ev->description_en : $ev->description_es;
                    $__label = $ev->category_label ? ($ev->category_label . ' · ' . $ev->name_localized) : $ev->name_localized;
                @endphp
                <option value="{{ $ev->id }}"
                    data-badges="{{ $ev->standards->pluck('regulation_badge')->filter()->unique()->implode(', ') }}"
                    data-codes="{{ $ev->standards->pluck('regulation_code')->filter()->implode(' · ') }}"
                    data-category="{{ $ev->category_label }}"
                    data-desc="{{ $__desc }}"
                    data-l="{{ $ev->default_likelihood }}"
                    data-c="{{ $ev->default_consequence }}"
                    {{ (string) $selectedValue === (string) $ev->id ? 'selected' : '' }}>{{ $__label }}</option>
            @endforeach
        </optgroup>
    @endif
@endforeach
