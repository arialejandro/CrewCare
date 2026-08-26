{{-- Formulario de TIPO de vehículo (§5 Capa 4). Reutilizable alta/edición.
     Recibe: $action, $submit, $type (nullable). El perfil PROPONE al crear un vehículo; editarlo
     NO cambia los vehículos ya dados de alta. --}}
@php
    $t = $type ?? null;
    $p = $t ? $t->profile() : \App\Support\VehicleChecklist::normalizeAttributes([]);
    $g = function ($k, $d = null) use ($t) { return old($k, $t ? ($t->{$k} ?? $d) : $d); };
    $ak = function ($k, $d = null) use ($p) { return old($k, $p[$k] ?? $d); };
    $scope = $t ? ('t' . $t->id) : 'new';
@endphp
<form method="POST" action="{{ $action }}" class="row g-2">
    @csrf
    <div class="col-md-3">
        <label class="form-label small mb-0">{{ __('Código') }}</label>
        <input type="text" name="code" value="{{ $g('code') }}" class="form-control form-control-sm font-monospace" maxlength="40" placeholder="camper_vestuario" @if($t) readonly @endif>
        @if($t)<div class="form-text small">{{ __('El código no se cambia (lo referencian catálogo y actas).') }}</div>@endif
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-0">{{ __('Nombre (ES)') }}</label>
        <input type="text" name="name_es" value="{{ $g('name_es') }}" class="form-control form-control-sm" maxlength="120">
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-0">{{ __('Nombre (EN)') }}</label>
        <input type="text" name="name_en" value="{{ $g('name_en') }}" class="form-control form-control-sm" maxlength="120">
    </div>
    <div class="col-md-2">
        <label class="form-label small mb-0">{{ __('Orden') }}</label>
        <input type="number" name="sort_order" value="{{ $g('sort_order', 0) }}" class="form-control form-control-sm">
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="is_special" value="0">
            <input class="form-check-input" type="checkbox" name="is_special" value="1" id="isp_{{ $scope }}" @checked($g('is_special'))>
            <label class="form-check-label small" for="isp_{{ $scope }}">{{ __('Especial (sin perfil; todo se declara a mano en la unidad)') }}</label>
        </div>
    </div>

    <div class="col-12"><hr class="my-1"></div>
    <div class="col-12"><div class="small text-uppercase text-muted">{{ __('Perfil de atributos propuesto') }}</div></div>

    <div class="col-md-3">
        <label class="form-label small mb-0">{{ __('Powertrain') }}</label>
        <select name="powertrain" class="form-select form-select-sm">
            <option value="combustion" @selected($ak('powertrain') === 'combustion')>{{ __('Combustión') }}</option>
            <option value="electric" @selected($ak('powertrain') === 'electric')>{{ __('Eléctrico') }}</option>
            <option value="hybrid" @selected($ak('powertrain') === 'hybrid')>{{ __('Híbrido') }}</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small mb-0">{{ __('Plazas') }}</label>
        <input type="number" name="seats" value="{{ $ak('seats') }}" class="form-control form-control-sm" min="0" max="200">
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-0">{{ __('Tanque de agua (L)') }}</label>
        <input type="number" name="water_tank_liters" value="{{ $ak('water_tank_liters') }}" class="form-control form-control-sm" min="0">
    </div>
    <div class="col-md-4 d-flex flex-column justify-content-end gap-1">
        <div class="form-check"><input type="hidden" name="has_cargo_box" value="0"><input class="form-check-input" type="checkbox" name="has_cargo_box" value="1" id="hcb_{{ $scope }}" @checked($ak('has_cargo_box'))><label class="form-check-label small" for="hcb_{{ $scope }}">{{ __('Caja / plataforma / rampa') }}</label></div>
        <div class="form-check"><input type="hidden" name="has_lpg_or_sanitary" value="0"><input class="form-check-input" type="checkbox" name="has_lpg_or_sanitary" value="1" id="hls_{{ $scope }}" @checked($ak('has_lpg_or_sanitary'))><label class="form-check-label small" for="hls_{{ $scope }}">{{ __('Gas LP / sanitario') }}</label></div>
        <div class="form-check"><input type="hidden" name="has_genset_or_heat_appliances" value="0"><input class="form-check-input" type="checkbox" name="has_genset_or_heat_appliances" value="1" id="hgh_{{ $scope }}" @checked($ak('has_genset_or_heat_appliances'))><label class="form-check-label small" for="hgh_{{ $scope }}">{{ __('Energía / aparatos de calor') }}</label></div>
        <div class="form-check"><input type="hidden" name="tows" value="0"><input class="form-check-input" type="checkbox" name="tows" value="1" id="tw_{{ $scope }}" @checked($ak('tows'))><label class="form-check-label small" for="tw_{{ $scope }}">{{ __('Jala remolque') }}</label></div>
        <div class="form-check"><input type="hidden" name="is_towed" value="0"><input class="form-check-input" type="checkbox" name="is_towed" value="1" id="itw_{{ $scope }}" @checked($ak('is_towed'))><label class="form-check-label small" for="itw_{{ $scope }}">{{ __('Es remolcada (no se conduce)') }}</label></div>
    </div>

    <div class="col-12">
        <label class="form-label small mb-0">{{ __('Notas') }}</label>
        <textarea name="notes" class="form-control form-control-sm" rows="1" maxlength="2000">{{ $g('notes') }}</textarea>
    </div>

    <div class="col-12">
        <button class="btn btn-primary btn-sm">{{ $submit }}</button>
    </div>
</form>
