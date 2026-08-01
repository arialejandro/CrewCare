{{--
    Feedback de formularios de seguridad (flash + errores de validación).
    Cierra el "bug de feedback silencioso": cuando store()/updateStatus() hacían
    redirect()->back() con un error, la vista no mostraba NADA. Incluir este
    parcial arriba del formulario (create) y en el show (para la ValidationException
    del bloqueo de estado PDCA).

    Estilos INLINE a propósito → funciona igual en vistas Tailwind o Bootstrap.
--}}
@if(session('success'))
    <div style="margin-bottom:1rem;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;padding:.75rem 1rem;border-radius:.5rem;font-size:.875rem;line-height:1.4;">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div style="margin-bottom:1rem;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;padding:.75rem 1rem;border-radius:.5rem;font-size:.875rem;line-height:1.4;">
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div style="margin-bottom:1rem;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;padding:.75rem 1rem;border-radius:.5rem;font-size:.875rem;line-height:1.4;">
        <strong>Revisa los siguientes puntos:</strong>
        <ul style="margin:.35rem 0 0;padding-left:1.25rem;">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{--
    Scroll-to-first-error + foco: al rebotar la validación, lleva al usuario al PRIMER
    campo con error (.is-invalid) y le da foco, en vez de dejarlo arriba leyendo el
    resumen. 100% defensivo (try/catch) y @once para no duplicar el <script>.
--}}
@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    try {
        var el = document.querySelector('.is-invalid, [aria-invalid="true"]');
        if (!el) { return; }
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof el.focus === 'function') {
            // preventScroll: el scrollIntoView ya centra; evita un salto doble.
            el.focus({ preventScroll: true });
        }
    } catch (e) {}
});
</script>
@endpush
@endonce
