{{--
    Control de ajuste para el diseñador de gafete: SOLO botones (sin escribir, sin unidades).
    Flechas sencillas = ±1, dobles = ±10. Con límites (min/max) → un valor fuera de rango NO
    "desaparece" el elemento de la tarjeta.

    Accesibilidad (arreglo bloqueante): los botones SON enfocables (sin tabindex="-1") y
    llevan aria-label; el grupo soporta teclado (ArrowUp/ArrowDown = ±1, con Shift = ±10)
    reutilizando el mismo handler del diseñador (applyStep).

    Iconos según el eje ($mode):
      vertical   → arriba/abajo (chevron-up / chevron-down)
      horizontal → izquierda/derecha (chevron-left / chevron-right)
      size       → contraer/expandir (chevron-down / chevron-up)
    Los botones ±10 muestran doble chevron para diferenciarse de ±1.

    Variables: $label, $name, $value (requeridas); $mode (default 'size'); $min, $max.
--}}
@php
    $mode = $mode ?? 'size';
    $min = $min ?? 0;
    $max = $max ?? 200;
    // Icono por eje: [negativo, positivo]. El doble (±10) se dibuja repitiendo el icono.
    $axis = [
        'vertical'   => ['chevron-up', 'chevron-down'],
        'horizontal' => ['chevron-left', 'chevron-right'],
        'size'       => ['chevron-down', 'chevron-up'],
    ];
    $ic = $axis[$mode] ?? $axis['size'];
    // Los dos botones a la izquierda del valor (−10, −1) y los dos a la derecha (+1, +10).
    $before = [
        ['step' => -10, 'icon' => $ic[0], 'double' => true,  'sfx' => '−10'],
        ['step' => -1,  'icon' => $ic[0], 'double' => false, 'sfx' => '−1'],
    ];
    $after = [
        ['step' => 1,   'icon' => $ic[1], 'double' => false, 'sfx' => '+1'],
        ['step' => 10,  'icon' => $ic[1], 'double' => true,  'sfx' => '+10'],
    ];
@endphp
<label class="form-label mb-1 small fw-semibold">{{ $label }}</label>
<div class="input-group input-group-sm badge-stepper" data-min="{{ $min }}" data-max="{{ $max }}">
    @foreach ($before as $s)
        <button type="button"
                class="btn btn-outline-secondary js-step px-2 {{ $s['double'] ? 'is-double' : '' }}"
                data-step="{{ $s['step'] }}"
                title="{{ $s['sfx'] }}"
                aria-label="{{ $label }} {{ $s['sfx'] }}">
            @include('componentes._icon', ['name' => $s['icon'], 'class' => 'cc-ico', 'label' => null])
            @if ($s['double'])
                @include('componentes._icon', ['name' => $s['icon'], 'class' => 'cc-ico cc-ico-2', 'label' => null])
            @endif
        </button>
    @endforeach
    <input type="number" name="{{ $name }}" value="{{ $value }}" min="{{ $min }}" max="{{ $max }}" step="1"
           class="form-control text-center js-live" aria-label="{{ $label }}" tabindex="-1" readonly>
    @foreach ($after as $s)
        <button type="button"
                class="btn btn-outline-secondary js-step px-2 {{ $s['double'] ? 'is-double' : '' }}"
                data-step="{{ $s['step'] }}"
                title="{{ $s['sfx'] }}"
                aria-label="{{ $label }} {{ $s['sfx'] }}">
            @include('componentes._icon', ['name' => $s['icon'], 'class' => 'cc-ico', 'label' => null])
            @if ($s['double'])
                @include('componentes._icon', ['name' => $s['icon'], 'class' => 'cc-ico cc-ico-2', 'label' => null])
            @endif
        </button>
    @endforeach
</div>
