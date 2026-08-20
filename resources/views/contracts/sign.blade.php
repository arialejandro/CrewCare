{{-- PÁGINA DE FIRMA (Paso C) · CEREMONIA DocuSign-like — el contratado firma sin sesión. Ve el
     PAQUETE COMPLETO (contrato + anexos + Hoja) ARMADO, ADOPTA su autógrafa UNA VEZ (dibuja / escribe
     / reúsa) y la ESTAMPA en cada etiqueta de firma. La ruta avanza sola. Todo fail-open: si pdf.js o
     un documento no cargan, quedan los enlaces "Abrir en pestaña" y nunca se atrapa al firmante. --}}
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
    .wrap { width: 100%; max-width: 720px; }
    .card { background: #111a2e; border: 1px solid #1f2b44; border-radius: 16px; padding: 1.4rem; margin-bottom: 1rem; }
    h1 { font-size: 1.2rem; margin: 0 0 .3rem; }
    h2 { font-size: 1rem; margin: 0 0 .5rem; }
    .muted { color: #9aa4b2; font-size: .88rem; }
    label.consent { display: flex; gap: .6rem; align-items: flex-start; font-size: .88rem; color: #cbd5e1; margin: 1rem 0; }
    /* El submit tiene su propia clase para NO aplastar los botones del pad de firma (type=button). */
    .cc-submit { width: 100%; padding: .8rem; border: 0; border-radius: 10px; background: #16a34a; color: #fff; font-weight: 700; font-size: 1.05rem; cursor: pointer; margin-top: 1rem; }
    .cc-submit:disabled { opacity: .5; cursor: not-allowed; }
    .ok { color: #86efac; } .warn { color: #fcd34d; }
    .err { background: #3b1220; border: 1px solid #7f1d3a; color: #fecdd3; padding: .6rem .8rem; border-radius: 10px; font-size: .85rem; margin-bottom: 1rem; }
    /* Base mínima para el pad de firma (esta página no carga Bootstrap). */
    .cc-label { display: block; font-size: .82rem; color: #cbd5e1; margin-bottom: .4rem; }
    .cc-sigpad .btn { width: auto; padding: .38rem .7rem; border: 1px solid #2a3a5c; border-radius: 8px; background: #16233f; color: #e5e7eb; font-size: .8rem; font-weight: 600; cursor: pointer; }
    .cc-sigpad .form-control { padding: .38rem .6rem; border: 1px solid #2a3a5c; border-radius: 8px; background: #0b1220; color: #e5e7eb; font-size: .85rem; }
    .cc-sigpad__savelbl { color: #9aa4b2; }
    /* ADOPTAR mi firma — paso 1 (tipo DocuSign "Adopt your signature"). */
    .cc-adopt__preview { display: none; align-items: center; gap: .9rem; flex-wrap: wrap; }
    .cc-adopt__preview img { height: 46px; max-width: 240px; background: #fff; border-radius: 8px; padding: 3px 8px; }
    .cc-adopt__badge { color: #86efac; font-size: .85rem; font-weight: 600; }
    .cc-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1rem; border: 0; border-radius: 10px; background: #1d4ed8; color: #fff; font-weight: 700; font-size: .95rem; cursor: pointer; }
    .cc-linkbtn { background: none; border: 0; color: #7dd3fc; font-size: .85rem; text-decoration: underline; cursor: pointer; padding: 0; }
    /* Rechazar: acción secundaria, subordinada a firmar. */
    .cc-decline-toggle { background: none; border: 0; color: #9aa4b2; font-size: .85rem; text-decoration: underline; cursor: pointer; padding: 0; }
    .cc-decline-toggle:hover { color: #cbd5e1; }
    .cc-decline-input { width: 100%; padding: .5rem .6rem; border: 1px solid #7f1d3a; border-radius: 8px; background: #0b1220; color: #e5e7eb; font-size: .9rem; font-family: inherit; }
    .cc-decline-submit { width: 100%; margin-top: .7rem; padding: .6rem; border: 1px solid #7f1d3a; border-radius: 10px; background: transparent; color: #fecdd3; font-weight: 700; font-size: .95rem; cursor: pointer; }
    .cc-decline-submit:hover { background: #3b1220; }
    /* Barra de progreso de firmas (DocuSign: "X de Y lugares"). */
    .ccbar { display: flex; align-items: center; justify-content: space-between; gap: .6rem; flex-wrap: wrap; padding: .6rem .8rem; margin-bottom: .8rem; border: 1px solid #1f2b44; border-radius: 10px; background: #0e1526; font-size: .88rem; transition: background .2s, border-color .2s; position: sticky; top: .4rem; z-index: 5; }
    .ccbar[data-done="1"] { border-color: #166534; background: #0e2417; }
    .ccbar[data-warn="1"] { border-color: #7f1d3a; background: #3b1220; }
    .ccbar b { color: #fff; }
    .ccbar__actions { display: flex; gap: .5rem; flex-wrap: wrap; }
    .ccbar__btn { border: 0; color: #fff; font-weight: 700; font-size: .82rem; border-radius: 8px; padding: .44rem .8rem; cursor: pointer; white-space: nowrap; background: #1d4ed8; }
    .ccbar__btn--all { background: #16a34a; }
    /* Documento del paquete. */
    .ccdoc { border: 1px solid #1f2b44; border-radius: 12px; overflow: hidden; margin-bottom: .9rem; }
    .ccdoc__h { display: flex; justify-content: space-between; align-items: center; gap: .6rem; padding: .55rem .8rem; background: #0e1526; border-bottom: 1px solid #1f2b44; }
    .ccdoc__name { font-weight: 600; font-size: .9rem; }
    .ccdoc__tagcount { color: #fcd34d; font-size: .78rem; font-weight: 600; }
    .ccdoc__tagcount[data-done="1"] { color: #86efac; }
    .ccdoc__open { color: #7dd3fc; text-decoration: none; font-size: .8rem; font-weight: 600; white-space: nowrap; }
    .ccdoc__pages { max-height: 70vh; overflow: auto; padding: .8rem; background: #525659; -webkit-overflow-scrolling: touch; }
    .ccdoc__loading { color: #e5e7eb; text-align: center; padding: 1.4rem 0; font-size: .85rem; }
    .ccpage { margin: 0 auto .7rem; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.4); }
    .ccpage:last-child { margin-bottom: 0; }
    /* El contrato HTML armado se embebe en un iframe (documento aislado, con sus propios estilos). */
    .ccframe { width: 100%; border: 0; background: #fff; display: block; }
    .ccframe-wrap { max-height: 70vh; overflow: auto; background: #525659; padding: .8rem; }
    /* Etiqueta de firma sobre PDF (coordenadas). */
    .ccmk-ov { position: absolute; inset: 0; }
    .ccmk { position: absolute; box-sizing: border-box; min-height: 30px; border: 2px dashed #f59e0b; background: rgba(245,158,11,.20); color: #7c2d12; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 2px; overflow: hidden; }
    .ccmk:hover { background: rgba(245,158,11,.34); }
    .ccmk.pulse { animation: ccpulse 1.4s ease-out; }
    .ccmk.applied { border: 1.5px solid #16a34a; background: rgba(255,255,255,.95); cursor: default; }
    .ccmk.applied img { max-width: 100%; max-height: 100%; object-fit: contain; }
    @keyframes ccpulse { 0% { box-shadow: 0 0 0 0 rgba(245,158,11,.6); } 100% { box-shadow: 0 0 0 14px rgba(245,158,11,0); } }
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

        {{-- PASO 1 · ADOPTA TU FIRMA (una vez; se estampa en todos los lugares). --}}
        <div class="card" id="ccAdopt">
            <h2>{{ __('1 · Adopta tu firma') }}</h2>
            <p class="muted" style="margin-top:0">{{ __('Dibújala, escríbela o reúsa la guardada. La usarás en todo el paquete.') }}</p>
            <div id="ccAdoptPad">
                @include('componentes._signature-pad', [
                    'name'    => 'adopt_signature',
                    'label'   => null,
                    'adopted' => $adopted ?? null,
                ])
                <button type="button" class="cc-btn" id="ccAdoptBtn" style="margin-top:.7rem">{{ __('Adoptar mi firma') }}</button>
            </div>
            <div class="cc-adopt__preview" id="ccAdoptPreview">
                <span class="cc-adopt__badge">{{ __('✓ Firma lista') }}</span>
                <img id="ccAdoptImg" src="" alt="{{ __('Tu firma') }}">
                <button type="button" class="cc-linkbtn" id="ccAdoptChange">{{ __('Cambiar') }}</button>
            </div>
        </div>

        {{-- PASO 2 · EL PAQUETE — cada documento armado, con TUS lugares de firma. --}}
        <div class="card">
            <h2>{{ __('2 · Revisa y firma tu paquete') }}</h2>
            <div class="ccbar" id="ccBar" data-done="0">
                <span>{{ __('Firmas colocadas') }}: <b id="ccCount">0 / 0</b></span>
                <span class="ccbar__actions">
                    <button type="button" class="ccbar__btn" id="ccNext">{{ __('Ir al siguiente') }}</button>
                    <button type="button" class="ccbar__btn ccbar__btn--all" id="ccApplyAll">{{ __('Firmar en todos') }}</button>
                </span>
            </div>

            @forelse($ceremony as $di => $doc)
                <section class="ccdoc" data-doc="{{ $di }}">
                    <div class="ccdoc__h">
                        <span class="ccdoc__name">{{ $doc['name'] ?? __('Documento') }}</span>
                        <span style="display:flex;gap:.8rem;align-items:center">
                            <span class="ccdoc__tagcount" data-doc-count="{{ $di }}"></span>
                            @if(($doc['mode'] ?? '') === 'pdf' && !empty($doc['url']))
                                <a class="ccdoc__open" href="{{ $doc['url'] }}" target="_blank" rel="noopener">{{ __('Abrir en pestaña') }}</a>
                            @endif
                        </span>
                    </div>
                    @if(($doc['mode'] ?? '') === 'html')
                        <div class="ccframe-wrap">
                            <iframe class="ccframe" data-doc="{{ $di }}" data-anchors='@json($doc['anchors'] ?? [])'
                                    srcdoc="{{ $doc['html'] ?? '' }}" title="{{ $doc['name'] ?? __('Documento') }}"></iframe>
                        </div>
                    @else
                        <div class="ccdoc__pages" data-doc="{{ $di }}" data-pdf-src="{{ $doc['url'] ?? '' }}" data-tags='@json($doc['tags'] ?? [])'>
                            <div class="ccdoc__loading">{{ __('Cargando documento…') }}</div>
                        </div>
                    @endif
                </section>
            @empty
                <div class="muted">{{ __('No hay documentos para mostrar.') }}</div>
            @endforelse
        </div>

        {{-- PASO 3 · FINALIZAR — consiente (una vez) y firma el paquete. --}}
        <div class="card" id="ccSignBox">
            @if(session('error'))<div class="err">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $signUrl }}" id="ccSignForm">
                @csrf
                @if($needsConsent)
                    <label class="consent">
                        <input type="checkbox" name="consent" value="1" required>
                        <span>{{ __('Acepto firmar electrónicamente. Reconozco que mi firma electrónica tiene la misma validez que la autógrafa (Cód. de Comercio 89 y 89 Bis; CCF 1811).') }}</span>
                    </label>
                @endif
                <input type="hidden" name="signature_image" id="ccSigInput" value="">
                <input type="hidden" name="save_signature" id="ccSaveFlag" value="0">
                <button type="submit" class="cc-submit" id="ccSubmit">{{ __('Finalizar y firmar') }}</button>
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
    <script src="{{ asset('js/vendor/pdfjs/pdf.min.js') }}"></script>
    <script>
    (function () {
        'use strict';
        var T = {
            adoptFirst: @json(__('Primero adopta tu firma arriba.')),
            sign:       @json(__('Firmar aquí')),
            fail:       @json(__('No se pudo mostrar aquí. Usa "Abrir en pestaña".')),
        };
        var WORKER = @json(asset('js/vendor/pdfjs/pdf.worker.min.js'));
        if (window.pdfjsLib) { pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER; }

        // ── Estado central de la ceremonia ──────────────────────────────────────
        var CC = {
            adopted: '',                 // dataURL de mi autógrafa (tras adoptar)
            tags: [],                    // {docIdx, applied, apply(), focus()}
            docTotal: 0,                 // documentos a preparar (iframe/pdf)
            docReady: 0,                 // documentos ya listos (o fallidos)
        };
        var submitBtn = document.getElementById('ccSubmit');
        var bar       = document.getElementById('ccBar');
        var countEl   = document.getElementById('ccCount');
        var sigInput  = document.getElementById('ccSigInput');
        if (submitBtn) { submitBtn.disabled = true; }   // la ceremonia gobierna; sin JS quedaría habilitado

        function docCountEl(idx) { return document.querySelector('.ccdoc__tagcount[data-doc-count="' + idx + '"]'); }

        function refresh() {
            var applied = 0;
            CC.tags.forEach(function (t) { if (t.applied) applied++; });
            if (countEl) { countEl.textContent = applied + ' / ' + CC.tags.length; }
            // conteo por documento
            var perDoc = {};
            CC.tags.forEach(function (t) {
                perDoc[t.docIdx] = perDoc[t.docIdx] || { a: 0, n: 0 };
                perDoc[t.docIdx].n++; if (t.applied) perDoc[t.docIdx].a++;
            });
            Object.keys(perDoc).forEach(function (idx) {
                var el = docCountEl(idx); if (!el) return;
                var d = perDoc[idx];
                el.textContent = d.a + '/' + d.n + ' ' + (d.a >= d.n ? '✓' : '');
                el.setAttribute('data-done', d.a >= d.n ? '1' : '0');
            });
            var ready = CC.docReady >= CC.docTotal;
            var done  = ready && (CC.tags.length === 0 || applied >= CC.tags.length);
            if (submitBtn) { submitBtn.disabled = !(CC.adopted && done); }
            if (bar) { bar.setAttribute('data-done', (CC.tags.length && applied >= CC.tags.length) ? '1' : '0'); }
        }

        function docDone() { CC.docReady++; refresh(); }

        function warnAdopt() {
            var pad = document.getElementById('ccAdopt');
            if (pad) { pad.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            if (bar) { bar.setAttribute('data-warn', '1'); setTimeout(function () { bar.removeAttribute('data-warn'); }, 2200); }
        }

        function register(tag) { tag.applied = false; CC.tags.push(tag); }

        function applyOne(tag) {
            if (!CC.adopted) { warnAdopt(); return; }
            if (!tag.applied) { tag.applied = true; tag.paint(CC.adopted); refresh(); }
        }

        function nextTag() {
            var t = null;
            for (var i = 0; i < CC.tags.length; i++) { if (!CC.tags[i].applied) { t = CC.tags[i]; break; } }
            if (t) { t.focus(); }
        }

        // ── ADOPTAR mi firma ─────────────────────────────────────────────────────
        (function adopt() {
            var pad      = document.querySelector('#ccAdoptPad .cc-sigpad');
            var dataEl   = pad ? pad.querySelector('.cc-sigpad__data') : null;
            var saveEl   = pad ? pad.querySelector('.cc-sigpad__saveflag') : null;
            var btn      = document.getElementById('ccAdoptBtn');
            var padBox   = document.getElementById('ccAdoptPad');
            var preview  = document.getElementById('ccAdoptPreview');
            var prevImg  = document.getElementById('ccAdoptImg');
            var change   = document.getElementById('ccAdoptChange');
            var saveFlag = document.getElementById('ccSaveFlag');

            function setAdopted(url) {
                CC.adopted = url || '';
                if (sigInput) { sigInput.value = CC.adopted; }
                if (saveFlag && saveEl) { saveFlag.value = saveEl.value || '0'; }
                if (prevImg) { prevImg.src = CC.adopted; }
                if (padBox)  { padBox.style.display = 'none'; }
                if (preview) { preview.style.display = 'flex'; }
                refresh();
            }
            if (btn) {
                btn.addEventListener('click', function () {
                    var url = dataEl ? (dataEl.value || '') : '';
                    if (!url || url.length < 100) { warnAdopt(); return; }
                    setAdopted(url);
                });
            }
            if (change) {
                change.addEventListener('click', function () {
                    if (padBox)  { padBox.style.display = ''; }
                    if (preview) { preview.style.display = 'none'; }
                });
            }
        })();

        // ── Barra: acciones ──────────────────────────────────────────────────────
        var applyAllBtn = document.getElementById('ccApplyAll');
        if (applyAllBtn) {
            applyAllBtn.addEventListener('click', function () {
                if (!CC.adopted) { warnAdopt(); return; }
                CC.tags.forEach(function (t) { if (!t.applied) { t.applied = true; t.paint(CC.adopted); } });
                refresh();
            });
        }
        var nextBtn = document.getElementById('ccNext');
        if (nextBtn) { nextBtn.addEventListener('click', function () { if (!CC.adopted) { warnAdopt(); return; } nextTag(); }); }

        // ── Documentos HTML (iframe): mis anclas pendientes → etiquetas clicables ──
        function initHtmlDoc(iframe) {
            var docIdx = iframe.getAttribute('data-doc');
            var mine = [];
            try { mine = JSON.parse(iframe.getAttribute('data-anchors') || '[]'); } catch (e) { mine = []; }

            function ready() {
                try {
                    var idoc = iframe.contentDocument;
                    if (!idoc) { docDone(); return; }
                    // auto-alto: mostrar el documento completo (el scroll lo lleva el contenedor)
                    var h = Math.max(idoc.body ? idoc.body.scrollHeight : 0, idoc.documentElement ? idoc.documentElement.scrollHeight : 0);
                    if (h > 0) { iframe.style.height = (h + 24) + 'px'; }

                    // estilos de la etiqueta DENTRO del iframe
                    var st = idoc.createElement('style');
                    st.textContent =
                        '.cc-tag{cursor:pointer;outline:2px dashed #d97706;outline-offset:2px;background:rgba(245,158,11,.22)!important;position:relative}' +
                        '.cc-tag:hover{background:rgba(245,158,11,.36)!important}' +
                        '.cc-tag .cc-tag-hint{position:absolute;top:-14px;left:0;font-size:8px;font-weight:700;color:#7c2d12;background:#fbbf24;border-radius:3px;padding:0 4px;font-style:normal}' +
                        '.cc-tag.applied{outline-color:#16a34a;background:rgba(255,255,255,.9)!important}' +
                        '.cc-tag.applied img{max-height:34px;max-width:170px;display:inline-block;mix-blend-mode:multiply}';
                    (idoc.head || idoc.body).appendChild(st);

                    mine.forEach(function (key) {
                        var boxes = idoc.querySelectorAll('[data-anchor="' + key + '"]');
                        Array.prototype.forEach.call(boxes, function (box) {
                            box.classList.add('cc-tag');
                            var hint = idoc.createElement('span'); hint.className = 'cc-tag-hint'; hint.textContent = T.sign;
                            box.appendChild(hint);
                            var tag = {
                                docIdx: docIdx,
                                paint: function (url) {
                                    box.classList.add('applied');
                                    box.innerHTML = '<img src="' + url + '" alt="">';
                                },
                                focus: function () {
                                    try { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
                                    var wrap = iframe.closest('.ccframe-wrap');
                                    if (wrap) { wrap.scrollTop = Math.max(0, box.offsetTop - 60); }
                                },
                            };
                            box.addEventListener('click', function () { applyOne(tag); });
                            register(tag);
                        });
                    });
                } catch (e) { /* cross-doc raro → se queda como lectura */ }
                docDone();
            }

            if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') { ready(); }
            else { iframe.addEventListener('load', ready); iframe.addEventListener('error', docDone); }
        }

        // ── Documentos PDF (pdf.js): render + etiquetas por coordenadas ────────────
        function drawPage(pdf, n, host, done) {
            pdf.getPage(n).then(function (page) {
                var maxW = Math.min(host.clientWidth - 24, 900);
                var base = page.getViewport({ scale: 1 });
                var vp = page.getViewport({ scale: maxW / base.width });
                var dpr = window.devicePixelRatio || 1;
                var wrap = document.createElement('div');
                wrap.className = 'ccpage';
                wrap.style.position = 'relative';
                wrap.style.width = vp.width + 'px';
                wrap.style.height = vp.height + 'px';
                var c = document.createElement('canvas');
                c.width = Math.floor(vp.width * dpr); c.height = Math.floor(vp.height * dpr);
                c.style.width = vp.width + 'px'; c.style.height = vp.height + 'px';
                wrap.appendChild(c);
                var ov = document.createElement('div'); ov.className = 'ccmk-ov';
                wrap.appendChild(ov);
                host.appendChild(wrap);
                page.render({
                    canvasContext: c.getContext('2d'), viewport: vp,
                    transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null,
                }).promise.then(function () { done(ov); });
            }).catch(function () { done(null); });
        }

        function initPdfDoc(host) {
            var docIdx = host.getAttribute('data-doc');
            var url = host.getAttribute('data-pdf-src');
            var tags = [];
            try { tags = JSON.parse(host.getAttribute('data-tags') || '[]'); } catch (e) { tags = []; }
            if (!window.pdfjsLib || !url) { host.innerHTML = '<div class="ccdoc__loading">' + T.fail + '</div>'; docDone(); return; }

            var overlays = {};
            pdfjsLib.getDocument(url).promise.then(function (pdf) {
                host.innerHTML = '';
                var n = 1;
                (function next() {
                    if (n > pdf.numPages) { placeTags(); return; }
                    var cur = n;
                    drawPage(pdf, cur, host, function (ov) { overlays[cur] = ov; n++; next(); });
                })();
            }).catch(function () {
                host.innerHTML = '<div class="ccdoc__loading">' + T.fail + '</div>';
                docDone();
            });

            function placeTags() {
                tags.forEach(function (f) {
                    var ov = overlays[parseInt(f.page, 10)];
                    if (!ov) { return; }
                    var mk = document.createElement('button');
                    mk.type = 'button'; mk.className = 'ccmk';
                    mk.style.left = f.x_pct + '%'; mk.style.top = f.y_pct + '%'; mk.style.width = f.w_pct + '%';
                    mk.textContent = T.sign;
                    ov.appendChild(mk);
                    var tag = {
                        docIdx: docIdx,
                        paint: function (url2) { mk.classList.add('applied'); mk.textContent = ''; var img = document.createElement('img'); img.src = url2; mk.appendChild(img); },
                        focus: function () { mk.scrollIntoView({ behavior: 'smooth', block: 'center' }); mk.classList.add('pulse'); setTimeout(function () { mk.classList.remove('pulse'); }, 1400); },
                    };
                    mk.addEventListener('click', function () { applyOne(tag); });
                    register(tag);
                });
                docDone();
            }
        }

        // ── Arranque ──────────────────────────────────────────────────────────────
        var htmlFrames = document.querySelectorAll('iframe.ccframe');
        var pdfHosts   = document.querySelectorAll('.ccdoc__pages[data-pdf-src]');
        CC.docTotal = htmlFrames.length + pdfHosts.length;
        if (CC.docTotal === 0) { CC.docTotal = 1; docDone(); }   // sin documentos → no atrapar
        Array.prototype.forEach.call(htmlFrames, initHtmlDoc);
        Array.prototype.forEach.call(pdfHosts, initPdfDoc);

        // Guarda dura del submit: nunca enviar sin autógrafa (el servidor también lo valida).
        var form = document.getElementById('ccSignForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!CC.adopted) { e.preventDefault(); warnAdopt(); }
                else if (sigInput) { sigInput.value = CC.adopted; }
            });
        }
        refresh();
    })();
    </script>
@endif
@stack('scripts')
</body>
</html>
