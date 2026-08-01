<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Bitácora Médica - {{ $branding['brand_name'] ?? 'CrewCare' }}</title>
    <style>
        @page { margin: 22px 24px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #222;
            margin: 0;
        }
        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
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
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .header .sub {
            font-size: 10px;
            opacity: .95;
        }
        table.log {
            width: 100%;
            border-collapse: collapse;
        }
        table.log thead th {
            background: #333333;
            color: #ffffff;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 6px 7px;
            border: 1px solid #333333;
            text-align: left;
        }
        table.log td {
            border: 1px solid #d9d9d9;
            padding: 5px 7px;
            vertical-align: top;
            font-size: 10px;
        }
        table.log tbody tr:nth-child(even) td { background: #f7f7f7; }
        .day-band td {
            background: {{ $branding['primary_color'] ?? '#ff9900' }} !important;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: .5px;
            padding: 5px 7px;
        }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .name { font-weight: bold; }
        .stat {
            margin: 4px 0 12px;
            font-size: 10px;
            color: #555;
        }
        .empty {
            padding: 30px;
            text-align: center;
            color: #666;
            border: 1px solid #d9d9d9;
        }
    </style>
</head>
<body>

    <table class="header">
        <tr>
            <td>
                <div class="brand">{{ $branding['brand_name'] ?? 'CrewCare' }}</div>
                <div class="title">Bitácora Médica</div>
                <div class="sub">{{ $rangeLabel }}</div>
            </td>
        </tr>
    </table>

    {{-- El PDF declara SIEMPRE el filtro de médico aplicado: un consolidado recortado que no lo
         dijera se leería como si fuera toda la operación. --}}
    <div class="stat">Total de atenciones: <strong>{{ $totalConsultas }}</strong> · Médico: <strong>{{ $medicsLabel ?? 'Todos los médicos' }}</strong></div>

    @if($totalConsultas === 0)
        <div class="empty">No hay consultas médicas en el rango seleccionado.</div>
    @else
        <table class="log">
            <thead>
                <tr>
                    <th style="width:16%">Nombre</th>
                    <th style="width:15%">Departamento</th>
                    <th style="width:23%">Diagnóstico</th>
                    <th style="width:23%">Medicamento</th>
                    <th style="width:23%">Observaciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byDay as $date => $day)
                    <tr class="day-band">
                        <td colspan="5">{{ $day['label'] }}</td>
                    </tr>
                    @foreach($day['rows'] as $row)
                        <tr>
                            <td class="name">{{ $row['name'] }}</td>
                            <td>{{ $row['department'] }}</td>
                            <td>{{ $row['diagnosis'] }}</td>
                            {{-- Sin medicamento, medications ya trae el MANEJO; esta línea extra es
                                 para cuando hubo ambos (2026-07-24 · PASO 3/3). --}}
                            <td>
                                {!! nl2br(e($row['medications'])) !!}
                                @if(($row['management'] ?? '') !== '' && $row['medications'] !== $row['management'])
                                    <div style="font-style:italic; font-size:8pt;">{{ $row['management'] }}</div>
                                @endif
                            </td>
                            <td>{{ $row['observations'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
