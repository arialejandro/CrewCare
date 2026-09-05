<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'CrewCare') }}</title>

    {{-- Shell COMPARTIDO de las pantallas de auth (login + recuperar/restablecer
         contraseña). Superficie ÚNICA sin marca de cliente: azul CrewCare fijo,
         estilo GLASS oscuro como el interior de la app. Autocontenido: solo
         Poppins + login.css (sin Bootstrap/jQuery/FontAwesome). Presentación pura:
         rutas, @csrf y mensajes del servidor NO cambian. --}}
    {{-- CSP/local: Poppins autoalojada desde 'self' (antes Google Fonts). Login usa 400/500/600/700,
         todos presentes en ui-fonts.css. --}}
    <link rel="stylesheet" href="{{ asset('fonts/ui/ui-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    @stack('head')
</head>

<body>
<main class="wrapper-login">
    <section class="login-card" aria-labelledby="auth-title">

        <div class="login-top">
            @include('layouts._lang-switch')
        </div>

        <div class="login-head">
            <img class="login-logo" src="{{ URL::asset('img/logo-cc-login.svg') }}" width="147" height="150" alt="CrewCare">
            <h1 id="auth-title" class="login-subtitle">@yield('heading', __('auth_ui.subtitle'))</h1>
            @hasSection('tagline')
                <p class="login-tagline">@yield('tagline')</p>
            @endif
        </div>

        {{-- Mensaje de estado del servidor (p. ej. "te enviamos el enlace"). --}}
        @if (session('status'))
            <div class="login-note" role="status">
                <svg class="login-note-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Errores del servidor DENTRO del bloque (no sueltos arriba). --}}
        @if ($errors->any())
            <div class="login-alert" role="alert">
                <svg class="login-alert-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @yield('content')

        <div class="hr"></div>

        @hasSection('foot')
            <div class="login-foot">@yield('foot')</div>
        @endif
        <div class="login-foot login-copy">{{ __('auth_ui.copyright', ['year' => date('Y')]) }}</div>

    </section>
</main>

<script>
    (function () {
        // Mostrar / ocultar contraseña — engancha TODOS los .pw-toggle por su aria-controls
        // (login: 1 campo; restablecer: contraseña + confirmar).
        var toggles = document.querySelectorAll('.pw-toggle');
        Array.prototype.forEach.call(toggles, function (toggle) {
            var input = document.getElementById(toggle.getAttribute('aria-controls'));
            if (!input) { return; }
            var eye = toggle.querySelector('.pw-eye');
            var eyeOff = toggle.querySelector('.pw-eye-off');
            toggle.addEventListener('click', function () {
                var show = input.getAttribute('type') === 'password';
                input.setAttribute('type', show ? 'text' : 'password');
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                toggle.setAttribute('aria-label', show ? toggle.dataset.hide : toggle.dataset.show);
                if (eye) { eye.hidden = show; }
                if (eyeOff) { eyeOff.hidden = !show; }
                input.focus();
            });
        });

        // Estado CARGANDO al enviar (deshabilita + spinner). Solo si el navegador dejó
        // pasar el submit (campos válidos). Deshabilita en el próximo tick para no
        // cancelar el propio envío del botón.
        var form = document.querySelector('.login-form');
        var btn = form ? form.querySelector('.submit-btn') : null;
        if (form && btn) {
            form.addEventListener('submit', function () {
                btn.classList.add('is-loading');
                btn.setAttribute('aria-busy', 'true');
                setTimeout(function () { btn.disabled = true; }, 0);
            });
        }
    })();
</script>
</body>

</html>
