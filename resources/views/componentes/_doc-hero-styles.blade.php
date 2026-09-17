{{--
    Estilos + auto-ajuste del HERO homologado de documentos (Acto Inseguro, Condición Insegura,
    Accidente). Reproduce EXACTAMENTE el hero del Daily Safety Report (patrón de oro) para que los
    reportes compartan la MISMA cabecera. Se incluye UNA vez por vista, después del <style> propio
    del reporte. NO redefine :root / .font-poster / .badge / .brand-corner (ya existen en cada vista).
--}}
<style>
    /* Fuente de marca del hero (small caps). Las vistas de incidente no la cargaban. */
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSCLight-Regular.ttf') format('truetype'); font-weight:300; font-style:normal; font-display:swap; }
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSC-Regular.ttf')      format('truetype'); font-weight:400; font-style:normal; font-display:swap; }
    @font-face { font-family:'Aspire SC'; src:url('/fonts/aspire-sc/AspireSCBlack-Regular.ttf')  format('truetype'); font-weight:900; font-style:normal; font-display:swap; }

    /* ===== HERO homologado (idéntico al DSR) ===== */
    .doc-hero { position: relative; width: 100%; height: 200px; overflow: hidden; background:#0f141c; }
    .doc-hero .hero-bg { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .doc-hero .hero-veil { position:absolute; inset:0; background: linear-gradient(90deg, rgba(6,10,16,.55) 0%, rgba(6,10,16,.12) 38%, rgba(6,10,16,.10) 60%, rgba(6,10,16,.60) 100%); }
    .doc-hero .hero-orange { position:absolute; left:0; right:0; bottom:0; height:4px; background: var(--brand-primary); z-index:3; }
    /* Nombre del proyecto (auto-ajustado al ancho de la caja) */
    .hero-side { position:absolute; top:14px; right:20px; z-index:3; width:360px; text-align:right; }
    .hero-project { font-family:'Aspire SC', sans-serif; font-weight:400; color:#fff; line-height:1.05; letter-spacing:.01em;
        white-space:nowrap; overflow:visible; padding-top:2px; text-shadow:0 3px 14px rgba(0,0,0,.6); }
    .hero-callbox { display:inline-block; background:#000; padding:5px 10px; margin-top:8px; text-align:center; }
    .hero-callbox .cl-loc { font-family:'Courier Prime', monospace; font-weight:700; color:#fff; font-size:12px; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap; overflow:hidden; }
    .hero-callbox .cl-date { font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; color:#e7e7e7; font-size:10px; letter-spacing:.08em; margin-top:2px; text-transform:uppercase; }
    /* Sub-línea del "llamado" (Int./Ext. · día/noche | escenas). MISMA familia monospace que la
       fecha: sin esta regla heredaba la sans del body y salía en otra tipografía que el resto del
       cuadro. El resto (tamaño/color/margen) lo fija el estilo inline del parcial _doc-hero. */
    .hero-callbox .cl-meta { font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    /* CrewCare + módulo (pie del hero): blanco esmerilado + esquinas superiores redondeadas + negro. */
    .hero-brand { position:absolute; left:50%; bottom:0; transform:translateX(-50%); z-index:2; text-align:center;
        padding:2px 14px 3px; border-radius:8px 8px 0 0; background:rgba(255,255,255,.55); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); }
    .hero-brand .cc { font-family:'Aspire SC', sans-serif; color:#0f141c; font-size:12px; letter-spacing:.03em; line-height:1; text-transform:uppercase; }
    .hero-brand .cc .crew { font-weight:300; }
    .hero-brand .cc .care { font-weight:400; }
    .hero-brand .mod { font-family:'Roboto', system-ui, sans-serif; font-weight:700; color:#374151; font-size:6px; letter-spacing:.28em; margin-top:1px; text-transform:uppercase; }
    /* Logo (placa esmerilada) top-left */
    .hero-logo { position:absolute; top:16px; left:16px; z-index:3; }

    /* ===== Cintillo "de un vistazo" bajo el hero — JERARQUÍA: LEAD primario + STATS secundarios ===== */
    .hero-band { display:flex; align-items:stretch; background:#0b0f16; border-bottom:1px solid #1f2733; }
    /* LEAD: bloque primario (sujeto del reporte). Acento de marca a la izquierda + nombre grande. */
    .hero-band .hb-lead { flex:1.8 1 0; display:flex; align-items:center; gap:12px; padding:9px 18px;
        border-left:3px solid var(--brand-primary); border-right:1px solid #1f2733; min-width:0; }
    .hero-band .hb-ico { font-size:22px; line-height:1; flex-shrink:0; }
    .hero-band .hb-txt { min-width:0; display:block; }
    /* El nombre (evento/persona) envuelve hasta 2 líneas para no cortar info importante del catálogo. */
    .hero-band .hb-name { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
        font-family:'Roboto Condensed', sans-serif; font-weight:700; font-size:15px;
        color:#fff; line-height:1.15; text-transform:uppercase; letter-spacing:.01em; overflow:hidden; }
    .hero-band .hb-sub { display:block; font-size:11px; color:#c7ccd6; line-height:1.25; margin-top:1px;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    /* STATS: celdas secundarias */
    .hero-band .hb-stats { flex:2 1 0; display:flex; min-width:0; }
    .hero-band .hb-cell { flex:1 1 0; display:flex; flex-direction:column; justify-content:center;
        padding:9px 12px; border-right:1px solid #1f2733; text-align:center; min-width:0; }
    .hero-band .hb-cell:last-child { border-right:0; }
    .hero-band .hb-lbl { display:block; font-size:8.5px; color:#8b93a3; text-transform:uppercase; letter-spacing:.16em; margin-bottom:3px; }
    .hero-band .hb-val { display:block; font-size:14px; font-weight:700; color:#fff; line-height:1.1;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .hero-band .hb-val.ok   { color:#34d399; }
    .hero-band .hb-val.warn { color:#f87171; }

    @media print {
        /* En print no hay backdrop-filter: el logo sube a un blanco esmerilado VISIBLE y ALINEADO. */
        .hero-logo-plate { background-color: rgba(255,255,255,0.34) !important;
            border: 1px solid rgba(255,255,255,0.45) !important;
            box-shadow: 0 6px 18px rgba(0,0,0,.35) !important; }
        .doc-hero, .hero-band { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>
<script>
(function () {
    /* Auto-ajuste del nombre del proyecto y de la locación al ancho de su caja (encoge, nunca
       desborda). Idéntico al DSR pero por CLASE (soporta el hero incluido una vez por vista).
       Guard para no re-registrar si dos parciales lo incluyeran. */
    if (window.__ccHeroFit) return; window.__ccHeroFit = true;
    function fit(el, maxPx, minPx) {
        if (!el) return;
        var size = maxPx; el.style.fontSize = size + 'px';
        while (el.scrollWidth > el.clientWidth && size > minPx) { size -= 1; el.style.fontSize = size + 'px'; }
    }
    function run() {
        document.querySelectorAll('.hero-project').forEach(function (el) { fit(el, 46, 18); });
        document.querySelectorAll('.hero-callbox .cl-loc').forEach(function (el) { fit(el, 12, 8); });
    }
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(run); }
    window.addEventListener('load', run);
    run();
})();
</script>
