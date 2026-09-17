{{--
    _row-link.blade.php — filas de tabla clicables SIN onclick inline (CSP).
    Uso: <tr data-href="{{ route(...) }}" style="cursor:pointer;">…</tr> y @include este parcial una vez.
    Un clic en la fila navega a data-href; un clic en un enlace/botón/control DENTRO de la fila NO
    navega (reemplaza el viejo manejador de stopPropagation en atributo). @once: seguro incluirlo de más.
--}}
@once
@push('scripts')
<script>
    document.addEventListener('click', function (e) {
        // No secuestrar clics en controles interactivos dentro de la fila.
        if (e.target.closest('a, button, input, select, textarea, label')) { return; }
        var row = e.target.closest('tr[data-href]');
        if (row) { window.location = row.getAttribute('data-href'); }
    });
</script>
@endpush
@endonce
