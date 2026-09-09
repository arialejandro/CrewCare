{{--
    _autosubmit.blade.php — envía el form al cambiar un control marcado [data-autosubmit], SIN
    onchange inline (CSP). Uso: <select data-autosubmit> / <input type=date data-autosubmit> + @include.
    Delegado y @once: seguro incluirlo de más. Respeta el atributo form= (el control puede vivir fuera del form).
--}}
@once
@push('scripts')
<script>
    document.addEventListener('change', function (e) {
        var el = e.target.closest('[data-autosubmit]');
        if (el && el.form) { el.form.submit(); }
    });
    // Bonus: [data-select-on-click] selecciona todo el texto al hacer click (copiar enlaces).
    document.addEventListener('click', function (e) {
        var s = e.target.closest('[data-select-on-click]');
        if (s && typeof s.select === 'function') { s.select(); }
    });
</script>
@endpush
@endonce
