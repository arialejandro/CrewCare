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
</style>
@stack('styles')
</head>
<body>
<div class="wrap">
    @if($stage === 'done')
        <div class="card">
            <h1 class="ok">{{ __('Listo') }}</h1>
            <p class="muted">{{ __('Este sobre ya está firmado o cerrado. No hay nada más que hacer aquí.') }}</p>
        </div>
    @elseif($stage === 'not_turn')
        <div class="card">
            <h1 class="warn">{{ __('Aún no es tu turno') }}</h1>
            <p class="muted">{{ __('El sobre está en firma con otra persona. Te avisaremos cuando te toque.') }}</p>
        </div>
    @else
        <div class="card">
            <h1>{{ __('Firma tu contrato') }}</h1>
            <p class="muted">{{ $recipient->name }} · {{ $recipient->roleLabel() }}</p>
        </div>

        <div class="card">
            <div class="muted" style="margin-bottom:.4rem">{{ __('Revisa los documentos del paquete:') }}</div>
            @foreach($docs as $i => $doc)
                <div class="doc">
                    <span>{{ $doc['name'] ?? 'Documento' }}</span>
                    <a href="{{ $docUrl($i) }}" target="_blank" rel="noopener">{{ __('Ver') }}</a>
                </div>
            @endforeach
        </div>

        <div class="card">
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
    @endif
</div>
@stack('scripts')
</body>
</html>
