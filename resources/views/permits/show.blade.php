@extends('layouts.app')
@section('content')
@php
    $pts = is_array($issued->points_snapshot) ? $issued->points_snapshot : [];
    $revs = is_array($issued->reverifications) ? $issued->reverifications : [];
    $execLabel = ['safety' => __('Safety'), 'especialista' => __('Especialista')];
    $scope = $issued->permit_site_scope;
    $isOpen = $issued->isOpen();
    // Estado terminal (para el cintillo): cerrado tiene prioridad sobre suspendido.
    $stateLabel = $issued->isClosed() ? __('CERRADO') : ($issued->isSuspended() ? __('SUSPENDIDO') : null);
    $stateWhen  = $issued->isClosed() ? $issued->closed_at : ($issued->isSuspended() ? $issued->suspended_at : null);
    // Detalle del cintillo precomputado: evita @if inline anidados (rompen el compilador de Blade).
    $stateDetail = null;
    if ($stateWhen) {
        $stateDetail = optional($stateWhen)->format('d/m/Y H:i');
        if ($issued->isSuspended() && $issued->suspended_reason) { $stateDetail .= ' · '.$issued->suspended_reason; }
        if ($issued->isClosed() && $issued->closed_by_name)     { $stateDetail .= ' · '.__('por').' '.$issued->closed_by_name; }
    }
    // Vigencia externa vencida: se marca de forma PERSISTENTE (no solo el flash de emisión), para que
    // quien lea el documento en sitio lo vea aunque haya vencido después de emitirse.
    $extExpired = $issued->ext_auth_valid_until && $issued->ext_auth_valid_until->lt(now()->startOfDay());
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 860px;">

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="crew-title mb-0">{{ __('Permiso de trabajo') }}</h1>
                <p class="text-muted mb-0 small">{{ $issued->folio() }} · {{ $issued->permit_code }}</p>
            </div>
            <a href="{{ route('permits.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Permisos') }}</a>
        </div>

        {{-- Estado CERRADO / SUSPENDIDO: el sello sigue válido; sólo cambió de estado. --}}
        @if ($stateLabel)
            <div class="alert alert-secondary d-flex align-items-start gap-2">
                @include('componentes._icon', ['name' => 'lock', 'label' => null])
                <div>
                    <strong>{{ __('Permiso') }} {{ $stateLabel }}</strong> — {{ __('el sello sigue siendo válido; solo cambió de estado.') }}
                    @if ($stateDetail)<br><span class="small text-muted">{{ $stateDetail }}</span>@endif
                    @if ($issued->supersededBy)<br><span class="small">{{ __('Sustituido por') }} <a href="{{ route('permits.show', $issued->supersededBy->uuid) }}">{{ $issued->supersededBy->folio() }}</a></span>@endif
                </div>
            </div>
        @endif
        {{-- Separado del bloque de estado (no @elseif): un @if…@endif inline anidado antes de un
             @elseif rompe el compilador naíf de Blade. --}}
        @if (! $stateLabel && $issued->isPendingClose($shootDay))
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                @include('componentes._icon', ['name' => 'octagon-alert', 'label' => null])
                <span>{{ __('PENDIENTE DE CIERRE') }} — {{ __('emitido en la jornada') }} {{ $issued->shoot_day }} {{ __('y nunca cerrado.') }}</span>
            </div>
        @endif

        {{-- Datos congelados de la emisión --}}
        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
            <h5 class="mb-2">{{ $issued->permit_name }}</h5>
            <div class="row g-3 small">
                <div class="col-12"><span class="text-muted d-block">{{ __('Actividad autorizada') }}</span>
                    <strong>{{ $issued->activity_description }}</strong></div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Sitio') }}</span>{{ $issued->site_label ?: '—' }}</div>
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Día de rodaje') }}</span>{{ is_null($issued->shoot_day) ? '—' : $issued->shoot_day }}</div>
                @if ($issued->tool_name)
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Disparado por') }}</span>{{ $issued->tool_name }} ({{ $issued->tool_code }})</div>
                @endif
                <div class="col-md-6"><span class="text-muted d-block">{{ __('Alcance de sitio') }}</span>{{ $scope }}</div>
            </div>
        </div>

        {{-- DOS FIRMAS congeladas: emite / acepta --}}
        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
            <div class="row g-3 small">
                <div class="col-md-6">
                    <span class="text-muted d-block">{{ __('Emite (Safety)') }}</span>
                    <strong>{{ $issued->issuer_name ?: '—' }}</strong>
                    @if ($issued->issuer_cedula)<span class="text-muted"> · {{ __('Cédula') }} {{ $issued->issuer_cedula }}</span>@endif
                    @if ($issued->issuer_role)<div class="text-muted">{{ $issued->issuer_role }}</div>@endif
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">{{ __('Acepta (ejecutante designado)') }}</span>
                    <strong>{{ $issued->acceptor_name ?: '—' }}</strong>
                    @if ($issued->acceptor_role)<span class="text-muted"> · {{ $issued->acceptor_role }}</span>@endif
                    @if ($issued->acceptor_id_value)<div class="text-muted">{{ $issued->acceptor_id_label ?: __('ID') }}: {{ $issued->acceptor_id_value }}</div>@endif
                    @if ($issued->accepted_at)<div class="text-muted">{{ optional($issued->accepted_at)->format('d/m/Y H:i') }}</div>@endif
                </div>
            </div>
        </div>

        {{-- AUTORIZACIÓN EXTERNA declarada (Paso 2): se enuncia como declaración, no verificación. --}}
        @if ($issued->ext_auth_mandatory || $issued->ext_auth_folio || $issued->ext_auth_authority)
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3" style="border-left:4px solid #6b7280 !important;">
                <div class="d-flex align-items-center gap-2 mb-1">
                    @include('componentes._icon', ['name' => 'stamp', 'label' => null])
                    <strong>{{ __('Autorización externa') }}</strong>
                    <span class="insp-tag">{{ $issued->ext_auth_mandatory ? __('obligatoria') : __('opcional') }}</span>
                </div>
                <p class="small text-muted mb-2">{{ __('DECLARADA bajo responsabilidad de quien la capturó. La app NO la verificó.') }}</p>
                <div class="row g-2 small">
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Autoridad') }}</span>{{ $issued->ext_auth_authority ?: '—' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Folio') }}</span>{{ $issued->ext_auth_folio ?: '—' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Vigencia hasta') }}</span>{{ $issued->ext_auth_valid_until ? optional($issued->ext_auth_valid_until)->format('d/m/Y') : '—' }}@if($extExpired) <span class="insp-tag insp-tag--gate">{{ __('VENCIDA') }}</span>@endif</div>
                    <div class="col-md-6"><span class="text-muted d-block">{{ __('Declarada por') }}</span>{{ $issued->ext_auth_declared_by ?: '—' }}</div>
                    @if ($issued->ext_auth_note)<div class="col-12"><span class="text-muted d-block">{{ __('Observaciones') }}</span>{{ $issued->ext_auth_note }}</div>@endif
                </div>
            </div>
        @endif

        {{-- Puntos compuerta congelados (todos cumplidos: no hay permiso a medias) --}}
        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
            <h5 class="mb-3">{{ __('Puntos verificados') }} <span class="insp-tag">{{ count($pts) }}</span></h5>
            @foreach ($pts as $s)
                <div class="insp-point is-gate" style="margin-bottom:.5rem;">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <p class="insp-point__text mb-1">{{ $s['text'] ?? '' }}</p>
                        <span class="insp-tag" style="background:#dcfce7;color:#166534;">{{ __('Cumple') }}</span>
                    </div>
                    <div class="insp-point__meta">
                        <span class="insp-tag">{{ $s['code'] ?? '' }}</span>
                        @if (! empty($s['executor']))<span class="insp-tag">{{ $execLabel[$s['executor']] ?? $s['executor'] }}</span>@endif
                        @if (! empty($s['site_sensitive']))<span class="insp-tag">{{ __('sensible al sitio') }}</span>@endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- FOTOGRAFÍAS congeladas (parte del documento sellado; rutas raíz-relativas /storage/…). --}}
        @php $photos = is_array($issued->photos) ? array_filter($issued->photos) : []; @endphp
        @if (! empty($photos))
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    @include('componentes._icon', ['name' => 'camera', 'label' => null])
                    <strong>{{ __('Fotografías') }}</strong>
                    <span class="insp-tag">{{ count($photos) }}</span>
                </div>
                <div class="row g-2">
                    @foreach ($photos as $src)
                        <div class="col-6 col-md-4">
                            <a href="{{ $src }}" target="_blank" rel="noopener">
                                <img src="{{ $src }}" alt="{{ __('Fotografía del permiso') }}"
                                     class="img-fluid rounded-3" style="width:100%;aspect-ratio:4/3;object-fit:cover;border:1px solid rgba(0,0,0,.12);">
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Sello + QR + cadena CFDI (verificable públicamente) --}}
        @include('componentes._seal-cfdi', ['doc' => $issued, 'folio' => $issued->folio(), 'prefix' => 'CREWCARE-PERM'])

        {{-- ===================== REVERIFICACIÓN / SITIO (Paso 3) ===================== --}}
        @if ($isOpen)
            @if ($scope === 'reverificacion')
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mt-3 mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        @include('componentes._icon', ['name' => 'refresh-cw', 'label' => null])
                        <strong>{{ __('Reverificar en otro sitio') }}</strong>
                    </div>
                    <p class="small text-muted mb-2">
                        {{ __('Al reanudar en otro lugar se re-corren SÓLO los puntos sensibles al sitio, no el permiso completo.') }}
                        {{ __('Si el rig o el anclaje se desmontó y se volvió a montar, NO es mover: es emisión nueva.') }}
                        <a href="{{ route('permits.create', ['permit' => $issued->permit_id, 'supersedes' => $issued->uuid]) }}">{{ __('Emitir uno nuevo') }}</a>.
                    </p>
                    @if ($siteSensitive->isEmpty())
                        <p class="small text-muted mb-0">{{ __('Este permiso no tiene puntos sensibles al sitio.') }}</p>
                    @else
                        <form method="post" action="{{ route('permits.reverify', $issued->uuid) }}">
                            @csrf
                            <label class="form-label small fw-semibold">{{ __('Nuevo sitio') }} *</label>
                            <input type="text" name="new_site_label" class="form-control mb-2" maxlength="255" required placeholder="{{ __('a dónde se movió') }}">
                            @foreach ($siteSensitive as $s)
                                <div class="insp-point is-gate" style="margin-bottom:.5rem;">
                                    <p class="insp-point__text mb-1">{{ $s['text'] ?? '' }}</p>
                                    <div class="insp-point__meta"><span class="insp-tag">{{ $s['code'] ?? '' }}</span></div>
                                    <div class="insp-answer">
                                        <label><input type="radio" name="answers[{{ $s['code'] }}]" value="cumple" required>
                                            <span class="btn-ans">{{ __('Cumple') }}</span></label>
                                        <label><input type="radio" name="answers[{{ $s['code'] }}]" value="no_cumple">
                                            <span class="btn-ans">{{ __('No cumple') }}</span></label>
                                    </div>
                                </div>
                            @endforeach
                            <button class="btn btn-sm btn-crew-accent">{{ __('Registrar reverificación') }}</button>
                        </form>
                    @endif
                </div>
            @elseif ($scope === 'ligado_al_sitio')
                <div class="alert alert-info d-flex align-items-start gap-2 mt-3 small">
                    @include('componentes._icon', ['name' => 'map-pin', 'label' => null])
                    <span>{{ __('Este permiso está LIGADO AL SITIO: cambiar de sitio exige emisión nueva.') }}
                        <a href="{{ route('permits.create', ['permit' => $issued->permit_id, 'supersedes' => $issued->uuid]) }}">{{ __('Emitir uno nuevo') }}</a>.</span>
                </div>
            @else
                <div class="alert alert-light border d-flex align-items-start gap-2 mt-3 small">
                    @include('componentes._icon', ['name' => 'map-pin', 'label' => null])
                    <span>{{ __('Vale por la jornada, sin importar el sitio.') }}</span>
                </div>
            @endif
        @endif

        {{-- Historial de reverificaciones (registro en el mismo permiso, sin re-sellar) --}}
        @if (! empty($revs))
            <div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
                <div class="small text-muted mb-2">{{ __('Reverificaciones') }}</div>
                @foreach ($revs as $r)
                    <div class="d-flex justify-content-between align-items-center small border-bottom py-1">
                        <span>
                            @if (($r['result'] ?? '') === 'ok')<span class="insp-tag" style="background:#dcfce7;color:#166534;">{{ __('OK') }}</span>
                            @else<span class="insp-tag insp-tag--gate">{{ __('FALLÓ') }}</span>@endif
                            {{ $r['site_label'] ?? '' }}
                        </span>
                        <span class="text-muted">{{ $r['by_name'] ?? '' }} · {{ $r['at'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- A5 · Condiciones pendientes al cierre → action item PDCA --}}
        @if (! empty($actionItem))
            @php $aiClosed = $actionItem->status === \App\Models\ActionItem::STATUS_CLOSED; @endphp
            <div class="card border-0 shadow-sm rounded-3 p-3 mb-3" style="border-left:4px solid {{ $aiClosed ? '#16a34a' : '#b45309' }} !important;">
                <div class="d-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => $aiClosed ? 'clipboard-check' : 'clipboard-list', 'label' => null])
                    <strong>{{ __('Condición pendiente') }}</strong>
                    <span class="insp-tag">{{ $aiClosed ? __('Cerrada') : __('Abierta') }}</span>
                </div>
                <div class="small mt-1">{{ $actionItem->description }}</div>
            </div>
        @endif

        {{-- ===================== CIERRE / SUSPENSIÓN (Paso 4) ===================== --}}
        @if ($isOpen)
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3" style="border-left:4px solid #16a34a !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    @include('componentes._icon', ['name' => 'circle-check', 'label' => null])
                    <strong>{{ __('Cerrar el permiso') }}</strong>
                </div>
                <p class="small text-muted mb-2">{{ __('Todo permiso emitido se cierra al terminar la actividad, con autor y hora.') }}</p>
                <form method="post" action="{{ route('permits.close', $issued->uuid) }}"
                      onsubmit="return confirm('{{ __('¿Cerrar el permiso? Queda registrado con tu nombre y hora.') }}');">
                    @csrf
                    @if ($issued->requires_fire_watch)
                        <div class="alert alert-warning d-flex align-items-start gap-2 py-2 small mb-2">
                            @include('componentes._icon', ['name' => 'flame', 'label' => null])
                            <label class="d-inline-flex align-items-start gap-2 mb-0">
                                <input type="checkbox" name="fire_watch_confirmed" value="1" class="mt-1">
                                <span>{{ __('Declaro cumplida la vigilancia posterior a la actividad (trabajo en caliente).') }}</span>
                            </label>
                        </div>
                    @endif
                    <label class="form-label small fw-semibold">{{ __('Condiciones pendientes (opcional)') }}</label>
                    <input type="text" name="pending_conditions" class="form-control mb-2" maxlength="2000" placeholder="{{ __('genera una acción correctiva de seguimiento') }}">
                    <label class="form-label small fw-semibold">{{ __('Notas de cierre (opcional)') }}</label>
                    <input type="text" name="close_notes" class="form-control mb-2" maxlength="2000">
                    <button class="btn btn-success d-inline-flex align-items-center gap-2">
                        @include('componentes._icon', ['name' => 'circle-check', 'label' => null])
                        {{ __('Cerrar permiso') }}
                    </button>
                </form>
            </div>

            <details class="mb-3">
                <summary class="text-muted small" style="cursor:pointer;">{{ __('Suspender el permiso') }}</summary>
                <form method="post" action="{{ route('permits.suspend', $issued->uuid) }}" class="mt-2 card border-0 shadow-sm rounded-3 p-3"
                      onsubmit="return confirm('{{ __('¿Suspender? No es cerrar ni alterar; el sello sigue válido.') }}');">
                    @csrf
                    <p class="small text-muted mb-2">{{ __('Cambió el clima, entró personal a la zona… Suspender NO es cerrar y NO es alterar.') }}</p>
                    <label class="form-label small fw-semibold">{{ __('Motivo') }} *</label>
                    <input type="text" name="suspended_reason" class="form-control mb-2" maxlength="255" required>
                    <div><button class="btn btn-sm btn-outline-danger">{{ __('Suspender') }}</button></div>
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
