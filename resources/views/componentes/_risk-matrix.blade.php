{{-- =====================================================================
     Componente REUTILIZABLE: Matriz de riesgo Amazon MGM (5×5).
     Valores EXACTOS del formulario oficial. Auto-contenido (CSS/JS con
     prefijo .rmx- y @once) para funcionar tanto en el form Bootstrap como
     en los documentos Tailwind (show / amazon) sin depender de ninguno.

     Parámetros (@include):
       'lang'        => 'es' | 'en'   (default 'es')  — idioma de las etiquetas.
       'interactive' => bool          (default true)  — cruz + lectura al pasar/tocar.
       'legend'      => bool          (default true)  — leyenda de clasificación → acción.

     La matriz es casi neutral al idioma (letras/números/color); solo cambian
     las etiquetas de ejes y palabras de clasificación → bilingüe "listo".
     ===================================================================== --}}
@php
    $lang        = $lang ?? 'es';
    $interactive = $interactive ?? true;
    $legend      = $legend ?? true;

    $L = [
        'es' => [
            'likelihood' => 'Probabilidad', 'consequence' => 'Consecuencia',
            'rows' => ['A' => 'Casi seguro', 'B' => 'Probable', 'C' => 'Moderado', 'D' => 'Improbable', 'E' => 'Raro'],
            'cols' => [1 => 'Insignificante', 2 => 'Menor', 3 => 'Moderada', 4 => 'Mayor', 5 => 'Catastrófica'],
            'rat'  => ['L' => 'Bajo', 'M' => 'Medio', 'H' => 'Alto', 'E' => 'Muy alto'],
            'act'  => [
                'L' => 'Procedimientos de rutina documentados.',
                'M' => 'Procede con aprobación del supervisor y método de trabajo seguro.',
                'H' => 'Solo procede con el riesgo reducido al mínimo razonable y aprobación.',
                'E' => 'No debe proceder; reduce el riesgo antes de continuar.',
            ],
            'pick' => 'Pasa el cursor o toca una celda para clasificar el riesgo.',
        ],
        'en' => [
            'likelihood' => 'Likelihood', 'consequence' => 'Consequence',
            'rows' => ['A' => 'Almost certain', 'B' => 'Likely', 'C' => 'Moderate', 'D' => 'Unlikely', 'E' => 'Rare'],
            'cols' => [1 => 'Insignificant', 2 => 'Minor', 3 => 'Moderate', 4 => 'Major', 5 => 'Catastrophic'],
            'rat'  => ['L' => 'Low', 'M' => 'Medium', 'H' => 'High', 'E' => 'Very high'],
            'act'  => [
                'L' => 'Managed by documented routine procedures.',
                'M' => 'Proceed with supervisor approval and a safe work method.',
                'H' => 'Proceed only with risk reduced as low as reasonably practicable, and approval.',
                'E' => 'Must not proceed; lower the risk first.',
            ],
            'pick' => 'Hover or tap a cell to classify the risk.',
        ],
    ];
    $t = $L[$lang] ?? $L['es'];

    // Matriz A–E × 1–5 (idéntica al PDF). Colores por clasificación (print-safe).
    $matrix = [
        'A' => ['M', 'H', 'H', 'E', 'E'],
        'B' => ['M', 'M', 'H', 'H', 'E'],
        'C' => ['L', 'M', 'M', 'H', 'E'],
        'D' => ['L', 'M', 'M', 'H', 'H'],
        'E' => ['L', 'L', 'M', 'M', 'H'],
    ];
    $col = [
        'L' => ['bg' => '#C0DD97', 'fg' => '#173404'],
        'M' => ['bg' => '#FAC775', 'fg' => '#412402'],
        'H' => ['bg' => '#F0997B', 'fg' => '#4A1B0C'],
        'E' => ['bg' => '#E24B4A', 'fg' => '#ffffff'],
    ];
    // id único: permite varias instancias (form + doc) en la misma página.
    $rmxId = 'rmx-' . substr(md5(uniqid('', true)), 0, 6);
@endphp

@once
@push('styles')
<style>
    .rmx-wrap { --rmx-accent: var(--brand-primary, #ff9900); font-size: 13px; color: #1f2937; }
    .rmx-grid { display: grid; grid-template-columns: 132px repeat(5, 1fr); gap: 5px; }
    .rmx-corner { display: flex; flex-direction: column; justify-content: flex-end; gap: 2px; padding: 4px 6px; font-size: 10.5px; color: #9ca3af; line-height: 1.2; }
    .rmx-head { display: flex; flex-direction: column; justify-content: center; padding: 5px 7px; font-size: 11px; color: #6b7280; line-height: 1.2; transition: color .15s ease; }
    .rmx-head .rmx-k { font-weight: 600; color: #6b7280; transition: color .15s ease; }
    .rmx-head.rmx-on, .rmx-head.rmx-on .rmx-k { color: #111827; }
    .rmx-cell { min-height: 46px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; outline: 2px solid transparent; outline-offset: -2px; transition: transform .16s cubic-bezier(0.23,1,0.32,1), outline-color .15s ease; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .rmx-cell.rmx-live { cursor: pointer; }
    .rmx-cell.rmx-live:hover { transform: scale(1.05); outline-color: #111827; }
    .rmx-cell.rmx-sel { outline-color: var(--rmx-accent); }
    .rmx-axis { font-size: 11px; color: #6b7280; margin: 0 0 5px 137px; }
    .rmx-readout { display: flex; align-items: center; gap: 9px; min-height: 22px; margin: 13px 0 4px; font-size: 13px; color: #374151; }
    .rmx-sw { width: 15px; height: 15px; border-radius: 4px; flex: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .rmx-strong { font-weight: 700; color: #111827; }
    .rmx-legend { display: grid; grid-template-columns: repeat(auto-fit, minmax(168px, 1fr)); gap: 7px; margin-top: 9px; }
    .rmx-leg { display: flex; gap: 8px; align-items: flex-start; padding: 8px 10px; background: #f8f8f7; border: 1px solid #ececec; border-radius: 7px; }
    .rmx-leg .rmx-lt { font-size: 11.5px; color: #6b7280; line-height: 1.4; }
    .rmx-leg .rmx-lt strong { color: #111827; font-weight: 700; }
    @media (max-width: 520px) { .rmx-grid { grid-template-columns: 92px repeat(5, 1fr); } .rmx-axis { margin-left: 97px; } .rmx-head { font-size: 10px; } .rmx-cell { min-height: 40px; font-size: 16px; } }
</style>
@endpush
@endonce

<div class="rmx-wrap" id="{{ $rmxId }}">
    <div class="rmx-axis">{{ $t['consequence'] }} &rarr;</div>
    <div class="rmx-grid">
        <div class="rmx-corner"><span>{{ $t['likelihood'] }} &darr;</span></div>
        @foreach($t['cols'] as $n => $clabel)
            <div class="rmx-head rmx-hc" data-c="{{ $n }}"><span class="rmx-k">{{ $n }}</span><span>{{ $clabel }}</span></div>
        @endforeach

        @foreach($matrix as $rowKey => $ratings)
            <div class="rmx-head rmx-hr" data-l="{{ $rowKey }}"><span class="rmx-k">{{ $rowKey }}</span><span>{{ $t['rows'][$rowKey] }}</span></div>
            @foreach($ratings as $ci => $r)
                <div class="rmx-cell {{ $interactive ? 'rmx-live' : '' }}"
                     data-l="{{ $rowKey }}" data-c="{{ $ci + 1 }}" data-r="{{ $r }}"
                     style="background: {{ $col[$r]['bg'] }}; color: {{ $col[$r]['fg'] }};">{{ $r }}</div>
            @endforeach
        @endforeach
    </div>

    @if($interactive)
        <div class="rmx-readout" data-role="readout"><span style="color:#9ca3af">{{ $t['pick'] }}</span></div>
    @endif

    @if($legend)
        <div class="rmx-legend">
            @foreach(['L', 'M', 'H', 'E'] as $r)
                <div class="rmx-leg">
                    <span class="rmx-sw" style="background: {{ $col[$r]['bg'] }};"></span>
                    <span class="rmx-lt"><strong>{{ $t['rat'][$r] }} ({{ $r }})</strong><br>{{ $t['act'][$r] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>

@if($interactive)
<script>
(function () {
    var root = document.getElementById('{{ $rmxId }}');
    if (!root) return;
    var cells = [].slice.call(root.querySelectorAll('.rmx-cell'));
    var hrs = [].slice.call(root.querySelectorAll('.rmx-hr'));
    var hcs = [].slice.call(root.querySelectorAll('.rmx-hc'));
    var readout = root.querySelector('[data-role="readout"]');
    var sel = null;
    var COLOR = { L: '#C0DD97', M: '#FAC775', H: '#F0997B', E: '#E24B4A' };
    var ROWS = @json($t['rows']);
    var COLS = @json($t['cols']);
    var RAT  = @json($t['rat']);

    function heads(l, c) {
        hrs.forEach(function (h) { h.classList.toggle('rmx-on', h.dataset.l === l); });
        hcs.forEach(function (h) { h.classList.toggle('rmx-on', h.dataset.c === String(c)); });
    }
    function clearHeads() {
        hrs.forEach(function (h) { h.classList.remove('rmx-on'); });
        hcs.forEach(function (h) { h.classList.remove('rmx-on'); });
    }
    function paint(cell) {
        if (!readout) return;
        var l = cell.dataset.l, c = cell.dataset.c, r = cell.dataset.r;
        readout.innerHTML = '<span class="rmx-sw" style="background:' + COLOR[r] + '"></span>' +
            '<span><span class="rmx-strong">' + l + '</span> ' + ROWS[l] +
            '  &times;  <span class="rmx-strong">' + c + '</span> ' + COLS[c] +
            '  &rarr;  <span class="rmx-strong">' + RAT[r] + ' (' + r + ')</span></span>';
    }
    root.addEventListener('mouseover', function (e) {
        var c = e.target.closest('.rmx-cell'); if (!c) return;
        heads(c.dataset.l, c.dataset.c); paint(c);
    });
    root.addEventListener('mouseout', function (e) {
        var c = e.target.closest('.rmx-cell'); if (!c) return;
        if (sel) { heads(sel.dataset.l, sel.dataset.c); paint(sel); } else { clearHeads(); }
    });
    root.addEventListener('click', function (e) {
        var c = e.target.closest('.rmx-cell'); if (!c) return;
        cells.forEach(function (x) { x.classList.remove('rmx-sel'); });
        c.classList.add('rmx-sel'); sel = c; heads(c.dataset.l, c.dataset.c); paint(c);
    });
})();
</script>
@endif
