{{--
    _dashboard-calendar.blade.php — CINTILLO DE CALENDARIO del tablero (2026-07-24).

    Contesta de un vistazo las dos preguntas que producción hace todos los días:
    ¿en qué día vamos? y ¿cuánto falta para el wrap?

    EL NÚMERO NO SE TECLEA. Sale de App\Support\ProductionCalendar, derivado de las fechas con
    reporte diario: el primer día con DSR es 1, el siguiente día distinto es 2. La prep va en
    negativo contando de lunes a sábado —el domingo no cuenta— y se dice en SEMANAS mientras
    falte más de una, y en DÍAS a partir de la semana -1.

    LO QUE NO SE INVENTA: si la producción no tiene `end_date`, no hay countdown y se dice que
    no lo hay. Un tablero que se saca un wrap de la manga es peor que un tablero incompleto.

    Recibe: $cal (array) ['hoy','en_rodaje','countdown','produccion'] desde HomeController.
--}}
@php
    $cd = isset($cal['countdown']) ? $cal['countdown'] : null;
@endphp

<div class="cc-cal {{ !empty($cal['en_rodaje']) ? 'cc-cal--rodaje' : '' }}">

    <div class="cc-cal__now">
        <span class="cc-cal__h3">{{ __('dashboard.cal_today') }}</span>
        <span class="cc-cal__day">{{ $cal['hoy'] }}</span>
        @if(!empty($cal['produccion']))
            <span class="cc-cal__prod">{{ $cal['produccion'] }}</span>
        @endif
    </div>

    <div class="cc-cal__sep" aria-hidden="true"></div>

    <div class="cc-cal__wrap">
        <span class="cc-cal__h3">{{ __('dashboard.cal_wrap') }}</span>
        @if($cd)
            {{-- Rebasar la fecha de wrap NO es un error: entre el 3 % y el 10 % de las
                 producciones se extienden. Por eso se dice en ámbar, no en rojo, y se sigue
                 mostrando la fecha original en vez de esconderla. --}}
            <span class="cc-cal__count {{ $cd['over'] ? 'cc-cal__count--over' : '' }}">{{ $cd['label'] }}</span>
            <span class="cc-cal__date">{{ $cd['date']->format('d/m/Y') }}</span>
        @else
            <span class="cc-cal__count cc-cal__count--none">{{ __('dashboard.cal_wrap_unset') }}</span>
            <span class="cc-cal__date">{{ __('dashboard.cal_wrap_unset_hint') }}</span>
        @endif
    </div>

</div>

@push('styles')
<style>
    .cc-cal {
        display: flex; flex-wrap: wrap; align-items: center; gap: 1rem 1.5rem;
        padding: .9rem 1.15rem; margin-bottom: 1.25rem;
        border: 1px solid var(--stroke); border-radius: 16px;
        background: var(--glass);
    }
    /* En rodaje, la barra de acento a la izquierda: el mismo recurso que usan las tarjetas KPI
       para decir "esto está vivo". En prep se queda sin barra, que también es información. */
    .cc-cal--rodaje { border-left: 3px solid var(--brand-accent); }

    .cc-cal__now, .cc-cal__wrap { display: flex; flex-direction: column; gap: .1rem; min-width: 0; }
    .cc-cal__h3 {
        font-size: .64rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
        color: var(--text-muted);
    }
    .cc-cal__day {
        font-size: 1.45rem; font-weight: 800; line-height: 1.1;
        font-variant-numeric: tabular-nums; color: var(--text);
    }
    .cc-cal__prod { font-size: .72rem; color: var(--text-muted); }

    .cc-cal__sep { width: 1px; align-self: stretch; background: var(--stroke); }

    .cc-cal__count {
        font-size: 1.05rem; font-weight: 700; line-height: 1.2;
        font-variant-numeric: tabular-nums; color: var(--brand-primary);
    }
    .cc-cal__count--over  { color: var(--warn, #b45309); }
    .cc-cal__count--none  { font-size: .92rem; font-weight: 600; color: var(--text-muted); }
    .cc-cal__date { font-size: .72rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }

    /* En móvil el separador vertical no tiene sentido: se convierte en una línea horizontal. */
    @media (max-width: 640px) {
        .cc-cal { gap: .75rem; }
        .cc-cal__sep { width: 100%; height: 1px; align-self: auto; }
    }
</style>
@endpush
