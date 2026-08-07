{{-- ============================================================================================
     REPORT v2 — CHROME COMPARTIDO (fuentes + estilos "Cinematic Dark Glass" + motor de impresión).
     Fuente única de verdad del diseño v2 para TODOS los reportes impresos. Editar aquí = editar todos.
     Depende de $primary (color de marca) del scope que lo incluye. Va dentro de <head>.
============================================================================================ --}}
{{-- (2026-07-23) Fuentes AUTOALOJADAS (offline). Antes cargaban desde fonts.googleapis.com → sin
     internet los 5 reportes caían a fuentes del sistema y cambiaba la tipografía del documento del
     estudio (incluida la cadena CFDI monoespaciada). Ahora se sirven LOCAL desde public/fonts/reports/
     (Poppins/Roboto Condensed/Roboto Mono/Courier Prime; OFL/Apache; subsets latin+latin-ext para
     ES/EN con acentos). Ruta raíz-relativa porque asset() está roto. Regenerar el CSS y los woff2:
     scratchpad/fetch_fonts.php. --}}
<link rel="stylesheet" href="/fonts/reports/report-fonts.css">
<style>
  :root{
    --bg-deep:#090C13; --bg:#0C1019; --sheet:rgba(20,26,38,.72); --panel:rgba(255,255,255,.04);
    --stroke:rgba(255,255,255,.10); --stroke-2:rgba(255,255,255,.16);
    --text:#EBEEF4; --muted:#9AA3B4; --faint:#6A7386;
    --brand:{{ $primary }}; --brand-primary:{{ $primary }}; --brand-glow:{{ $primary }}55; --brand-ink:#1a0d02;
    --ok:#34D399; --warn:#FBBF24; --danger:#FF6B6B;
    --r-1:#2f9e6b; --r-3:#e8b230; --r-4:#e8792f; --r-5:#e0463f;
    --radius:16px; --radius-sm:11px; --ease:cubic-bezier(.16,1,.3,1);
    --font:'Poppins',ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    --mono:'Roboto Mono',ui-monospace,Consolas,monospace;
    --poster:'Roboto Condensed',var(--font);
    --pad:34px; color-scheme:dark;
  }
  :root[data-theme="light"]{
    --bg-deep:#E7EBF2; --bg:#EEF1F7; --sheet:rgba(255,255,255,.9); --panel:rgba(16,24,40,.02);
    --stroke:rgba(16,24,40,.10); --stroke-2:rgba(16,24,40,.18);
    --text:#141925; --muted:#5A6472; --faint:#8892A2; --brand-ink:#fff; color-scheme:light;
  }
  :root[data-view="print"]{
    --bg:#fff; --bg-deep:#fff; --sheet:#fff; --panel:#fbfcfd;
    --stroke:#d7dbe2; --stroke-2:#b9c0cc; --text:#14181f; --muted:#4a5261; --faint:#6b7382; color-scheme:light;
    /* (2026-07-22) Semánticos re-tokenizados PARA PAPEL. En pantalla oscura #34D399/#FBBF24/#FF6B6B
       son correctos, pero sobre blanco quedan a ~1.7:1 (ilegibles). Estas variantes oscuras dan
       AA (>4.5:1) y curan los 5 reportes de golpe — incluido .seal.ok, la línea peor contrastada
       del documento firmable. Arreglo CENTRAL (antes el DSR lo hacía local por clase). */
    --ok:#15803d; --warn:#b45309; --danger:#b91c1c;
  }
  *{box-sizing:border-box}
  html,body{margin:0}
  body{font-family:var(--font);color:var(--text);background:var(--bg);-webkit-font-smoothing:antialiased;line-height:1.5}

  /* Icono _icon por defecto (.cc-ico): 1em, escala con la tipografía del contexto. Estos
     documentos standalone NO cargan Tailwind, así que sin esta regla un icono suelto (fuera de
     un contenedor que dimensione el svg) saldría a tamaño gigante. Los selectores contextuales
     (.sec-h svg, .chip svg, .btn svg, .seal svg…) siguen ganando donde existen. */
  .cc-ico{width:1.05em;height:1.05em;flex:none;vertical-align:-.15em}

  .ambient{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
  .ambient .b{position:absolute;border-radius:50%;filter:blur(80px);opacity:.5}
  .ambient .b1{width:560px;height:560px;left:-140px;top:-120px;background:radial-gradient(circle,var(--brand-glow),transparent 66%);animation:d1 28s var(--ease) infinite alternate}
  .ambient .b2{width:620px;height:620px;right:-180px;top:28%;background:radial-gradient(circle,rgba(94,106,210,.15),transparent 70%);animation:d2 34s var(--ease) infinite alternate}
  @keyframes d1{to{transform:translate(60px,40px) scale(1.1)}}
  @keyframes d2{to{transform:translate(-60px,50px) scale(1.08)}}
  :root[data-view="print"] .ambient{display:none}

  .toolbar{position:fixed;top:16px;right:16px;z-index:50;display:flex;gap:8px;align-items:center;padding:8px;
    background:rgba(255,255,255,.06);backdrop-filter:blur(20px) saturate(1.3);-webkit-backdrop-filter:blur(20px) saturate(1.3);
    border:1px solid var(--stroke-2);border-radius:14px;box-shadow:0 12px 34px -12px rgba(0,0,0,.6)}
  :root[data-theme="light"] .toolbar{background:rgba(255,255,255,.72)}
  .tb{display:flex;align-items:center;gap:8px;height:40px;padding:0 14px;border-radius:10px;border:1px solid var(--stroke);
    background:transparent;color:var(--text);font:600 .84rem/1 var(--font);cursor:pointer;text-decoration:none;transition:.16s}
  .tb:hover{border-color:var(--stroke-2);background:rgba(255,255,255,.06)}
  .tb svg{width:16px;height:16px}
  .tb.primary{background:linear-gradient(150deg,var(--brand),color-mix(in srgb,var(--brand) 70%,#fff));color:var(--brand-ink);border:0;box-shadow:0 8px 22px -8px var(--brand-glow)}
  .tb.ico{width:40px;justify-content:center;padding:0}

  /* ---- Controles operativos (no-print): tira sobre el documento + botones/campos ---- */
  .no-print{}
  .ops{width:100%;max-width:860px;margin:0 auto 12px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between}
  .ops form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0}
  .ops .ops-note{font-size:.78rem;color:var(--muted)}
  .field{height:38px;padding:0 12px;border-radius:10px;border:1px solid var(--stroke-2);background:var(--panel);color:var(--text);font:600 .82rem/1 var(--font)}
  .btn{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 14px;border-radius:10px;border:1px solid var(--stroke);
    background:transparent;color:var(--text);font:700 .8rem/1 var(--font);cursor:pointer;text-decoration:none;transition:.16s}
  .btn:hover{border-color:var(--stroke-2);background:rgba(255,255,255,.06)}
  .btn svg{width:15px;height:15px}
  .btn.brand{background:linear-gradient(150deg,var(--brand),color-mix(in srgb,var(--brand) 70%,#fff));border:0;color:var(--brand-ink)}
  .btn.ok{background:var(--ok);border:0;color:#04120b}
  .btn.sm{height:30px;padding:0 11px;font-size:.72rem;border-radius:8px}
  .alert{width:100%;max-width:860px;margin:0 auto 12px;padding:11px 15px;border-radius:12px;font-size:.82rem;border:1px solid var(--stroke)}
  .alert.ok{background:color-mix(in srgb,var(--ok) 12%,transparent);border-color:color-mix(in srgb,var(--ok) 40%,transparent);color:var(--ok)}
  .alert.bad{background:color-mix(in srgb,var(--danger) 12%,transparent);border-color:color-mix(in srgb,var(--danger) 40%,transparent);color:var(--danger)}

  .stage{position:relative;z-index:1;min-height:100vh;padding:74px 20px 60px;display:flex;justify-content:center}
  :root[data-view="print"] .stage{padding:0}
  .sheet{width:100%;max-width:860px;background:var(--sheet);border:1px solid var(--stroke);border-radius:var(--radius);
    overflow:hidden;box-shadow:0 40px 90px -30px rgba(0,0,0,.7);backdrop-filter:blur(22px) saturate(1.25);-webkit-backdrop-filter:blur(22px) saturate(1.25)}
  :root[data-view="print"] .sheet{max-width:none;border:0;border-radius:0;box-shadow:none;backdrop-filter:none}

  /* hero */
  .hero{position:relative;height:196px;overflow:hidden;background:#0c1119}
  .hero .img{position:absolute;inset:0;background-size:cover;background-position:center;
    background-image:linear-gradient(120deg,#12203a,#0c1119 55%,#241016)}
  .hero .veil{position:absolute;inset:0;background:linear-gradient(90deg,rgba(6,10,16,.74),rgba(6,10,16,.2) 42%,rgba(6,10,16,.68))}
  .hero .plate{position:absolute;top:16px;left:18px;display:flex;align-items:center;gap:9px;padding:8px 12px;border-radius:12px;
    background:rgba(255,255,255,.14);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.2)}
  .hero .plate .m{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;color:#1a0d02;
    background:linear-gradient(150deg,var(--brand),color-mix(in srgb,var(--brand) 70%,#fff))}
  .hero .plate .m svg{width:15px;height:15px}
  .hero .plate b{color:#fff;font-size:.9rem;letter-spacing:-.01em;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .hero .side{position:absolute;top:16px;right:20px;text-align:right;z-index:3;max-width:62%}
  .hero .side .proj{font-family:var(--poster);font-weight:900;font-style:italic;text-transform:uppercase;font-size:1.7rem;color:#fff;line-height:1;text-shadow:0 3px 16px rgba(0,0,0,.6)}
  .hero .callbox{display:inline-block;margin-top:9px;background:rgba(0,0,0,.66);padding:6px 11px;border-radius:8px;text-align:center}
  .hero .callbox .loc{font-family:var(--mono);font-weight:600;color:#fff;font-size:.72rem;letter-spacing:.03em;text-transform:uppercase}
  .hero .callbox .dt{font-family:var(--mono);color:#cfd4de;font-size:.66rem;letter-spacing:.05em;margin-top:2px}
  .hero .tag{position:absolute;left:50%;bottom:0;transform:translateX(-50%);z-index:3;text-align:center;padding:3px 16px 4px;
    border-radius:9px 9px 0 0;background:rgba(255,255,255,.64);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
  .hero .tag .cc{font-weight:800;color:#0f141c;font-size:.78rem}
  .hero .tag .cc .care{color:var(--brand)}
  .hero .tag .md{font-size:.54rem;font-weight:700;letter-spacing:.24em;text-transform:uppercase;color:#374151;margin-top:1px}
  .hero .rule{position:absolute;left:0;right:0;bottom:0;height:4px;z-index:4;background:linear-gradient(90deg,var(--brand),color-mix(in srgb,var(--brand) 60%,#fff))}
  :root[data-view="print"] .hero{height:150px}
  :root[data-view="print"] .hero,:root[data-view="print"] .tag,:root[data-view="print"] .rule,:root[data-view="print"] .band{-webkit-print-color-adjust:exact;print-color-adjust:exact}

  /* quick-read band */
  .band{display:flex;background:rgba(0,0,0,.3);border-bottom:1px solid var(--stroke)}
  :root[data-view="print"] .band{background:#0b0f16}
  .band .lead{flex:1.7;display:flex;align-items:center;gap:12px;padding:11px 20px;border-left:3px solid var(--brand);border-right:1px solid var(--stroke);min-width:0}
  .band .lead .ic{width:26px;height:26px;color:var(--brand);flex:none}
  .band .lead .ic svg{width:26px;height:26px}
  .band .lead .who{display:flex;flex-direction:column;min-width:0;gap:1px}
  .band .lead .lbl{font-size:.58rem;letter-spacing:.16em;text-transform:uppercase;color:var(--muted)}
  .band .lead .val{font-weight:800;font-size:1rem;color:#fff;line-height:1.15;text-transform:uppercase;overflow-wrap:break-word;text-wrap:balance}
  .band .lead .sub{font-size:.74rem;color:#c7ccd6;margin-top:1px}
  :root[data-view="print"] .band .lead .val,:root[data-view="print"] .band .lead .sub,:root[data-view="print"] .band .cell .v{color:#fff}
  .band .stats{flex:2;display:flex;min-width:0}
  .band .cell{flex:1;display:flex;flex-direction:column;justify-content:center;padding:11px 12px;border-right:1px solid var(--stroke);text-align:center;min-width:0}
  .band .cell:last-child{border-right:0}
  .band .cell .lbl{font-size:.55rem;letter-spacing:.14em;text-transform:uppercase;color:var(--faint);margin-bottom:3px}
  :root[data-view="print"] .band .cell .lbl{color:#9aa3b4}
  .band .cell .v{font-weight:800;font-size:.92rem;color:#fff}
  .band .cell .v.warn{color:var(--danger)} .band .cell .v.ok{color:var(--ok)}

  /* body */
  .body{padding:var(--pad)}
  .sec{margin-bottom:22px;break-inside:avoid}
  .sec-h{display:flex;align-items:center;gap:9px;margin:0 0 12px}
  .sec-h .bar{width:8px;height:22px;background:var(--brand);transform:skewX(-12deg);flex:none}
  .sec-h svg{width:16px;height:16px;color:var(--brand);flex:none}
  .sec-h h2{margin:0;font-family:var(--poster);font-weight:900;font-style:italic;font-size:1.05rem;letter-spacing:.02em;text-transform:uppercase;color:var(--text)}
  .sec-h .line{flex:1;height:1px;background:var(--stroke)}
  .panel{background:var(--panel);border:1px solid var(--stroke);border-radius:var(--radius-sm);padding:15px 16px}
  .facts{display:grid;grid-template-columns:repeat(4,1fr);gap:14px 18px}
  .fact .k{font-size:.58rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:3px}
  .fact .v{font-weight:700;font-size:.9rem;word-break:break-word}
  .fact .v.mono{font-family:var(--mono);font-size:.8rem}
  .two{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}
  p.desc{margin:0;font-size:.9rem;color:var(--text);border-left:3px solid var(--stroke-2);padding-left:12px}
  .chips{display:flex;flex-wrap:wrap;gap:7px}
  .chip{display:inline-flex;align-items:center;gap:6px;font-size:.76rem;font-weight:600;padding:6px 11px;border-radius:20px;background:var(--panel);border:1px solid var(--stroke);color:var(--text)}
  .chip svg{width:13px;height:13px;color:var(--brand)}
  .chip.ok{color:var(--ok);border-color:color-mix(in srgb,var(--ok) 40%,transparent)} .chip.ok svg{color:var(--ok)}
  .chip.warn{color:var(--danger);border-color:color-mix(in srgb,var(--danger) 40%,transparent)} .chip.warn svg{color:var(--danger)}
  .badge{display:inline-flex;align-items:center;padding:2px 7px;border-radius:4px;font-size:9px;font-weight:800;color:#fff;letter-spacing:.05em}

  /* risk chips row */
  .riskrow{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
  .rc{display:inline-flex;align-items:center;gap:9px;padding:9px 14px;border-radius:14px;border:1px solid var(--stroke);background:var(--panel);min-height:44px}
  .rc svg{width:18px;height:18px;flex:none}
  .rc .t{display:flex;flex-direction:column;line-height:1.15}
  .rc .l{font-size:.55rem;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);font-weight:700}
  .rc .v{font-size:.86rem;font-weight:800}
  .rc.danger{border-color:color-mix(in srgb,var(--r-5) 55%,transparent)} .rc.danger svg,.rc.danger .v{color:var(--r-5)}
  .rc.warn{border-color:color-mix(in srgb,var(--r-4) 55%,transparent)} .rc.warn svg,.rc.warn .v{color:var(--r-4)}
  .rc.caution{border-color:color-mix(in srgb,var(--r-3) 55%,transparent)} .rc.caution svg,.rc.caution .v{color:var(--r-3)}
  .rc.ok{border-color:color-mix(in srgb,var(--ok) 55%,transparent)} .rc.ok svg,.rc.ok .v{color:var(--ok)}
  .rc.neutral svg,.rc.neutral .v{color:var(--muted)}

  /* 5x5 matrix */
  .matrix{display:grid;grid-template-columns:auto repeat(5,1fr);gap:4px;font-family:var(--mono)}
  .matrix .corner{font-size:.5rem;color:var(--faint);display:flex;align-items:flex-end;padding:3px}
  .matrix .ch{font-size:.52rem;text-align:center;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;padding:3px 1px;font-family:var(--font);font-weight:700}
  .matrix .rh{font-size:.52rem;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;display:flex;align-items:center;justify-content:flex-end;padding-right:6px;font-family:var(--font);font-weight:700;white-space:nowrap}
  .cell5{aspect-ratio:1.7/1;border-radius:5px;display:grid;place-items:center;font-size:.56rem;font-weight:800;color:rgba(0,0,0,.6)}
  .cell5.on{outline:3px solid #fff;outline-offset:-1px;box-shadow:0 0 0 2px var(--brand),0 6px 16px -4px var(--brand-glow);z-index:2;position:relative;color:#111}
  :root[data-view="print"] .cell5.on{outline:2px solid #111;box-shadow:0 0 0 2px var(--brand)}
  .mlegend{display:flex;gap:14px;margin-top:10px;font-size:.7rem;color:var(--muted);flex-wrap:wrap;align-items:center}
  .mlegend i{width:11px;height:11px;border-radius:3px;display:inline-block;vertical-align:-1px;margin-right:5px}

  /* photos */
  .photos{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
  .photo{aspect-ratio:4/3;border-radius:var(--radius-sm);border:1px solid var(--stroke);overflow:hidden;position:relative;background:#141a26}
  .photo img{width:100%;height:100%;object-fit:cover;display:block}
  .photo .cap{position:absolute;left:0;right:0;bottom:0;padding:6px 9px;font-size:.66rem;color:#fff;background:linear-gradient(0deg,rgba(0,0,0,.62),transparent)}

  /* table */
  .tbl{width:100%;border-collapse:collapse;font-size:.8rem}
  .tbl th{text-align:left;font-size:.58rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);padding:8px 10px;border-bottom:1px solid var(--stroke)}
  .tbl td{padding:8px 10px;border-bottom:1px solid var(--stroke);color:var(--text)}
  .tbl td.mono{font-family:var(--mono);font-size:.74rem}

  /* signature + seal */
  .sign{display:grid;grid-template-columns:1fr 1fr;gap:18px}
  .sig{border:1px dashed var(--stroke-2);border-radius:var(--radius-sm);padding:14px 16px}
  .sig .who{font-weight:700;font-size:.9rem;margin-top:26px;border-top:1px solid var(--stroke);padding-top:7px}
  .sig .role{font-size:.7rem;color:var(--muted)}
  .seal{display:flex;align-items:center;gap:11px;margin-top:14px;padding:12px 14px;border-radius:var(--radius-sm)}
  .seal svg{width:20px;height:20px;flex:none}
  /* Ya NO hay cintillo verde (2026-07-24): el documento sano no anuncia nada. Quedan el rojo
     (alterado) y el neutro (sin sellar / dato del anexo). .seal.ok se conserva como ALIAS del
     neutro, no como estado propio: si una vista vieja o un copy-paste vuelve a escribir
     "seal ok", saldrá gris en vez de quedarse sin fondo — degrada, no rompe. */
  .seal.ok{background:var(--panel);border:1px solid var(--stroke)}
  .seal.bad{background:color-mix(in srgb,var(--danger) 10%,transparent);border:1px solid color-mix(in srgb,var(--danger) 35%,transparent)}
  .seal.bad svg,.seal.bad b{color:var(--danger)}
  .seal.none{background:var(--panel);border:1px solid var(--stroke)}
  .seal .h{font-family:var(--mono);font-size:.7rem;color:var(--muted)}
  .restricted{font-size:.78rem;color:var(--faint);font-style:italic}

  /* Sello estilo CFDI (recuadro fiscal): marca/sello a la izquierda + sello digital, cadena
     original y metadatos a la derecha. Monoespaciado para los hashes. Imprimible. */
  .cfdi{display:flex;gap:14px;margin-top:12px;padding:14px;border:1px solid var(--stroke-2);border-radius:var(--radius-sm);background:var(--panel);break-inside:avoid}
  .cfdi-mark{flex:none;width:92px;height:92px;border:1px solid var(--stroke-2);border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;
    background:linear-gradient(150deg,color-mix(in srgb,var(--brand) 14%,transparent),transparent)}
  .cfdi-mark .m{width:36px;height:36px;color:var(--brand)}
  .cfdi-mark .m svg{width:36px;height:36px}
  .cfdi-mark .lbl{font-family:var(--mono);font-size:.5rem;font-weight:700;letter-spacing:.06em;text-align:center;color:var(--muted);line-height:1.25}
  /* Variante QR (2026-07-24): el mismo hueco, ahora ocupado por el código. Fondo BLANCO y
     relleno de 6px SIEMPRE, también en modo oscuro: el lector de QR necesita contraste y una
     zona de silencio alrededor; el generador se pide con margen 0 para no desperdiciar módulos
     y ese margen lo pone aquí el padding. 106px (no 92) porque el escudo se leía de lejos y un
     QR no: a 92px cada módulo quedaba por debajo de lo que una cámara resuelve en papel. */
  .cfdi-mark--qr{width:106px;height:106px;padding:6px;gap:0;background:#fff;
    -webkit-print-color-adjust:exact;print-color-adjust:exact}
  .cfdi-mark--qr svg{width:100%;height:100%;display:block}
  .cfdi-body{flex:1;min-width:0}
  .cfdi-row{margin-bottom:9px}
  .cfdi-k{font-size:.54rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:2px}
  .cfdi-v{font-family:var(--mono);font-size:.62rem;color:var(--text);word-break:break-all;line-height:1.5}
  .cfdi-meta{display:flex;flex-wrap:wrap;gap:5px 18px;margin-top:6px;padding-top:9px;border-top:1px solid var(--stroke);font-size:.66rem;color:var(--muted)}
  .cfdi-meta .mono{font-family:var(--mono);font-size:.6rem;word-break:break-all}
  /* Columna del identicon (2026-07-24): sólo el sello visual del hash, 56px, sin pie. El QR se
     mudó al hueco de la izquierda, así que esta columna mide exactamente lo que mide el dibujo:
     una columna de 140px con un dibujo de 56 dejaba un vacío que parecía un bloque roto. */
  .cfdi-verify{flex:none;width:56px;display:flex;flex-direction:column;align-items:center}
  .cfdi-idc{width:56px;height:56px}
  .cfdi-idc svg{width:100%;height:100%;display:block;border-radius:8px;border:1px solid var(--stroke-2)}
  /* En pantalla angosta el recuadro se apila; el QR NO encoge (a menos tamaño deja de leerse,
     que es justo para lo que existe) y el identicon deja de ocupar una columna propia. */
  @media (max-width:720px){ .cfdi{flex-direction:column} .cfdi-mark{width:74px;height:74px} .cfdi-mark--qr{width:106px;height:106px} .cfdi-verify{width:auto} }

  /* Selector de vista (lite ⇄ completa): segmentado, no-print. */
  .viewseg{display:inline-flex;gap:2px;background:var(--panel);border:1px solid var(--stroke-2);border-radius:12px;padding:3px}
  .viewseg .seg{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 13px;border-radius:9px;border:0;background:transparent;
    color:var(--text);font:700 .76rem/1 var(--font);cursor:pointer;text-decoration:none;transition:.16s}
  .viewseg .seg:hover{background:rgba(255,255,255,.06)}
  .viewseg .seg.active{background:linear-gradient(150deg,var(--brand),color-mix(in srgb,var(--brand) 70%,#fff));color:var(--brand-ink);cursor:default}
  .viewseg .seg.active:hover{background:linear-gradient(150deg,var(--brand),color-mix(in srgb,var(--brand) 70%,#fff))}

  .docfoot{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px var(--pad);border-top:1px solid var(--stroke);font-size:.66rem;color:var(--faint);font-family:var(--mono)}
  .docfoot .cc{font-family:var(--font);font-weight:700;color:var(--muted)}
  .docfoot .cc .care{color:var(--brand)}
  .caption{margin:22px auto 0;max-width:860px;font-size:.76rem;color:var(--faint);display:flex;gap:8px;align-items:flex-start;padding:0 4px}
  .caption svg{width:15px;height:15px;color:var(--brand);flex:none;margin-top:2px}
  :root[data-view="print"] .toolbar,:root[data-view="print"] .caption,:root[data-view="print"] .no-print,:root[data-view="print"] .ops,:root[data-view="print"] .alert{display:none}

  @media print{
    /* @page margin:0 → el hero (thead) y el pie (fixed) van a sangre en cada hoja.
       Hoja OFICIO (Oficio MX 216×340mm): más alto vertical que Carta → menos cortes/huecos. */
    @page{size:216mm 340mm;margin:0}
    /* Modo papel forzado aunque el JS no corra. Incluye los semánticos oscuros para papel
       (ver el bloque :root[data-view="print"] de arriba): sin esto, un Ctrl+P directo sin pasar
       por el botón "Vista impresión" imprimiría --ok/--warn/--danger de pantalla (ilegibles). */
    :root{--bg:#fff;--sheet:#fff;--panel:#fbfcfd;--stroke:#d7dbe2;--stroke-2:#b9c0cc;--text:#14181f;--muted:#4a5261;--faint:#6b7382;--ok:#15803d;--warn:#b45309;--danger:#b91c1c}
    .toolbar,.caption,.ambient,.docfoot,.no-print,.ops,.alert{display:none!important}
    body{background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .stage{padding:0}
    .sheet{max-width:none;border:0;border-radius:0;box-shadow:none;backdrop-filter:none}
    .sec{break-inside:avoid}
    /* Repetición por hoja: hero (thead) + espaciador de pie (tfoot). */
    .report-wrap>thead{display:table-header-group}
    .report-wrap>tfoot{display:table-footer-group}
    .footer-spacer{height:60px}
    .doc-hero{height:150px!important}
    .band{-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .body{padding:12mm 12mm 0}
    /* PIE fijo con el UUID, repetido al fondo real de CADA hoja. */
    .print-foot{display:flex;position:fixed;left:0;right:0;bottom:0;justify-content:space-between;align-items:center;
      padding:8px 12mm;background:#f9fafb;border-top:1px solid #e5e7eb;font-family:var(--mono);font-size:8pt;color:#6b7382}
    .print-foot .cc{font-family:var(--font);font-weight:700;color:#4a5261}
    .print-foot .cc .care{color:var(--brand)}
    .print-foot .uuid{font-size:7.5pt;text-align:right}
  }
  @media (max-width:720px){
    .facts{grid-template-columns:repeat(2,1fr)}
    .two,.sign{grid-template-columns:1fr}
    .photos{grid-template-columns:repeat(2,1fr)}
    .band{flex-direction:column}
    .hero .side .proj{font-size:1.25rem}
  }
  @media (prefers-reduced-motion:reduce){*{animation:none!important}}

  /* ===== Motor de paginación (tabla): thead=hero de marca + tfoot=espaciador → repiten por hoja ===== */
  .report-wrap{width:100%;border-collapse:collapse}
  .report-wrap>thead>tr>td,.report-wrap>tbody>tr>td,.report-wrap>tfoot>tr>td{padding:0;border:0}
  .footer-spacer{height:0}
  .print-foot{display:none}

  /* ===== NORMAS DEL HALLAZGO/RIESGO (N:M) — chips de componentes/_standards-chips =====
     (2026-08-06) EXTRAÍDO del DSR al chrome compartido cuando el PAE se volvió el 2º
     adoptante del parcial (el DSR ya lo anticipaba en su comentario). Valores IDÉNTICOS a
     los que tenía el DSR: mover al chrome no cambia un solo pixel de su render. */
  .std-row{display:flex;flex-wrap:wrap;gap:6px}
  .std-stack{display:flex;flex-direction:column;gap:5px;align-items:flex-end}
  .std-one{display:inline-flex;align-items:center;gap:6px;font-size:.62rem;line-height:1.25;
    border:1px solid var(--stroke);border-radius:7px;padding:3px 7px;max-width:100%}
  .std-code{font-family:var(--mono);color:var(--muted);white-space:nowrap}
  .std-cat{color:var(--faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .std-link{font-weight:700;color:var(--brand);text-decoration:none;white-space:nowrap}
  /* En la tarjeta basta marco + código: el nombre de la categoría satura la rejilla. */
  .std-row .std-cat{display:none}
</style>
{{-- Tokens de color de los chips .badge-XXX (fuera de <style>: el parcial trae el suyo con @once). --}}
@include('componentes._badge-tokens')
@include('componentes._doc-hero-styles')
<style>
  /* ===== Frost imprimible + FOOTER DE MARCA (2026-07-15, feedback owner) =====
     - El blur (frost) se CONSERVA en pantalla; el PDF lo descarta, así que en papel el frost se
       recrea con rellenos translúcidos. El logo-plate mantiene el frost SIN borde exterior.
     - Footer profesional: wordmark "CrewCare" en Aspire SC (fuente de marca); en papel = NEGRO. ===== */
  .hero-logo-plate{border:0!important} /* frost sí, borde exterior no */

  /* ---- FOOTER = SELLO DE DOCUMENTO: pequeño, sutil, gris tenue, discreto ---- */
  .docfoot,.print-foot{display:flex!important;justify-content:space-between;align-items:flex-end;gap:20px;
    padding:10px var(--pad)!important;border-top:1px solid var(--stroke)!important;font-family:var(--font)!important}
  .docfoot .ft-r,.print-foot .ft-r{text-align:right}
  .docfoot .ft-lbl,.print-foot .ft-lbl{font-size:6px;text-transform:uppercase;letter-spacing:.22em;font-weight:600;color:var(--faint);margin-bottom:3px}
  .docfoot .ft-name,.print-foot .ft-name{font-weight:600;text-transform:uppercase;font-size:.62rem;letter-spacing:.04em;color:var(--muted);line-height:1.1}
  .docfoot .ft-meta,.print-foot .ft-meta{font-size:.56rem;color:var(--faint);margin-top:2px}
  .docfoot .cc,.print-foot .cc{font-family:'Aspire SC',var(--font)!important;text-transform:uppercase;letter-spacing:.08em;font-size:.72rem;line-height:1;color:var(--muted)}
  .docfoot .cc .crew,.print-foot .cc .crew{font-weight:300}
  .docfoot .cc .care,.print-foot .cc .care{font-weight:400;color:inherit!important}
  .docfoot .ft-uuid,.print-foot .ft-uuid{font-family:var(--mono);font-size:.52rem;color:var(--faint);margin-top:4px;letter-spacing:.01em}

  /* Pie de impresión visible también en el preview "Vista impresión". */
  :root[data-view="print"] .stage{padding-bottom:74px}
  :root[data-view="print"] .print-foot{position:fixed;left:0;right:0;bottom:0;z-index:60;background:#fff;border-top:1px solid #eceef1}
  /* En papel: SELLO en gris tenue (no negro), muy discreto. */
  :root[data-view="print"] .print-foot .ft-name,:root[data-view="print"] .print-foot .cc{color:#9aa3af}
  :root[data-view="print"] .print-foot .ft-lbl,:root[data-view="print"] .print-foot .ft-meta,:root[data-view="print"] .print-foot .ft-uuid{color:#aab1bb}

  /* (2026-08-04) FOOTER DUPLICADO EN PANTALLA: la regla de arriba fuerza
     .docfoot,.print-foot{display:flex!important}, lo que anulaba el .print-foot{display:none}
     de pantalla → el pie de IMPRESIÓN se veía en el flujo, DEBAJO del pie de pantalla.
     Separación limpia: en PANTALLA (no preview) solo el .docfoot; en preview de impresión
     solo el .print-foot (como en papel). El @media screen evita tocar la impresión real. */
  @media screen {
    :root:not([data-view="print"]) .print-foot{display:none!important}
  }
  :root[data-view="print"] .docfoot{display:none!important}

  /* BANDA sólida (no degradado): pantalla oscura / papel claro. */
  .band{background:#141a26!important}
  :root[data-view="print"] .band{background:#eef2f7!important}
  /* Frost translúcido SOLO en paneles/chips (la banda es sólida). */
  :root[data-view="print"] .panel,:root[data-view="print"] .rc,:root[data-view="print"] .chip,:root[data-view="print"] .sig{
    background:linear-gradient(180deg,rgba(255,255,255,.95),rgba(240,244,250,.72));
    box-shadow:inset 0 1px 0 rgba(255,255,255,.75),0 1px 2px rgba(20,30,55,.06);
  }
  /* Banda: texto OSCURO sobre el degradado claro (evita que el gris→blanco se coma el texto). */
  :root[data-view="print"] .band .lead .val,:root[data-view="print"] .band .cell .v{color:#14181f}
  :root[data-view="print"] .band .lead .lbl,:root[data-view="print"] .band .cell .lbl,:root[data-view="print"] .band .lead .sub{color:#4a5261}
  :root[data-view="print"] .band .cell .v.warn{color:#b91c1c}
  :root[data-view="print"] .band .cell .v.ok{color:#15803d}
  @media print{
    .band{background:#eef2f7!important}
    .band .lead .val,.band .cell .v{color:#14181f!important}
    .band .lead .lbl,.band .cell .lbl,.band .lead .sub{color:#4a5261!important}
    .band .cell .v.warn{color:#b91c1c!important}
    .band .cell .v.ok{color:#15803d!important}
  }
  @media print{
    .panel,.rc,.chip,.sig{
      background:linear-gradient(180deg,rgba(255,255,255,.95),rgba(240,244,250,.72))!important;
      box-shadow:inset 0 1px 0 rgba(255,255,255,.75),0 1px 2px rgba(20,30,55,.06)!important;
      -webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;
    }
    /* Logo-plate: frost translúcido, SIN borde exterior. */
    .hero-logo-plate{background-color:rgba(255,255,255,.17)!important;border:0!important;
      box-shadow:0 4px 14px rgba(0,0,0,.28)!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important}
    /* Footer de papel (se repite por hoja): SELLO SUTIL en gris tenue, no negro. */
    .docfoot{display:none!important}
    .print-foot{background:#fff!important;border-top:1px solid #eceef1!important;padding:8px 12mm!important}
    .print-foot .ft-name,.print-foot .cc{color:#9aa3af!important}
    .print-foot .ft-lbl,.print-foot .ft-meta,.print-foot .ft-uuid{color:#aab1bb!important}
  }
</style>
