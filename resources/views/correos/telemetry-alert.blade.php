{{--
    correos.telemetry-alert — Telemetría TÉCNICA al dev (NO a la operación).

    Se dispara solo por fallos de INFRAESTRUCTURA de la verificación de cédula
    (fuente caída / error técnico). Las alertas de NEGOCIO nunca llegan aquí:
    ver App\Support\CredentialTelemetry.

    Tono sobrio y banda gris pizarra a propósito: NO es una emergencia de seguridad,
    es un aviso de infraestructura. El rojo se reserva para correos.safety-alert.

    Variables (todas provistas por CredentialTelemetry::report()):
      $reason, $reason_label, $context (array k => v ya saneado),
      $app_name, $url, $occurred_at, $subject
--}}
@php
    $appName    = $app_name ?? 'CrewCare';
    $reasonCode = $reason ?? '';
    $label      = $reason_label ?? 'Fallo técnico';
    $rows       = isset($context) && is_array($context) ? $context : [];
    $appUrl     = $url ?? '';
    $showUrl    = is_string($appUrl) && (strpos($appUrl, 'http://') === 0 || strpos($appUrl, 'https://') === 0);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? 'Telemetría técnica' }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; -webkit-text-size-adjust:100%; font-family:Arial, Helvetica, sans-serif;">

    <!-- Preheader oculto -->
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#f1f5f9; font-size:1px; line-height:1px;">
        Telemetría técnica — {{ $label }}{{ !empty($occurred_at) ? ' · '.$occurred_at : '' }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:24px 12px;">
        <tr>
            <td align="center">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.08);">

                    <!-- Encabezado -->
                    <tr>
                        <td style="background:#0f172a; padding:20px 28px; color:#ffffff; font-size:18px; font-weight:bold; letter-spacing:.5px;">
                            {{ $appName }}
                            <span style="font-weight:normal; color:#94a3b8; font-size:13px;">&nbsp;·&nbsp;telemetría técnica</span>
                        </td>
                    </tr>

                    <!-- Banda de aviso (gris pizarra: infraestructura, no emergencia) -->
                    <tr>
                        <td style="background:#334155; padding:12px 28px; color:#e2e8f0; font-size:13px; font-weight:bold; text-transform:uppercase; letter-spacing:.8px;">
                            Aviso de infraestructura
                        </td>
                    </tr>

                    <!-- Cuerpo -->
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 4px; color:#0f172a; font-size:16px; font-weight:bold;">
                                Falló la verificación de credencial por una causa técnica.
                            </p>
                            <p style="margin:0 0 20px; color:#475569; font-size:14px; line-height:1.5;">
                                Este aviso es para diagnóstico. La operación no recibió copia: las alertas de
                                negocio se resuelven en la aplicación.
                            </p>

                            <!-- Motivo / Cuándo -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:10px; border-collapse:separate; overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; width:120px; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Motivo</td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #e2e8f0; color:#0f172a; font-size:14px; line-height:1.45;">
                                        <strong>{{ $label }}</strong>
                                        @if(!empty($reasonCode))
                                            <br><span style="font-family:Consolas, Menlo, Monaco, 'Courier New', monospace; font-size:12px; color:#475569; background:#f1f5f9; padding:2px 6px; border-radius:4px;">{{ $reasonCode }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Cuándo</td>
                                    <td style="padding:12px 16px; color:#0f172a; font-size:14px;">
                                        {{ !empty($occurred_at) ? $occurred_at : 'No especificado' }}
                                    </td>
                                </tr>
                            </table>

                            <!-- Contexto de diagnóstico -->
                            <p style="margin:24px 0 8px; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold;">
                                Contexto
                            </p>

                            @if(empty($rows))
                                <p style="margin:0; padding:12px 16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; color:#94a3b8; font-size:13px;">
                                    Sin contexto adicional.
                                </p>
                            @else
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:10px; border-collapse:separate; overflow:hidden;">
                                    @foreach($rows as $k => $v)
                                        <tr>
                                            <td style="padding:10px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; width:160px; color:#475569; font-size:12px; font-family:Consolas, Menlo, Monaco, 'Courier New', monospace; vertical-align:top; word-break:break-all;">
                                                {{ $k }}
                                            </td>
                                            <td style="padding:10px 16px; border-bottom:1px solid #e2e8f0; color:#0f172a; font-size:13px; line-height:1.45; word-break:break-word;">
                                                {{ $v !== '' ? $v : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if($showUrl)
                                <p style="margin:20px 0 0; color:#64748b; font-size:12px; line-height:1.5;">
                                    Instancia: <a href="{{ $appUrl }}" style="color:#334155;">{{ $appUrl }}</a>
                                </p>
                            @endif
                        </td>
                    </tr>

                    <!-- Pie -->
                    <tr>
                        <td style="padding:18px 28px; background:#f8fafc; border-top:1px solid #e2e8f0;">
                            <p style="margin:0; color:#94a3b8; font-size:11px; line-height:1.5;">
                                Aviso automático de telemetría técnica de {{ $appName }}. Se envía únicamente a la
                                dirección de desarrollo configurada. No respondas a este mensaje.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
