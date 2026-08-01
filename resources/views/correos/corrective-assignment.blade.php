{{--
    correos.corrective-assignment — Aviso al RESPONSABLE de una acción correctiva (condición insegura).
    (2026-07-24) Lógica de alerta de la CONDICIÓN (algo que hay que REPARAR): va a la persona que se
    hará cargo, no al jefe de nadie. Vista DESACOPLADA del disparador (notifyCorrectiveResponsible).

    Variables: $branding, $recipient_name, $subject, $type_label, $what, $corrective, $due, $when,
               $where, $production, $url
--}}
@php
    $brandName = $branding['brand_name'] ?? 'CrewCare';
    $primary   = $branding['primary_color'] ?? '#ff9900';
    $onPrimary = \App\Support\Branding::textOn($primary);
    $logo      = $branding['client_logo'] ?? '';
    $showLogo  = is_string($logo) && (strpos($logo, 'http://') === 0 || strpos($logo, 'https://') === 0);
    $band      = '#b45309'; // ámbar: hay algo que reparar con un plazo
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? 'Acción correctiva asignada' }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; -webkit-text-size-adjust:100%; font-family:Arial, Helvetica, sans-serif;">

    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#f1f5f9; font-size:1px; line-height:1px;">
        {{ $type_label ?? 'Condición Insegura' }}{{ !empty($due) ? ' — vence '.$due : '' }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.08);">

                    <tr>
                        <td style="background:{{ $primary }}; padding:20px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="color:{{ $onPrimary }}; font-size:18px; font-weight:bold; letter-spacing:.5px; vertical-align:middle;">{{ $brandName }}</td>
                                    @if($showLogo)
                                    <td align="right" style="vertical-align:middle;"><img src="{{ $logo }}" alt="{{ $brandName }}" height="34" style="max-height:34px; width:auto; display:inline-block;"></td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:{{ $band }}; padding:12px 28px; color:#ffffff; font-size:13px; font-weight:bold; text-transform:uppercase; letter-spacing:.8px;">
                            Acción correctiva asignada{{ !empty($due) ? ' · vence '.$due : '' }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 4px; color:#0f172a; font-size:16px; font-weight:bold;">Se te asignó una acción correctiva.</p>
                            <p style="margin:0 0 20px; color:#475569; font-size:14px; line-height:1.5;">
                                Hola{{ !empty($recipient_name) ? ' '.$recipient_name : '' }}, eres el responsable de resolver una
                                condición insegura registrada en {{ $brandName }}. Aquí el detalle:
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:10px; border-collapse:separate; overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; width:130px; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Condición</td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #e2e8f0; color:#0f172a; font-size:14px; line-height:1.45;">{{ !empty($what) ? $what : '—' }}</td>
                                </tr>
                                @if(!empty($corrective))
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Acción</td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #e2e8f0; color:#0f172a; font-size:14px; line-height:1.45;">{{ $corrective }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Fecha compromiso</td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #e2e8f0; color:#0f172a; font-size:14px;">{{ !empty($due) ? $due : 'Sin fecha definida' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Dónde</td>
                                    <td style="padding:12px 16px; color:#0f172a; font-size:14px; line-height:1.45;">
                                        {{ !empty($where) ? $where : 'No especificado' }}
                                        @if(!empty($production))<br><span style="color:#64748b; font-size:12px;">Producción: {{ $production }}</span>@endif
                                    </td>
                                </tr>
                            </table>

                            @if(!empty($url))
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $url }}" style="display:inline-block; background:{{ $primary }}; color:{{ $onPrimary }}; text-decoration:none; font-size:14px; font-weight:bold; padding:13px 28px; border-radius:9px;">Ver el reporte en {{ $brandName }}</a>
                                    </td>
                                </tr>
                            </table>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px; background:#f8fafc; border-top:1px solid #e2e8f0;">
                            <p style="margin:0; color:#94a3b8; font-size:11px; line-height:1.5;">
                                Aviso automático de {{ $brandName }} — Salud &amp; Seguridad. Recibiste este correo porque eres el
                                responsable de la acción correctiva. No respondas a este mensaje.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
