{{--
    _form-kit.blade.php — SISTEMA DE ESTILOS REUTILIZABLE PARA FORMULARIOS (Bootstrap 5 + marca).

    Objetivo: que cualquier formulario de captura (los que hoy se ven "básicos/rústicos") pase
    por una capa de diseño coherente SIN reescribir Bootstrap ni tocar la lógica del backend.
    Se incluye UNA vez por página:  @include('componentes._form-kit')  (idealmente al inicio del
    contenido). Emite su CSS a @stack('styles') del <head> (evita el modo Quirks) y se protege con
    @once para no duplicarse aunque se incluya varias veces.

    100% construido sobre los TOKENS ya definidos en layouts/_brand-theme:
      superficies  --surface / --surface-2 / --glass
      texto        --text / --text-muted
      bordes       --border / --stroke / --stroke-2
      marca        --brand-primary / --brand-primary-rgb / --brand-on-primary / --brand-primary-dark
      estado       --ok / --warn / --danger
      geometría    --radius / --radius-sm / --shadow / --ease
    → hereda claro/oscuro y el color de marca configurable automáticamente.

    Buenas prácticas embebidas: contraste AA (texto siempre en --text/--text-muted), targets
    táctiles ≥44px, labels visibles (NO placeholder-only), escala de espaciado 4/8px, foco de
    marca visible, iconos SVG a 16–20px (clases .cc-ico-NN, NUNCA gigantes), jerarquía tipográfica.

    ---- CATÁLOGO DE CLASES (para rodarlo a los demás formularios) ----
      Iconos:      .cc-ico-14 / -16 / -18 / -20 / -24   (tamaño explícito del SVG de componentes._icon)
      Tarjeta:     .cc-form-card  >  .cc-form-card__head ( .cc-form-ico + .cc-form-card__titles
                                       ( .cc-form-card__title / .cc-form-card__sub ) )
                                   >  .cc-form-card__body
      Colapsable:  .cc-collapse-head  (mismo header pero como <button> con chevron)
      Campo:       .cc-field  >  .cc-label ( + .cc-req / .cc-optional )  >  control  >  .cc-help
      Controles:   .cc-control (input/textarea)   .cc-select (select)   — se suman a form-control/select
      Botones:     .cc-cta (CTA primario, va con .btn .btn-primary)     .cc-btn-ghost (secundario)
      Ficha datos: .cc-info  >  .cc-info-item ( .cc-info-lbl / .cc-info-val )
      Chips:       .cc-chip  .cc-chip--danger / --ok / --brand
      Autofirma:   .cc-signnote ( .cc-signnote__ico )
--}}
@once
@push('styles')
<style>
    /* ============================================================
       1) ICONOS SVG — tamaños EXPLÍCITOS (arreglan los "iconos gigantes").
       El SVG de componentes._icon trae viewBox 24×24 pero SIN width/height;
       si no se le da tamaño por CSS revienta a ~150px. Estas clases fijan el
       tamaño correcto (16–20px de trabajo, 24px sólo para cabeceras). Incluyen
       la alineación vertical con el texto y flex:none para que no se deformen.
       ============================================================ */
    .cc-ico-14, .cc-ico-16, .cc-ico-18, .cc-ico-20, .cc-ico-24 {
        display: inline-block; vertical-align: -0.125em; flex: none;
    }
    .cc-ico-14 { width: 14px; height: 14px; }
    .cc-ico-16 { width: 16px; height: 16px; }
    .cc-ico-18 { width: 18px; height: 18px; }
    .cc-ico-20 { width: 20px; height: 20px; }
    .cc-ico-24 { width: 24px; height: 24px; }

    /* Red de seguridad: si un formulario que adopta este kit todavía pasa clases
       tipo Tailwind (w-4/h-4…) que en este proyecto Bootstrap NO existen, el SVG
       igual queda a tamaño sensato (SOLO afecta <svg>, nunca otros elementos). */
    svg.w-3 { width: .75rem; }  svg.h-3 { height: .75rem; }
    svg.w-4 { width: 1rem; }    svg.h-4 { height: 1rem; }
    svg.w-5 { width: 1.25rem; } svg.h-5 { height: 1.25rem; }
    svg.w-6 { width: 1.5rem; }  svg.h-6 { height: 1.5rem; }

    /* ============================================================
       2) TARJETA DE SECCIÓN — agrupa campos con jerarquía y aire.
       Reusa el lenguaje de vidrio del sistema de marca; cae a superficie
       sólida donde no hay backdrop-filter o en impresión.
       ============================================================ */
    .cc-form-card {
        background: var(--glass, var(--surface-2));
        border: 1px solid var(--stroke, var(--border));
        border-radius: var(--radius, 16px);
        box-shadow: var(--shadow, 0 8px 24px -16px rgba(20,30,55,.25));
        overflow: hidden;                 /* recorta el radio de la cabecera */
        margin-bottom: 1rem;
        color: var(--text);
    }
    @media screen {
        .cc-form-card {
            -webkit-backdrop-filter: blur(var(--glass-blur, 18px)) saturate(var(--glass-sat, 1.3));
            backdrop-filter: blur(var(--glass-blur, 18px)) saturate(var(--glass-sat, 1.3));
        }
    }
    @supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
        .cc-form-card { background: var(--surface-2); }
    }
    @media print {
        .cc-form-card { background: #fff !important; box-shadow: none !important;
            -webkit-backdrop-filter: none !important; backdrop-filter: none !important; }
    }

    /* Cabecera de la tarjeta: chip de icono + título (+ subtítulo opcional). */
    .cc-form-card__head {
        display: flex; align-items: center; gap: .75rem;
        padding: .9rem 1.15rem;
        border-bottom: 1px solid var(--stroke, var(--border));
    }
    .cc-form-card__titles { min-width: 0; }
    .cc-form-card__title {
        margin: 0; font-size: 1rem; font-weight: 700; line-height: 1.25; color: var(--text);
    }
    .cc-form-card__sub {
        margin: .12rem 0 0; font-size: .8rem; color: var(--text-muted); line-height: 1.35;
    }
    .cc-form-card__body { padding: 1.15rem; }

    /* Chip de icono de sección: cuadro redondeado tintado con la marca, SVG a 20px. */
    .cc-form-ico {
        flex: none; width: 38px; height: 38px; border-radius: 11px;
        display: inline-flex; align-items: center; justify-content: center;
        color: var(--brand-primary);
        background: rgba(var(--brand-primary-rgb), .12);
        border: 1px solid rgba(var(--brand-primary-rgb), .22);
    }
    .cc-form-ico svg { width: 20px; height: 20px; }

    /* Variante colapsable: la cabecera es un <button> a lo ancho (data-bs-toggle collapse). */
    .cc-collapse-head {
        display: flex; align-items: center; gap: .75rem; width: 100%;
        padding: .9rem 1.15rem; border: 0; background: transparent; text-align: left;
        color: var(--text); font: inherit; cursor: pointer;
    }
    .cc-collapse-head:hover { background: rgba(var(--brand-primary-rgb), .05); }
    .cc-collapse-head .cc-collapse-caret { margin-left: auto; color: var(--text-muted); transition: transform .2s var(--ease, ease); }
    .cc-collapse-head[aria-expanded="true"] .cc-collapse-caret { transform: rotate(180deg); }

    /* ============================================================
       3) CAMPO — label visible arriba + control + helper.
       ============================================================ */
    .cc-field { margin-bottom: 1rem; }
    .cc-field:last-child { margin-bottom: 0; }
    .cc-label {
        display: flex; align-items: center; gap: .35rem;
        margin-bottom: .4rem; font-size: .82rem; font-weight: 600; color: var(--text);
        letter-spacing: .01em;
    }
    .cc-req { color: var(--danger); font-weight: 700; }
    .cc-optional { color: var(--text-muted); font-weight: 500; font-size: .78rem; }
    .cc-help {
        display: block; margin-top: .35rem; font-size: .78rem; color: var(--text-muted); line-height: 1.45;
    }
    /* Título de subgrupo dentro de una tarjeta (p.ej. Patológicos / No patológicos). */
    .cc-group-title {
        display: flex; align-items: center; gap: .4rem;
        margin: 0 0 .55rem; font-size: .8rem; font-weight: 700; letter-spacing: .02em;
        text-transform: uppercase; color: var(--text-muted);
    }

    /* ============================================================
       4) CONTROLES — se SUMAN a .form-control / .form-select de Bootstrap
       (conservan validación is-invalid y el caret nativo del select); sólo
       elevan radio, alto táctil, borde y tipografía. El anillo de foco de
       marca ya lo aporta _brand-theme (.form-control:focus).
       ============================================================ */
    .cc-control, .cc-select {
        min-height: 44px;
        border-radius: var(--radius-sm, 11px);
        border: 1px solid var(--stroke-2, var(--border));
        background-color: var(--surface);
        color: var(--text);
        font-size: .95rem;
    }
    .cc-control { padding: .55rem .8rem; }
    .cc-control::placeholder { color: var(--text-muted); opacity: .8; }
    textarea.cc-control { min-height: 104px; line-height: 1.55; resize: vertical; }
    /* Inputs compactos (tablas densas): conservan el radio y el color de marca
       sin forzar 44px (el touch ya lo cubre @media pointer:coarse de _brand-theme). */
    .cc-control-sm {
        border-radius: 9px; border: 1px solid var(--stroke-2, var(--border));
        background-color: var(--surface); color: var(--text);
    }

    /* ============================================================
       5) BOTONES — CTA primario coherente con la marca + secundario "ghost".
       ============================================================ */
    .cc-cta {
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
        min-height: 44px; padding: .7rem 1.4rem;
        border-radius: var(--radius-sm, 11px); font-weight: 600;
        box-shadow: 0 8px 20px -10px rgba(var(--brand-primary-rgb), .55);
        transition: box-shadow .15s var(--ease, ease), transform .1s var(--ease, ease);
    }
    .cc-cta:hover { box-shadow: 0 10px 24px -10px rgba(var(--brand-primary-rgb), .7); transform: translateY(-1px); }
    .cc-cta:active { transform: translateY(0); }
    .cc-btn-ghost {
        display: inline-flex; align-items: center; gap: .4rem;
        min-height: 40px; padding: .5rem .9rem;
        border-radius: var(--radius-sm, 11px); border: 1px solid var(--stroke-2, var(--border));
        background: transparent; color: var(--text-muted); font-weight: 600; font-size: .85rem;
        text-decoration: none; transition: color .15s ease, border-color .15s ease, background-color .15s ease;
    }
    .cc-btn-ghost:hover {
        color: var(--text); border-color: var(--brand-primary);
        background: rgba(var(--brand-primary-rgb), .06);
    }

    /* ============================================================
       6) FICHA DE DATOS (solo lectura) — etiqueta arriba / valor abajo, en grid.
       ============================================================ */
    .cc-info { display: grid; grid-template-columns: 1fr; gap: .7rem .5rem; }
    @media (min-width: 576px) { .cc-info { grid-template-columns: 1fr 1fr; gap: .8rem 1.5rem; } }
    .cc-info-item { display: flex; flex-direction: column; gap: .1rem; min-width: 0; }
    .cc-info-item--wide { grid-column: 1 / -1; }   /* ocupa el ancho completo del grid */
    .cc-info-lbl {
        font-size: .72rem; text-transform: uppercase; letter-spacing: .04em;
        color: var(--text-muted); font-weight: 600;
    }
    .cc-info-val { font-size: .95rem; color: var(--text); font-weight: 500; word-break: break-word; }

    /* ============================================================
       7) CHIPS de estado (alergias, banderas) — pastilla tintada legible.
       ============================================================ */
    .cc-chip {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .3rem .6rem; border-radius: 999px; font-size: .78rem; font-weight: 600;
    }
    .cc-chip--danger {
        color: var(--danger); background: color-mix(in srgb, var(--danger) 14%, transparent);
        border: 1px solid color-mix(in srgb, var(--danger) 35%, transparent);
    }
    .cc-chip--ok {
        color: var(--ok); background: color-mix(in srgb, var(--ok) 14%, transparent);
        border: 1px solid color-mix(in srgb, var(--ok) 35%, transparent);
    }
    .cc-chip--brand {
        color: var(--brand-primary); background: rgba(var(--brand-primary-rgb), .12);
        border: 1px solid rgba(var(--brand-primary-rgb), .25);
    }

    /* ============================================================
       8) NOTA DE AUTOFIRMA / auditoría — franja discreta con icono.
       ============================================================ */
    .cc-signnote {
        display: flex; align-items: center; gap: .6rem;
        padding: .7rem .9rem; border: 1px dashed var(--stroke-2, var(--border));
        border-radius: var(--radius-sm, 11px); background: var(--surface-2);
        color: var(--text-muted); font-size: .82rem; line-height: 1.4;
    }
    .cc-signnote__ico { color: var(--ok); flex: none; }
    .cc-signnote strong { color: var(--text); }
</style>
@endpush
@endonce
