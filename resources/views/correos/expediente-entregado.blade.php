{{-- Correo al TITULAR con su expediente clínico adjunto (2026-07-24 · PIEZA 3).

     Escrito a mano, sin la plantilla del constructor visual que traía el correo COVID retirado:
     ése publicaba una contraseña en claro y datos de contacto de terceros. Aquí sólo va lo
     necesario, con estilos inline (los clientes de correo no cargan CSS externo).

     ⚠ RECIBE: nombre · folio · fecha. NADA CLÍNICO EN EL CUERPO. El detalle va en el PDF
     adjunto; un correo se reenvía y se previsualiza en pantallas ajenas. --}}
<div style="margin:0;padding:24px 12px;background:#f6f7f9;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">

    <div style="padding:20px 24px;border-bottom:1px solid #eef0f3;">
      <p style="margin:0;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;">CrewCare</p>
      <h1 style="margin:4px 0 0;font-size:19px;color:#111827;">{{ __('health.mail_title') }}</h1>
    </div>

    <div style="padding:22px 24px;color:#374151;font-size:14px;line-height:1.6;">
      <p style="margin:0 0 14px;">{{ __('health.mail_hello', ['nombre' => $nombre]) }}</p>

      <p style="margin:0 0 14px;">{{ __('health.mail_body_1', ['folio' => $folio, 'fecha' => $fecha]) }}</p>

      <p style="margin:0 0 14px;padding:12px 14px;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;">
        <strong>{{ __('health.mail_review_title') }}</strong><br>
        {{ __('health.mail_review_body') }}
      </p>

      <p style="margin:0 0 14px;">{{ __('health.mail_body_2') }}</p>

      <p style="margin:0;color:#6b7280;font-size:12px;">{{ __('health.mail_body_3') }}</p>
    </div>

    <div style="padding:14px 24px;background:#f9fafb;border-top:1px solid #eef0f3;color:#9ca3af;font-size:11px;">
      {{ __('health.mail_footer') }}
    </div>

  </div>
</div>
