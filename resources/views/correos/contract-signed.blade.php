{{-- EL INFOSHEET · FASE 3.4 — correo al CONTRATADO cuando la ruta de firma se completa. Los PDF del
     paquete van ADJUNTOS; abajo el certificado (quién firmó, cuándo, integridad). Tono de la casa. --}}
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
            <h1 style="font-size:22px;margin:0 0 4px;font-weight:700;">{{ __('Tu contrato está firmado') }}</h1>
            <p style="font-size:14px;line-height:1.7;color:#3d4757;margin:10px 0;">
              {{ __('Hola') }} <strong>{{ $toName ?: __('colega') }}</strong>,<br>
              {{ __('Todas las partes firmaron tu paquete de contratación. Adjuntamos los documentos firmados para tu resguardo. Abajo encontrarás el certificado de firma con la constancia de integridad de cada firmante.') }}
            </p>
            @if($completedAt)
              <p style="font-size:13px;color:#6b7482;margin:4px 0 0;">{{ __('Firma completada:') }} <strong>{{ $completedAt }}</strong></p>
            @endif
          </td>
        </tr>

        {{-- Certificado de firma --}}
        <tr>
          <td style="padding:14px 34px 6px;">
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#8a93a2;font-weight:700;margin-bottom:8px;">{{ __('Certificado de firma') }}</div>
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-size:13px;">
              <thead>
                <tr style="background:#f6f8fa;color:#4a5568;">
                  <th align="left"  style="padding:8px 10px;border-bottom:1px solid #e6e9ee;">{{ __('Firmante') }}</th>
                  <th align="left"  style="padding:8px 10px;border-bottom:1px solid #e6e9ee;">{{ __('Firmado') }}</th>
                  <th align="left"  style="padding:8px 10px;border-bottom:1px solid #e6e9ee;">{{ __('Integridad') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($signers as $s)
                  <tr>
                    <td style="padding:9px 10px;border-bottom:1px solid #eef1f5;color:#1f2a3a;">
                      <strong>{{ $s['name'] ?: '—' }}</strong>
                      <div style="color:#8a93a2;font-size:12px;">{{ $s['role'] ?: '' }}</div>
                    </td>
                    <td style="padding:9px 10px;border-bottom:1px solid #eef1f5;color:#3d4757;">{{ $s['signed_at'] ?: '—' }}</td>
                    <td style="padding:9px 10px;border-bottom:1px solid #eef1f5;color:#15803d;">
                      @if($s['hash'])
                        <span style="font-size:12px;">&#10003; {{ __('Verificada') }}</span>
                        <div style="color:#9aa4b2;font-family:Consolas,monospace;font-size:11px;">{{ $s['hash'] }}…</div>
                      @else
                        <span style="color:#9aa4b2;font-size:12px;">—</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="3" style="padding:10px;color:#8a93a2;">{{ __('Sin firmantes registrados.') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:14px 34px 24px;">
            <p style="font-size:12px;line-height:1.6;color:#8a93a2;margin:12px 0 0;">
              {{ __('Este es un mensaje automático de una firma electrónica con validez jurídica (Cód. de Comercio 89 y 89 Bis; CCF 1811). Si no reconoces esta contratación, responde a este correo.') }}
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
