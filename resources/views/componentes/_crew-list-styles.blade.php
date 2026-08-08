{{--
    Estilos COMPARTIDOS de las listas (Crew List + Gafetes) — así las dos superficies se ven
    hermanas. Owner: superficie "Listas". Consúmelo desde @push('styles') envolviendo la
    página en .crew-page. Tokenizado para dark (var(--surface)/--text/--border/…); el acento
    de marca se inyecta por página vía --crew-accent (fallback al color de marca global).

    Incluye: header de marca, tabla, avatares con reserva de dimensiones + fallback de iniciales,
    empty-state, paginación de marca, chips-filtro clicables y pills de estado (Gafetes).
--}}
<style>
    .crew-page { --crew-accent: {{ $branding['primary_color'] ?? '#ff9900' }}; }

    /* Tarjeta de VIDRIO (Cinematic Dark Glass): el vidrio deja ver los blobs de
       _ambient detrás. Fallback sólido var(--surface-2) donde no hay backdrop-filter,
       y neutralizado a blanco en @media print (no romper reportes imprimibles). */
    .crew-page .card {
        background: var(--glass, var(--surface));
        color: var(--text);
        border: 1px solid var(--stroke, var(--border));
        box-shadow: var(--shadow);
    }
    @supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
        .crew-page .card { background: var(--surface-2, var(--surface)); }
    }
    @media screen {
        .crew-page .card {
            -webkit-backdrop-filter: blur(var(--glass-blur, 18px)) saturate(var(--glass-sat, 1.3));
            backdrop-filter: blur(var(--glass-blur, 18px)) saturate(var(--glass-sat, 1.3));
        }
    }
    /* Header/footer transparentes: una sola superficie de vidrio, divisor hairline. */
    .crew-page .card-header,
    .crew-page .card-footer { background: transparent; border-color: var(--stroke, var(--border)); }
    @media print {
        .crew-page .card { background: #fff; box-shadow: none; -webkit-backdrop-filter: none; backdrop-filter: none; }
    }

    .crew-title {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 1.6rem;
        line-height: 1.1;
        color: var(--text);
    }

    .crew-header-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
        color: var(--brand-on-primary);
        background: var(--crew-accent);
    }
    .crew-header-icon .cc-ico { width: 1.4rem; height: 1.4rem; }

    /* Buscador ------------------------------------------------------------ */
    .crew-search .form-control:focus,
    .crew-search .input-group-text { box-shadow: none; }
    .crew-search .input-group {
        border: 1px solid var(--border);
        border-radius: .5rem;
        overflow: hidden;
    }
    .crew-search .form-control,
    .crew-search .input-group-text {
        border: 0;
        background: var(--surface);
        color: var(--text);
    }
    .crew-search .form-control::placeholder { color: var(--text-muted); opacity: 1; }
    .crew-search .input-group:focus-within {
        border-color: var(--crew-accent);
        box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--crew-accent) 20%, transparent);
    }

    .btn-crew-accent {
        background: var(--crew-accent);
        border-color: var(--crew-accent);
        color: var(--brand-on-primary);
    }
    .btn-crew-accent:hover,
    .btn-crew-accent:focus {
        filter: brightness(.92);
        color: var(--brand-on-primary);
    }

    /* Botón SECUNDARIO (p. ej. Exportar): una sola CTA primaria por pantalla → el resto
       queda subordinado. Vidrio con borde, legible en claro y oscuro. */
    .btn-crew-soft {
        background: var(--surface);
        border: 1px solid var(--border);
        color: var(--text);
    }
    .btn-crew-soft:hover,
    .btn-crew-soft:focus { background: var(--surface-2); color: var(--text); }
    .btn-crew-soft .dropdown-toggle::after { vertical-align: middle; }

    /* Tabla --------------------------------------------------------------- */
    .crew-table { color: var(--text); }
    .crew-table thead th {
        font-family: 'Poppins', sans-serif;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--text-muted);
        font-weight: 600;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    .crew-table tbody td {
        border-bottom: 1px solid var(--border);
        padding-top: .75rem;
        padding-bottom: .75rem;
        color: var(--text);
    }
    .crew-table tbody tr:last-child td { border-bottom: 0; }
    .crew-table .text-muted { color: var(--text-muted) !important; }

    /* Avatares: reservan 40px SIEMPRE (evita layout shift) + fallback iniciales */
    .crew-avatar {
        width: 40px;
        height: 40px;
        object-fit: cover;
        flex-shrink: 0;
    }
    .crew-avatar-initials {
        background: color-mix(in srgb, var(--crew-accent) 14%, var(--surface));
        color: var(--crew-accent);
        font-weight: 600;
        font-size: .85rem;
    }
    .crew-avatar-initials .cc-ico { width: 1.15rem; height: 1.15rem; }

    .crew-name { font-weight: 600; color: var(--text); line-height: 1.2; }
    .crew-sub { line-height: 1.2; color: var(--text-muted); }

    .crew-badge-soft { background: var(--surface-2); color: var(--text); font-weight: 500; }

    /* Chips-filtro (clicables) ------------------------------------------- */
    .crew-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
    .crew-chip {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .35rem .7rem;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text-muted);
        font-size: .8rem;
        font-weight: 600;
        line-height: 1;
        cursor: pointer;
        min-height: 34px;
    }
    .crew-chip .cc-ico { width: .95rem; height: .95rem; }
    .crew-chip:hover { background: var(--surface-2); color: var(--text); }
    .crew-chip[aria-pressed="true"] {
        background: color-mix(in srgb, var(--crew-accent) 12%, var(--surface));
        border-color: var(--crew-accent);
        color: var(--text);
    }
    .crew-chip .crew-chip-count { color: var(--text); font-weight: 700; }

    /* Pills de estado (Gafetes) — estado por texto + icono, nunca solo color */
    .crew-pill {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .2rem .55rem;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--text);
        font-size: .72rem;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
    }
    .crew-pill .cc-ico { width: .85rem; height: .85rem; }
    .crew-pill--ok .cc-ico { color: var(--ok); }
    .crew-pill--warn .cc-ico { color: var(--warn); }
    .crew-pill--info .cc-ico { color: var(--crew-accent); }
    .crew-pill--muted { color: var(--text-muted); }

    /* Empty-state --------------------------------------------------------- */
    .crew-empty-icon {
        width: 64px;
        height: 64px;
        font-size: 1.5rem;
        background: var(--surface-2);
        color: var(--text-muted);
    }
    .crew-empty-icon .cc-ico { width: 1.6rem; height: 1.6rem; }
    .crew-empty h5 { color: var(--text); }

    /* Acciones icon-only: tap target accesible >=38px, foco visible lo da el tema base */
    .crew-actions { display: inline-flex; gap: .35rem; justify-content: flex-end; flex-wrap: wrap; }
    .crew-actions .btn { min-width: 38px; }
    .crew-actions .cc-ico { width: 1rem; height: 1rem; }

    /* Menú kebab: superficie + iconos alineados con el texto */
    .crew-page .dropdown-menu { background: var(--surface); border-color: var(--border); }
    .crew-page .dropdown-item { color: var(--text); }
    .crew-page .dropdown-item:hover,
    .crew-page .dropdown-item:focus { background: var(--surface-2); color: var(--text); }
    .crew-page .dropdown-item .cc-ico { width: 1rem; height: 1rem; vertical-align: -.15em; margin-right: .3rem; }
    .crew-page .dropdown-divider { border-color: var(--border); }
    .crew-page .dropdown-toggle .cc-ico { width: 1.1rem; height: 1.1rem; vertical-align: -.2em; }

    /* Paginación de marca */
    .crew-page .pagination { margin-bottom: 0; justify-content: flex-end; }
    .crew-page .page-link { color: var(--crew-accent); background: var(--surface); border-color: var(--border); }
    .crew-page .page-item.active .page-link {
        background: var(--crew-accent);
        border-color: var(--crew-accent);
        color: var(--brand-on-primary);
    }
    .crew-page .page-item.disabled .page-link { background: var(--surface); border-color: var(--border); color: var(--text-muted); }

    /* ── DENSIDAD MÓVIL (2026-08-07) ──────────────────────────────────────────────
       El .cc-stack global apila cada campo en un renglón de ~.5rem+borde → con 6 datos
       + acciones la tarjeta rondaba ~250px y solo cabían ~4 personas por pantalla. Quien
       abre el Crew List busca teléfono/correo/F.Nac, así que NADA se esconde: se COMPACTA.
       Todo va acotado a `.crew-page .crew-table.cc-stack` para NO tocar las demás tablas
       que comparten .cc-stack (gafetes, catálogos, roles…). Solo <768px. */
    @media (max-width: 767px) {
        .crew-page .crew-table.cc-stack tr {
            position: relative;
            margin: 0 0 .5rem;
            padding: 0 .1rem .2rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-2);
        }
        /* Campos: etiqueta:valor en UNA línea compacta, tipografía y espaciado ajustados,
           sin borde entre celdas (la tarjeta ya los agrupa). */
        .crew-page .crew-table.cc-stack td {
            padding: .11rem .7rem;
            font-size: .82rem;
            line-height: 1.32;
            border-bottom: 0;
            min-height: 0;
        }
        .crew-page .crew-table.cc-stack td::before {
            font-size: .72rem;
            font-weight: 600;
        }
        /* Cabecera de tarjeta = celda "Miembro": ancho completo, sin etiqueta, avatar chico,
           deja hueco a la derecha para el kebab flotante. */
        .crew-page .crew-table.cc-stack td[data-label="Miembro"] {
            justify-content: flex-start;
            text-align: left;
            gap: .55rem;
            padding: .4rem 2.6rem .35rem .6rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: .1rem;
        }
        .crew-page .crew-table.cc-stack td[data-label="Miembro"]::before { content: ""; margin: 0; }
        .crew-page .crew-table.cc-stack td[data-label="Miembro"] .crew-avatar { width: 34px; height: 34px; }
        .crew-page .crew-table.cc-stack td[data-label="Miembro"] .crew-name { font-size: .9rem; }
        .crew-page .crew-table.cc-stack td[data-label="Miembro"] .crew-sub { font-size: .74rem; }
        /* Acciones: kebab FLOTANTE en la esquina superior derecha → no gasta un renglón. */
        .crew-page .crew-table.cc-stack td.text-end {
            position: absolute;
            top: .3rem;
            right: .3rem;
            width: auto;
            padding: 0;
        }
        .crew-page .crew-table.cc-stack td.text-end::before { content: ""; }
    }

    /* ── Barra de acciones en TELÉFONO (2026-08-07) ────────────────────────────────
       Antes el buscador compartía renglón con "Exportar" + "Nuevo miembro" y quedaba
       aplastado a ~"Busc". Ahora el buscador toma su PROPIA fila (100%) y los dos
       botones envuelven debajo al 50/50, con objetivos táctiles parejos. Solo <576px;
       de sm en adelante la barra vuelve a ir en línea. */
    @media (max-width: 575.98px) {
        .crew-page .crew-actions-bar .crew-search { flex: 1 1 100%; }
        .crew-page .crew-actions-bar > .dropdown { flex: 1 1 0; }
        .crew-page .crew-actions-bar > .dropdown > .dropdown-toggle { width: 100%; justify-content: center; }
        .crew-page .crew-actions-bar > .btn { flex: 1 1 0; justify-content: center; }
    }
</style>
