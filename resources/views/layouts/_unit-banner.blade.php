{{-- ============================================================================
     FRANJA DE UNIDAD VIGENTE (Unidades 2b) — imposible de no notar.
     · Sólo cuando la unidad vigente NO es la principal (y hay más de una unidad).
       Con una sola unidad, o estando en la principal, NO se pinta nada → idéntico a hoy.
     · Sticky bajo el topbar: acompaña toda la sesión. Amber saturado, texto oscuro.
     · Aparece en TODAS las pantallas, incluidas las de creación (la razón de existir:
       que nadie cree un documento en la unidad equivocada sin darse cuenta).
     · CSP-safe: sin <script>, sin on*=. El "volver" es un <form> POST nativo.
============================================================================ --}}
@auth
@if(\App\Support\CurrentUnit::hasMultiple() && ! \App\Support\CurrentUnit::isPrincipal())
    <div class="cc-unit-banner no-print" role="status" aria-live="polite">
        <span class="cc-unit-banner__dot" aria-hidden="true"></span>
        <span class="cc-unit-banner__txt">
            {{ __('Trabajando en') }} <strong>{{ \App\Support\CurrentUnit::label() }}</strong> —
            {{ __('lo que crees queda sellado en esta unidad.') }}
        </span>
        <form method="POST" action="{{ route('unit.switch') }}" class="cc-unit-banner__form">
            @csrf
            <button type="submit" name="unit_id" value="" class="cc-unit-banner__btn">{{ __('Volver a la principal') }}</button>
        </form>
    </div>

    <style>
        .cc-unit-banner {
            position:sticky; top:60px; z-index:1019;
            display:flex; align-items:center; gap:.6rem; flex-wrap:wrap;
            padding:.5rem 1rem;
            background:#f59e0b; color:#1f2937;
            border-bottom:2px solid #b45309;
            box-shadow:0 6px 18px -10px rgba(0,0,0,.5);
            font-size:.9rem;
        }
        .cc-unit-banner__dot {
            width:10px; height:10px; border-radius:999px; background:#1f2937; flex:none;
            box-shadow:0 0 0 3px rgba(31,41,55,.2);
        }
        .cc-unit-banner__txt { font-weight:600; }
        .cc-unit-banner__txt strong { font-weight:800; }
        .cc-unit-banner__form { margin-left:auto; }
        .cc-unit-banner__btn {
            min-height:32px; padding:.2rem .8rem; border-radius:999px; cursor:pointer;
            font-size:.8rem; font-weight:700; line-height:1; white-space:nowrap;
            color:#f59e0b; background:#1f2937; border:1px solid #1f2937;
        }
        .cc-unit-banner__btn:hover, .cc-unit-banner__btn:focus-visible { background:#111827; }
        @media (max-width:767px){ .cc-unit-banner { top:60px; font-size:.85rem; } }
    </style>
@endif
@endauth
