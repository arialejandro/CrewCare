<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Materialidad - {{ $branding['brand_name'] ?? 'CrewCare' }}</title>
    <style>
        @page { margin: 26px 26px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #222; margin: 0; }
        .internal { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .internal td {
            background: #fff4e5; border: 1px solid #f0c890; color: #92400e; font-weight: bold;
            padding: 6px 10px; font-size: 9px; text-transform: uppercase; letter-spacing: .5px;
        }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header td { padding: 10px 14px; background: {{ $branding['primary_color'] ?? '#ff9900' }}; color: #ffffff; }
        .header .brand { font-size: 18px; font-weight: bold; letter-spacing: .5px; }
        .header .title { font-size: 12px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
        .header .sub { font-size: 10px; opacity: .95; }
        .stats { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .stats td { width: 50%; border: 1px solid #d9d9d9; padding: 8px 12px; }
        .stats .label { font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: #666; }
        .stats .value { font-size: 16px; font-weight: bold; color: {{ $branding['primary_color'] ?? '#ff9900' }}; }

        table.evi { width: 100%; border-collapse: collapse; }
        table.evi td { border: 1px solid #d9d9d9; padding: 8px; vertical-align: top; }
        table.evi tr { page-break-inside: avoid; }
        .evi-imgcell { width: 200px; text-align: center; background: #fafafa; }
        .evi-imgcell img { max-width: 184px; max-height: 150px; }
        .evi-noimg { color: #999; font-style: italic; padding: 30px 6px; font-size: 9px; }
        .evi-date { font-size: 11px; font-weight: bold; color: #111; margin-bottom: 4px; }
        .evi-note { font-size: 10px; color: #333; }
        .evi-note .lbl { color: #777; text-transform: uppercase; font-size: 8px; letter-spacing: .5px; display: block; margin-bottom: 2px; }
        .empty { padding: 30px; text-align: center; color: #666; border: 1px solid #d9d9d9; }
        thead { display: table-header-group; }
    </style>
</head>
<body>

    <table class="internal">
        <tr>
            <td>MATERIALIDAD — EVIDENCIA FISCAL · FECHA DE SERVIDOR (NO EDITABLE)</td>
        </tr>
    </table>

    <table class="header">
        <tr>
            <td>
                <div class="brand">{{ $branding['brand_name'] ?? 'CrewCare' }}</div>
                <div class="title">Materialidad — Comprobación fiscal</div>
                <div class="sub">{{ $rangeLabel }}</div>
            </td>
        </tr>
    </table>

    <table class="stats">
        <tr>
            <td>
                <div class="label">Evidencias en el rango</div>
                <div class="value">{{ $totalPhotos }}</div>
            </td>
            <td>
                <div class="label">Periodo</div>
                <div class="value" style="font-size:11px;">{{ $from }} — {{ $to }}</div>
            </td>
        </tr>
    </table>

    @if(empty($items))
        <div class="empty">No hay evidencia de materialidad en el rango seleccionado.</div>
    @else
        <table class="evi">
            <tbody>
                @foreach($items as $it)
                    <tr>
                        <td class="evi-imgcell">
                            @if(!empty($it['data_uri']))
                                <img src="{{ $it['data_uri'] }}" alt="evidencia">
                            @else
                                <div class="evi-noimg">Imagen no disponible</div>
                            @endif
                        </td>
                        <td>
                            <div class="evi-date">
                                {{ optional($it['created_at'])->format('d/m/Y H:i') ?? '—' }}
                            </div>
                            <div class="evi-note">
                                <span class="lbl">Concepto</span>
                                {{ !empty($it['note']) ? $it['note'] : 'Sin concepto' }}
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
