{{-- ============================================================================
     SELECTOR DE UNIDAD VIGENTE (Unidades 2b, opción (a)) — topbar, por sesión.
     · Sólo aparece cuando hay MÁS DE UNA unidad (principal + ≥1 adicional). Con una
       sola unidad no se pinta nada → el topbar se ve exactamente como hoy.
     · CSP-safe: <form> POST nativo + <button> submit (sin <script>, sin on*=).
     · El chip activo se resalta; la franja de abajo grita cuando NO es la principal.
============================================================================ --}}
@php
    $ccUnits  = \App\Support\CurrentUnit::activeUnits();
    $ccActive = \App\Support\CurrentUnit::id();
@endphp
@if($ccUnits->isNotEmpty())
    <form method="POST" action="{{ route('unit.switch') }}" class="cc-unit-switch" aria-label="{{ __('Unidad de trabajo') }}">
        @csrf
        <span class="cc-unit-switch__lbl d-none d-md-inline">{{ __('Unidad') }}</span>
        <button type="submit" name="unit_id" value="" class="cc-unit-chip {{ $ccActive === null ? 'is-on' : '' }}">{{ \App\Models\Unit::PRINCIPAL_LABEL }}</button>
        @foreach($ccUnits as $u)
            <button type="submit" name="unit_id" value="{{ $u->id }}" class="cc-unit-chip {{ $ccActive === (int) $u->id ? 'is-on' : '' }}">{{ $u->name }}</button>
        @endforeach
    </form>

    <style>
        .cc-unit-switch { display:flex; align-items:center; gap:.25rem; flex-wrap:wrap; margin-right:.4rem; }
        .cc-unit-switch__lbl { font-size:.68rem; text-transform:uppercase; letter-spacing:.06em; color:rgba(255,255,255,.7); margin-right:.15rem; }
        .cc-unit-chip {
            min-height:34px; padding:.25rem .7rem; border-radius:999px; cursor:pointer;
            font-size:.8rem; font-weight:600; line-height:1; white-space:nowrap;
            color:rgba(255,255,255,.9); background:rgba(255,255,255,.08);
            border:1px solid rgba(255,255,255,.2);
            transition:background-color .15s ease, border-color .15s ease, color .15s ease;
        }
        .cc-unit-chip:hover, .cc-unit-chip:focus-visible { background:rgba(255,255,255,.16); color:#fff; border-color:rgba(255,255,255,.35); }
        {{-- Chip activo NO-principal: amber, para que se lea "estoy en otra unidad" desde el propio selector. --}}
        .cc-unit-chip.is-on { background:#f59e0b; color:#1f2937; border-color:#f59e0b; font-weight:700; }
        .cc-unit-chip[value=""].is-on { background:#fff; color:#1f2937; border-color:#fff; }
        @media (max-width:767px){ .cc-unit-chip { min-height:38px; } }
    </style>
@endif
