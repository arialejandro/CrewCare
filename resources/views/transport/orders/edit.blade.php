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

    // Rótulo de un lugar de corrida. En el EDITOR transpo ve el rótulo real (el enmascarado 'CASA'
    // es del PDF/no-transpo). kind: call|private|text.
    $placeLabel = function ($kind, $id, $text) use ($callById, $privById) {
        if ($kind === 'text')    return $text ?: '—';
        if ($kind === 'call')    return optional($callById->get($id))->name ?? '—';
        if ($kind === 'private') return optional($privById->get($id))->label ?? '—';
        return '—';
    };
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <a href="{{ route('transport.order.index') }}" class="btn btn-sm btn-outline-secondary">
                @include('componentes._icon', ['name' => 'chevron-right', 'label' => null]) {{ __('Órdenes') }}
            </a>
            @if ($order->isFrozen())
                <span class="badge bg-secondary">{{ __('Congelada') }} · v{{ $order->version }}</span>
            @else
                <span class="badge bg-warning text-dark">{{ __('Borrador') }} · v{{ $order->version }}</span>
            @endif
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

        @if (session('ok'))
            <div class="alert alert-success py-2">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger py-2">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        {{-- Encabezado por PUESTO (§1): crew registrado del día, dinámico. Referencia para armar ocupantes. --}}
        <details class="mb-3">
            <summary class="fw-semibold small text-uppercase text-muted" style="cursor:pointer">
                {{ __('Crew del día por puesto') }} ({{ $roster['counts']['total'] ?? 0 }})
            </summary>
            <div class="row g-2 mt-1">
                @forelse ($roster['groups'] as $g)
                    <div class="col-md-4">
                        <div class="border rounded p-2 h-100">
                            <div class="small fw-semibold">{{ $g['label'] }}</div>
                            <ul class="list-unstyled mb-0 small text-muted">
                                @foreach ($g['people'] as $p)
                                    <li>{{ $p['name'] }}@if($p['cargo']) · {{ $p['cargo'] }}@endif</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><p class="text-muted small mb-0">{{ __('Sin crew llamado ese día.') }}</p></div>
                @endforelse
            </div>
        </details>

        {{-- ===== CORRIDAS ===== --}}
        <h2 class="h6 text-uppercase text-muted mb-2">{{ __('Corridas') }}</h2>

        @forelse ($order->runs as $run)
            @php $veh = $run->vehicle_id ? $vehById->get($run->vehicle_id) : null; @endphp
            <div class="border rounded-3 p-3 mb-3">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="badge bg-light text-dark border">
                            @if ($run->run_type === 'aeropuerto')@include('componentes._icon', ['name' => 'map-pin', 'label' => null]) @endif
                            {{ $typeLabels[$run->run_type] ?? $run->run_type }}
                        </span>
                        <span class="fw-semibold ms-2">
                            @if ($veh){{ $veh['label'] }}@if($veh['plate']) <span class="text-muted font-monospace small">{{ $veh['plate'] }}</span>@endif
                            @elseif ($run->run_type === 'aplicacion')<span class="text-muted">{{ __('Sin vehículo (aplicación)') }}</span>
                            @else <span class="text-muted">—</span>@endif
                        </span>
                    </div>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('transport.order.run.destroy', [$order, $run]) }}" onsubmit="return confirm('¿Eliminar la corrida?')">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">{{ __('Eliminar') }}</button>
                        </form>
                    @endif
                </div>

                <div class="row g-2 mt-1 small">
                    <div class="col-md-3"><span class="text-muted">{{ __('Conductor') }}:</span> {{ $run->driver_user_id ? optional($crewById->get($run->driver_user_id))['name'] ?? ('#'.$run->driver_user_id) : '—' }}</div>
                    <div class="col-md-3"><span class="text-muted">{{ __('Pick up') }}:</span> {{ $run->pickup_literal ?: '—' }} @if($run->pickup_place_kind) · {{ $placeLabel($run->pickup_place_kind, $run->pickup_place_id, $run->pickup_place_text) }}@endif</div>
                    <div class="col-md-3"><span class="text-muted">{{ __('Destino') }}:</span> {{ $placeLabel($run->dest_place_kind, $run->dest_place_id, $run->dest_text) }}</div>
                    <div class="col-md-3">
                        <span class="text-muted">{{ __('Equipo') }}:</span>
                        @forelse (($run->equipment ?? []) as $code)
                            <span class="badge bg-light text-dark border me-1" title="{{ optional($equipByCode->get($code))->name_es ?? $code }}">
                                @include('componentes._transport-equip-icon', ['icon' => optional($equipByCode->get($code))->icon ?? ''])
                                {{ optional($equipByCode->get($code))->name_es ?? $code }}
                            </span>
                        @empty — @endforelse
                    </div>
                </div>
                @if ($run->notes)<div class="small text-muted mt-1">{{ $run->notes }}</div>@endif

                {{-- Ocupantes de la corrida --}}
                <div class="mt-2 pt-2 border-top">
                    <div class="small text-muted text-uppercase mb-1">{{ __('Ocupantes') }}</div>
                    @foreach ($run->occupants as $occ)
                        <div class="d-flex align-items-center justify-content-between small py-1">
                            <span>
                                {{ $occ->displayName() ?: '—' }}
                                @if ($occ->source !== 'crew')<span class="badge bg-info-subtle text-dark border ms-1">{{ $occ->source }}</span>@endif
                                @if ($occ->department_id) · <span class="text-muted">{{ optional($deptById->get($occ->department_id))->name }}</span>@endif
                                @if ($occ->load_note) · <span class="text-muted">{{ $occ->load_note }}</span>@endif
                            </span>
                            @if ($canEdit)
                                <form method="POST" action="{{ route('transport.order.occupant.destroy', [$order, $occ]) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-link text-danger p-0">{{ __('quitar') }}</button>
                                </form>
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
                                    @foreach ($crew as $c)
                                        <option value="{{ $c['user_id'] }}">{{ $c['name'] }}@if($c['cargo']) — {{ $c['cargo'] }}@endif</option>
                                    @endforeach
                                </select>
                                <input type="text" name="name" class="form-control form-control-sm occ-name d-none" list="ccPartyList" placeholder="{{ __('Nombre…') }}" autocomplete="off">
                            </div>
                            <div class="col-auto">
                                <select name="department_id" class="form-select form-select-sm">
                                    <option value="">{{ __('Depto') }}</option>
                                    @foreach ($departments as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <input type="text" name="load_note" class="form-control form-control-sm" placeholder="{{ __('Carga') }}" maxlength="120">
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-sm btn-outline-primary">{{ __('+ ocupante') }}</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-muted">{{ __('Sin corridas todavía.') }}@if(!$canEdit && !$order->isFrozen()) {{ __('El borrador está vacío.') }}@endif</p>
        @endforelse

        {{-- Hitos SIN vehículo van a NOTAS GENERALES (§1); notas + emisión de versión = capa siguiente. --}}
        @if ($order->notes_general)
            <div class="border rounded p-2 mb-3 small"><span class="text-muted text-uppercase">{{ __('Notas generales') }}:</span> {{ $order->notes_general }}</div>
        @endif

        {{-- ===== AGREGAR CORRIDA ===== --}}
        @if ($canEdit)
            <div class="border rounded-3 p-3 bg-body-tertiary">
                <h3 class="h6 mb-2">{{ __('Agregar corrida') }}</h3>
                <form method="POST" action="{{ route('transport.order.run.store', $order) }}" class="row g-2" id="ccAddRun">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label small mb-0">{{ __('Tipo') }}</label>
                        <select name="run_type" class="form-select form-select-sm" id="ccRunType">
                            <option value="normal">{{ __('Normal') }}</option>
                            <option value="aeropuerto">{{ __('Aeropuerto') }}</option>
                            <option value="aplicacion">{{ __('Transporte de aplicación') }}</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-0">{{ __('Vehículo') }} <span class="text-muted" id="ccVehHint"></span></label>
                        <select name="vehicle_id" class="form-select form-select-sm js-typeahead" id="ccVehSel">
                            <option value="">{{ __('Sin vehículo…') }}</option>
                            @foreach ($vehicles as $v)
                                <option value="{{ $v['id'] }}" data-driver="{{ $v['driver_id'] }}">{{ $v['label'] }}@if($v['plate']) — {{ $v['plate'] }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-0">{{ __('Conductor') }}</label>
                        <select name="driver_user_id" class="form-select form-select-sm js-typeahead" id="ccDriverSel">
                            <option value="">{{ __('Conductor…') }}</option>
                            @foreach ($crew as $c)
                                <option value="{{ $c['user_id'] }}">{{ $c['name'] }}@if($c['cargo']) — {{ $c['cargo'] }}@endif</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small mb-0">{{ __('Pick up (hora)') }}</label>
                        <input type="text" name="pickup_literal" class="form-control form-control-sm" placeholder="06:30" maxlength="16">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-0">{{ __('Lugar de pick up') }}</label>
                        <select name="pickup_ref" class="form-select form-select-sm cc-ref" data-text="#ccPickupText">
                            <option value="">{{ __('—') }}</option>
                            <optgroup label="{{ __('Lugares del llamado') }}">
                                @foreach ($callPlaces as $pl)<option value="call:{{ $pl->id }}">{{ $pl->name }}</option>@endforeach
                            </optgroup>
                            <optgroup label="{{ __('Direcciones privadas') }}">
                                @foreach ($privateAddresses as $ad)<option value="private:{{ $ad->id }}">{{ $ad->label }}</option>@endforeach
                            </optgroup>
                            <option value="text">{{ __('Otro (escribir)…') }}</option>
                        </select>
                        <input type="text" name="pickup_place_text" id="ccPickupText" class="form-control form-control-sm mt-1 d-none" placeholder="{{ __('Escribe el lugar') }}" maxlength="255">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-0">{{ __('Destino') }}</label>
                        <select name="dest_ref" class="form-select form-select-sm cc-ref" data-text="#ccDestText">
                            <option value="">{{ __('—') }}</option>
                            <optgroup label="{{ __('Lugares del llamado') }}">
                                @foreach ($callPlaces as $pl)<option value="call:{{ $pl->id }}">{{ $pl->name }}</option>@endforeach
                            </optgroup>
                            <optgroup label="{{ __('Direcciones privadas') }}">
                                @foreach ($privateAddresses as $ad)<option value="private:{{ $ad->id }}">{{ $ad->label }}</option>@endforeach
                            </optgroup>
                            <option value="text">{{ __('Otro (escribir)…') }}</option>
                        </select>
                        <input type="text" name="dest_text" id="ccDestText" class="form-control form-control-sm mt-1 d-none" placeholder="{{ __('Escribe el destino') }}" maxlength="255">
                    </div>

                    <div class="col-12">
                        <label class="form-label small mb-0">{{ __('Equipamiento') }}</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($equipment as $eq)
                                <label class="border rounded px-2 py-1 small d-inline-flex align-items-center gap-1" style="cursor:pointer">
                                    <input type="checkbox" name="equipment[]" value="{{ $eq->code }}" class="form-check-input mt-0">
                                    @include('componentes._transport-equip-icon', ['icon' => $eq->icon])
                                    {{ $eq->name_es }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small mb-0">{{ __('Notas') }}</label>
                        <input type="text" name="notes" class="form-control form-control-sm" maxlength="255">
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary btn-sm">@include('componentes._icon', ['name' => 'file-plus', 'label' => null]) {{ __('Agregar corrida') }}</button>
                    </div>
                </form>
            </div>
        @elseif ($order->isFrozen())
            <p class="text-muted small">{{ __('Orden congelada: sólo lectura.') }}</p>
        @endif

        {{-- Padrón ligero (cast/agencia/cliente) para sugerir la 2ª vez (§2). --}}
        <datalist id="ccPartyList">
            @foreach ($parties as $kind => $list)
                @foreach ($list as $party)<option value="{{ $party->name }}">@endforeach
            @endforeach
        </datalist>

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@push('scripts')
    @include('componentes._typeahead')
<script>
(function () {
    // Vehículo → propone conductor (§2: la relación es bidireccional).
    var veh = document.getElementById('ccVehSel'),
        drv = document.getElementById('ccDriverSel'),
        rt  = document.getElementById('ccRunType'),
        hint = document.getElementById('ccVehHint');
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

    // Lugar = "Otro (escribir)" → muestra el texto libre.
    document.querySelectorAll('.cc-ref').forEach(function (sel) {
        var target = document.querySelector(sel.getAttribute('data-text'));
        var sync = function () { if (target) target.classList.toggle('d-none', sel.value !== 'text'); };
        sel.addEventListener('change', sync); sync();
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
