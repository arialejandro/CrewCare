{{-- EL INFOSHEET · aviso "hay un trato por autorizar". El capturista terminó la hoja de información y
     la envía a autorización; el autorizador entra, revisa y firma (lo que dispara el contrato). --}}
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f5f7;padding:0;margin:0;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;background-color:#ffffff;border-radius:12px;overflow:hidden;font-family:'Raleway',Segoe UI,Arial,sans-serif;">
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

        <tr>
          <td style="padding:22px 34px 6px;color:#1f2a3a;">
            <h1 style="font-size:22px;margin:0 0 4px;font-weight:700;">{{ __('Un trato espera tu autorización') }}</h1>
            <p style="font-size:14px;line-height:1.7;color:#3d4757;margin:10px 0;">
              {{ __('Hola') }} <strong>{{ $toName ?: __('colega') }}</strong>,<br>
              {{ __('Se capturó la hoja de información') }}@if(!empty($payeeName)) {{ __('de') }} <strong>{{ $payeeName }}</strong>@endif@if(!empty($puesto)) ({{ $puesto }})@endif.
              {{ __('Revisa los datos y autoriza con tu firma; al completarse las autorizaciones, el contrato se genera y se envía a firma.') }}
            </p>
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:20px 34px 6px;">
            <a href="{{ $url }}" style="display:inline-block;background:#ff0046;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 30px;border-radius:9px;">{{ __('Ver pendientes por autorizar') }}</a>
          </td>
        </tr>

        <tr>
          <td style="padding:10px 34px 24px;">
            <p style="font-size:12px;line-height:1.6;color:#8a93a2;margin:12px 0 0;">
              {{ __('También encuentras estos pendientes en tu bandeja "Infosheets por autorizar" dentro de CrewCare.') }}
            </p>
          </td>
        </tr>

        <tr>
          <td align="center" style="background:#333333;padding:16px;">
            <div style="color:#c2c2c2;font-size:12px;">&copy; CrewCare</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
