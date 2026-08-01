{{-- Filtro POR MÉDICO de los reportes AGREGADOS (2026-07-24 · PASO 2/3, item 4).

     Es un filtro de CONSULTA, no de permiso: quien ya puede ver el agregado lo filtra como
     quiera — uno, varios o todos. Sirve para separar métricas y evaluar el impacto de las
     consultas por médico según lo que necesite cada producción. Por defecto: TODOS (ninguna
     casilla marcada = sin cláusula whereIn).

     OJO: esto NO es el aislamiento del item 1. La bitácora y el conteo SIEMPRE concentran a
     todos los médicos (si no, el entregable a producción se rompe); esto sólo recorta la vista.

     Casillas (no <select multiple>): con pocos médicos es más legible y táctil, y no esconde
     la selección detrás de un ctrl+clic que en móvil no existe.

     Recibe: $medicOptions (colección de ['id','name']), $selectedMedics (array<int>). --}}
@if($medicOptions->count() > 1)
<div class="col-12 col-md-auto">
    <div class="cc-field mb-0">
        <span class="cc-label d-block">{{ __('Médico') }}</span>
        <div class="cc-medfilter" role="group" aria-label="{{ __('Filtrar por médico') }}">
            @foreach($medicOptions as $m)
                @php $mid = 'medf-'.$m['id']; @endphp
                <label class="cc-medfilter__opt" for="{{ $mid }}">
                    <input id="{{ $mid }}" type="checkbox" name="medics[]" value="{{ $m['id'] }}"
                           {{ in_array($m['id'], $selectedMedics, true) ? 'checked' : '' }}>
                    <span>{{ $m['name'] }}</span>
                </label>
            @endforeach
        </div>
        <span class="cc-help">{{ __('Sin marcar = todos los médicos.') }}</span>
    </div>
</div>
@endif

@once
@push('styles')
<style>
    .cc-medfilter { display: flex; flex-wrap: wrap; gap: .35rem .5rem; }
    .cc-medfilter__opt {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .4rem .7rem; margin: 0;
        min-height: 40px; /* táctil */
        border: 1px solid var(--stroke-2, var(--border)); border-radius: 999px;
        background: var(--surface-2); color: var(--text);
        font-size: .88rem; cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease;
    }
    .cc-medfilter__opt:hover { border-color: var(--brand-primary, var(--border)); }
    .cc-medfilter__opt input { margin: 0; cursor: pointer; }
    /* Estado marcado legible sin depender sólo del check del navegador. */
    .cc-medfilter__opt:focus-within { outline: 2px solid var(--brand-primary, currentColor); outline-offset: 2px; }
</style>
@endpush
@endonce
