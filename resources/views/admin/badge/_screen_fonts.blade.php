{{--
    @font-face para PANTALLA (y html2canvas → JPG). Incrusta Poppins/Montserrat/Roboto desde
    los TTF LOCALES (public/fonts/*) por URL root-relative, para que el gafete conserve su
    tipografía aunque Google Fonts (CDN) no responda / se esté offline.

    Espejo de _pdf_fonts.blade.php (que usa rutas de DISCO para dompdf); aquí van URLs WEB.
    Sólo declara la cara si el TTF existe (defensivo). Incluir UNA vez por página (no dentro
    del bucle de tarjetas). NO usar en la ruta PDF (forPdf): ahí manda _pdf_fonts.
--}}
@php
    $screenFaces = [
        ['Poppins',    100, 'fonts/poppins/Poppins-Thin.ttf'],
        ['Poppins',    300, 'fonts/poppins/Poppins-Light.ttf'],
        ['Poppins',    400, 'fonts/poppins/Poppins-Regular.ttf'],
        ['Poppins',    700, 'fonts/poppins/Poppins-Bold.ttf'],
        ['Poppins',    900, 'fonts/poppins/Poppins-Black.ttf'],
        ['Montserrat', 400, 'fonts/montserrat/Montserrat-Regular.ttf'],
        ['Montserrat', 700, 'fonts/montserrat/Montserrat-Bold.ttf'],
        ['Roboto',     400, 'fonts/roboto/Roboto-Regular.ttf'],
        ['Roboto',     700, 'fonts/roboto/Roboto-Bold.ttf'],
    ];
@endphp
<style>
@foreach ($screenFaces as $f)
@if (is_file(public_path($f[2])))
@font-face { font-family: '{{ $f[0] }}'; font-weight: {{ $f[1] }}; font-style: normal; font-display: swap; src: url('/{{ $f[2] }}') format('truetype'); }
@endif
@endforeach
</style>
