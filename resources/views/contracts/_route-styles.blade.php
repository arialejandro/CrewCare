{{-- RUTA DE FIRMA · lenguaje visual compartido (estilo Signus/Logical Contracts).
     Un tablero VERTICAL de pasos numerados con conector. Lo usan dos superficies:
       · /contratos/ruta-config  → configurar la cadena por PUESTO (editable, con ocupante resuelto).
       · sobre/{envelope}        → la cadena REAL con estados vivos (hecho / en turno / en espera).
     Todo por tokens de tema (claro/oscuro). Sin animaciones infinitas (evita parpadeo). --}}
@push('styles')
<style>
    .cc-route { --rail: 44px; --bubble: 32px; }
    .cc-route__phase + .cc-route__phase { margin-top: 26px; }

    .cc-route__phead {
        display: flex; align-items: center; gap: 10px; margin: 0 0 14px;
    }
    .cc-route__pnum {
        flex: 0 0 auto; width: 24px; height: 24px; border-radius: 7px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 800; letter-spacing: .2px;
        background: color-mix(in srgb, var(--brand-primary) 14%, var(--surface-2));
        color: var(--brand-primary-dark); border: 1px solid color-mix(in srgb, var(--brand-primary) 30%, var(--border));
    }
    .cc-route__ptitle { font-weight: 700; font-size: 14.5px; color: var(--text); line-height: 1.1; }
    .cc-route__psub   { font-size: 12px; color: var(--text-muted); }
    .cc-route__pcount {
        margin-left: auto; font-size: 11.5px; font-weight: 600; color: var(--text-muted);
        background: var(--surface-3); border: 1px solid var(--border); border-radius: 999px; padding: 2px 10px;
    }

    /* ── Lista + pasos ─────────────────────────────────────────────── */
    .cc-route__list { display: flex; flex-direction: column; }

    .cc-step {
        display: grid; grid-template-columns: var(--rail) minmax(0, 1fr); align-items: stretch;
    }
    .cc-step__rail { position: relative; display: flex; justify-content: center; }
    /* conector vertical detrás de las burbujas */
    .cc-step__rail::before {
        content: ""; position: absolute; left: 50%; transform: translateX(-50%);
        top: 0; bottom: 0; width: 2px; background: var(--border);
    }
    .cc-route__list .cc-step:first-child .cc-step__rail::before { top: calc(var(--bubble) / 2); }
    .cc-route__list .cc-step:last-child  .cc-step__rail::before { bottom: calc(100% - var(--bubble) / 2); }

    .cc-step__num {
        position: relative; z-index: 1; margin-top: 12px;
        width: var(--bubble); height: var(--bubble); border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 800;
        background: var(--surface); color: var(--text-muted);
        border: 2px solid var(--border); box-sizing: border-box;
    }
    .cc-step__num svg { width: 16px; height: 16px; }

    .cc-step__card {
        margin: 6px 0 6px 4px; padding: 11px 13px; flex: 1 1 auto;
        background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px;
        display: flex; align-items: flex-start; gap: 12px;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .cc-step__main { min-width: 0; flex: 1 1 auto; }
    .cc-step__side { flex: 0 0 auto; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
    .cc-step__pill {
        display: inline-block; font-size: 10.5px; font-weight: 700; letter-spacing: .3px;
        text-transform: uppercase; padding: 1px 8px; border-radius: 999px; margin-bottom: 3px;
        background: var(--surface-3); color: var(--text-muted); border: 1px solid var(--border);
    }
    .cc-step__pill--approve {
        background: color-mix(in srgb, var(--warn) 12%, var(--surface-2));
        color: var(--warn); border-color: color-mix(in srgb, var(--warn) 32%, var(--border));
    }
    .cc-step__pill--sign {
        background: color-mix(in srgb, var(--brand-primary) 12%, var(--surface-2));
        color: var(--brand-primary-dark); border-color: color-mix(in srgb, var(--brand-primary) 30%, var(--border));
    }
    .cc-step__pill--lead {
        background: color-mix(in srgb, var(--ok) 13%, var(--surface-2));
        color: var(--ok); border-color: color-mix(in srgb, var(--ok) 32%, var(--border));
    }
    .cc-step__title { font-weight: 700; font-size: 14px; color: var(--text); line-height: 1.25; word-break: break-word; }
    .cc-step__who   { font-size: 12.5px; color: var(--text-muted); margin-top: 1px; display: flex; align-items: center; gap: 5px; }
    .cc-step__who svg { width: 13px; height: 13px; flex: 0 0 auto; }
    .cc-step__who--warn { color: var(--warn); }
    .cc-step__who--ok strong { color: var(--text); font-weight: 600; }

    /* acciones (reordenar / quitar) */
    .cc-step__acts { display: flex; align-items: center; gap: 4px; flex: 0 0 auto; }
    .cc-step__act {
        width: 30px; height: 30px; border-radius: 8px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--surface); color: var(--text-muted);
        border: 1px solid var(--border); cursor: pointer;
        transition: transform .12s ease-out, background .15s ease, color .15s ease, border-color .15s ease;
    }
    .cc-step__act svg { width: 15px; height: 15px; }
    .cc-step__act:hover { background: var(--surface-3); color: var(--text); }
    .cc-step__act:active { transform: scale(.92); }
    .cc-step__act:disabled { opacity: .35; cursor: default; pointer-events: none; }
    .cc-step__act--del:hover { background: color-mix(in srgb, var(--danger) 12%, var(--surface)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--border)); }
    .cc-step__act:focus-visible { outline: 2px solid var(--ring); outline-offset: 2px; }

    /* ── Variantes ─────────────────────────────────────────────────── */
    /* ancla fija (Contratado): protagonista, no editable */
    .cc-step--anchor .cc-step__num { background: var(--brand-primary); color: var(--brand-on-primary); border-color: var(--brand-primary); }
    .cc-step--anchor .cc-step__card { border-color: color-mix(in srgb, var(--brand-primary) 28%, var(--border)); }
    /* paso por defecto (sin config) — atenuado, informativo */
    .cc-step--ghost .cc-step__card { border-style: dashed; background: var(--surface); }
    .cc-step--ghost .cc-step__title { color: var(--text-muted); font-weight: 600; }

    /* Estados VIVOS (runtime del sobre) */
    .cc-step--done .cc-step__num { background: var(--ok); color: #fff; border-color: var(--ok); }
    .cc-step--now  .cc-step__num { background: var(--brand-primary); color: var(--brand-on-primary); border-color: var(--brand-primary); }
    .cc-step--now  .cc-step__card {
        border-color: color-mix(in srgb, var(--brand-primary) 40%, var(--border));
        box-shadow: 0 0 0 3px rgba(var(--brand-primary-rgb), .14);
    }
    .cc-step__state {
        display: inline-flex; align-items: center; gap: 4px; flex: 0 0 auto;
        font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px; white-space: nowrap;
        background: var(--surface-3); color: var(--text-muted); border: 1px solid var(--border);
    }
    .cc-step__state svg { width: 13px; height: 13px; }
    .cc-step__state--done { background: color-mix(in srgb, var(--ok) 13%, var(--surface-2)); color: var(--ok); border-color: color-mix(in srgb, var(--ok) 32%, var(--border)); }
    .cc-step__state--now  { background: color-mix(in srgb, var(--brand-primary) 13%, var(--surface-2)); color: var(--brand-primary-dark); border-color: color-mix(in srgb, var(--brand-primary) 32%, var(--border)); }
    .cc-step__state--view { background: color-mix(in srgb, var(--warn) 12%, var(--surface-2)); color: var(--warn); border-color: color-mix(in srgb, var(--warn) 30%, var(--border)); }
    .cc-step__times { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; display: flex; flex-wrap: wrap; gap: 2px 14px; }
    .cc-step__times b { font-weight: 600; color: var(--text); }

    /* ── Añadir paso ───────────────────────────────────────────────── */
    .cc-route__add { display: grid; grid-template-columns: var(--rail) minmax(0, 1fr); margin-top: 2px; }
    .cc-route__add-inner { margin-left: 4px; display: flex; gap: 8px; align-items: center; }
    .cc-route__add .form-select { max-width: 420px; }

    @media (max-width: 575.98px) {
        .cc-route { --rail: 34px; --bubble: 28px; }
        .cc-step__card { flex-wrap: wrap; }
        .cc-step__acts { margin-left: auto; }
        .cc-route__add .form-select { max-width: none; }
    }
</style>
@endpush
