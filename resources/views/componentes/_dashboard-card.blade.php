{{--
    _dashboard-card.blade.php — Tarjeta KPI del dashboard, DATA-DRIVEN y reutilizable.
    Estilo "Cinematic Dark Glass": vidrio + franja de estado + sparkline (canvas).

    Recibe un arreglo $w (widget) con:
      - 'icon'  (obligatorio) clave del parcial _icon (Lucide).
      - 'label' (obligatorio) etiqueta ya traducida (__('...')).
      - 'value' (obligatorio) valor a mostrar.
      - 'route' (opcional)    URL de destino; si viene → la tarjeta es un <a> (hover con
                              sentido); si no → un <div> estático.
      - 'tone'  (opcional)    'ok'|'brand'|'accent'|'info'|'warn'|'danger' → color del
                              badge del icono, la franja y el sparkline. Default 'brand'.
      - 'pill'  (opcional)    texto de una píldora de estado (arriba a la derecha).
      - 'spark' (opcional)    arreglo de números → mini-gráfica ambiental al pie (canvas).

    Los estilos (.cc-kpi*) viven en la vista del dashboard (inicio.blade). Tokens
    semánticos + layer glass → dark mode real. PHP 7.4: sin match()/nullsafe.
--}}
@php
    $tones = [
        'ok'     => 'var(--ok)',
        'brand'  => 'var(--brand-primary)',
        'accent' => 'var(--brand-accent)',
        'info'   => 'var(--brand-accent)',
        'warn'   => 'var(--warn)',
        'danger' => 'var(--danger)',
    ];
    $tone   = $w['tone'] ?? 'brand';
    $accent = $tones[$tone] ?? $tones['brand'];
    $href   = $w['route'] ?? null;
    $icon   = $w['icon']  ?? 'info';
    $pill   = $w['pill']  ?? null;
    $spark  = isset($w['spark']) && is_array($w['spark']) ? $w['spark'] : null;
@endphp

@if($href)
    <a href="{{ $href }}" class="cc-kpi" style="--kpi-accent: {{ $accent }};">
@else
    <div class="cc-kpi cc-kpi--static" style="--kpi-accent: {{ $accent }};">
@endif
        <span class="cc-kpi__stripe" aria-hidden="true"></span>
        <div class="cc-kpi__top">
            <span class="cc-kpi__ico" aria-hidden="true">
                @include('componentes._icon', ['name' => $icon, 'class' => 'cc-kpi__ico-svg', 'label' => null])
            </span>
            @if($pill)
                <span class="cc-kpi__pill cc-kpi__pill--{{ $tone }}">{{ $pill }}</span>
            @endif
        </div>
        <span class="cc-kpi__value">{{ $w['value'] ?? '—' }}</span>
        <span class="cc-kpi__label">{{ $w['label'] ?? '' }}</span>
        @if($spark)
            <canvas class="cc-kpi__spark" aria-hidden="true"
                    data-spark="{{ implode(',', $spark) }}" data-tone="{{ $tone }}"></canvas>
        @endif
@if($href)
    </a>
@else
    </div>
@endif
