@extends('layouts.app')
@section('content')
@php
    $snap = is_array($inspection->checklist_snapshot) ? $inspection->checklist_snapshot : [];
    $verdictMap = [
        'paro'                    => ['class' => 'verdict--paro',   'icon' => 'octagon-alert', 'title' => __('PARO INMEDIATO'),        'sub' => __('La herramienta no se usa.')],
        'actividad_no_ejecutable' => ['class' => 'verdict--noexec', 'icon' => 'alert-triangle','title' => __('ACTIVIDAD NO EJECUTABLE'),'sub' => __('La herramienta puede estar bien; la actividad no procede.')],
        'apta'                    => ['class' => 'verdict--apta',   'icon' => 'shield-check',  'title' => __('APTA'),                    'sub' => __('Sigue en operación.')],
    ];
    $v = $verdictMap[$inspection->verdict] ?? $verdictMap['apta'];
    // A2 · un PARO en PRE-USO se REDACTA como "equipo no autorizado" (hay ventana), no paro.
    if ($inspection->verdict === 'paro' && $inspection->isPreUse()) {
        $v = ['class' => 'verdict--noexec', 'icon' => 'alert-triangle',
              'title' => __('EQUIPO NO AUTORIZADO'),
              'sub'   => __('Nada está corriendo: hay ventana para corregir o sustituir antes del uso.')];
    }
    $pathLabel = [
        'reemplazo' => __('Fuera de servicio: sale y se reemplaza.'),
        'correccion_mismo_dia' => __('Corrección el mismo día; vuelve hoy si se corrige y se reinspecciona.'),
    ];
    $momentLabels = ['llegada_equipo'=>__('Llegada del equipo'),'previo_al_uso'=>__('Previo al uso'),'en_uso'=>__('En uso'),'por_hallazgo'=>__('Por hallazgo')];
    $scopeLabels = ['universal'=>__('Universal'),'universal_energizada'=>__('Energizada'),'familia'=>__('Familia'),'tipo'=>__('Tipo'),'actividad'=>__('Actividad')];
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 860px;">

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="crew-title mb-0">{{ __('Acta de inspección') }}</h1>
                <p class="text-muted mb-0 small">{{ $inspection->folio() }}</p>
            </div>
            <a href="{{ route('tools.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Catálogo') }}</a>
        </div>

        {{-- Estado RETIRADO (Parte B): el sello sigue válido; el documento ya no está vigente. --}}
        @if ($inspection->isRetired())
            <div class="alert alert-secondary d-flex align-items-start gap-2">
                @include('componentes._icon', ['name' => 'lock', 'label' => null])
                <div>
                    <strong>{{ __('Acta RETIRADA') }}</strong> — {{ __('el sello sigue siendo válido; solo cambió de estado.') }}
                    @if ($inspection->retired_at)<br><span class="small text-muted">{{ __('Retirada el') }} {{ optional($inspection->retired_at)->format('d/m/Y H:i') }}@if($inspection->retired_reason) · {{ $inspection->retired_reason }}@endif</span>@endif
                    @if ($inspection->supersededBy)<br><span class="small">{{ __('Sustituida por') }} <a href="{{ route('tools.inspection.show', $inspection->supersededBy->uuid) }}">{{ $inspection->supersededBy->folio() }}</a></span>@endif
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

        {{-- Desbloqueo del PARO (acto con autor) --}}
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
                        <form method="post" action="{{ route('tools.inspection.unblock', $inspection->uuid) }}"
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

        {{-- A5 · LA OBLIGACIÓN: el action item ligado al acta (flujo PDCA existente). --}}
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
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Herramienta') }}</span>
                    <strong>{{ $inspection->tool_name }}</strong> <span class="text-muted">({{ $inspection->tool_code }})</span></div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Marca / modelo') }}</span>
                    {{ $inspection->tool_model ?: '—' }}</div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Departamento') }}</span>
                    {{ $inspection->department_name ?: '—' }}</div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Checklist') }}</span>
                    {{ $inspection->checklist_mode === 'operator' ? __('Operador (pre-turno)') : __('Safety (al detectar)') }}</div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Momento') }}</span>
                    {{ $momentLabels[$inspection->inspection_moment] ?? '—' }}</div>
                @if ($inspection->origin_type && $inspection->origin_id)
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Origen') }}</span>
                        {{ __('Ligada a un reporte') }} #{{ $inspection->origin_id }}</div>
                @endif
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Inspector') }}</span>
                    {{ $inspection->inspector_name ?: '—' }}
                    @if ($inspection->inspector_cedula)<span class="text-muted"> · {{ __('Cédula') }} {{ $inspection->inspector_cedula }}</span>@endif</div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Fecha') }}</span>
                    {{ optional($inspection->created_at)->format('d/m/Y H:i') }}</div>
            </div>
            @if ($inspection->observations)
                <hr>
                <span class="text-muted small d-block mb-1">{{ __('Observaciones') }}</span>
                <div style="white-space: pre-line;">{{ $inspection->observations }}</div>
            @endif
        </div>

        {{-- Checklist ejecutado, congelado (texto, norma y respuesta como estaban) --}}
        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
            <h5 class="mb-3">{{ __('Checklist ejecutado') }}</h5>
            @php $lastScope = null; @endphp
            @foreach ($snap as $s)
                @if (($s['scope'] ?? null) !== $lastScope)
                    <div class="insp-scope-head">{{ $scopeLabels[$s['scope'] ?? ''] ?? ($s['scope'] ?? '') }}</div>
                    @php $lastScope = $s['scope'] ?? null; @endphp
                @endif
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
                        @foreach (($s['standards'] ?? []) as $sc)<span class="insp-tag">{{ $sc }}</span>@endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Sello + QR + cadena CFDI (verificable públicamente) --}}
        @include('componentes._seal-cfdi', ['doc' => $inspection, 'folio' => $inspection->folio(), 'prefix' => 'CREWCARE-INSP'])

        {{-- Retirar el acta (Parte B): NO re-sella; el sello sigue válido, cambia el estado. --}}
        @if (! $inspection->isRetired())
            <details class="mt-3">
                <summary class="text-muted small" style="cursor:pointer;">{{ __('Retirar esta acta') }}</summary>
                <form method="post" action="{{ route('tools.inspection.retire', $inspection->uuid) }}" class="mt-2 card border-0 shadow-sm rounded-3 p-3"
                      onsubmit="return confirm('{{ __('¿Retirar el acta? Deja de estar vigente. El sello sigue siendo válido.') }}');">
                    @csrf
                    <label class="form-label small fw-semibold">{{ __('Motivo (opcional)') }}</label>
                    <input type="text" name="retired_reason" class="form-control mb-2" maxlength="255" placeholder="{{ __('p. ej. equipo sustituido / reinspeccionado') }}">
                    <label class="form-label small fw-semibold">{{ __('Sustituida por (UUID de otra acta, opcional)') }}</label>
                    <input type="text" name="superseded_by" class="form-control mb-2" placeholder="{{ __('opcional') }}">
                    <div><button class="btn btn-sm btn-outline-danger">{{ __('Retirar acta') }}</button></div>
                </form>
            </details>
        @endif

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
