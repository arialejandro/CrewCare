{{-- Campos compartidos del alta/edición de vehículo. Recibe $types, $crew, $payees, $vehicle (nullable),
     $attrs (atributos resueltos, opcional). EL TIPO PROPONE el perfil; los atributos se ajustan por unidad. --}}
@php
    $v = $vehicle ?? null;
    $attrs = isset($attrs) && is_array($attrs) ? $attrs : ($v ? $v->resolvedAttributes() : \App\Support\VehicleChecklist::normalizeAttributes([]));
    $get = function ($k, $d = null) use ($v) { return old($k, $v ? ($v->{$k} ?? $d) : $d); };
    $ak  = function ($k, $d = null) use ($attrs) { return old($k, $attrs[$k] ?? $d); };
    $ownerKind = $get('owner_kind', 'provider');
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('Tipo de vehículo') }}</label>
        <select name="vehicle_type_id" class="form-select js-typeahead" data-ta-placeholder="{{ __('Elige un tipo…') }}">
            <option value="">{{ __('— Sin tipo —') }}</option>
            @foreach ($types as $t)
                <option value="{{ $t->id }}" @selected((string) $get('vehicle_type_id') === (string) $t->id)>{{ $t->name_es }}</option>
            @endforeach
        </select>
        <div class="form-text">{{ __('El tipo propone el perfil de atributos; puedes ajustarlo abajo.') }}</div>
    </div>
    <div class="col-md-3"><label class="form-label">{{ __('Marca') }}</label><input type="text" name="make" value="{{ $get('make') }}" class="form-control" maxlength="120"></div>
    <div class="col-md-3"><label class="form-label">{{ __('Modelo') }}</label><input type="text" name="model" value="{{ $get('model') }}" class="form-control" maxlength="120"></div>

    <div class="col-md-2"><label class="form-label">{{ __('Año') }}</label><input type="number" name="year" value="{{ $get('year') }}" class="form-control" min="1900" max="2100"></div>
    <div class="col-md-2"><label class="form-label">{{ __('Color') }}</label><input type="text" name="color" value="{{ $get('color') }}" class="form-control" maxlength="60"></div>
    <div class="col-md-3"><label class="form-label">{{ __('Placas') }}</label><input type="text" name="plate" value="{{ $get('plate') }}" class="form-control" maxlength="40"></div>
    <div class="col-md-5"><label class="form-label">{{ __('VIN') }}</label><input type="text" name="vin" value="{{ $get('vin') }}" class="form-control" maxlength="60"></div>
</div>

<hr class="my-4">
<h6 class="mb-3">{{ __('Propietario y conductor') }}</h6>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">{{ __('Tipo de propietario') }}</label>
        <select name="owner_kind" class="form-select">
            <option value="provider" @selected($ownerKind === 'provider')>{{ __('Proveedor (padrón de pago)') }}</option>
            <option value="person" @selected($ownerKind === 'person')>{{ __('Persona (crew)') }}</option>
            <option value="other" @selected($ownerKind === 'other')>{{ __('Particular (nombre suelto)') }}</option>
        </select>
    </div>
    <div class="col-md-8">
        <label class="form-label">{{ __('Propietario') }}</label>
        <div class="row g-2">
            <div class="col-md-6">
                <select name="owner_payee_id" class="form-select js-typeahead" data-ta-placeholder="{{ __('Proveedor…') }}">
                    <option value="">{{ __('— Proveedor —') }}</option>
                    @foreach ($payees as $p)
                        <option value="{{ $p->id }}" @selected((string) $get('owner_payee_id') === (string) $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <select name="owner_user_id" class="form-select js-typeahead" data-ta-placeholder="{{ __('Persona…') }}">
                    <option value="">{{ __('— Persona (crew) —') }}</option>
                    @foreach ($crew as $u)
                        <option value="{{ $u->id }}" @selected((string) $get('owner_user_id') === (string) $u->id)>{{ \App\Models\User::displayName($u) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <input type="text" name="owner_name" value="{{ $get('owner_name') }}" class="form-control" maxlength="255" placeholder="{{ __('Nombre del particular (si no está en el sistema)') }}">
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('Conductor asignado (crew)') }}</label>
        <select name="driver_user_id" class="form-select js-typeahead" data-ta-placeholder="{{ __('Conductor…') }}">
            <option value="">{{ __('— Sin asignar —') }}</option>
            @foreach ($crew as $u)
                <option value="{{ $u->id }}" @selected((string) $get('driver_user_id') === (string) $u->id)>{{ \App\Models\User::displayName($u) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">{{ __('Kilometraje inicial') }}</label><input type="number" name="initial_km" value="{{ $get('initial_km') }}" class="form-control" min="0"></div>
</div>

<hr class="my-4">
<h6 class="mb-1">{{ __('Atributos (encienden los módulos del checklist)') }}</h6>
<p class="text-muted small mb-3">{{ __('Cargados desde el tipo; ajústalos si esta unidad difiere (un camión trae planta y otro no).') }}</p>
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">{{ __('Powertrain') }}</label>
        <select name="powertrain" class="form-select">
            <option value="combustion" @selected($ak('powertrain') === 'combustion')>{{ __('Combustión') }}</option>
            <option value="electric" @selected($ak('powertrain') === 'electric')>{{ __('Eléctrico') }}</option>
            <option value="hybrid" @selected($ak('powertrain') === 'hybrid')>{{ __('Híbrido') }}</option>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">{{ __('Plazas') }}</label><input type="number" name="seats" value="{{ $ak('seats') }}" class="form-control" min="0" max="120"></div>
    <div class="col-md-3"><label class="form-label">{{ __('Tanque de agua (L)') }}</label><input type="number" name="water_tank_liters" value="{{ $ak('water_tank_liters') }}" class="form-control" min="0"></div>
    <div class="col-md-3 d-flex flex-column justify-content-end gap-2">
        <div class="form-check"><input type="hidden" name="has_cargo_box" value="0"><input class="form-check-input" type="checkbox" name="has_cargo_box" value="1" id="hcb" @checked($ak('has_cargo_box'))><label class="form-check-label" for="hcb">{{ __('Caja / plataforma / rampa') }}</label></div>
        <div class="form-check"><input type="hidden" name="has_lpg_or_sanitary" value="0"><input class="form-check-input" type="checkbox" name="has_lpg_or_sanitary" value="1" id="hls" @checked($ak('has_lpg_or_sanitary'))><label class="form-check-label" for="hls">{{ __('Gas LP / sanitario') }}</label></div>
        <div class="form-check"><input type="hidden" name="has_genset_or_heat_appliances" value="0"><input class="form-check-input" type="checkbox" name="has_genset_or_heat_appliances" value="1" id="hgh" @checked($ak('has_genset_or_heat_appliances'))><label class="form-check-label" for="hgh">{{ __('Energía / aparatos de calor') }}</label></div>
        <div class="form-check"><input type="hidden" name="tows" value="0"><input class="form-check-input" type="checkbox" name="tows" value="1" id="tows" @checked($ak('tows'))><label class="form-check-label" for="tows">{{ __('Remolca / remolcado') }}</label></div>
    </div>
</div>

<div class="mt-3">
    <label class="form-label">{{ __('Notas') }}</label>
    <textarea name="notes" class="form-control" rows="2" maxlength="2000">{{ $get('notes') }}</textarea>
</div>

@once
    @push('scripts')
        @include('componentes._typeahead')
    @endpush
@endonce
