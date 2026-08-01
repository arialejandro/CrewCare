{{--
    correos.involved-alert — Aviso al JEFE INMEDIATO (lead del depto) de que un miembro de su
    equipo quedó registrado como INVOLUCRADO en un reporte de seguridad.

    (2026-07-24) El nombre del involucrado NO viaja al documento (evita cultura punitiva). Este
    correo es el ÚNICO canal donde el nombre sí viaja, y solo al jefe directo, para que actúe.
    Vista DESACOPLADA del disparador (controller notifyInvolvedLead): se restila sin tocar la lógica.

    Variables (provistas por el controller):
      $branding, $recipient_name, $subject, $type_label, $involved_name, $involved_department,
      $what, $when, $where, $production, $url
--}}
@php
    $brandName = $branding['brand_name'] ?? 'CrewCare';
    $primary   = $branding['primary_color'] ?? '#ff9900';
    $onPrimary = \App\Support\Branding::textOn($primary);
    $logo      = $branding['client_logo'] ?? '';
    $showLogo  = is_string($logo) && (strpos($logo, 'http://') === 0 || strpos($logo, 'https://') === 0);
    $band      = '#0369a1'; // azul informativo (no es alarma de riesgo; es un aviso de seguimiento)
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? 'Miembro de tu equipo en un reporte de seguridad' }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; -webkit-text-size-adjust:100%; font-family:Arial, Helvetica, sans-serif;">

    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#f1f5f9; font-size:1px; line-height:1px;">
        {{ $type_label ?? 'Reporte de seguridad' }}{{ !empty($involved_name) ? ' — '.$involved_name : '' }}
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
                                    <td style="color:{{ $onPrimary }}; font-size:18px; font-weight:bold; letter-spacing:.5px; vertical-align:middle;">{{ $brandName }}</td>
                                    @if($showLogo)
                                    <td align="right" style="vertical-align:middle;"><img src="{{ $logo }}" alt="{{ $brandName }}" height="34" style="max-height:34px; width:auto; display:inline-block;"></td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Banda -->
                    <tr>
                        <td style="background:{{ $band }}; padding:12px 28px; color:#ffffff; font-size:13px; font-weight:bold; text-transform:uppercase; letter-spacing:.8px;">
                            Seguimiento — Miembro de tu equipo involucrado
                        </td>
                    </tr>

                    <!-- Cuerpo -->
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 4px; color:#0f172a; font-size:16px; font-weight:bold;">Un miembro de tu equipo quedó registrado en un reporte de seguridad.</p>
                            <p style="margin:0 0 20px; color:#475569; font-size:14px; line-height:1.5;">
                                Hola{{ !empty($recipient_name) ? ' '.$recipient_name : '' }}, como responsable del área te avisamos
                                para dar seguimiento. Este aviso es confidencial: el nombre no aparece en el documento del reporte.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:10px; border-collapse:separate; overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; width:130px; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Involucrado</td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #e2e8f0; color:#0f172a; font-size:14px; line-height:1.45;">
                                        <strong>{{ $involved_name ?? '—' }}</strong>
                                        @if(!empty($involved_department))<br><span style="color:#64748b; font-size:12px;">{{ $involved_department }}</span>@endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Qué</td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #e2e8f0; color:#0f172a; font-size:14px; line-height:1.45;">
                                        <strong>{{ $type_label ?? 'Reporte de seguridad' }}</strong>
                                        @if(!empty($what))<br><span style="color:#334155;">{{ $what }}</span>@endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; vertical-align:top;">Cuándo</td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #e2e8f0; color:#0f172a; font-size:14px;">{{ !empty($when) ? $when : 'No especificado' }}</td>
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

                    <!-- Pie -->
                    <tr>
                        <td style="padding:18px 28px; background:#f8fafc; border-top:1px solid #e2e8f0;">
                            <p style="margin:0; color:#94a3b8; font-size:11px; line-height:1.5;">
                                Aviso confidencial de {{ $brandName }} — Salud &amp; Seguridad. Recibiste este correo como jefe
                                directo del área del involucrado. El nombre no aparece en el documento del reporte. No respondas a este mensaje.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
