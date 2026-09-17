{{-- ENVÍO DE ARCHIVOS CON MARCA DE AGUA — correo de entrega. El PDF adjunto trae, en diagonal, el
     nombre en créditos de quien lo recibe (copia personal, trazable). Mismo tono de la casa. --}}
@php
    $title = $title ?? 'Documento';
    $body  = trim((string) ($body ?? ''));
@endphp
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f5f7;padding:0;margin:0;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;background-color:#ffffff;border-radius:12px;overflow:hidden;font-family:'Raleway',Segoe UI,Arial,sans-serif;">
        {{-- Encabezado --}}
        <tr>
          <td align="center" style="padding:26px 20px 10px;">
            <img src="https://crewcare.mx/img/logo-mails-c.png" alt="CrewCare" width="220" style="display:inline-block;border:0;height:auto;max-width:220px;">
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:2px 24px 0;">
            <div style="height:3px;width:56px;background:#ff0046;border-radius:3px;margin:8px auto 0;"></div>
          </td>
        </tr>

        {{-- Cuerpo --}}
        <tr>
          <td style="padding:22px 34px 6px;color:#1f2a3a;">
            <h1 style="font-size:22px;margin:0 0 4px;font-weight:700;">{{ $title }}</h1>
            <p style="font-size:14px;line-height:1.7;color:#3d4757;margin:10px 0;">
              {{ __('Hola') }} <strong>{{ $toName ?: __('colega') }}</strong>,<br>
              @if($body !== '')
                {!! nl2br(e($body)) !!}
              @else
                {{ __('Adjuntamos el documento para tu consulta.') }}
              @endif
            </p>
          </td>
        </tr>

        {{-- Adjunto --}}
        <tr>
          <td style="padding:6px 34px 6px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-size:13px;background:#f6f8fa;border-radius:8px;">
              <tr>
                <td style="padding:12px 14px;color:#1f2a3a;font-weight:600;">
                  📎 {{ __('El documento va adjunto en PDF.') }}
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Nota de trazabilidad --}}
        <tr>
          <td style="padding:10px 34px 24px;">
            <p style="font-size:12px;line-height:1.6;color:#8a93a2;margin:12px 0 0;">
              {{ __('Esta es tu copia personal: lleva tu nombre marcado en el documento. Por favor no la reenvíes ni la publiques.') }}
            </p>
          </td>
        </tr>

        {{-- Pie --}}
        <tr>
          <td align="center" style="background:#333333;padding:16px;">
            <div style="color:#c2c2c2;font-size:12px;">&copy; CrewCare</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
