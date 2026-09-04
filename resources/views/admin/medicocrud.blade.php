@extends('layouts.app')
@section('content')

{{--
    HOME MÉDICO UNIFICADO — CARDS (2026-07-24 · PASO 3/3 · unificado 2026-07-31).

    Antes había DOS puertas: ésta (crew) y /pacientes-lite (personas sin cuenta). Se fundieron:
    aquí el buscador encuentra a AMBOS y, si no aparece nadie, se ofrece registrar a la persona
    fuera del crew en la misma pantalla. Así se evita el "conflicto de pacientes" — que el médico
    re-registrara a un integrante del crew como paciente lite por buscar en la superficie equivocada.

    Las cards del crew viven en componentes._crew-medical-cards; el directorio completo (crew + lite
    + estado vacío con el registro) en componentes._medical-directory, COMPARTIDO con la respuesta
    del buscador AJAX (componentes._medical-directory vía search-results-medical), para que escribir
    en el buscador no cambie el diseño a media pantalla.

    Privacidad del dato clínico: EDAD (no la fecha de nacimiento) y NUNCA teléfono ni email.
--}}

{{-- Estilos de formularios (para el alta de persona no-crew). --}}
@include('componentes._form-kit')

<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4">

        {{-- Encabezado + buscador --}}
        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Consulta Médica') }}</h1>
                    <p class="text-muted mb-0 small">
                        {{ $usuarios->total() }} {{ __('del crew') }}@if($litePatients->count()) · {{ $litePatients->count() }} {{ __('sin cuenta') }}@endif
                    </p>
                </div>
            </div>

            <div class="crew-search flex-grow-1 flex-lg-grow-0">
                <label for="search" class="visually-hidden">{{ __('Buscar paciente por nombre') }}</label>
                <form data-search-noop>
                    <div class="input-group">
                        <span class="input-group-text border-end-0" aria-hidden="true">
                            @include('componentes._icon', ['name' => 'search', 'class' => 'cc-ico', 'label' => null])
                        </span>
                        <input class="form-control border-start-0 ps-0" id="search" type="search" autocomplete="off" placeholder="{{ __('Buscar crew o persona sin cuenta') }}...">
                    </div>
                </form>
            </div>
        </div>

        {{-- Mensajes (éxito/errores del alta de persona no-crew) --}}
        @include('componentes._form-feedback')

        {{-- Alta de persona FUERA del crew: "registrar si no aparece". Persistente (fuera de
             #usertable) para no perder lo escrito al buscar. Sólo la ve un clínico. --}}
        @include('componentes._lite-register', ['litePatients' => $litePatients])

        {{-- Directorio (el buscador reemplaza el contenido de este contenedor) --}}
        <div id="usertable" aria-live="polite">
            @include('componentes._medical-directory', [
                'usuarios'     => $usuarios,
                'litePatients' => $litePatients,
                'liteCounts'   => $liteCounts,
            ])
        </div>

    </div>
</div>

<script>
    // Abre el alta de persona no-crew (lo llama el estado vacío del directorio, que se re-inyecta
    // por AJAX; por eso la función vive en la página, no en el HTML intercambiado).
    function ccOpenRegister() {
        var d = document.getElementById('reg-nocrew');
        if (!d) { return; }
        d.open = true;
        d.scrollIntoView({ behavior: 'smooth', block: 'start' });
        var inp = d.querySelector('input[name="full_name"]');
        if (inp) { inp.focus(); }
    }

    // Delegación (CSP: sin on* en atributo). El botón "registrar" del estado vacío se re-inyecta
    // por AJAX, por eso el listener vive aquí (en el documento), no en el HTML intercambiado.
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-cc-open-register]')) { ccOpenRegister(); }
    });
    // El buscador NO envía el form (la búsqueda es por keyup/AJAX): sustituye el viejo veto de envío.
    document.addEventListener('submit', function (e) {
        if (e.target.closest('[data-search-noop]')) { e.preventDefault(); }
    });

    $(document).ready(function () {
        var searchTimer = null;

        function runSearch() {
            var valor = document.getElementById('search').value;
            if (valor === '') {
                valor = 'vacio';
            }
            fetch('/searchmedico/' + encodeURIComponent(valor) + '/?page=1', {
                method: 'get'
            }).then(function (response) {
                return response.text();
            }).then(function (htmlContent) {
                if (htmlContent === '') {
                    htmlContent = '<div class="crew-empty text-center py-5"><h2 class="h6 mb-1">{{ __('No se encontraron pacientes.') }}</h2></div>';
                }
                $('#usertable').html(htmlContent);
            }).catch(function (err) {
                console.log(err);
            });
        }

        // Debounce ~300ms: no dispara un fetch por cada tecla.
        $('#search').on('keyup', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(runSearch, 300);
        });
    });
</script>

@push('styles')
    @include('componentes._crew-list-styles')
    <style>
        /* ⚠ TAMAÑO DE ICONO — no borrar.
           `componentes/_icon` emite <svg viewBox="0 0 24 24"> SIN atributos width/height: depende
           al 100% del CSS. Las clases cc-ico-14/16/18 viven en `_form-kit`, que esta página SÍ
           incluye ahora (para el alta), pero las cards usan `.cc-ico` a secas; esta regla las
           dimensiona. */
        .crew-cards .cc-ico { width: 15px; height: 15px; flex: none; }

        /* Secciones del directorio (crew / sin cuenta). */
        .cc-med-section { margin-bottom: 1.75rem; }
        .cc-med-section:last-child { margin-bottom: 0; }
        .cc-med-section__title {
            display: flex; align-items: center; gap: .5rem;
            margin: 0 0 .9rem; font-size: .95rem; font-weight: 700; color: var(--text);
        }
        .cc-med-section__title .cc-ico { width: 17px; height: 17px; flex: none; color: var(--text-muted); }
        .cc-med-section__hint { font-size: .8rem; font-weight: 500; color: var(--text-muted); }

        /* Rejilla: auto-fill, sin breakpoints que mantener. */
        .crew-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }

        .crew-card {
            display: flex; flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--stroke-2, var(--border));
            border-radius: 14px;
            background: var(--surface, var(--surface-2));
            transition: border-color .15s ease, transform .15s ease;
        }
        .crew-card:hover { border-color: var(--brand-primary, var(--border)); transform: translateY(-2px); }
        @media (prefers-reduced-motion: reduce) {
            .crew-card { transition: none; }
            .crew-card:hover { transform: none; }
        }

        /* Persona sin cuenta: mismo lenguaje, con un acento tenue que la distingue del crew. */
        .crew-card--lite { border-style: dashed; }

        /* --- Retrato: la foto es el fondo y el nombre va encima, sobre un velo --- */
        .crew-card__hero { position: relative; aspect-ratio: 4 / 3; background: var(--surface-2); }
        /* aspect-ratio no existe en navegadores viejos → altura de respaldo. */
        @supports not (aspect-ratio: 4 / 3) { .crew-card__hero { height: 170px; } }
        .crew-card__hero img {
            position: absolute; inset: 0;
            width: 100%; height: 100%; object-fit: cover; object-position: center 22%;
        }
        .crew-card__hero.is-empty {
            background: linear-gradient(140deg,
                color-mix(in srgb, var(--brand-primary, #64748b) 16%, var(--surface-2)),
                var(--surface-2));
        }
        .crew-card__initials {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.6rem; font-weight: 700; letter-spacing: .04em;
            color: var(--text-muted); opacity: .5;
        }
        /* Etiqueta "Sin cuenta" en el retrato de las personas no-crew. */
        .crew-card__tag {
            position: absolute; top: .5rem; right: .5rem; z-index: 2;
            padding: .12rem .5rem; border-radius: 999px;
            font-size: .68rem; font-weight: 700; letter-spacing: .02em;
            color: #fff; background: rgba(6, 10, 18, .6);
            border: 1px solid rgba(255, 255, 255, .35);
        }
        /* Velo SIEMPRE presente: el texto no puede depender de qué tan clara sea la foto. */
        .crew-card__scrim {
            position: absolute; inset: auto 0 0 0;
            padding: 2.2rem .8rem .6rem;
            background: linear-gradient(to top, rgba(6, 10, 18, .92) 18%, rgba(6, 10, 18, .55) 55%, transparent);
        }
        .crew-card__name {
            margin: 0; font-size: 1rem; font-weight: 700; color: #fff; line-height: 1.25;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .crew-card__role {
            margin: .1rem 0 0; font-size: .78rem; color: rgba(255, 255, 255, .82);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* --- Datos --- */
        .crew-card__body { padding: .7rem .8rem .2rem; flex: 1 1 auto; }
        .crew-card__meta { display: flex; flex-wrap: wrap; gap: .1rem 1rem; margin: 0; }
        .crew-card__meta dt {
            font-size: .62rem; text-transform: uppercase; letter-spacing: .06em;
            color: var(--text-muted); font-weight: 700;
        }
        .crew-card__meta dd {
            margin: 0; font-size: .85rem; color: var(--text); font-variant-numeric: tabular-nums;
        }

        /* Advertencia, no decoración: cambia lo que el médico sabe antes de recetar. */
        .crew-card__flag {
            display: inline-flex; align-items: center; gap: .35rem; margin: .6rem 0 0;
            font-size: .75rem; font-weight: 600; color: var(--warning, #b45309);
        }
        .crew-card__flag--soft { font-weight: 500; opacity: .85; }

        /* --- Acciones: discretas, al pie --- */
        .crew-card__actions {
            display: flex; margin-top: .7rem;
            border-top: 1px solid var(--stroke-2, var(--border));
        }
        .crew-card__btn {
            flex: 1 1 0;
            display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
            min-height: 44px; /* táctil */
            padding: .5rem .4rem;
            font-size: .82rem; font-weight: 600; text-decoration: none;
            color: var(--text-muted); background: transparent;
            transition: color .15s ease, background-color .15s ease;
        }
        .crew-card__btn + .crew-card__btn { border-left: 1px solid var(--stroke-2, var(--border)); }
        .crew-card__btn:hover {
            color: var(--brand-primary, var(--text));
            background: color-mix(in srgb, var(--brand-primary, #0d6efd) 8%, transparent);
        }
        .crew-card__btn:focus-visible { outline: 2px solid var(--brand-primary, currentColor); outline-offset: -2px; }
    </style>
@endpush

@endsection
