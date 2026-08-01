@extends('layouts.app')
@section('content')
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,300;0,400;0,700;1,900&family=Courier+Prime:wght@700&display=swap" rel="stylesheet">

<style>
    :root { --brand-primary: #ff9900; }
    .font-poster { font-family: 'Roboto Condensed', sans-serif; font-weight: 900; font-style: italic; text-transform: uppercase; }
    .font-slug { font-family: 'Courier Prime', monospace; }
    .badge-cc { display: inline-flex; align-items: center; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800; color: white; letter-spacing: 0.05em; height: 18px;}
    .badge-STPS { background-color: #15803d; }
    .badge-OSHA { background-color: #1d4ed8; }
    .badge-CSATF { background-color: #b91c1c; }
    .badge-AMAZON { background-color: #ff9900; color: black; }
    .badge-NA { background-color: #6b7280; }

    @media print {
        @page { size: letter portrait; margin: 0; }
        body { margin: 0; padding-bottom: 120px; background-color: white !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .no-print, .navbar, .sidebar, #sidebar { display: none !important; }
        .max-w-4xl { max-width: 100% !important; width: 100% !important; }
        .break-inside-avoid, .grid > div, .mb-8, p, h1, h2, h3, h4 { page-break-inside: avoid !important; break-inside: avoid !important; }
    }
</style>

<div class="max-w-4xl mx-auto mb-4 flex justify-between items-center no-print mt-4">
    <a href="{{ route('call_sheets.index') }}" class="text-blue-600 font-bold">&larr; Volver</a>
    <div class="flex gap-2 items-center">
        @if($callSheet->isSent())
            <span class="bg-green-100 text-green-800 px-3 py-2 rounded font-bold text-sm border border-green-300">
                ✅ ENVIADO {{ $callSheet->sent_at ? \Carbon\Carbon::parse($callSheet->sent_at)->format('d/m/Y H:i') : '' }}
            </span>
        @endif

        <a href="{{ route('call_sheets.pdf', $callSheet->id) }}" class="bg-red-600 text-white px-4 py-2 rounded font-bold shadow hover:bg-red-700">
            <i class="fas fa-file-pdf"></i> PDF
        </a>

        @if(!$callSheet->isSent())
        @can('call_sheets.create')
        <form action="{{ route('call_sheets.send', $callSheet->id) }}" method="POST">
            @csrf
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded font-bold shadow hover:bg-green-700">
                <i class="fas fa-paper-plane"></i> Enviar
            </button>
        </form>
        @endcan
        @endif
    </div>
</div>

<div class="max-w-4xl mx-auto bg-white shadow-2xl min-h-screen overflow-hidden mb-10">

    {{-- ===== HEADER BAND ===== --}}
    <div class="relative w-full bg-gray-900 overflow-hidden">
        <div class="relative z-10 p-8 flex justify-between items-start">
            <div class="flex flex-col items-start">
                <span class="font-poster text-[var(--brand-primary)] text-xs">
                    <img src="https://eneg.crewcare.mx/img/cc_pimienta.svg" width="240" alt="logo">
                </span>
                @if($callSheet->production)
                    <p class="text-white/80 font-poster not-italic tracking-widest text-sm mt-3">{{ $callSheet->production->name }}</p>
                @endif
            </div>

            <div class="text-right text-white">
                <h1 class="font-poster text-5xl text-[var(--brand-primary)] transform -skew-x-6 drop-shadow-2xl leading-none">
                    EL LLAMADO
                </h1>
                <p class="text-lg font-bold tracking-[0.3em] mt-1 text-white/90 font-poster not-italic">CALL SHEET</p>

                @if($callSheet->title)
                <div class="mt-4 inline-block bg-black/70 border border-gray-600 px-4 py-2 transform skew-x-[-6deg]">
                    <div class="transform skew-x-[6deg] font-slug text-base font-bold tracking-tight text-white uppercase">
                        {{ $callSheet->title }}
                    </div>
                </div>
                @endif

                <div class="mt-3 text-xs font-mono opacity-80 uppercase tracking-widest">
                    {{ \Carbon\Carbon::parse($callSheet->sheet_date)->format('d M Y') }}
                    @if($callSheet->general_call) | CALL: {{ \Carbon\Carbon::parse($callSheet->general_call)->format('H:i') }} HRS @endif
                </div>
            </div>
        </div>

        {{-- Strip inferior: shoot day / set / clima --}}
        <div class="w-full bg-black/90 border-t-4 border-[var(--brand-primary)] flex h-16">
            <div class="flex-grow flex items-center justify-around px-4 border-r border-gray-700">
                <div class="text-center"><span class="block text-[9px] text-gray-400 uppercase tracking-widest">Shoot Day</span><span class="text-xl font-bold text-white">{{ $callSheet->shoot_day ?: '—' }}</span></div>
                <div class="text-center"><span class="block text-[9px] text-gray-400 uppercase tracking-widest">Set</span><span class="text-lg font-bold text-white">{{ $callSheet->set_setting ?: '—' }}</span></div>
                <div class="text-center"><span class="block text-[9px] text-gray-400 uppercase tracking-widest">Momento</span><span class="text-lg font-bold text-white">{{ $callSheet->day_part ?: '—' }}</span></div>
            </div>
            <div class="w-1/3 min-w-[180px] flex items-center justify-center bg-gray-800 px-4 gap-3 text-white">
                <span class="text-2xl">🌤️</span>
                <div class="leading-none">
                    <div class="text-[9px] opacity-60 uppercase tracking-wide mb-1">Clima</div>
                    <div class="text-xs font-bold">{{ $callSheet->weather_note ?: 'Sin dato' }}</div>
                    @if($callSheet->sunrise || $callSheet->sunset)
                    <div class="text-[9px] opacity-70 mt-1 font-mono">
                        @if($callSheet->sunrise) ☀ {{ \Carbon\Carbon::parse($callSheet->sunrise)->format('H:i') }} @endif
                        @if($callSheet->sunset) 🌙 {{ \Carbon\Carbon::parse($callSheet->sunset)->format('H:i') }} @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===== TIRA DE EMERGENCIA / LOCACIÓN ===== --}}
    <div class="bg-white border-b border-gray-200 relative z-20 shadow-sm">
        <div class="flex divide-x divide-gray-100 flex-wrap">
            <div class="w-full md:w-1/3 p-3 flex items-start gap-3">
                <div class="bg-yellow-50 p-2 rounded text-yellow-600 shrink-0">📍</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">Locación</h4>
                    <p class="text-xs font-bold text-gray-800">{{ $callSheet->location_name ?: '—' }}</p>
                    <p class="text-[10px] text-gray-500 leading-tight">{{ $callSheet->location_address }}</p>
                </div>
            </div>
            <div class="w-full md:w-1/3 p-3 flex items-start gap-3 bg-red-50/30">
                <div class="bg-red-50 p-2 rounded text-red-600 shrink-0">🏥</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-red-400 tracking-wider">Hospital / Médico</h4>
                    <p class="text-xs font-bold text-gray-800">{{ $callSheet->nearest_hospital ?: '—' }}</p>
                    <p class="text-[10px] text-gray-500 leading-tight">{{ $callSheet->hospital_address }}</p>
                    <p class="text-[10px] text-gray-500">{{ $callSheet->ambulance_company }} @if($callSheet->emergency_phone) | ☎ {{ $callSheet->emergency_phone }} @endif</p>
                </div>
            </div>
            <div class="w-full md:w-1/3 p-3 flex items-start gap-3">
                <div class="bg-blue-50 p-2 rounded text-blue-600 shrink-0">🧯</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">Punto de Reunión</h4>
                    <p class="text-xs font-bold text-gray-800">{{ $callSheet->assembly_point ?: '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="p-8">

        {{-- ===== BOLETINES DE SEGURIDAD DEL DÍA (AUTO-ATTACH) ===== --}}
        <div class="mb-4 flex justify-between items-end border-b border-gray-200 pb-2">
            <h3 class="font-poster text-xl text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> BOLETINES DE SEGURIDAD DEL DÍA
            </h3>
        </div>

        @php $risks = is_array($callSheet->identified_risks) ? $callSheet->identified_risks : []; @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            @forelse($risks as $risk)
            <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid">
                <div class="p-3">
                    <div class="flex justify-between items-start mb-1">
                        <h4 class="font-bold text-gray-800 text-sm w-3/4 leading-tight">{{ $risk['category'] ?? 'Riesgo' }}</h4>
                        <div class="text-right">
                            <span class="badge-cc badge-{{ $risk['badge'] ?? 'NA' }}">{{ $risk['badge'] ?? 'NA' }}</span>
                            <div class="text-[9px] text-gray-400 font-mono mt-0.5">{{ $risk['code'] ?? '' }}</div>
                        </div>
                    </div>
                    @if(!empty($risk['url']))
                        <a href="{{ $risk['url'] }}" target="_blank" rel="noopener" class="inline-block text-[11px] font-bold text-red-700 hover:underline mt-1">📄 Ver boletín</a>
                    @else
                        <span class="text-[10px] text-gray-400 italic">Boletín sin URL registrada.</span>
                    @endif
                </div>
            </div>
            @empty
                <p class="text-gray-500 text-sm md:col-span-2 italic">No se identificaron riesgos / boletines para este llamado.</p>
            @endforelse
        </div>

        {{-- ===== NOTAS DE SEGURIDAD ===== --}}
        @if($callSheet->safety_notes)
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> NOTAS DE SEGURIDAD
            </h3>
            <div class="text-sm text-gray-600 leading-relaxed text-justify border-l-4 border-gray-100 pl-4">
                <p>{{ $callSheet->safety_notes }}</p>
            </div>
        </div>
        @endif

    </div>

    {{-- ===== FOOTER ===== --}}
    <div class="mt-8 bg-gray-50 border-t border-gray-200 p-6">
        <div class="flex justify-between items-end">
            <div>
                <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-2">Llamado elaborado por</p>
                <p class="font-bold text-gray-800 text-sm uppercase">{{ $callSheet->created_by ?: '—' }}</p>
                <p class="text-xs text-gray-500">Producción / Seguridad</p>
            </div>
            <div class="text-right opacity-70">
                <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-1">POWERED BY</p>
                <img src="https://eneg.crewcare.mx/img/logo-cc-report.svg" class="h-5 w-auto grayscale opacity-80 inline-block" alt="CrewCare Logo">
                <p class="text-[8px] text-gray-400 font-mono mt-1">
                    CALLSHEET-{{ $callSheet->id }}-{{ \Carbon\Carbon::parse($callSheet->sheet_date)->format('dmY') }} | VER 1.0
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
