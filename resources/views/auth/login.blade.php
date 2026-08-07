<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'CrewCare') }}</title>

    {{-- Login autocontenido: sin frameworks externos (ni jQuery/Bootstrap/FontAwesome).
         Solo la fuente Poppins de la marca + el CSS propio. Presentación pura: rutas,
         @csrf y mensajes del servidor NO cambian. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>

<body>
<main class="wrapper-login">
    <section class="login-card" aria-labelledby="login-title">

        <div class="login-top">
            @include('layouts._lang-switch')
        </div>

        <div class="login-head">
            <img class="login-logo" src="{{ URL::asset('img/logo-cc-login.svg') }}" width="147" height="150"
                 alt="CrewCare">
            <h1 id="login-title" class="login-subtitle">{{ __('auth_ui.subtitle') }}</h1>
        </div>

        {{-- (Paso 4) Los errores de acceso se ven DENTRO del bloque, no sueltos arriba.
             El texto es el del servidor (credenciales, validación); aquí solo se muestra. --}}
        @if ($errors->any())
            <div class="login-alert" role="alert">
                <svg class="login-alert-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form class="login-form" method="POST" action="{{ route('login') }}" novalidate>
            @csrf

            <div class="field">
                <label class="field-label" for="email">{{ __('auth_ui.email') }}</label>
                <input id="email" name="email" type="email"
                       class="field-input @error('email') is-invalid @enderror"
                       value="{{ old('email') }}"
                       placeholder="{{ __('auth_ui.email_ph') }}"
                       inputmode="email" autocomplete="email" autocapitalize="none"
                       autocorrect="off" spellcheck="false" required autofocus>
            </div>

            <div class="field">
                <label class="field-label" for="password">{{ __('auth_ui.password') }}</label>
                <div class="field-pw">
                    <input id="password" name="password" type="password"
                           class="field-input @error('password') is-invalid @enderror"
                           placeholder="{{ __('auth_ui.password_ph') }}"
                           autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" aria-controls="password" aria-pressed="false"
                            aria-label="{{ __('auth_ui.show_password') }}"
                            data-show="{{ __('auth_ui.show_password') }}"
                            data-hide="{{ __('auth_ui.hide_password') }}">
                        <svg class="pw-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M9.88 9.88a3 3 0 0 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="submit-btn" data-loading-label="{{ __('auth_ui.signing_in') }}">
                <span class="btn-default">
                    <span>{{ __('auth_ui.sign_in') }}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                </span>
                <span class="btn-loading" aria-hidden="true">
                    <svg class="btn-spinner" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="44 44"/></svg>
                    <span>{{ __('auth_ui.signing_in') }}</span>
                </span>
            </button>
        </form>

        <div class="hr"></div>

        <div class="login-foot">
            <a class="login-link" href="{{ route('password.request') }}">{{ __('auth_ui.forgot_password') }}</a>
        </div>
        <div class="login-foot login-copy">{{ __('auth_ui.copyright', ['year' => date('Y')]) }}</div>

    </section>
</main>

<script>
    (function () {
        // Mostrar / ocultar contraseña (escribir a ciegas de pie y con prisa es lo que más falla).
        var pw = document.getElementById('password');
        var toggle = document.querySelector('.pw-toggle');
        if (pw && toggle) {
            var eye = toggle.querySelector('.pw-eye');
            var eyeOff = toggle.querySelector('.pw-eye-off');
            toggle.addEventListener('click', function () {
                var show = pw.getAttribute('type') === 'password';
                pw.setAttribute('type', show ? 'text' : 'password');
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                toggle.setAttribute('aria-label', show ? toggle.dataset.hide : toggle.dataset.show);
                if (eye) { eye.hidden = show; }
                if (eyeOff) { eyeOff.hidden = !show; }
                pw.focus();
            });
        }

        // Estado CARGANDO: al enviar, el botón se deshabilita y lo dice (evita el triple clic
        // con mala señal). Solo corre si el navegador dejó pasar el submit (campos válidos).
        var form = document.querySelector('.login-form');
        var btn = form ? form.querySelector('.submit-btn') : null;
        if (form && btn) {
            form.addEventListener('submit', function () {
                btn.classList.add('is-loading');
                btn.setAttribute('aria-busy', 'true');
                // Deshabilita en el próximo tick para no cancelar el envío del propio botón.
                setTimeout(function () { btn.disabled = true; }, 0);
            });
        }
    })();
</script>
</body>

</html>
