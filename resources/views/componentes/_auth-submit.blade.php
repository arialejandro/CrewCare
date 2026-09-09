{{--
    _auth-submit — botón primario de auth con estado CARGANDO (login + reset).
    El JS del layout de auth le pone .is-loading + disabled al enviar.
    Params: $label (texto normal), $loading (texto mientras envía).
--}}
<button type="submit" class="submit-btn">
    <span class="btn-default">
        <span>{{ $label }}</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
    </span>
    <span class="btn-loading" aria-hidden="true">
        <svg class="btn-spinner" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="44 44"/></svg>
        <span>{{ $loading }}</span>
    </span>
</button>
