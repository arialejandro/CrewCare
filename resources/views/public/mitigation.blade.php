{{-- Página PÚBLICA (sin auth) — Pilar 1b Magic Links: subida de foto de mitigación.
     Standalone: NO usa layouts.app (admin). HTML mínimo mobile-first, CSS inline,
     SIN dependencias externas (CSP bloquea CDNs). La seguridad la impone el
     middleware 'signed' de la ruta; el POST va a la MISMA firma vía $storeUrl. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>Acción correctiva · CrewCare</title>
    <style>
        :root { --brand:#0d6efd; --ok:#198754; --bg:#f4f6f9; --card:#ffffff; --ink:#1f2733; --muted:#6b7683; --line:#e3e8ef; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; }
        body {
            background: var(--bg);
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            -webkit-text-size-adjust: 100%;
        }
        .wrap { max-width: 560px; margin: 0 auto; padding: 20px 16px 48px; }
        .brand { text-align:center; font-weight:700; letter-spacing:.02em; color:var(--muted); font-size:14px; margin-bottom:14px; text-transform:uppercase; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(16,24,40,.06);
        }
        h1 { font-size: 19px; margin: 0 0 4px; }
        .sub { color: var(--muted); font-size: 14px; margin: 0 0 18px; }
        .label { display:block; font-weight:600; font-size:14px; margin: 16px 0 6px; }
        .desc-box {
            background:#f8fafc; border:1px solid var(--line); border-radius:10px;
            padding:12px 14px; font-size:15px; white-space:pre-wrap; word-break:break-word;
        }
        input[type=file], textarea {
            width:100%; font-size:16px; color:var(--ink);
            border:1px solid var(--line); border-radius:10px; padding:12px; background:#fff;
        }
        input[type=file] { padding:10px; }
        textarea { resize:vertical; min-height:84px; font-family:inherit; }
        .hint { color:var(--muted); font-size:12.5px; margin:6px 0 0; }
        .btn {
            display:block; width:100%; margin-top:22px; border:0; cursor:pointer;
            background: var(--brand); color:#fff; font-size:16px; font-weight:600;
            padding:14px 16px; border-radius:11px; text-align:center;
        }
        .btn:active { opacity:.9; }
        .errors {
            background:#fff5f5; border:1px solid #f3c2c2; color:#a12525;
            border-radius:10px; padding:12px 14px; font-size:14px; margin-bottom:16px;
        }
        .errors ul { margin:6px 0 0; padding-left:18px; }
        .done-ico {
            width:64px; height:64px; border-radius:50%; margin:4px auto 14px;
            background: var(--ok); color:#fff; display:flex; align-items:center; justify-content:center;
            font-size:34px; font-weight:700;
        }
        .center { text-align:center; }
        .thumb { display:block; max-width:100%; border-radius:10px; border:1px solid var(--line); margin:14px auto 0; }
        /* Marcos normativos: dan legitimidad a la petición sin identificar a nadie. */
        .flags { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
        .flag {
            display:inline-block; padding:3px 9px; border-radius:999px;
            background:#eef2f7; border:1px solid var(--line); color:#334155;
            font-size:12px; font-weight:700; letter-spacing:.04em;
        }
        .foot { text-align:center; color:var(--muted); font-size:12px; margin-top:22px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">CrewCare · Seguridad</div>

        <div class="card">
            @if (! empty($closed))
                {{-- (2026-07-24) El enlace muere cuando el hallazgo se CIERRA — no al primer
                     upload (si la foto sale mal hay que poder corregirla). Del otro lado hay
                     alguien en set que hizo lo que se le pidió: merece un mensaje, no un error. --}}
                <div class="done-ico" style="background:var(--muted)" aria-hidden="true">&#10003;</div>
                <div class="center">
                    <h1>Este hallazgo ya está cerrado</h1>
                    <p class="sub">La acción correctiva fue verificada dentro del sistema, así que este enlace ya no admite fotos. Si crees que falta algo, avisa a Salud y Seguridad.</p>
                </div>
            @elseif (! empty($done))
                {{-- Estado de éxito tras subir la foto --}}
                <div class="done-ico" aria-hidden="true">&#10003;</div>
                <div class="center">
                    <h1>¡Gracias! Foto recibida.</h1>
                    <p class="sub">La evidencia de la acción correctiva quedó registrada. El cierre y la verificación se completan dentro del sistema.</p>
                </div>
                @if ($item->hasMitigation())
                    <img class="thumb" src="{{ $item->mitigation_image_path }}" alt="Foto de mitigación recibida">
                @endif
            @else
                <h1>Acción correctiva</h1>
                <p class="sub">Sube una foto que evidencie la acción ya ejecutada. No necesitas iniciar sesión.</p>

                @if ($errors->any())
                    <div class="errors">
                        <strong>Revisa lo siguiente:</strong>
                        <ul>
                            @foreach ($errors->all() as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- (2026-07-24) Se muestra QUÉ HAY QUE HACER, no qué pasó.
                     `description` salió de aquí: es texto libre del safety y puede nombrar al
                     involucrado o describir el hecho — y este enlace llega por WhatsApp, que se
                     reenvía. Las siglas normativas sustituyen esa "prueba de contexto": le dicen al
                     responsable que la petición está respaldada, sin identificar a nadie. --}}
                <span class="label">Acción correctiva a realizar</span>
                <div class="desc-box">{{ $item->description }}</div>

                @if (! empty($badges))
                    <div class="flags" aria-label="Marcos normativos aplicables">
                        @foreach ($badges as $b)
                            <span class="flag">{{ $b }}</span>
                        @endforeach
                    </div>
                    <p class="hint">Esta acción está respaldada por la normativa señalada.</p>
                @endif

                {{-- El action apunta a la MISMA URL firmada (mitigation.store) para conservar
                     la firma; el middleware 'signed' la valida en el POST. --}}
                <form method="POST" action="{{ isset($storeUrl) ? $storeUrl : url()->current() }}" enctype="multipart/form-data">
                    @csrf

                    <label class="label" for="mitigation_image">Foto de la acción correctiva</label>
                    <input type="file" id="mitigation_image" name="mitigation_image"
                           accept="image/*,.heic,.heif" capture="environment" required data-cc-photo>
                    <p class="hint">Puedes tomar la foto con la cámara. Formatos: JPG o PNG (máx. 12 MB).</p>

                    <label class="label" for="mitigation_note">Nota (opcional)</label>
                    <textarea id="mitigation_note" name="mitigation_note" maxlength="1000"
                              placeholder="Describe brevemente qué se hizo…"></textarea>

                    <button type="submit" class="btn">Enviar foto</button>
                </form>
            @endif
        </div>

        <p class="foot">Enlace seguro y temporal. CrewCare.</p>
    </div>
    {{-- HEIC (iPhone): conversión a JPEG en el navegador antes de subir (el servidor no decodifica HEIC). --}}
    <script src="/js/cc-photo.js"></script>
    <script src="/js/cc-photo-auto.js"></script>
</body>
</html>
