<style>
    /* ===== Llamado (motor de horarios) ===== */
    /* Verde de "encendido" para toggles y tenedor: apagado = gris, encendido = verde (legible en claro/oscuro).
       En :root para que resuelva aunque el switch/tenedor viva fuera de .cs-wrap. */
    :root { --cs-on:#22c55e; }
    .cs-wrap { max-width: 1040px; }

    .adm-header { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .adm-icon {
        width:48px; height:48px; border-radius:14px; flex:none;
        display:inline-flex; align-items:center; justify-content:center; color:var(--brand-primary);
        background:color-mix(in srgb, var(--brand-primary) 16%, transparent); border:1px solid var(--stroke);
    }
    .adm-icon .cc-ico { width:22px; height:22px; }
    .adm-title { font-family:'Poppins',sans-serif; font-weight:600; letter-spacing:-.02em; margin:0; color:var(--text); font-size:1.35rem; }
    .adm-subtitle { color:var(--text-muted); font-size:.9rem; margin:.15rem 0 0; }

    /* Cabecera de día + pestañas */
    .cs-head { margin:1rem 0 1.25rem; }
    .cs-head__top { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:.75rem; }
    .cs-daynav { display:flex; align-items:center; gap:.5rem; }
    .cs-daybtn {
        width:38px; height:38px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center;
        border:1px solid var(--stroke); background:var(--glass); color:var(--text); text-decoration:none;
    }
    .cs-daybtn:hover { border-color:var(--brand-primary); color:var(--brand-primary); }
    .cs-daybtn .cc-ico { width:18px; height:18px; }
    .cs-daynav__center { text-align:center; min-width:150px; }
    .cs-daylabel { display:block; font-weight:700; color:var(--text); font-size:1.05rem; }
    .cs-datestr { display:block; color:var(--text-muted); font-size:.8rem; text-transform:capitalize; }
    .cs-head__actions { display:flex; gap:.5rem; flex-wrap:wrap; }
    .cs-head__actions .cc-ico { width:15px; height:15px; }

    .cs-tabs { display:flex; gap:.35rem; border-bottom:1px solid var(--stroke); flex-wrap:wrap; }
    .cs-tab {
        display:inline-flex; align-items:center; gap:.4rem; padding:.55rem .9rem; text-decoration:none;
        color:var(--text-muted); font-weight:600; font-size:.9rem; border-bottom:2px solid transparent; margin-bottom:-1px;
    }
    .cs-tab .cc-ico { width:16px; height:16px; }
    .cs-tab:hover { color:var(--text); }
    .cs-tab.is-active { color:var(--brand-primary); border-bottom-color:var(--brand-primary); }

    /* Tarjetas + campos */
    .cs-card { margin-bottom:1.25rem; }
    .cs-card .card-header {
        background:transparent; color:var(--text); font-weight:600; letter-spacing:.01em;
        border-bottom:1px solid var(--stroke); border-radius:var(--radius) var(--radius) 0 0;
    }
    .cs-wrap .form-label { color:var(--text); font-weight:600; }
    .cs-wrap .form-text { color:var(--text-muted); }
    .cs-wrap .form-control, .cs-wrap .form-select {
        background-color:var(--glass); border:1px solid var(--stroke); color:var(--text);
    }
    .cs-wrap .form-control:focus, .cs-wrap .form-select:focus {
        background-color:var(--bg-2); border-color:var(--brand-primary); color:var(--text);
        box-shadow:0 0 0 .2rem rgba(var(--brand-primary-rgb), .22);
    }
    .cs-wrap .form-control::placeholder { color:var(--text-muted); opacity:.7; }

    /* Comidas */
    .cs-meal { display:grid; grid-template-columns:auto 1.4fr .7fr 1fr .7fr .7fr auto; gap:.5rem; align-items:center; padding:.4rem 0; border-bottom:1px dashed var(--stroke); }
    .cs-meal:last-child { border-bottom:0; }
    .cs-meal__head { display:grid; grid-template-columns:auto 1.4fr .7fr 1fr .7fr .7fr auto; gap:.5rem; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted); padding-bottom:.25rem; }
    .cs-meal input, .cs-meal select { font-size:.85rem; }
    .cs-icon-btn { background:none; border:1px solid var(--stroke); border-radius:8px; color:var(--text-muted); width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
    .cs-icon-btn:hover { color:#dc3545; border-color:#dc3545; }
    @media (max-width:820px){
        .cs-meal, .cs-meal__head { grid-template-columns:1fr 1fr; }
        .cs-meal__head { display:none; }
        .cs-meal { border:1px solid var(--stroke); border-radius:10px; padding:.6rem; margin-bottom:.5rem; }
    }

    /* Pantalla Departamentos: tarjetas COMPACTAS (solo deptos con gente ese día). */
    .cs-depts { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:.6rem; }
    .cs-dept {
        border:1px solid var(--stroke); border-radius:11px; padding:.6rem .7rem; background:var(--glass);
    }
    .cs-dept__name { font-weight:700; color:var(--text); font-size:.9rem; display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
    .cs-dept__count { flex:none; color:var(--text-muted); font-weight:600; font-size:.72rem; background:var(--surface-3); border-radius:999px; padding:.05rem .45rem; font-variant-numeric:tabular-nums; }
    /* Canal de radio del depto (atributo global; editable aquí para no ir al catálogo). */
    .cs-dept__radio { display:flex; align-items:center; gap:.35rem; margin:.4rem 0 .45rem; }
    .cs-dept__radio label { color:var(--text-muted); font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; font-weight:700; margin:0; }
    .cs-dept__radio input { flex:1; min-width:0; padding:.15rem .4rem; font-size:.8rem; background:var(--glass); border:1px solid var(--stroke); border-radius:7px; color:var(--text); }
    .cs-dept__radio input:focus { outline:none; border-color:var(--brand-primary); }
    .cs-dept__row { display:flex; gap:.35rem; align-items:center; }
    .cs-dept__row .form-control { font-size:.85rem; font-variant-numeric:tabular-nums; }
    .cs-dept__row .form-control, .cs-dept__row .form-select { padding:.25rem .4rem; }
    .cs-suggest { color:var(--text-muted); font-size:.72rem; font-variant-numeric:tabular-nums; }
    .cs-quick { display:flex; gap:.2rem; flex-wrap:wrap; margin-top:.35rem; }
    .cs-quick button { font-size:.68rem; padding:.1rem .4rem; border:1px solid var(--stroke); border-radius:999px; background:transparent; color:var(--text-muted); cursor:pointer; }
    .cs-quick button:hover { border-color:var(--brand-primary); color:var(--brand-primary); }

    /* Pantalla Personas: tabla */
    .cs-ptable { width:100%; border-collapse:collapse; }
    .cs-ptable th { text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted); padding:.4rem .5rem; border-bottom:1px solid var(--stroke); position:sticky; top:0; background:var(--bg); }
    .cs-ptable td { padding:.35rem .5rem; border-bottom:1px solid var(--stroke); vertical-align:middle; }
    .cs-ptable .form-control, .cs-ptable .form-select { font-size:.82rem; padding:.2rem .4rem; }
    .cs-deptband td { background:color-mix(in srgb, var(--brand-primary) 8%, var(--surface-2)); font-weight:700; color:var(--text); }
    .cs-mealchk { width:20px; height:20px; }
    /* Tenedor: marca de comida (encendido = come). El checkbox va oculto pero enfocable. */
    .cs-fork { display:inline-flex; cursor:pointer; }
    .cs-fork input { position:absolute; opacity:0; width:0; height:0; }
    .cs-fork svg { width:20px; height:20px; color:var(--text-muted); opacity:.28; transition:opacity .12s ease, color .12s ease; }
    .cs-fork input:checked + svg { color:var(--cs-on); opacity:1; }
    .cs-fork input:focus-visible + svg { outline:2px solid var(--cs-on); outline-offset:2px; border-radius:4px; }
    .cs-ptable th .cc-ico { width:15px; height:15px; vertical-align:middle; }
    .cs-scroll { overflow-x:auto; }
    .cs-filters { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-bottom:.75rem; }
    .cs-filters .form-control, .cs-filters .form-select { max-width:260px; }

    .cs-note { border-radius:10px; padding:.7rem .9rem; font-size:.88rem; border:1px solid var(--stroke); background:var(--glass); color:var(--text-muted); }

    /* ===== Config rediseñada ===== */
    /* Configuración inicial en una línea: general · Cast · BG (+ wrap). */
    .cs-hero { display:flex; gap:1.1rem; align-items:flex-end; flex-wrap:wrap;
        border:1px solid var(--stroke); border-radius:16px; padding:1.1rem 1.3rem; margin-bottom:.6rem;
        background:linear-gradient(180deg, color-mix(in srgb, var(--brand-primary) 6%, var(--surface-2)), var(--surface-2)); }
    .cs-hero__field { display:flex; flex-direction:column; }
    .cs-hero__label { display:block; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); font-weight:700; margin-bottom:.3rem; }
    .cs-hero__time, .cs-hero__num { font-weight:700; font-variant-numeric:tabular-nums; letter-spacing:-.01em;
        background:var(--bg-2); border:1px solid var(--stroke); border-radius:12px; color:var(--text); padding:.3rem .6rem; }
    .cs-hero__time { font-size:1.75rem; width:auto; max-width:190px; }
    .cs-hero__num { font-size:1.5rem; width:110px; }
    .cs-hero__time:focus, .cs-hero__num:focus { outline:none; border-color:var(--brand-primary); box-shadow:0 0 0 .2rem rgba(var(--brand-primary-rgb), .22); }
    .cs-hero__sep { align-self:stretch; width:1px; background:var(--stroke); margin:.2rem 0; }
    .cs-hero__hint { color:var(--text-muted); font-size:.82rem; margin:0 0 1.25rem; }
    @media (max-width:575px){ .cs-hero__sep { display:none; } .cs-hero__time, .cs-hero__num { font-size:1.4rem; } }

    /* Firma para aprobación: fila destacada. */
    .cs-sign { display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem;
        border:1px solid var(--stroke); border-left:4px solid var(--brand-primary); border-radius:12px;
        padding:.9rem 1.1rem; background:color-mix(in srgb, var(--brand-primary) 5%, var(--surface-2)); }
    .cs-sign__ico { flex:none; width:40px; height:40px; border-radius:11px; display:inline-flex; align-items:center; justify-content:center;
        color:var(--brand-primary); background:color-mix(in srgb, var(--brand-primary) 16%, transparent); }
    .cs-sign__ico .cc-ico { width:20px; height:20px; }
    .cs-sign__body { flex:1; min-width:0; }
    .cs-sign__title { font-weight:700; color:var(--text); }
    .cs-sign__sub { color:var(--text-muted); font-size:.83rem; }
    .cs-switch--lg { width:46px; height:26px; }
    .cs-switch--lg > span { width:46px; height:26px; }
    .cs-switch--lg > span::after { width:22px; height:22px; }
    .cs-switch--lg input:checked + span::after { transform:translateX(20px); }

    /* Comidas como cards. */
    .cs-meals { display:grid; grid-template-columns:repeat(auto-fill, minmax(215px, 1fr)); gap:.75rem; }
    .cs-mcard { border:1px solid var(--stroke); border-radius:12px; padding:.7rem .8rem; background:var(--glass); transition:opacity .15s ease; }
    .cs-mcard.is-off { opacity:.5; }
    .cs-mcard__top { display:flex; align-items:center; gap:.5rem; margin-bottom:.55rem; }
    .cs-mcard__label { flex:1; border:none; background:transparent; color:var(--text); font-weight:700; font-size:.95rem; padding:.1rem .1rem; min-width:0; }
    .cs-mcard__label:focus { outline:none; border-bottom:1px solid var(--brand-primary); }
    /* Hora y lugar apilados: el select de Lugar cabe completo y es clicable (antes se salía del card). */
    .cs-mcard__row { display:flex; flex-direction:column; gap:.4rem; }
    .cs-mcard__row .form-control,
    .cs-mcard__row .form-select { width:100%; min-width:0; background:var(--bg-2); border:1px solid var(--stroke); color:var(--text); font-variant-numeric:tabular-nums; }
    .cs-mcard__row .form-select { font-size:.82rem; }

    /* Toggle switch chico. */
    .cs-switch { position:relative; display:inline-flex; flex:none; width:34px; height:20px; cursor:pointer; }
    .cs-switch input[type=checkbox] { position:absolute; opacity:0; width:0; height:0; }
    .cs-switch > span { width:34px; height:20px; border-radius:999px; background:var(--stroke-2); border:1px solid color-mix(in srgb, var(--text) 12%, transparent); transition:background .15s ease, border-color .15s ease; position:relative; }
    .cs-switch > span::after { content:''; position:absolute; top:2px; left:2px; width:16px; height:16px; border-radius:50%; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.35); transition:transform .15s ease; }
    .cs-switch input:checked + span { background:var(--cs-on); border-color:var(--cs-on); }
    .cs-switch input:checked + span::after { transform:translateX(14px); }
    .cs-switch input:focus-visible + span { outline:2px solid var(--cs-on); outline-offset:2px; }

    /* Contingente. */
    .cs-count-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; max-width:420px; }
    .cs-count .form-control { font-size:1.3rem; font-weight:700; font-variant-numeric:tabular-nums; background:var(--glass); border:1px solid var(--stroke); color:var(--text); }

    .cs-inline-switch { display:inline-flex; align-items:center; gap:.55rem; color:var(--text); font-size:.9rem; cursor:pointer; }
    .cs-inline-switch input { width:1.05rem; height:1.05rem; accent-color:var(--brand-primary); }
</style>
