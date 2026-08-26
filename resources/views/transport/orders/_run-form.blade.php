{{-- Formulario de corrida (Bloque 2 §2), reutilizable para AGREGAR y EDITAR EN EL LUGAR.
     Espera del scope padre: $vehicles, $crew, $callPlaces, $pickAddresses (privadas FILTRADAS por
     allowlist), $privById (mapa completo para enmascarar), $equipment.
     Recibe: $action (URL), $submitLabel, y $run (opcional, para prefill; null = alta). --}}
@php
    $run = $run ?? null;
    $eqSel = collect($run->equipment ?? []);
    // Fallbacks defensivos (sólo se incluye desde el editor, que sí los pasa).
    $pickAddresses = $pickAddresses ?? ($privateAddresses ?? collect());
    $privById = $privById ?? collect();
    $pickupRef = $run && $run->pickup_place_kind
        ? ($run->pickup_place_kind === 'text' ? 'text' : $run->pickup_place_kind . ':' . $run->pickup_place_id)
        : '';
    $destRef = $run && $run->dest_place_kind
        ? ($run->dest_place_kind === 'text' ? 'text' : $run->dest_place_kind . ':' . $run->dest_place_id)
        : '';
    // Si la corrida ya apunta a una privada que ESTE capturista no puede ver, se preserva como opción
    // ENMASCARADA ('CASA · asignada'): no se pierde al guardar y no revela de quién es la casa.
    $hiddenPickup = $run && $run->pickup_place_kind === 'private'
        && ! $pickAddresses->contains(fn ($a) => (int) $a->id === (int) $run->pickup_place_id);
    $hiddenDest = $run && $run->dest_place_kind === 'private'
        && ! $pickAddresses->contains(fn ($a) => (int) $a->id === (int) $run->dest_place_id);
    $maskLabel = fn ($id) => (optional($privById->get($id))->publicLabel() ?? __('CASA')) . ' · ' . __('asignada');
@endphp
<form method="POST" action="{{ $action }}" class="row g-2 cc-run-form">
    @csrf
    <div class="col-md-3">
        <label class="form-label small mb-0">{{ __('Tipo') }}</label>
        <select name="run_type" class="form-select form-select-sm cc-runtype">
            <option value="normal" @selected($run && $run->run_type === 'normal')>{{ __('Normal') }}</option>
            <option value="aeropuerto" @selected($run && $run->run_type === 'aeropuerto')>{{ __('Aeropuerto') }}</option>
            <option value="aplicacion" @selected($run && $run->run_type === 'aplicacion')>{{ __('Transporte de aplicación') }}</option>
        </select>
    </div>
    <div class="col-md-5">
        <label class="form-label small mb-0">{{ __('Vehículo') }} <span class="text-muted cc-veh-hint"></span></label>
        <select name="vehicle_id" class="form-select form-select-sm cc-veh js-typeahead">
            <option value="">{{ __('Sin vehículo…') }}</option>
            @foreach ($vehicles as $v)
                <option value="{{ $v['id'] }}" data-driver="{{ $v['driver_id'] }}" @selected($run && (int) $run->vehicle_id === (int) $v['id'])>{{ $v['label'] }}@if($v['plate']) — {{ $v['plate'] }}@endif</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-0">{{ __('Conductor') }}</label>
        <select name="driver_user_id" class="form-select form-select-sm cc-driver js-typeahead">
            <option value="">{{ __('Conductor…') }}</option>
            @foreach ($crew as $c)
                <option value="{{ $c['user_id'] }}" @selected($run && (int) $run->driver_user_id === (int) $c['user_id'])>{{ $c['name'] }}@if($c['cargo']) — {{ $c['cargo'] }}@endif</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label small mb-0">{{ __('Pick up (hora)') }}</label>
        <input type="text" name="pickup_literal" value="{{ $run->pickup_literal ?? '' }}" class="form-control form-control-sm" placeholder="06:30" maxlength="16">
    </div>
    <div class="col-md-5">
        <label class="form-label small mb-0">{{ __('Lugar de pick up') }}</label>
        <select name="pickup_ref" class="form-select form-select-sm cc-ref" data-text=".cc-pickup-text">
            <option value="">{{ __('—') }}</option>
            <optgroup label="{{ __('Lugares del llamado') }}">
                @foreach ($callPlaces as $pl)<option value="call:{{ $pl->id }}" @selected($pickupRef === 'call:' . $pl->id)>{{ $pl->name }}</option>@endforeach
            </optgroup>
            <optgroup label="{{ __('Direcciones') }}">
                @foreach ($pickAddresses as $ad)<option value="private:{{ $ad->id }}" @selected($pickupRef === 'private:' . $ad->id)>{{ $ad->label }}</option>@endforeach
                @if ($hiddenPickup)<option value="private:{{ $run->pickup_place_id }}" selected>{{ $maskLabel($run->pickup_place_id) }}</option>@endif
            </optgroup>
            <option value="text" @selected($pickupRef === 'text')>{{ __('Otro (escribir)…') }}</option>
        </select>
        <input type="text" name="pickup_place_text" value="{{ $run->pickup_place_text ?? '' }}" class="form-control form-control-sm mt-1 cc-pickup-text {{ $pickupRef === 'text' ? '' : 'd-none' }}" placeholder="{{ __('Escribe el lugar') }}" maxlength="255">
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-0">{{ __('Destino') }}</label>
        <select name="dest_ref" class="form-select form-select-sm cc-ref" data-text=".cc-dest-text">
            <option value="">{{ __('—') }}</option>
            <optgroup label="{{ __('Lugares del llamado') }}">
                @foreach ($callPlaces as $pl)<option value="call:{{ $pl->id }}" @selected($destRef === 'call:' . $pl->id)>{{ $pl->name }}</option>@endforeach
            </optgroup>
            <optgroup label="{{ __('Direcciones') }}">
                @foreach ($pickAddresses as $ad)<option value="private:{{ $ad->id }}" @selected($destRef === 'private:' . $ad->id)>{{ $ad->label }}</option>@endforeach
                @if ($hiddenDest)<option value="private:{{ $run->dest_place_id }}" selected>{{ $maskLabel($run->dest_place_id) }}</option>@endif
            </optgroup>
            <option value="text" @selected($destRef === 'text')>{{ __('Otro (escribir)…') }}</option>
        </select>
        <input type="text" name="dest_text" value="{{ $run->dest_text ?? '' }}" class="form-control form-control-sm mt-1 cc-dest-text {{ $destRef === 'text' ? '' : 'd-none' }}" placeholder="{{ __('Escribe el destino') }}" maxlength="255">
    </div>

    <div class="col-12">
        <label class="form-label small mb-0">{{ __('Equipamiento') }}</label>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($equipment as $eq)
                <label class="border rounded px-2 py-1 small d-inline-flex align-items-center gap-1" style="cursor:pointer">
                    <input type="checkbox" name="equipment[]" value="{{ $eq->code }}" class="form-check-input mt-0" @checked($eqSel->contains($eq->code))>
                    @include('componentes._transport-equip-icon', ['icon' => $eq->icon])
                    {{ $eq->name_es }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="col-12">
        <label class="form-label small mb-0">{{ __('Notas') }}</label>
        <input type="text" name="notes" value="{{ $run->notes ?? '' }}" class="form-control form-control-sm" maxlength="255">
    </div>

    <div class="col-12">
        <button class="btn btn-primary btn-sm">{{ $submitLabel }}</button>
    </div>
</form>
