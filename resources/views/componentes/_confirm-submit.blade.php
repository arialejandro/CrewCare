{{--
    _confirm-submit.blade.php — confirmación de un submit destructivo, SIN JS inline.

    POR QUÉ EXISTE (no es azúcar sintáctico, es la corrección de un XSS almacenado):
    el patrón viejo era

        <form onsubmit="return confirm('¿Eliminar «{{ $item->name }}»?');">

    y ahí {{ }} NO protege, aunque lo parezca. El atributo `onsubmit` es contexto JS
    dentro de contexto HTML: Blade escapa la comilla simple a &#039;, pero el parser de
    HTML DECODIFICA la entidad ANTES de que el motor de JS compile el handler. O sea que
    el nombre entra al literal de cadena como comilla real y lo rompe. Dos caras del
    mismo fallo, ambas verificadas en esta BD:
      · Benigna: un apóstrofo normal ("Tierra de baton (Fuller's earth)") produce un
        SyntaxError → el handler NO se compila → `form.onsubmit === null` → el submit
        sale SIN preguntar. El borrado de Consumable es DURO (no hay SoftDeletes) y
        además purga el pivote N:M: el confirm era la única salvaguarda.
      · Hostil: quien tiene sds.create escribe el `name` y con él cierra la cadena e
        inyecta JS que se ejecuta en la sesión de quien tiene sds.manage al pulsar
        «Verificar» — cruza el único acto de gobierno del módulo.

    LA CAUSA es meter datos en un contexto JS. Aquí NO se meten: el mensaje viaja en un
    atributo `data-confirm`, que es contexto HTML puro — exactamente para lo que {{ }}
    (e() con ENT_QUOTES) está hecho — y el JS lo LEE con getAttribute, sin compilar nada.
    No hay literal de cadena que romper, así que el apóstrofo deja de ser un problema y
    la inyección deja de tener superficie.

    USO:
      1) En la vista, una sola vez (dentro de @@section o donde se renderice el form):
             @@include('componentes._confirm-submit')
         Es idempotente: lleva @@once, así que incluirlo de más no duplica el script.
      2) En cada form destructivo, en vez de onsubmit:
             <form method="POST" action="…" data-confirm="{{ $mensaje }}">
         $mensaje puede llevar saltos de línea reales ("\n"): el atributo los conserva.

    DEGRADACIÓN: sin JS no hay diálogo y el form se envía — idéntico al onsubmit viejo
    (que tampoco corría sin JS), así que no se pierde nada respecto al comportamiento
    anterior. La autorización real no vive aquí: la hacen la ruta (permission:x) y el
    controlador. Esto es una red de seguridad de UI, nunca un control de acceso.

    PHP 7.4: sin nullsafe ni match.
--}}
@once
@push('scripts')
<script>
    (function () {
        // Delegado en el documento y en FASE DE CAPTURA: es un veto, así que tiene que
        // correr ANTES que cualquier otro listener de submit del form. Si el usuario
        // cancela, se detiene la propagación para que nadie más lo procese.
        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (!form || typeof form.getAttribute !== 'function') { return; }

            var msg = form.getAttribute('data-confirm');
            // Sin atributo (null) o vacío → este form no pide confirmación.
            if (msg === null || msg === '') { return; }

            if (!window.confirm(msg)) {
                ev.preventDefault();
                ev.stopPropagation();
            }
        }, true);
    })();
</script>
@endpush
@endonce
