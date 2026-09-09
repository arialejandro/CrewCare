{{-- ANTI-FLASH DE TEMA (debe correr ANTES del primer paint): lee la preferencia manual
     guardada por el botón de Dashboard en localStorage 'cc-theme' ('dark'|'light') y la
     estampa en <html data-theme=...>. Si no hay preferencia, no toca nada y el tema lo
     decide @media (prefers-color-scheme). Defensivo: try/catch por si localStorage no existe
     (modo privado / sandbox). Va PRIMERO, sin defer, para que el atributo esté puesto cuando
     el CSS de abajo se evalúe. --}}
<script>
    (function () {
        try {
            var t = localStorage.getItem('cc-theme');
            if (t === 'dark' || t === 'light') {
                document.documentElement.setAttribute('data-theme', t);
            }
        } catch (e) { /* localStorage bloqueado: se usa la preferencia del sistema */ }
    })();
</script>
{{--
    TEMA GLOBAL DE MARCA — se incluye UNA vez en el <head> del layout maestro (layouts/app),
    así CADA página (actual y futura) hereda la marca automáticamente, sin cablear nada.

    Qué hace:
      1) Publica las variables CSS de marca en :root → --brand-primary/-secondary/-accent,
         --brand-on-primary (texto legible calculado por luminancia) y --brand-primary-rgb.
      2) Puentea Bootstrap: en 5.1.3 los componentes usan color COMPILADO (no variables), por eso
         se refuerzan con reglas explícitas (.btn-primary, .text/bg/border-primary, links, foco,
         paginación, nav-pills, form-check, progress) apuntando a la marca. Si algún día se sube a
         Bootstrap 5.2+, --bs-primary ya queda seteado.
      3) Ofrece utilidades .text-brand / .bg-brand / .btn-brand / .badge-brand / -accent para el
         trabajo futuro (una sola clase y ya está marcado).

    Nota: el texto sobre el primario se calcula (Branding::textOn) para que un primario claro
    (p.ej. amarillo) no quede con texto blanco ilegible.
--}}
@php
    $__b  = \App\Support\Branding::all();
    $__p  = $__b['primary_color']   ?? '#ff9900';
    $__s  = $__b['secondary_color'] ?? '#1f2937';
    $__a  = $__b['accent_color']    ?? '#0ea5e9';
    $__on = \App\Support\Branding::textOn($__p);
    $__pRgb  = \App\Support\Branding::rgb($__p);
    $__pDark = \App\Support\Branding::shade($__p, 0.85); // hover/active
    $__aOn   = \App\Support\Branding::textOn($__a);
@endphp
<style>
    :root {
        --brand-primary: {{ $__p }};
        --brand-secondary: {{ $__s }};
        --brand-accent: {{ $__a }};
        --brand-on-primary: {{ $__on }};
        --brand-primary-rgb: {{ $__pRgb }};
        --brand-primary-dark: {{ $__pDark }};
        /* Texto legible ENCIMA del primario (calculado por luminancia). Alias semántico
           de --brand-on-primary para que las superficies pidan un nombre estable. */
        --brand-primary-contrast: {{ $__on }};

        /* ===== TOKENS SEMÁNTICOS (API compartida — claro por defecto) =====
           Las superficies dependen SOLO de estos nombres; nunca de hex directos.
           Superficies: base / elevado / hundido. */
        --surface:   #ffffff;   /* fondo base de la página */
        --surface-2: #f6f7f9;   /* elevado: cards, popovers, panel del sidebar */
        --surface-3: #eceff3;   /* hundido: wells, celdas de tabla, tracks */
        --text:       #1f2937;  /* texto principal */
        --text-muted: #566072;  /* texto secundario — 6:1 sobre --surface (≥4.5:1 AA) */
        --border:     #e2e6ea;  /* bordes/divisores */
        --ok:     #16a34a;      /* estado correcto */
        --warn:   #b45309;      /* estado advertencia (≥4.5:1 sobre --surface) */
        --danger: #dc2626;      /* estado error/peligro */
        /* Anillo de foco: deriva del primario de marca. */
        --ring: var(--brand-primary);

        /* Puentes a Bootstrap (5.2+ los consume; en 5.1 reforzamos abajo). */
        --bs-primary: {{ $__p }};
        --bs-primary-rgb: {{ $__pRgb }};
        --bs-link-color: {{ $__p }};
        --bs-link-hover-color: {{ $__pDark }};
    }

    /* ===== Bootstrap 5.1 → marca (componentes con color compilado) ===== */
    .btn-primary {
        --bs-btn-bg: var(--brand-primary); --bs-btn-border-color: var(--brand-primary);
        --bs-btn-color: var(--brand-on-primary);
        --bs-btn-hover-bg: var(--brand-primary-dark); --bs-btn-hover-border-color: var(--brand-primary-dark);
        --bs-btn-hover-color: var(--brand-on-primary);
        --bs-btn-active-bg: var(--brand-primary-dark); --bs-btn-active-border-color: var(--brand-primary-dark);
        background-color: var(--brand-primary); border-color: var(--brand-primary); color: var(--brand-on-primary);
    }
    .btn-primary:hover, .btn-primary:focus, .btn-primary:active, .btn-primary.active {
        background-color: var(--brand-primary-dark); border-color: var(--brand-primary-dark); color: var(--brand-on-primary);
    }
    .btn-outline-primary { color: var(--brand-primary); border-color: var(--brand-primary); }
    .btn-outline-primary:hover, .btn-outline-primary:focus, .btn-outline-primary:active {
        background-color: var(--brand-primary); border-color: var(--brand-primary); color: var(--brand-on-primary);
    }
    .text-primary { color: var(--brand-primary) !important; }
    .bg-primary { background-color: var(--brand-primary) !important; color: var(--brand-on-primary) !important; }
    .border-primary { border-color: var(--brand-primary) !important; }
    .link-primary { color: var(--brand-primary) !important; }
    .badge.bg-primary, .badge.text-bg-primary { background-color: var(--brand-primary) !important; color: var(--brand-on-primary) !important; }
    .nav-pills .nav-link.active, .nav-pills .show > .nav-link { background-color: var(--brand-primary); color: var(--brand-on-primary); }
    /* Paginación Bootstrap (si alguna vista usa la nativa) → marca. La app usa el pager propio abajo. */
    .page-link { color: var(--brand-primary) !important; }
    .page-item.active .page-link { background-color: var(--brand-primary) !important; border-color: var(--brand-primary) !important; color: var(--brand-on-primary) !important; }

    /* (2026-07-25) Tablas Bootstrap "peladas" → tokens. BS 5.1 compila `.table { color:#212529 }`
       (gris oscuro FIJO): en claro se ve, pero sobre superficie oscura (dark mode / tarjeta glass)
       queda texto-oscuro-sobre-oscuro, CASI INVISIBLE. Pasaba con los nombres y guiones del "Registro
       de pacientes" lite (columnas Datos/Procedencia) y en cualquier <table> sin color propio. Se
       puentea al token semántico para que herede el tema. Los muteados a propósito (.cc-muted,
       .text-muted) ganan por su token/!important y NO se aclaran — sólo se repara el texto PRIMARIO. */
    .table {
        color: var(--text);
        border-color: var(--border);
        --bs-table-color: var(--text);
        --bs-table-border-color: var(--border);
        --bs-table-striped-color: var(--text);
        --bs-table-active-color: var(--text);
        --bs-table-hover-color: var(--text);
    }

    /* ===== Paginador de marca (.cc-pager) — vista pagination.brand ===== */
    .cc-pager { display: flex; justify-content: center; margin: .25rem 0; }
    /* flex-wrap (2026-07): con muchas páginas el pager desbordaba la pantalla en móviles
       (~375px); ahora los números envuelven a una segunda línea centrada. */
    .cc-pager__list { display: inline-flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: .3rem; list-style: none; margin: 0; padding: 0; }
    .cc-pager__link {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 40px; height: 40px; padding: 0 .7rem; box-sizing: border-box;
        border-radius: 11px; border: 1px solid #e6e8ec; background: #fff; color: #475569;
        font-size: .9rem; font-weight: 600; line-height: 1; text-decoration: none;
        transition: background-color .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease, transform .1s ease;
    }
    .cc-pager__link--nav { font-size: .78rem; color: #64748b; }
    .cc-pager__item > a.cc-pager__link:hover {
        border-color: #d7dbe0; background: #f6f7f9; color: var(--brand-primary-dark); /* fallback */
        border-color: color-mix(in srgb, var(--brand-primary) 45%, #e6e8ec);
        background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
        color: var(--brand-primary-dark);
    }
    .cc-pager__item--active .cc-pager__link {
        background: var(--brand-primary); border-color: var(--brand-primary); color: var(--brand-on-primary);
        box-shadow: 0 4px 12px rgba(var(--brand-primary-rgb), .35);
        transform: translateY(-1px); cursor: default;
    }
    .cc-pager__item--disabled .cc-pager__link { color: #c2c8d0; background: #fff; border-color: #eef0f2; cursor: not-allowed; box-shadow: none; }
    .cc-pager__item--gap .cc-pager__link { border-color: transparent; background: transparent; color: #9aa3af; min-width: 24px; padding: 0 .15rem; cursor: default; }

    /* ===== Neutraliza regla legada de form-register.css: `small { display:block;
       margin:5rem 1rem }` distorsionaba TODOS los <small> de la app (huecos de 80px
       en formularios). El login/registro no incluye este tema, así que conserva la suya. ===== */
    small { display: inline; margin: 0; }

    /* ===== Backport Bootstrap 5.3 subtle/emphasis (esta app corre 5.1.3, que NO las tiene →
       los .badge quedaban con texto blanco por defecto y sin fondo = invisibles). ===== */
    .bg-primary-subtle   { background-color: #cfe2ff !important; }
    .bg-secondary-subtle { background-color: #e2e3e5 !important; }
    .bg-success-subtle   { background-color: #d1e7dd !important; }
    .bg-danger-subtle    { background-color: #f8d7da !important; }
    .bg-warning-subtle   { background-color: #fff3cd !important; }
    .bg-info-subtle      { background-color: #cff4fc !important; }
    .bg-light-subtle     { background-color: #fcfcfd !important; }
    .bg-dark-subtle      { background-color: #ced4da !important; }
    .text-primary-emphasis   { color: #052c65 !important; }
    .text-secondary-emphasis { color: #2b2f32 !important; }
    .text-success-emphasis   { color: #0a3622 !important; }
    .text-danger-emphasis    { color: #58151c !important; }
    .text-warning-emphasis   { color: #664d03 !important; }
    .text-info-emphasis      { color: #055160 !important; }
    .text-light-emphasis     { color: #495057 !important; }
    .text-dark-emphasis      { color: #495057 !important; }

    /* ===== Backport Bootstrap 5.2 `.text-bg-*` (esta app corre 5.1.3, que SOLO tiene `.bg-*`; las
       `.text-bg-*` llegaron en 5.2 → sin ellas los badges quedaban SIN FONDO y con texto base =
       ilegibles / "pelados", sobre todo en oscuro). Colores SÓLIDOS con texto contrastante: cada pill
       trae su propio fondo, así se lee igual en claro y oscuro (no depende de la superficie). El
       `text-bg-primary` de marca ya está arriba y NO se toca. ===== */
    .text-bg-secondary { color: #fff !important; background-color: #6c757d !important; }
    .text-bg-success   { color: #fff !important; background-color: #198754 !important; }
    .text-bg-danger    { color: #fff !important; background-color: #dc3545 !important; }
    .text-bg-warning   { color: #000 !important; background-color: #ffc107 !important; }
    .text-bg-info      { color: #000 !important; background-color: #0dcaf0 !important; }
    .text-bg-light     { color: #212529 !important; background-color: #f1f3f5 !important; }
    .text-bg-dark      { color: #fff !important; background-color: #343a40 !important; }
    .form-check-input:checked { background-color: var(--brand-primary); border-color: var(--brand-primary); }
    .form-control:focus, .form-select:focus, .form-check-input:focus {
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 .25rem rgba(var(--brand-primary-rgb), .25);
    }
    .progress-bar { background-color: var(--brand-primary); }
    .list-group-item.active { background-color: var(--brand-primary); border-color: var(--brand-primary); color: var(--brand-on-primary); }
    a.link-primary:hover, .link-primary:hover { color: var(--brand-primary-dark) !important; }

    /* ===== Utilidades de marca (para trabajo futuro) ===== */
    .text-brand { color: var(--brand-primary) !important; }
    .text-brand-2 { color: var(--brand-secondary) !important; }
    .text-brand-accent { color: var(--brand-accent) !important; }
    .bg-brand { background-color: var(--brand-primary) !important; color: var(--brand-on-primary) !important; }
    .bg-brand-2 { background-color: var(--brand-secondary) !important; color: #fff !important; }
    .bg-brand-accent { background-color: var(--brand-accent) !important; color: {{ $__aOn }} !important; }
    .border-brand { border-color: var(--brand-primary) !important; }
    .border-brand-accent { border-color: var(--brand-accent) !important; }
    .btn-brand { background-color: var(--brand-primary); border: 1px solid var(--brand-primary); color: var(--brand-on-primary); }
    .btn-brand:hover, .btn-brand:focus { background-color: var(--brand-primary-dark); border-color: var(--brand-primary-dark); color: var(--brand-on-primary); }

    /* ===== .btn-crew / .btn-crew-soft — botones primario y secundario del "crew design system".
       Se usan en TODA la app (contratos, safety, etc.) pero su base sólo vivía en parciales por-página
       (_crew-list-styles), así que en las pantallas que no los cargan quedaban SIN ESTILO = texto
       invisible en oscuro. Aquí van a la base GLOBAL (tokens → claro y oscuro); los parciales que ya
       los definen ganan por ser más tardíos/específicos. ===== */
    .btn-crew { background-color: var(--brand-primary); border: 1px solid var(--brand-primary); color: var(--brand-on-primary); }
    .btn-crew:hover, .btn-crew:focus { background-color: var(--brand-primary-dark); border-color: var(--brand-primary-dark); color: var(--brand-on-primary); }
    .btn-crew-soft { background-color: var(--surface-2); border: 1px solid var(--border); color: var(--text); }
    .btn-crew-soft:hover, .btn-crew-soft:focus { background-color: var(--surface-3); border-color: var(--brand-primary); color: var(--text); }
    .badge-brand { display: inline-block; background-color: var(--brand-primary); color: var(--brand-on-primary); padding: .35em .6em; border-radius: .375rem; font-size: .75em; font-weight: 600; }
    .badge-accent { display: inline-block; background-color: var(--brand-accent); color: {{ $__aOn }}; padding: .35em .6em; border-radius: .375rem; font-size: .75em; font-weight: 600; }

    /* ===== Backport de utilidades de ancho RESPONSIVE (Bootstrap 5.1 no las trae).
       Patrón: botón/control a lo ancho en móvil (w-100) y ancho natural en pantallas
       mayores (w-sm-auto / w-md-auto). Varias vistas ya usan `w-100 w-sm-auto`
       asumiendo que existía — con este backport ahora sí funciona. ===== */
    @media (min-width: 576px) { .w-sm-auto { width: auto !important; } }
    @media (min-width: 768px) { .w-md-auto { width: auto !important; } }

    /* ===== Guardia global anti-desborde horizontal en móviles (2026-07):
       las imágenes nunca deben empujar el ancho de la página. Conservador:
       solo max-width, no cambia layouts existentes. ===== */
    img { max-width: 100%; height: auto; }

    /* ===================================================================
       MODO OSCURO — overrides de los TOKENS SEMÁNTICOS.
       Tres vías (por orden de prioridad creciente):
         1) @media (prefers-color-scheme: dark)  → sigue al sistema (auto).
         2) :root[data-theme="dark"]              → toggle manual a oscuro.
         3) :root[data-theme="light"]             → toggle manual a claro
            (fuerza claro AUNQUE el sistema esté en oscuro; gana por mayor
            especificidad 0,0,2,0 vs. el :root del @media 0,0,1,0).
       Solo se re-mapean los tokens; el resto del CSS ya los consume.
       ================================================================== */
    @media (prefers-color-scheme: dark) {
        :root {
            --surface:   #111827;
            --surface-2: #1f2937;
            --surface-3: #0b1220;
            --text:       #e5e7eb;
            --text-muted: #9aa5b5;  /* ≥4.5:1 sobre --surface oscuro */
            --border:     #2a3542;
            --ok:     #22c55e;
            --warn:   #f59e0b;
            --danger: #f87171;
        }
    }
    :root[data-theme="dark"] {
        --surface:   #111827;
        --surface-2: #1f2937;
        --surface-3: #0b1220;
        --text:       #e5e7eb;
        --text-muted: #9aa5b5;
        --border:     #2a3542;
        --ok:     #22c55e;
        --warn:   #f59e0b;
        --danger: #f87171;
    }
    :root[data-theme="light"] {
        --surface:   #ffffff;
        --surface-2: #f6f7f9;
        --surface-3: #eceff3;
        --text:       #1f2937;
        --text-muted: #566072;
        --border:     #e2e6ea;
        --ok:     #16a34a;
        --warn:   #b45309;
        --danger: #dc2626;
    }

    /* ===================================================================
       REGLAS GLOBALES DE ACCESIBILIDAD Y TÁCTIL (criterio de aceptación).
       ================================================================== */
    /* Foco visible en TODO lo interactivo — mismo anillo de marca en toda la app. */
    a:focus-visible, button:focus-visible, .btn:focus-visible, .nav-link:focus-visible,
    [tabindex]:focus-visible, .form-control:focus-visible, .form-select:focus-visible {
        outline: 2px solid var(--ring);
        outline-offset: 2px;
        border-radius: 3px;
    }
    /* Tap targets ≥44px en punteros gruesos (dedo) SIN tocar el markup. */
    @media (pointer: coarse) {
        .form-control, .form-select, .btn, .nav-link, .cc-ta-opt { min-height: 44px; }
    }
    /* Texto secundario accesible — reemplaza a text-gray-400/text-muted informativos. */
    .cc-muted { color: var(--text-muted) !important; }

    /* Icono SVG inline (parcial componentes/_icon): escala con font-size (1em) por defecto;
       si se le pasa w-5/h-5 (Tailwind) u otra clase de tamaño, esa gana. */
    .cc-ico { width: 1em; height: 1em; display: inline-block; vertical-align: -0.125em; flex: none; }

    /* ===================================================================
       PUENTE DE TAMAÑOS tipo Tailwind (w-3..w-9 / h-3..h-9) SOLO para el
       ICONO SVG inline. Varias vistas pasan estas clases a componentes._icon
       como "tamaño", pero este proyecto es Bootstrap (NO Tailwind) y esas
       utilidades NO existen → el <svg> (viewBox 24×24 sin width/height)
       explota a ~150px = los "iconos gigantes" que reportó el owner.
       Se mapean a una escala rem sensata y SÓLO sobre <svg> (nunca afectan
       otros elementos, y nada más en la app define estas clases → sin choques).
       Tamaños EXPLÍCITOS de trabajo viven en componentes/_form-kit (.cc-ico-NN).
       =================================================================== */
    svg.w-3 { width: .75rem; }  svg.h-3 { height: .75rem; }
    svg.w-4 { width: 1rem; }    svg.h-4 { height: 1rem; }
    svg.w-5 { width: 1.25rem; } svg.h-5 { height: 1.25rem; }
    svg.w-6 { width: 1.5rem; }  svg.h-6 { height: 1.5rem; }
    svg.w-7 { width: 1.75rem; } svg.h-7 { height: 1.75rem; }
    svg.w-8 { width: 2rem; }    svg.h-8 { height: 2rem; }
    svg.w-9 { width: 2.25rem; } svg.h-9 { height: 2.25rem; }

    /* ===================================================================
       .cc-stack — TABLA → TARJETAS APILADAS EN MÓVIL (mata el scroll horizontal).
       Uso: <table class="cc-stack"> con un <thead> visible en desktop y CADA <td>
       con data-label="Etiqueta". En ≤767px cada <tr> se vuelve una tarjeta y cada
       celda muestra su data-label como columna izquierda (content: attr(data-label)).
       Ej: <td data-label="Nombre">@{{ $u->name }}</td>
       ================================================================== */
    @media (max-width: 767px) {
        table.cc-stack, table.cc-stack tbody, table.cc-stack tr, table.cc-stack td { display: block; width: 100%; }
        /* (2026-08-04) Al apilar, un min-width EN LÍNEA (p.ej. style="min-width:1200px",
           necesario para no aplastar la tabla en escritorio) seguiría forzando scroll
           horizontal en el teléfono. Al estar apilada ya no lo necesita: lo anulamos.
           Va con !important porque compite contra el min-width en línea; solo <768px. */
        table.cc-stack { min-width: 0 !important; }
        table.cc-stack thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
        table.cc-stack tr {
            margin: 0 0 .75rem; padding: .35rem .25rem;
            border: 1px solid var(--border); border-radius: 12px; background: var(--surface-2);
        }
        table.cc-stack td {
            display: flex; justify-content: space-between; align-items: baseline; gap: 1rem;
            padding: .5rem .75rem; border: 0; border-bottom: 1px solid var(--border);
            text-align: right;
        }
        table.cc-stack td:last-child { border-bottom: 0; }
        table.cc-stack td::before {
            content: attr(data-label);
            font-weight: 600; color: var(--text-muted);
            text-align: left; margin-right: auto;
        }
        /* Celda sin data-label (p.ej. la de acciones): centra su contenido. */
        table.cc-stack td:not([data-label])::before { content: ""; }
    }

    /* ===================================================================
       CAPA GLASS — "Cinematic Dark Glass" (mockup aprobado por el owner).
       Amplía los tokens semánticos existentes con: superficies de VIDRIO
       translúcido, fondo cinematográfico de página, hairlines, glow de
       marca, radios, easing y sombras. NO redefine los tokens existentes
       (--surface/--text/--text-muted/--border/--ok/--warn/--danger/--ring):
       solo AGREGA nombres nuevos. El acento del glow deriva de la marca
       configurable (--brand-primary-rgb) — NO se hardcodea naranja.
       ------------------------------------------------------------------
       Contraste: el texto siempre usa --text / --text-muted (AA ≥4.5:1)
       sobre el vidrio, nunca gris-sobre-vidrio. En claro el vidrio es
       blanco .72 sobre un fondo frosted casi-blanco; en oscuro es blanco
       muy tenue sobre #0C1019 → texto claro de alto contraste.
       =================================================================== */
    :root {
        /* Fondo cinematográfico de PÁGINA (frosted light por defecto). */
        --bg-deep: #E8ECF3;
        --bg:      #F3F5FA;
        --bg-2:    #FFFFFF;
        /* Vidrio translúcido (3 intensidades) + hairlines. */
        --glass:    rgba(255, 255, 255, .72);
        --glass-2:  rgba(255, 255, 255, .85);
        --glass-hi: rgba(255, 255, 255, .95);
        --stroke:   rgba(16, 24, 40, .09);
        --stroke-2: rgba(16, 24, 40, .16);
        /* Glow de acento DERIVADO de la marca (configurable). */
        --brand-glow: rgba(var(--brand-primary-rgb), .16);
        /* Geometría / movimiento / profundidad. */
        --radius:    16px;
        --radius-sm: 11px;
        --ease: cubic-bezier(.16, 1, .3, 1);
        --shadow: 0 12px 30px -14px rgba(20, 30, 55, .28), 0 2px 6px -2px rgba(20, 30, 55, .12);
        /* Blur/saturate del vidrio (un solo lugar para calibrar). */
        --glass-blur: 18px;
        --glass-sat:  1.3;
    }

    /* Oscuro cinematográfico — 3 vías (sistema + toggle manual), igual que
       el mapeo de tokens semánticos de arriba. */
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme]) {
            --bg-deep: #090C13;
            --bg:      #0C1019;
            --bg-2:    #11161F;
            --glass:    rgba(255, 255, 255, .045);
            --glass-2:  rgba(255, 255, 255, .065);
            --glass-hi: rgba(255, 255, 255, .09);
            --stroke:   rgba(255, 255, 255, .09);
            --stroke-2: rgba(255, 255, 255, .15);
            --brand-glow: rgba(var(--brand-primary-rgb), .30);
            --shadow: 0 10px 30px -12px rgba(0, 0, 0, .6), 0 2px 6px -2px rgba(0, 0, 0, .4);
        }
    }
    :root[data-theme="dark"] {
        --bg-deep: #090C13;
        --bg:      #0C1019;
        --bg-2:    #11161F;
        --glass:    rgba(255, 255, 255, .045);
        --glass-2:  rgba(255, 255, 255, .065);
        --glass-hi: rgba(255, 255, 255, .09);
        --stroke:   rgba(255, 255, 255, .09);
        --stroke-2: rgba(255, 255, 255, .15);
        --brand-glow: rgba(var(--brand-primary-rgb), .30);
        --shadow: 0 10px 30px -12px rgba(0, 0, 0, .6), 0 2px 6px -2px rgba(0, 0, 0, .4);
    }
    :root[data-theme="light"] {
        --bg-deep: #E8ECF3;
        --bg:      #F3F5FA;
        --bg-2:    #FFFFFF;
        --glass:    rgba(255, 255, 255, .72);
        --glass-2:  rgba(255, 255, 255, .85);
        --glass-hi: rgba(255, 255, 255, .95);
        --stroke:   rgba(16, 24, 40, .09);
        --stroke-2: rgba(16, 24, 40, .16);
        --brand-glow: rgba(var(--brand-primary-rgb), .16);
        --shadow: 0 12px 30px -14px rgba(20, 30, 55, .28), 0 2px 6px -2px rgba(20, 30, 55, .12);
    }

    /* ---- Fondo cinematográfico de la página (solo en pantalla) ----------
       El body pinta el fondo por tokens; .main queda transparente para que
       los blobs ambientales (_ambient) se vean detrás del contenido.
       El fondo de root pinta DEBAJO de todo, así el ambient (z-index:-1)
       aparece encima del color y debajo del contenido. */
    @media screen {
        body { background: var(--bg); }
        .main, .content-wrap { background: transparent; }
    }

    /* ===================================================================
       CLASES UTILITARIAS DE VIDRIO (reutilizables por Shell/Listas/Índices).
       backdrop-filter va SOLO bajo @media screen para que la impresión NO
       lo herede. Fallback sólido (--surface-2) donde no hay backdrop-filter.
       =================================================================== */
    .cc-glass {
        background: var(--glass);
        border: 1px solid var(--stroke);
    }
    .cc-glass-card {
        background: var(--glass);
        border: 1px solid var(--stroke);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 1.1rem 1.15rem;
    }
    .cc-glass-2 { background: var(--glass-2); }
    .cc-glow {
        box-shadow: 0 12px 34px -12px var(--brand-glow), var(--shadow);
    }
    .cc-hairline { border: 1px solid var(--stroke); }
    .cc-radius   { border-radius: var(--radius); }

    @media screen {
        .cc-glass, .cc-glass-card, .cc-glass-2 {
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat));
            backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat));
        }
    }
    /* Sin soporte de backdrop-filter (Firefox viejo, etc.): superficie sólida. */
    @supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
        .cc-glass, .cc-glass-card, .cc-glass-2 { background: var(--surface-2); }
    }

    /* ===================================================================
       UPGRADE GLOBAL — las vistas interiores heredan el look SIN tocarlas:
       la .card de Bootstrap adopta el vidrio (fondo/borde/radio/sombra).
       Conservador: solo apariencia; no cambia el layout ni el contraste
       (el texto sigue en --text/--text-muted, ya AA sobre el vidrio).
       =================================================================== */
    .card {
        background-color: var(--glass);
        border: 1px solid var(--stroke);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        color: var(--text);
    }
    .card .card-header,
    .card .card-footer {
        background-color: transparent;
        border-color: var(--stroke);
    }
    @media screen {
        .card {
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat));
            backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat));
        }
    }
    @supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
        .card { background-color: var(--surface-2); }
    }

    /* ---- IMPRESIÓN: neutraliza el vidrio para no arruinar los reportes
       imprimibles (window.print). Sin blur, superficies sólidas, sin
       fondo cinematográfico que gaste tinta. NO tocamos el markup de esos
       documentos; solo garantizamos que la capa glass no se filtre. ---- */
    @media print {
        body { background: #fff; }
        .card, .cc-glass, .cc-glass-card, .cc-glass-2 {
            background: #fff !important;
            -webkit-backdrop-filter: none !important;
            backdrop-filter: none !important;
            box-shadow: none !important;
        }
    }
</style>

{{-- ===================================================================
     AUTOGUARDADO GENÉRICO DE FORMULARIOS (borradores en localStorage).
     Marca cualquier formulario de captura con:  <form data-cc-autosave="CLAVE">
       • Guarda en 'ccdraft:'+CLAVE los valores de inputs/selects/textareas con [name]
         mientras el usuario escribe (excluye file, password y el hidden _token de CSRF).
       • Al cargar, si el formulario está VACÍO (ningún campo de texto/área con valor),
         restaura el borrador — así NO pisa un formulario de edición ya poblado por el server.
       • Al hacer submit limpia el borrador.
     100% defensivo (try/catch): si localStorage no existe, no hace nada y no rompe la página.
     Sin dependencias; corre en DOMContentLoaded.
     =================================================================== --}}
<script>
    (function () {
        // Guard de disponibilidad de localStorage (modo privado, sandbox, políticas).
        function hasLS() {
            try { var k = '__cc_t'; localStorage.setItem(k, '1'); localStorage.removeItem(k); return true; }
            catch (e) { return false; }
        }
        if (!hasLS()) { return; }

        function keyFor(form) { return 'ccdraft:' + (form.getAttribute('data-cc-autosave') || ''); }

        // ¿Es un campo que debemos persistir?
        function savable(el) {
            if (!el.name) { return false; }
            if (el.name === '_token') { return false; }              // CSRF
            var t = (el.type || '').toLowerCase();
            if (t === 'file' || t === 'password' || t === 'submit' || t === 'button' || t === 'reset') { return false; }
            if (el.disabled) { return false; }
            return true;
        }

        // Clave estable por campo (radio/checkbox comparten name → incluimos value).
        function fieldKey(el) {
            var t = (el.type || '').toLowerCase();
            return (t === 'checkbox' || t === 'radio') ? el.name + '::' + el.value : el.name;
        }

        function collect(form) {
            var out = {};
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!savable(el)) { return; }
                var t = (el.type || '').toLowerCase();
                if (t === 'checkbox' || t === 'radio') { out[fieldKey(el)] = el.checked ? 1 : 0; }
                else { out[fieldKey(el)] = el.value; }
            });
            return out;
        }

        // "Vacío" = ningún campo de texto/área/select/número tiene contenido.
        // (Los checkbox/radio no cuentan para no bloquear la restauración por un default marcado.)
        function isEmpty(form) {
            var empty = true;
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!savable(el)) { return; }
                var t = (el.type || '').toLowerCase();
                if (t === 'checkbox' || t === 'radio') { return; }
                if (el.value && String(el.value).trim() !== '') { empty = false; }
            });
            return empty;
        }

        function save(form) {
            try { localStorage.setItem(keyFor(form), JSON.stringify(collect(form))); } catch (e) {}
        }

        function restore(form) {
            var raw;
            try { raw = localStorage.getItem(keyFor(form)); } catch (e) { return; }
            if (!raw) { return; }
            if (!isEmpty(form)) { return; }   // no pisar edición poblada por el server
            var data;
            try { data = JSON.parse(raw); } catch (e) { return; }
            if (!data) { return; }
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!savable(el)) { return; }
                var k = fieldKey(el);
                if (!(k in data)) { return; }
                var t = (el.type || '').toLowerCase();
                if (t === 'checkbox' || t === 'radio') { el.checked = !!data[k]; }
                else { el.value = data[k]; }
                // Notifica a listeners existentes (typeahead, previews, contadores).
                el.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        function clear(form) {
            try { localStorage.removeItem(keyFor(form)); } catch (e) {}
        }

        document.addEventListener('DOMContentLoaded', function () {
            var forms = document.querySelectorAll('form[data-cc-autosave]');
            Array.prototype.forEach.call(forms, function (form) {
                restore(form);
                // Debounce ligero para no escribir en cada tecla.
                var t = null;
                form.addEventListener('input', function () {
                    if (t) { clearTimeout(t); }
                    t = setTimeout(function () { save(form); }, 300);
                });
                form.addEventListener('change', function () { save(form); });
                form.addEventListener('submit', function () { clear(form); });
            });
        });
    })();
</script>
