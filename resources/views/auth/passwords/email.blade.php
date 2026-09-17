@extends('layouts.auth')

{{-- Recuperar contraseña (pedir el enlace). Ruta password.email, campo email y
     el mensaje de estado del servidor NO cambian. --}}

@section('heading', __('auth_ui.reset_link_title'))
@section('tagline', __('auth_ui.reset_link_subtitle'))

@section('content')
    <form class="login-form" method="POST" action="{{ route('password.email') }}" novalidate>
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

        @include('componentes._auth-submit', [
            'label' => __('auth_ui.send_reset_link'), 'loading' => __('auth_ui.sending'),
        ])
    </form>
@endsection

@section('foot')
    <a class="login-link" href="{{ route('login') }}">{{ __('auth_ui.back_to_login') }}</a>
@endsection
