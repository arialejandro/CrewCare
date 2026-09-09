<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f3f5fa;font-family:'Segoe UI',Arial,sans-serif;color:#1f2a3a;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5fa;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border:1px solid #e2e6ea;border-radius:14px;overflow:hidden;">
        <tr><td style="background:#1f2937;padding:18px 26px;">
          <span style="color:#ffffff;font-size:16px;font-weight:700;letter-spacing:.02em;">CrewCare</span>
        </td></tr>
        <tr><td style="padding:26px 26px 8px;">
          @if($monthChange)
            <p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#b45309;font-weight:700;">Cambio de mes</p>
            <h1 style="margin:0 0 10px;font-size:20px;line-height:1.3;color:#18202c;">Recuerda los documentos del mes</h1>
            <p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#566072;">
              Además de la factura de la semana, con el cambio de mes toca revisar los documentos mensuales
              (por ejemplo la 32-D del mes). Envíalos por los mismos canales de siempre.
            </p>
          @else
            <p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#2f6d8f;font-weight:700;">Ventana abierta</p>
            <h1 style="margin:0 0 10px;font-size:20px;line-height:1.3;color:#18202c;">Abrió la recepción: {{ $label }}</h1>
            <p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#566072;">
              @if($toName){{ $toName }}, @endif ya puedes enviar tu factura (PDF y XML) y los documentos de esta semana.
            </p>
          @endif

          <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;background:#f6f8fb;border:1px solid #e2e6ea;border-radius:10px;margin:6px 0 16px;">
            <tr><td style="padding:12px 16px;font-size:14px;color:#1f2a3a;">
              <strong>{{ $label }}</strong><br>
              <span style="color:#566072;">Ventana: {{ $opensOn }} — {{ $closesOn }}</span>
            </td></tr>
          </table>

          <p style="margin:0 0 4px;font-size:12.5px;line-height:1.6;color:#8a93a1;">
            Este aviso solo anuncia que la ventana abrió. Recibir no es validar: tus documentos se
            reciben y se organizan; la revisión es aparte.
          </p>
        </td></tr>
        <tr><td style="padding:14px 26px 22px;border-top:1px solid #eef1f5;">
          <span style="font-size:11px;color:#8a93a1;">CrewCare · Recepción centralizada por periodo de pago</span>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
