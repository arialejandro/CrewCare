<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- <title>{{ config('app.name', 'Control covid') }}</title> -->
    <title>{{ $branding['app_title'] ?? 'CrewCare' }}</title>

    <!-- Scripts -->
    <link rel="stylesheet" href="{{ asset("css/form-register.css") }}">
    {{-- CSP/local: Bootstrap 5.1.3 CSS servido desde 'self' (antes jsdelivr). Misma versión. --}}
    <link href="{{ asset('vendor/bootstrap/css/bootstrap-5.1.3.min.css') }}" rel="stylesheet">
    {{-- bootstrap-icons RETIRADO: 0 usos de `bi-*` en vistas vivas (era una carga de CDN muerta). --}}
    {{-- CSP/local: Bootstrap 5.1.3 bundle (incluye Popper) servido desde 'self' (antes jsdelivr). Misma versión. --}}
    <script src="{{ asset('js/vendor/bootstrap-5.1.3.bundle.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    {{-- CSP: oculta <img data-hide-on-error> rotas sin onerror inline (same-origin, en <head>). --}}
    <script src="/js/img-fallback.js"></script>
    {{-- TinyMCE removido temporalmente (se reintroduce con el módulo de correos masivos) --}}
    {{-- CSP/local: Chart.js servido desde 'self' (antes jsdelivr sin pin → 4.5.x). Fijado a 4.5.1. --}}
    <script src="{{ asset('js/vendor/chart-4.5.1.umd.min.js') }}"></script>




    <script src="{{ asset('js/a2hs.js') }}"></script>

    <!-- Fonts · CSP/local: autoalojadas desde 'self' (antes Google Fonts). ui-fonts.css declara
         Poppins (fuente de la UI) + Roboto Condensed (pósters/hero) + Roboto + las fuentes de firma;
         reusa los .woff2 de /fonts/reports. Se RETIRARON Nunito, Lato y Material Icons: ningún CSS
         cargado los aplicaba (fuentes muertas, verificado con document.fonts); el full-range de
         Roboto tampoco se usaba. gstatic/googleapis quedan FUERA de la política. -->
    <link rel="stylesheet" href="{{ asset('fonts/ui/ui-fonts.css') }}">
    {{-- CSP/local: Font Awesome 6.7.2 (CSS + webfonts en ../webfonts) servido desde 'self' (antes cdnjs). --}}
    <link href="{{ asset('vendor/fontawesome/css/all-6.7.2.min.css') }}" rel="stylesheet">
    
    {{-- CSP/local: jQuery servido desde 'self' (antes code.jquery.com 3.3.1). Actualizado a 3.7.1
         (el 3.3.1 era de 2018). Misma posición de carga para no alterar el orden. --}}
    <script src="{{ asset('js/vendor/jquery-3.7.1.min.js') }}"></script>
{{-- Lato y los preconnect a Google Fonts ELIMINADOS: Lato no lo aplicaba ningún CSS cargado
     (sólo el legado main.css, que no se enlaza) y las fuentes ya se autoalojan en ui-fonts.css. --}}

{{-- PWA: el paquete silviolleite/laravelpwa se retiró en el upgrade (era sólo el cascarón;
     ver [[pwa-push-native-strategy]]). Una PWA propia (manifest + service worker) llegará después. --}}

    {{-- TEMA GLOBAL DE MARCA: variables CSS + puente a Bootstrap + utilidades .*-brand.
         Se incluye ANTES de @stack('styles') para que sea la base de toda la app y las
         páginas puedan ajustar por encima si alguna vez lo necesitan. --}}
    @include('layouts._brand-theme')

    {{-- Fondo/tinta de la app atados a los TOKENS semánticos → el modo oscuro real
         cubre TODA la página (antes el body quedaba blanco de Bootstrap en oscuro).
         En claro es idéntico a antes. Se pone tras _brand-theme (que define los tokens)
         y antes de @stack('styles') para que las páginas puedan ajustar por encima. --}}
    <style>
        {{-- Fondo CINEMATOGRÁFICO de página (token --bg del layer glass) para que la luz
             ambiental (_ambient) se vea detrás del contenido. Los wrappers quedan
             transparentes; las tarjetas/paneles ya son de vidrio (upgrade global). --}}
        body { background: var(--bg); color: var(--text); }
        .container-fluid, .container-fluid > .row, #app { background: transparent; }
        /* Contenedor canónico: la columna col-md-10 es el marco junto al sidebar; la
           altura mínima evita que el fondo deje ver el blanco del navegador. */
        main.cc-main { min-height: calc(100vh - 60px); background: transparent; }
    </style>

    {{-- Per-page styles (additive): views push their CSS here so it loads in <head>
         instead of being injected before @extends (the old Quirks-mode cause). --}}
    @stack('styles')
</head>

<body>

    {{-- Luz ambiental cinematográfica (blobs de marca + índigo). Se pinta DETRÁS del
         contenido (position:fixed; z-index:-1) y encima del color de fondo del body. --}}
    @include('componentes._ambient')

            @include('layouts.header')

        {{-- Franja de UNIDAD VIGENTE (Unidades 2b): grita cuando se trabaja fuera de la principal.
             Sólo se pinta con más de una unidad y estando fuera de la principal → idéntico a hoy si no. --}}
        @include('layouts._unit-banner')

        <div class="container-fluid">
            <div class="row">
                <!-- -------------- Sidebar - Author -------------- -->
            {{-- (2026-07-24) Antes: `admin || can('users.view')`. Ese OR era el puente de la
                 migración a RBAC y funcionó mientras TODOS los roles de oficina tenían
                 `users.view`. El rol `medic` no lo tiene —un médico no administra el directorio
                 de crew— así que un médico con `admin=0` se quedaba sin sidebar y nunca veía el
                 menú médico, aunque pasara todos sus permisos. Ahora el criterio es tener al
                 menos UN permiso de panel (User::PANEL_PERMISSIONS, fuente única). --}}
            @if(auth()->check() && auth()->user()->canSeePanel())
                @include('layouts.sidebar')
            @else
            @endif
            <!-- -------------- Sidebar Hide Button -------------- -->
        {{-- Responsive fix (2026-07): era col-lg-10 — en tablets (md, 768-991px) el sidebar
             col-md-2 sí se muestra pero el main tomaba width:100% y se ENVOLVÍA DEBAJO del
             sidebar. col-md-10 lo alinea al lado del sidebar desde md en adelante. --}}
        <main class="col-md-10 cc-main">
            
                <div id="app">
                        
                         @yield('content')
                        
                        
                </div>
             </main>
        </div>


        {{-- TinyMCE removido temporalmente (se reintroduce con el módulo de correos masivos).
             El campo #MyEmail queda como <textarea> normal. --}}


        {{-- Paleta de comandos global (⌘K / Ctrl-K). Se incluye al final para que el
             sidebar ya esté renderizado cuando su JS recolecte los enlaces (RBAC). --}}
        @include('componentes._command-palette')

        {{-- Transportación (Fase 5): poll liviano + toast del contador de atención (self-guarded). --}}
        @auth @include('layouts._transport-notify') @endauth

        {{-- Per-page scripts (additive): views push their JS here, after the layout's
             own JS (Bootstrap 5 bundle, jQuery, Chart.js, app.js) so dependencies exist. --}}
        @stack('scripts')

        {{-- (2026-08-24) CONVERSIÓN HEIC EN CLIENTE, GLOBAL. Antes se incluía por página; ahora
             engancha CUALQUIER <input type=file accept*=image> del sitio (idempotente: si una vista
             vieja también lo incluye, no se duplica el listener). Un iPhone convierte su HEIC a JPEG
             ANTES de subir → el servidor no lo ve. Los flujos con cableado manual (scouting/riskmap)
             marcan sus inputs con data-cc-noauto. El servidor conserva su conversión (Imagick+libheif)
             como respaldo y el rechazo accionable como último recurso. --}}
        <script src="{{ asset('js/cc-photo.js') }}"></script>
        <script src="{{ asset('js/cc-photo-auto.js') }}"></script>
    </body>

</html>