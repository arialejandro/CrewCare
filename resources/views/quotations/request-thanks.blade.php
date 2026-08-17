{{-- COTIZACIÓN · gracias tras enviar por enlace firmado (público, sin sesión). Autocontenida. --}}
@php
    $brand = \App\Support\Branding::all()['primary_color'] ?? '#ff9900';
@endphp
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cotización recibida — CrewCare</title>
<style>
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0b0f16;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
    .card{max-width:440px;margin:24px;padding:32px 28px;background:#111827;border:1px solid rgba(255,255,255,.08);border-radius:16px;text-align:center}
    .ico{width:56px;height:56px;border-radius:50%;display:grid;place-items:center;margin:0 auto 14px;background:rgba(34,197,94,.16);border:1px solid rgba(34,197,94,.4)}
    h1{font-size:1.2rem;margin:0 0 8px}
    p{color:#9aa5b5;line-height:1.5;margin:0 0 6px}
    .name{color:#fff;font-weight:700}
</style></head>
<body>
    <div class="card">
        <div class="ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
        <h1>¡Gracias{{ !empty($name) ? ', ' : '' }}<span class="name">{{ $name ?? '' }}</span>!</h1>
        <p>Tu cotización quedó <strong>RECIBIDA</strong>.</p>
        <p>La producción la revisará. Puedes cerrar esta ventana.</p>
    </div>
</body></html>
