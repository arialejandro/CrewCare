{{-- EL CONTRATO · FASE 4 — aviso "es tu turno de firmar". Enlace para revisar y firmar + los datos que
     el firmante SÍ mira (contraprestación, situación fiscal, vigencia). Tono de la casa. --}}
@php
    $key = $key ?? [];
    $fmtDate = function ($d) {
        if (empty($d)) return null;
        try { return \Illuminate\Support\Carbon::parse($d)->format('d/m/Y'); } catch (\Throwable $e) { return (string) $d; }
    };
    $fee = (isset($key['fee']) && $key['fee'] !== null && $key['fee'] !== '')
        ? number_format((float) $key['fee'], 2) . ' ' . ($key['currency'] ?? 'MXN')
        : null;
    $vig = ($fmtDate($key['start'] ?? null) && $fmtDate($key['end'] ?? null))
        ? $fmtDate($key['start']) . ' — ' . $fmtDate($key['end'])
        : ($fmtDate($key['start'] ?? null) ?: null);
    $rows = array_filter([
        __('Concepto')        => $conceptLabel ?? null,
        __('Contraprestación') => $fee,
        __('RFC')             => $key['rfc'] ?? null,
        __('Situación fiscal') => trim(implode(' · ', array_filter([$natureLabel ?? null, $key['regime'] ?? null]))) ?: null,
        __('Vigencia')        => $vig,
    ]);
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
            <h1 style="font-size:22px;margin:0 0 4px;font-weight:700;">{{ __('Es tu turno de firmar') }}</h1>
            @php
                $ccLead = __('Tienes un contrato esperando tu firma');
                if (!empty($cargo)) { $ccLead .= ' (' . $cargo . ')'; }
                if (!empty($payeeName)) { $ccLead .= ', ' . __('a nombre de') . ' ' . $payeeName; }
                $ccLead .= '.';
            @endphp
            <p style="font-size:14px;line-height:1.7;color:#3d4757;margin:10px 0;">
              {{ __('Hola') }} <strong>{{ $toName ?: __('colega') }}</strong>,<br>
              {{ $ccLead }}
              {{ __('Revisa los datos y firma cuando estés listo:') }}
            </p>
          </td>
        </tr>

        {{-- Datos clave (nunca a ciegas) --}}
        @if(count($rows))
        <tr>
          <td style="padding:6px 34px 6px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-size:13px;background:#f6f8fa;border-radius:8px;">
              @foreach($rows as $k => $v)
                <tr>
                  <td style="padding:8px 12px;color:#8a93a2;width:38%;border-bottom:1px solid #eef1f5;">{{ $k }}</td>
                  <td style="padding:8px 12px;color:#1f2a3a;font-weight:600;border-bottom:1px solid #eef1f5;">{{ $v }}</td>
                </tr>
              @endforeach
            </table>
          </td>
        </tr>
        @endif

        {{-- Botón --}}
        <tr>
          <td align="center" style="padding:20px 34px 6px;">
            <a href="{{ $signUrl }}" style="display:inline-block;background:#ff0046;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 30px;border-radius:9px;">{{ __('Revisar y firmar') }}</a>
          </td>
        </tr>

        <tr>
          <td style="padding:10px 34px 24px;">
            <p style="font-size:12px;line-height:1.6;color:#8a93a2;margin:12px 0 0;">
              {{ __('Tu firma electrónica tiene la misma validez que la autógrafa (Cód. de Comercio 89 y 89 Bis; CCF 1811). Si no reconoces esta contratación, responde a este correo.') }}
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
