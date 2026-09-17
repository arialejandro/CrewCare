{{--
    LUZ AMBIENTAL — blobs de luz difusa para el fondo "Cinematic Dark Glass".
    Se incluye UNA sola vez en el layout maestro, DETRÁS del contenido:

        @include('componentes._ambient')

    Diseño:
      • position:fixed; inset:0; z-index:-1; pointer-events:none  → nunca
        intercepta clics ni entra en el flujo; pinta debajo del contenido y
        encima del color de fondo del body (los tokens --bg).
      • 3 blobs: naranja de MARCA (var(--brand-glow), configurable) + 2 fríos
        índigo, blur ~70px, opacidad baja, deriva MUY lenta.
      • overflow:hidden en el contenedor → los blobs no generan scroll.
      • @media (prefers-reduced-motion: reduce) → sin animación (quietos).
      • @media print → oculto (no gasta tinta en los reportes imprimibles).
    Auto-contenido: markup + <style> con clases prefijadas .cc-amb* para no
    chocar con nada de la app ni del mockup.
--}}
<div class="cc-amb" aria-hidden="true">
    <span class="cc-amb__blob cc-amb__blob--brand"></span>
    <span class="cc-amb__blob cc-amb__blob--indigo"></span>
    <span class="cc-amb__blob cc-amb__blob--indigo2"></span>
</div>

<style>
    .cc-amb {
        position: fixed;
        inset: 0;
        z-index: -1;
        overflow: hidden;
        pointer-events: none;
        /* Los blobs están COMPOSITADOS (will-change:transform + animación con scale) y sangran fuera
           del viewport a propósito. `overflow:hidden` por sí solo NO recorta de forma confiable a un
           hijo con su propia capa de composición → a veces se escapaba y empujaba un scroll horizontal
           (se veía como si el contenido "se desbordara"). `contain: layout paint` hace de esta capa una
           frontera dura de pintura y layout: los blobs no pueden pintar ni afectar nada fuera de aquí. */
        contain: layout paint;
    }
    .cc-amb__blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(70px);
        will-change: transform;
    }
    /* Naranja de MARCA (deriva de --brand-glow → --brand-primary). */
    .cc-amb__blob--brand {
        width: 520px; height: 520px;
        left: -120px; top: -80px;
        opacity: .5;
        background: radial-gradient(circle, var(--brand-glow), transparent 68%);
        animation: cc-amb-drift1 26s var(--ease, ease-in-out) infinite alternate;
    }
    /* Índigo frío grande (contrapunto de temperatura). */
    .cc-amb__blob--indigo {
        width: 600px; height: 600px;
        right: -160px; top: 18%;
        opacity: .45;
        background: radial-gradient(circle, rgba(94, 106, 210, .18), transparent 70%);
        animation: cc-amb-drift2 32s var(--ease, ease-in-out) infinite alternate;
    }
    /* Índigo frío tenue inferior. */
    .cc-amb__blob--indigo2 {
        width: 440px; height: 440px;
        left: 34%; bottom: -170px;
        opacity: .35;
        background: radial-gradient(circle, rgba(94, 106, 210, .12), transparent 70%);
        animation: cc-amb-drift1 30s var(--ease, ease-in-out) infinite alternate;
    }

    @keyframes cc-amb-drift1 { to { transform: translate(60px, 50px) scale(1.12); } }
    @keyframes cc-amb-drift2 { to { transform: translate(-70px, 40px) scale(1.08); } }

    /* Respeta la preferencia de movimiento reducido: blobs quietos. */
    @media (prefers-reduced-motion: reduce) {
        .cc-amb__blob { animation: none !important; }
    }

    /* No imprimir el ambiente (reportes window.print quedan limpios). */
    @media print {
        .cc-amb { display: none !important; }
    }
</style>
