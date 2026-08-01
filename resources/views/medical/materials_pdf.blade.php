<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Conteo de Medicamentos - {{ $branding['brand_name'] ?? 'CrewCare' }}</title>
    <style>
        @page { margin: 26px 26px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #222;
            margin: 0;
        }
        .internal {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .internal td {
            background: #fff4e5;
            border: 1px solid #f0c890;
            color: #92400e;
            font-weight: bold;
            padding: 6px 10px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header td {
            padding: 10px 14px;
            background: {{ $branding['primary_color'] ?? '#ff9900' }};
            color: #ffffff;
        }
        .header .brand {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: .5px;
        }
        .header .title {
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .header .sub { font-size: 10px; opacity: .95; }
        .stats {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .stats td {
            width: 50%;
            border: 1px solid #d9d9d9;
            padding: 8px 12px;
        }
        .stats .label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #666;
        }
        .stats .value {
            font-size: 16px;
            font-weight: bold;
            color: {{ $branding['primary_color'] ?? '#ff9900' }};
        }
        table.mat {
            width: 100%;
            border-collapse: collapse;
        }
        table.mat thead th {
            background: #333333;
            color: #ffffff;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 6px 8px;
            border: 1px solid #333333;
            text-align: left;
        }
        table.mat td {
            border: 1px solid #d9d9d9;
            padding: 5px 8px;
            font-size: 10px;
        }
        table.mat tbody tr:nth-child(even) td { background: #f7f7f7; }
        .qty { text-align: right; font-weight: bold; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .empty {
            padding: 30px;
            text-align: center;
            color: #666;
            border: 1px solid #d9d9d9;
        }
    </style>
</head>
<body>

    <table class="internal">
        <tr>
            <td>CONTEO DE CONSUMO — USO INTERNO/FISCAL, NO COSTEO</td>
        </tr>
    </table>

    <table class="header">
        <tr>
            <td>
                <div class="brand">{{ $branding['brand_name'] ?? 'CrewCare' }}</div>
                <div class="title">Conteo de Medicamentos (Uso Interno)</div>
                {{-- El PDF declara SIEMPRE el filtro de médico aplicado: un consolidado recortado
                     que no lo dijera se leería como si fuera toda la operación. --}}
                <div class="sub">{{ $rangeLabel }} · Médico: {{ $medicsLabel ?? 'Todos los médicos' }}</div>
            </td>
        </tr>
    </table>

    <table class="stats">
        <tr>
            <td>
                <div class="label">Atenciones</div>
                <div class="value">{{ $totalConsultas }}</div>
            </td>
            <td>
                <div class="label">Medicamentos distintos</div>
                <div class="value">{{ $distinctMeds }}</div>
            </td>
        </tr>
    </table>

    @if(empty($totals))
        <div class="empty">No hay consumo de medicamentos en el rango seleccionado.</div>
    @else
        <table class="mat">
            <thead>
                <tr>
                    <th style="width:40%">Medicamento</th>
                    <th style="width:20%">Dosis</th>
                    <th style="width:22%">Presentación</th>
                    <th style="width:18%" class="qty">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($totals as $item)
                    <tr>
                        <td><strong>{{ $item['name'] }}</strong></td>
                        <td>{{ $item['dosage'] }}</td>
                        <td>{{ $item['presentation'] }}</td>
                        <td class="qty">{{ $item['quantity'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
