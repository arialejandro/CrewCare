{{-- PÁGINA DE FIRMA (Paso C) — independiente (el contratado firma sin sesión). Ve el paquete
     completo, consiente (una vez) y firma. La ruta avanza sola. --}}
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
    button { width: 100%; padding: .8rem; border: 0; border-radius: 10px; background: #16a34a; color: #fff; font-weight: 700; font-size: 1.05rem; cursor: pointer; }
    .ok { color: #86efac; } .warn { color: #fcd34d; }
</style>
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
            <form method="POST" action="{{ $signUrl }}">
                @csrf
                @if($needsConsent)
                    <label class="consent">
                        <input type="checkbox" name="consent" value="1" required>
                        <span>{{ __('Acepto firmar electrónicamente. Reconozco que mi firma electrónica tiene la misma validez que la autógrafa (Cód. de Comercio 89 y 89 Bis; CCF 1811).') }}</span>
                    </label>
                @endif
                <button type="submit">{{ __('Firmar el paquete') }}</button>
            </form>
        </div>
    @endif
</div>
</body>
</html>
