{{--
    correos.safety-alert — Aviso de seguridad transaccional (riesgo Alto/Extremo).
    VISTA DESACOPLADA del disparador (Listener SendHighRiskSafetyAlert): el día que se
    vista con el formato Mailchimp del owner, se cambia SOLO este archivo.

    Variables (todas provistas por el Listener):
      $branding, $recipient_name, $subject, $type_label, $risk_level,
      $what, $when_date, $when_time, $where, $gps, $production, $url
--}}
@php
    $brandName = $branding['brand_name'] ?? 'CrewCare';
    $primary   = $branding['primary_color'] ?? '#ff9900';
    $onPrimary = \App\Support\Branding::textOn($primary);
    $risk      = $risk_level ?? '';
    // Color del badge de riesgo (rojo = Extremo, naranja quemado = Alto).
    $riskColor = $risk === 'Extremo' ? '#b91c1c' : '#c2410c';
    $logo      = $branding['client_logo'] ?? '';
    // En correo solo sirven URLs absolutas; si el logo es ruta relativa, se omite (evita imagen rota).
    $showLogo  = is_string($logo) && (strpos($logo, 'http://') === 0 || strpos($logo, 'https://') === 0);
    $when      = trim(($when_date ?? '') . ' ' . ($when_time ?? ''));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? 'Aviso de seguridad' }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; -webkit-text-size-adjust:100%; font-family:Arial, Helvetica, sans-serif;">

    <!-- Preheader oculto -->
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#f1f5f9; font-size:1px; line-height:1px;">
        {{ $type_label ?? 'Evento de seguridad' }} — Riesgo {{ $risk }}{{ $production ? ' · '.$production : '' }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:24px 12px;">
        <tr>
            <td align="center">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.08);">

                    <!-- Encabezado de marca -->
                    <tr>
                        <td style="background:{{ $primary }}; padding:20px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="color:{{ $onPrimary }}; font-size:18px; font-weight:bold; letter-spacing:.5px; vertical-align:middle;">
                                        {{ $brandName }}
                                    </td>
                                    @if($showLogo)
                                    <td align="right" style="vertical-align:middle;">
                                        <img src="{{ $logo }}" alt="{{ $brandName }}" height="34" style="max-height:34px; width:auto; display:inline-block;">
                                    </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Banda de riesgo -->
                    <tr>
                        <td style="background:{{ $riskColor }}; padding:12px 28px; color:#ffffff; font-size:13px; font-weight:bold; text-transform:uppercase; letter-spacing:.8px;">
                            &#9888; Riesgo {{ $risk !== '' ? $risk : 'elevado' }} — {{ $type_label ?? 'Evento de seguridad' }}
                        </td>
                    </tr>

                    <!-- Cuerpo -->
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 4px; color:#0f172a; font-size:16px; font-weight:bold;">
                                Se registró un evento de seguridad que requiere atención.
                            </p>
                            <p style="margin:0 0 20px; color:#475569; font-size:14px; line-height:1.5;">
                                Hola{{ !empty($recipient_name) ? ' '.$recipient_name : '' }}, un reporte clasificado como
                                <strong style="color:{{ $riskColor }};">{{ $risk !== '' ? $risk : 'de riesgo elevado' }}</strong>
                                acaba de generarse en {{ $brandName }}. Aquí el resumen:
                            </p>

                            <!-- Qué / Cuándo / Dónde -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:10px; border-collapse:separate; overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; width:120px; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Qué</td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #e2e8f0; color:#0f172a; font-size:14px; line-height:1.45;">
                                        <strong>{{ $type_label ?? 'Evento de seguridad' }}</strong>
                                        @if(!empty($what))<br><span style="color:#334155;">{{ $what }}</span>@endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Cuándo</td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #e2e8f0; color:#0f172a; font-size:14px;">
                                        {{ $when !== '' ? $when : 'No especificado' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Dónde</td>
                                    <td style="padding:12px 16px; color:#0f172a; font-size:14px; line-height:1.45;">
                                        {{ !empty($where) ? $where : 'No especificado' }}
                                        @if(!empty($gps))<br><span style="color:#64748b; font-size:12px;">{{ $gps }}</span>@endif
                                        @if(!empty($production))<br><span style="color:#64748b; font-size:12px;">Producción: {{ $production }}</span>@endif
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA -->
                            @if(!empty($url))
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $url }}" style="display:inline-block; background:{{ $primary }}; color:{{ $onPrimary }}; text-decoration:none; font-size:14px; font-weight:bold; padding:13px 28px; border-radius:9px;">
                                            Ver el reporte en {{ $brandName }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            @endif
                        </td>
                    </tr>

                    <!-- Pie -->
                    <tr>
                        <td style="padding:18px 28px; background:#f8fafc; border-top:1px solid #e2e8f0;">
                            <p style="margin:0; color:#94a3b8; font-size:11px; line-height:1.5;">
                                Aviso automático de {{ $brandName }} — Salud &amp; Seguridad. Recibiste este correo porque
                                figuras en la lista de notificaciones o por tu rol/puesto en la producción. No respondas a
                                este mensaje.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
