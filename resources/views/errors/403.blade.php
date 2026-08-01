@php
    // Acceso denegado NORMAL (no beta): pantalla clara, no el 403 crudo del framework. Auto-contenida.
    $brand   = $branding['brand_name'] ?? 'CrewCare';
    $primary = $branding['primary_color'] ?? '#ff9900';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso denegado · {{ $brand }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:#f1f5f9; color:#0f172a; font-family:Arial,Helvetica,sans-serif; padding:24px; }
        .card { background:#fff; max-width:520px; width:100%; border-radius:16px; padding:40px 36px;
                box-shadow:0 10px 30px rgba(15,23,42,.08); text-align:center; }
        .dot { width:64px; height:64px; border-radius:50%; margin:0 auto 20px;
               background:#fee2e2; display:flex; align-items:center; justify-content:center; }
        .dot svg { width:30px; height:30px; stroke:#dc2626; }
        h1 { font-size:1.35rem; margin:0 0 10px; }
        p { color:#475569; line-height:1.6; margin:0 0 8px; font-size:.96rem; }
        .code { font-size:.75rem; color:#94a3b8; letter-spacing:.5px; text-transform:uppercase; margin-bottom:6px; }
        .cta { display:inline-block; margin-top:22px; background:#0f172a; color:#fff; text-decoration:none;
               padding:11px 22px; border-radius:10px; font-weight:bold; font-size:.95rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="dot">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>
        <div class="code">Error 403</div>
        <h1>No tienes permiso para ver esta sección</h1>
        <p>Tu cuenta no cuenta con el acceso necesario para esta parte del sistema.</p>
        <p>Si crees que deberías tenerlo, pídeselo a un administrador de la producción.</p>
        <a class="cta" href="{{ url('/home') }}">Volver al inicio</a>
    </div>
</body>
</html>
