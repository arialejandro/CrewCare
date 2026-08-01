{{--
    @font-face para dompdf (PDF). dompdf NO carga fuentes de Google/CDN: hay que
    incrustarlas desde archivos TTF locales (public/fonts/*). Se normalizan las barras
    a "/" porque en url() de CSS la barra invertida de Windows es un escape.
    Sólo se declara la cara si el TTF existe (defensivo).
--}}
@php
    $faces = [
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
@foreach ($faces as $f)
@php $abs = public_path($f[2]); @endphp
@if (is_file($abs))
@font-face { font-family: '{{ $f[0] }}'; font-weight: {{ $f[1] }}; font-style: normal; font-display: swap; src: url('{{ str_replace('\\', '/', $abs) }}') format('truetype'); }
@endif
@endforeach
</style>
