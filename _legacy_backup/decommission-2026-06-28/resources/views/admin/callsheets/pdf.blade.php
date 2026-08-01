<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Llamado #{{ $callSheet->id }}</title>
    <style>
        /* DomPDF NO ejecuta CSS externo / Tailwind / JS: todo va inline aquí. Tamaño carta. */
        @page { margin: 1.5cm; }
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1f2937; font-size: 11px; margin: 0; }

        .header { background-color: #111827; color: #ffffff; padding: 16px; }
        .header h1 { color: #ff9900; font-size: 26px; margin: 0; text-transform: uppercase; font-style: italic; }
        .header .sub { letter-spacing: 4px; font-size: 11px; color: #e5e7eb; margin: 2px 0 0 0; }
        .header .prod { font-size: 11px; color: #d1d5db; margin: 6px 0 0 0; text-transform: uppercase; letter-spacing: 2px; }
        .header .title-chip { display: inline-block; background-color: #000; border: 1px solid #4b5563; padding: 4px 8px; margin-top: 8px; font-family: 'Courier New', monospace; font-weight: bold; text-transform: uppercase; }
        .header .meta { font-family: 'Courier New', monospace; font-size: 10px; color: #d1d5db; margin-top: 8px; text-transform: uppercase; letter-spacing: 2px; }

        .strip { width: 100%; border-collapse: collapse; background-color: #1f2937; color: #fff; }
        .strip td { padding: 8px; text-align: center; border-right: 1px solid #374151; }
        .strip .lbl { display: block; font-size: 8px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; }
        .strip .val { font-size: 14px; font-weight: bold; }

        .info-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .info-table td { vertical-align: top; padding: 8px; border: 1px solid #e5e7eb; width: 33.33%; }
        .info-table .lbl { font-size: 8px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .info-table .val { font-size: 11px; font-weight: bold; color: #111827; }
        .info-table .sub { font-size: 9px; color: #6b7280; }

        .section-title { font-size: 15px; font-weight: bold; font-style: italic; text-transform: uppercase; color: #1f2937; border-bottom: 2px solid #ff9900; padding-bottom: 4px; margin: 18px 0 10px 0; }

        .risk-table { width: 100%; border-collapse: collapse; }
        .risk-table td { width: 50%; vertical-align: top; padding: 4px; }
        .risk-card { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; }
        .risk-card .cat { font-weight: bold; font-size: 11px; color: #111827; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; color: #fff; }
        .badge-STPS { background-color: #15803d; }
        .badge-OSHA { background-color: #1d4ed8; }
        .badge-CSATF { background-color: #b91c1c; }
        .badge-AMAZON { background-color: #ff9900; color: #000; }
        .badge-NA { background-color: #6b7280; }
        .risk-card .code { font-family: 'Courier New', monospace; font-size: 9px; color: #6b7280; margin-top: 2px; }
        .risk-card .url { font-size: 9px; color: #b91c1c; word-break: break-all; margin-top: 4px; }

        .notes { border-left: 4px solid #e5e7eb; padding-left: 10px; font-size: 11px; color: #374151; text-align: justify; }

        .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>

    {{-- ===== HEADER ===== --}}
    <div class="header">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:top;">
                    @if($callSheet->production)
                        <p class="prod">{{ $callSheet->production->name }}</p>
                    @endif
                    <p class="meta">
                        {{ \Carbon\Carbon::parse($callSheet->sheet_date)->format('d M Y') }}
                        @if($callSheet->general_call) | CALL: {{ \Carbon\Carbon::parse($callSheet->general_call)->format('H:i') }} HRS @endif
                    </p>
                </td>
                <td style="vertical-align:top; text-align:right;">
                    <h1>EL LLAMADO</h1>
                    <p class="sub">CALL SHEET</p>
                    @if($callSheet->title)
                        <span class="title-chip">{{ $callSheet->title }}</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ===== STRIP ===== --}}
    <table class="strip">
        <tr>
            <td><span class="lbl">Shoot Day</span><span class="val">{{ $callSheet->shoot_day ?: '—' }}</span></td>
            <td><span class="lbl">Set</span><span class="val">{{ $callSheet->set_setting ?: '—' }}</span></td>
            <td><span class="lbl">Momento</span><span class="val">{{ $callSheet->day_part ?: '—' }}</span></td>
            <td style="border-right:none;"><span class="lbl">Clima</span><span class="val" style="font-size:11px;">{{ $callSheet->weather_note ?: '—' }}</span></td>
        </tr>
    </table>

    {{-- ===== LOCACIÓN / EMERGENCIA ===== --}}
    <table class="info-table">
        <tr>
            <td>
                <span class="lbl">Locación</span>
                <div class="val">{{ $callSheet->location_name ?: '—' }}</div>
                <div class="sub">{{ $callSheet->location_address }}</div>
                @if($callSheet->sunrise || $callSheet->sunset)
                <div class="sub">
                    @if($callSheet->sunrise) Amanecer: {{ \Carbon\Carbon::parse($callSheet->sunrise)->format('H:i') }} @endif
                    @if($callSheet->sunset) | Atardecer: {{ \Carbon\Carbon::parse($callSheet->sunset)->format('H:i') }} @endif
                </div>
                @endif
            </td>
            <td>
                <span class="lbl">Hospital / Médico</span>
                <div class="val">{{ $callSheet->nearest_hospital ?: '—' }}</div>
                <div class="sub">{{ $callSheet->hospital_address }}</div>
                <div class="sub">{{ $callSheet->ambulance_company }}@if($callSheet->emergency_phone) | Tel: {{ $callSheet->emergency_phone }} @endif</div>
            </td>
            <td>
                <span class="lbl">Punto de Reunión</span>
                <div class="val">{{ $callSheet->assembly_point ?: '—' }}</div>
            </td>
        </tr>
    </table>

    {{-- ===== BOLETINES DE SEGURIDAD (AUTO-ATTACH) ===== --}}
    <div class="section-title">Boletines de Seguridad del Día</div>

    @php $risks = is_array($callSheet->identified_risks) ? $callSheet->identified_risks : []; @endphp
    @if(count($risks) > 0)
        <table class="risk-table">
            @foreach(array_chunk($risks, 2) as $pair)
            <tr>
                @foreach($pair as $risk)
                <td>
                    <div class="risk-card">
                        <span class="cat">{{ $risk['category'] ?? 'Riesgo' }}</span><br>
                        <span class="badge badge-{{ $risk['badge'] ?? 'NA' }}">{{ $risk['badge'] ?? 'NA' }}</span>
                        <span class="code">{{ $risk['code'] ?? '' }}</span>
                        @if(!empty($risk['url']))
                            <div class="url">Boletín: {{ $risk['url'] }}</div>
                        @endif
                    </div>
                </td>
                @endforeach
                @if(count($pair) < 2)<td></td>@endif
            </tr>
            @endforeach
        </table>
    @else
        <p style="color:#6b7280; font-style:italic;">No se identificaron riesgos / boletines para este llamado.</p>
    @endif

    {{-- ===== NOTAS DE SEGURIDAD ===== --}}
    @if($callSheet->safety_notes)
        <div class="section-title">Notas de Seguridad</div>
        <div class="notes">{{ $callSheet->safety_notes }}</div>
    @endif

    {{-- ===== FOOTER ===== --}}
    <div class="footer">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:bottom;">
                    Elaborado por: <strong>{{ $callSheet->created_by ?: '—' }}</strong>
                    @if($callSheet->isSent()) | ENVIADO {{ \Carbon\Carbon::parse($callSheet->sent_at)->format('d/m/Y H:i') }} @endif
                </td>
                <td style="vertical-align:bottom; text-align:right;">
                    CALLSHEET-{{ $callSheet->id }}-{{ \Carbon\Carbon::parse($callSheet->sheet_date)->format('dmY') }} | CrewCare VER 1.0
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
