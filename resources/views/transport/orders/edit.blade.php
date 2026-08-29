@extends('layouts.app')
@section('content')
@php
    $callById  = $callPlaces->keyBy('id');
    $privById  = $privateAddresses->keyBy('id');
    $equipByCode = $equipment->keyBy('code');
    $vehById   = collect($vehicles)->keyBy('id');
    $crewById  = collect($crew)->keyBy('user_id');
    $deptById  = $departments->keyBy('id');
    $typeLabels = ['normal' => __('Normal'), 'aeropuerto' => __('Aeropuerto'), 'aplicacion' => __('Transporte de aplicación')];
    $byKey = $diff['byKey'] ?? [];
    $dsum  = $diff['summary'] ?? ['has_prev' => false, 'counts' => ['nueva' => 0, 'modificada' => 0, 'baja' => 0], 'dropped' => []];
    $totalChanges = ($dsum['counts']['nueva'] ?? 0) + ($dsum['counts']['modificada'] ?? 0) + ($dsum['counts']['baja'] ?? 0);

    // §3/§4: las privadas se enmascaran a 'CASA' salvo que el viewer esté en la allowlist ($realAddrIds).
    $realAddrIds = $realAddrIds ?? [];
    $placeLabel = function ($kind, $id, $text) use ($callById, $privById, $realAddrIds) {
        if ($kind === 'text')    return $text ?: '—';
        if ($kind === 'call')    return optional($callById->get($id))->name ?? '—';
        if ($kind === 'private') {
            $a = $privById->get($id);
            if (! $a) return '—';
            return in_array((int) $id, $realAddrIds, true) ? $a->label : $a->publicLabel();
        }
        return '—';
    };
    // Marca de cambio (doble señal): color+peso+▸, sobrevive B/N. $chg('campo') para una corrida.
    $mark = function (bool $on) { return $on ? 'cc-chg' : ''; };
    // Fase 2: pick up DERIVADO por corrida (SET) + elegibilidad discreta + si el viewer es transpo.
    $derived      = $derived ?? [];
    $discreetElig = $discreetElig ?? [];
    $viewerIsFull = $viewerIsFull ?? true;
    $snapByKey    = $snapByKey ?? [];
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <a href="{{ route('transport.order.index') }}" class="btn btn-sm btn-outline-secondary">
                @include('componentes._icon', ['name' => 'chevron-right', 'label' => null]) {{ __('Órdenes') }}
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('transport.order.agenda', $order) }}" class="btn btn-sm btn-outline-secondary">
                    @include('componentes._icon', ['name' => 'truck', 'label' => null]) {{ __('Agenda') }}
                </a>
                @if ($order->isFrozen())
                    <a href="{{ route('transport.order.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                        @include('componentes._icon', ['name' => 'file-text', 'label' => null]) {{ __('PDF') }}
                    </a>
                    <span class="badge bg-secondary">{{ __('Congelada') }} · v{{ $order->version }}</span>
                @else
                    <span class="badge bg-warning text-dark">{{ __('Borrador') }} · v{{ $order->version }}</span>
                @endif
            </div>
        </div>

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Orden de transportación') }}</h1>
                <p class="text-muted mb-0 small">{{ \Carbon\Carbon::parse($order->order_date)->translatedFormat('l d \d\e F Y') }}</p>
            </div>
        </div>

        @if (session('ok'))<div class="alert alert-success py-2">{{ session('ok') }}</div>@endif
        @if (session('warn'))<div class="alert alert-warning py-2">@include('componentes._icon', ['name' => 'map-pin', 'label' => null]) {{ session('warn') }}</div>@endif
        @if (session('info'))<div class="alert alert-info py-2">@include('componentes._icon', ['name' => 'chevron-up', 'label' => null]) {{ session('info') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger py-2">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        {{-- Cinta de cambios contra la versión inmediata anterior (§4). --}}
        @if (($dsum['has_prev'] ?? false) && $totalChanges > 0)
            <div class="alert alert-info py-2 d-flex flex-wrap gap-2 align-items-center">
                <strong>{{ __('Cambios vs. v') }}{{ $dsum['prev_version'] ?? ($order->version - 1) }}:</strong>
                @if($dsum['counts']['nueva'])<span class="badge bg-success">{{ $dsum['counts']['nueva'] }} {{ __('nueva(s)') }}</span>@endif
                @if($dsum['counts']['modificada'])<span class="badge bg-warning text-dark">{{ $dsum['counts']['modificada'] }} {{ __('modificada(s)') }}</span>@endif
                @if($dsum['counts']['baja'])<span class="badge bg-danger">{{ $dsum['counts']['baja'] }} {{ __('baja(s)') }}</span>@endif
            </div>
        @endif

        {{-- Encabezado por PUESTO (§1). --}}
        <details class="mb-3">
            <summary class="fw-semibold small text-uppercase text-muted" style="cursor:pointer">{{ __('Crew del día por puesto') }} ({{ $roster['counts']['total'] ?? 0 }})</summary>
            <div class="row g-2 mt-1">
                @forelse ($roster['groups'] as $g)
                    <div class="col-md-4"><div class="border rounded p-2 h-100">
                        <div class="small fw-semibold">{{ $g['label'] }}</div>
                        <ul class="list-unstyled mb-0 small text-muted">
                            @foreach ($g['people'] as $p)<li>{{ $p['name'] }}@if($p['cargo']) · {{ $p['cargo'] }}@endif</li>@endforeach
                        </ul>
                    </div></div>
                @empty
                    <div class="col-12"><p class="text-muted small mb-0">{{ __('Sin crew llamado ese día.') }}</p></div>
                @endforelse
            </div>
        </details>

        {{-- ===== PROPUESTA (§2): marcados en el back que faltan en la orden ===== --}}
        @if ($canEdit && ($proposal['count'] ?? 0) > 0)
            <div class="alert alert-primary d-flex flex-wrap align-items-center gap-2 py-2">
                <div class="flex-grow-1">
                    <strong>{{ __('Propuesta del back') }}:</strong>
                    {{ __(':n persona(s) marcada(s) que aún no están en la orden.', ['n' => $proposal['count']]) }}
                    <span class="small d-block text-muted">
                        @foreach ($proposal['groups'] as $g)
                            {{ optional($vehById->get($g['vehicle_id']))['label'] ?? __('Vehículo') }}: {{ collect($g['users'])->pluck('name')->implode(', ') }}@if(! $loop->last); @endif
                        @endforeach
                        @if (! empty($proposal['loose'])) · {{ __('Sin vehículo') }}: {{ collect($proposal['loose'])->pluck('name')->implode(', ') }}@endif
                    </span>
                </div>
                <form method="POST" action="{{ route('transport.order.proposal.accept', $order) }}">
                    @csrf<button class="btn btn-sm btn-primary">{{ __('Agregar a la orden') }}</button>
                </form>
            </div>
        @endif

        {{-- ===== TRASLAPE (§3): fuera-de-llamado comparte unidad con set → confirmación bilateral ===== --}}
        @if ($canEdit && ! empty($overlaps))
            <div class="alert alert-warning py-2">
                @include('componentes._icon', ['name' => 'shield-alert', 'label' => null])
                <strong>{{ __('Traslape de unidad') }}:</strong>
                {{ __('una corrida fuera de llamado comparte driver o vehículo con una de set — confírmalo con producción.') }}
                <ul class="mb-0 small mt-1">
                    @foreach ($overlaps as $ov)
                        <li>{{ optional($vehById->get($ov['fuera']->vehicle_id))['label'] ?? __('unidad') }} · {{ $ov['by'] === 'vehicle' ? __('mismo vehículo') : __('mismo conductor') }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ===== CORRIDAS ===== --}}
        <h2 class="h6 text-uppercase text-muted mb-2">{{ __('Corridas') }}</h2>

        @forelse ($order->runs as $run)
            @php
                $veh = $run->vehicle_id ? $vehById->get($run->vehicle_id) : null;
                $d = $byKey[$run->run_key] ?? ['status' => 'sin_cambio', 'changed' => []];
                $chg = fn ($f) => $mark(in_array($f, $d['changed'] ?? [], true));
                $dv = $derived[$run->id] ?? null;   // derivado (SET); null si es FUERA
                $isEvento = $run->isEvento();
            @endphp
            @if ($viewerIsFull || ! $run->is_discreet)
            <div class="border rounded-3 p-3 mb-3 {{ ($d['status'] ?? '') === 'nueva' ? 'border-success' : '' }} {{ $run->is_discreet ? 'border-warning' : '' }}">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        @if ($isEvento)
                            <span class="badge bg-info-subtle text-dark border {{ $chg('type_label') }}">@include('componentes._icon', ['name' => 'calendar', 'label' => null]) {{ __('Evento') }}</span>
                        @else
                        <span class="badge {{ $run->isSet() ? 'bg-primary-subtle' : 'bg-secondary-subtle' }} text-dark border">{{ $run->isSet() ? __('SET') : __('Fuera') }}</span>
                        <span class="badge bg-light text-dark border {{ $chg('type_label') }}">
                            @if ($run->run_type === 'aeropuerto')@include('componentes._icon', ['name' => 'map-pin', 'label' => null]) @endif
                            {{ $typeLabels[$run->run_type] ?? $run->run_type }}
                        </span>
                        @endif
                        @if ($run->is_discreet)<span class="badge bg-warning text-dark ms-1">@include('componentes._icon', ['name' => 'lock', 'label' => null]) {{ __('Discreta') }}</span>@endif
                        <span class="fw-semibold ms-2 {{ $chg('vehicle_label') }}">
                            @if ($veh){{ $veh['label'] }}@if($veh['plate']) <span class="text-muted font-monospace small">{{ $veh['plate'] }}</span>@endif
                            @elseif ($run->run_type === 'aplicacion')<span class="text-muted">{{ __('Sin vehículo (aplicación)') }}</span>
                            @else <span class="text-muted">—</span>@endif
                        </span>
                        @if (($d['status'] ?? '') === 'nueva')<span class="badge bg-success ms-2">{{ __('NUEVA') }}</span>@endif
                    </div>
                    @if ($canEdit)
                        <div class="d-flex gap-2">
                            @if (! empty($discreetElig[$run->id]))
                                <form method="POST" action="{{ route('transport.order.run.discreet', [$order, $run]) }}">
                                    @csrf<button class="btn btn-sm {{ $run->is_discreet ? 'btn-warning' : 'btn-outline-secondary' }}" title="{{ __('Discreta: no aparece en la orden ni el PDF; el back sí publica su hora') }}">
                                        @include('componentes._icon', ['name' => 'lock', 'label' => null]) {{ $run->is_discreet ? __('Mostrar') : __('Discreta') }}
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('transport.order.run.destroy', [$order, $run]) }}" onsubmit="return confirm('¿Eliminar la corrida?')">
                                @csrf<button class="btn btn-sm btn-outline-danger">{{ __('Eliminar') }}</button>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="row g-2 mt-1 small">
                    <div class="col-md-3"><span class="text-muted">{{ __('Conductor') }}:</span> <span class="{{ $chg('driver_label') }}">{{ $run->driver_user_id ? (optional($crewById->get($run->driver_user_id))['name'] ?? ('#'.$run->driver_user_id)) : '—' }}</span></div>
                    @if ($isEvento)
                        <div class="col-md-3"><span class="text-muted">{{ __('Horario') }}:</span> <span class="{{ $chg('pickup') }}">{{ $run->pickup_literal ?: '—' }}@if($run->end_literal) – {{ $run->end_literal }}@endif</span></div>
                        <div class="col-md-6"><span class="text-muted">{{ __('Descripción') }}:</span> <span class="{{ $chg('dest') }}">{{ $run->dest_text ?: '—' }}</span></div>
                    @elseif ($run->isSet())
                        @php $sr = $snapByKey[$run->run_key] ?? []; @endphp
                        <div class="col-md-3">
                            <span class="text-muted">{{ __('Pick up') }}:</span>
                            <span class="{{ $chg('pickup') }}">@if (trim($sr['pickup'] ?? '') !== '')<strong>{{ $sr['pickup'] }}</strong>@else<span class="text-warning">{{ __('sin ancla') }}</span>@endif</span>
                            @if ($dv && $dv['ok'])<div class="text-muted" style="font-size:.7rem">{{ __('traslado') }} {{ $dv['travel'] ?? '?' }}m · {{ __('ajuste') }} {{ (int) $run->travel_adjust_minutes }}m</div>@endif
                        </div>
                        <div class="col-md-3"><span class="text-muted">{{ __('Destino') }}:</span> <span class="{{ $chg('dest') }}">{{ ($sr['dest'] ?? '') ?: '—' }}</span></div>
                    @else
                        <div class="col-md-3"><span class="text-muted">{{ __('Pick up') }}:</span> <span class="{{ $chg('pickup') }}">{{ $run->pickup_literal ?: '—' }}@if($run->pickup_place_kind) · {{ $placeLabel($run->pickup_place_kind, $run->pickup_place_id, $run->pickup_place_text) }}@endif</span></div>
                        <div class="col-md-3"><span class="text-muted">{{ __('Destino') }}:</span> <span class="{{ $chg('dest') }}">{{ $placeLabel($run->dest_place_kind, $run->dest_place_id, $run->dest_text) }}</span></div>
                    @endif
                    @unless ($isEvento)
                    <div class="col-md-3">
                        <span class="text-muted">{{ __('Equipo') }}:</span>
                        <span class="{{ $chg('equipment_label') }}">
                        @forelse (($run->equipment ?? []) as $code)
                            <span class="badge bg-light text-dark border me-1" title="{{ optional($equipByCode->get($code))->name_es ?? $code }}">@include('componentes._transport-equip-icon', ['icon' => optional($equipByCode->get($code))->icon ?? '']) {{ optional($equipByCode->get($code))->name_es ?? $code }}</span>
                        @empty — @endforelse
                        </span>
                    </div>
                    @endunless
                </div>
                @if ($run->notes)<div class="small mt-1 {{ $chg('notes') }}">{{ $run->notes }}</div>@endif

                {{-- Ocupantes (un evento de vehículo no lleva). --}}
                @unless ($isEvento)
                <div class="mt-2 pt-2 border-top">
                    <div class="small text-muted text-uppercase mb-1 {{ $chg('occupants_label') }}">{{ __('Ocupantes') }}</div>
                    @foreach ($run->occupants as $occ)
                        <div class="d-flex align-items-center justify-content-between small py-1">
                            <span>
                                {{ $occ->displayName() ?: '—' }}
                                @if ($occ->source !== 'crew')<span class="badge bg-info-subtle text-dark border ms-1">{{ $occ->source }}</span>@endif
                                @if ($occ->department_id)<span class="text-muted"> · {{ optional($deptById->get($occ->department_id))->name }}</span>@endif
                                @if ($occ->load_note)<span class="text-muted"> · {{ $occ->load_note }}</span>@endif
                            </span>
                            @if ($canEdit)
                                <form method="POST" action="{{ route('transport.order.occupant.destroy', [$order, $occ]) }}">@csrf<button class="btn btn-sm btn-link text-danger p-0">{{ __('quitar') }}</button></form>
                            @endif
                        </div>
                    @endforeach

                    @if ($canEdit)
                        <form method="POST" action="{{ route('transport.order.occupant.store', [$order, $run]) }}" class="row g-1 mt-1 occ-form align-items-end">
                            @csrf
                            <div class="col-auto">
                                <select name="source" class="form-select form-select-sm occ-source">
                                    <option value="crew">{{ __('Crew') }}</option>
                                    <option value="cast">{{ __('Cast') }}</option>
                                    <option value="agency">{{ __('Agencia') }}</option>
                                    <option value="client">{{ __('Cliente') }}</option>
                                    <option value="free">{{ __('Libre') }}</option>
                                </select>
                            </div>
                            <div class="col">
                                <select name="user_id" class="form-select form-select-sm occ-crew js-typeahead">
                                    <option value="">{{ __('Buscar crew…') }}</option>
                                    @foreach ($crew as $c)<option value="{{ $c['user_id'] }}">{{ $c['name'] }}@if($c['cargo']) — {{ $c['cargo'] }}@endif</option>@endforeach
                                </select>
                                <input type="text" name="name" class="form-control form-control-sm occ-name d-none" list="ccPartyList" placeholder="{{ __('Nombre…') }}" autocomplete="off">
                            </div>
                            <div class="col-auto">
                                <select name="department_id" class="form-select form-select-sm">
                                    <option value="">{{ __('Depto') }}</option>
                                    @foreach ($departments as $dp)<option value="{{ $dp->id }}">{{ $dp->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-auto"><input type="text" name="load_note" class="form-control form-control-sm" placeholder="{{ __('Carga') }}" maxlength="120"></div>
                            <div class="col-auto"><button class="btn btn-sm btn-outline-primary">{{ __('+ ocupante') }}</button></div>
                        </form>
                    @endif
                </div>
                @endunless

                {{-- Editar la corrida EN EL LUGAR (conserva su identidad run_key). --}}
                @if ($canEdit)
                    <details class="mt-2">
                        <summary class="small text-primary" style="cursor:pointer">{{ __('Editar corrida') }}</summary>
                        <div class="mt-2">
                            @include('transport.orders._run-form', ['run' => $run, 'action' => route('transport.order.run.update', [$order, $run]), 'submitLabel' => __('Guardar corrida')])
                        </div>
                    </details>
                @endif
            </div>
            @endif {{-- fin envoltura: discretas ocultas a producción --}}
        @empty
            <p class="text-muted">{{ __('Sin corridas todavía.') }}</p>
        @endforelse

        {{-- Bajas contra la versión anterior (§4): no como corrida vacía, como nota. --}}
        @if (! empty($dsum['dropped']))
            <div class="border rounded p-2 mb-3 small">
                <span class="text-danger fw-semibold text-uppercase">{{ __('Bajas vs. v') }}{{ $dsum['prev_version'] ?? ($order->version - 1) }}:</span>
                <ul class="mb-0">
                    @foreach ($dsum['dropped'] as $dr)
                        <li>{{ $dr['type_label'] ?? '' }} · {{ $dr['vehicle_label'] ?: __('sin vehículo') }} @if($dr['pickup']) · {{ $dr['pickup'] }}@endif</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ===== NOTAS GENERALES (§1: hitos sin vehículo) ===== --}}
        <h2 class="h6 text-uppercase text-muted mb-2 mt-4">{{ __('Notas generales') }}</h2>
        @if ($canEdit)
            <form method="POST" action="{{ route('transport.order.update', $order) }}" class="mb-3">
                @csrf
                <div class="mb-2">
                    <label class="form-label small mb-0">{{ __('Modo de pick up') }}</label>
                    <select name="pickup_mode" class="form-select form-select-sm" style="max-width:320px">
                        <option value="masivo" @selected(! $order->isLigero())>{{ __('Masivo — la mayoría del crew') }}</option>
                        <option value="ligero" @selected($order->isLigero())>{{ __('Ligero — solo puestos marcados') }}</option>
                    </select>
                    <div class="form-text small">{{ __('Le dice al back qué esperar: en ligero, el crew no marcado queda en blanco (no “falta capturarlo”).') }}</div>
                </div>
                <textarea name="notes_general" class="form-control form-control-sm" rows="3" placeholder="{{ __('Hitos sin vehículo, avisos del día…') }}">{{ $order->notes_general }}</textarea>
                <button class="btn btn-sm btn-outline-secondary mt-1">{{ __('Guardar') }}</button>
            </form>
        @else
            <div class="mb-2"><span class="badge bg-light text-dark border">{{ __('Modo') }}: {{ $order->isLigero() ? __('ligero') : __('masivo') }}</span></div>
            @if ($order->notes_general)
                <div class="border rounded p-2 mb-3 small">{{ $order->notes_general }}</div>
            @endif
        @endif

        {{-- ===== LEYENDA (solo las claves usadas ese día) ===== --}}
        @php $lg = $legend ?? ['types' => [], 'equipment' => []]; @endphp
        @if (! empty($lg['types']) || ! empty($lg['equipment']))
            <div class="small text-muted mb-3">
                <span class="text-uppercase">{{ __('Leyenda') }}:</span>
                @foreach (($lg['equipment'] ?? []) as $code => $name)
                    <span class="me-2">@include('componentes._transport-equip-icon', ['icon' => optional($equipByCode->get($code))->icon ?? '']) {{ $name }}</span>
                @endforeach
                @if (! empty($lg['types']['aeropuerto']))<span class="me-2">@include('componentes._icon', ['name' => 'map-pin', 'label' => null]) {{ __('Aeropuerto') }}</span>@endif
            </div>
        @endif

        {{-- ===== AGREGAR CORRIDA ===== --}}
        @if ($canEdit)
            <div class="border rounded-3 p-3 bg-body-tertiary mb-3">
                <h3 class="h6 mb-2">{{ __('Agregar corrida') }}</h3>
                @include('transport.orders._run-form', ['run' => null, 'action' => route('transport.order.run.store', $order), 'submitLabel' => __('Agregar corrida')])
            </div>

            {{-- Emitir / congelar --}}
            <form method="POST" action="{{ route('transport.order.freeze', $order) }}" onsubmit="return confirm('¿Emitir esta versión? Una vez congelada no se edita; para cambios se emite una versión nueva.')">
                @csrf
                <button class="btn btn-primary">@include('componentes._icon', ['name' => 'clipboard-check', 'label' => null]) {{ __('Emitir / congelar versión') }} v{{ $order->version }}</button>
            </form>
        @elseif ($order->isFrozen())
            <p class="text-muted small">{{ __('Orden congelada: sólo lectura.') }}</p>
            @if (\App\Support\TransportAccess::canFull(auth()->user()))
                <form method="POST" action="{{ route('transport.order.create') }}">
                    @csrf
                    <input type="hidden" name="order_date" value="{{ \Carbon\Carbon::parse($order->order_date)->toDateString() }}">
                    <button class="btn btn-outline-primary">@include('componentes._icon', ['name' => 'file-plus', 'label' => null]) {{ __('Emitir nueva versión') }}</button>
                </form>
            @endif
        @endif

        {{-- UUID discreto (control); en el PDF va al pie de todas las páginas. --}}
        <p class="text-muted mt-4" style="font-size:.72rem">{{ __('Control') }}: {{ $order->uuid }}</p>

        <datalist id="ccPartyList">
            @foreach ($parties as $kind => $list)@foreach ($list as $party)<option value="{{ $party->name }}">@endforeach @endforeach
        </datalist>

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
    <style>
        .cc-chg { color:#c2410c; font-weight:600; }
        .cc-chg::before { content:"\25B8\00a0"; } /* ▸ — sobrevive impresión B/N */
    </style>
@endpush

@push('scripts')
    @include('componentes._typeahead')
<script>
(function () {
    // Cada formulario de corrida se cablea por SÍ MISMO (hay varios: alta + edición por corrida).
    document.querySelectorAll('.cc-run-form').forEach(function (form) {
        var veh = form.querySelector('.cc-veh'),
            drv = form.querySelector('.cc-driver'),
            rt  = form.querySelector('.cc-runtype'),
            hint = form.querySelector('.cc-veh-hint');
        if (veh && drv) {
            veh.addEventListener('change', function () {
                var opt = veh.options[veh.selectedIndex];
                var d = opt ? opt.getAttribute('data-driver') : '';
                if (d && !drv.value) { drv.value = d; drv.dispatchEvent(new Event('change')); }
            });
        }
        if (rt && hint) {
            var syncHint = function () { hint.textContent = rt.value === 'aplicacion' ? '{{ __('(opcional)') }}' : '{{ __('(obligatorio)') }}'; };
            rt.addEventListener('change', syncHint); syncHint();
        }
        form.querySelectorAll('.cc-ref').forEach(function (sel) {
            var target = form.querySelector(sel.getAttribute('data-text'));
            var sync = function () { if (target) target.classList.toggle('d-none', sel.value !== 'text'); };
            sel.addEventListener('change', sync); sync();
        });
        // Clase de corrida: SET (derivado) · FUERA (a mano) · EVENTO (agenda del vehículo) alternan bloques.
        var rc = form.querySelector('.cc-runclass'),
            setB = form.querySelector('.cc-set-block'),
            fueraB = form.querySelector('.cc-fuera-block'),
            eventoB = form.querySelector('.cc-evento-block'),
            endB = form.querySelector('.cc-corrida-end');
        if (rc) {
            var syncClass = function () {
                var v = rc.value;
                if (setB) setB.classList.toggle('d-none', v !== 'set');
                if (fueraB) fueraB.classList.toggle('d-none', v !== 'fuera');
                if (eventoB) eventoB.classList.toggle('d-none', v !== 'evento');
                if (endB) endB.classList.toggle('d-none', v === 'evento'); // el evento tiene su propio Fin
            };
            rc.addEventListener('change', syncClass); syncClass();
        }
    });

    // Ocupante: la fuente decide crew (select) vs nombre libre (texto + padrón).
    document.querySelectorAll('.occ-form').forEach(function (form) {
        var src = form.querySelector('.occ-source'),
            crew = form.querySelector('.occ-crew'),
            name = form.querySelector('.occ-name');
        if (!src) return;
        var sync = function () {
            var isCrew = src.value === 'crew';
            if (crew) crew.classList.toggle('d-none', !isCrew);
            if (name) name.classList.toggle('d-none', isCrew);
        };
        src.addEventListener('change', sync); sync();
    });
})();
</script>
@endpush
@endsection
