{{-- CERTIFICADO DE CIERRE (Fase 3c) — documento autónomo (para PDF por Browsershot o pantalla). Es una
     VISTA de datos ya sellados: no se sella por sí mismo. Sin layout, CSS embebido, tamaño carta. --}}
@php
    $stateLabel = [
        \App\Models\ContractEnvelope::STATUS_COMPLETED => __('Completado'),
        \App\Models\ContractEnvelope::STATUS_SENT      => __('En firma'),
        \App\Models\ContractEnvelope::STATUS_CANCELLED => __('Anulado'),
        \App\Models\ContractEnvelope::STATUS_DECLINED  => __('Rechazado'),
        \App\Models\ContractEnvelope::STATUS_EXPIRED   => __('Vencido'),
        \App\Models\ContractEnvelope::STATUS_DRAFT     => __('Borrador'),
    ][$envelope->status] ?? $envelope->status;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ __('Certificado de firma') }} · {{ $folio }}</title>
<style>
    @page { size: Letter; margin: 1.5cm 1.6cm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #1a1a1a;
        font-size: 11px; line-height: 1.5; }
    .ink   { color: #16324f; }
    .muted { color: #6b7280; }
    .mono  { font-family: "Courier New", ui-monospace, monospace; }

    .hd { border-bottom: 3px solid #16324f; padding-bottom: 10px; margin-bottom: 16px;
        display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; }
    .hd h1 { font-family: Georgia, "Times New Roman", serif; font-size: 20px; margin: 0; color: #16324f; letter-spacing: .2px; }
    .hd .sub { font-size: 10.5px; color: #6b7280; margin-top: 2px; }
    .folio { text-align: right; white-space: nowrap; }
    .folio .n { font-family: Georgia, serif; font-size: 18px; color: #16324f; font-weight: 700; }
    .chip { display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px;
        border: 1px solid #cbd5e1; color: #16324f; background: #f1f5f9; }

    h2 { font-size: 11.5px; text-transform: uppercase; letter-spacing: .6px; color: #16324f;
        border-bottom: 1px solid #e2e2e2; padding-bottom: 4px; margin: 18px 0 8px; }

    .grid { display: flex; flex-wrap: wrap; gap: 4px 28px; }
    .grid div { font-size: 11px; }
    .grid b { color: #16324f; }

    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 5px 7px; vertical-align: top; border-bottom: 1px solid #eef0f2; }
    th { font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; border-bottom: 1px solid #cbd5e1; }
    td { font-size: 10.5px; }
    .tag-ok  { color: #166534; font-weight: 700; }
    .tag-no  { color: #92400e; font-weight: 700; }

    .seal-list { list-style: none; margin: 0; padding: 0; }
    .seal-list li { padding: 6px 0; border-bottom: 1px solid #eef0f2; }
    .seal-list .who { font-weight: 600; color: #16324f; }
    .seal-list .h { font-size: 9.5px; word-break: break-all; color: #334155; }

    .integrity { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 9px; border-radius: 6px; }
    .integrity.ok  { background: #dcfce7; color: #166534; }
    .integrity.bad { background: #fee2e2; color: #991b1b; }

    .log { list-style: none; margin: 0; padding: 0; }
    .log li { display: flex; gap: 10px; font-size: 10px; padding: 2px 0; }
    .log .t { color: #6b7280; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .log .e { font-weight: 600; color: #1a1a1a; }
    .log .m { color: #6b7280; }

    .foot { margin-top: 22px; border-top: 1px solid #e2e2e2; padding-top: 12px;
        display: flex; gap: 16px; align-items: flex-start; }
    .foot .qr { flex: 0 0 auto; }
    .foot .qr svg { display: block; width: 96px; height: 96px; }
    .foot .txt { font-size: 9.5px; color: #6b7280; }
    .foot .txt .u { word-break: break-all; color: #16324f; }
</style>
</head>
<body>

    <div class="hd">
        <div>
            <h1>{{ __('Certificado de firma electrónica') }}</h1>
            <div class="sub">{{ __('CrewCare · Constancia del proceso de firma de un sobre de contrato') }}</div>
        </div>
        <div class="folio">
            <div class="n">{{ $folio }}</div>
            <div><span class="chip">{{ $stateLabel }}</span></div>
        </div>
    </div>

    <div class="grid">
        <div><b>{{ __('Contratado') }}:</b> {{ optional($envelope->contract->payee)->name ?? '—' }}</div>
        <div><b>UUID:</b> <span class="mono">{{ $envelope->uuid ?: '—' }}</span></div>
        @if($envelope->completed_at)<div><b>{{ __('Completado') }}:</b> {{ $envelope->completed_at->format('d/m/Y H:i') }}</div>@endif
        @if($envelope->isStoppedShort() && $envelope->resolution_reason)
            <div><b>{{ __('Motivo del cierre') }}:</b> {{ $envelope->resolution_reason }}</div>
        @endif
    </div>

    {{-- PARTES Y ACTOS DE ACEPTACIÓN --}}
    <h2>{{ __('Partes y actos de firma') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Parte') }}</th>
                <th>{{ __('Rol') }}</th>
                <th>{{ __('Visto') }}</th>
                <th>{{ __('Firmado') }}</th>
                <th>{{ __('IP') }}</th>
                <th>{{ __('Método') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($parties as $p)
                <tr>
                    <td>{{ $p['name'] }}@if($p['email'])<br><span class="muted">{{ $p['email'] }}</span>@endif</td>
                    <td>{{ $p['role'] }}</td>
                    <td>{{ $p['viewed_at'] ?: '—' }}</td>
                    <td>@if($p['signed'])<span class="tag-ok">{{ $p['signed_at'] ?: '✓' }}</span>@else<span class="tag-no">{{ __('sin firmar') }}</span>@endif</td>
                    <td class="mono">{{ $p['ip'] ?: '—' }}</td>
                    <td>{{ $p['method'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- SELLOS DIGITALES (hash completo, cotejable) --}}
    @php $sealed = array_filter($parties, fn ($p) => ! empty($p['hash'])); @endphp
    @if(count($sealed))
        <h2>{{ __('Sellos digitales de las firmas') }} <span class="muted" style="text-transform:none;letter-spacing:0">({{ __('HMAC-SHA256') }})</span></h2>
        <ul class="seal-list">
            @foreach($sealed as $p)
                <li>
                    <div class="who">{{ $p['name'] }} <span class="muted">· {{ $p['role'] }}</span></div>
                    <div class="h mono">{{ $p['hash'] }}</div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- DOCUMENTO FIRMADO (Fase 3b) --}}
    @if(! empty($signed['hash']))
        <h2>{{ __('Documento firmado (contrato con autógrafas)') }}</h2>
        <div class="grid">
            <div><b>{{ __('Sello del PDF') }} (SHA-256):</b> <span class="mono h">{{ $signed['hash'] }}</span></div>
            @if(! empty($signed['rendered_at']))<div><b>{{ __('Congelado') }}:</b> {{ $signed['rendered_at'] }}</div>@endif
        </div>
    @endif

    {{-- BITÁCORA (cadena inmutable) --}}
    <h2>
        {{ __('Bitácora de eventos') }}
        @if(! empty($events))
            <span class="integrity {{ $chain['ok'] ? 'ok' : 'bad' }}">
                {{ $chain['ok'] ? __('Cadena íntegra') . ' · ' . ($chain['count'] ?? count($events)) : __('Cadena alterada') }}
            </span>
        @endif
    </h2>
    @if(empty($events))
        <div class="muted">{{ __('Sin eventos registrados.') }}</div>
    @else
        <ul class="log">
            @foreach($events as $e)
                <li>
                    <span class="t">{{ $e['at'] }} {{ $e['tz'] }}</span>
                    <span class="e">{{ $e['label'] }}</span>
                    <span class="m">— {{ $e['actor'] }}@if($e['ip']) · {{ $e['ip'] }}@endif</span>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- PIE: QR al verificador público + validez --}}
    <div class="foot">
        @if($qr)<div class="qr">{!! $qr !!}</div>@endif
        <div class="txt">
            @if($verifyUrl)
                <div>{{ __('Verifica la integridad de este sobre, sin cuenta, escaneando el código o visitando:') }}</div>
                <div class="u mono">{{ $verifyUrl }}</div>
            @endif
            <div style="margin-top:8px">{{ __('La firma electrónica de este documento tiene la misma validez que la autógrafa conforme al Código de Comercio (arts. 89 y 89 Bis) y al Código Civil Federal (art. 1811). Este certificado comprueba el proceso de firma; no reproduce el contenido del contrato.') }}</div>
        </div>
    </div>

</body>
</html>
