@extends('layouts.app')
@section('content')

@push('styles')
    {{-- Fuentes del gafete autoalojadas para pantalla/JPG (Poppins/Montserrat/Roboto). --}}
    @include('admin.badge._screen_fonts')
@endpush

<div class="col-md-12 d-flex flex-column align-items-center">
    <div id="printpdf" class="p-3 py-5">
        <div id="print">
            @include('admin.badge._card', ['user' => $users, 'tpl' => $tpl, 'forPdf' => false])
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <a id="btnCapturar" href="#" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
            @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico', 'label' => null])
            <span>Descargar como imagen (JPG)</span>
        </a>
        <a id="btnPdf" href="{{ route('badge.pdf', $users->id) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            <span>Descargar como PDF</span>
        </a>
    </div>
</div>

@push('scripts')
{{-- (2026-07-19) html2canvas AUTOALOJADO (antes CDN hertzen.com) → JPG offline. --}}
<script type="text/javascript" src="/js/vendor/html2canvas.min.js"></script>
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
        var boton = document.getElementById('btnCapturar');
        if (!boton) return;

        // Carga las fuentes del gafete antes de rasterizar (si no, el JPG usa la fuente de
        // sistema y se rompe el formato). Degrada a directo si la API no existe.
        function withFonts(cb) {
            if (!document.fonts || !document.fonts.load) { cb(); return; }
            var want = ['300 16px Poppins', '400 16px Poppins', '700 16px Poppins', '900 16px Poppins',
                        '400 16px Montserrat', '700 16px Montserrat', '400 16px Roboto', '700 16px Roboto'];
            Promise.all(want.map(function (f) { return document.fonts.load(f).catch(function () {}); }))
                .then(function () { return document.fonts.ready; })
                .then(cb).catch(cb);
        }

        boton.addEventListener('click', function (e) {
            e.preventDefault();

            // Guard: si la librería no cargó, AVISA en vez de no hacer nada.
            if (typeof html2canvas === 'undefined') {
                alert('No se pudo cargar la librería de imagen. Recarga la página; si persiste, usa "Descargar como PDF".');
                return;
            }

            boton.style.pointerEvents = 'none';
            boton.setAttribute('aria-busy', 'true');

            withFonts(function () {
                var target = document.getElementById('print');
                html2canvas(target, { scale: 2, useCORS: true, backgroundColor: '#ffffff' }).then(function (canvas) {
                    var dataUrl = canvas.toDataURL('image/jpeg', 0.95);
                    var a = document.createElement('a');
                    a.href = dataUrl;
                    a.download = 'gafete-{{ $users->id }}.jpg';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    boton.style.pointerEvents = '';
                    boton.removeAttribute('aria-busy');
                }).catch(function () {
                    alert('No se pudo generar la imagen del gafete. Intenta de nuevo o usa "Descargar como PDF".');
                    boton.style.pointerEvents = '';
                    boton.removeAttribute('aria-busy');
                });
            });
        });
    });
</script>
@endpush

@endsection
