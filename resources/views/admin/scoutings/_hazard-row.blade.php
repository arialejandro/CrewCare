{{-- Una fila de la TABLA DE PELIGROS (Amazon MGM). Se usa en el @foreach de
     _form y dentro de <template> para clonar filas nuevas por JS.
     Todos los inputs usan name[] (sin índice): el POST los reindexa por orden
     del DOM y buildHazards() los alinea por posición. --}}
@php
    $hz   = $hz ?? [];
    // (2026-07-13) HOMOLOGADO: la fila elige un EVENTO del catálogo único (agrupado por
    // contexto). $hazardEvents llega por scope compartido del @include desde _form.
    // (2026-07-17 · Paso 1b) El agrupado/loop vive ahora en componentes._event-options.
    $hazardEvents = $hazardEvents ?? collect();
    $rmap = [
        'L' => ['Bajo', '#C0DD97', '#173404'],
        'M' => ['Medio', '#FAC775', '#412402'],
        'H' => ['Alto', '#F0997B', '#4A1B0C'],
        'E' => ['Muy alto', '#E24B4A', '#ffffff'],
    ];
    $rr = $hz['rating'] ?? null;
@endphp
<tr class="hz-row">
    <td data-label="Peligro">
        {{-- (2026-07-13) HOMOLOGADO: UN solo selector de EVENTO del catálogo único,
             agrupado por contexto (Locaciones / Set / Construcción / Foros / Transversal).
             El evento aporta la categoría y su(s) norma(s) → reemplaza el select de 13
             categorías y el select de norma manual (columna "Norma", ahora auto). --}}
        {{-- data-ta-rich="1": el typeahead pinta chips de marco por opción y habilita el
             filtro por marco. El loop de opciones sale del sub-parcial COMPARTIDO (5×1). --}}
        <select name="hz_event_id[]" class="form-select form-select-sm mb-1 hz-event js-typeahead" data-ta-rich="1">
            <option value="">— Evento / peligro —</option>
            @include('componentes._event-options', ['hazardEvents' => $hazardEvents, 'selectedValue' => $hz['event_id'] ?? ''])
        </select>
        <input type="text" name="hz_hazard[]" class="form-control form-control-sm" value="{{ $hz['hazard'] ?? '' }}" placeholder="Detalle adicional (opcional)">
    </td>
    <td data-label="Prob.">
        {{-- Probabilidad (A–E) — etiquetas de la ESPEC CANÓNICA de la matriz de riesgo. --}}
        <select name="hz_likelihood[]" class="form-select form-select-sm hz-l">
            <option value="">—</option>
            @foreach(['A' => 'A · Casi seguro', 'B' => 'B · Probable', 'C' => 'C · Moderado', 'D' => 'D · Improbable', 'E' => 'E · Raro'] as $lk => $llabel)
                <option value="{{ $lk }}" {{ ($hz['likelihood'] ?? '') === $lk ? 'selected' : '' }}>{{ $llabel }}</option>
            @endforeach
        </select>
    </td>
    <td data-label="Cons.">
        {{-- Consecuencia (1–5) — etiquetas de la ESPEC CANÓNICA de la matriz de riesgo. --}}
        <select name="hz_consequence[]" class="form-select form-select-sm hz-c">
            <option value="">—</option>
            @foreach([1 => '1 · Insignificante', 2 => '2 · Menor', 3 => '3 · Moderado', 4 => '4 · Mayor', 5 => '5 · Catastrófico'] as $cn => $clabel)
                <option value="{{ $cn }}" {{ (string) ($hz['consequence'] ?? '') === (string) $cn ? 'selected' : '' }}>{{ $clabel }}</option>
            @endforeach
        </select>
    </td>
    <td data-label="Clasif." class="text-center align-middle">
        <span class="hz-rating badge rounded-pill {{ $rr && isset($rmap[$rr]) ? '' : 'bg-light text-muted border' }}"
              @if($rr && isset($rmap[$rr])) style="background: {{ $rmap[$rr][1] }}; color: {{ $rmap[$rr][2] }};" @endif>
            {{ $rr && isset($rmap[$rr]) ? $rr . ' · ' . $rmap[$rr][0] : '—' }}
        </span>
    </td>
    {{-- (captura fluida · Paso C) Control / Residual / Personal solo se muestran cuando la
         fila ya está CALIFICADA (Prob+Cons) o ya traen contenido. Es SOLO presentación
         (clase .hz-cond, alternada por JS con .is-rated en la fila): el POST envía todo
         igual → buildHazards y el sello no cambian. El guion aparece mientras están ocultos. --}}
    <td data-label="Control">
        <span class="hz-locked" aria-hidden="true">—</span>
        <input type="text" name="hz_control[]" class="form-control form-control-sm hz-cond" value="{{ $hz['control'] ?? '' }}">
    </td>
    <td data-label="Residual">
        <span class="hz-locked" aria-hidden="true">—</span>
        <select name="hz_residual[]" class="form-select form-select-sm hz-cond">
            <option value="">—</option>
            @foreach(['L' => 'Bajo', 'M' => 'Medio', 'H' => 'Alto', 'E' => 'Muy alto'] as $rk => $rl)
                <option value="{{ $rk }}" {{ ($hz['residual'] ?? '') === $rk ? 'selected' : '' }}>{{ $rl }} ({{ $rk }})</option>
            @endforeach
        </select>
    </td>
    <td data-label="Personal">
        <span class="hz-locked" aria-hidden="true">—</span>
        <input type="text" name="hz_personnel[]" class="form-control form-control-sm hz-cond" value="{{ $hz['personnel'] ?? '' }}" placeholder="Ej. 2 riggers">
    </td>
    <td data-label="Norma" class="align-middle">
        {{-- (2026-07-13) Norma AUTO: se deriva del EVENTO elegido (ya no es un select
             manual). El JS de _form la rellena al cambiar el evento; en edición muestra
             el badge/código guardado en la fila (norma principal del evento). --}}
        <span class="hz-norm small text-muted">
            @if(!empty($hz['badge']) || !empty($hz['code']))
                <span class="fw-semibold">{{ $hz['badge'] }}</span> {{ $hz['code'] }}
            @else
                —
            @endif
        </span>
    </td>
    <td data-label="" class="text-center align-middle">
        <button type="button" class="btn btn-sm btn-link text-danger p-0 hz-del" title="Quitar peligro" aria-label="Quitar peligro">&times;</button>
    </td>
</tr>
