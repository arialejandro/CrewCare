@extends('layouts.app')
@section('content')
{{-- (2026-07-13) MÓDULO 14 — i18n del documento vía ?lang=en|es SIN tocar rutas/middleware.
     __() lee el locale al renderizar, así que sólo las etiquetas estáticas ya migradas a
     __('reports.*') cambian de idioma; los datos y la UI operativa (no-print) siguen igual. --}}
@php if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); } @endphp
{{-- HOMOLOGADO (2026-06-28) al estándar visual del Daily Report (dailyreports/show.blade.php).
     Mismas convenciones: Tailwind CDN, fuentes poster/slug, badges normativos idénticos y
     reglas @media print. NO se inventan campos: sólo se usan las columnas de hazardnotifications. --}}
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
            padding-bottom: 150px; /* Espacio para que el contenido no choque con el pie fijo */
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

{{-- Barra de herramientas (no se imprime) --}}
{{-- flex-wrap (2026-07, fix responsive): barra de acciones (no-print, no afecta el PDF)
     desbordaba en ~375px con select + 2 botones; ahora envuelven. --}}
<div class="max-w-4xl mx-auto mb-4 flex flex-wrap gap-2 justify-between items-center no-print mt-4">
    <a href="{{ route('hazard_notifications.index') }}" class="inline-flex items-center gap-1 bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded font-semibold shadow-sm hover:bg-gray-100 no-underline">&larr; Volver</a>
    <div class="flex flex-wrap gap-2 items-center">
        {{-- Cierre del ciclo: actualizar estado de la acción correctiva (sólo managers) --}}
        @can('hazards.manage')
        <form action="{{ route('hazard_notifications.status', $hazardNotification->id) }}" method="POST" class="flex items-center gap-1">
            @csrf
            <select name="action_status" class="border rounded text-sm px-2 py-2">
                @foreach(['Abierto','En proceso','Cerrado'] as $opt)
                    <option value="{{ $opt }}" {{ ($hazardNotification->action_status ?: 'Abierto') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
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

{{-- Feedback de validación / flash (visible en pantalla; fuera del PDF).
     CLAVE: el formulario "Actualizar estado" (POST hazard_notifications.status) puede lanzar
     un error de validación (bloqueo PDCA: no se puede Cerrar con acciones abiertas) y ese error
     DEBE verse. El parcial muestra session('success'), session('error') y $errors. --}}
<div class="max-w-4xl mx-auto no-print">
    @include('componentes._form-feedback')
</div>

<div class="max-w-4xl mx-auto bg-white shadow-2xl min-h-screen overflow-hidden mb-10">

    {{-- HERO homologado (patrón DSR): logo · proyecto + locación/fecha/hora · pie "CrewCare Unsafe Act Report".
         Se eliminó el título/subtítulo del reporte; el módulo del pie ya lo nombra. --}}
    @include('componentes._doc-hero', [
        'heroImage'    => $hazardNotification->main_image_path,
        'heroLocation' => $hazardNotification->name_loc ?: ($hazardNotification->location_hazard_unsafe_act ?: ''),
        'heroDate'     => $hazardNotification->date_observed ? \Carbon\Carbon::parse($hazardNotification->date_observed)->format('d M Y') : null,
        'heroTime'     => $hazardNotification->time_observed ?: null,
        'heroModule'   => __('reports.hazard_module'),
    ])

    {{-- Cintillo "acto inseguro de un vistazo" (JERARQUÍA): LEAD = el acto/evento (catálogo) con la
         locación como subrótulo · STATS = riesgo · estatus de la acción. Reutiliza _doc-hero-band. --}}
    @php
        $hzEvent      = $hazardNotification->hazardEvent;
        $hzEventName  = $hzEvent ? $hzEvent->name_localized : null;
        $hzLeadValue  = $hzEventName ?: ($hazardNotification->name_loc ?: '—');
        // Subrótulo = CONTEXTO del evento (clasificador corto: "Set de filmación", "Construcción"…),
        // NO la locación (ya vive en el hero → así no se repite).
        $hzLeadSub    = $hzEvent ? $hzEvent->context_label : null;
        $hzRisk       = $hazardNotification->risk_level;
        $hzRiskTone   = in_array($hzRisk, ['Extremo', 'Alto'], true) ? 'warn' : '';
        $hzStatus     = $hazardNotification->action_status ?: 'Abierto';
        $hzStatusTone = $hzStatus === 'Cerrado' ? 'ok' : ($hzStatus === 'Abierto' ? 'warn' : '');
    @endphp
    @include('componentes._doc-hero-band', [
        'lead' => [
            'icon'  => '⚠️',
            'label' => __('reports.band_lead_hazard'),
            'value' => $hzLeadValue,
            'sub'   => $hzLeadSub,
        ],
        'stats' => [
            ['label' => __('reports.label_risk'),         'value' => $hzRisk ?: '—', 'tone' => $hzRiskTone],
            ['label' => __('reports.injury_band_status'), 'value' => $hzStatus, 'tone' => $hzStatusTone],
        ],
    ])

    {{-- META STRIP (adaptado del DSR: Fecha/Hora · Ubicación · Badge normativo) --}}
    <div class="bg-white border-b border-gray-200 relative z-20 shadow-sm">
        <div class="flex divide-x divide-gray-100">
            <div class="w-1/3 p-3 flex items-start gap-3">
                <div class="bg-blue-50 p-2 rounded text-blue-600 shrink-0">🕒</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_observed') }}</h4>
                    <p class="text-xs font-bold text-gray-800">{{ $hazardNotification->date_observed ? \Carbon\Carbon::parse($hazardNotification->date_observed)->format('d-m-Y') : 'N/D' }}</p>
                    <p class="text-[10px] text-gray-500 leading-tight">{{ $hazardNotification->time_observed ?: __('reports.label_time_not_recorded') }}</p>
                </div>
            </div>

            <div class="w-1/3 p-3 flex items-start gap-3">
                <div class="bg-orange-50 p-2 rounded text-orange-600 shrink-0">📍</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_act_location') }}</h4>
                    <p class="text-[11px] text-gray-700 leading-tight">{{ $hazardNotification->location_hazard_unsafe_act ?: __('reports.empty_not_specified') }}</p>
                    {{-- (2026-07-07) GPS: dirección detectada + link al mapa (solo si hay coordenadas) --}}
                    @if($hazardNotification->latitude && $hazardNotification->longitude)
                        @if($hazardNotification->gps_address)
                            <p class="text-[10px] text-gray-500 leading-tight mt-0.5">{{ $hazardNotification->gps_address }}</p>
                        @endif
                        <a href="https://www.google.com/maps?q={{ $hazardNotification->latitude }},{{ $hazardNotification->longitude }}" target="_blank" rel="noopener" class="no-print inline-block text-[9px] font-bold text-blue-700 hover:underline mt-0.5">📍 {{ __('reports.label_view_map') }}</a>
                    @endif
                </div>
            </div>

            {{-- Celda de badge normativo: sólo si la fila trae un código de norma --}}
            @if($hazardNotification->regulation_code)
            <div class="w-1/3 p-3 flex items-start gap-3 bg-red-50/30">
                <div class="bg-red-50 p-2 rounded text-red-600 shrink-0">⚖️</div>
                <div>
                    <h4 class="text-[9px] uppercase font-bold text-red-400 tracking-wider">{{ __('reports.label_applicable_regulation') }}</h4>
                    <div class="flex items-center gap-1 mt-0.5">
                        <span class="badge badge-{{ $hazardNotification->regulation_badge }}">{{ $hazardNotification->regulation_badge }}</span>
                        <span class="text-[10px] text-gray-600 font-mono">{{ $hazardNotification->regulation_code }}</span>
                    </div>
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
    @if(\Illuminate\Support\Facades\Schema::hasColumn('hazardnotifications', 'manual_location_justification') && $hazardNotification->manual_location_justification)
    <div class="bg-amber-50 border-b border-amber-100 px-8 py-3 flex items-start gap-3 break-inside-avoid">
        <div class="bg-amber-100 p-2 rounded text-amber-700 shrink-0">🧭</div>
        <div>
            <h4 class="text-[9px] uppercase font-bold text-amber-500 tracking-wider">{{ __('reports.hazard_manual_location_ref') }}</h4>
            <p class="text-xs text-gray-700 leading-snug">{{ $hazardNotification->manual_location_justification }}</p>
        </div>
    </div>
    @endif

    {{-- CONTENIDO --}}
    <div class="p-8">

        {{-- Descripción del acto inseguro --}}
        <div class="mb-8 break-inside-avoid">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.hazard_section_description') }}
            </h3>
            <div class="text-sm text-gray-600 leading-relaxed text-justify border-l-4 border-gray-100 pl-4">
                <p>{{ $hazardNotification->description_hazard_unsafe_act ?: __('reports.empty_description') }}</p>
            </div>
        </div>

        {{-- Acción tomada --}}
        <div class="mb-8 break-inside-avoid">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-green-500 block transform -skew-x-12"></span> {{ __('reports.hazard_section_action_taken') }}
            </h3>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r">
                <p class="text-sm text-green-800">{{ $hazardNotification->action_taken ?: __('reports.hazard_empty_action_taken') }}</p>
            </div>
        </div>

        {{-- Sugerencias de acción correctiva --}}
        <div class="mb-8 break-inside-avoid">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-blue-500 block transform -skew-x-12"></span> {{ __('reports.hazard_section_suggestions') }}
            </h3>
            <div class="text-sm text-gray-600 leading-relaxed text-justify border-l-4 border-blue-100 pl-4">
                <p>{{ $hazardNotification->suggestions_corrective_action ?: __('reports.hazard_empty_suggestions') }}</p>
            </div>
        </div>

        {{-- Acciones correctivas (PDCA): ciclo de cierre estructurado con responsable y fecha límite.
             DEFENSIVO (regla de guarda): sólo si existe la tabla action_items y hay ítems, así nunca
             truena si el owner aún no aplica el SQL. --}}
        @if(\Illuminate\Support\Facades\Schema::hasTable('action_items') && $hazardNotification->actionItems->count())
        <div class="mb-8 break-inside-avoid">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-purple-500 block transform -skew-x-12"></span> {{ __('reports.section_corrective_actions') }}
            </h3>
            <div class="space-y-3">
                @foreach($hazardNotification->actionItems as $ai)
                    @php
                        $aiStatusMap = [
                            'open'        => ['label' => __('reports.status_open'),        'chip' => 'bg-amber-100 text-amber-800'],
                            'in_progress' => ['label' => __('reports.status_in_progress'), 'chip' => 'bg-blue-100 text-blue-800'],
                            'closed'      => ['label' => __('reports.status_closed'),      'chip' => 'bg-green-100 text-green-800'],
                        ];
                        $aiMeta = isset($aiStatusMap[$ai->status]) ? $aiStatusMap[$ai->status] : ['label' => $ai->status, 'chip' => 'bg-gray-100 text-gray-600'];
                        $aiOverdue = method_exists($ai, 'isOverdue') && $ai->isOverdue();
                    @endphp
                    <div class="border rounded-r border-l-4 p-3 {{ $aiOverdue ? 'border-red-300 border-l-red-500 bg-red-50/60' : 'border-gray-200 border-l-purple-400 bg-gray-50' }}">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm text-gray-700 leading-snug">{{ $ai->description }}</p>
                            <span class="shrink-0 inline-block text-[10px] font-bold px-2 py-0.5 rounded-full {{ $aiMeta['chip'] }}">{{ $aiMeta['label'] }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-gray-500">
                            <span>
                                <span class="uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_deadline') }}:</span>
                                @if($ai->due_date)
                                    <span class="{{ $aiOverdue ? 'text-red-700 font-bold' : 'text-gray-600' }}">{{ $ai->due_date->format('d/m/Y H:i') }}</span>
                                    @if($aiOverdue)<span class="text-red-700 font-bold uppercase">· {{ __('reports.label_overdue') }}</span>@endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </span>
                            <span>
                                <span class="uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_responsible') }}:</span>
                                <span class="text-gray-600">{{ $ai->owner ? $ai->owner->name : '—' }}</span>
                            </span>
                            @if($ai->source)
                            <span>
                                <span class="uppercase font-bold text-gray-400 tracking-wider">{{ __('reports.label_source') }}:</span>
                                <span class="text-gray-600">{{ $ai->source === 'auto' ? __('reports.label_auto') : __('reports.label_manual') }}</span>
                            </span>
                            @endif
                        </div>
                        {{-- (Ola A) Magic Link WhatsApp: enviar/recibir la foto de mitigación de la acción.
                             Gateado con @feature('magic_links') dentro del parcial; no-print (operativo). --}}
                        <div class="no-print">
                            @include('componentes._wa-mitigation-link', ['item' => $ai])
                        </div>
                        @can('hazards.manage')
                            <div class="no-print mt-2 pt-2 border-t border-gray-100">
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
                            </div>
                        @endcan
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Normas aplicables: catálogo normativo enlazado (relación standards / tabla standardables).
             DEFENSIVO (regla de guarda): sólo si existe la tabla standardables y hay normas. --}}
        @if(\Illuminate\Support\Facades\Schema::hasTable('standardables') && $hazardNotification->standards->count())
        <div class="mb-8 break-inside-avoid">
            <h3 class="font-poster text-xl mb-2 text-gray-800 flex items-center gap-2">
                <span class="w-2 h-6 bg-red-500 block transform -skew-x-12"></span> {{ __('reports.section_applicable_standards') }}
            </h3>
            <div class="flex flex-wrap gap-2">
                @foreach($hazardNotification->standards as $std)
                    <div class="inline-flex items-center gap-2 border border-gray-200 bg-gray-50 rounded px-2 py-1">
                        <span class="badge badge-{{ $std->regulation_badge }}">{{ $std->regulation_badge }}</span>
                        <span class="text-[11px] text-gray-700">{{ $std->category_name_localized }}</span>
                        <span class="text-[10px] text-gray-500 font-mono">{{ $std->regulation_code }}</span>
                        @if($std->reference_url)
                            <a href="{{ $std->reference_url }}" target="_blank" rel="noopener" class="no-print text-[9px] font-bold text-red-700 hover:underline">📄 {{ __('reports.label_view') }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Evidencia gráfica: foto principal (hero) + galería de adicionales (array cast) --}}
        @if($hazardNotification->main_image_path || (is_array($hazardNotification->additional_images_paths) && count($hazardNotification->additional_images_paths)))
        <div class="mb-4 break-inside-avoid">
            <div class="mb-4 flex justify-between items-end border-b border-gray-200 pb-2">
                <h3 class="font-poster text-xl text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-6 bg-[var(--brand-primary)] block transform -skew-x-12"></span> {{ __('reports.hazard_section_graphic_evidence') }}
                </h3>
            </div>

            @if($hazardNotification->main_image_path)
            <div class="relative w-full mb-6 overflow-hidden rounded shadow-lg bg-gray-100">
                {{-- Homologado: altura acotada + object-cover para que una foto vertical no deforme el documento. --}}
                <img src="{{ $hazardNotification->main_image_path }}" class="w-full max-h-[28rem] object-cover" alt="Evidencia principal">
                <div class="brand-corner"></div>
            </div>
            @endif

            @if(is_array($hazardNotification->additional_images_paths) && count($hazardNotification->additional_images_paths))
            <div class="grid grid-cols-3 gap-4">
                @foreach($hazardNotification->additional_images_paths as $imagePath)
                <div class="relative overflow-hidden rounded shadow border border-gray-200 bg-gray-100">
                    <img src="{{ $imagePath }}" class="w-full h-40 object-cover" alt="Evidencia adicional">
                    <div class="brand-corner"></div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

    </div>
</div>

{{-- PIE FIJO PARA IMPRESIÓN (bloque firma, igual que el DSR)
========================================== --}}
<div class="mt-8 bg-gray-50 border-t border-gray-200 p-6 print-footer">
    <div class="flex justify-between items-end">

        <div>
            <p class="text-[9px] text-gray-400 uppercase tracking-widest mb-4">{{ __('reports.label_prepared_by') }}</p>
            <div class="flex items-center gap-3">
                <div>
                    <p class="font-bold text-gray-800 text-sm uppercase">{{ $hazardNotification->make_by ?: 'CrewCare' }}</p>
                    <p class="text-xs text-gray-500">{{ __('reports.label_risk_assessment') }}</p>
                    <p class="text-[10px] text-gray-400 font-mono mt-1">
                        {{ $hazardNotification->make_date ? \Carbon\Carbon::parse($hazardNotification->make_date)->format('d M Y') : 'Fecha no registrada' }}
                    </p>
                </div>
            </div>

            {{-- (2026-07-12) Módulo 6 — Sello de integridad (no-repudio). verifyLatestSignature()
                 compara el hash SHA-256 vigente contra el de la última firma. DEFENSIVO: guard de
                 tabla; si aún no hay firma (null) no se muestra el sello. --}}
            @php
                $sigValid = \Illuminate\Support\Facades\Schema::hasTable('digital_signatures')
                    ? $hazardNotification->verifyLatestSignature()
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
            <p class="text-[8px] text-gray-400 font-mono mt-1">
                UUID: HAZ-{{ 16210 + $hazardNotification->id }}-{{ \Carbon\Carbon::parse($hazardNotification->created_at)->format('dmY') }} | VER 2.1
            </p>
        </div>

    </div>
</div>

@endsection
