{{-- SEGUNDO FACTOR de firma (Paso C) — pantalla independiente (el contratado firma sin sesión).
     crew → fecha de nacimiento; no-crew → RFC como contraseña. Intentos limitados. --}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Verificación de identidad') }}</title>
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        background: #0b1220; color: #e5e7eb; font-family: system-ui, -apple-system, Segoe UI, sans-serif; padding: 1.2rem; }
    .card { width: 100%; max-width: 420px; background: #111a2e; border: 1px solid #1f2b44; border-radius: 16px; padding: 1.6rem; }
    h1 { font-size: 1.15rem; margin: 0 0 .4rem; }
    p { color: #9aa4b2; font-size: .9rem; margin: 0 0 1.1rem; }
    label { display: block; font-size: .85rem; margin-bottom: .35rem; color: #cbd5e1; }
    input { width: 100%; padding: .7rem .8rem; border-radius: 10px; border: 1px solid #2a3a5c; background: #0b1220; color: #e5e7eb; font-size: 1rem; }
    button { width: 100%; margin-top: 1rem; padding: .75rem; border: 0; border-radius: 10px; background: #16a34a; color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer; }
    button:disabled { opacity: .5; cursor: not-allowed; }
    .err { background: #3b1220; border: 1px solid #7f1d3a; color: #fecdd3; padding: .6rem .8rem; border-radius: 10px; font-size: .85rem; margin-bottom: 1rem; }
</style>
</head>
<body>
    <div class="card">
        <h1>{{ __('Verifica tu identidad') }}</h1>
        <p>{{ __('Para ver y firmar tu contrato, confirma este dato. Es solo tuyo.') }}</p>

        @if($error)<div class="err">{{ $error }}</div>@endif

        <form method="POST" action="{{ $verifyUrl }}">
            @csrf
            @if($factor === 'borndate')
                <label for="fv">{{ __('Fecha de nacimiento') }}</label>
                <input id="fv" type="date" name="factor_value" required @disabled($locked)>
            @else
                <label for="fv">{{ __('RFC (tu contraseña de acceso)') }}</label>
                <input id="fv" type="text" name="factor_value" autocapitalize="characters" autocomplete="off" placeholder="XAXX010101000" required @disabled($locked)>
            @endif
            <button type="submit" @disabled($locked)>{{ __('Continuar') }}</button>
        </form>
    </div>
</body>
</html>
