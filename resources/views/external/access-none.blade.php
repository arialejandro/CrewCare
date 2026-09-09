{{-- EL INFOSHEET · FASE 4 — acceso externo verificado, pero sin documentos pendientes por firmar. --}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Acceso verificado') }}</title>
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        background: #0b1220; color: #e5e7eb; font-family: system-ui, -apple-system, Segoe UI, sans-serif; padding: 1.2rem; }
    .card { width: 100%; max-width: 440px; background: #111a2e; border: 1px solid #1f2b44; border-radius: 16px; padding: 1.6rem; text-align: center; }
    h1 { font-size: 1.2rem; margin: 0 0 .5rem; color: #86efac; }
    p { color: #9aa4b2; font-size: .92rem; line-height: 1.6; margin: 0; }
</style>
</head>
<body>
    <div class="card">
        <h1>{{ __('Acceso verificado') }}</h1>
        <p>{{ __('Hola') }} {{ $name ?: '' }}. {{ __('Por ahora no tienes documentos pendientes por firmar. Si esperabas un contrato, avísale a producción.') }}</p>
    </div>
</body>
</html>
