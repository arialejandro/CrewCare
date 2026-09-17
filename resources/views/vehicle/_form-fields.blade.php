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

    {{-- Conductor: ACOTADO al depto de Transportación con puesto de chofer (regla del owner: si no
         pertenece, no se ve). Default = sólo LIBRES; el toggle revela a los ya asignados, con su unidad.
         FILTRO, no candado: reasignar es deliberado y libera la unidad anterior (avisa el controlador). --}}
    <div class="col-md-6">
        <label class="form-label">{{ __('Conductor asignado (chofer de transpo)') }}</label>
        @php $drivers = $drivers ?? []; $curDriver = (string) $get('driver_user_id'); @endphp
        <select name="driver_user_id" class="form-select cc-driver-select">
            <option value="">{{ __('— Sin asignar —') }}</option>
            @foreach ($drivers as $d)
                @php $isCur = $curDriver === (string) $d['id']; $busy = $d['other_vehicle'] && ! $isCur; @endphp
                <option value="{{ $d['id'] }}" @selected($isCur) @if($busy) data-busy="1" hidden @endif>{{ $d['name'] }}@if($busy) — {{ __('en') }} {{ $d['other_vehicle'] }}@endif</option>
            @endforeach
        </select>
        <label class="form-text d-inline-flex align-items-center gap-1 mt-1" style="cursor:pointer">
            <input type="checkbox" class="cc-driver-show-busy"> {{ __('Mostrar conductores ya asignados a otra unidad') }}
        </label>
        @if (empty($drivers))<div class="form-text text-warning">{{ __('Aún no hay choferes en el departamento de Transportación.') }}</div>@endif
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
        <div class="form-check"><input type="hidden" name="tows" value="0"><input class="form-check-input" type="checkbox" name="tows" value="1" id="tows" @checked($ak('tows'))><label class="form-check-label" for="tows">{{ __('Jala remolque (tractor/pickup)') }}</label></div>
        <div class="form-check"><input type="hidden" name="is_towed" value="0"><input class="form-check-input" type="checkbox" name="is_towed" value="1" id="is_towed" @checked($ak('is_towed'))><label class="form-check-label" for="is_towed">{{ __('Es remolcada (no se conduce)') }}</label></div>
    </div>
</div>

<div class="mt-3">
    <label class="form-label">{{ __('Notas') }}</label>
    <textarea name="notes" class="form-control" rows="2" maxlength="2000">{{ $get('notes') }}</textarea>
</div>

@once
    @push('scripts')
        @include('componentes._typeahead')
        <script>
        // Driver: toggle "mostrar asignados" revela las opciones ocupadas (data-busy), con su unidad.
        document.querySelectorAll('.cc-driver-select').forEach(function (sel) {
            var wrap = sel.closest('.col-md-6') || sel.parentNode;
            var cb = wrap.querySelector('.cc-driver-show-busy');
            if (!cb) { return; }
            cb.addEventListener('change', function () {
                sel.querySelectorAll('option[data-busy="1"]').forEach(function (o) { o.hidden = !cb.checked; });
            });
        });
        </script>
    @endpush
@endonce
