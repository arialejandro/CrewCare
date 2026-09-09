{{--
    _auth-password — campo de CONTRASEÑA con mostrar/ocultar (pantallas de auth).
    Reutilizable (login + reset: password, confirmar). El JS del layout de auth
    engancha todos los .pw-toggle por su aria-controls.

    Params: $id, $name, $label, $placeholder(''), $autocomplete('current-password'),
            $autofocus(false), $invalid(false)
--}}
@php
    $autofocus    = $autofocus ?? false;
    $autocomplete = $autocomplete ?? 'current-password';
    $invalid      = $invalid ?? false;
    $placeholder  = $placeholder ?? '';
@endphp
<div class="field">
    <label class="field-label" for="{{ $id }}">{{ $label }}</label>
    <div class="field-pw">
        <input id="{{ $id }}" name="{{ $name }}" type="password"
               class="field-input {{ $invalid ? 'is-invalid' : '' }}"
               placeholder="{{ $placeholder }}"
               autocomplete="{{ $autocomplete }}" required @if($autofocus) autofocus @endif>
        <button type="button" class="pw-toggle" aria-controls="{{ $id }}" aria-pressed="false"
                aria-label="{{ __('auth_ui.show_password') }}"
                data-show="{{ __('auth_ui.show_password') }}" data-hide="{{ __('auth_ui.hide_password') }}">
            <svg class="pw-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M9.88 9.88a3 3 0 0 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
        </button>
    </div>
</div>
