{{-- Aviso al HOD del departamento cuando una inspección produce PARO. Texto plano,
     sin datos de más: dice el veredicto, la herramienta y la vía de salida. --}}
@php
    $pathText = [
        'reemplazo' => 'Fuera de servicio: sale y se reemplaza.',
        'correccion_mismo_dia' => 'Corrección el mismo día; vuelve hoy si se corrige y se reinspecciona.',
    ];
@endphp
<div style="font-family: Arial, sans-serif; color: #1f2937; max-width: 560px;">
    <p style="font-size:15px;">Hola {{ $recipient_name ?? '' }},</p>

    @php $immediate = $immediate ?? true; @endphp
    <div style="background:{{ $immediate ? '#fef2f2' : '#fffbeb' }}; border:1px solid {{ $immediate ? '#fecaca' : '#fde68a' }}; border-radius:10px; padding:14px 16px; margin:12px 0;">
        <div style="font-weight:700; color:{{ $immediate ? '#991b1b' : '#92400e' }}; font-size:16px;">
            {{ $immediate ? 'PARO de herramienta' : 'Equipo NO AUTORIZADO' }}
        </div>
        <div style="color:{{ $immediate ? '#7f1d1d' : '#78350f' }}; font-size:13px; margin-top:2px;">
            @if(!$immediate){{ 'Nada está corriendo: hay ventana para corregir o sustituir antes del uso. ' }}@endif
            @if(!empty($resolution) && isset($pathText[$resolution])){{ $pathText[$resolution] }}@endif
        </div>
    </div>

    <table style="font-size:14px; border-collapse:collapse;">
        <tr><td style="color:#6b7280; padding:2px 10px 2px 0;">Herramienta</td><td><strong>{{ $tool_name }}</strong>@if(!empty($tool_model)) — {{ $tool_model }}@endif</td></tr>
        <tr><td style="color:#6b7280; padding:2px 10px 2px 0;">Departamento</td><td>{{ $department }}</td></tr>
        <tr><td style="color:#6b7280; padding:2px 10px 2px 0;">Inspeccionó</td><td>{{ $inspector }}</td></tr>
        <tr><td style="color:#6b7280; padding:2px 10px 2px 0;">Folio</td><td>{{ $folio }}</td></tr>
    </table>

    <p style="font-size:13px; color:#6b7280; margin-top:16px;">
        La herramienta está fuera de uso hasta que se cumpla la vía de salida y un safety levante el paro.
        Este aviso es un control interno de la producción.
    </p>
</div>
