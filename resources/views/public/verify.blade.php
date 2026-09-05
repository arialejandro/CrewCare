{{-- Página PÚBLICA (sin auth) — VERIFICADOR DE SELLOS. Se llega escaneando el QR impreso.

     ⚠ RECIBE UN ACUSE, NO UN MODELO. `$acuse` es un array de claves acotadas que arma
     SealVerifier::resolve() — type_label, folio, uuid, sealed_at, verdict (integridad) +
     retired, retired_at, superseded_folio (vigencia) — y el modelo muere dentro de ese método.
     Ninguna clave es contenido ni identidad. Ningún modelo sellable tiene $hidden, así que si
     aquí llegara el documento, un descuido sacaría nombre, teléfono, diagnóstico o GPS. NO
     agregues variables a esta vista: si hace falta un dato más, se agrega al DTO a conciencia.

     TRES ESTADOS, no dos: la INTEGRIDAD (íntegro/alterado/sin sello) y la VIGENCIA (vigente/
     retirado) son ejes distintos. Un documento retirado SIGUE siendo íntegro: su sello es
     válido, solo cambió de estado. NUNCA se muestra "alterado" por estar retirado.

     Standalone: sin layouts.app, CSS inline, sin dependencias externas (la CSP bloquea CDNs).
     noindex: es una URL por documento, no contenido que deba estar en buscadores. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>Verificación de documento · CrewCare</title>
    <style>
        :root{--bg:#f4f6f9;--card:#fff;--ink:#1f2733;--muted:#6b7683;--line:#e3e8ef;
              --ok:#198754;--bad:#b42318;--warn:#b45309;--mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;}
        *{box-sizing:border-box}
        html,body{margin:0;padding:0}
        body{background:var(--bg);color:var(--ink);line-height:1.5;-webkit-text-size-adjust:100%;
             font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
        .wrap{max-width:520px;margin:0 auto;padding:24px 16px 48px}
        .brand{text-align:center;font-weight:700;letter-spacing:.06em;color:var(--muted);
               font-size:12px;text-transform:uppercase;margin-bottom:14px}
        .card{background:var(--card);border:1px solid var(--line);border-radius:14px;
              padding:22px;box-shadow:0 1px 3px rgba(16,24,40,.06)}
        .verdict{display:flex;align-items:center;gap:12px;padding-bottom:18px;
                 border-bottom:1px solid var(--line);margin-bottom:18px}
        .dot{width:44px;height:44px;border-radius:50%;flex:0 0 44px;display:flex;
             align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:700}
        .dot.ok{background:var(--ok)} .dot.bad{background:var(--bad)} .dot.none{background:var(--muted)} .dot.warn{background:var(--warn)}
        .verdict h1{font-size:17px;margin:0}
        .verdict p{margin:2px 0 0;font-size:13.5px;color:var(--muted)}
        dl{margin:0;display:grid;grid-template-columns:auto 1fr;gap:10px 16px;align-items:baseline}
        dt{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:600}
        dd{margin:0;font-size:14.5px;word-break:break-all}
        dd.mono{font-family:var(--mono);font-size:13px}
        .tsr-dl{display:inline-block;margin-top:3px;padding:7px 14px;border-radius:8px;
                background:var(--ink);color:#fff;text-decoration:none;font-size:13px;font-weight:600}
        .tsr-dl:hover{opacity:.9}
        .foot{text-align:center;color:var(--muted);font-size:12px;margin-top:20px;line-height:1.6}
        @media (max-width:400px){dl{grid-template-columns:1fr;gap:2px 0}dt{margin-top:8px}}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">CrewCare · Verificación</div>

        <div class="card">
            @if ($acuse === null)
                {{-- Respuesta GENÉRICA: idéntica para tipo inválido y para uuid inexistente. La
                     diferencia entre ambas ya sería una filtración (dejaría sondear qué existe). --}}
                <div class="verdict">
                    <span class="dot none" aria-hidden="true">?</span>
                    <div>
                        <h1>No se encontró el documento</h1>
                        <p>El identificador no corresponde a ningún documento emitido.</p>
                    </div>
                </div>
                <p style="margin:0;font-size:13.5px;color:var(--muted)">
                    Revisa que el código se haya leído completo. Si el problema sigue, solicita el
                    documento de nuevo a quien te lo entregó.
                </p>
            @else
                <div class="verdict">
                    @if ($acuse['verdict'] === 'ok' && ! empty($acuse['retired']))
                        {{-- VÁLIDO PERO RETIRADO/CERRADO/SUSPENDIDO: el sello es auténtico; el documento
                             ya no está vigente. No es alteración — no se marca en rojo. La etiqueta la
                             pone cada tipo (retirado / cerrado / suspendido); si no la trae, "retirado". --}}
                        <span class="dot warn" aria-hidden="true">&#10003;</span>
                        <div>
                            <h1>Válido, pero {{ mb_strtolower($acuse['retired_label'] ?? 'retirado') }}</h1>
                            <p>El sello es auténtico y el contenido no cambió, pero el documento
                               ya no está vigente{{ ! empty($acuse['retired_at']) ? ' (desde el '.$acuse['retired_at'].')' : '' }}.</p>
                        </div>
                    @elseif ($acuse['verdict'] === 'ok')
                        <span class="dot ok" aria-hidden="true">&#10003;</span>
                        <div>
                            <h1>Documento íntegro y vigente</h1>
                            <p>El contenido no ha cambiado desde que se selló.</p>
                        </div>
                    @elseif ($acuse['verdict'] === 'altered')
                        <span class="dot bad" aria-hidden="true">&#33;</span>
                        <div>
                            <h1>Documento alterado</h1>
                            <p>El contenido NO coincide con el sello emitido.</p>
                        </div>
                    @else
                        <span class="dot none" aria-hidden="true">&#8211;</span>
                        <div>
                            <h1>Documento sin sello</h1>
                            <p>Existe, pero nunca se selló: no hay integridad que comprobar.</p>
                        </div>
                    @endif
                </div>

                <dl>
                    <dt>Tipo</dt>      <dd>{{ $acuse['type_label'] }}</dd>
                    <dt>Folio</dt>     <dd class="mono">{{ $acuse['folio'] }}</dd>
                    <dt>UUID</dt>      <dd class="mono">{{ $acuse['uuid'] }}</dd>
                    <dt>Sellado</dt>   <dd>{{ $acuse['sealed_at'] ?: '—' }}</dd>
                    @if (! empty($acuse['tsa_at']))
                        {{-- Sello de tiempo externo (TSA). Se muestra cuando existe; no se exige.
                             Se ofrece el HASH timbrado + la descarga del token .tsr para que un
                             tercero pueda verificar el timbre SIN CrewCare (ver VERIFICACION-DOCUMENTOS.md). --}}
                        <dt>Sello de tiempo</dt>
                        <dd>{{ $acuse['tsa_at'] }}<br><small>Timbre RFC&nbsp;3161 · {{ $acuse['tsa_authority'] ?? 'TSA' }}</small></dd>
                        @if (! empty($acuse['tsa_imprint']))
                            <dt>Hash timbrado</dt>
                            <dd class="mono">{{ $acuse['tsa_imprint'] }}<br><small>SHA-256 — es lo que atestigua el timbre</small></dd>
                        @endif
                        @if (! empty($acuse['timbre_url']))
                            <dt>Comprobante</dt>
                            <dd><a class="tsr-dl" href="{{ $acuse['timbre_url'] }}" download>Descargar timbre (.tsr)</a><br><small>para verificarlo por tu cuenta con OpenSSL</small></dd>
                        @endif
                    @endif
                    @if ($acuse['verdict'] === 'ok')
                        {{-- Vigencia solo si el sello es íntegro (si está alterado, la integridad manda). --}}
                        @php
                            $estadoTxt = empty($acuse['retired'])
                                ? 'Vigente'
                                : (($acuse['retired_label'] ?? 'Retirado').(! empty($acuse['retired_at']) ? ' · '.$acuse['retired_at'] : ''));
                        @endphp
                        <dt>Estado</dt>
                        <dd>
                            {{ $estadoTxt }}
                            @if (! empty($acuse['retired']) && ! empty($acuse['superseded_folio']))<br>Sustituido por <span class="mono">{{ $acuse['superseded_folio'] }}</span>@endif
                        </dd>
                    @endif
                </dl>
            @endif
        </div>

        <p class="foot">
            Esta página comprueba la INTEGRIDAD del documento y no muestra su contenido.<br>
            CrewCare · Salud y Seguridad
        </p>
    </div>
</body>
</html>
