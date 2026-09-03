{{-- =============================================================================
     APPBAR (barra superior global) — rediseño 2026-07.
     · Sticky, altura consistente, marca configurable (var(--brand-secondary)).
     · Iconos migrados de fa-* al parcial SVG _icon (Lucide).
     · Botón toggle de tema (sol/luna) que usa el hook de la base
       (localStorage 'cc-theme' + <html data-theme>).
     · z-index por debajo del offcanvas móvil (1045) y del botón hamburguesa (1035),
       para no taparlos.  no-print: no aparece al imprimir/PDF.
     ============================================================================ --}}
<header class="cc-appbar no-print">
    <div class="cc-appbar__inner">

        {{-- Marca: logo CrewCare + logo del cliente (configurable por branding).
             MÓVIL: solo la MARCA (el lockup con texto queda como micro-letras ilegibles a ese
             tamaño). La marca del CLIENTE no va en el topbar móvil: su color ya tiñe toda la
             interfaz, así que competiría por espacio sin aportar. Ambas vuelven en escritorio. --}}
        <a href="/" class="cc-appbar__brand" aria-label="{{ __('nav.home') }}">
            <img src="{{ URL::asset('img/logo-cc-login.svg') }}" alt="CrewCare" class="cc-appbar__mark d-md-none">
            <img src="{{ URL::asset('img/logo-cc-usrs.svg') }}" alt="CrewCare" class="cc-appbar__logo d-none d-md-inline">
            <span class="cc-appbar__sep d-none d-md-inline" aria-hidden="true"></span>
            <img src="{{ ($branding['client_logo'] ?? '') ?: URL::asset('img/redrum.png') }}" alt="" class="cc-appbar__logo cc-appbar__logo--client d-none d-md-inline">
        </a>

        {{-- Acciones. En móvil los textos se ocultan (icon-only con aria-label);
             desde md se muestran las etiquetas. RBAC preservado (admin vs crew). --}}
        <nav class="cc-appbar__actions" aria-label="{{ __('nav.menu') }}">

            <a class="cc-appbar__btn" href="{{ URL::previous() }}">
                @include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-appbar__ico', 'label' => __('nav.back')])
                <span class="cc-appbar__btn-txt d-none d-md-inline">{{ __('nav.back') }}</span>
            </a>

            {{-- (2026-07-24) Mismo criterio que el sidebar (User::canSeePanel): antes era
                 `admin || users.view`, que dejaba fuera al médico. Fuente única, para que la
                 mini-nav y el panel no discrepen. --}}
            @if(auth()->user()->canSeePanel())
                <a class="cc-appbar__btn" href="{{ route('perfil') }}">
                    @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-appbar__ico', 'label' => __('nav.profile')])
                    <span class="cc-appbar__btn-txt d-none d-md-inline">{{ __('nav.profile') }}</span>
                </a>
            @else
                <a class="cc-appbar__btn" href="{{ route('changepassword') }}">
                    @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-appbar__ico', 'label' => __('messages.password')])
                    <span class="cc-appbar__btn-txt d-none d-md-inline">{{ __('messages.password') }}</span>
                </a>
                <a class="cc-appbar__btn" href="{{ route('perfil') }}">
                    @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-appbar__ico', 'label' => __('messages.profile')])
                    <span class="cc-appbar__btn-txt d-none d-md-inline">{{ __('messages.profile') }}</span>
                </a>
            @endif

            {{-- (2026-08-30) Bitácora de lectura clínica — SOLO super-admin (el visor aborta 403 al resto). --}}
            @if(auth()->user()->hasRole('super-admin'))
                <a class="cc-appbar__btn" href="{{ route('clinical_log.index') }}" title="{{ __('Bitácora clínica') }}">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-appbar__ico', 'label' => __('Bitácora clínica')])
                    <span class="cc-appbar__btn-txt d-none d-md-inline">{{ __('Bitácora') }}</span>
                </a>
            @endif

            {{-- Disparador de la paleta de comandos (⌘K / Ctrl-K). Abre el overlay
                 componentes/_command-palette; la clase .cc-cmd-open la escucha su JS. --}}
            <button type="button" class="cc-appbar__btn cc-appbar__btn--search cc-cmd-open"
                    aria-label="{{ __('nav.search') }}" title="{{ __('nav.search') }}">
                @include('componentes._icon', ['name' => 'search', 'class' => 'cc-appbar__ico', 'label' => null])
                <span class="cc-appbar__btn-txt d-none d-lg-inline">{{ __('nav.search') }}</span>
                <span class="cc-appbar__kbd d-none d-lg-inline" aria-hidden="true">⌘K</span>
            </button>

            {{-- Toggle de tema claro/oscuro (sol ⇢ luna). Usa el hook global de _brand-theme. --}}
            <button type="button" id="cc-theme-toggle" class="cc-appbar__btn cc-appbar__btn--icon"
                    aria-label="{{ __('nav.theme_toggle') }}" title="{{ __('nav.theme_toggle') }}">
                <span class="cc-theme-ico cc-theme-ico--sun">
                    @include('componentes._icon', ['name' => 'sun', 'class' => 'cc-appbar__ico', 'label' => null])
                </span>
                <span class="cc-theme-ico cc-theme-ico--moon">
                    @include('componentes._icon', ['name' => 'moon', 'class' => 'cc-appbar__ico', 'label' => null])
                </span>
            </button>

            {{-- Transportación (Fase 5): contador de atención (propuestas + traslapes). Sólo transpo/producción.
                 El badge sube en vivo por poll (layouts._transport-notify); a 0 queda oculto. --}}
            @if (($__truckShow ?? false))
                <a class="cc-appbar__btn cc-appbar__btn--icon position-relative" href="{{ route('transport.order.index') }}"
                   aria-label="{{ __('Transportación') }}" title="{{ __('Novedades de transportación') }}">
                    @include('componentes._icon', ['name' => 'truck', 'class' => 'cc-appbar__ico', 'label' => null])
                    <span class="cc-appbar__badge" id="cc-truck-badge" style="{{ ($__truckCount ?? 0) > 0 ? '' : 'display:none' }}">{{ $__truckCount ?? 0 }}</span>
                </a>
            @endif

            {{-- Selector de idioma (ES/EN) — componente compartido. --}}
            <span class="cc-appbar__lang">@include('layouts._lang-switch')</span>
        </nav>
    </div>
</header>

<style>
    {{-- Appbar de VIDRIO: fondo brand-secondary translúcido + backdrop-blur → barra
         frosted que deja pasar la luz ambiental, conservando texto/logos BLANCOS
         legibles en ambos temas (el vidrio claro de página haría desaparecer un logo
         blanco; por eso la barra mantiene un sustrato oscuro de marca). --}}
    .cc-appbar {
        position: sticky; top: 0; z-index: 1020;
        background: var(--brand-secondary, #1f2937); /* fallback sólido sin color-mix */
        background: color-mix(in srgb, var(--brand-secondary) 80%, transparent);
        color: #fff;
        border-bottom: 1px solid rgba(255, 255, 255, .10);
        box-shadow: 0 1px 0 rgba(0, 0, 0, .25), 0 8px 24px -12px rgba(0, 0, 0, .5);
    }
    @media screen {
        .cc-appbar {
            -webkit-backdrop-filter: blur(16px) saturate(1.3);
            backdrop-filter: blur(16px) saturate(1.3);
        }
    }
    .cc-appbar__inner {
        display: flex; align-items: center; gap: .5rem;
        min-height: 60px; padding: .35rem .85rem;
        /* Reserva sitio al botón hamburguesa flotante (solo existe en móvil). */
        padding-left: 3.35rem;
    }
    @media (min-width: 768px) { .cc-appbar__inner { padding-left: 1rem; } }

    .cc-appbar__brand {
        display: inline-flex; align-items: center; gap: .55rem;
        text-decoration: none; margin-right: auto;
        min-height: 44px;
    }
    .cc-appbar__logo { height: 30px; width: auto; display: block; }
    .cc-appbar__mark { height: 36px; width: auto; display: block; }  /* solo móvil: marca cuadrada, legible */
    .cc-appbar__logo--client { height: 26px; opacity: .95; }
    .cc-appbar__sep { width: 1px; height: 26px; background: rgba(255, 255, 255, .25); display: inline-block; }
    /* Badge del contador de transportación (Fase 5). */
    .cc-appbar__badge {
        position: absolute; top: 2px; right: 0;
        min-width: 16px; height: 16px; padding: 0 4px;
        border-radius: 999px; background: #ef4444; color: #fff;
        font-size: 10px; line-height: 16px; font-weight: 700; text-align: center;
    }

    .cc-appbar__actions { display: flex; align-items: center; gap: .3rem; flex-wrap: nowrap; }

    .cc-appbar__btn {
        display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
        min-height: 44px; min-width: 44px; padding: 0 .7rem;
        border-radius: 10px; border: 1px solid transparent;
        background: transparent; color: rgba(255, 255, 255, .92);
        font-size: .85rem; font-weight: 600; line-height: 1; text-decoration: none;
        cursor: pointer;
        transition: background-color .15s ease, color .15s ease, border-color .15s ease;
    }
    .cc-appbar__btn:hover, .cc-appbar__btn:focus-visible {
        background: rgba(255, 255, 255, .12);
        color: #fff; border-color: rgba(255, 255, 255, .18);
    }
    .cc-appbar__btn--icon { padding: 0; }
    .cc-appbar__ico { width: 18px; height: 18px; flex: none; }
    .cc-appbar__btn-txt { white-space: nowrap; }

    /* Botón "Buscar…": un pelín más marcado (parece campo) + atajo ⌘K. */
    .cc-appbar__btn--search {
        border-color: rgba(255, 255, 255, .18);
        background: rgba(255, 255, 255, .06);
        font-weight: 500;
    }
    .cc-appbar__kbd {
        font-family: ui-monospace, "Cascadia Code", Consolas, monospace;
        font-size: .68rem; line-height: 1;
        padding: 3px 6px; border-radius: 6px;
        background: rgba(255, 255, 255, .12);
        border: 1px solid rgba(255, 255, 255, .18);
        color: rgba(255, 255, 255, .8);
    }

    /* Icono del toggle: se muestra sol o luna según el tema efectivo (lo decide el JS). */
    .cc-theme-ico { display: none; align-items: center; justify-content: center; }
    .cc-theme-ico.is-on { display: inline-flex; }

    /* Selector de idioma dentro del appbar oscuro: legible sobre fondo oscuro. */
    .cc-appbar__lang { margin-left: .15rem; }
    .cc-appbar__lang .btn-outline-secondary {
        color: rgba(255, 255, 255, .85); border-color: rgba(255, 255, 255, .28);
    }
    .cc-appbar__lang .btn-outline-secondary:hover {
        color: #fff; background: rgba(255, 255, 255, .14); border-color: rgba(255, 255, 255, .4);
    }
    .cc-appbar__lang .btn-dark {
        background: rgba(255, 255, 255, .16); border-color: rgba(255, 255, 255, .28); color: #fff;
    }

    /* Móvil: el selector de idioma tenía objetivos táctiles chicos (btn-group-sm ~31px).
       Se agrandan para el pulgar (≥44px de alto/ancho). */
    @media (max-width: 767px) {
        .cc-appbar__lang .btn {
            min-height: 44px; min-width: 44px;
            padding: .5rem .65rem; font-size: .84rem;
            display: inline-flex; align-items: center; justify-content: center;
        }
    }

    @media (max-width: 400px) {
        .cc-appbar__mark { height: 32px; }
        .cc-appbar__btn { padding: 0 .5rem; }
    }
</style>

<script>
    {{-- Toggle de tema. El anti-flash de _brand-theme ya estampó data-theme si había
         preferencia; aquí solo alternamos y mostramos el icono correcto. Defensivo. --}}
    (function () {
        var btn = document.getElementById('cc-theme-toggle');
        if (!btn) { return; }
        var root = document.documentElement;
        var sun  = btn.querySelector('.cc-theme-ico--sun');
        var moon = btn.querySelector('.cc-theme-ico--moon');

        function effective() {
            var t = root.getAttribute('data-theme');
            if (t === 'dark' || t === 'light') { return t; }
            try {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) { return 'dark'; }
            } catch (e) {}
            return 'light';
        }
        function render() {
            var isDark = effective() === 'dark';
            // En oscuro mostramos el SOL (clic → claro); en claro mostramos la LUNA (clic → oscuro).
            if (sun)  { sun.classList.toggle('is-on', isDark); }
            if (moon) { moon.classList.toggle('is-on', !isDark); }
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        }
        render();

        btn.addEventListener('click', function () {
            var next = effective() === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem('cc-theme', next); } catch (e) {}
            root.setAttribute('data-theme', next);
            render();
        });

        {{-- Si el sistema cambia y no hay preferencia manual, refresca el icono. --}}
        try {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            var onChange = function () { render(); };
            if (mq.addEventListener) { mq.addEventListener('change', onChange); }
            else if (mq.addListener) { mq.addListener(onChange); }
        } catch (e) {}
    })();
</script>
