@extends('layouts.auth')

{{-- Restablecer contraseña (con el token del enlace). ⚠ Antes esta vista tenía
     @section('content') SIN @extends → renderizaba EN BLANCO. Ahora usa el layout
     de auth. Ruta password.update, hidden token, campos y validación NO cambian. --}}

@section('heading', __('auth_ui.reset_title'))
@section('tagline', __('auth_ui.reset_subtitle'))

@section('content')
    <form class="login-form" method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label class="field-label" for="email">{{ __('auth_ui.email') }}</label>
            <input id="email" name="email" type="email"
                   class="field-input @error('email') is-invalid @enderror"
                   value="{{ $email ?? old('email') }}"
                   placeholder="{{ __('auth_ui.email_ph') }}"
                   inputmode="email" autocomplete="email" autocapitalize="none"
                   autocorrect="off" spellcheck="false" required>
        </div>

        @include('componentes._auth-password', [
            'id' => 'password', 'name' => 'password',
            'label' => __('auth_ui.new_password'), 'placeholder' => __('auth_ui.new_password_ph'),
            'autocomplete' => 'new-password', 'invalid' => $errors->has('password'), 'autofocus' => true,
        ])

        @include('componentes._auth-password', [
            'id' => 'password-confirm', 'name' => 'password_confirmation',
            'label' => __('auth_ui.confirm_password'), 'placeholder' => __('auth_ui.confirm_password_ph'),
            'autocomplete' => 'new-password',
        ])

        @include('componentes._auth-submit', [
            'label' => __('auth_ui.save_password'), 'loading' => __('auth_ui.saving'),
        ])
    </form>
@endsection

@section('foot')
    <a class="login-link" href="{{ route('login') }}">{{ __('auth_ui.back_to_login') }}</a>
@endsection
