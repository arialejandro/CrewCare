{{-- HOJA DE ACEPTACIÓN de cotización (dompdf). Render de datos YA sellados. El PDF/partidas
     originales NO se modifican; esta hoja los acompaña y prueba la aceptación. --}}
@php
    $money = fn ($n) => '$'.number_format((float) $n, 2).' MXN';
    $acceptorName = $acceptor ? trim(($acceptor->name ?? '').' '.($acceptor->lname ?? '')) : '—';
    $acceptorRole = ($acceptor && method_exists($acceptor, 'getRoleNames')) ? (optional($acceptor->getRoleNames())->first() ?: '') : '';
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
    .wrap { padding: 28px 34px; }
    h1 { font-size: 17px; margin: 0 0 2px; }
    .sub { color: #6b7280; font-size: 11px; margin: 0 0 14px; }
    table { width: 100%; border-collapse: collapse; }
    .kv td { padding: 4px 0; vertical-align: top; }
    .kv td.k { color: #6b7280; width: 34%; }
    .kv td.v { color: #111827; font-weight: bold; }
    .sec { margin-top: 16px; }
    .sec h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin: 0 0 6px; }
    .items { margin-top: 4px; }
    .items th, .items td { border-bottom: 1px solid #eef2f7; padding: 5px 4px; font-size: 11px; text-align: left; }
    .items th { color: #6b7280; }
    .items td.num, .items th.num { text-align: right; }
    .totals td { padding: 3px 4px; font-size: 12px; }
    .totals td.lbl { text-align: right; color: #6b7280; }
    .totals td.amt { text-align: right; font-weight: bold; width: 130px; }
    .hashbox { margin-top: 6px; padding: 8px 10px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; }
    .hash { font-family: DejaVu Sans Mono, monospace; font-size: 9px; word-break: break-all; color: #334155; }
    .sig { margin-top: 8px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px; width: 320px; }
    .sig img { max-width: 300px; max-height: 90px; }
    .sig .who { font-size: 11px; color: #111827; font-weight: bold; margin-top: 4px; }
    .sig .role { font-size: 10px; color: #6b7280; }
    .foot { margin-top: 18px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Hoja de aceptación de cotización</h1>
    <p class="sub">Prueba de la aceptación. El documento cotizado original la acompaña sin alteración.</p>

    <table class="kv">
        <tr><td class="k">Emisor</td><td class="v">{{ $q->emitter_name }}</td></tr>
        @if($q->emitter_email)<tr><td class="k">Correo</td><td class="v">{{ $q->emitter_email }}</td></tr>@endif
        @if(optional($version)->quotation_number)<tr><td class="k">Número de cotización</td><td class="v">{{ $version->quotation_number }}</td></tr>@endif
        @if($q->department)<tr><td class="k">Departamento</td><td class="v">{{ $q->department->name }}</td></tr>@endif
        @if($q->location_name)<tr><td class="k">Locación</td><td class="v">{{ $q->location_name }}</td></tr>@endif
        <tr><td class="k">Total aceptado</td><td class="v">{{ $money(optional($version)->total) }}</td></tr>
        <tr><td class="k">Aceptó</td><td class="v">{{ $acceptorName }}{{ $acceptorRole ? ' ('.$acceptorRole.')' : '' }}</td></tr>
        <tr><td class="k">Fecha de aceptación</td><td class="v">{{ optional($q->accepted_at)->format('d/m/Y H:i') }}</td></tr>
    </table>

    @if($version && $version->isItems() && $version->items->count())
        <div class="sec">
            <h2>Partidas (congeladas)</h2>
            <table class="items">
                <thead><tr><th>Descripción</th><th class="num">Cant.</th><th class="num">Días</th><th class="num">P. unit.</th><th class="num">Importe</th></tr></thead>
                <tbody>
                    @foreach($version->items as $it)
                        <tr>
                            <td>{{ $it->description }}@if($it->detail)<br><span style="color:#6b7280;font-size:10px">{{ $it->detail }}</span>@endif</td>
                            <td class="num">{{ rtrim(rtrim((string) $it->quantity, '0'), '.') }}</td>
                            <td class="num">{{ $it->days !== null ? rtrim(rtrim((string) $it->days, '0'), '.') : '—' }}</td>
                            <td class="num">{{ $money($it->unit_price) }}</td>
                            <td class="num">{{ $money($it->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif($version && $version->isPdf())
        <div class="sec">
            <h2>Documento cotizado</h2>
            <p style="font-size:11px;margin:2px 0">Archivo: <strong>{{ $version->pdf_original_name ?: 'cotizacion.pdf' }}</strong> (se conserva byte-intact).</p>
        </div>
    @endif

    @if($version)
    <table class="totals" style="margin-top:6px">
        <tr><td class="lbl">Subtotal</td><td class="amt">{{ $money($version->subtotal) }}</td></tr>
        <tr><td class="lbl">IVA{{ $version->iva_included ? ' (incluido)' : '' }}</td><td class="amt">{{ $money($version->iva_amount) }}</td></tr>
        <tr><td class="lbl"><strong>Total</strong></td><td class="amt">{{ $money($version->total) }}</td></tr>
    </table>
    @endif

    <div class="sec">
        <h2>Integridad</h2>
        <div class="hashbox">
            <div style="font-size:10px;color:#6b7280;margin-bottom:2px">Hash del documento aceptado (SHA-256)</div>
            <div class="hash">{{ $q->accepted_doc_hash }}</div>
            @if($signature)
                <div style="font-size:10px;color:#6b7280;margin:6px 0 2px">Sello del acuse (SHA-256)</div>
                <div class="hash">{{ $signature->document_hash }}</div>
            @endif
        </div>
        @if($verifyUrl)
            <p style="font-size:10px;color:#6b7280;margin-top:6px">Verifica en: {{ $verifyUrl }}</p>
        @endif
        @if($identicon)
            <div style="margin-top:6px">{!! $identicon !!}</div>
        @endif
    </div>

    @if($q->acceptance_signature_image)
    <div class="sig">
        <img src="{{ $q->acceptance_signature_image }}" alt="firma">
        <div class="who">{{ $acceptorName }}</div>
        <div class="role">{{ $acceptorRole ?: 'Autorización' }} · {{ optional($q->accepted_at)->format('d/m/Y H:i') }}</div>
    </div>
    @endif

    <div class="foot">
        Documento generado por CrewCare. La aceptación quedó sellada con firma digital (HMAC-SHA256);
        cualquier alteración posterior invalida el sello y se detecta en el verificador público.
    </div>
</div>
</body>
</html>
