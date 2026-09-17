{{-- Estilos de la vertical de inspección. Espejan el lenguaje de las cards de crew
     (medicocrud) y añaden lo propio: badges de paro/permiso/operador, checklist y
     banda de veredicto. Tokenizado (var(--...)) con respaldos, claro/oscuro. --}}
<style>
    .insp-page { --accent: var(--brand, #2f6df6); }
    .insp-page .cc-ico { width: 15px; height: 15px; flex: none; }   /* _icon sin tamaño → gigante sin esto */

    /* Rejilla auto-fill: sin breakpoints que mantener. */
    .tool-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; }

    .tool-card {
        display: flex; flex-direction: column;
        background: var(--surface, #fff); border: 1px solid var(--border, #e5e7eb);
        border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,.04);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .tool-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.10); }
    @media (prefers-reduced-motion: reduce) { .tool-card { transition: none; } .tool-card:hover { transform: none; } }

    .tool-card__hero { position: relative; aspect-ratio: 16/10; background:
        linear-gradient(135deg, color-mix(in srgb, var(--accent) 18%, var(--surface-2, #f1f5f9)), var(--surface-2, #f1f5f9)); }
    @supports not (aspect-ratio: 1/1) { .tool-card__hero { height: 150px; } }
    .tool-card__mono { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        color: color-mix(in srgb, var(--accent) 60%, #64748b); opacity: .8; }
    .tool-card__mono svg { width: 44px; height: 44px; }
    .tool-card__code { position: absolute; top: 8px; left: 10px; font-size: .68rem; font-weight: 600;
        letter-spacing: .04em; color: color-mix(in srgb, var(--accent) 70%, #475569); opacity: .85; }

    /* Badge de PARO en rojo sobre la imagen (arriba-derecha). */
    .tool-card__paro { position: absolute; top: 8px; right: 8px; display: inline-flex; align-items: center; gap: 4px;
        background: #b91c1c; color: #fff; font-size: .72rem; font-weight: 700; padding: 2px 8px; border-radius: 999px;
        box-shadow: 0 1px 3px rgba(0,0,0,.25); }
    .tool-card__paro .cc-ico { width: 13px; height: 13px; }

    /* Franja de badges: relleno SÓLIDO = de la herramienta; punteado = del catálogo. */
    .tool-card__badges { position: absolute; left: 8px; bottom: 8px; display: flex; flex-wrap: wrap; gap: 4px; }
    .tool-badge { display: inline-flex; align-items: center; gap: 4px; font-size: .68rem; font-weight: 600;
        padding: 2px 7px; border-radius: 7px; line-height: 1.4; }
    .tool-badge--solid  { background: color-mix(in srgb, var(--accent) 88%, #000 0%); color: #fff; }
    .tool-badge--catalog { background: color-mix(in srgb, var(--surface, #fff) 82%, var(--accent));
        color: color-mix(in srgb, var(--accent) 75%, #1f2937); border: 1px dashed color-mix(in srgb, var(--accent) 55%, #94a3b8); }
    .tool-badge .cc-ico { width: 12px; height: 12px; }

    .tool-card__body { padding: .75rem .85rem .5rem; display: flex; flex-direction: column; gap: .2rem; flex: 1; }
    .tool-card__name { font-size: 1rem; font-weight: 650; color: var(--text, #0f172a); line-height: 1.2; margin: 0; }
    .tool-card__sub  { font-size: .8rem; color: var(--text-muted, #64748b); margin: 0; }
    .tool-card__alias { font-size: .78rem; color: color-mix(in srgb, var(--accent) 65%, #475569); margin: .1rem 0 0; }
    /* "QUÉ MIRAR" — lo que un catálogo de producto no tiene. */
    .tool-card__look { margin: .45rem 0 0; padding: .4rem .5rem; border-radius: 8px; font-size: .78rem; line-height: 1.35;
        background: color-mix(in srgb, var(--accent) 7%, var(--surface-2, #f8fafc)); color: var(--text, #334155);
        display: flex; gap: .35rem; }
    .tool-card__look .cc-ico { margin-top: 2px; color: var(--accent); }

    .tool-card__actions { display: flex; gap: .4rem; padding: .5rem .85rem .85rem; margin-top: auto; }
    .tool-card__inspect { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        min-height: 44px; background: var(--accent); color: #fff; border: none; border-radius: 10px; font-weight: 650;
        text-decoration: none; }
    .tool-card__inspect:hover { filter: brightness(1.06); color: #fff; }
    .tool-card__fiche { display: inline-flex; align-items: center; justify-content: center; min-width: 44px; min-height: 44px;
        border: 1px solid var(--border, #e5e7eb); border-radius: 10px; color: var(--text-muted, #64748b); text-decoration: none; }
    .tool-card__fiche:hover { color: var(--text, #0f172a); border-color: var(--accent); }

    /* Estado sin resultados → NO es error: comodín. */
    .insp-empty { text-align: center; padding: 2.5rem 1rem; border: 1px dashed var(--border, #e5e7eb); border-radius: 14px;
        background: var(--surface-2, #f8fafc); }
    .insp-empty h3 { margin: .5rem 0 .25rem; font-size: 1.05rem; color: var(--text, #0f172a); }
    .insp-empty p { color: var(--text-muted, #64748b); margin: 0 0 1rem; }

    /* ---- Checklist (ejecución) ---- */
    .insp-point { border: 1px solid var(--border, #e5e7eb); border-radius: 12px; padding: .9rem 1rem; margin-bottom: .75rem;
        background: var(--surface, #fff); }
    .insp-point.is-gate { border-left: 4px solid #b91c1c; }
    .insp-point.is-info { border-left: 4px solid color-mix(in srgb, var(--accent) 50%, #94a3b8); }
    .insp-point__text { font-size: 1rem; line-height: 1.45; color: var(--text, #0f172a); margin: 0 0 .6rem; }
    .insp-point__meta { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .6rem; font-size: .72rem; }
    .insp-tag { padding: 1px 7px; border-radius: 6px; background: var(--surface-2, #f1f5f9); color: var(--text-muted, #64748b); }
    .insp-tag--gate { background: #fee2e2; color: #991b1b; font-weight: 600; }
    .insp-answer { display: flex; gap: .5rem; }
    .insp-answer label { flex: 1; }
    .insp-answer input { position: absolute; opacity: 0; pointer-events: none; }
    .insp-answer .btn-ans { display: flex; align-items: center; justify-content: center; gap: 6px; min-height: 46px;
        border-radius: 10px; border: 1.5px solid var(--border, #e5e7eb); font-weight: 650; cursor: pointer; width: 100%; }
    /* Valores de TOOL inspection (ok/fail) Y de PERMISOS (cumple/no_cumple): el radio está oculto
       (opacity:0) y el feedback visual sale de este `:checked + .btn-ans`. Sin la variante de
       permisos, al hacer clic el radio SÍ se selecciona pero no cambia nada → parecía "no se puede
       seleccionar" (bug reportado por el owner en la emisión de permisos). */
    .insp-answer input[value="ok"]:checked + .btn-ans,
    .insp-answer input[value="cumple"]:checked + .btn-ans    { background: #dcfce7; border-color: #16a34a; color: #166534; }
    .insp-answer input[value="fail"]:checked + .btn-ans,
    .insp-answer input[value="no_cumple"]:checked + .btn-ans { background: #fee2e2; border-color: #dc2626; color: #991b1b; }

    .insp-scope-head { font-size: .74rem; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted, #94a3b8);
        margin: 1.1rem 0 .5rem; }

    /* ---- Banda de veredicto (acta) ---- */
    .verdict-band { border-radius: 14px; padding: 1rem 1.15rem; display: flex; gap: .8rem; align-items: center; margin-bottom: 1rem; }
    .verdict-band .cc-ico { width: 26px; height: 26px; flex: none; }
    .verdict-band h2 { margin: 0; font-size: 1.15rem; }
    .verdict-band p { margin: .15rem 0 0; font-size: .85rem; opacity: .9; }
    .verdict--paro { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .verdict--noexec { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .verdict--apta { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

    @media (prefers-color-scheme: dark) {
        .verdict--paro { background: #2a1416; color: #fecaca; border-color: #7f1d1d; }
        .verdict--noexec { background: #2a2210; color: #fde68a; border-color: #78350f; }
        .verdict--apta { background: #0f2417; color: #bbf7d0; border-color: #14532d; }
        .insp-answer input[value="ok"]:checked + .btn-ans { background: #0f2417; color: #bbf7d0; }
        .insp-answer input[value="fail"]:checked + .btn-ans { background: #2a1416; color: #fecaca; }
    }
</style>
