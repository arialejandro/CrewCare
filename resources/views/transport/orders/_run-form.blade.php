{{-- Formulario de corrida (Fase 2), reutilizable para AGREGAR y EDITAR EN EL LUGAR.
     DOS EJES: run_class (set|fuera) y run_type. SET deriva el pick up (origen del catálogo + destino
     del scouting + traslado ± ajuste); FUERA va a mano (hora + inicio + fin).
     Espera del scope padre: $vehicles, $crew, $pickAddresses, $privById, $pickupPoints, $dayLocations,
     $equipment. Recibe: $action, $submitLabel, $run (nullable). --}}
@php
    $run = $run ?? null;
    $eqSel = collect($run->equipment ?? []);
    $pickAddresses = $pickAddresses ?? ($privateAddresses ?? collect());
    $privById = $privById ?? collect();
    $pickupPoints = $pickupPoints ?? collect();
    $dayLocations = $dayLocations ?? collect();
    $runClass = $run ? $run->run_class : 'fuera';
    $pickupRef = $run && $run->pickup_place_kind
        ? ($run->pickup_place_kind === 'text' ? 'text' : $run->pickup_place_kind . ':' . $run->pickup_place_id)
        : '';
    $destRef = $run && $run->dest_place_kind
        ? ($run->dest_place_kind === 'text' ? 'text' : $run->dest_place_kind . ':' . $run->dest_place_id)
        : '';
    $hiddenPickup = $run && $run->pickup_place_kind === 'private'
        && ! $pickAddresses->contains(fn ($a) => (int) $a->id === (int) $run->pickup_place_id);
    $hiddenDest = $run && $run->dest_place_kind === 'private'
        && ! $pickAddresses->contains(fn ($a) => (int) $a->id === (int) $run->dest_place_id);
    $maskLabel = fn ($id) => (optional($privById->get($id))->publicLabel() ?? __('CASA')) . ' · ' . __('asignada');
@endphp
<form method="POST" action="{{ $action }}" class="row g-2 cc-run-form">
    @csrf
    <div class="col-md-3">
        <label class="form-label small mb-0">{{ __('Clase') }}</label>
        <select name="run_class" class="form-select form-select-sm cc-runclass">
            <option value="set" @selected($runClass === 'set')>{{ __('De set (deriva)') }}</option>
            <option value="fuera" @selected(! in_array($runClass, ['set', 'evento'], true))>{{ __('Fuera de llamado') }}</option>
            <option value="evento" @selected($runClass === 'evento')>{{ __('Evento de vehículo') }}</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-0">{{ __('Tipo') }}</label>
        <select name="run_type" class="form-select form-select-sm cc-runtype">
            <option value="normal" @selected($run && $run->run_type === 'normal')>{{ __('Normal') }}</option>
            <option value="aeropuerto" @selected($run && $run->run_type === 'aeropuerto')>{{ __('Aeropuerto') }}</option>
            <option value="aplicacion" @selected($run && $run->run_type === 'aplicacion')>{{ __('Transporte de aplicación') }}</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small mb-0">{{ __('Vehículo') }} <span class="text-muted cc-veh-hint"></span></label>
        {{-- Alta INLINE del day player: se busca por PLACA (llave natural, dedup fuerte en el server);
             si no hay coincidencia, "Crear" es un paso deliberado con confirmación. Reusa el patrón de
             crear del typeahead. Gateado por canFull (esta pieza sólo se rinde a quien puede editar). --}}
        <select name="vehicle_id" class="form-select form-select-sm cc-veh js-typeahead"
                data-ta-extra="1" data-ta-create="1" data-ta-create-url="{{ route('transport.order.vehicle.quick') }}"
                data-ta-create-key="plate" data-ta-create-noun="{{ __('vehículo') }}">
            <option value="">{{ __('Sin vehículo…') }}</option>
            @foreach ($vehicles as $v)
                <option value="{{ $v['id'] }}" data-driver="{{ $v['driver_id'] }}" data-search="{{ $v['plate'] }}" @selected($run && (int) $run->vehicle_id === (int) $v['id'])>{{ $v['label'] }}@if($v['plate']) — {{ $v['plate'] }}@endif</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small mb-0">{{ __('Conductor') }}</label>
        <select name="driver_user_id" class="form-select form-select-sm cc-driver js-typeahead">
            <option value="">{{ __('Conductor…') }}</option>
            @foreach ($crew as $c)
                <option value="{{ $c['user_id'] }}" @selected($run && (int) $run->driver_user_id === (int) $c['user_id'])>{{ $c['name'] }}@if($c['cargo']) — {{ $c['cargo'] }}@endif</option>
            @endforeach
        </select>
    </div>

    {{-- ===== SET (derivado) ===== --}}
    <div class="col-12 cc-set-block">
        <div class="row g-2 border rounded p-2 bg-body-tertiary">
            <div class="col-12"><span class="small text-uppercase text-muted">{{ __('Pick up derivado (llamado más temprano − traslado ± ajuste)') }}</span></div>
            <div class="col-md-4">
                <label class="form-label small mb-0">{{ __('Punto de origen') }}</label>
                <select name="pickup_point_id" class="form-select form-select-sm js-typeahead">
                    <option value="">{{ __('Punto…') }}</option>
                    @foreach ($pickupPoints as $pt)<option value="{{ $pt->id }}" @selected($run && (int) $run->pickup_point_id === (int) $pt->id)>{{ $pt->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-0">{{ __('Locación del día (destino)') }}</label>
                <select name="dest_location_ref" class="form-select form-select-sm js-typeahead">
                    <option value="">{{ __('Locación…') }}</option>
                    @foreach ($dayLocations as $loc)<option value="{{ $loc->id }}" @selected($run && (int) $run->dest_location_ref === (int) $loc->id)>{{ $loc->location_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">{{ __('Traslado (min)') }}</label>
                <input type="number" name="travel_minutes" value="{{ $run->travel_minutes ?? '' }}" class="form-control form-control-sm" min="0" max="1440" placeholder="{{ __('matriz') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">{{ __('Ajuste ± (min)') }}</label>
                <input type="number" name="travel_adjust_minutes" value="{{ $run->travel_adjust_minutes ?? 0 }}" class="form-control form-control-sm" min="-600" max="600">
            </div>
            <div class="col-12"><span class="small text-muted">{{ __('El traslado sale de la matriz por el par punto × locación; escribe uno sólo si ese par aún no está.') }}</span></div>
        </div>
    </div>

    {{-- ===== FUERA (a mano) ===== --}}
    <div class="col-12 cc-fuera-block">
        <div class="row g-2 border rounded p-2">
            <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Pick up (hora)') }}</label>
                <input type="text" name="pickup_literal" value="{{ $run->pickup_literal ?? '' }}" class="form-control form-control-sm" placeholder="06:30" maxlength="16">
            </div>
            <div class="col-md-5">
                <label class="form-label small mb-0">{{ __('Lugar de inicio') }}</label>
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
                <label class="form-label small mb-0">{{ __('Lugar de finalización') }}</label>
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
        </div>
    </div>

    {{-- ===== EVENTO de vehículo (escotilla) ===== --}}
    <div class="col-12 cc-evento-block">
        <div class="row g-2 border rounded p-2 bg-body-tertiary">
            <div class="col-12"><span class="small text-uppercase text-muted">{{ __('Evento de vehículo — ocupa su agenda, sin ocupantes') }}</span></div>
            <div class="col-md-6">
                <label class="form-label small mb-0">{{ __('Descripción') }}</label>
                <input type="text" name="event_desc" value="{{ $run && $run->isEvento() ? $run->dest_text : '' }}" class="form-control form-control-sm" placeholder="{{ __('Mudanza de utilería, mantenimiento…') }}" maxlength="255">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Inicio') }}</label>
                <input type="text" name="event_start" value="{{ $run && $run->isEvento() ? $run->pickup_literal : '' }}" class="form-control form-control-sm" placeholder="07:00" maxlength="16">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Fin') }}</label>
                <input type="text" name="event_end" value="{{ $run && $run->isEvento() ? $run->end_literal : '' }}" class="form-control form-control-sm" placeholder="12:00" maxlength="16">
            </div>
            <div class="col-12"><span class="small text-muted">{{ __('El vehículo es obligatorio: el evento reserva su unidad en esa ventana.') }}</span></div>
        </div>
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
