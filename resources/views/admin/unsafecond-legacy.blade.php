@extends('layouts.app')
@section('content')
{{-- (2026-07-13) MÓDULO 14 — i18n del documento vía ?lang=en|es SIN tocar rutas/middleware.
     __() lee el locale al renderizar, así que sólo las etiquetas estáticas migradas a
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

   /* === MAGIA PARA EL PDF NATIVO (mismas reglas que el Daily Report) === */
    @media print {
        @page {
            size: letter portrait; /* Tamaño Carta (Letter) */
            margin: 0;
        }

        body {
            margin: 0;
            /* Padding inferior para que el contenido nunca choque con el pie de página fijo */
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

{{-- Barra de herramientas (no se imprime): Volver + PDF --}}
{{-- flex-wrap (2026-07, fix responsive): barra de acciones (no-print, no afecta el PDF)
     desbordaba en ~375px con select + 2 botones; ahora envuelven. --}}
<div class="max-w-4xl mx-auto mb-4 flex flex-wrap gap-2 justify-between items-center no-print mt-4">
    <a href="{{ route('unsafenotifications.index') }}" class="inline-flex items-center gap-1 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded font-semibold shadow-sm hover:bg-gray-100 no-underline">&larr; Volver</a>
    <div class="flex flex-wrap gap-2 items-center">
        {{-- (2026-06-28) Cierre del ciclo: actualizar el estado de la acción correctiva. Solo managers. --}}
        @can('hazards.manage')
        <form action="{{ route('unsafenotifications.status', $unsafenotification->id) }}" method="POST" class="flex items-center gap-1">
            @csrf
            <select name="action_status" class="border rounded text-sm px-2 py-2">
                @foreach(['Abierto','En proceso','Cerrado'] as $opt)
                    <option value="{{ $opt }}" {{ ($unsafenotification->action_status ?: 'Abierto') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
            <button class="bg-gray-800 text-white px-3 py-2 rounded text-sm font-bold">Actualizar estado</button>
        </form>
        @endcan
        <button onclick="window.print();" class="inline-flex items-center gap-1 bg-red-600 text-white px-4 py-2 rounded font-bold shadow hover:bg-red-700">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
    </div>
</div>

{{-- (2026-07-09) Feedback de validación/flash (no-print). CLAVE: el form "Actualizar estado"
     (POST unsafenotifications.status) puede lanzar el error de bloqueo PDCA y sin esto no se vería. --}}
<div class="max-w-4xl mx-auto no-print">
    @include('componentes._form-feedback')
</div>

<div class="max-w-4xl mx-auto bg-white shadow-2xl min-h-screen overflow-hidden mb-10">

    {{-- HERO homologado (patrón DSR): logo · proyecto + locación/fecha/hora · pie "CrewCare Unsafe Condition Report".
         Se eliminó el título/subtítulo del reporte; el módulo del pie ya lo nombra. --}}
    @include('componentes._doc-hero', [
        'heroImage'    => $unsafenotification->main_image_path,
        'heroLocation' => $unsafenotification->name_loc ?: ($unsafenotification->location_unsafe_cond ?: ''),
        'heroDate'     => $unsafenotification->date_observed ? \Carbon\Carbon::parse($unsafenotification->date_observed)->format('d M Y') : null,
        'heroTime'     => $unsafenotification->time_observed ?: null,
        'heroModule'   => __('reports.unsafe_module'),
    ])

    {{-- Cintillo "condición insegura de un vistazo" (JERARQUÍA): LEAD = la condición/evento (catálogo)
         con la locación como subrótulo · STATS = riesgo · estatus de la acción. Reutiliza _doc-hero-band. --}}
    @php
        $ucEvent      = $unsafenotification->hazardEvent;
        $ucEventName  = $ucEvent ? $ucEvent->name_localized : null;
        $ucLeadValue  = $ucEventName ?: ($unsafenotification->name_loc ?: '—');
        // Subrótulo = CONTEXTO del evento (clasificador corto), NO la locación (ya vive en el hero → no repetir).
        $ucLeadSub    = $ucEvent ? $ucEvent->context_label : null;
        $ucRisk       = $unsafenotification->risk_level;
        $ucRiskTone   = in_array($ucRisk, ['Extremo', 'Alto'], true) ? 'warn' : '';
        $ucStatus     = $unsafenotification->action_status ?: 'Abierto';
        $ucStatusTone = $ucStatus === 'Cerrado' ? 'ok' : ($ucStatus === 'Abierto' ? 'warn' : '');
    @endphp
    @include('componentes._doc-hero-band', [
        'lead' => [
            'icon'  => '⚠️',
            'label' => __('reports.band_lead_unsafe'),
            'value' => $ucLeadValue,
            'sub'   => $ucLeadSub,
        ],
        'stats' => [
            ['label' => __('reports.label_risk'),         'value' => $ucRisk ?: '—', 'tone' => $ucRiskTone],
            ['label' => __('reports.injury_band_status'), 'value' => $ucStatus, 'tone' => $ucStatusTone],
        ],
    ])

    {{-- META STRIP: Observación / Ubicación / Notificación / Badge normativo --}}
    <div class="bg-white border-b border-gray-200 relative z-20 shadow-sm">
        <div class="flex flex-wrap divide-x divide-gray-100">
            <div class="w-1/2 md:w-1/4 p-3 flex items-start gap-3">
                <div class="bg-blue-50 p-2 rounded text-blue-600 shrink-0">🕒</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.unsafe_label_observed') }}</h4>
                    <p class="text-xs font-bold text-gray-800">{{ $unsafenotification->date_observed ? \Carbon\Carbon::parse($unsafenotification->date_observed)->format('d/m/Y') : 'N/A' }}</p>
                    <p class="text-[10px] text-gray-500 leading-tight">{{ $unsafenotification->time_observed ?? 'N/A' }} hrs</p>
                </div>
            </div>

            <div class="w-1/2 md:w-1/4 p-3 flex items-start gap-3">
                <div class="bg-gray-100 p-2 rounded text-gray-600 shrink-0">📍</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.unsafe_label_condition_location') }}</h4>
                    <p class="text-[11px] text-gray-700 leading-tight">{{ $unsafenotification->location_unsafe_cond ?: __('reports.empty_not_specified') }}</p>
                    {{-- (2026-07-07) GPS: dirección detectada + link al mapa (solo si hay coordenadas) --}}
                    @if($unsafenotification->latitude && $unsafenotification->longitude)
                        @if($unsafenotification->gps_address)
                            <p class="text-[10px] text-gray-500 leading-tight mt-0.5">{{ $unsafenotification->gps_address }}</p>
                        @endif
                        <a href="https://www.google.com/maps?q={{ $unsafenotification->latitude }},{{ $unsafenotification->longitude }}" target="_blank" rel="noopener" class="no-print inline-block text-[9px] font-bold text-blue-700 hover:underline mt-0.5">📍 {{ __('reports.label_view_map') }}</a>
                    @endif
                </div>
            </div>

            <div class="w-1/2 md:w-1/4 p-3 flex items-start gap-3">
                <div class="bg-red-50 p-2 rounded text-red-600 shrink-0">📢</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.unsafe_label_notification') }}</h4>
                    <p class="text-xs font-bold text-gray-800">{{ ($unsafenotification->unsafe_act_notify ?? null) == '1' ? __('reports.label_yes') : __('reports.label_no') }}</p>
                    <p class="text-[10px] text-gray-500 leading-tight">{{ $unsafenotification->date_notify_unsafe_act ? \Carbon\Carbon::parse($unsafenotification->date_notify_unsafe_act)->format('d/m/Y') : 'S/F' }}</p>
                </div>
            </div>

            {{-- Catálogo normativo: badge + código + enlace al boletín (solo si se etiquetó una norma) --}}
            @if($unsafenotification->regulation_code)
            <div class="w-1/2 md:w-1/4 p-3 flex items-start gap-3">
                <div class="bg-yellow-50 p-2 rounded text-yellow-600 shrink-0">⚖️</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_applicable_regulation') }}</h4>
                    <div class="mt-0.5">
                        <span class="badge badge-{{ $unsafenotification->regulation_badge }}">{{ $unsafenotification->regulation_badge }}</span>
                    </div>
                    <p class="text-[10px] text-gray-500 font-mono mt-0.5">{{ $unsafenotification->regulation_code }}</p>
                    @if($standardUrl)
                        <a href="{{ $standardUrl }}" target="_blank" rel="noopener" class="no-print inline-block text-[9px] font-bold text-red-700 hover:underline mt-0.5">📄 {{ __('reports.label_view_bulletin') }}</a>
                    @endif
                </div>
            </div>
            @endif

            {{-- (2026-07-15) La celda Riesgo/Estatus se movió al cintillo "de un vistazo" bajo el hero
                 (jerarquía), así que ya NO se repite aquí. --}}
        </div>
    </div>

    {{-- (2026-07-12) Módulo 11 — Referencia de ubicación (captura manual): se muestra
         junto al bloque de ubicación/GPS sólo cuando el reporte no tuvo coordenadas y el
         usuario describió el lugar. DEFENSIVO: guard de columna para PROD sin el ALTER. --}}
    @if(\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'manual_location_justification') && $unsafenotification->manual_location_justification)
    <div class="bg-amber-50 border-b border-amber-100 px-8 py-3 flex items-start gap-3 break-inside-avoid">
        <div class="bg-amber-100 p-2 rounded text-amber-700 shrink-0">🧭</div>
        <div>
            <h4 class="text-[9px] uppercase font-bold text-amber-500 tracking-wider">{{ __('reports.hazard_manual_location_ref') }}</h4>
            <p class="text-xs text-gray-700 leading-snug">{{ $unsafenotification->manual_location_justification }}</p>
        </div>
    </div>
    @endif

    {{-- CUERPO: tarjetas de contenido --}}
    <div class="p-8">
        <div class="mb-8">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.unsafe_section_description') }}
            </h3>
            <div class="text-sm text-gray-600 leading-relaxed text-justify border-l-4 border-gray-100 pl-4">
                <p>{{ $unsafenotification->description_unsafe_cond ?: __('reports.empty_description') }}</p>
            </div>
        </div>

        {{-- (2026-07-15) Homologación de cabecera: la persona / departamento involucrado se movió del
             encabezado (donde sólo deben ir locación · fecha · hora) al desarrollo del reporte.
             DEFENSIVO: guard de columna para PROD sin el ALTER. Sólo si trae valor. --}}
        @if(\Illuminate\Support\Facades\Schema::hasColumn('unsafeconds', 'involved_department') && $unsafenotification->involved_department)
        <div class="mb-8 break-inside-avoid">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-indigo-500 block transform -skew-x-12"></span> {{ __('reports.label_department') }}
            </h3>
            <div class="text-sm text-gray-700 leading-relaxed border-l-4 border-gray-100 pl-4">
                <p class="font-bold">{{ $unsafenotification->involved_department }}</p>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid">
                <div class="p-4">
                    <h4 class="font-poster text-sm text-gray-800 flex items-center gap-2 mb-2">
                        <span class="w-2 h-5 bg-green-500 block transform -skew-x-12"></span> {{ __('reports.hazard_section_action_taken') }}
                    </h4>
                    <p class="text-xs text-gray-600 leading-relaxed">{{ $unsafenotification->action_taken ?: __('reports.hazard_empty_action_taken') }}</p>
                </div>
            </div>

            <div class="bg-white border border-gray-200 shadow-lg rounded overflow-hidden break-inside-avoid">
                <div class="p-4">
                    <h4 class="font-poster text-sm text-gray-800 flex items-center gap-2 mb-2">
                        <span class="w-2 h-5 bg-blue-500 block transform -skew-x-12"></span> {{ __('reports.unsafe_section_corrective_action') }}
                    </h4>
                    <p class="text-xs text-gray-600 leading-relaxed">{{ $unsafenotification->corrective_action ?: __('reports.unsafe_empty_corrective_action') }}</p>
                </div>
            </div>
        </div>

        {{-- (2026-07-09) Acciones correctivas (PDCA): plan de acción estructurado y contable.
             DEFENSIVO: guarda de tabla para no truncar si el owner aún no aplicó el SQL. --}}
        @if(\Illuminate\Support\Facades\Schema::hasTable('action_items') && $unsafenotification->actionItems->count())
        <div class="mb-8">
            <div class="mb-4 flex justify-between items-end border-b border-gray-200 pb-2">
                <h3 class="font-poster text-xl text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.section_corrective_actions') }}
                </h3>
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ $unsafenotification->actionItems->count() }} {{ __('reports.label_actions_suffix') }}</span>
            </div>
            <div class="space-y-3">
                @foreach($unsafenotification->actionItems as $item)
                    @php
                        $aiMap   = ['open'=>'bg-amber-100 text-amber-800','in_progress'=>'bg-blue-100 text-blue-800','closed'=>'bg-green-100 text-green-800'];
                        $aiLabel = ['open'=>__('reports.status_open'),'in_progress'=>__('reports.status_in_progress'),'closed'=>__('reports.status_closed')];
                        $overdue = method_exists($item, 'isOverdue') && $item->isOverdue();
                    @endphp
                    <div class="bg-white border border-gray-200 shadow rounded overflow-hidden break-inside-avoid {{ $overdue ? 'border-l-4 border-l-red-500' : '' }}">
                        <div class="p-4">
                            <div class="flex justify-between items-start gap-3 mb-2">
                                <p class="text-sm text-gray-700 leading-relaxed flex-1">{{ $item->description }}</p>
                                <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0 {{ $aiMap[$item->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $aiLabel[$item->status] ?? $item->status }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[10px] text-gray-500">
                                <span class="flex items-center gap-1">
                                    <span class="uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_responsible') }}:</span>
                                    {{ $item->owner ? $item->owner->name : '—' }}
                                </span>
                                <span class="flex items-center gap-1 {{ $overdue ? 'text-red-600 font-bold' : '' }}">
                                    <span class="uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_due') }}:</span>
                                    {{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') : '—' }}
                                    @if($overdue)<span class="text-red-600">⚠ {{ __('reports.label_overdue') }}</span>@endif
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_source') }}:</span>
                                    {{ $item->source === 'auto' ? __('reports.label_auto') : __('reports.label_manual') }}
                                </span>
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
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- (2026-07-09) Normas aplicables (N:M vía standardables): todas las normas vinculadas.
             DEFENSIVO: guarda de tabla para no truncar si el owner aún no aplicó el SQL. --}}
        @if(\Illuminate\Support\Facades\Schema::hasTable('standardables') && $unsafenotification->standards->count())
        <div class="mb-8">
            <div class="mb-4 flex justify-between items-end border-b border-gray-200 pb-2">
                <h3 class="font-poster text-xl text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.section_applicable_standards') }}
                </h3>
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ $unsafenotification->standards->count() }} {{ __('reports.label_standards_suffix') }}</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($unsafenotification->standards as $std)
                    <div class="bg-white border border-gray-200 shadow rounded p-3 flex items-start gap-3 break-inside-avoid">
                        <span class="badge badge-{{ $std->regulation_badge }} shrink-0 mt-0.5">{{ $std->regulation_badge }}</span>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-gray-800 leading-tight">{{ $std->category_name_localized }}</p>
                            <p class="text-[10px] text-gray-500 font-mono mt-0.5">{{ $std->regulation_code }}</p>
                            @if($std->reference_url)
                                <a href="{{ $std->reference_url }}" target="_blank" rel="noopener" class="no-print inline-block text-[9px] font-bold text-red-700 hover:underline mt-0.5">📄 {{ __('reports.label_view_bulletin') }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- EVIDENCIA: foto principal (hero) + galería de adicionales (cast array) --}}
        @if($unsafenotification->main_image_path || (is_array($unsafenotification->additional_images_paths) && count($unsafenotification->additional_images_paths)))
        <div class="mb-4 flex justify-between items-end border-b border-gray-200 pb-2">
            <h3 class="font-poster text-xl text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.hazard_section_graphic_evidence') }}
            </h3>
        </div>

        @if($unsafenotification->main_image_path)
            <div class="mb-6 break-inside-avoid">
                <div class="relative overflow-hidden bg-gray-100 rounded shadow-lg">
                    <img src="{{ $unsafenotification->main_image_path }}" class="w-full max-h-[28rem] object-cover" alt="Evidencia principal">
                    <div class="absolute top-2 left-2 bg-black/60 backdrop-blur text-white text-[9px] font-bold px-2 py-0.5 rounded">{{ __('reports.unsafe_label_main_evidence') }}</div>
                    <div class="brand-corner"></div>
                </div>
            </div>
        @endif

        @if(is_array($unsafenotification->additional_images_paths) && count($unsafenotification->additional_images_paths))
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                {{-- additional_images_paths viene casteado a array desde el modelo: se itera directo. --}}
                @foreach($unsafenotification->additional_images_paths as $imagePath)
                    <div class="relative overflow-hidden bg-gray-100 rounded shadow break-inside-avoid">
                        <img src="{{ $imagePath }}" class="w-full h-40 object-cover" alt="Evidencia adicional">
                        <div class="brand-corner"></div>
                    </div>
                @endforeach
            </div>
        @endif
        @endif
    </div>
</div>

{{-- PIE DE PÁGINA FIJO PARA IMPRESIÓN (firma) — mismo patrón que el Daily Report --}}
<div class="mt-8 bg-gray-50 border-t border-gray-200 p-6 print-footer">
    <div class="flex justify-between items-end">

        <div>
            <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-4">{{ __('reports.label_prepared_by') }}</p>
            <div class="flex items-center gap-3">
                <div>
                    <p class="font-bold text-gray-800 text-sm uppercase">{{ $unsafenotification->make_by ?: 'CrewCare' }}</p>
                    <p class="text-xs text-gray-500">{{ __('reports.label_risk_assessment') }}</p>
                    @if($unsafenotification->make_date)
                        <p class="text-[10px] text-gray-400 font-mono mt-0.5">{{ \Carbon\Carbon::parse($unsafenotification->make_date)->format('d M Y') }}</p>
                    @endif
                </div>
            </div>

            {{-- (2026-07-12) Módulo 6 — Sello de integridad (no-repudio). verifyLatestSignature()
                 compara el hash SHA-256 vigente contra el de la última firma. DEFENSIVO: guard de
                 tabla; si aún no hay firma (null) no se muestra el sello. --}}
            @php
                $sigValid = \Illuminate\Support\Facades\Schema::hasTable('digital_signatures')
                    ? $unsafenotification->verifyLatestSignature()
                    : null;
            @endphp
            @if($sigValid !== null)
                @if($sigValid)
                    <div class="inline-flex items-center gap-1.5 mt-3 bg-green-50 border border-green-200 text-green-800 text-[10px] font-bold px-2.5 py-1 rounded">
                        🔒 <span>{{ __('reports.hazard_signed_intact') }}</span>
                    </div>
                @else
                    <div class="inline-flex items-center gap-1.5 mt-3 bg-red-50 border border-red-200 text-red-800 text-[10px] font-bold px-2.5 py-1 rounded">
                        ⚠ <span>{{ __('reports.hazard_modified') }}</span>
                    </div>
                @endif
            @endif
        </div>

        <div class="text-right opacity-70">
            <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-1">{{ __('reports.label_powered_by') }}</p>
            <div class="flex items-center justify-end gap-2 mb-1">
                <img src="https://eneg.crewcare.mx/img/logo-cc-report.svg" class="h-5 w-auto grayscale opacity-80" alt="CrewCare Logo">
            </div>
        </div>

    </div>
</div>
@endsection
