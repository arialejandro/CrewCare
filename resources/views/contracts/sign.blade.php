{{-- PÁGINA DE FIRMA (Paso C) · CEREMONIA tipo DocuSign — el contratado firma sin sesión. Ve el
     PAQUETE COMPLETO (contrato + anexos + Hoja) ARMADO, ADOPTA su autógrafa UNA VEZ y la ESTAMPA en
     cada etiqueta; cada firma muestra su HASH (sello). Tema claro, profesional. Todo fail-open: si
     pdf.js o un documento no cargan, quedan los enlaces "Abrir en pestaña" y nunca se atrapa. --}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Firma de contrato') }}</title>
{{-- Letras manuscritas para la galería de estilos (como DocuSign); con respaldo a fuentes del sistema. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&family=Caveat:wght@600&family=Great+Vibes&family=Sacramento&family=Satisfy&display=swap" rel="stylesheet">
<style>
    :root{
        --canvas:#eaedf2; --page:#fff; --ink:#18212f; --muted:#5f6b7c; --faint:#8b95a4;
        --line:#e3e7ee; --line2:#eef1f5; --brand:#ff0046; --nav:#2563eb; --nav-bg:#eef3ff;
        --tab:#ffcf4a; --tab-edge:#e3a600; --tab-ink:#6a4a00; --ok:#12a150; --ok-bg:#e7f6ee;
        --seal:#4b53d6; --shadow-page:0 1px 2px rgba(16,24,40,.06),0 10px 26px rgba(16,24,40,.10);
        --ease:cubic-bezier(.23,1,.32,1); color-scheme:light;
    }
    *{box-sizing:border-box}
    html,body{margin:0}
    body{background:var(--canvas);color:var(--ink);
        font-family:"Segoe UI",system-ui,-apple-system,Roboto,Helvetica,Arial,sans-serif;
        font-size:15px;line-height:1.45;-webkit-font-smoothing:antialiased;}
    button{font-family:inherit}
    .center{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px 28px;max-width:460px;box-shadow:var(--shadow-page)}
    .card h1{font-size:1.25rem;margin:0 0 .4rem}
    .muted{color:var(--muted);font-size:.92rem}
    .ok{color:var(--ok)} .warn{color:#b7791f}
    .card--done{text-align:center;max-width:440px}
    .card--done h1{margin-top:0}
    .donebadge{width:62px;height:62px;margin:2px auto 14px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--ok-bg);color:var(--ok)}
    .donebadge--warn{background:#fdf3e2;color:#b7791f}
    .donebadge svg{width:32px;height:32px}
    .doneacts{display:flex;flex-direction:column;gap:10px;align-items:center;margin-top:22px}
    .doneacts .btn{width:100%;max-width:260px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
    .doneacts .note{margin:2px 0 0;font-size:.85rem}
    .doneacts .closehint{margin:2px 0 0;font-size:.85rem;color:var(--faint)}
    .btn{border:0;border-radius:9px;padding:10px 16px;font-size:14px;font-weight:700;cursor:pointer;
        transition:transform .12s var(--ease),background .18s var(--ease)}
    .btn:active{transform:scale(.97)}
    .btn--finish{background:var(--ok);color:#fff}
    .btn--finish:disabled{background:#dfe4ea;color:#9aa4b2;cursor:not-allowed}
    .btn--dark{background:#111827;color:#fff}
    .btn--ghost{background:#f3f5f9;color:var(--ink)}
    .btn--ghost:hover{background:#eaeef4}

    /* ── Toolbar ─────────────────────────────────────────── */
    .tbar{position:fixed;top:0;left:0;right:0;height:60px;z-index:40;background:#fff;
        border-bottom:1px solid var(--line);display:flex;align-items:center;gap:14px;padding:0 18px;}
    .tbar::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:var(--brand)}
    .brand{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:-.2px;min-width:0}
    .brand .logo{width:26px;height:26px;border-radius:7px;background:var(--brand);color:#fff;display:grid;place-items:center;font-size:15px;font-weight:800;flex:0 0 auto}
    .brand b{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .brand small{display:block;font-weight:500;font-size:11px;color:var(--faint);letter-spacing:.3px;text-transform:uppercase}
    .tbar .grow{flex:1}
    .prog{display:flex;align-items:center;gap:10px;min-width:150px}
    .prog__bar{flex:1;height:7px;border-radius:99px;background:#e9edf3;overflow:hidden}
    .prog__fill{height:100%;width:0;background:var(--ok);border-radius:99px;transition:width .35s var(--ease)}
    .prog__txt{font-size:12.5px;color:var(--muted);font-weight:600;white-space:nowrap;font-variant-numeric:tabular-nums}

    /* ── Doc tabs ────────────────────────────────────────── */
    .docs{position:fixed;top:60px;left:0;right:0;z-index:35;background:#fbfcfd;border-bottom:1px solid var(--line);
        display:flex;gap:4px;padding:8px 16px;overflow-x:auto}
    .doctab{display:inline-flex;align-items:center;gap:8px;border:1px solid transparent;background:transparent;
        color:var(--muted);font-size:13px;font-weight:600;padding:7px 12px;border-radius:8px;cursor:pointer;white-space:nowrap;
        transition:background .15s var(--ease),color .15s var(--ease)}
    .doctab:hover{background:#eef2f7;color:var(--ink)}
    .doctab[aria-current="true"]{background:var(--nav-bg);color:var(--nav)}
    .doctab .dot{width:16px;height:16px;border-radius:99px;border:2px solid #cfd6e0;display:grid;place-items:center;font-size:10px;color:#fff}
    .doctab[data-done="1"] .dot{background:var(--ok);border-color:var(--ok)}
    .doctab .pend{background:#fff4d6;color:#8a6400;border-radius:99px;font-size:11px;font-weight:800;padding:1px 7px;min-width:18px;text-align:center}
    .doctab[data-done="1"] .pend,.doctab .pend:empty{display:none}

    /* ── Stage + pages ───────────────────────────────────── */
    .stage{padding:132px 14px 120px;max-width:900px;margin:0 auto}
    .docblock{margin:0 0 28px}
    .dochead{display:flex;align-items:center;gap:10px;margin:0 4px 10px;color:var(--muted);font-size:13px;font-weight:700}
    .dochead .kind{background:#eef2f7;color:#586173;border-radius:6px;font-size:11px;padding:2px 8px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
    .dochead .open{margin-left:auto;color:var(--nav);text-decoration:none;font-size:12.5px;font-weight:600}
    /* documento embebido (HTML armado) — a alto completo; el SCROLL es de la página (así "Siguiente"
       lleva a la etiqueta exacta, no solo al documento). */
    .frameShell{background:#525659;border-radius:10px;padding:16px}
    .ccframe{width:100%;border:0;background:#fff;display:block;border-radius:4px;box-shadow:var(--shadow-page)}
    /* documento PDF (pdf.js) — también a alto completo. */
    .pdfShell{background:#525659;border-radius:10px;padding:14px}
    .pdfShell .loading{color:#e5e7eb;text-align:center;padding:1.6rem 0;font-size:.86rem}
    .ccpage{position:relative;margin:0 auto 14px;background:#fff;box-shadow:var(--shadow-page)}
    .ccpage:last-child{margin-bottom:0}
    .ccmk-ov{position:absolute;inset:0}

    /* ── Etiqueta de firma (tab) ─────────────────────────── */
    .ccmk{position:absolute;display:inline-flex;align-items:center;gap:6px;cursor:pointer;
        background:var(--tab);border:1px solid var(--tab-edge);color:var(--tab-ink);
        border-radius:5px;padding:8px 12px;font-size:12.5px;font-weight:800;letter-spacing:.2px;
        box-shadow:0 2px 5px rgba(180,120,0,.28);white-space:nowrap;z-index:3;
        transition:transform .12s var(--ease),background .15s var(--ease)}
    .ccmk:hover{background:#ffd968;transform:translateY(-1px)}
    .ccmk:active{transform:scale(.97)}
    /* Rúbrica (iniciales): etiqueta compacta y en tono violeta para distinguirla de la firma. */
    .ccmk.small{padding:5px 8px;font-size:11px;background:#e9defb;border-color:#b794f4;color:#5b21b6}
    .ccmk.small:hover{background:#ddd0f7}
    .ccmk.pulse{animation:pulse 1.2s var(--ease)}
    @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(234,166,0,.55)}100%{box-shadow:0 0 0 16px rgba(234,166,0,0)}}
    .ccmk.applied{background:#fff;border:1px solid #d8dcff;box-shadow:0 1px 3px rgba(16,24,40,.1);cursor:default;
        color:var(--ink);padding:6px 8px;width:auto;max-width:250px;white-space:normal}
    /* sello estampado (Firmado por: + hash) */
    .stamp{display:flex;align-items:stretch;gap:6px;text-align:left}
    .stamp__bar{width:5px;border:1.5px solid var(--seal);border-right:0;border-radius:4px 0 0 4px;flex:0 0 auto}
    .stamp__body{display:flex;flex-direction:column;min-width:0}
    .stamp__lbl{font-size:8px;color:#6b7482;letter-spacing:.3px;font-weight:700}
    .stamp img{height:30px;max-width:170px;object-fit:contain;mix-blend-mode:multiply;display:block}
    .stamp__hash{font-size:8px;color:var(--ok);font-weight:700;max-width:200px;line-height:1.25;margin-top:1px}
    .stamp__hash code{font-family:"SFMono-Regular",Consolas,monospace;font-size:8px;color:#5b6472;font-weight:600;word-break:break-all}
    .ccmk.small.applied .stamp img{height:22px;max-width:100px}
    .ccmk.small.applied .stamp__lbl{display:none}

    /* ── Botón flotante "Comenzar" ───────────────────────── */
    .start{position:fixed;left:20px;top:50%;transform:translateY(-50%);z-index:30;
        background:var(--tab);border:1px solid var(--tab-edge);color:var(--tab-ink);
        border-radius:10px;padding:12px 15px;font-weight:800;font-size:14px;cursor:pointer;
        box-shadow:0 6px 18px rgba(180,120,0,.3);display:flex;align-items:center;gap:8px;
        transition:transform .12s var(--ease),opacity .25s var(--ease)}
    .start:active{transform:translateY(-50%) scale(.96)}
    .start.hide{opacity:0;pointer-events:none}

    /* ── Modal adopta ────────────────────────────────────── */
    .scrim{position:fixed;inset:0;z-index:60;background:rgba(15,22,34,.5);display:none;align-items:center;justify-content:center;padding:18px;opacity:0;transition:opacity .2s var(--ease)}
    .scrim.open{display:flex;opacity:1}
    .modal{background:#fff;border-radius:16px;width:100%;max-width:640px;overflow:hidden;transform:scale(.96);opacity:0;transition:transform .22s var(--ease),opacity .22s var(--ease);box-shadow:0 30px 80px rgba(10,16,28,.4)}
    .scrim.open .modal{transform:scale(1);opacity:1}
    .modal__h{padding:20px 24px 4px}
    .modal__h h3{margin:0;font-size:18px;font-weight:700}
    .modal__h p{margin:4px 0 0;color:var(--muted);font-size:13px}
    .modal__b{padding:14px 24px 4px}
    .field{margin-bottom:14px}
    .field label{display:block;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
    .field input[type=text]{width:100%;height:42px;border:1px solid var(--line);border-radius:10px;padding:0 14px;font-size:15px;color:var(--ink);background:#fbfcfd}
    .field input[type=text]:focus{outline:none;border-color:var(--nav);box-shadow:0 0 0 3px var(--nav-bg)}
    .seg{display:inline-flex;background:#f0f3f7;border-radius:10px;padding:3px;gap:2px;margin-bottom:12px}
    .seg button{border:0;background:transparent;color:var(--muted);font-size:13px;font-weight:700;padding:7px 15px;border-radius:8px;cursor:pointer}
    .seg button[aria-selected="true"]{background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(16,24,40,.12)}
    .sigbox{border:1px dashed #cfd6e0;border-radius:12px;background:#fffdf5;height:170px;position:relative;overflow:hidden}
    .sigbox canvas{position:absolute;inset:0;width:100%;height:100%;touch-action:none;cursor:crosshair}
    .sigbox .base{position:absolute;left:8%;right:8%;bottom:34%;border-bottom:1px solid #e2c98a}
    .sigbox .hint{position:absolute;left:14px;bottom:12px;font-size:11px;color:#b7a56a;font-weight:700;letter-spacing:.5px}
    .styled{position:absolute;inset:0;display:none;align-items:center;justify-content:center;font-family:"Segoe Script","Brush Script MT","Snell Roundhand",cursive;font-size:50px;color:#16233b;padding:0 20px;text-align:center}
    /* dos marcas: Firma (grande) + Rúbrica (marca personal) lado a lado */
    .markrow{display:flex;gap:12px;align-items:flex-start}
    .markcol{flex:1 1 auto;min-width:0}
    .markcol--rub{flex:0 0 42%}
    .marklbl{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin:0 0 5px}
    .marklbl small{font-weight:600;text-transform:none;letter-spacing:0;color:var(--faint);font-size:10.5px}
    .sigbox--rub{height:170px}
    .sigbox--rub .styled{font-size:38px}
    .usefirma{display:flex;gap:7px;align-items:center;font-size:11.5px;color:var(--muted);margin-top:7px;cursor:pointer}
    #rubBox.off{opacity:.35;pointer-events:none;filter:grayscale(1)}
    @media(max-width:560px){.markrow{flex-direction:column}.markcol,.markcol--rub{flex:1 1 auto;width:100%}}
    /* DocuSign: Nombre + Iniciales arriba; pestañas ELEGIR / DIBUJAR; galería de estilos */
    .namerow{display:flex;gap:12px}
    .namerow .field{flex:1;margin-bottom:6px}
    .seg2{display:flex;gap:22px;border-bottom:1px solid var(--line);margin:6px 0 0;padding:0}
    .seg2 button{background:none;border:0;border-bottom:2px solid transparent;color:var(--muted);font-size:12.5px;font-weight:700;letter-spacing:.6px;padding:9px 2px;cursor:pointer}
    .seg2 button[aria-selected="true"]{color:var(--nav);border-bottom-color:var(--nav)}
    .fpane{padding-top:12px}
    .stylelist{max-height:250px;overflow:auto;border:1px solid var(--line);border-radius:10px}
    .styleopt{display:flex;align-items:stretch;gap:12px;padding:11px 14px;border-bottom:1px solid var(--line2);cursor:pointer}
    .styleopt:last-child{border-bottom:0}
    .styleopt:hover{background:#f7f9fb}
    .styleopt.sel{background:var(--nav-bg)}
    .styleopt input{margin:0;align-self:center;flex:0 0 auto}
    .styleopt .sbrk{flex:1 1 auto;display:flex;flex-direction:column;justify-content:center;min-width:0;padding-left:9px;border-left:2px solid #4b53d6}
    .styleopt .sbrk--ini{flex:0 0 92px}
    .styleopt .sblab{font-size:8px;font-weight:800;color:#6b7482;letter-spacing:.4px;text-transform:uppercase;margin-bottom:2px}
    .styleopt .sname{font-size:24px;color:#16233b;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .styleopt .sini{font-size:20px;color:#16233b;line-height:1.1}
    .reuse{margin:10px 2px 0;font-size:12.5px}
    .reuse a{color:var(--nav);cursor:pointer;text-decoration:underline}
    .consent{display:flex;gap:9px;align-items:flex-start;font-size:12px;color:#546070;padding:12px 24px 2px;line-height:1.5}
    .disc{font-size:11px;color:var(--faint);padding:6px 24px 4px;line-height:1.5}
    .modal__f{display:flex;align-items:center;gap:10px;padding:14px 24px 22px;border-top:1px solid var(--line2);margin-top:12px}
    .modal__f .grow{flex:1}
    .link{background:none;border:0;color:var(--muted);font-size:13px;text-decoration:underline;cursor:pointer;padding:0}
    .err{background:#fdecef;border:1px solid #f6c2ce;color:#a01235;padding:.6rem .8rem;border-radius:10px;font-size:.85rem;margin:0 0 12px}

    /* Rechazar */
    .decline{max-width:900px;margin:0 auto;padding:0 14px 40px;text-align:center}
    .decline__toggle{background:none;border:0;color:var(--muted);font-size:13px;text-decoration:underline;cursor:pointer}
    .decline__box{display:none;max-width:460px;margin:12px auto 0;text-align:left;background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px}
    .decline__box textarea{width:100%;border:1px solid #f0c4d0;border-radius:10px;padding:.6rem;font-family:inherit;font-size:.92rem;min-height:76px}
    .decline__submit{width:100%;margin-top:.7rem;padding:.6rem;border:1px solid #f0c4d0;border-radius:10px;background:#fff;color:#a01235;font-weight:700;cursor:pointer}
    @media(max-width:640px){.start{display:none}.stage{padding-top:146px}.brand small{display:none}}
</style>
@stack('styles')
</head>
<body>
@if($stage === 'done')
    @php
        $isWarn    = $envelope->isDeclined() || $envelope->isExpired() || $envelope->isCancelled();
        $homeUrl   = auth()->check() ? route('home') : null;
        $verifyUrl = (! $isWarn && $envelope->isCompleted() && $envelope->uuid)
            ? route('seal.verify', ['tipo' => 'cenv', 'uuid' => $envelope->uuid]) : null;
    @endphp
    <div class="center"><div class="card card--done">
        @if($envelope->isDeclined())
            <div class="donebadge donebadge--warn" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></div>
            <h1 class="warn">{{ __('Contrato rechazado') }}</h1>
            <p class="muted">{{ __('Registramos que no se firmará este contrato. Producción se encargará del siguiente paso.') }}</p>
        @elseif($envelope->isExpired())
            <div class="donebadge donebadge--warn" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
            <h1 class="warn">{{ __('Sobre vencido') }}</h1>
            <p class="muted">{{ __('El plazo para firmar este contrato ya pasó. Avísale a producción si aún necesitas firmarlo.') }}</p>
        @elseif($envelope->isCancelled())
            <div class="donebadge donebadge--warn" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg></div>
            <h1 class="warn">{{ __('Sobre anulado') }}</h1>
            <p class="muted">{{ __('Este contrato fue anulado por producción. No hay nada que firmar aquí.') }}</p>
        @else
            <div class="donebadge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
            <h1 class="ok">{{ __('¡Listo! Contrato firmado') }}</h1>
            <p class="muted">{{ __('Tu firma quedó estampada y sellada con su hash. Recibirás una copia certificada por correo.') }}</p>
            @unless($envelope->isCompleted())
                <p class="muted note">{{ __('Faltan otras firmas del sobre; te avisaremos cuando el contrato quede completo.') }}</p>
            @endunless
        @endif

        <div class="doneacts">
            @if($verifyUrl)
                <a class="btn btn--dark" href="{{ $verifyUrl }}" target="_blank" rel="noopener">{{ __('Verificar la firma') }}</a>
            @endif
            @if($homeUrl)
                <a class="btn btn--ghost" href="{{ $homeUrl }}">{{ __('Ir al inicio') }}</a>
                <p class="muted note">{{ __('Te llevaremos al inicio en unos segundos…') }}</p>
            @else
                <p class="closehint">{{ __('Ya puedes cerrar esta ventana.') }}</p>
            @endif
        </div>
    </div></div>
    @if($homeUrl)
        <script>setTimeout(function(){ window.location.href = @json($homeUrl); }, 7000);</script>
    @endif
@elseif($stage === 'not_turn')
    <div class="center"><div class="card">
        <h1 class="warn">{{ __('Aún no es tu turno') }}</h1>
        <p class="muted">{{ __('El sobre está en firma con otra persona. En cuanto sea tu turno recibirás un correo con el enlace para firmar.') }}</p>
    </div></div>
@else
    {{-- ── Toolbar ── --}}
    <div class="tbar">
        <div class="brand"><span class="logo">C</span><span><b>{{ __('Firma de contrato') }}</b><small>{{ $recipient->name }} · {{ $recipient->roleLabel() }}</small></span></div>
        <div class="grow"></div>
        <div class="prog">
            <div class="prog__bar"><div class="prog__fill" id="pf"></div></div>
            <span class="prog__txt" id="pt">0 / 0</span>
        </div>
        <button type="button" class="btn btn--finish" id="finish" disabled>{{ __('Finalizar y firmar') }}</button>
    </div>

    <nav class="docs" id="docs"></nav>
    <button type="button" class="start" id="start">→ {{ __('Comenzar') }}</button>

    <div class="stage" id="stage">
        @forelse($ceremony as $di => $doc)
            @php
                $kind = $doc['key'] === 'hoja' ? __('Informativo') : ($di === 0 || ($doc['key'] ?? '') === 'caratula' ? __('Contrato') : __('Anexo'));
            @endphp
            <section class="docblock" data-doc="{{ $di }}" data-name="{{ $doc['name'] ?? __('Documento') }}">
                <div class="dochead">
                    <span class="kind">{{ $kind }}</span> {{ $doc['name'] ?? __('Documento') }}
                    @if(($doc['mode'] ?? '') === 'pdf' && !empty($doc['url']))
                        <a class="open" href="{{ $doc['url'] }}" target="_blank" rel="noopener">{{ __('Abrir en pestaña') }}</a>
                    @endif
                </div>
                @if(($doc['mode'] ?? '') === 'html')
                    <div class="frameShell">
                        <iframe class="ccframe" data-doc="{{ $di }}" data-anchors='@json($doc['anchors'] ?? [])'
                                srcdoc="{{ $doc['html'] ?? '' }}" title="{{ $doc['name'] ?? __('Documento') }}"></iframe>
                    </div>
                @else
                    <div class="pdfShell" data-doc="{{ $di }}" data-pdf-src="{{ $doc['url'] ?? '' }}" data-tags='@json($doc['tags'] ?? [])'>
                        <div class="loading">{{ __('Cargando documento…') }}</div>
                    </div>
                @endif
            </section>
        @empty
            <div class="muted" style="text-align:center;padding:2rem">{{ __('No hay documentos para mostrar.') }}</div>
        @endforelse
    </div>

    {{-- Formulario real (el POST no cambia: una autógrafa; el servidor sella + estampa en todas mis anclas). --}}
    <form method="POST" action="{{ $signUrl }}" id="signForm" style="display:none">
        @csrf
        <input type="hidden" name="consent" id="fConsent" value="0">
        <input type="hidden" name="signature_image" id="fSig" value="">
        <input type="hidden" name="rubrica_image" id="fRubrica" value="">
        <input type="hidden" name="save_signature" id="fSave" value="0">
    </form>

    {{-- Rechazar --}}
    <div class="decline">
        <button type="button" class="decline__toggle" data-decline-toggle>{{ __('No puedo firmar este contrato') }}</button>
        <form method="POST" action="{{ $declineUrl }}" id="declineForm" class="decline__box">
            @csrf
            <label style="font-size:.85rem;color:#546070;display:block;margin-bottom:.4rem">{{ __('Cuéntanos por qué (obligatorio):') }}</label>
            <textarea name="reason" id="declineReason" maxlength="500" placeholder="{{ __('Ej.: el monto no coincide con lo acordado.') }}"></textarea>
            <button type="submit" class="decline__submit">{{ __('Confirmar que no firmaré') }}</button>
        </form>
    </div>

    {{-- Modal · adopta tu firma --}}
    <div class="scrim" id="scrim" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="mt">
            @php $iniciales = collect(preg_split('/\s+/', trim($recipient->name)))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(4)->implode(''); @endphp
            <div class="modal__h"><h3 id="mt">{{ __('Crear su firma') }}</h3></div>
            <div class="modal__b">
                @if(session('error') || (isset($errors) && $errors->any()))
                    <div class="err">{{ session('error') ?: (isset($errors) ? $errors->first() : '') }}</div>
                @endif
                <div class="namerow">
                    <div class="field"><label>{{ __('Nombre completo') }} *</label><input type="text" id="fname" value="{{ $recipient->name }}"></div>
                    <div class="field"><label>{{ __('Iniciales') }} *</label><input type="text" id="finit" value="{{ $iniciales }}" maxlength="6"></div>
                </div>
                <div class="seg2" role="tablist">
                    <button type="button" role="tab" aria-selected="true" id="tabElegir">{{ __('ELEGIR') }}</button>
                    <button type="button" role="tab" aria-selected="false" id="tabDibujar">{{ __('DIBUJAR') }}</button>
                </div>
                <div class="fpane" id="paneElegir">
                    <div class="stylelist" id="styleList"></div>
                </div>
                <div class="fpane" id="paneDibujar" style="display:none">
                    <div class="markrow">
                        <div class="markcol">
                            <span class="marklbl">{{ __('Firma') }}</span>
                            <div class="sigbox"><span class="base"></span><canvas id="cv"></canvas><span class="hint" id="hint">{{ __('Dibujar firma') }}</span></div>
                        </div>
                        <div class="markcol markcol--rub">
                            <span class="marklbl">{{ __('Iniciales') }}</span>
                            <div class="sigbox sigbox--rub"><span class="base"></span><canvas id="cvR"></canvas><span class="hint" id="hintR">{{ __('Dibujar iniciales') }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
            @if($needsConsent)
                <label class="consent"><input type="checkbox" id="cConsent" checked>
                    <span>{{ __('Acepto firmar electrónicamente; mi firma electrónica tiene la misma validez que la autógrafa (Cód. de Comercio 89 y 89 Bis; CCF 1811).') }}</span></label>
            @else
                <p class="disc">{{ __('Tu firma se sella con SHA-256; el hash acompaña a cada firma como prueba de integridad.') }}</p>
            @endif
            <div class="modal__f">
                <button type="button" class="link" id="clearSig" style="display:none">{{ __('Limpiar') }}</button>
                <div class="grow"></div>
                <button type="button" class="link" id="cancelSig">{{ __('Cancelar') }}</button>
                <button type="button" class="btn btn--dark" id="adoptSig">{{ __('Crear') }}</button>
            </div>
        </div>
    </div>
@endif

@if($stage === 'sign')
    <script>function ccDecline(){var f=document.getElementById('declineForm');var o=f.style.display==='block';f.style.display=o?'none':'block';var t=document.getElementById('declineReason');o?t.removeAttribute('required'):(t.setAttribute('required','required'),t.focus());}
document.addEventListener('click',function(e){if(e.target.closest('[data-decline-toggle]')){ccDecline();}});</script>
    <script src="{{ asset('js/vendor/pdfjs/pdf.min.js') }}"></script>
    <script>
    (function(){
        'use strict';
        var T={ sign:@json(__('✎ Firmar')), rubrica:@json(__('🅡 Rúbrica')), signedBy:@json(__('Firmado por:')), integ:@json(__('íntegra')),
                fail:@json(__('No se pudo mostrar aquí. Usa "Abrir en pestaña".')), seal:@json(__('Sello SHA-256')),
                drawFirst:@json(__('Dibuja tu firma primero')) };
        if(window.pdfjsLib){ pdfjsLib.GlobalWorkerOptions.workerSrc=@json(asset('js/vendor/pdfjs/pdf.worker.min.js')); }

        var CC={ adopted:'', rubrica:'', tags:[], docTotal:0, docReady:0 };
        var pf=document.getElementById('pf'), pt=document.getElementById('pt'), finish=document.getElementById('finish'),
            start=document.getElementById('start'), signForm=document.getElementById('signForm'),
            fSig=document.getElementById('fSig'), fRubrica=document.getElementById('fRubrica'), fConsent=document.getElementById('fConsent'), fSave=document.getElementById('fSave');

        // ── hash (sello) para el preview de la ceremonia; el hash autoritativo lo sella el servidor ──
        function sha256Hex(s){
            try{ return crypto.subtle.digest('SHA-256', new TextEncoder().encode(s)).then(function(b){
                return [].map.call(new Uint8Array(b),function(x){return x.toString(16).padStart(2,'0')}).join(''); }); }
            catch(e){ return Promise.resolve(''); }
        }
        function stampHTML(url,hash,small){
            if(small){ return '<span class="stamp"><span class="stamp__bar"></span><span class="stamp__body">'+
                '<img src="'+url+'"><span class="stamp__hash">✓ '+T.integ+'</span></span></span>'; }
            var h = hash ? ('<span class="stamp__hash">✓ <code>'+hash+'</code></span>') : ('<span class="stamp__hash">✓ '+T.seal+'</span>');
            return '<span class="stamp"><span class="stamp__bar"></span><span class="stamp__body">'+
                '<span class="stamp__lbl">'+T.signedBy+'</span><img src="'+url+'">'+h+'</span></span>';
        }

        function refresh(){
            var applied=CC.tags.filter(function(t){return t.applied}).length, tot=CC.tags.length;
            pf.style.width=(tot?applied/tot*100:0)+'%';
            pt.textContent=applied+' / '+tot;
            // por documento
            var per={};
            CC.tags.forEach(function(t){ per[t.docIdx]=per[t.docIdx]||{a:0,n:0}; per[t.docIdx].n++; if(t.applied)per[t.docIdx].a++; });
            document.querySelectorAll('.doctab').forEach(function(tab){
                var d=tab.dataset.tab, p=per[d];
                var pend=tab.querySelector('.pend');
                if(p){ tab.setAttribute('data-done', p.a>=p.n?'1':'0'); if(pend) pend.textContent=(p.n-p.a)||''; }
                else { tab.setAttribute('data-done','1'); if(pend) pend.textContent=''; }
            });
            var ready=CC.docReady>=CC.docTotal, done=ready&&(tot===0||applied>=tot);
            finish.disabled=!(CC.adopted && done);
            start.classList.toggle('hide', tot>0 && applied>=tot);
            if(applied>0) start.textContent='→ '+@json(__('Siguiente'));
        }
        function docDone(){ CC.docReady++; refresh(); }

        function apply(tag){
            if(!CC.adopted){ openModal(tag); return; }
            var small=tag.small;
            var img = small ? (CC.rubrica || CC.adopted) : CC.adopted;   // rúbrica ≠ firma
            tag.applied=true; tag.el.classList.add('applied'); tag.el.classList.remove('pulse');
            sha256Hex(img+'|'+Date.now()+'|'+@json($recipient->id)).then(function(hash){
                tag.el.innerHTML=stampHTML(img,hash,small);
                refresh();
            });
            refresh();
        }
        // Posición ABSOLUTA del tag en la página (reading order). Para tags dentro de un iframe
        // el rect es relativo al iframe → hay que sumar el offset del iframe (igual que focus()).
        function tagPos(t){
            try{
                var el=t.el, doc=el.ownerDocument, r=el.getBoundingClientRect(), ox=0, oy=0;
                if(doc!==document){ var fr=doc.defaultView&&doc.defaultView.frameElement; if(fr){ var fb=fr.getBoundingClientRect(); ox=fb.left; oy=fb.top; } }
                return { y: window.scrollY+oy+r.top, x: window.scrollX+ox+r.left };
            }catch(e){ return {y:0,x:0}; }
        }
        // Pendientes en orden de lectura: por Y (arriba→abajo) y, en la misma fila, por X (izq→der).
        function pendingSorted(){
            return CC.tags.filter(function(x){return !x.applied})
                .map(function(t){ var p=tagPos(t); return {t:t,y:p.y,x:p.x}; })
                .sort(function(a,b){ return (Math.abs(a.y-b.y)>6 ? a.y-b.y : a.x-b.x); })
                .map(function(o){ return o.t; });
        }
        function nextTag(){ var p=pendingSorted()[0]; if(p){ p.focus(); } }
        function register(t){ t.applied=false; CC.tags.push(t); }

        // ── barra + start + finish ──
        start.onclick=function(){ if(!CC.adopted){ openModal(pendingSorted()[0]||null); return; } nextTag(); };
        finish.onclick=function(){ if(finish.disabled) return; fSig.value=CC.adopted; if(fRubrica){ fRubrica.value=CC.rubrica||CC.adopted; } signForm.submit(); };

        // ── documentos HTML (iframe embebido) ──
        // srcdoc dispara 'load' ANTES de maquetar el cuerpo → hay que esperar a que aparezcan las
        // anclas, y re-medir el alto tras asentar tipografías (si no, el contrato queda aplastado).
        function initHtml(iframe){
            var docIdx=iframe.getAttribute('data-doc'); var mine=[]; try{mine=JSON.parse(iframe.getAttribute('data-anchors')||'[]')}catch(e){}
            var wired=false, tries=0;
            function sizeFrame(){ try{ var d=iframe.contentDocument; var h=Math.max(d.body?d.body.scrollHeight:0, d.documentElement?d.documentElement.scrollHeight:0); if(h>0) iframe.style.height=(h+24)+'px'; }catch(e){} }
            function attempt(){
                if(wired) return;
                var idoc; try{ idoc=iframe.contentDocument; }catch(e){ idoc=null; }
                var ok = idoc && idoc.body && idoc.querySelector('[data-anchor]');
                if(!ok){ if(tries++<50){ setTimeout(attempt,70); return; } docDone(); return; }   // fail-open: lectura
                wired=true;
                try{
                    var st=idoc.createElement('style');
                    st.textContent='[data-anchor].cc-tag{cursor:pointer;background:#ffcf4a!important;border:1px solid #e3a600!important;color:#6a4a00!important;box-shadow:0 2px 5px rgba(180,120,0,.28);border-radius:5px}'+
                        '[data-anchor].cc-tag:hover{background:#ffd968!important}'+
                        '.cc-tag.applied{background:#fff!important;border:1px solid #d8dcff!important;box-shadow:0 1px 3px rgba(16,24,40,.1)}'+
                        '.stamp{display:flex;align-items:stretch;gap:6px;text-align:left}.stamp__bar{width:5px;border:1.5px solid #4b53d6;border-right:0;border-radius:4px 0 0 4px;flex:0 0 auto}'+
                        '.stamp__body{display:flex;flex-direction:column;min-width:0}.stamp__lbl{font-size:8px;color:#6b7482;font-weight:700}'+
                        '.stamp img{height:30px;max-width:170px;object-fit:contain;mix-blend-mode:multiply;display:block}'+
                        '.stamp__hash{font-size:8px;color:#12a150;font-weight:700;max-width:200px;line-height:1.25}.stamp__hash code{font-family:Consolas,monospace;font-size:8px;color:#5b6472;word-break:break-all}';
                    (idoc.head||idoc.body).appendChild(st);
                    mine.forEach(function(key){
                        Array.prototype.forEach.call(idoc.querySelectorAll('[data-anchor="'+key+'"]'), function(box){
                            box.classList.add('cc-tag'); box.textContent=T.sign;
                            var small=(key==='rubrica');
                            var tag={docIdx:docIdx, el:box, small:small, focus:function(){
                                try{ var fr=iframe.getBoundingClientRect(), br=box.getBoundingClientRect();
                                     window.scrollTo({top:Math.max(0, window.scrollY+fr.top+br.top-130), behavior:'smooth'}); }catch(e){}
                                box.classList.add('pulse'); setTimeout(function(){box.classList.remove('pulse')},1200); } };
                            box.addEventListener('click', function(){ apply(tag); });
                            register(tag);
                        });
                    });
                }catch(e){}
                sizeFrame(); setTimeout(sizeFrame,300); setTimeout(sizeFrame,1000);
                docDone();
            }
            iframe.addEventListener('load', attempt);
            attempt();
        }

        // ── documentos PDF (pdf.js + coordenadas) ──
        function drawPage(pdf,n,host,done){
            pdf.getPage(n).then(function(page){
                var maxW=Math.min(host.clientWidth-20,900), base=page.getViewport({scale:1}), vp=page.getViewport({scale:maxW/base.width}), dpr=window.devicePixelRatio||1;
                var wrap=document.createElement('div'); wrap.className='ccpage'; wrap.style.width=vp.width+'px'; wrap.style.height=vp.height+'px';
                var c=document.createElement('canvas'); c.width=Math.floor(vp.width*dpr); c.height=Math.floor(vp.height*dpr); c.style.width=vp.width+'px'; c.style.height=vp.height+'px'; wrap.appendChild(c);
                var ov=document.createElement('div'); ov.className='ccmk-ov'; wrap.appendChild(ov); host.appendChild(wrap);
                page.render({canvasContext:c.getContext('2d'),viewport:vp,transform:dpr!==1?[dpr,0,0,dpr,0,0]:null}).promise.then(function(){done(ov)});
            }).catch(function(){done(null)});
        }
        function initPdf(host){
            var docIdx=host.getAttribute('data-doc'), url=host.getAttribute('data-pdf-src'), tags=[]; try{tags=JSON.parse(host.getAttribute('data-tags')||'[]')}catch(e){}
            if(!window.pdfjsLib||!url){ host.innerHTML='<div class="loading">'+T.fail+'</div>'; docDone(); return; }
            var ov={};
            pdfjsLib.getDocument(url).promise.then(function(pdf){
                host.innerHTML=''; var n=1;
                (function nx(){ if(n>pdf.numPages){ place(); return; } var cur=n; drawPage(pdf,cur,host,function(o){ov[cur]=o;n++;nx();}); })();
            }).catch(function(){ host.innerHTML='<div class="loading">'+T.fail+'</div>'; docDone(); });
            function place(){
                tags.forEach(function(f){
                    var o=ov[parseInt(f.page,10)]; if(!o) return;
                    var isRub=(f.key==='rubrica');
                    var mk=document.createElement('button'); mk.type='button'; mk.className='ccmk'+(isRub?' small':'');
                    mk.style.left=f.x_pct+'%'; mk.style.top=f.y_pct+'%'; mk.style.minWidth=f.w_pct+'%'; mk.textContent=isRub?T.rubrica:T.sign;
                    o.appendChild(mk);
                    var tag={docIdx:docIdx, el:mk, small:isRub, focus:function(){ mk.scrollIntoView({behavior:'smooth',block:'center'}); mk.classList.add('pulse'); setTimeout(function(){mk.classList.remove('pulse')},1200); }};
                    mk.addEventListener('click', function(){ apply(tag); });
                    register(tag);
                });
                docDone();
            }
        }

        // ── modal "Crear su firma" (DocuSign): Nombre + Iniciales; pestañas ELEGIR (estilos) / DIBUJAR ──
        var scrim=document.getElementById('scrim'),
            cv=document.getElementById('cv'), ctx=cv.getContext('2d'),
            cvR=document.getElementById('cvR'), ctxR=cvR.getContext('2d'),
            fname=document.getElementById('fname'), finit=document.getElementById('finit'),
            hint=document.getElementById('hint'), hintR=document.getElementById('hintR'),
            styleList=document.getElementById('styleList'), clearBtn=document.getElementById('clearSig'),
            paneElegir=document.getElementById('paneElegir'), paneDibujar=document.getElementById('paneDibujar'),
            tabElegir=document.getElementById('tabElegir'), tabDibujar=document.getElementById('tabDibujar'),
            mode='elegir', drawn=false, drawnR=false, pending=null, saveCb=null, styleIdx=0, initEdited=false,
            FONTS=["'Dancing Script','Segoe Script',cursive","'Caveat','Bradley Hand','Segoe Print',cursive","'Great Vibes','Lucida Handwriting',cursive","'Sacramento','Segoe Script',cursive","'Satisfy','Brush Script MT',cursive"];

        function sizeOne(c,x){ var r=c.getBoundingClientRect(); c.width=r.width*2; c.height=r.height*2; x.setTransform(1,0,0,1,0,0); x.scale(2,2); x.lineWidth=2.6; x.lineCap='round'; x.lineJoin='round'; x.strokeStyle='#16233b'; }
        function sizeCanvas(){ sizeOne(cv,ctx); sizeOne(cvR,ctxR); }
        function openModal(tag){ pending=tag||null; scrim.classList.add('open'); scrim.setAttribute('aria-hidden','false'); buildStyles(); if(mode==='dibujar'){ setTimeout(sizeCanvas,60); } }
        function closeModal(){ scrim.classList.remove('open'); scrim.setAttribute('aria-hidden','true'); }
        document.getElementById('cancelSig').onclick=closeModal;
        scrim.addEventListener('click', function(e){ if(e.target===scrim) closeModal(); });

        function bindDraw(c,x,onDraw){
            var d=false;
            function pos(e){ var r=c.getBoundingClientRect(); var t=(e.touches&&e.touches[0])||e; return {x:t.clientX-r.left,y:t.clientY-r.top}; }
            c.addEventListener('mousedown', function(e){d=true;var p=pos(e);x.beginPath();x.moveTo(p.x,p.y)});
            c.addEventListener('mousemove', function(e){if(!d)return;var p=pos(e);x.lineTo(p.x,p.y);x.stroke();onDraw()});
            window.addEventListener('mouseup', function(){d=false});
            c.addEventListener('touchstart', function(e){d=true;var p=pos(e);x.beginPath();x.moveTo(p.x,p.y);e.preventDefault()},{passive:false});
            c.addEventListener('touchmove', function(e){if(!d)return;var p=pos(e);x.lineTo(p.x,p.y);x.stroke();onDraw();e.preventDefault()},{passive:false});
        }
        bindDraw(cv,ctx,function(){drawn=true}); bindDraw(cvR,ctxR,function(){drawnR=true});

        function esc2(s){ return String(s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
        function iniciales(){ var p=(fname.value||'').trim().split(/\s+/).filter(Boolean).slice(0,4).map(function(w){return w.charAt(0).toUpperCase()}).join(''); return p||'—'; }
        // ELEGIR: galería de estilos (el mismo nombre+iniciales en varias letras manuscritas).
        function buildStyles(){
            var nm=fname.value||'Firma', ini=finit.value||iniciales();
            styleList.innerHTML=FONTS.map(function(f,i){
                return '<label class="styleopt'+(i===styleIdx?' sel':'')+'" data-i="'+i+'">'
                    +'<input type="radio" name="sigstyle"'+(i===styleIdx?' checked':'')+'>'
                    +'<span class="sbrk"><span class="sblab">Firmado por:</span><span class="sname" style="font-family:'+f+'">'+esc2(nm)+'</span></span>'
                    +'<span class="sbrk sbrk--ini"><span class="sblab">Rúbrica</span><span class="sini" style="font-family:'+f+'">'+esc2(ini)+'</span></span>'
                    +'</label>';
            }).join('');
            Array.prototype.forEach.call(styleList.querySelectorAll('.styleopt'), function(el){
                el.addEventListener('click', function(){ styleIdx=parseInt(el.dataset.i,10)||0;
                    styleList.querySelectorAll('.styleopt').forEach(function(x){x.classList.remove('sel')}); el.classList.add('sel');
                    var r=el.querySelector('input'); if(r) r.checked=true; });
            });
        }
        // Las iniciales se autocompletan del nombre hasta que el usuario las edita a mano.
        finit.addEventListener('input', function(){ initEdited=true; buildStyles(); });
        fname.addEventListener('input', function(){ if(!initEdited){ finit.value=iniciales(); } buildStyles(); });

        function setMode(m){ mode=m;
            tabElegir.setAttribute('aria-selected', m==='elegir'); tabDibujar.setAttribute('aria-selected', m==='dibujar');
            paneElegir.style.display = m==='elegir'?'block':'none'; paneDibujar.style.display = m==='dibujar'?'block':'none';
            clearBtn.style.display = m==='dibujar'?'inline':'none';
            if(m==='dibujar'){ setTimeout(sizeCanvas,30); }
        }
        tabElegir.onclick=function(){ setMode('elegir'); }; tabDibujar.onclick=function(){ setMode('dibujar'); };
        clearBtn.onclick=function(){ ctx.clearRect(0,0,cv.width,cv.height); drawn=false; ctxR.clearRect(0,0,cvR.width,cvR.height); drawnR=false; };

        function typedImage(text,font,size){ var c=document.createElement('canvas'); c.width=680; c.height=210; var x=c.getContext('2d');
            x.fillStyle='#16233b'; x.font='italic '+(size||74)+'px '+(font||'"Segoe Script",cursive'); x.textAlign='center'; x.textBaseline='middle';
            x.fillText(text||'—',340,105); return c.toDataURL('image/png'); }

        function finalizeAdopt(firmaUrl, rubUrl){
            CC.adopted=firmaUrl; CC.rubrica=rubUrl||firmaUrl;
            fSig.value=CC.adopted; if(fRubrica){ fRubrica.value=CC.rubrica; }
            fConsent.value = (document.getElementById('cConsent') ? (document.getElementById('cConsent').checked?'1':'0') : '1');
            fSave.value = saveCb && saveCb.checked ? '1':'0';
            closeModal();
            if(pending){ var t=pending; pending=null; apply(t); setTimeout(nextTag,220); }
            else { refresh(); }
        }
        document.getElementById('adoptSig').onclick=function(){
            var cc=document.getElementById('cConsent');
            if(cc && !cc.checked){ cc.focus(); var lbl=cc.closest('.consent'); if(lbl) lbl.style.color='#c0392b'; return; }
            var ini=finit.value||iniciales(), firmaUrl, rubUrl;
            if(mode==='dibujar'){
                if(!drawn){ hint.textContent=T.drawFirst; hint.style.color='#c0392b'; return; }
                firmaUrl=cv.toDataURL('image/png');
                rubUrl = drawnR ? cvR.toDataURL('image/png') : typedImage(ini, FONTS[0], 92);
            } else {
                var f=FONTS[styleIdx]||FONTS[0];
                firmaUrl=typedImage(fname.value||'Firma', f, 74);
                rubUrl=typedImage(ini, f, 92);
            }
            finalizeAdopt(firmaUrl, rubUrl);
        };
        setMode('elegir');

        // ── construir tabs de documentos ──
        var nav=document.getElementById('docs');
        document.querySelectorAll('.docblock').forEach(function(block){
            var d=block.dataset.doc, name=block.dataset.name||('Doc '+d);
            var b=document.createElement('button'); b.type='button'; b.className='doctab'; b.dataset.tab=d;
            b.innerHTML='<span class="dot">✓</span>'+name+'<span class="pend"></span>';
            b.onclick=function(){ block.scrollIntoView({behavior:'smooth',block:'start'}); };
            nav.appendChild(b);
        });
        var io=new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){
            var d=e.target.dataset.doc; document.querySelectorAll('.doctab').forEach(function(x){x.removeAttribute('aria-current')});
            var tab=document.querySelector('.doctab[data-tab="'+d+'"]'); if(tab)tab.setAttribute('aria-current','true'); }}); },{rootMargin:'-45% 0px -50% 0px'});
        document.querySelectorAll('.docblock').forEach(function(b){io.observe(b)});

        // ── arranque ──
        var frames=document.querySelectorAll('iframe.ccframe'), hosts=document.querySelectorAll('.pdfShell[data-pdf-src]');
        CC.docTotal=frames.length+hosts.length;
        if(CC.docTotal===0){ CC.docTotal=1; docDone(); }
        Array.prototype.forEach.call(frames, initHtml);
        Array.prototype.forEach.call(hosts, initPdf);
        refresh();
    })();
    </script>
@endif
@stack('scripts')
</body>
</html>
