{{-- PASO 3 · SEGUNDO FACTOR del enlace de intake (solo SELF). Antes de abrir el asistente,
     coteja la FECHA DE NACIMIENTO (el dato que el alta ya exige). NO es muro: si no coincide
     avisa sin bloquear y dirige a producción. Autocontenida y móvil-first, misma piel que el
     asistente. --}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Registro — CrewCare</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;background:#0b0f16;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
    -webkit-text-size-adjust:100%}
  .wrap{max-width:480px;margin:0 auto;min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;
    justify-content:center;padding:24px 20px calc(24px + env(safe-area-inset-bottom))}
  .brand{font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:#f5b301;font-weight:800}
  h1{font-size:1.35rem;font-weight:800;color:#fff;margin:14px 0 6px;line-height:1.25}
  .who{font-size:.9rem;color:#9aa5b5;margin-bottom:18px}
  .who b{color:#fff}
  label{display:block;font-size:.78rem;color:#c7ccd6;margin:6px 0 6px;font-weight:600}
  input{width:100%;height:54px;padding:0 14px;font-size:16px;color:#fff;background:#111827;
    border:1px solid #2b3648;border-radius:12px;outline:none}
  input:focus{border-color:#f5b301}
  .hint{font-size:.78rem;color:#9aa5b5;margin-top:10px;line-height:1.45}
  .banner{margin:2px 0 14px;padding:12px 14px;border-radius:12px;font-size:.86rem;line-height:1.4;
    background:rgba(245,179,1,.12);border:1px solid rgba(245,179,1,.4);color:#fcd34d}
  .btn{width:100%;height:54px;margin-top:18px;border:0;border-radius:13px;font-size:1rem;font-weight:800;
    cursor:pointer;background:#f5b301;color:#111}
  .btn[disabled]{opacity:.5;cursor:not-allowed}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">CrewCare · Registro</div>
  <h1>Confirma que eres tú</h1>
  <div class="who">Antes de abrir el registro de <b>{{ $who }}</b>, escribe la <b>fecha de nacimiento</b> con la que te dieron de alta.</div>

  @if($error)
    <div class="banner">{{ $error }}</div>
  @endif

  <form method="POST" action="{{ $postUrl }}" autocomplete="off">
    @csrf
    <label for="borndate">Fecha de nacimiento</label>
    <input type="date" id="borndate" name="borndate" value="" @if($locked) disabled @endif required>
    <button class="btn" type="submit" @if($locked) disabled @endif>Continuar</button>
  </form>

  <div class="hint">Es un segundo dato de seguridad, además del enlace. No lo compartas.
    Si la fecha no coincide y crees que hay un error, avísale a la producción para corregir tu registro.</div>
</div>
</body>
</html>
