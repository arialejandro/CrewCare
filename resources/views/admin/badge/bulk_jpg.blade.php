@extends('layouts.app')
@section('content')

@push('styles')
    {{-- Fuentes del gafete autoalojadas para pantalla/JPG (Poppins/Montserrat/Roboto). --}}
    @include('admin.badge._screen_fonts')
@endpush

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="mb-0">Gafetes — Exportar ZIP de JPG</h4>
        <div class="d-flex align-items-center gap-3">
            <span id="progreso" class="text-muted small"></span>
            <button id="btnZip" class="btn btn-primary">
                <i class="fa-solid fa-file-zipper"></i> Descargar ZIP de JPG
            </button>
        </div>
    </div>

    <p class="text-muted small">
        Se generarán {{ count($users) }} gafete(s) (los de tu alcance). El navegador dibuja cada
        credencial y las empaqueta en un solo archivo <code>credenciales.zip</code>.
    </p>

    <div id="badges" class="d-flex flex-wrap gap-3">
        @foreach ($users as $user)
            <div class="gft-export" data-name="gafete-{{ $user->id }}">
                @include('admin.badge._card', ['user' => $user, 'tpl' => $tpl, 'forPdf' => false])
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
{{-- (2026-07-19) Librerías AUTOALOJADAS (antes iban por CDN: html2canvas.hertzen.com + cdnjs).
     Así el export en lote funciona offline / sin CDN. Rutas root-relative (asset() no es fiable). --}}
<script type="text/javascript" src="/js/vendor/html2canvas.min.js"></script>
<script type="text/javascript" src="/js/vendor/jszip.min.js"></script>
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('btnZip');
        var progreso = document.getElementById('progreso');
        if (!btn) return;

        // Muestra un error VISIBLE (no falla en silencio) y re-habilita el botón.
        function fail(msg) {
            progreso.className = 'small text-danger';
            progreso.textContent = msg;
            btn.disabled = false;
        }

        // Carga las fuentes locales del gafete ANTES de rasterizar: si html2canvas dibuja
        // antes de que Poppins/Montserrat/Roboto estén listas, el JPG saldría con la fuente
        // de sistema y el formato gráfico se rompe. Degrada a ejecutar directo si no hay API.
        function withFonts(cb) {
            if (!document.fonts || !document.fonts.load) { cb(); return; }
            var want = ['300 16px Poppins', '400 16px Poppins', '700 16px Poppins', '900 16px Poppins',
                        '400 16px Montserrat', '700 16px Montserrat', '400 16px Roboto', '700 16px Roboto'];
            Promise.all(want.map(function (f) { return document.fonts.load(f).catch(function () {}); }))
                .then(function () { return document.fonts.ready; })
                .then(cb).catch(cb);
        }

        btn.addEventListener('click', function () {
            progreso.className = 'small text-muted';

            // Guard: si las librerías no cargaron (archivo movido, red bloqueada), AVISA.
            if (typeof html2canvas === 'undefined' || typeof JSZip === 'undefined') {
                fail('No se pudieron cargar las librerías de exportación. Recarga la página; si persiste, usa "Descargar PDF".');
                return;
            }

            var nodes = Array.prototype.slice.call(document.querySelectorAll('.gft-export'));
            if (nodes.length === 0) {
                fail('No hay gafetes para exportar.');
                return;
            }

            btn.disabled = true;
            var zip = new JSZip();
            var i = 0, ok = 0, failed = 0;

            function finish() {
                // Si NINGUNO se generó, no bajes un ZIP vacío: avisa.
                if (ok === 0) {
                    fail('No se pudo generar ningún gafete (' + failed + ' con error). Intenta de nuevo o usa "Descargar PDF".');
                    return;
                }
                progreso.textContent = 'Empaquetando...';
                zip.generateAsync({ type: 'blob' }).then(function (blob) {
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'credenciales.zip';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    // Mensaje HONESTO: éxitos reales vs total (antes decía "Listo (N)" aunque hubiera fallos).
                    if (failed > 0) {
                        progreso.className = 'small text-warning';
                        progreso.textContent = 'Listo: ' + ok + ' de ' + nodes.length + ' generados (' + failed + ' con error).';
                    } else {
                        progreso.textContent = 'Listo (' + ok + ').';
                    }
                    btn.disabled = false;
                }).catch(function () {
                    fail('Error al empaquetar el ZIP. Intenta de nuevo.');
                });
            }

            function next() {
                if (i >= nodes.length) { finish(); return; }

                var node = nodes[i];
                var name = node.getAttribute('data-name') || ('gafete-' + i);
                progreso.textContent = 'Generando ' + (i + 1) + ' / ' + nodes.length + '...';

                var target = node.querySelector('.gft-card') || node;
                var p;
                try {
                    p = html2canvas(target, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
                } catch (e) {
                    failed++; i++; next(); return;
                }
                p.then(function (canvas) {
                    var dataUrl = canvas.toDataURL('image/jpeg', 0.95);
                    zip.file(name + '.jpg', dataUrl.split(',')[1], { base64: true });
                    ok++; i++; next();
                }).catch(function () {
                    failed++; i++; next();
                });
            }

            withFonts(next);
        });
    });
</script>
@endpush

@endsection
