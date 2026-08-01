{{--
    _repeatable-rows — grupos de FILAS que se auto-agregan (captura fluida).
    Reutilizable: cualquier tabla/lista que arranque vacía y agregue filas a demanda.
    Fuente ÚNICA del comportamiento (evita que dos implementaciones divergan).

    Convención de marcado en el anfitrión:
      <div data-cc-repeat>
        <tbody data-cc-repeat-body> ... <tr data-cc-repeat-row> ...
             <button type="button" data-cc-repeat-remove>&times;</button> </tr> ... </tbody>
        <template data-cc-repeat-tpl><tr data-cc-repeat-row> ...fila vacía... </tr></template>
        <button type="button" data-cc-repeat-add>Agregar</button>
      </div>

    Los inputs usan name[] SIN índice: el POST los reindexa por orden del DOM. NO auto-foca
    (en móvil abriría el teclado de golpe). Se puede quitar cualquier fila, incluso la última.
--}}
@once
@push('scripts')
<script>
(function () {
    function initGroup(root) {
        if (root.__ccRepeat) return;
        root.__ccRepeat = true;
        var body = root.querySelector('[data-cc-repeat-body]');
        var tpl  = root.querySelector('template[data-cc-repeat-tpl]');
        var add  = root.querySelector('[data-cc-repeat-add]');
        if (!body || !tpl) return;

        if (add) {
            add.addEventListener('click', function () {
                body.appendChild(tpl.content.cloneNode(true));
            });
        }
        // Quitar por delegación (funciona también en filas recién agregadas).
        body.addEventListener('click', function (e) {
            var btn = e.target && e.target.closest ? e.target.closest('[data-cc-repeat-remove]') : null;
            if (!btn) return;
            var row = btn.closest('[data-cc-repeat-row]') || btn.closest('tr');
            if (row) { row.remove(); }
        });
    }
    function initAll() {
        document.querySelectorAll('[data-cc-repeat]').forEach(initGroup);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
</script>
@endpush
@endonce
