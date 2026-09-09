@extends('layouts.auth')

{{-- Login. Encabezado por defecto = "Salud y Seguridad" (auth_ui.subtitle).
     Presentación: la ruta, @csrf, campos (email/password) y los mensajes del
     servidor NO cambian. --}}

@section('content')
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

        @include('componentes._auth-password', [
            'id' => 'password', 'name' => 'password',
            'label' => __('auth_ui.password'), 'placeholder' => __('auth_ui.password_ph'),
            'autocomplete' => 'current-password', 'invalid' => $errors->has('password'),
        ])

        @include('componentes._auth-submit', [
            'label' => __('auth_ui.sign_in'), 'loading' => __('auth_ui.signing_in'),
        ])
    </form>
@endsection

@section('foot')
    <a class="login-link" href="{{ route('password.request') }}">{{ __('auth_ui.forgot_password') }}</a>
@endsection
