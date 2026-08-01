@extends('layouts.app')
@section('content')
{{-- (2026-07-13) MÓDULO 14 — i18n del documento vía ?lang=en|es SIN tocar rutas/middleware.
     Se fija el locale ANTES del bloque @php de abajo para que $ratingWord (y demás
     etiquetas estáticas migradas a __('reports.*')) resuelvan en el idioma pedido.
     Datos de BD y UI operativa (no-print) siguen igual. --}}
@php if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); } @endphp

{{-- Vista SHOW del Scouting H&S, homologada al estilo "magazine" del
     Daily Safety Report (admin/dailyreports/show.blade.php): Tailwind CDN,
     badges CSATF/OSHA/STPS, @media print y botón PDF nativo. --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { corePlugins: { preflight: false } };</script>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,300;0,400;0,700;1,900&family=Courier+Prime:wght@700&display=swap" rel="stylesheet">

<style>
    :root { --brand-primary: {{ $branding['primary_color'] ?? '#ff9900' }}; --brand-secondary: {{ $branding['secondary_color'] ?? '#1f2937' }}; --brand-accent: {{ $branding['accent_color'] ?? '#0ea5e9' }}; }
    .font-poster { font-family: 'Roboto Condensed', sans-serif; font-weight: 900; font-style: italic; text-transform: uppercase; }
    .font-slug { font-family: 'Courier Prime', monospace; }
    .badge-hs { display: inline-flex; align-items: center; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800; color: white; letter-spacing: 0.05em; height: 18px;}
    .badge-STPS { background-color: #15803d; }
    .badge-OSHA { background-color: #1d4ed8; }
    .badge-CSATF { background-color: #b91c1c; }

    @media print {
        @page { size: letter portrait; margin: 0; }
        body {
            margin: 0;
            padding-bottom: 150px;
            background-color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .no-print, .navbar, .sidebar, #sidebar { display: none !important; }
        .max-w-4xl { max-width: 100% !important; width: 100% !important; }
        .break-inside-avoid, .grid > div, .mb-8, p, h1, h2, h3, h4 {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
    }
</style>

@php
    // Chip de color por clasificación Amazon (L/M/H/E), print-safe.
    $ratingChip = function ($r) {
        $map = [
            'L' => 'background:#C0DD97;color:#173404;',
            'M' => 'background:#FAC775;color:#412402;',
            'H' => 'background:#F0997B;color:#4A1B0C;',
            'E' => 'background:#E24B4A;color:#ffffff;',
        ];
        return $map[$r] ?? 'background:#f3f4f6;color:#6b7280;';
    };
    $ratingWord = ['L' => __('reports.rating_low'), 'M' => __('reports.rating_medium'), 'H' => __('reports.rating_high'), 'E' => __('reports.rating_very_high')];

    // Normaliza cada fila de risk_assessment al esquema nuevo (tolerando el viejo:
    // label/risk(Bajo/Medio/Alto)/note → hazard/rating/control).
    $oldRiskMap = ['Bajo' => 'L', 'Medio' => 'M', 'Alto' => 'H', 'Extremo' => 'E'];
    $rows = [];
    foreach ((is_array($report->risk_assessment) ? $report->risk_assessment : []) as $h) {
        $rows[] = [
            'hazard'      => $h['hazard'] ?? ($h['label'] ?? ''),
            'likelihood'  => $h['likelihood'] ?? null,
            'consequence' => $h['consequence'] ?? null,
            'rating'      => $h['rating'] ?? ($oldRiskMap[$h['risk'] ?? ''] ?? null),
            'control'     => $h['control'] ?? ($h['note'] ?? null),
            'residual'    => $h['residual'] ?? null,
            'personnel'   => $h['personnel'] ?? null,
            'badge'       => $h['badge'] ?? null,
            'code'        => $h['code'] ?? null,
            'url'         => $h['url'] ?? null,
        ];
    }
@endphp

{{-- Barra de acciones (no se imprime) --}}
{{-- flex-wrap (2026-07, fix responsive): barra de acciones (no-print) envolvía en ~375px. --}}
<div class="max-w-4xl mx-auto mb-4 flex flex-wrap gap-2 justify-between items-center no-print mt-4">
    <a href="{{ route('scoutings.index') }}" class="inline-flex items-center gap-1 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded font-semibold shadow-sm hover:bg-gray-100 no-underline">&larr; Volver</a>
    <div class="flex items-center gap-2">
        @can('locations.create')
            <a href="{{ route('scoutings.edit', $report->id) }}" class="inline-flex items-center gap-1 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded font-semibold shadow-sm hover:bg-gray-100 no-underline">✏️ Editar</a>
        @endcan
        <button onclick="window.print();" class="inline-flex items-center gap-1 bg-red-600 text-white px-4 py-2 rounded font-bold shadow hover:bg-red-700">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
    </div>
</div>

{{-- Feedback de validación / flash (no-print). CLAVE: al editar y marcar estatus
     "Final", el update puede lanzar el bloqueo PDCA (ActionItems abiertos) o la
     ValidationException SB-132; sin esto el error viaja silencioso. --}}
<div class="max-w-4xl mx-auto no-print">
    @include('componentes._form-feedback')
</div>

<div class="max-w-4xl mx-auto bg-white shadow-2xl min-h-screen overflow-hidden mb-10">

    {{-- ===== HERO ===== --}}
    <div class="relative w-full h-72 bg-gray-900 overflow-hidden">
        @if($report->main_image_path)
            <img src="{{ $report->main_image_path }}" class="absolute inset-0 w-full h-full object-cover opacity-60 grayscale-[30%]">
        @else
            <div class="absolute inset-0 bg-gray-800"></div>
        @endif

        <div class="relative z-10 p-8 flex justify-between items-start h-full">
            <div class="flex flex-col items-center">
                @include('componentes._doc-hero-logo', ['logoWidth' => 280])
            </div>
            <div class="text-right text-white">
                <p class="text-xl font-bold tracking-[0.3em] mt-1 text-white/90 font-poster not-italic">{{ __('reports.scouting_title') }}</p>
                <div class="mt-3 inline-block bg-black/70 border border-gray-600 backdrop-blur-md px-4 py-2 transform skew-x-[-6deg]">
                    <div class="transform skew-x-[6deg] font-slug text-lg font-bold tracking-tight text-white uppercase flex items-center gap-2">
                        <span class="text-[var(--brand-primary)]">{{ $report->loc_setting === 'Mixto' ? 'Int./Ext.' : ($report->loc_setting ?: 'LOC') }}</span>
                        <span>{{ $report->location_name }}</span>
                        @if($report->shoot_time)
                            <span class="text-gray-400">-</span>
                            <span class="{{ str_contains($report->shoot_time, 'Noche') ? 'text-blue-200' : 'text-yellow-200' }}">{{ $report->shoot_time }}</span>
                        @endif
                    </div>
                </div>
                <div class="mt-2 text-xs font-mono opacity-80 uppercase tracking-widest">
                    {{ $report->production_name ?: __('reports.scouting_no_production') }}
                    @if($report->date_shoot) | {{ __('reports.label_shoot') }}: {{ $report->date_shoot->format('d M Y') }} @endif
                </div>
            </div>
        </div>

        {{-- Tira de complejidad + flag SB132 --}}
        <div class="absolute bottom-0 w-full bg-black/90 backdrop-blur border-t-4 border-[var(--brand-primary)] flex h-14">
            <div class="flex-grow flex items-center justify-around px-4 border-r border-gray-700">
                <div class="text-center"><span class="block text-[9px] text-gray-400 uppercase tracking-widest">{{ __('reports.label_scene') }}</span><span class="text-sm font-bold text-white">{{ $report->scene ?: '—' }}</span></div>
                <div class="text-center"><span class="block text-[9px] text-gray-400 uppercase tracking-widest">{{ __('reports.label_complexity') }}</span><span class="text-sm font-bold text-white">{{ $report->complexity ?: '—' }}</span></div>
                <div class="text-center"><span class="block text-[9px] text-gray-400 uppercase tracking-widest">{{ __('reports.label_status') }}</span><span class="text-sm font-bold text-green-400 uppercase">{{ $report->status }}</span></div>
            </div>
            <div class="w-1/3 min-w-[180px] flex items-center justify-center px-4 {{ $report->requires_specific_ra ? 'bg-red-700' : 'bg-gray-800' }}">
                @if($report->requires_specific_ra)
                    <span class="text-white text-xs font-bold tracking-wide">⚠ SB132</span>
                @else
                    <span class="text-gray-400 text-xs">{{ __('reports.scouting_no_sb132') }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== TIRA DE EMERGENCIA (quick-read) ===== --}}
    <div class="bg-white border-b border-gray-200 relative z-20 shadow-sm">
        <div class="flex divide-x divide-gray-100">
            <div class="w-1/3 p-3 flex items-start gap-3 bg-red-50/30">
                <div class="bg-red-50 p-2 rounded text-red-600 shrink-0">🏥</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-red-400 tracking-wider">{{ __('reports.label_hospital') }}</h4>
                    <p class="text-xs font-bold text-gray-800">{{ $report->nearest_hospital ?: '—' }}</p>
                    <p class="text-[10px] text-gray-500 leading-tight">{{ $report->hospital_address }}</p>
                    @if($report->hospital_eta)<p class="text-[10px] text-gray-500">ETA: {{ $report->hospital_eta }}</p>@endif
                </div>
            </div>
            <div class="w-1/3 p-3 flex items-start gap-3">
                <div class="bg-blue-50 p-2 rounded text-blue-600 shrink-0">🚑</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.scouting_label_support') }}</h4>
                    <p class="text-xs font-bold text-gray-800">{{ $report->ambulance_company ?: '—' }}</p>
                    <p class="text-[10px] text-gray-500">{{ __('reports.label_phone') }}: {{ $report->emergency_phone ?: '—' }}</p>
                </div>
            </div>
            <div class="w-1/3 p-3 flex items-start gap-3">
                <div class="bg-amber-50 p-2 rounded text-amber-600 shrink-0">📍</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.scouting_label_assembly_point') }}</h4>
                    <p class="text-xs font-bold text-gray-800">{{ $report->assembly_point ?: '—' }}</p>
                    <p class="text-[10px] text-gray-500 leading-tight">{{ $report->emergency_access }}</p>
                </div>
            </div>
        </div>

        {{-- Coordenadas GPS del scouting: dirección + link discreto al mapa --}}
        @if($report->latitude && $report->longitude)
            <div class="px-4 py-1.5 bg-gray-50 border-t border-gray-100 flex items-center gap-2">
                <span class="text-[10px] text-gray-500 truncate">📍 {{ $report->location_address ?: __('reports.scouting_gps_registered') }}</span>
                <a href="https://www.google.com/maps?q={{ $report->latitude }},{{ $report->longitude }}" target="_blank" rel="noopener"
                   class="no-print text-[10px] font-bold text-blue-600 hover:underline shrink-0 no-underline">{{ __('reports.label_view_map') }} ↗</a>
            </div>
        @endif
    </div>

    <div class="p-8">

        {{-- ===== BANNER SB132 ===== --}}
        @if($report->requires_specific_ra)
            <div class="mb-6 bg-red-600 text-white px-5 py-3 rounded shadow-lg flex items-center gap-3 break-inside-avoid">
                <span class="text-2xl">⚠</span>
                <div>
                    <p class="font-poster text-lg leading-none">{{ __('reports.scouting_sb132_banner_title') }}</p>
                    <p class="text-xs opacity-90 mt-1">{{ __('reports.scouting_sb132_banner_desc') }}</p>
                </div>
            </div>
        @endif

        {{-- ===== DESGLOSE SB-132 (detalle estructurado) =====
             Solo si el owner ya aplicó la columna sb132_details (guarda de esquema)
             y trae datos. Si requires_specific_ra=true pero aún no hay columna/datos,
             el banner rojo de arriba sigue cubriendo el aviso. --}}
        @if($report->requires_specific_ra
            && \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'sb132_details')
            && is_array($report->sb132_details)
            && count($report->sb132_details))
            @php
                $sb = $report->sb132_details;
                // Etiquetas de actividad SB-132. 'fuego' y 'altura' son NUEVAS: sus claves
                // de traducción viven en resources/lang/*/reports.php (fuera de este cambio),
                // así que se resuelven con guarda Lang::has y un literal por locale de respaldo
                // (evita imprimir la clave cruda si aún no se agregó la traducción).
                $en = app()->getLocale() === 'en';
                $actLabels = [
                    'armas'     => __('reports.scouting_activity_armas'),
                    'pirotecnia'=> __('reports.scouting_activity_pirotecnia'),
                    'stunts'    => __('reports.scouting_activity_stunts'),
                    'aereo'     => __('reports.scouting_activity_aereo'),
                    'agua'      => __('reports.scouting_activity_agua'),
                    'off-road'  => __('reports.scouting_activity_offroad'),
                    'fuego'     => \Illuminate\Support\Facades\Lang::has('reports.scouting_activity_fuego')
                                    ? __('reports.scouting_activity_fuego')
                                    : ($en ? 'Open flame / fire' : 'Fuego abierto / llamas'),
                    'altura'    => \Illuminate\Support\Facades\Lang::has('reports.scouting_activity_altura')
                                    ? __('reports.scouting_activity_altura')
                                    : ($en ? 'Work at height / rigging' : 'Trabajo en altura / rigging'),
                ];
                $acts = [];
                foreach ((is_array($sb['activity_type'] ?? null) ? $sb['activity_type'] : []) as $a) {
                    $acts[] = $actLabels[$a] ?? ucfirst(str_replace(['-', '_'], ' ', $a));
                }
                $cpr = $sb['certified_personnel_required'] ?? null;
                $cprTxt = is_bool($cpr) ? ($cpr ? __('reports.label_yes') : __('reports.label_no')) : (($cpr === null || $cpr === '') ? '—' : $cpr);
                $sceneNo = $sb['scene_number'] ?? null;
            @endphp
            <div class="mb-6 border-2 border-red-600 rounded shadow-lg break-inside-avoid overflow-hidden">
                <div class="bg-red-600 text-white px-4 py-2 flex items-center gap-2">
                    <span class="text-lg">⚠</span>
                    <p class="font-poster text-base leading-none">{{ __('reports.scouting_sb132_detail_title') }}</p>
                </div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4 bg-red-50">
                    <div class="sm:col-span-3">
                        <p class="text-[9px] uppercase font-bold text-red-500 tracking-wider mb-1">{{ __('reports.scouting_sb132_activities_declared') }}</p>
                        <p class="text-sm font-semibold text-gray-800">{{ count($acts) ? implode(', ', $acts) : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase font-bold text-red-500 tracking-wider mb-1">{{ __('reports.label_scene') }}</p>
                        <p class="text-sm font-semibold text-gray-800">{{ ($sceneNo !== null && $sceneNo !== '') ? $sceneNo : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase font-bold text-red-500 tracking-wider mb-1">{{ __('reports.scouting_sb132_certified_personnel') }}</p>
                        <p class="text-sm font-semibold text-gray-800">{{ $cprTxt }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ===== TABLA DE PELIGROS (formato Amazon MGM) ===== --}}
        <div class="mb-3 flex justify-between items-end border-b border-gray-200 pb-2">
            <h3 class="font-poster text-xl text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.scouting_section_risk_assessment') }}
            </h3>
            <a href="{{ route('scoutings.amazon', $report->id) }}"
               class="no-print inline-flex items-center gap-1 text-[11px] font-bold text-gray-600 border border-gray-300 rounded px-2 py-1 hover:bg-gray-100 no-underline">
                {{ __('reports.scouting_view_amazon_format') }} ↗
            </a>
        </div>

        @if(count($rows))
            <div class="overflow-x-auto mb-8">
                <table class="w-full text-xs border border-gray-200" style="min-width:640px;">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-[9px]">
                        <tr>
                            <th class="text-left p-2">{{ __('reports.scouting_th_hazard') }}</th>
                            <th class="text-center p-2" title="{{ __('reports.label_probability') }}">P</th>
                            <th class="text-center p-2" title="{{ __('reports.label_consequence') }}">C</th>
                            <th class="text-center p-2">{{ __('reports.scouting_th_classification') }}</th>
                            <th class="text-left p-2">{{ __('reports.scouting_th_controls') }}</th>
                            <th class="text-center p-2">{{ __('reports.scouting_th_residual') }}</th>
                            <th class="text-left p-2">{{ __('reports.scouting_th_personnel') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="border-t border-gray-100 break-inside-avoid align-top">
                                <td class="p-2 font-semibold text-gray-800">
                                    {{ $row['hazard'] ?: '—' }}
                                    @if(!empty($row['badge']))
                                        <div class="mt-1 flex items-center gap-1 flex-wrap">
                                            <span class="badge-hs badge-{{ $row['badge'] }}">{{ $row['badge'] }}</span>
                                            <span class="text-[9px] text-gray-400 font-mono">{{ $row['code'] }}</span>
                                            @if(!empty($row['url']))
                                                <a href="{{ $row['url'] }}" target="_blank" rel="noopener" class="no-print text-[9px] font-bold text-red-700 hover:underline">📄 {{ __('reports.label_bulletin') }}</a>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="p-2 text-center font-bold text-gray-700">{{ $row['likelihood'] ?: '—' }}</td>
                                <td class="p-2 text-center font-bold text-gray-700">{{ $row['consequence'] ?: '—' }}</td>
                                <td class="p-2 text-center">
                                    <span class="inline-block font-bold rounded px-1.5 py-0.5 text-[10px]" style="{{ $ratingChip($row['rating']) }}">
                                        {{ $row['rating'] ? $row['rating'].' · '.($ratingWord[$row['rating']] ?? '') : '—' }}
                                    </span>
                                </td>
                                <td class="p-2 text-gray-600 leading-snug">{{ $row['control'] ?: '—' }}</td>
                                <td class="p-2 text-center">
                                    @if($row['residual'])
                                        <span class="inline-block font-bold rounded px-1.5 py-0.5 text-[10px]" style="{{ $ratingChip($row['residual']) }}">{{ $row['residual'] }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="p-2 text-gray-600 leading-snug">{{ $row['personnel'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 text-sm italic mb-8">{{ __('reports.scouting_empty_risk_assessment') }}</p>
        @endif

        {{-- ===== RESUMEN EJECUTIVO ===== --}}
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.dsr_section_executive_summary') }}
            </h3>
            <div class="text-sm text-gray-600 leading-relaxed text-justify border-l-4 border-gray-100 pl-4">
                <p>{{ $report->exec_summary ?: __('reports.scouting_empty_exec_summary') }}</p>
            </div>
        </div>

        {{-- ===== VIABILIDAD ===== --}}
        @php $viab = is_array($report->viability_checklist) ? $report->viability_checklist : []; @endphp
        @if(count($viab))
            <div class="mb-8 break-inside-avoid">
                <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.scouting_section_viability') }}
                </h3>
                <table class="w-full text-xs border border-gray-200">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-[10px]">
                        <tr>
                            <th class="text-left p-2">{{ __('reports.scouting_th_area') }}</th>
                            <th class="text-left p-2">{{ __('reports.label_status') }}</th>
                            <th class="text-left p-2">{{ __('reports.label_responsible') }}</th>
                            <th class="text-left p-2">{{ __('reports.scouting_th_note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($viab as $v)
                            <tr class="border-t border-gray-100">
                                <td class="p-2 font-semibold text-gray-700">{{ $v['area'] ?? '' }}</td>
                                <td class="p-2">
                                    @php $st = $v['status'] ?? ''; @endphp
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold
                                        {{ $st === 'OK' ? 'bg-green-100 text-green-700' : ($st === 'Pendiente' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $st ?: '—' }}
                                    </span>
                                </td>
                                <td class="p-2 text-gray-600">{{ $v['responsible'] ?? '' }}</td>
                                <td class="p-2 text-gray-600">{{ $v['note'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ===== ACUERDOS ===== --}}
        @php $agr = is_array($report->agreements) ? $report->agreements : []; @endphp
        @if(count($agr))
            <div class="mb-8 break-inside-avoid">
                <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.scouting_section_agreements') }}
                </h3>
                <table class="w-full text-xs border border-gray-200">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-[10px]">
                        <tr>
                            <th class="text-left p-2">{{ __('reports.scouting_th_agreement') }}</th>
                            <th class="text-left p-2">{{ __('reports.label_responsible') }}</th>
                            <th class="text-left p-2">{{ __('reports.label_date') }}</th>
                            <th class="text-left p-2">{{ __('reports.label_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($agr as $a)
                            <tr class="border-t border-gray-100">
                                <td class="p-2 font-semibold text-gray-700">{{ $a['item'] ?? '' }}</td>
                                <td class="p-2 text-gray-600">{{ $a['responsible'] ?? '' }}</td>
                                <td class="p-2 text-gray-600">{{ $a['date'] ?? '' }}</td>
                                <td class="p-2 text-gray-600">{{ $a['status'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ===== ACCIONES CORRECTIVAS (PDCA) =====
             El Scouting suele resolver con sus "Acuerdos", así que este panel puede
             venir vacío: en ese caso NO se muestra. Doble guarda: tabla presente
             (por si el owner no aplicó el SQL) + que existan items. --}}
        @if(\Illuminate\Support\Facades\Schema::hasTable('action_items') && $report->actionItems->count())
            <div class="mb-8 break-inside-avoid">
                <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.section_corrective_actions') }}
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border border-gray-200" style="min-width:640px;">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-[10px]">
                            <tr>
                                <th class="text-left p-2">{{ __('reports.scouting_th_corrective_action') }}</th>
                                <th class="text-left p-2">{{ __('reports.label_responsible') }}</th>
                                <th class="text-center p-2">{{ __('reports.label_due') }}</th>
                                <th class="text-center p-2">{{ __('reports.label_status') }}</th>
                                <th class="text-center p-2">{{ __('reports.label_source') }}</th>
                                @can('hazards.manage')<th class="text-center p-2 no-print">{{ __('reports.label_action') }}</th>@endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report->actionItems as $ai)
                                @php
                                    $statusMap = [
                                        'open'        => [__('reports.status_open'),        'bg-amber-100 text-amber-700'],
                                        'in_progress' => [__('reports.status_in_progress'), 'bg-blue-100 text-blue-700'],
                                        'closed'      => [__('reports.status_closed'),      'bg-green-100 text-green-700'],
                                    ];
                                    $stInfo  = $statusMap[$ai->status] ?? [$ai->status, 'bg-gray-100 text-gray-600'];
                                    $overdue = method_exists($ai, 'isOverdue') && $ai->isOverdue();
                                @endphp
                                <tr class="border-t border-gray-100 align-top break-inside-avoid {{ $overdue ? 'bg-red-50/40' : '' }}">
                                    <td class="p-2 text-gray-700 leading-snug">{{ $ai->description ?: '—' }}</td>
                                    <td class="p-2 text-gray-600">{{ $ai->owner ? $ai->owner->name : '—' }}</td>
                                    <td class="p-2 text-center whitespace-nowrap {{ $overdue ? 'text-red-700 font-bold' : 'text-gray-600' }}">
                                        {{ $ai->due_date ? $ai->due_date->format('d M Y') : '—' }}
                                        @if($overdue)<span class="block text-[9px] uppercase tracking-wide">{{ __('reports.label_overdue') }}</span>@endif
                                    </td>
                                    <td class="p-2 text-center">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $stInfo[1] }}">{{ $stInfo[0] }}</span>
                                    </td>
                                    <td class="p-2 text-center text-[10px] text-gray-400 uppercase">{{ $ai->source === 'auto' ? __('reports.label_auto_short') : __('reports.label_manual') }}</td>
                                    @can('hazards.manage')
                                        <td class="p-2 text-center no-print">
                                            @if(in_array($ai->status, ['open', 'in_progress'], true))
                                                <form action="{{ route('action_items.close', $ai->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold px-2.5 py-1 rounded bg-green-600 text-white hover:bg-green-700">{{ __('reports.label_mark_closed') }}</button>
                                                </form>
                                            @else
                                                <form action="{{ route('action_items.reopen', $ai->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-100">{{ __('reports.label_reopen') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ===== NOTAS OPERATIVAS ===== --}}
        @if($report->operational_notes)
            <div class="mb-8">
                <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.scouting_section_operational_notes') }}
                </h3>
                <div class="text-sm text-gray-600 leading-relaxed text-justify border-l-4 border-gray-100 pl-4">
                    <p>{{ $report->operational_notes }}</p>
                </div>
            </div>
        @endif

        {{-- ===== IMÁGENES ADICIONALES ===== --}}
        @php $imgs = $report->additionalImagesList(); @endphp
        @if(count($imgs))
            <div class="mb-8 break-inside-avoid">
                <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_photo_evidence') }}
                </h3>
                <div class="grid grid-cols-3 gap-3">
                    @foreach($imgs as $img)
                        <figure class="m-0 break-inside-avoid">
                            <img src="{{ $img['path'] }}" class="w-full h-32 object-cover rounded border border-gray-200">
                            @if(!empty($img['caption']))
                                <figcaption class="text-xs text-gray-600 mt-1 leading-snug">{{ $img['caption'] }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ===== INVENTARIO, LOGÍSTICA, EPP + INTEGRIDAD (módulos 6, 8 y 9) =====
             Todo GATED por Schema::hasColumn / hasTable: en prod, sin el SQL de
             cimientos, ni las columnas nuevas ni la tabla de firmas existen → la
             sección (o el bloque interno) simplemente no se muestra. --}}
        @php
            $hasMaxHeadcount = \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'max_headcount');
            $hasEmergInv     = \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'emergency_equipment_inventory');
            $hasLogistics    = \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'logistics_facilities');
            $hasReqPpe       = \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'required_ppe');

            $maxHeadcount = $hasMaxHeadcount ? $report->max_headcount : null;
            $eei = ($hasEmergInv && is_array($report->emergency_equipment_inventory)) ? $report->emergency_equipment_inventory : [];
            $lf  = ($hasLogistics && is_array($report->logistics_facilities)) ? $report->logistics_facilities : [];
            $ppe = ($hasReqPpe && is_array($report->required_ppe)) ? $report->required_ppe : [];

            $eeiFire = $eei['fire_extinguishers'] ?? null;
            $eeiKits = $eei['first_aid_kits'] ?? null;
            $eeiAed  = !empty($eei['aed']);
            $lfHyd   = $lf['hydration_stations'] ?? null;
            $lfRest  = !empty($lf['restrooms']);
            $lfShade = !empty($lf['shade_areas']);

            $hasHeadcount = ($maxHeadcount !== null && $maxHeadcount !== '');
            $hasEeiData   = ($eeiFire !== null && $eeiFire !== '') || ($eeiKits !== null && $eeiKits !== '') || $eeiAed;
            $hasLfData    = $lfRest || $lfShade || ($lfHyd !== null && $lfHyd !== '');
            $hasPpeData   = count($ppe) > 0;

            // Firma digital / no-repudio: null si no hay tabla o no hay firmas registradas.
            $sigVerified = \Illuminate\Support\Facades\Schema::hasTable('digital_signatures') ? $report->verifyLatestSignature() : null;
            $latestSig   = ($sigVerified !== null) ? $report->signatures()->latest('id')->first() : null;
        @endphp
        @if($hasHeadcount || $hasEeiData || $hasLfData || $hasPpeData || $sigVerified !== null)
            <div class="mb-8 break-inside-avoid">
                <h3 class="font-poster text-xl mb-3 text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.scouting_section_inventory') }}
                </h3>

                @if($hasHeadcount || $hasEeiData || $hasLfData || $hasPpeData)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- Aforo + equipo de emergencia --}}
                        @if($hasHeadcount || $hasEeiData)
                            <div class="border border-gray-200 rounded p-3">
                                <p class="text-[9px] uppercase font-bold text-gray-400 tracking-wider mb-2">{{ __('reports.scouting_label_headcount_equipment') }}</p>
                                <ul class="text-xs text-gray-700 space-y-1">
                                    @if($hasHeadcount)
                                        <li class="flex justify-between gap-2"><span class="text-gray-500">{{ __('reports.scouting_label_max_headcount') }}</span><span class="font-bold">{{ $maxHeadcount }}</span></li>
                                    @endif
                                    @if($eeiFire !== null && $eeiFire !== '')
                                        <li class="flex justify-between gap-2"><span class="text-gray-500">{{ __('reports.scouting_label_extinguishers') }}</span><span class="font-bold">{{ $eeiFire }}</span></li>
                                    @endif
                                    @if($eeiKits !== null && $eeiKits !== '')
                                        <li class="flex justify-between gap-2"><span class="text-gray-500">{{ __('reports.scouting_label_first_aid_kits') }}</span><span class="font-bold">{{ $eeiKits }}</span></li>
                                    @endif
                                    @if($hasEeiData)
                                        <li class="flex justify-between gap-2"><span class="text-gray-500">DEA / AED</span>
                                            <span class="font-bold {{ $eeiAed ? 'text-green-700' : 'text-gray-400' }}">{{ $eeiAed ? __('reports.label_yes') : __('reports.label_no') }}</span></li>
                                    @endif
                                </ul>
                            </div>
                        @endif

                        {{-- Instalaciones y logística --}}
                        @if($hasLfData)
                            <div class="border border-gray-200 rounded p-3">
                                <p class="text-[9px] uppercase font-bold text-gray-400 tracking-wider mb-2">{{ __('reports.scouting_label_facilities_logistics') }}</p>
                                <ul class="text-xs text-gray-700 space-y-1">
                                    <li class="flex justify-between gap-2"><span class="text-gray-500">{{ __('reports.scouting_label_restrooms') }}</span>
                                        <span class="font-bold {{ $lfRest ? 'text-green-700' : 'text-gray-400' }}">{{ $lfRest ? __('reports.label_yes') : __('reports.label_no') }}</span></li>
                                    @if($lfHyd !== null && $lfHyd !== '')
                                        <li class="flex justify-between gap-2"><span class="text-gray-500">{{ __('reports.scouting_label_hydration') }}</span><span class="font-bold">{{ $lfHyd }}</span></li>
                                    @endif
                                    <li class="flex justify-between gap-2"><span class="text-gray-500">{{ __('reports.scouting_label_shade') }}</span>
                                        <span class="font-bold {{ $lfShade ? 'text-green-700' : 'text-gray-400' }}">{{ $lfShade ? __('reports.label_yes') : __('reports.label_no') }}</span></li>
                                </ul>
                            </div>
                        @endif

                        {{-- EPP requerido --}}
                        @if($hasPpeData)
                            <div class="border border-gray-200 rounded p-3">
                                <p class="text-[9px] uppercase font-bold text-gray-400 tracking-wider mb-2">{{ __('reports.label_required_ppe') }}</p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($ppe as $p)
                                        <span class="inline-block bg-gray-100 text-gray-700 rounded px-2 py-0.5 text-[10px] font-semibold">{{ $p }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Badge de integridad (firma digital SHA-256 / no-repudio) --}}
                @if($sigVerified !== null)
                    <div class="mt-4 break-inside-avoid">
                        @if($sigVerified === true)
                            <div class="flex items-start gap-3 border border-green-200 bg-green-50 rounded p-3">
                                <span class="text-green-600 text-lg leading-none">🔒</span>
                                <div>
                                    <p class="text-xs font-bold text-green-800">{{ __('reports.scouting_signed_intact') }}</p>
                                    <p class="text-[10px] text-green-700 mt-0.5">
                                        @if($latestSig)
                                            {{ __('reports.label_signed_by') }} {{ $latestSig->user ? $latestSig->user->name : ($report->make_by ?: '—') }}
                                            @if($latestSig->signed_at) · {{ $latestSig->signed_at->format('d M Y H:i') }} @endif
                                            @if($latestSig->document_hash) · <span class="font-mono">{{ substr($latestSig->document_hash, 0, 12) }}…</span> @endif
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="flex items-start gap-3 border border-red-300 bg-red-50 rounded p-3">
                                <span class="text-red-600 text-lg leading-none">⚠</span>
                                <div>
                                    <p class="text-xs font-bold text-red-800">{{ __('reports.scouting_modified') }}</p>
                                    <p class="text-[10px] text-red-700 mt-0.5">{{ __('reports.scouting_modified_desc') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

    </div>

    {{-- ===== FOOTER ===== --}}
    <div class="bg-gray-50 border-t border-gray-200 p-6">
        <div class="flex justify-between items-end">
            <div>
                <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-1">{{ __('reports.label_prepared_by') }}</p>
                <p class="font-bold text-gray-800 text-sm uppercase">{{ $report->make_by ?: '—' }}</p>
                <p class="text-xs text-gray-500">{{ __('reports.label_risk_assessment') }}{{ $report->make_date ? ' · '.$report->make_date->format('d M Y') : '' }}</p>
            </div>
            <div class="text-right opacity-70">
                <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-1">{{ __('reports.label_powered_by') }}</p>
                <img src="https://eneg.crewcare.mx/img/logo-cc-report.svg" class="h-5 w-auto grayscale opacity-80 ml-auto" alt="CrewCare" onerror="this.style.display='none'">
                <p class="text-[8px] text-gray-400 font-mono mt-1">SCOUT-{{ $report->id }}-{{ \Carbon\Carbon::parse($report->created_at)->format('dmY') }}</p>
            </div>
        </div>
    </div>

</div>
@endsection
