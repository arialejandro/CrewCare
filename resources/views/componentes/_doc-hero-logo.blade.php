{{--
    Logo del HERO de los documentos (Scouting, Daily, Cond. Inseguras, Actos Inseguros, Accidentes).
    Placa de "vidrio esmerilado": panel semitransparente sobre la foto para separar el logo.
    Fuente ÚNICA de verdad → apariencia consistente en todos los documentos.

    - Logo = Branding::documentLogo() (logo del cliente configurable; fallback cc_pimienta).
    - EN PANTALLA usa `backdrop-filter:blur` (desenfoque REAL y alineado del fondo).
    - EN PDF el backdrop-filter no se rasteriza, así que la regla `@media print .hero-logo-plate`
      (en cada vista) sube la opacidad del panel a un blanco esmerilado visible y ALINEADO (deja
      ver la foto real debajo, no una zona equivocada). El blur gaussiano real no se captura en PDF.
    - $logoWidth opcional (default 200; Scouting usa 280).
--}}
@php
    $logoWidth = $logoWidth ?? 200;   // alineado con el default de _doc-hero (antes 300)
    // $logoSrc OPCIONAL: los documentos SELLADOS (p.ej. el póster MEDEVAC) pasan el logo CONGELADO
    // en su payload para que el membrete quede DENTRO del sello (cambiar `client_logo` en Marca no
    // altera un documento ya emitido). Sin él, se usa el logo VIVO de Marca — comportamiento de
    // siempre para los demás reportes (retrocompatible).
    $logoSrc = (isset($logoSrc) && $logoSrc !== '' && $logoSrc !== null) ? $logoSrc : \App\Support\Branding::documentLogo();
@endphp
{{-- 🪤 El relleno de la placa va INLINE, no con `px-5 py-3`. Esas son utilidades de Tailwind y
     estas vistas de reporte llevan su propio CSS: Tailwind no está cargado aquí, así que las clases
     no aplicaban NADA y la placa quedaba con relleno CERO — el logo pegado a los cuatro bordes, que
     es exactamente el "apretado" que se ve en pantalla y en el PDF. Se conservan las clases por si
     algún día se incluye en una vista con Tailwind, pero el relleno ya no depende de ellas.
     El alto se acota además con max-height: `width` sola deja crecer sin límite a un logo alto o
     cuadrado, y la placa se desborda. --}}
<div class="hero-logo-plate inline-flex items-center justify-center px-5 py-3"
     style="background-color: rgba(255,255,255,0.10); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-radius:6px; padding:14px 18px; display:inline-flex; align-items:center; justify-content:center;">
    <img src="{{ $logoSrc }}"
         alt="{{ $branding['brand_name'] ?? 'Logo' }}"
         style="width:{{ (int) $logoWidth }}px; max-width:100%; max-height:64px; height:auto; display:block;"
         data-hide-on-error>
</div>
