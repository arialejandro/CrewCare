@extends('layouts.app')
@section('content')
{{-- (2026-07-13) MÓDULO 14 — i18n del documento vía ?lang=en|es SIN tocar rutas/middleware.
     __() lee el locale al renderizar, así que sólo las etiquetas estáticas ya migradas a
     __('reports.*') cambian de idioma; los datos y la UI operativa (no-print) siguen igual. --}}
@php if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); } @endphp
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { corePlugins: { preflight: false } };</script>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,300;0,400;0,700;1,900&family=Courier+Prime:wght@700&display=swap" rel="stylesheet">

<style>
    :root { --brand-primary: {{ $branding['primary_color'] ?? '#ff9900' }}; --brand-secondary: {{ $branding['secondary_color'] ?? '#1f2937' }}; --brand-accent: {{ $branding['accent_color'] ?? '#0ea5e9' }}; }
    .font-poster { font-family: 'Roboto Condensed', sans-serif; font-weight: 900; font-style: italic; text-transform: uppercase; }
    .font-slug { font-family: 'Courier Prime', monospace; }
    .badge { display: inline-flex; align-items: center; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800; color: white; letter-spacing: 0.05em; height: 18px;}
    .badge-STPS { background-color: #15803d; }
    .badge-OSHA { background-color: #1d4ed8; }
    .badge-CSATF { background-color: #b91c1c; }
    .badge-AMAZON { background-color: #ff9900; color: black; }
    .brand-corner { position: absolute; bottom: 0; right: 0; width: 0; height: 0; border-style: solid; border-width: 0 0 30px 30px; border-color: transparent transparent var(--brand-primary) transparent; }

   /* === MAGIA PARA EL PDF NATIVO === */
    @media print {
        @page {
            size: letter portrait; /* Tamaño Carta (Letter) */
            margin: 0;
        }

        body {
            margin: 0;
            /* Padding inferior para que el contenido nunca choque con el pie de página */
            padding-bottom: 150px;
            background-color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .no-print, .navbar, .sidebar, #sidebar { display: none !important; }

        .max-w-4xl { max-width: 100% !important; width: 100% !important; }

        /* === ANTI-SUPERPOSICIÓN (EVITAR TEXTOS Y TARJETAS ENCIMADAS) === */
        .break-inside-avoid, .grid > div, .mb-8, p, h1, h2, h3, h4, textarea {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        /* MAGIA DEL PIE DE PÁGINA REPETITIVO */
        .print-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            margin: 0 auto;
            max-width: 56rem;
            z-index: 50;
            background-color: #f9fafb !important;
            border-top: 1px solid #e5e7eb !important;
        }
    }
</style>
{{-- Hero homologado (mismo patrón que el DSR): estilos + auto-ajuste compartidos. --}}
@include('componentes._doc-hero-styles')

{{-- (2026-07-13) COHERENCIA: chip de riesgo rápido-lectura homologado a la matriz 5×5.
     Sustituye al chip legacy de seriousness. risk_level lo calcula el servidor; guard
     defensivo de columna (PROD aún sin el delta estructural → cae a null y no se muestra). --}}
@php
    $riskLevel = \Illuminate\Support\Facades\Schema::hasColumn('injury_reports', 'risk_level')
        ? $injuryReport->risk_level
        : null;
    if ($riskLevel === 'Extremo')   { $riskClass = 'bg-red-100 text-red-700 border border-red-300'; }
    elseif ($riskLevel === 'Alto')  { $riskClass = 'bg-orange-100 text-orange-700 border border-orange-300'; }
    elseif ($riskLevel === 'Medio') { $riskClass = 'bg-amber-100 text-amber-700 border border-amber-300'; }
    else                            { $riskClass = 'bg-green-100 text-green-700 border border-green-300'; }
@endphp

{{-- flex-wrap (2026-07, fix responsive): barra de acciones (no-print) desbordaba en ~375px. --}}
<div class="max-w-4xl mx-auto mb-4 flex flex-wrap gap-2 justify-between items-center no-print mt-4">
    <a href="{{ route('injury_reports.index') }}" class="inline-flex items-center gap-1 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded font-semibold shadow-sm hover:bg-gray-100 no-underline">&larr; Volver</a>
    <div class="flex gap-2">
        <button onclick="window.print();" class="inline-flex items-center gap-1 bg-red-600 text-white px-4 py-2 rounded font-bold shadow hover:bg-red-700">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
    </div>
</div>

{{-- (2026-07-09) Feedback de acciones (flash + errores de validación). no-print: NO sale en el PDF.
     Sin esto, un error de validación (p.ej. el bloqueo PDCA al cerrar/finalizar) sería invisible. --}}
<div class="max-w-4xl mx-auto mb-4 no-print">
    @include('componentes._form-feedback')
</div>

{{-- (2026-07-12) MÓDULO 6: Badge de integridad (firma digital SHA-256). Compara el
     hash firmado con el estado actual del documento. Verde = intacto, rojo = alterado
     tras la firma. Si no hay firma/tabla, no se muestra nada. --}}
@php
    $sig = \Illuminate\Support\Facades\Schema::hasTable('digital_signatures')
        ? $injuryReport->verifyLatestSignature()
        : null;
@endphp
@if($sig === true)
<div class="max-w-4xl mx-auto mb-4">
    <div class="inline-flex items-center gap-2 bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded text-sm font-semibold">
        <i class="fas fa-shield-alt"></i> {{ __('reports.injury_signed_verified') }}
    </div>
</div>
@elseif($sig === false)
<div class="max-w-4xl mx-auto mb-4">
    <div class="inline-flex items-center gap-2 bg-red-50 border border-red-300 text-red-800 px-4 py-2 rounded text-sm font-semibold">
        <i class="fas fa-exclamation-triangle"></i> {{ __('reports.injury_altered') }}
    </div>
</div>
@endif

<div class="max-w-4xl mx-auto bg-white shadow-2xl min-h-screen overflow-hidden mb-10">

    {{-- HERO homologado (patrón DSR): logo · proyecto + locación/fecha/hora · pie "CrewCare Injury Report".
         Se eliminó el título/subtítulo del reporte; el módulo del pie ya lo nombra. --}}
    @include('componentes._doc-hero', [
        'heroImage'    => $injuryReport->main_image_path,
        'heroLocation' => $injuryReport->location ?: ($injuryReport->incident_location ?: ''),
        'heroDate'     => $injuryReport->incident_date ? \Carbon\Carbon::parse($injuryReport->incident_date)->format('d M Y') : null,
        'heroTime'     => $injuryReport->time ? \Carbon\Carbon::parse($injuryReport->time)->format('H:i') : null,
        'heroModule'   => __('reports.injury_module'),
    ])

    {{-- Cintillo "accidente de un vistazo" (JERARQUÍA):
           · LEAD  = la persona lesionada (sujeto del reporte): nombre + apellido, con departamento
                     (y puesto, si hay) como subrótulo. Aparece SÓLO aquí (ya no se repite abajo).
           · STATS = parte del cuerpo · riesgo · estado (registrable). Riesgo/Estado con color. --}}
    @php
        $bandRiskTone  = in_array($riskLevel, ['Extremo', 'Alto'], true) ? 'warn' : '';
        $injRecordable = \Illuminate\Support\Facades\Schema::hasColumn('injury_reports', 'is_recordable')
            ? $injuryReport->is_recordable : null;
        $bandStatus     = $injRecordable === null ? null : ($injRecordable ? __('reports.injury_recordable') : __('reports.injury_not_recordable'));
        $bandStatusTone = $injRecordable === null ? '' : ($injRecordable ? 'warn' : 'ok');
        // Subrótulo del lesionado: "Departamento · Puesto" (lo que exista).
        $injDept = trim((string) ($injuryReport->department ?? ''));
        $injPos  = trim((string) ($injuryReport->position ?? ''));
        $injSub  = $injPos !== '' ? trim($injDept . ($injDept !== '' ? ' · ' : '') . $injPos) : $injDept;
    @endphp
    @include('componentes._doc-hero-band', [
        'lead' => [
            'icon'  => '🧑‍🔧',
            'label' => __('reports.label_injured_person'),
            'value' => $injuryReport->name ?: '—',
            'sub'   => $injSub !== '' ? $injSub : null,
        ],
        'stats' => [
            ['label' => __('reports.label_body_part'),    'value' => $injuryReport->body_part ?: null],
            ['label' => __('reports.label_risk'),         'value' => $riskLevel ?: '—', 'tone' => $bandRiskTone],
            ['label' => __('reports.injury_band_status'), 'value' => $bandStatus ?: '—', 'tone' => $bandStatusTone],
        ],
    ])

    {{-- META STRIP (detalles): Hospital / GPS / Norma. El lesionado y la parte del cuerpo viven ahora
         en el cintillo "de un vistazo" (jerarquía), así que ya NO se repiten aquí. --}}
    <div class="bg-white border-b border-gray-200 relative z-20 shadow-sm">
        <div class="flex divide-x divide-gray-100 flex-wrap">
            <div class="w-1/4 p-3 flex items-start gap-3 bg-red-50/30">
                <div class="bg-red-50 p-2 rounded text-red-600 shrink-0">🏥</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-red-400 tracking-wider">{{ __('reports.label_hospital') }}</h4>
                    {{-- (2026-07-12) MÓDULO 13: dato médico → gated por policy viewMedical. --}}
                    @can('viewMedical', $injuryReport)
                        <p class="text-xs font-bold text-gray-800">{{ $injuryReport->hospital ?: '—' }}</p>
                        <p class="text-[10px] text-gray-500">{{ $injuryReport->treatment_by }}</p>
                    @else
                        <p class="text-[10px] text-gray-400 italic">{{ __('reports.label_restricted_medical') }}</p>
                    @endcan
                </div>
            </div>

            {{-- (2026-07-07) GPS: dirección detectada + link al mapa (solo si hay coordenadas) --}}
            @if($injuryReport->latitude && $injuryReport->longitude)
            <div class="w-1/4 p-3 flex items-start gap-3">
                <div class="bg-orange-50 p-2 rounded text-orange-600 shrink-0">📍</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_gps_location') }}</h4>
                    @if($injuryReport->gps_address)
                        <p class="text-[10px] text-gray-500 leading-tight">{{ $injuryReport->gps_address }}</p>
                    @endif
                    <a href="https://www.google.com/maps?q={{ $injuryReport->latitude }},{{ $injuryReport->longitude }}" target="_blank" rel="noopener" class="no-print inline-block text-[9px] font-bold text-blue-700 hover:underline mt-0.5">📍 {{ __('reports.label_view_map') }}</a>
                </div>
            </div>
            @endif

            @if($injuryReport->regulation_code)
            <div class="w-1/4 p-3 flex items-start gap-3">
                <div class="bg-gray-100 p-2 rounded text-gray-700 shrink-0">⚖️</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_regulation') }}</h4>
                    <span class="badge badge-{{ $injuryReport->regulation_badge }}">{{ $injuryReport->regulation_badge }}</span>
                    <div class="text-[8px] text-gray-400 font-mono mt-0.5">{{ $injuryReport->regulation_code }}</div>
                    @if($standardUrl)
                        <a href="{{ $standardUrl }}" target="_blank" rel="noopener" class="no-print inline-block text-[8px] font-bold text-red-700 hover:underline mt-0.5">📄 {{ __('reports.label_view_bulletin') }}</a>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="p-8">

        {{-- (2026-07-09) REGISTRABILIDAD OSHA 300/301 & FATIGA.
             Se muestra SOLO si el owner aplicó el delta estructural (columna is_recordable).
             Las demás columnas (treatment_level, días, hours_worked_prior, employer_name,
             risk_level) llegan en el MISMO delta → si is_recordable existe, todas existen. --}}
        @if(\Illuminate\Support\Facades\Schema::hasColumn('injury_reports', 'is_recordable'))
        @php
            $treatmentLabels = [
                'first_aid'         => __('reports.treatment_first_aid'),
                'medical_treatment' => __('reports.treatment_medical'),
                'hospitalization'   => __('reports.treatment_hospitalization'),
                'fatality'          => __('reports.treatment_fatality'),
            ];
            $tl      = $injuryReport->treatment_level;
            $tlLabel = ($tl && isset($treatmentLabels[$tl])) ? $treatmentLabels[$tl] : $tl;
            $rl      = $injuryReport->risk_level;
            if ($rl === 'Extremo')   { $rlClass = 'bg-red-100 text-red-700 border border-red-300'; }
            elseif ($rl === 'Alto')  { $rlClass = 'bg-orange-100 text-orange-700 border border-orange-300'; }
            elseif ($rl === 'Medio') { $rlClass = 'bg-amber-100 text-amber-700 border border-amber-300'; }
            else                     { $rlClass = 'bg-green-100 text-green-700 border border-green-300'; }
        @endphp
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_osha') }}
            </h3>
            <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid p-4">
                <div class="flex flex-wrap items-center gap-3 mb-3">
                    @if($injuryReport->is_recordable)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-600 text-white uppercase tracking-wide">{{ __('reports.injury_recordable') }}</span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gray-200 text-gray-600 uppercase tracking-wide">{{ __('reports.injury_not_recordable') }}</span>
                    @endif
                    @if($rl)
                        <span class="inline-block px-2 py-1 rounded text-[11px] font-bold {{ $rlClass }}">{{ __('reports.label_risk') }}: {{ $rl }}</span>
                    @endif
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @if($tlLabel)
                    <div>
                        <span class="block text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_treatment_level') }}</span>
                        {{-- (2026-07-12) MÓDULO 13: dato médico → gated por policy viewMedical. --}}
                        @can('viewMedical', $injuryReport)
                            <span class="text-[11px] font-bold text-gray-800">{{ $tlLabel }}</span>
                        @else
                            <span class="text-[11px] font-bold text-gray-400 italic">{{ __('reports.label_restricted_medical') }}</span>
                        @endcan
                    </div>
                    @endif
                    @if($injuryReport->days_away_from_work > 0)
                    <div>
                        <span class="block text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_days_away') }}</span>
                        <span class="text-[11px] font-bold text-gray-800">{{ $injuryReport->days_away_from_work }}</span>
                    </div>
                    @endif
                    @if($injuryReport->days_restricted_work > 0)
                    <div>
                        <span class="block text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_days_restricted') }}</span>
                        <span class="text-[11px] font-bold text-gray-800">{{ $injuryReport->days_restricted_work }}</span>
                    </div>
                    @endif
                    @if(!is_null($injuryReport->hours_worked_prior))
                    <div>
                        <span class="block text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_hours_worked_prior') }}</span>
                        @if($injuryReport->hours_worked_prior > 12)
                            <span class="text-[11px] font-bold text-red-600">{{ $injuryReport->hours_worked_prior }} h · {{ __('reports.label_possible_fatigue') }}</span>
                        @else
                            <span class="text-[11px] font-bold text-gray-800">{{ $injuryReport->hours_worked_prior }} h</span>
                        @endif
                    </div>
                    @endif
                    @if(!empty($injuryReport->employer_name))
                    <div>
                        <span class="block text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_employer') }}</span>
                        <span class="text-[11px] font-bold text-gray-800">{{ $injuryReport->employer_name }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- TIPO DE LESIÓN (array cast → chips) --}}
        @if(is_array($injuryReport->injury_type) && count($injuryReport->injury_type))
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_type') }}
            </h3>
            <div class="flex flex-wrap gap-2">
                @foreach($injuryReport->injury_type as $type)
                    <span class="inline-block bg-gray-100 border border-gray-200 text-gray-700 text-xs font-bold px-3 py-1 rounded-full">{{ $type }}</span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- QUÉ PASÓ --}}
        <div class="mb-8 flex gap-6">
            <div class="w-full">
                <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_what_happened') }}
                </h3>
                <div class="text-sm text-gray-600 leading-relaxed text-justify border-l-4 border-gray-100 pl-4">
                    <p>{{ $injuryReport->what_happened ?: __('reports.injury_empty_what_happened') }}</p>
                </div>
            </div>
        </div>

        {{-- QUÉ LO CAUSÓ --}}
        <div class="mb-8 flex gap-6">
            <div class="w-full">
                <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_what_caused') }}
                </h3>
                <div class="text-sm text-gray-600 leading-relaxed text-justify border-l-4 border-gray-100 pl-4">
                    <p>{{ $injuryReport->what_caused ?: __('reports.injury_empty_what_caused') }}</p>
                </div>
            </div>
        </div>

        {{-- (2026-07-09) ANÁLISIS DE CAUSA RAÍZ (RCA estructurado, JSON). Se muestra solo si
             hay datos; si la columna aún no existe, el cast devuelve null → is_array() falla. --}}
        @if(is_array($injuryReport->root_cause_analysis) && count(array_filter($injuryReport->root_cause_analysis)))
        {{-- (2026-07-12) MÓDULO 13: causa raíz = dato médico/sensible → gated por policy. --}}
        @can('viewMedical', $injuryReport)
        @php
            $rca = $injuryReport->root_cause_analysis;
            $rcaCategories = (isset($rca['categories']) && is_array($rca['categories'])) ? array_filter($rca['categories']) : [];
        @endphp
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_root_cause') }}
            </h3>
            {{-- (2026-07-13) COHERENCIA: categorías de causa raíz (checkboxes) → chips. --}}
            @if(!empty($rcaCategories))
            <div class="flex flex-wrap gap-2 mb-3">
                @foreach($rcaCategories as $rcaCat)
                    <span class="inline-block bg-gray-100 border border-gray-200 text-gray-700 text-xs font-bold px-3 py-1 rounded-full">{{ $rcaCat }}</span>
                @endforeach
            </div>
            @endif
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @if(!empty($rca['immediate']))
                <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid p-3">
                    <h4 class="font-bold text-gray-800 text-xs mb-1 uppercase tracking-wide">{{ __('reports.label_immediate_cause') }}</h4>
                    <p class="text-[11px] text-gray-600">{{ is_array($rca['immediate']) ? implode(', ', $rca['immediate']) : $rca['immediate'] }}</p>
                </div>
                @endif
                @if(!empty($rca['contributing']))
                <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid p-3">
                    <h4 class="font-bold text-gray-800 text-xs mb-1 uppercase tracking-wide">{{ __('reports.label_contributing_factors') }}</h4>
                    <p class="text-[11px] text-gray-600">{{ is_array($rca['contributing']) ? implode(', ', $rca['contributing']) : $rca['contributing'] }}</p>
                </div>
                @endif
                @if(!empty($rca['root']))
                <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid p-3 border-l-4 border-l-[var(--brand-primary)]">
                    <h4 class="font-bold text-gray-800 text-xs mb-1 uppercase tracking-wide">{{ __('reports.label_root_cause') }}</h4>
                    <p class="text-[11px] text-gray-600">{{ is_array($rca['root']) ? implode(', ', $rca['root']) : $rca['root'] }}</p>
                </div>
                @endif
            </div>
        </div>
        @else
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_root_cause') }}
            </h3>
            <p class="text-[11px] text-gray-400 italic">{{ __('reports.label_restricted_medical') }}</p>
        </div>
        @endcan
        @endif

        {{-- (2026-07-09) EPP (equipo de protección personal) estructurado, JSON.
             (2026-07-12) MÓDULO 13: dato médico/sensible → gated por policy viewMedical. --}}
        @if(is_array($injuryReport->ppe_details) && count(array_filter($injuryReport->ppe_details)))
        @can('viewMedical', $injuryReport)
        @php
            $ppe        = $injuryReport->ppe_details;
            $wornLabels = ['si' => __('reports.label_yes'), 'no' => __('reports.label_no'), 'na' => __('reports.label_na')];
            $wornRaw    = isset($ppe['worn']) ? $ppe['worn'] : null;
            $wornLabel  = ($wornRaw !== null && isset($wornLabels[$wornRaw])) ? $wornLabels[$wornRaw] : $wornRaw;
            $ppeTypes   = (isset($ppe['types']) && is_array($ppe['types'])) ? implode(', ', $ppe['types']) : (isset($ppe['types']) ? $ppe['types'] : '');
        @endphp
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_ppe') }}
            </h3>
            <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid p-3 text-[11px] text-gray-600 flex flex-wrap gap-x-6 gap-y-1">
                @if($wornLabel !== null && $wornLabel !== '')
                    <p><strong>{{ __('reports.label_ppe_worn') }}:</strong> {{ $wornLabel }}</p>
                @endif
                @if($ppeTypes !== '')
                    <p><strong>{{ __('reports.label_ppe_types') }}:</strong> {{ $ppeTypes }}</p>
                @endif
                @if(!empty($ppe['condition']))
                    <p><strong>{{ __('reports.label_condition') }}:</strong> {{ $ppe['condition'] }}</p>
                @endif
            </div>
        </div>
        @else
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_ppe') }}
            </h3>
            <p class="text-[11px] text-gray-400 italic">{{ __('reports.label_restricted_medical') }}</p>
        </div>
        @endcan
        @endif

        {{-- TRATAMIENTO Y CUMPLIMIENTO (tarjetas) --}}
        <div class="mb-4 flex justify-between items-end border-b border-gray-200 pb-2">
            <h3 class="font-poster text-xl text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_treatment_compliance') }}
            </h3>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-8">
            {{-- Tratamiento — (2026-07-12) MÓDULO 13: silo médico → gated por policy. --}}
            <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid">
                <div class="p-3">
                    <h4 class="font-bold text-gray-800 text-xs mb-2 uppercase tracking-wide">🩺 {{ __('reports.label_treatment') }}</h4>
                    @can('viewMedical', $injuryReport)
                        <p class="text-[11px] text-gray-600"><strong>{{ __('reports.label_type') }}:</strong> {{ $injuryReport->treatment_type ?: '—' }}</p>
                        <p class="text-[11px] text-gray-600"><strong>{{ __('reports.label_first_responder') }}:</strong> {{ $injuryReport->treatment_by ?: '—' }}</p>
                        <p class="text-[11px] text-gray-600"><strong>{{ __('reports.label_doctor_hospital') }}:</strong> {{ $injuryReport->hospital ?: '—' }}</p>
                        @if($injuryReport->treatment_comments)
                        <div class="bg-gray-50 border-l-2 border-gray-400 p-1.5 rounded-r mt-2">
                            <p class="text-[10px] text-gray-700">{{ $injuryReport->treatment_comments }}</p>
                        </div>
                        @endif
                    @else
                        <p class="text-[11px] text-gray-400 italic">{{ __('reports.label_restricted_medical') }}</p>
                    @endcan
                </div>
            </div>

            {{-- Prevención --}}
            <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid">
                <div class="p-3">
                    <h4 class="font-bold text-gray-800 text-xs mb-2 uppercase tracking-wide">🛡️ {{ __('reports.label_prevention') }}</h4>
                    @if($injuryReport->preventions)
                    <div class="bg-green-50 border-l-2 border-green-500 p-1.5 rounded-r">
                        <p class="text-[10px] text-green-800">{{ $injuryReport->preventions }}</p>
                    </div>
                    @else
                        <p class="text-[11px] text-gray-500 italic">{{ __('reports.injury_empty_preventions') }}</p>
                    @endif
                </div>
            </div>

            {{-- Notificación a la autoridad (BLOQUE LEGACY).
                 (2026-07-13) COHERENCIA: sólo se muestra si NO hay datos del módulo 10
                 (authority_notifications), para evitar duplicidad. En registros nuevos
                 la captura vive únicamente en la sección "Notificación a Autoridades". --}}
            @unless(\Illuminate\Support\Facades\Schema::hasColumn('injury_reports', 'authority_notifications') && is_array($injuryReport->authority_notifications) && count($injuryReport->authority_notifications))
            <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid">
                <div class="p-3">
                    <h4 class="font-bold text-gray-800 text-xs mb-2 uppercase tracking-wide">📢 {{ __('reports.label_authority_notification') }}</h4>
                    <p class="text-[11px] text-gray-600">
                        <strong>{{ __('reports.label_notified_question') }}:</strong>
                        @if($injuryReport->notified_to_worksafe)
                            <span class="text-green-700 font-bold">{{ __('reports.label_yes') }}</span>
                        @else
                            <span class="text-gray-500 font-bold">{{ __('reports.label_no') }}</span>
                        @endif
                    </p>
                    @if($injuryReport->date_notified)
                        <p class="text-[11px] text-gray-600"><strong>{{ __('reports.label_date') }}:</strong> {{ \Carbon\Carbon::parse($injuryReport->date_notified)->format('d M Y') }}</p>
                    @endif
                    @if($injuryReport->notified_by)
                        <p class="text-[11px] text-gray-600"><strong>{{ __('reports.label_by') }}:</strong> {{ $injuryReport->notified_by }}</p>
                    @endif
                    @if($injuryReport->notified_comment)
                    <div class="bg-gray-50 border-l-2 border-gray-400 p-1.5 rounded-r mt-2">
                        <p class="text-[10px] text-gray-700">{{ $injuryReport->notified_comment }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endunless

            {{-- Comentarios adicionales --}}
            @if($injuryReport->further_comments)
            <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid">
                <div class="p-3">
                    <h4 class="font-bold text-gray-800 text-xs mb-2 uppercase tracking-wide">💬 {{ __('reports.label_comments') }}</h4>
                    <p class="text-[11px] text-gray-600">{{ $injuryReport->further_comments }}</p>
                </div>
            </div>
            @endif
        </div>

        {{-- (2026-07-09) ACCIONES CORRECTIVAS (PDCA). Guarda de tabla: si el owner aún no aplicó
             el delta (action_items), no truena. Injury no tiene formulario de actualizar estado,
             pero las acciones auto-generadas desde 'preventions' se listan igual. --}}
        @if(\Illuminate\Support\Facades\Schema::hasTable('action_items') && $injuryReport->actionItems->count())
        @php
            $statusLabels  = ['open' => __('reports.status_open'), 'in_progress' => __('reports.status_in_progress'), 'closed' => __('reports.status_closed')];
            $statusClasses = [
                'open'        => 'bg-amber-100 text-amber-700 border border-amber-300',
                'in_progress' => 'bg-blue-100 text-blue-700 border border-blue-300',
                'closed'      => 'bg-green-100 text-green-700 border border-green-300',
            ];
        @endphp
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.section_corrective_actions') }}
            </h3>
            <div class="space-y-2">
                @foreach($injuryReport->actionItems as $item)
                @php
                    $st      = $item->status;
                    $stLabel = isset($statusLabels[$st]) ? $statusLabels[$st] : $st;
                    $stClass = isset($statusClasses[$st]) ? $statusClasses[$st] : 'bg-gray-100 text-gray-600 border border-gray-300';
                    $overdue = $item->isOverdue();
                @endphp
                <div class="bg-white border {{ $overdue ? 'border-red-300 bg-red-50/40' : 'border-gray-200' }} shadow-lg rounded overflow-hidden break-inside-avoid p-3">
                    <div class="flex justify-between items-start gap-3">
                        <p class="text-[11px] text-gray-700 leading-snug w-3/4">{{ $item->description }}</p>
                        <span class="inline-block px-2 py-0.5 rounded text-[9px] font-bold shrink-0 {{ $stClass }}">{{ $stLabel }}</span>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-[10px] text-gray-500">
                        <span><strong>{{ __('reports.label_responsible') }}:</strong> {{ $item->owner ? $item->owner->name : '—' }}</span>
                        <span class="{{ $overdue ? 'text-red-600 font-bold' : '' }}">
                            <strong>{{ __('reports.label_due') }}:</strong>
                            {{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('d M Y H:i') : '—' }}
                            @if($overdue) · {{ __('reports.label_overdue_caps') }} @endif
                        </span>
                        <span><strong>{{ __('reports.label_source') }}:</strong> {{ $item->source === 'auto' ? __('reports.label_auto') : __('reports.label_manual') }}</span>
                    </div>
                    {{-- (Ola A) Magic Link WhatsApp: enviar/recibir la foto de mitigación de la acción.
                         Gateado con @feature('magic_links') dentro del parcial; no-print (operativo). --}}
                    <div class="no-print">
                        @include('componentes._wa-mitigation-link', ['item' => $item])
                    </div>
                    @can('hazards.manage')
                        <div class="no-print mt-2 pt-2 border-t border-gray-100">
                            @if(in_array($item->status, ['open', 'in_progress'], true))
                                <form action="{{ route('action_items.close', $item->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold px-2.5 py-1 rounded bg-green-600 text-white hover:bg-green-700">{{ __('reports.label_mark_closed') }}</button>
                                </form>
                            @else
                                <form action="{{ route('action_items.reopen', $item->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-100">{{ __('reports.label_reopen') }}</button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- (2026-07-09) NORMAS APLICABLES (N:M vía standardables). Guarda de tabla defensiva. --}}
        @if(\Illuminate\Support\Facades\Schema::hasTable('standardables') && $injuryReport->standards->count())
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.section_applicable_standards') }}
            </h3>
            <div class="flex flex-wrap gap-2">
                @foreach($injuryReport->standards as $std)
                <div class="inline-flex items-center gap-2 bg-white border border-gray-200 shadow-sm rounded px-3 py-2 break-inside-avoid">
                    <span class="badge badge-{{ $std->regulation_badge }}">{{ $std->regulation_badge }}</span>
                    <span class="text-[11px] font-bold text-gray-800">{{ $std->category_name_localized }}</span>
                    <span class="text-[9px] text-gray-400 font-mono">{{ $std->regulation_code }}</span>
                    @if($std->reference_url)
                        <a href="{{ $std->reference_url }}" target="_blank" rel="noopener" class="no-print text-[9px] font-bold text-red-700 hover:underline">📄 {{ __('reports.label_view_bulletin') }}</a>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- (2026-07-12) MÓDULO 7: TESTIGOS (1:N). Guard de tabla defensivo. El NOMBRE
             es visible; el TELÉFONO y la DECLARACIÓN son sensibles → gated por policy. --}}
        @if(\Illuminate\Support\Facades\Schema::hasTable('witnesses') && $injuryReport->witnesses->count())
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.section_witnesses') }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($injuryReport->witnesses as $witness)
                <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid p-3">
                    <p class="text-xs font-bold text-gray-800">👤 {{ $witness->name }}</p>
                    @can('viewMedical', $injuryReport)
                        @if($witness->phone)
                            <p class="text-[11px] text-gray-600"><strong>{{ __('reports.label_phone') }}:</strong> {{ $witness->phone }}</p>
                        @endif
                        @if($witness->statement)
                            <p class="text-[11px] text-gray-600 mt-1">{{ $witness->statement }}</p>
                        @endif
                    @else
                        <p class="text-[10px] text-gray-400 italic">{{ __('reports.label_contact_statement_restricted') }}</p>
                    @endcan
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- (2026-07-12) MÓDULO 10: NOTIFICACIÓN A AUTORIDADES (JSON). Registro de
             cumplimiento (no clínico) → visible; guard de columna defensivo para prod. --}}
        @if(\Illuminate\Support\Facades\Schema::hasColumn('injury_reports', 'authority_notifications') && is_array($injuryReport->authority_notifications) && count($injuryReport->authority_notifications))
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.section_authority_notifications') }}
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-[11px] border border-gray-200">
                    <thead class="bg-gray-100 text-gray-500 uppercase text-[9px]">
                        <tr>
                            <th class="p-2 text-left">{{ __('reports.label_authority') }}</th>
                            <th class="p-2 text-left">{{ __('reports.label_date') }}</th>
                            <th class="p-2 text-left">{{ __('reports.label_notified_by') }}</th>
                            <th class="p-2 text-left">{{ __('reports.label_folio') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($injuryReport->authority_notifications as $note)
                        <tr class="border-t border-gray-100">
                            <td class="p-2 font-bold text-gray-800">{{ $note['authority'] ?? '—' }}</td>
                            <td class="p-2 text-gray-600">{{ !empty($note['notified_at']) ? \Carbon\Carbon::parse($note['notified_at'])->format('d M Y H:i') : '—' }}</td>
                            <td class="p-2 text-gray-600">{{ $note['notified_by'] ?? '—' }}</td>
                            <td class="p-2 font-mono text-gray-600">{{ $note['folio_number'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- (2026-07-12) MÓDULO 11: Justificación de ubicación manual (sin GPS).
             Guard de columna defensivo. --}}
        @if(\Illuminate\Support\Facades\Schema::hasColumn('injury_reports', 'manual_location_justification') && !empty($injuryReport->manual_location_justification))
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_manual_location') }}
            </h3>
            <div class="text-sm text-gray-600 leading-relaxed text-justify border-l-4 border-gray-100 pl-4">
                <p>{{ $injuryReport->manual_location_justification }}</p>
            </div>
        </div>
        @endif

        {{-- GALERÍA DE EVIDENCIAS ADICIONALES --}}
        @if(is_array($injuryReport->additional_images_paths) && count($injuryReport->additional_images_paths))
        <div class="mb-4">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.injury_section_photo_evidence') }}
            </h3>
            <div class="grid grid-cols-3 gap-4">
                @foreach($injuryReport->additional_images_paths as $img)
                <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden group relative break-inside-avoid">
                    <div class="relative h-40 overflow-hidden bg-gray-100">
                        <img src="{{ $img }}" class="w-full h-full object-cover">
                        <div class="brand-corner"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

{{-- (Ola A · Pilar 4) Addendums médicos (append-only): registran cambios posteriores de
     diagnóstico/tratamiento SIN alterar el reporte firmado. Operativo (no-print) y gateado
     con @feature('medical_addendum') + guard de tabla dentro del parcial. --}}
<div class="max-w-4xl mx-auto no-print mb-10">
    @include('componentes._addendum-list', ['injuryReport' => $injuryReport])
</div>

{{-- footer fijo para impresión (AUTOFIRMA)
========================================== --}}

<div class="mt-8 bg-gray-50 border-t border-gray-200 p-6 print-footer">
            <div class="flex justify-between items-end">

                <div>
                    <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-4">{{ __('reports.label_prepared_by') }}</p>
                    <div class="flex items-center gap-3">
                        <div>
                            <p class="font-bold text-gray-800 text-sm uppercase">{{ $injuryReport->make_by }}</p>
                            <p class="text-xs text-gray-500">{{ __('reports.label_risk_assessment') }}
                                @if($injuryReport->make_date)
                                    &middot; {{ \Carbon\Carbon::parse($injuryReport->make_date)->format('d M Y') }}
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="text-right opacity-70">
                    <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-1">{{ __('reports.label_powered_by') }}</p>
                    <div class="flex items-center justify-end gap-2 mb-1">
                        <img src="https://eneg.crewcare.mx/img/logo-cc-report.svg" class="h-5 w-auto grayscale opacity-80" alt="CrewCare Logo">
                    </div>
                    <p class="text-[8px] text-gray-400 font-mono mt-1">
                        UUID: {{ $branding['brand_name'] ?? 'CrewCare' }}-INJ-{{ 16210 + $injuryReport->id }}-{{ \Carbon\Carbon::parse($injuryReport->created_at)->format('dmY') }} | VER 2.1
                    </p>
                </div>

            </div>
        </div>
@endsection
