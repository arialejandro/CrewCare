@extends('layouts.app')
@section('content')
@php
    $snap = is_array($inspection->checklist_snapshot) ? $inspection->checklist_snapshot : [];
    $crewSnap = is_array($inspection->crew_snapshot) ? $inspection->crew_snapshot : [];

    $verdictMap = [
        'paro'                    => ['class' => 'verdict--paro',   'icon' => 'octagon-alert',  'title' => __('PARO INMEDIATO'),        'sub' => __('La unidad no se usa.')],
        'actividad_no_ejecutable' => ['class' => 'verdict--noexec', 'icon' => 'alert-triangle', 'title' => __('ACTIVIDAD NO EJECUTABLE'),'sub' => __('El recurso no alcanza para la actividad prevista.')],
        'apta'                    => ['class' => 'verdict--apta',   'icon' => 'shield-check',   'title' => __('APTA'),                    'sub' => __('El recurso queda disponible.')],
    ];
    $v = $verdictMap[$inspection->verdict] ?? $verdictMap['apta'];

    $pathLabel = [
        'reemplazo'             => __('Fuera de servicio: la unidad sale y se reemplaza.'),
        'correccion_mismo_dia'  => __('Corrección el mismo día; vuelve si se corrige y se reverifica.'),
    ];
    $triggerLabels = [
        'completa'  => __('Verificación completa'),
        'identidad' => __('Identidad'),
        'persona'   => __('Persona / tripulación'),
        'unidad'    => __('Unidad'),
        'consumo'   => __('Consumo / botiquín'),
        'riesgo'    => __('Riesgo del día'),
    ];
    $riskLabels = [1 => __('Muy bajo'), 2 => __('Bajo'), 3 => __('Medio'), 4 => __('Alto'), 5 => __('Muy alto')];
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 860px;">

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="crew-title mb-0">{{ __('Constancia de verificación de recurso de emergencia en sitio') }}</h1>
                <p class="text-muted mb-0 small">{{ $inspection->folio() }}</p>
            </div>
            <a href="{{ route('ambulance.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Recursos') }}</a>
        </div>

        {{-- Encuadre: es constancia de verificación en sitio, NO inspección sanitaria. --}}
        <p class="text-muted small mb-3">
            {{ __('Registra que el recurso de emergencia se verificó en sitio. No sustituye el dictamen de la autoridad sanitaria.') }}
        </p>

        {{-- Estado RETIRADO: el sello sigue válido; el documento ya no está vigente. --}}
        @if ($inspection->isRetired())
            <div class="alert alert-secondary d-flex align-items-start gap-2">
                @include('componentes._icon', ['name' => 'lock', 'label' => null])
                <div>
                    <strong>{{ __('Acta RETIRADA') }}</strong> — {{ __('el sello sigue siendo válido; solo cambió de estado.') }}
                    @if ($inspection->retired_at)<br><span class="small text-muted">{{ __('Retirada el') }} {{ optional($inspection->retired_at)->format('d/m/Y H:i') }}@if($inspection->retired_reason) · {{ $inspection->retired_reason }}@endif</span>@endif
                    @if ($inspection->supersededBy)<br><span class="small">{{ __('Sustituida por') }} <a href="{{ route('ambulance.acta', $inspection->supersededBy->uuid) }}">{{ $inspection->supersededBy->folio() }}</a></span>@endif
                </div>
            </div>
        @endif

        {{-- Veredicto (derivado del dato) --}}
        <div class="verdict-band {{ $v['class'] }}">
            @include('componentes._icon', ['name' => $v['icon'], 'label' => null])
            <div>
                <h2>{{ $v['title'] }}</h2>
                <p>{{ $v['sub'] }}
                    @if ($inspection->verdict === 'paro' && $inspection->resolution_path)
                        — {{ $pathLabel[$inspection->resolution_path] ?? $inspection->resolution_path }}
                    @endif
                </p>
            </div>
        </div>

        {{-- Desbloqueo del PARO (acto con autor). La página interna ya está gateada por
             permission:ambulance.manage, así que quien la ve puede levantarlo. --}}
        @if ($inspection->isParo())
            <div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
                @if ($inspection->unblocked_at)
                    <div class="d-flex align-items-center gap-2 text-success">
                        @include('componentes._icon', ['name' => 'circle-check', 'label' => null])
                        <span>{{ __('Paro levantado por') }} <strong>{{ $inspection->unblocked_by_name }}</strong>
                            · {{ optional($inspection->unblocked_at)->format('d/m/Y H:i') }}</span>
                    </div>
                @else
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="text-muted small">{{ __('El paro dura minutos: levántalo cuando la vía de salida esté cumplida.') }}</span>
                        <form method="post" action="{{ route('ambulance.unblock', $inspection->uuid) }}"
                              onsubmit="return confirm('{{ __('¿Levantar el paro? Queda registrado con tu nombre y hora, y el acta se re-sella.') }}');">
                            @csrf
                            <button class="btn btn-danger d-inline-flex align-items-center gap-2">
                                @include('componentes._icon', ['name' => 'lock', 'label' => null])
                                {{ __('Levantar el paro') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @endif

        {{-- La obligación: el action item ligado al acta (flujo PDCA existente). --}}
        @if (! empty($actionItem))
            @php $aiClosed = $actionItem->status === \App\Models\ActionItem::STATUS_CLOSED; @endphp
            <div class="card border-0 shadow-sm rounded-3 p-3 mb-3" style="border-left:4px solid {{ $aiClosed ? '#16a34a' : '#b45309' }} !important;">
                <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            @include('componentes._icon', ['name' => $aiClosed ? 'clipboard-check' : 'clipboard-list', 'label' => null])
                            <strong>{{ __('Acción correctiva') }}</strong>
                            <span class="insp-tag">{{ $aiClosed ? __('Cerrada') : __('Abierta') }}</span>
                        </div>
                        <div class="small mt-1">{{ $actionItem->description }}</div>
                        @if ($actionItem->due_date)<div class="small text-muted">{{ __('Compromiso') }}: {{ optional($actionItem->due_date)->format('d/m/Y H:i') }}</div>@endif
                    </div>
                    @if (! $aiClosed)
                        @can('hazards.manage')
                            <form method="post" action="{{ url('/action-items/'.$actionItem->id.'/close') }}"
                                  onsubmit="return confirm('{{ __('¿Cerrar la acción? Si es un PARO, se levanta y el acta se re-sella.') }}');">
                                @csrf
                                <button class="btn btn-sm btn-success d-inline-flex align-items-center gap-1">
                                    @include('componentes._icon', ['name' => 'circle-check', 'label' => null])
                                    {{ __('Cerrar y levantar paro') }}
                                </button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        @endif

        {{-- Datos congelados --}}
        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
            <div class="row g-3 small">
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Tipo') }}</span>
                    <strong>{{ $inspection->type_name }}</strong> <span class="text-muted">({{ $inspection->type_code }})</span></div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Rama / nivel') }}</span>
                    {{ $inspection->rama ?: '—' }}@if($inspection->type_level) · {{ __('Nivel') }} {{ $inspection->type_level }}@endif
                    @if($inspection->capacity_level) · {{ __('Capacidad') }} {{ $inspection->capacity_level }}@endif</div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Proveedor') }}</span>
                    {{ $inspection->provider_name ?: '—' }}</div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Verificación') }}</span>
                    {{ $triggerLabels[$inspection->trigger_scope] ?? ($inspection->trigger_scope ?: '—') }}</div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Placas') }}</span>
                    {{ $inspection->plates ?: '—' }}</div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('N.º económico') }}</span>
                    {{ $inspection->economic_number ?: '—' }}</div>
                @if ($inspection->day_risk_level !== null)
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Riesgo del día') }}</span>
                        {{ $inspection->day_risk_level }} · {{ $riskLabels[$inspection->day_risk_level] ?? '' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('¿Corresponde al riesgo?') }}</span>
                        {{ $inspection->correspondence_ok ? __('Sí') : __('No') }}</div>
                @endif
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Inspector') }}</span>
                    {{ $inspection->inspector_name ?: '—' }}
                    @if ($inspection->inspector_role)<span class="text-muted"> · {{ $inspection->inspector_role }}</span>@endif
                    @if ($inspection->inspector_cedula)<span class="text-muted"> · {{ __('Cédula') }} {{ $inspection->inspector_cedula }}</span>@endif</div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Fecha') }}</span>
                    {{ optional($inspection->created_at)->format('d/m/Y H:i') }}</div>
            </div>

            {{-- Tripulación congelada del día. --}}
            @if (count($crewSnap))
                <hr>
                <span class="text-muted small d-block mb-2">{{ __('Tripulación') }}</span>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($crewSnap as $m)
                        @php $r = $m['role'] ?? ($m['crew_role'] ?? null); $ver = ! empty($m['verified']); $folio = $m['conocer_folio'] ?? null; @endphp
                        <span class="insp-tag {{ $ver ? 'insp-tag--gate' : '' }}">
                            {{ $m['name'] ?? ($m['full_name'] ?? '—') }}@if($r) · {{ $r }}@endif
                            @if($folio) · {{ __('CONOCER') }} {{ $folio }}@endif
                            @if($ver)
                                · @include('componentes._icon', ['name' => 'circle-check', 'label' => null]) {{ __('cotejado') }}
                            @elseif($folio) · {{ __('sin cotejar') }}@endif
                        </span>
                    @endforeach
                </div>
                <div class="form-text mt-1">{{ __('TAMP = Técnico en Atención Médica Prehospitalaria. "Cotejado" = folio CONOCER con foto del certificado y de la persona.') }}</div>
            @endif

            @if ($inspection->observations)
                <hr>
                <span class="text-muted small d-block mb-1">{{ __('Observaciones') }}</span>
                <div style="white-space: pre-line;">{{ $inspection->observations }}</div>
            @endif
        </div>

        {{-- Foto REAL de la unidad (sellada con el acta). --}}
        @if ($inspection->unitPhotoUrl())
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <span class="text-muted small d-block mb-2">{{ __('Foto de la unidad') }}</span>
                <a href="{{ $inspection->unitPhotoUrl() }}" target="_blank" rel="noopener">
                    <img src="{{ $inspection->unitPhotoUrl() }}" alt="{{ $inspection->type_name }}"
                         style="max-width:100%;max-height:360px;border-radius:.5rem;object-fit:contain;">
                </a>
            </div>
        @endif

        {{-- Evidencia fotográfica adicional (sellada): sostiene la decisión de revocar o autorizar. --}}
        @php $evidence = $inspection->evidencePhotoUrls(); @endphp
        @if (count($evidence))
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <span class="text-muted small d-block mb-2">{{ __('Evidencia fotográfica') }} ({{ count($evidence) }})</span>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($evidence as $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener">
                            <img src="{{ $url }}" alt="{{ __('Evidencia') }}"
                                 style="width:140px;height:140px;border-radius:.5rem;object-fit:cover;background:var(--surface-2);">
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Checklist ejecutado, congelado (texto, norma y respuesta como estaban) --}}
        @if (count($snap))
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <h5 class="mb-3">{{ __('Checklist ejecutado') }}</h5>
                @foreach ($snap as $s)
                    <div class="insp-point {{ !empty($s['is_gate']) ? 'is-gate' : 'is-info' }}" style="margin-bottom:.5rem;">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <p class="insp-point__text mb-1">{{ $s['text'] ?? '' }}</p>
                            @if (($s['answer'] ?? '') === 'fail')
                                <span class="insp-tag insp-tag--gate">{{ __('FALLA') }}</span>
                            @else
                                <span class="insp-tag" style="background:#dcfce7;color:#166534;">{{ __('Cumple') }}</span>
                            @endif
                        </div>
                        <div class="insp-point__meta">
                            <span class="insp-tag">{{ $s['code'] ?? '' }}</span>
                            @if (! empty($s['is_gate']))<span class="insp-tag insp-tag--gate">{{ __('Compuerta') }}</span>@endif
                            @if (! empty($s['norm']))<span class="insp-tag">{{ $s['norm'] }}</span>@endif
                            @if (! empty($s['requires_document']))<span class="insp-tag">{{ __('Documento') }}</span>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Sello + QR + cadena CFDI (verificable públicamente) --}}
        @include('componentes._seal-cfdi', ['doc' => $inspection, 'folio' => $inspection->folio(), 'prefix' => 'CREWCARE-AMBU'])

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
