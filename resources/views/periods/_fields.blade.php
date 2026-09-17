{{-- Campos COMPARTIDOS del periodo (alta + edición). $period = modelo|null. Muestra/oculta la
     VENTANA (semanal/quincenal) vs. el DÍA TRABAJADO (day-player) según la frecuencia. El <form>
     y el botón los pone cada vista; aquí solo van las columnas dentro de un .row g-3.js-period-form. --}}
@php
    $pp    = $period ?? null;
    $dv    = fn ($c) => $c ? $c->format('Y-m-d') : '';
    $freqV = old('frequency', optional($pp)->frequency);
@endphp
<div class="col-sm-6 col-md-3">
    <label class="form-label small text-muted">{{ __('Frecuencia') }}</label>
    <select name="frequency" class="form-select js-freq" required>
        @foreach($frequencies as $val => $label)
            <option value="{{ $val }}" @selected($freqV === $val)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-sm-6 col-md-3">
    <label class="form-label small text-muted">{{ __('Etiqueta') }} <span class="text-muted">({{ __('opcional') }})</span></label>
    <input type="text" name="label" value="{{ old('label', optional($pp)->label) }}" class="form-control" placeholder="{{ __('Semana 5, Quincena ago-2…') }}">
</div>

{{-- Ventana (semanal/quincenal). Oculta para day-player: su ventana ES el día trabajado. --}}
<div class="col-sm-6 col-md-3 js-window-field">
    <label class="form-label small text-muted">{{ __('Abre recepción') }}</label>
    <input type="date" name="opens_on" value="{{ old('opens_on', $dv(optional($pp)->opens_on)) }}" class="form-control js-window-input">
</div>
<div class="col-sm-6 col-md-3 js-window-field">
    <label class="form-label small text-muted">{{ __('Cierra recepción') }}</label>
    <input type="date" name="closes_on" value="{{ old('closes_on', $dv(optional($pp)->closes_on)) }}" class="form-control js-window-input">
</div>

{{-- Day player: el DÍA que trabajó (su ventana) + la persona. Se pide solo aquí; opens/closes se derivan. --}}
<div class="col-sm-6 col-md-3 js-dp-field">
    <label class="form-label small text-muted">{{ __('Día trabajado') }}</label>
    <input type="date" name="worked_on" value="{{ old('worked_on', $dv(optional($pp)->worked_on)) }}" class="form-control js-dp-date">
</div>
<div class="col-sm-6 col-md-5 js-dp-field">
    <label class="form-label small text-muted">{{ __('Persona (day player)') }}</label>
    <select name="payee_id" class="form-select js-typeahead">
        <option value="">{{ __('—') }}</option>
        @foreach($dayPlayerPayees as $dp)
            <option value="{{ $dp->id }}" @selected((string) old('payee_id', optional($pp)->payee_id) === (string) $dp->id)>{{ $dp->name }}</option>
        @endforeach
    </select>
</div>

@once
@push('scripts')
<script>
(function () {
    document.querySelectorAll('.js-period-form').forEach(function (form) {
        var freq = form.querySelector('.js-freq');
        if (!freq) { return; }
        var DAY_PLAYER = 'day_player';
        function sync() {
            var isDp = freq.value === DAY_PLAYER;
            form.querySelectorAll('.js-window-field').forEach(function (el) { el.style.display = isDp ? 'none' : ''; });
            form.querySelectorAll('.js-dp-field').forEach(function (el) { el.style.display = isDp ? '' : 'none'; });
            // required solo sobre inputs de FECHA visibles: la persona la valida el servidor (required
            // en un <select> que el typeahead oculta rompería el submit HTML5).
            form.querySelectorAll('.js-window-input').forEach(function (el) { el.required = !isDp; });
            form.querySelectorAll('.js-dp-date').forEach(function (el) { el.required = isDp; });
        }
        freq.addEventListener('change', sync);
        sync();
    });
})();
</script>
@endpush
@endonce
