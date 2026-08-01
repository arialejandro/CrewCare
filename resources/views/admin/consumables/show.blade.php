@extends('layouts.app')
@section('title', 'Ficha SDS · ' . $consumable->name)

@feature('sds_sfx')

@push('styles')
<style>
    /* ==================================================================
       FICHA SDS (show) — lenguaje "Cinematic Dark Glass" (tokens de
       layouts/_brand-theme). Hermana de admin/consumables/index.

       CASO DE USO PRIMARIO (define TODA la jerarquía): un Safety Officer
       DE PIE EN SET, con UNA MANO, en un TELÉFONO, con alguien quemado o
       intoxicado en el suelo. Por eso la sección 4 (primeros auxilios) y
       la 8 (controles/EPP) van ARRIBA, ABIERTAS y NUNCA en acordeón: si
       en una emergencia hay que hacer scroll y abrir un desplegable para
       saber qué hacer, la vista falló.
       ================================================================== */

    .cc-sds { max-width: 940px; margin: 0 auto; }

    /* ---- Chips. NO estan centralizadas: cada vista copia el bloque en su
            @@push('styles'). Copiado tal cual de admin/consumables/index. ---- */
    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.28rem .58rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}
    .cc-chip-danger{color:var(--danger);background:color-mix(in srgb,var(--danger) 16%,transparent);border-color:color-mix(in srgb,var(--danger) 34%,transparent)}
    .cc-chip-neutral{color:var(--text-muted);background:var(--glass-2);border-color:var(--stroke)}
    /* `material_family` llega a 42 caracteres ("Pirotecnia - material energetico
       controlado"): con el nowrap heredado empujaria el ancho en un telefono de 375px.
       Solo en la cabecera se le deja envolver. */
    .cc-hero__meta .cc-chip { white-space:normal; text-align:left; }

    /* ---- Volver (navegación superior) ---- */
    .cc-back { display:inline-flex; align-items:center; gap:.4rem; min-height:44px; padding:.35rem .15rem;
        color:var(--text-muted); text-decoration:none; font-size:.9rem; font-weight:600; }
    .cc-back:hover { color:var(--text); }
    .cc-back .cc-ico { width:16px; height:16px; }

    /* ---- Aviso de ficha pendiente de verificación (ámbar, arriba, visible) ---- */
    .cc-pending { display:flex; align-items:flex-start; gap:.7rem; padding:.85rem 1rem; margin-bottom:1rem;
        border-radius:var(--radius-sm); color:var(--warn);
        background:color-mix(in srgb, var(--warn) 14%, transparent);
        border:1px solid color-mix(in srgb, var(--warn) 34%, transparent); }
    .cc-pending svg { width:20px; height:20px; flex:none; margin-top:.1rem; }
    .cc-pending strong { display:block; font-size:.95rem; }
    .cc-pending span { display:block; margin-top:.2rem; color:var(--text); font-size:.85rem; font-weight:500; }

    /* ---- Encabezado ---- */
    .cc-hero { padding:1.15rem; margin-bottom:1.15rem; }
    .cc-hero__eyebrow { font-size:.66rem; letter-spacing:.2em; text-transform:uppercase;
        color:var(--brand-primary); font-weight:700; }
    .cc-hero__code { color:var(--text-muted); font-variant-numeric:tabular-nums; }
    .cc-hero__title { margin:.25rem 0 .5rem; font-family:'Poppins',sans-serif; font-weight:800;
        letter-spacing:-.02em; font-size:1.55rem; line-height:1.1; color:var(--text); overflow-wrap:break-word; }
    .cc-hero__meta { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.65rem; }
    .cc-syn { margin:0 0 .8rem; color:var(--text-muted); font-size:.9rem; line-height:1.5; max-width:68ch; }
    .cc-syn b { color:var(--text); font-weight:600; }

    /* ---- Pictogramas GHS + palabra de advertencia ---- */
    .cc-hazard-row { display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; }
    .cc-ghs-row { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
    .cc-ghs-row .cc-ghs { width:58px; height:58px; flex:none; }
    /* La palabra de advertencia GHS se rotula en versalitas en la etiqueta normativa;
       ademas el dato viene con caja MIXTA en BD ('PELIGRO' y 'Peligro' conviven), asi
       que el uppercase la homologa sin tocar el dato. */
    .cc-signal { display:inline-flex; align-items:center; min-height:34px; padding:.3rem .85rem;
        border-radius:999px; font-weight:800; font-size:.82rem; letter-spacing:.1em;
        text-transform:uppercase; border:1px solid transparent; }
    .cc-signal--danger { color:var(--danger); background:color-mix(in srgb, var(--danger) 16%, transparent);
        border-color:color-mix(in srgb, var(--danger) 40%, transparent); }
    .cc-signal--warn { color:var(--warn); background:color-mix(in srgb, var(--warn) 16%, transparent);
        border-color:color-mix(in srgb, var(--warn) 38%, transparent); }

    /* ---- Identificadores (UN / CAS) ---- */
    .cc-ids { display:flex; flex-wrap:wrap; gap:.5rem; margin:.85rem 0 0; }
    .cc-id { padding:.4rem .7rem; border-radius:var(--radius-sm); background:var(--glass-2);
        border:1px solid var(--stroke); }
    .cc-id dt { font-size:.62rem; letter-spacing:.14em; text-transform:uppercase;
        color:var(--text-muted); font-weight:700; }
    .cc-id dd { margin:.1rem 0 0; color:var(--text); font-weight:700; font-size:.92rem;
        font-variant-numeric:tabular-nums; }

    /* ==================================================================
       ZONA DE EMERGENCIA — secciones 4 y 8. Siempre abiertas, arriba.
       En movil (1 columna) el orden natural del DOM ya pone primero los
       primeros auxilios, que es lo que se busca con alguien en el suelo.
       ================================================================== */
    .cc-er-grid { display:grid; gap:.9rem; margin-bottom:1.15rem; }
    @media (min-width: 768px) { .cc-er-grid { grid-template-columns:1fr 1fr; align-items:start; } }

    .cc-er-card { border-radius:var(--radius); padding:1.05rem; border:1px solid var(--stroke-2);
        background:var(--glass-2); box-shadow:var(--shadow); }
    @media screen {
        .cc-er-card {
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat));
            backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat));
        }
    }
    @supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
        .cc-er-card { background: var(--surface-2); }
    }
    .cc-er-card--aid { border-color:color-mix(in srgb, var(--danger) 42%, transparent);
        background:linear-gradient(180deg, color-mix(in srgb, var(--danger) 10%, transparent), transparent 60%), var(--glass-2); }
    .cc-er-card--ppe { border-color:color-mix(in srgb, var(--brand-primary) 42%, transparent);
        background:linear-gradient(180deg, var(--brand-glow), transparent 60%), var(--glass-2); }

    .cc-er-card__hd { display:flex; align-items:center; gap:.7rem; margin-bottom:.75rem; }
    .cc-er-ico { width:42px; height:42px; border-radius:12px; flex:none;
        display:inline-flex; align-items:center; justify-content:center; }
    /* El SVG de componentes/_icon sale SIN width/height: su tamaño lo pone el CSS. Estos
       cuatro iconos pedían la clase `cc-ico-24`, que NO vive en _brand-theme (el layout
       solo carga ese) sino en componentes/_form-kit — un parcial que esta vista nunca
       incluye, porque no es un formulario. Sin ninguna regla que casara, el SVG caía al
       tamaño por defecto de elemento reemplazado (300×150 acotado por el viewBox 1:1 =
       150×150) dentro de una caja de 42px con min-width:auto, que no puede encogerlo:
       dos iconos gigantes reventando la cabecera de las dos tarjetas que esta ficha
       existe para que se lean con una mano y alguien en el suelo. Se sigue la convención
       de la propia vista (todos sus demás iconos son `.cc-ico` dimensionados por su
       contenedor) en vez de arrastrar los 13 KB del kit de formularios. */
    .cc-er-ico .cc-ico { width:24px; height:24px; }
    .cc-er-card--aid .cc-er-ico { color:var(--danger);
        background:color-mix(in srgb, var(--danger) 15%, transparent);
        border:1px solid color-mix(in srgb, var(--danger) 32%, transparent); }
    .cc-er-card--ppe .cc-er-ico { color:var(--brand-primary);
        background:color-mix(in srgb, var(--brand-primary) 15%, transparent);
        border:1px solid color-mix(in srgb, var(--brand-primary) 34%, transparent); }
    .cc-er-eyebrow { font-size:.63rem; letter-spacing:.18em; text-transform:uppercase;
        font-weight:800; color:var(--text-muted); }
    .cc-er-t { margin:.05rem 0 0; font-family:'Poppins',sans-serif; font-weight:800; font-size:1.08rem;
        letter-spacing:-.01em; color:var(--text); line-height:1.15; }

    /* ---- Prosa larga (ver INS-FIRE-01): medida legible y sin desbordes ---- */
    .cc-prose { color:var(--text); font-size:1rem; line-height:1.6; max-width:68ch; overflow-wrap:break-word; }

    /* ---- Resumen operativo (hazards / precautions) ---- */
    .cc-sum { padding:1.05rem; margin-bottom:1.15rem; }
    .cc-sum__hd { display:flex; align-items:center; gap:.55rem; margin-bottom:.2rem; }
    .cc-sum__hd .cc-ico { width:18px; height:18px; color:var(--text-muted); }
    .cc-sum__t { margin:0; font-family:'Poppins',sans-serif; font-weight:700; font-size:1rem; color:var(--text); }
    .cc-sum__note { margin:0 0 .9rem; color:var(--text-muted); font-size:.82rem; }
    .cc-sum__k { display:block; font-size:.63rem; letter-spacing:.16em; text-transform:uppercase;
        font-weight:800; color:var(--text-muted); margin-bottom:.25rem; }
    .cc-sum__block + .cc-sum__block { margin-top:.9rem; padding-top:.9rem; border-top:1px solid var(--stroke); }

    /* ==================================================================
       LAS 14 SECCIONES RESTANTES — <details>/<summary> nativo: cero JS,
       accesible y operable con teclado.
       ================================================================== */
    .cc-secs { margin-bottom:1.15rem; }
    .cc-secs__hd { display:flex; align-items:center; gap:.55rem; margin:0 0 .65rem; padding:0 .15rem; }
    .cc-secs__hd .cc-ico { width:18px; height:18px; color:var(--text-muted); }
    .cc-secs__t { margin:0; font-family:'Poppins',sans-serif; font-weight:700; font-size:1rem; color:var(--text); }
    .cc-secs__c { margin-left:auto; color:var(--text-muted); font-size:.78rem; }

    .cc-sec { border:1px solid var(--stroke); border-radius:var(--radius-sm); background:var(--glass);
        margin-bottom:.5rem; }
    .cc-sec__sum { display:flex; align-items:center; gap:.7rem; min-height:52px; padding:.7rem .9rem;
        cursor:pointer; color:var(--text); font-weight:600; font-size:.97rem; line-height:1.3;
        list-style:none; border-radius:var(--radius-sm); }
    /* El triangulo nativo se retira en los 3 motores: display:flex (Chrome),
       list-style:none (Firefox) y ::-webkit-details-marker (Safari). */
    .cc-sec__sum::-webkit-details-marker { display:none; }
    .cc-sec__sum::marker { content:''; }
    .cc-sec__sum:hover { background:var(--glass-2); }
    .cc-sec__n { flex:none; display:inline-flex; align-items:center; justify-content:center;
        min-width:26px; height:26px; padding:0 .3rem; border-radius:8px; background:var(--glass-2);
        border:1px solid var(--stroke); color:var(--text-muted); font-size:.74rem; font-weight:800;
        font-variant-numeric:tabular-nums; }
    .cc-sec__chev { margin-left:auto; width:18px; height:18px; flex:none; color:var(--text-muted);
        transition:transform .18s var(--ease, cubic-bezier(.16,1,.3,1)); }
    .cc-sec[open] .cc-sec__chev { transform:rotate(180deg); }
    .cc-sec[open] .cc-sec__sum { border-bottom-left-radius:0; border-bottom-right-radius:0; }
    .cc-sec__body { padding:.85rem .9rem 1rem; border-top:1px solid var(--stroke); }
    @media (prefers-reduced-motion: reduce) { .cc-sec__chev { transition:none; } }

    /* ---- Efectos que usan este insumo (N:M inversa) ---- */
    .cc-eff { list-style:none; margin:0; padding:0; display:grid; gap:.45rem; }
    /* flex-wrap: con un nombre largo + el chip de familia (nowrap) un telefono de 375px
       desbordaria a lo ancho; asi el chip baja a una segunda linea. */
    .cc-eff__a { display:flex; flex-wrap:wrap; align-items:center; gap:.65rem; min-height:52px; padding:.6rem .8rem;
        border:1px solid var(--stroke); border-radius:var(--radius-sm); background:var(--glass);
        color:var(--text); text-decoration:none; transition:background .18s, border-color .18s; }
    .cc-eff__a:hover { background:var(--glass-2); border-color:var(--stroke-2); color:var(--text); }
    .cc-eff__a > .cc-ico:first-child { width:17px; height:17px; flex:none; color:var(--danger); }
    .cc-eff__n { font-weight:600; min-width:0; overflow-wrap:break-word; }
    .cc-eff__f { margin-left:auto; flex:none; }
    .cc-eff__go { width:16px; height:16px; flex:none; color:var(--text-muted); }

    /* ---- Pie: fuente, descargo y verificación ---- */
    .cc-src { padding:1.05rem; }
    .cc-src__link { display:inline-flex; align-items:center; gap:.5rem; min-height:44px;
        color:var(--brand-primary); text-decoration:none; font-weight:700; font-size:.92rem;
        overflow-wrap:anywhere; }
    .cc-src__link:hover { text-decoration:underline; color:var(--brand-primary); }
    .cc-src__link .cc-ico { width:16px; height:16px; flex:none; }
    .cc-src__status { display:flex; align-items:flex-start; gap:.5rem; margin:.35rem 0 0;
        color:var(--text-muted); font-size:.88rem; line-height:1.5; max-width:68ch; }
    .cc-src__status .cc-ico { width:15px; height:15px; flex:none; margin-top:.2rem; }
    .cc-src__rows { margin:.95rem 0 0; padding:.95rem 0 0; border-top:1px solid var(--stroke);
        display:grid; gap:.6rem; }
    .cc-src__row { display:flex; flex-wrap:wrap; gap:.15rem 1rem; }
    .cc-src__row dt { flex:none; min-width:9.5rem; color:var(--text-muted); font-size:.8rem; font-weight:700; }
    .cc-src__row dd { margin:0; color:var(--text); font-size:.88rem; line-height:1.5;
        max-width:64ch; overflow-wrap:break-word; }
    .cc-src__disc { margin:.95rem 0 0; padding:.7rem .8rem; border-radius:var(--radius-sm);
        background:var(--glass-2); border:1px solid var(--stroke);
        color:var(--text-muted); font-size:.82rem; line-height:1.5; max-width:70ch; }

    /* ---- Acciones ---- */
    .cc-acts { display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1.25rem; }
    .cc-btn { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; min-height:44px;
        padding:.62rem 1.05rem; border-radius:var(--radius-sm); text-decoration:none; font-weight:700;
        font-size:.9rem; border:1px solid var(--stroke); background:var(--glass); color:var(--text);
        cursor:pointer; transition:background .18s, border-color .18s, transform .18s var(--ease, cubic-bezier(.16,1,.3,1)); }
    .cc-btn:hover { background:var(--glass-2); border-color:var(--stroke-2); color:var(--text); }
    .cc-btn .cc-ico { width:16px; height:16px; }
    .cc-btn--primary { background:var(--brand-primary); border-color:var(--brand-primary);
        color:var(--brand-on-primary); box-shadow:0 10px 26px -12px var(--brand-glow); }
    .cc-btn--primary:hover { background:var(--brand-primary); color:var(--brand-on-primary);
        filter:brightness(1.04); transform:translateY(-1px); }
    @media (max-width: 479px) { .cc-acts .cc-btn, .cc-acts form { width:100%; } }
    @media (prefers-reduced-motion: reduce) { .cc-btn { transition:none; } .cc-btn--primary:hover { transform:none; } }

    /* ---- Vacio elegante (degradacion sin datos) ---- */
    .cc-none { text-align:center; padding:2rem 1.25rem; color:var(--text-muted); }
    .cc-none .cc-ico { width:36px; height:36px; opacity:.55; margin-bottom:.5rem; }

    /* ==================================================================
       IMPRESION — en set se imprime la ficha y se pega en el camion.
       Sobrio: sin vidrio, sin botones, acordeones abiertos (los abre el
       script de abajo en beforeprint) y sin cortes a media seccion.
       ================================================================== */
    @media print {
        .cc-back, .cc-acts, .cc-sec__chev { display:none !important; }
        .cc-sds { max-width:none; }
        .cc-hero, .cc-er-card, .cc-sec, .cc-sum, .cc-src, .cc-eff__a {
            background:#fff !important; box-shadow:none !important; border-color:#c9ced6 !important;
            -webkit-backdrop-filter:none !important; backdrop-filter:none !important;
        }
        .cc-er-card, .cc-sec, .cc-sum, .cc-src { break-inside:avoid; page-break-inside:avoid; }
        .cc-er-grid { grid-template-columns:1fr 1fr; }
        .cc-prose, .cc-hero__title, .cc-er-t, .cc-sec__sum, .cc-src__row dd { color:#000 !important; }
        /* El enlace de la HDS impreso sin su URL es papel muerto. */
        .cc-src__link::after { content:" — " attr(href); font-weight:400; font-size:.75rem; word-break:break-all; }
    }
</style>
@endpush

@section('content')
{{-- Confirmación del submit de «Verificar ficha» sin JS inline. --}}
@include('componentes._confirm-submit')
@php
    /** Etiquetas de las 16 secciones en su ORDEN OFICIAL (1→16). Se itera ESTO, nunca
     *  `$consumable->sds_sections` a pelo: MySQL 5.7 reordena las claves de un JSON
     *  nativo (por longitud y luego binario) y un foreach a ciegas empezaria a pintar
     *  la hoja de seguridad POR LA SECCION 16. Verificado en esta BD. */
    $__labels = \App\Models\Consumable::sdsSectionLabels();

    /* Las dos que se consultan CON UNA PERSONA EN EL SUELO: van fuera del acordeon. */
    $__featured = ['4_primeros_auxilios', '8_controles_epp_vle'];
    $__aid = $consumable->sdsSection('4_primeros_auxilios');
    $__ppe = $consumable->sdsSection('8_controles_epp_vle');

    /* ¿Esta ficha trae HDS extendida CON contenido? No basta con supportsExtendedSds():
       las 12 fichas legacy viven en una BD que SI tiene el delta 4b y aun asi tienen
       `sds_sections` NULL. El que manda para elegir cuerpo es el contenido real. */
    $__hasSections = false;
    foreach ($__labels as $__k => $__l) {
        if (filled($consumable->sdsSection($__k))) { $__hasSections = true; break; }
    }
    $__secCount = 0;
    foreach ($__labels as $__k => $__l) {
        if (!in_array($__k, $__featured, true) && filled($consumable->sdsSection($__k))) { $__secCount++; }
    }

    /* Sinonimos: JSON casteado a array → se imprime legible, nunca con json_encode. */
    $__syn = [];
    if (is_array($consumable->synonyms)) {
        foreach ($consumable->synonyms as $__s) {
            if (is_scalar($__s) && trim((string) $__s) !== '') { $__syn[] = trim((string) $__s); }
        }
    }

    /* Pictogramas GHS: el parcial ignora en silencio lo que no reconozca. */
    $__ghs = [];
    if (is_array($consumable->ghs_pictograms)) {
        foreach ($consumable->ghs_pictograms as $__g) {
            if (is_scalar($__g) && trim((string) $__g) !== '') { $__ghs[] = trim((string) $__g); }
        }
    }

    /* Palabra de advertencia: la BD guarda caja MIXTA ('PELIGRO' 23 filas / 'Peligro' 6),
       asi que el color se decide en minusculas — igual que en el index. Un
       `=== 'Peligro'` pintaria de ambar 23 fichas que gritan PELIGRO. */
    $__signal   = trim((string) $consumable->signal_word);
    $__isDanger = mb_strtolower($__signal) === 'peligro';

    /* Badge ambar de procedencia. El spec pedia siempre "Capturado en set", pero los 9
       pendientes de hoy TIENEN `code`: los 9 salieron del import y nadie los capturo en
       set — ese texto mentiria 9 de 9 veces. isFieldCaptured() dice la verdad. */
    $__pendingLabel = $consumable->isFieldCaptured()
        ? 'Capturado en set · pendiente de verificación'
        : 'Importado del catálogo · pendiente de verificación';

    /* Rastro de verificacion. Dos NULL con significados distintos (no se colapsan en un ??):
         - verified_by_id NULL           → sello "de origen": lo avala el catalogo base.
         - verified_by_id CON valor pero relacion NULL → hubo persona y su usuario se borro.
       PHP 7.4: sin nullsafe ni match. Mismo criterio que admin/consumables/edit. */
    $__verifiedNote = null;
    if ($consumable->isVerified()) {
        $__verifier     = $consumable->verifiedBy;
        $__verifierName = $__verifier !== null ? ($__verifier->name ?? '') : '';
        $__verifiedNote = $consumable->verified_by_id === null
            ? 'Verificada de origen (catálogo base)'
            : 'Verificada por ' . ($__verifierName !== '' ? $__verifierName : 'usuario dado de baja');
    }

    /* ── `sds_url` NO es una URL de confianza ────────────────────────────────────
       Su regla de validacion es `nullable|string|max:2048` (ConsumableController::
       validatedData), o sea que NADIE comprueba el esquema al guardar: quien tiene
       sds.create puede dejar ahi `javascript:fetch('/consumables/9/verify',{...})`.
       {{ }} escapa las comillas y por eso el atributo no se puede romper — pero un URI
       `javascript:` no necesita comillas, y `target=_blank`/`rel=noopener` no filtran
       esquemas (se ignoran para javascript:, que corre en ESTE documento). O sea que el
       clic que la ficha invita a dar («Ver la HDS de la fuente») ejecutaria el JS del
       atacante en la sesion de quien lee, incluido un sds.manage.
       LISTA BLANCA, no lista negra: solo http/https se convierten en enlace. Medido en
       esta BD: las 32 fichas con `sds_url` son 31 https + 1 http → no se pierde ninguna.
       Un valor con otro esquema simplemente no se pinta como enlace; `sds_status` de
       abajo sigue explicando el estado de la fuente.
       PENDIENTE OWNER (backend, fuera del 4d): endurecer la regla a `url` y acotar el
       esquema en el Form Request para que no entre al dato de origen. */
    $__sdsHref = null;
    if (filled($consumable->sds_url)) {
        $__sdsRaw    = trim((string) $consumable->sds_url);
        $__sdsScheme = mb_strtolower((string) parse_url($__sdsRaw, PHP_URL_SCHEME));
        if ($__sdsScheme === 'http' || $__sdsScheme === 'https') { $__sdsHref = $__sdsRaw; }
    }

    /* Efectos que usan este insumo. Declarar el belongsToMany es perezoso, pero
       EJECUTARLO contra las tablas de la Capa A si truena si no existen → isAvailable()
       manda. Si el controlador ya trajo la relacion no se vuelve a consultar. */
    $__effects = [];
    if (\App\Models\SfxEffectType::isAvailable()) {
        $__effects = $consumable->relationLoaded('sfxEffectTypes')
            ? $consumable->sfxEffectTypes
            : $consumable->sfxEffectTypes()->orderBy('name')->get();
    }
@endphp

<div class="container-fluid py-4 px-3 px-md-4">
<div class="cc-sds">

    <a href="{{ route('consumables.index') }}" class="cc-back">
        @include('componentes._icon', ['name' => 'chevron-left', 'label' => null])
        Volver al listado
    </a>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3 mt-2" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3 mt-2" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    {{-- ===================== 1 · ENCABEZADO ===================== --}}

    {{-- Lo pendiente se ve marcado ARRIBA y en ambar, antes que el nombre: quien abre
         esta ficha en set tiene que saber que aun no la avala nadie ANTES de leerla. --}}
    @if($consumable->isPendingVerification())
        <div class="cc-pending mt-2">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico', 'label' => 'Atención'])
            <div>
                <strong>{{ $__pendingLabel }}</strong>
                <span>Un responsable de SDS todavía no valida estos datos. Contrasta con la HDS del proveedor antes de operar.</span>
            </div>
        </div>
    @endif

    <header class="cc-glass-card cc-hero">
        <div class="cc-hero__eyebrow">
            Hoja de datos de seguridad
            @if(filled($consumable->code))
                <span class="cc-hero__code">· {{ $consumable->code }}</span>
            @endif
        </div>

        <h1 class="cc-hero__title">{{ $consumable->name }}</h1>

        <div class="cc-hero__meta">
            @if($consumable->type_label)
                <span class="cc-chip cc-chip-neutral">{{ $consumable->type_label }}</span>
            @endif
            @if(filled($consumable->material_family))
                <span class="cc-chip cc-chip-neutral">{{ $consumable->material_family }}</span>
            @endif
            @if(!$consumable->is_active)
                <span class="cc-chip cc-chip-neutral">Inactivo</span>
            @endif
        </div>

        @if(count($__syn))
            <p class="cc-syn"><b>También:</b> {{ implode(' · ', $__syn) }}</p>
        @endif

        @if(count($__ghs) || $__signal !== '')
            <div class="cc-hazard-row">
                @if(count($__ghs))
                    <div class="cc-ghs-row">
                        @foreach($__ghs as $__g)
                            @include('componentes._ghs-pictogram', ['code' => $__g, 'class' => 'cc-ghs', 'label' => null])
                        @endforeach
                    </div>
                @endif
                @if($__signal !== '')
                    <span class="cc-signal {{ $__isDanger ? 'cc-signal--danger' : 'cc-signal--warn' }}">{{ $__signal }}</span>
                @endif
            </div>
        @endif

        @if(filled($consumable->un_number) || filled($consumable->cas_number))
            <dl class="cc-ids">
                @if(filled($consumable->un_number))
                    <div class="cc-id"><dt>Número UN</dt><dd>{{ $consumable->un_number }}</dd></div>
                @endif
                @if(filled($consumable->cas_number))
                    <div class="cc-id"><dt>Número CAS</dt><dd>{{ $consumable->cas_number }}</dd></div>
                @endif
            </dl>
        @endif
    </header>

    {{-- ===================== 2 · CUERPO ===================== --}}

    @if($__hasSections)

        {{-- ---- Zona de emergencia: secciones 4 y 8, abiertas y arriba del pliegue ---- --}}
        @if(filled($__aid) || filled($__ppe))
            <div class="cc-er-grid">
                @if(filled($__aid))
                    <section class="cc-er-card cc-er-card--aid" aria-labelledby="cc-t-aid">
                        <div class="cc-er-card__hd">
                            <span class="cc-er-ico">
                                @include('componentes._icon', ['name' => 'heart-pulse', 'label' => null])
                            </span>
                            <div>
                                <div class="cc-er-eyebrow">Sección 4</div>
                                <h2 class="cc-er-t" id="cc-t-aid">{{ $__labels['4_primeros_auxilios'] }}</h2>
                            </div>
                        </div>
                        <div class="cc-prose">{!! nl2br(e($__aid)) !!}</div>
                    </section>
                @endif

                @if(filled($__ppe))
                    <section class="cc-er-card cc-er-card--ppe" aria-labelledby="cc-t-ppe">
                        <div class="cc-er-card__hd">
                            <span class="cc-er-ico">
                                @include('componentes._icon', ['name' => 'shield', 'label' => null])
                            </span>
                            <div>
                                <div class="cc-er-eyebrow">Sección 8</div>
                                <h2 class="cc-er-t" id="cc-t-ppe">{{ $__labels['8_controles_epp_vle'] }}</h2>
                            </div>
                        </div>
                        <div class="cc-prose">{!! nl2br(e($__ppe)) !!}</div>
                    </section>
                @endif
            </div>
        @endif

        {{-- ---- Resumen operativo. `hazards`/`precautions` estan poblados en las 53
             fichas (tambien en las importadas) y son la sintesis que CrewCare curo;
             sin esto solo se verian en el listado y desaparecerian en el detalle. ---- --}}
        @if(filled($consumable->hazards) || filled($consumable->precautions))
            <section class="cc-glass-card cc-sum" aria-labelledby="cc-t-sum">
                <div class="cc-sum__hd">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'label' => null])
                    <h2 class="cc-sum__t" id="cc-t-sum">Resumen operativo</h2>
                </div>
                <p class="cc-sum__note">Síntesis de CrewCare para set. La HDS formal completa está más abajo.</p>
                @if(filled($consumable->hazards))
                    <div class="cc-sum__block">
                        <span class="cc-sum__k">Peligros</span>
                        <div class="cc-prose">{!! nl2br(e($consumable->hazards)) !!}</div>
                    </div>
                @endif
                @if(filled($consumable->precautions))
                    <div class="cc-sum__block">
                        <span class="cc-sum__k">Precauciones</span>
                        <div class="cc-prose">{!! nl2br(e($consumable->precautions)) !!}</div>
                    </div>
                @endif
            </section>
        @endif

        {{-- ---- Las 14 restantes, en acordeon nativo ---- --}}
        @if($__secCount > 0)
            <div class="cc-secs">
                <div class="cc-secs__hd">
                    @include('componentes._icon', ['name' => 'file-text', 'label' => null])
                    <h2 class="cc-secs__t">Hoja de datos completa</h2>
                    <span class="cc-secs__c">{{ $__secCount }} {{ $__secCount === 1 ? 'sección' : 'secciones' }}</span>
                </div>

                @foreach($__labels as $__key => $__label)
                    @continue(in_array($__key, $__featured, true))
                    @php $__val = $consumable->sdsSection($__key); @endphp
                    @continue(!filled($__val))
                    <details class="cc-sec">
                        <summary class="cc-sec__sum">
                            <span class="cc-sec__n">{{ strtok($__key, '_') }}</span>
                            <span>{{ $__label }}</span>
                            @include('componentes._icon', ['name' => 'chevron-down', 'class' => 'cc-sec__chev', 'label' => null])
                        </summary>
                        <div class="cc-sec__body">
                            <div class="cc-prose">{!! nl2br(e($__val)) !!}</div>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif

    @else

        {{-- ---- DEGRADACION: ficha sin HDS de 16 secciones (las 12 legacy, o una BD sin
             el delta 4b). No hay acordeon vacio ni primeros auxilios inventados: lo que
             SI hay son `hazards` y `precautions`, y aqui son EL cuerpo — asi que se les
             da la misma prominencia que a la zona de emergencia. ---- --}}
        @if(filled($consumable->hazards) || filled($consumable->precautions))
            <div class="cc-er-grid">
                @if(filled($consumable->hazards))
                    <section class="cc-er-card cc-er-card--aid" aria-labelledby="cc-t-haz">
                        <div class="cc-er-card__hd">
                            <span class="cc-er-ico">
                                @include('componentes._icon', ['name' => 'alert-triangle', 'label' => null])
                            </span>
                            <div>
                                <div class="cc-er-eyebrow">Ficha resumida</div>
                                <h2 class="cc-er-t" id="cc-t-haz">Peligros</h2>
                            </div>
                        </div>
                        <div class="cc-prose">{!! nl2br(e($consumable->hazards)) !!}</div>
                    </section>
                @endif

                @if(filled($consumable->precautions))
                    <section class="cc-er-card cc-er-card--ppe" aria-labelledby="cc-t-pre">
                        <div class="cc-er-card__hd">
                            <span class="cc-er-ico">
                                @include('componentes._icon', ['name' => 'shield', 'label' => null])
                            </span>
                            <div>
                                <div class="cc-er-eyebrow">Ficha resumida</div>
                                <h2 class="cc-er-t" id="cc-t-pre">Precauciones y EPP</h2>
                            </div>
                        </div>
                        <div class="cc-prose">{!! nl2br(e($consumable->precautions)) !!}</div>
                    </section>
                @endif
            </div>
        @endif

        @if(filled($consumable->description))
            <section class="cc-glass-card cc-sum">
                <div class="cc-sum__hd">
                    @include('componentes._icon', ['name' => 'info', 'label' => null])
                    <h2 class="cc-sum__t">Descripción</h2>
                </div>
                <div class="cc-prose">{!! nl2br(e($consumable->description)) !!}</div>
            </section>
        @endif

        @if(!filled($consumable->hazards) && !filled($consumable->precautions) && !filled($consumable->description))
            <section class="cc-glass-card">
                <div class="cc-none">
                    @include('componentes._icon', ['name' => 'file-text', 'label' => null])
                    <div class="fw-semibold" style="color:var(--text);">Esta ficha aún no tiene contenido de seguridad.</div>
                    <small>Consulta la HDS del proveedor antes de operar con este material.</small>
                </div>
            </section>
        @endif

    @endif

    {{-- ===================== 3 · PIE: fuente, descargo y verificación ===================== --}}
    <section class="cc-glass-card cc-src" aria-labelledby="cc-t-src">
        <div class="cc-sum__hd">
            @include('componentes._icon', ['name' => 'info', 'label' => null])
            <h2 class="cc-sum__t" id="cc-t-src">Fuente y verificación</h2>
        </div>

        @if($__sdsHref !== null)
            <a href="{{ $__sdsHref }}" target="_blank" rel="noopener" class="cc-src__link">
                @include('componentes._icon', ['name' => 'external-link', 'label' => null])
                Ver la HDS de la fuente
            </a>
        @endif

        {{-- Sin `sds_url` NO se pone un enlace muerto ni se esconde el hueco: `sds_status`
             trae la prosa que explica POR QUE no lo hay ("Controlado — sin enlace publico
             apropiado; HDS con embarque SEDENA."). Eso es informacion, no un vacio. Con
             enlace tambien se enseña, porque ahi dice si el enlace fue cotejado. --}}
        {{-- El icono lo decide `sds_url_verified`, NO la mera presencia del enlace.
             Antes era `filled($consumable->sds_url) ? 'check-circle' : 'shield-alert'`:
             bastaba con que HUBIERA URL para pintar la palomita. Medido en esta BD: de
             las 32 fichas con `sds_url`, 5 tienen `sds_url_verified = 0` — entre ellas
             INS-FIRE-05 (gel de fuego, 3 quimiotipos), cuyo propio `sds_status` dice
             «Enlace de referencia (quimiotipo DEG); confirmar el del producto que llega a
             set.». O sea que el ✓ avalaba justo la frase que lo desmiente, en el insumo
             donde usar la HDS del quimiotipo equivocado cambia primeros auxilios y medios
             de extincion. El dato correcto estaba en el modelo (docblock: «sds_url_verified
             = el enlace resuelve a la HDS de ESA sustancia») y la vista no lo leia.
             Sin enlace cotejado → shield-alert, que es lo que de verdad hay. --}}
        @if(filled($consumable->sds_status))
            <p class="cc-src__status">
                @include('componentes._icon', ['name' => $consumable->sds_url_verified ? 'check-circle' : 'shield-alert', 'label' => null])
                <span>{{ $consumable->sds_status }}</span>
            </p>
        @endif

        <dl class="cc-src__rows">
            @if(filled($consumable->sds_source_note))
                <div class="cc-src__row"><dt>Nota de la fuente</dt><dd>{{ $consumable->sds_source_note }}</dd></div>
            @endif
            @if($consumable->sds_level !== null)
                <div class="cc-src__row">
                    <dt>Profundidad de la ficha</dt>
                    <dd>Nivel {{ $consumable->sds_level }}</dd>
                </div>
            @endif
            {{-- OJO: `sds_source_date` NO es la fecha de verificacion — es cuando se GENERO
                 el catalogo (vale igual en las 41 importadas). Van en filas separadas y
                 rotuladas distinto A PROPOSITO: mezclarlas haria pasar por avalada una
                 ficha que nadie sello. --}}
            @if($consumable->sds_source_date !== null)
                <div class="cc-src__row">
                    <dt>Catálogo generado</dt>
                    <dd>{{ $consumable->sds_source_date->format('d/m/Y') }}</dd>
                </div>
            @endif
            <div class="cc-src__row">
                <dt>Verificación</dt>
                <dd>
                    @if($consumable->isVerified())
                        {{ $__verifiedNote }} · {{ $consumable->verified_at->format('d/m/Y H:i') }}
                    @elseif($consumable->isPendingVerification())
                        <span style="color:var(--warn);font-weight:700;">Pendiente</span> — ningún responsable de SDS la ha validado.
                    @else
                        <span class="cc-muted">Sin estado de verificación en esta instancia.</span>
                    @endif
                </dd>
            </div>
        </dl>

        @if(filled($consumable->sds_disclaimer))
            <p class="cc-src__disc">{{ $consumable->sds_disclaimer }}</p>
        @endif
    </section>

    {{-- ===================== 4 · EFECTOS QUE USAN ESTE INSUMO ===================== --}}
    @if(count($__effects))
        <section class="cc-secs" style="margin-top:1.15rem;" aria-labelledby="cc-t-eff">
            <div class="cc-secs__hd">
                @include('componentes._icon', ['name' => 'flame', 'label' => null])
                <h2 class="cc-secs__t" id="cc-t-eff">Efectos que usan este insumo</h2>
                <span class="cc-secs__c">{{ count($__effects) }}</span>
            </div>
            <ul class="cc-eff">
                @foreach($__effects as $__e)
                    <li>
                        <a href="{{ route('sfx-effects.show', $__e->id) }}" class="cc-eff__a">
                            @include('componentes._icon', ['name' => 'flame', 'label' => null])
                            <span class="cc-eff__n">{{ $__e->name }}</span>
                            @if(filled($__e->family))
                                <span class="cc-chip cc-chip-neutral cc-eff__f">{{ $__e->family }}</span>
                            @endif
                            @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-eff__go', 'label' => null])
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ===================== 5 · ACCIONES ===================== --}}
    <div class="cc-acts">
        @can('sds.manage')
            @if($consumable->isPendingVerification())
                {{-- data-confirm, NO onsubmit: ver componentes/_confirm-submit. El `name` es
                     texto libre de quien tiene sds.create y en contexto JS rompería la cadena. --}}
                <form method="POST" action="{{ route('consumables.verify', $consumable->id) }}"
                      data-confirm="¿Confirmas que los datos de «{{ $consumable->name }}» son correctos? Quedará registrada tu verificación.">
                    @csrf
                    <button type="submit" class="cc-btn cc-btn--primary">
                        @include('componentes._icon', ['name' => 'check', 'label' => null])
                        Verificar ficha
                    </button>
                </form>
            @endif
        @endcan
        @can('sds.create')
            <a href="{{ route('consumables.edit', $consumable->id) }}" class="cc-btn">
                @include('componentes._icon', ['name' => 'pencil', 'label' => null])
                Editar
            </a>
        @endcan
        <button type="button" class="cc-btn" id="cc-print">
            @include('componentes._icon', ['name' => 'printer', 'label' => null])
            Imprimir
        </button>
        <a href="{{ route('consumables.index') }}" class="cc-btn">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null])
            Volver al listado
        </a>
    </div>

</div>
</div>
@endsection

@push('scripts')
<script>
    /* El acordeon es <details> nativo (cero JS para operarlo). Lo unico que necesita
       script es la IMPRESION: un <details> cerrado NO imprime su contenido, y una ficha
       impresa a medias es peor que no imprimirla. Se abren todos antes de imprimir,
       tanto desde el boton como desde Ctrl+P. Progresivo: sin JS la vista funciona
       igual, solo imprimiria lo que este abierto. */
    (function () {
        function openAll() {
            var ds = document.querySelectorAll('.cc-sds details');
            Array.prototype.forEach.call(ds, function (d) { d.open = true; });
        }

        var btn = document.getElementById('cc-print');
        if (btn) {
            btn.addEventListener('click', function () { openAll(); window.print(); });
        }

        // Ctrl+P / menu del navegador. Safari no dispara 'beforeprint' → matchMedia.
        window.addEventListener('beforeprint', openAll);
        if (window.matchMedia) {
            var mq = window.matchMedia('print');
            var onChange = function (m) { if (m.matches) { openAll(); } };
            if (mq.addEventListener) { mq.addEventListener('change', onChange); }
            else if (mq.addListener) { mq.addListener(onChange); }
        }
    })();
</script>
@endpush
@endfeature
