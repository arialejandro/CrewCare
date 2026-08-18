{{-- PÁGINA DE FIRMA (Paso C) — independiente (el contratado firma sin sesión). Ve el paquete
     completo, consiente (una vez), DIBUJA SU FIRMA AUTÓGRAFA (DocuSign) y firma. La ruta avanza sola. --}}
@php $docs = $envelope->documents ?? []; @endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Firma de contrato') }}</title>
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; background: #0b1220; color: #e5e7eb;
        font-family: system-ui, -apple-system, Segoe UI, sans-serif; padding: 1.2rem; display: flex; justify-content: center; }
    .wrap { width: 100%; max-width: 560px; }
    .card { background: #111a2e; border: 1px solid #1f2b44; border-radius: 16px; padding: 1.4rem; margin-bottom: 1rem; }
    h1 { font-size: 1.2rem; margin: 0 0 .3rem; }
    .muted { color: #9aa4b2; font-size: .88rem; }
    .doc { display: flex; justify-content: space-between; align-items: center; padding: .6rem 0; border-bottom: 1px solid #1f2b44; }
    .doc:last-child { border-bottom: 0; }
    .doc a { color: #7dd3fc; text-decoration: none; font-weight: 600; }
    label.consent { display: flex; gap: .6rem; align-items: flex-start; font-size: .88rem; color: #cbd5e1; margin: 1rem 0; }
    /* El submit tiene su propia clase para NO aplastar los botones del pad de firma (type=button). */
    .cc-submit { width: 100%; padding: .8rem; border: 0; border-radius: 10px; background: #16a34a; color: #fff; font-weight: 700; font-size: 1.05rem; cursor: pointer; margin-top: 1rem; }
    .ok { color: #86efac; } .warn { color: #fcd34d; }
    .err { background: #3b1220; border: 1px solid #7f1d3a; color: #fecdd3; padding: .6rem .8rem; border-radius: 10px; font-size: .85rem; margin-bottom: 1rem; }
    /* Base mínima para el pad de firma (esta página no carga Bootstrap). */
    .cc-label { display: block; font-size: .82rem; color: #cbd5e1; margin-bottom: .4rem; }
    .cc-sigpad .btn { width: auto; padding: .38rem .7rem; border: 1px solid #2a3a5c; border-radius: 8px; background: #16233f; color: #e5e7eb; font-size: .8rem; font-weight: 600; cursor: pointer; }
    .cc-sigpad .form-control { padding: .38rem .6rem; border: 1px solid #2a3a5c; border-radius: 8px; background: #0b1220; color: #e5e7eb; font-size: .85rem; }
    .cc-sigpad__savelbl { color: #9aa4b2; }
    /* Rechazar: acción secundaria, subordinada a firmar (no compite con el botón verde). */
    .cc-decline-toggle { background: none; border: 0; color: #9aa4b2; font-size: .85rem; text-decoration: underline; cursor: pointer; padding: 0; }
    .cc-decline-toggle:hover { color: #cbd5e1; }
    .cc-decline-input { width: 100%; padding: .5rem .6rem; border: 1px solid #7f1d3a; border-radius: 8px; background: #0b1220; color: #e5e7eb; font-size: .9rem; font-family: inherit; }
    .cc-decline-submit { width: 100%; margin-top: .7rem; padding: .6rem; border: 1px solid #7f1d3a; border-radius: 10px; background: transparent; color: #fecdd3; font-weight: 700; font-size: .95rem; cursor: pointer; }
    .cc-decline-submit:hover { background: #3b1220; }
    /* Visor de documento inline (pdf.js): leer el contrato SIN salir de la página. */
    .ccdoc { border: 1px solid #1f2b44; border-radius: 12px; overflow: hidden; margin-bottom: .8rem; }
    .ccdoc__h { display: flex; justify-content: space-between; align-items: center; gap: .6rem; padding: .55rem .8rem; background: #0e1526; border-bottom: 1px solid #1f2b44; }
    .ccdoc__name { font-weight: 600; font-size: .9rem; }
    .ccdoc__open { color: #7dd3fc; text-decoration: none; font-size: .8rem; font-weight: 600; white-space: nowrap; }
    .ccdoc__pages { max-height: 62vh; overflow: auto; padding: .8rem; background: #525659; -webkit-overflow-scrolling: touch; }
    .ccdoc__loading { color: #e5e7eb; text-align: center; padding: 1.4rem 0; font-size: .85rem; }
    .ccpage { margin: 0 auto .7rem; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.4); }
    .ccpage:last-child { margin-bottom: 0; }
    .cc-jump { display: inline-flex; align-items: center; gap: .4rem; background: none; border: 0; color: #7dd3fc; font-size: .88rem; font-weight: 600; cursor: pointer; padding: .3rem 0 0; text-decoration: underline; }
</style>
@stack('styles')
</head>
<body>
<div class="wrap">
    @if($stage === 'done')
        <div class="card">
            @if($envelope->isDeclined())
                <h1 class="warn">{{ __('Contrato rechazado') }}</h1>
                <p class="muted">{{ __('Registramos que no se firmará este contrato. Producción se encargará del siguiente paso.') }}</p>
            @elseif($envelope->isExpired())
                <h1 class="warn">{{ __('Sobre vencido') }}</h1>
                <p class="muted">{{ __('El plazo para firmar este contrato ya pasó. Avísale a producción si aún necesitas firmarlo.') }}</p>
            @elseif($envelope->isCancelled())
                <h1 class="warn">{{ __('Sobre anulado') }}</h1>
                <p class="muted">{{ __('Este contrato fue anulado por producción. No hay nada que firmar aquí.') }}</p>
            @else
                <h1 class="ok">{{ __('Listo') }}</h1>
                <p class="muted">{{ __('Este sobre ya está firmado o cerrado. No hay nada más que hacer aquí.') }}</p>
            @endif
        </div>
    @elseif($stage === 'not_turn')
        <div class="card">
            <h1 class="warn">{{ __('Aún no es tu turno') }}</h1>
            <p class="muted">{{ __('El sobre está en firma con otra persona. En cuanto sea tu turno recibirás un correo con el enlace para firmar.') }}</p>
        </div>
    @else
        <div class="card">
            <h1>{{ __('Firma tu contrato') }}</h1>
            <p class="muted">{{ $recipient->name }} · {{ $recipient->roleLabel() }}</p>
        </div>

        <div class="card">
            <div class="muted" style="margin-bottom:.6rem">{{ __('Revisa tu contrato antes de firmar:') }}</div>
            @forelse($docs as $i => $doc)
                <section class="ccdoc">
                    <div class="ccdoc__h">
                        <span class="ccdoc__name">{{ $doc['name'] ?? __('Documento') }}</span>
                        <a class="ccdoc__open" href="{{ $docUrl($i) }}" target="_blank" rel="noopener">{{ __('Abrir en pestaña') }}</a>
                    </div>
                    <div class="ccdoc__pages" data-pdf-src="{{ $docUrl($i) }}">
                        <div class="ccdoc__loading">{{ __('Cargando documento…') }}</div>
                    </div>
                </section>
            @empty
                <div class="muted">{{ __('No hay documentos para mostrar.') }}</div>
            @endforelse
            <button type="button" class="cc-jump" onclick="var b=document.getElementById('ccSignBox'); if(b){ b.scrollIntoView({behavior:'smooth'}); }">
                {{ __('Ir a firmar ↓') }}
            </button>
        </div>

        <div class="card" id="ccSignBox">
            @if(session('error'))<div class="err">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $signUrl }}">
                @csrf
                @if($needsConsent)
                    <label class="consent">
                        <input type="checkbox" name="consent" value="1" required>
                        <span>{{ __('Acepto firmar electrónicamente. Reconozco que mi firma electrónica tiene la misma validez que la autógrafa (Cód. de Comercio 89 y 89 Bis; CCF 1811).') }}</span>
                    </label>
                @endif

                @include('componentes._signature-pad', [
                    'label'   => __('Dibuja tu firma (o escríbela con tu nombre):'),
                    'adopted' => optional($recipient->user)->adopted_signature,
                ])

                <button type="submit" class="cc-submit">{{ __('Firmar el paquete') }}</button>
            </form>
        </div>

        {{-- RECHAZAR (Fase 2) — el firmante se niega, con motivo obligatorio. Detiene el sobre. --}}
        <div class="card">
            <button type="button" class="cc-decline-toggle" onclick="ccToggleDecline()">{{ __('No puedo firmar este contrato') }}</button>
            <form method="POST" action="{{ $declineUrl }}" id="ccDeclineForm" style="display:none;margin-top:.9rem">
                @csrf
                <label class="cc-label" for="ccDeclineReason">{{ __('Cuéntanos por qué (obligatorio):') }}</label>
                <textarea id="ccDeclineReason" name="reason" rows="3" maxlength="500" class="cc-decline-input"
                    placeholder="{{ __('Ej.: el monto no coincide con lo acordado.') }}"></textarea>
                <button type="submit" class="cc-decline-submit">{{ __('Confirmar que no firmaré') }}</button>
            </form>
        </div>
    @endif
</div>
@if($stage === 'sign')
    <script>
        function ccToggleDecline() {
            var f = document.getElementById('ccDeclineForm');
            var open = f.style.display !== 'none';
            f.style.display = open ? 'none' : 'block';
            var t = document.getElementById('ccDeclineReason');
            if (open) { t.removeAttribute('required'); } else { t.setAttribute('required', 'required'); t.focus(); }
        }
    </script>
    {{-- VISOR INLINE del paquete (pdf.js autoalojado, offline). Cada documento del sobre se sirve como
         PDF byte-intact; aquí solo se DIBUJA para leerlo sin salir. Si pdf.js no carga o un PDF no se
         puede dibujar, quedan los enlaces "Abrir en pestaña" (nunca se atrapa al firmante sin poder leer). --}}
    <script src="{{ asset('js/vendor/pdfjs/pdf.min.js') }}"></script>
    <script>
    (function () {
        if (!window.pdfjsLib) { return; }   // sin pdf.js → los enlaces "Abrir en pestaña" son el respaldo
        pdfjsLib.GlobalWorkerOptions.workerSrc = @json(asset('js/vendor/pdfjs/pdf.worker.min.js'));
        var FAIL = @json(__('No se pudo mostrar aquí. Usa "Abrir en pestaña".'));

        function drawPage(pdf, n, host, done) {
            pdf.getPage(n).then(function (page) {
                var maxW = Math.min(host.clientWidth - 24, 900);
                var base = page.getViewport({ scale: 1 });
                var vp = page.getViewport({ scale: maxW / base.width });
                var dpr = window.devicePixelRatio || 1;
                var wrap = document.createElement('div');
                wrap.className = 'ccpage';
                wrap.style.width = vp.width + 'px';
                wrap.style.height = vp.height + 'px';
                var c = document.createElement('canvas');
                c.width = Math.floor(vp.width * dpr);
                c.height = Math.floor(vp.height * dpr);
                c.style.width = vp.width + 'px';
                c.style.height = vp.height + 'px';
                wrap.appendChild(c);
                host.appendChild(wrap);
                page.render({
                    canvasContext: c.getContext('2d'),
                    viewport: vp,
                    transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null
                }).promise.then(done);
            });
        }

        document.querySelectorAll('.ccdoc__pages[data-pdf-src]').forEach(function (host) {
            var url = host.getAttribute('data-pdf-src');
            pdfjsLib.getDocument(url).promise.then(function (pdf) {
                host.innerHTML = '';
                var n = 1;
                (function next() {
                    if (n > pdf.numPages) { return; }
                    drawPage(pdf, n, host, function () { n++; next(); });
                })();
            }).catch(function () {
                host.innerHTML = '<div class="ccdoc__loading">' + FAIL + '</div>';
            });
        });
    })();
    </script>
@endif
@stack('scripts')
</body>
</html>
