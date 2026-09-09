{{--
    Bloque reutilizable de geolocalización. La lógica vive en
    public/js/crewcare-geo.js (auto-init por data-attributes).

    MODOS ('mode'):

    'silent'  — Reportes de seguridad (Cond./Acc. Inseguras, Accidentes).
                CERO interfaz: inputs hidden (latitude/longitude/gps_address).
                Al abrir el form detecta la posición por detrás y, si hay un
                scouting a ≤500 m, sugiere su NOMBRE en el campo Locación.
                Params: 'suggestTarget' (selector del campo Locación),
                        'noteTarget'    (selector del small donde explicar la sugerencia).

    'address' — Formulario de Scouting. Renderiza el campo Dirección completo:
                input + botón discreto 📍 (mi ubicación) + status. Lat/lng viven
                en hidden. Escribir la dirección a mano también geocodifica y
                guarda coordenadas por detrás (al salir del campo o Enter).
                Params: 'addressName' (default 'location_address'),
                        'addressValue', 'label' (default 'Dirección'),
                        'required' (bool).

    'full'    — (default, retrocompatible) Bloque original con lat/lng visibles
                y botón "Obtener mi ubicación". Params: latName/lngName/latValue/
                lngValue/addressTarget/title/auto/suggestTarget.
--}}
@php
    $mode     = $mode ?? 'full';
    $latName  = $latName ?? 'latitude';
    $lngName  = $lngName ?? 'longitude';
    $latValue = $latValue ?? old($latName);
    $lngValue = $lngValue ?? old($lngName);
@endphp

@if($mode === 'silent')
    @php
        $suggestTarget = $suggestTarget ?? '';
        $noteTarget    = $noteTarget ?? '';
        $gpsAddressValue = $gpsAddressValue ?? old('gps_address');
    @endphp
    <div data-geo-silent
         @if($suggestTarget) data-suggest-target="{{ $suggestTarget }}" @endif
         @if($noteTarget) data-note-target="{{ $noteTarget }}" @endif>
        <input type="hidden" name="{{ $latName }}" value="{{ $latValue }}">
        <input type="hidden" name="{{ $lngName }}" value="{{ $lngValue }}">
        <input type="hidden" name="gps_address" value="{{ $gpsAddressValue }}">
    </div>

@elseif($mode === 'address')
    @php
        $addressName  = $addressName ?? 'location_address';
        $addressValue = $addressValue ?? old($addressName);
        $label        = $label ?? 'Dirección';
        $required     = $required ?? false;
        $auto         = $auto ?? false;
    @endphp
    <div data-geo-address @if($auto) data-geo-auto @endif>
        <label class="form-label fw-semibold" for="{{ $addressName }}">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>
        <div class="input-group">
            <input type="text" name="{{ $addressName }}" id="{{ $addressName }}"
                   class="form-control js-geo-addr" value="{{ $addressValue }}"
                   placeholder="Calle, número, colonia, ciudad…" @if($required) required @endif>
            <button type="button" class="btn btn-outline-secondary js-geo-locate"
                    title="{{ __('Usar mi ubicación actual') }}" aria-label="{{ __('Usar mi ubicación actual') }}">@include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico'])</button>
        </div>
        <small class="js-geo-status small text-muted d-block mt-1">
            Escribe la dirección (las coordenadas se guardan solas) o toca 📍 para usar tu ubicación.
        </small>
        <input type="hidden" name="{{ $latName }}" value="{{ $latValue }}">
        <input type="hidden" name="{{ $lngName }}" value="{{ $lngValue }}">
    </div>

@else
    @php
        $addressTarget = $addressTarget ?? '';
        $title         = $title ?? 'Ubicación GPS (opcional)';
        $auto          = $auto ?? false;
        $suggestTarget = $suggestTarget ?? '';
    @endphp
    <div class="border rounded p-3 bg-light" data-geo-capture
         @if($addressTarget) data-address-target="{{ $addressTarget }}" @endif
         @if($auto) data-geo-auto @endif
         @if($suggestTarget) data-suggest-target="{{ $suggestTarget }}" @endif>
        <label class="form-label fw-semibold mb-2">{{ $title }}</label>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Latitud</label>
                <input type="number" step="any" min="-90" max="90" name="{{ $latName }}"
                       class="form-control js-geo-lat" placeholder="Ej. 19.4326000"
                       value="{{ $latValue }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Longitud</label>
                <input type="number" step="any" min="-180" max="180" name="{{ $lngName }}"
                       class="form-control js-geo-lng" placeholder="Ej. -99.1332000"
                       value="{{ $lngValue }}">
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-outline-dark w-100 js-geo-btn">
                    @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico me-1']) {{ __('Obtener mi ubicación') }}
                </button>
            </div>
        </div>
        <div class="mt-2">
            <small class="js-geo-help text-muted d-block">
                Usa el botón para capturar tu ubicación y llenar la dirección automáticamente, o escribe todo a mano.
            </small>
            {{-- Sin d-inline-block: su !important le ganaría al display:none del JS --}}
            <a class="js-geo-maps small mt-1" href="#" target="_blank" rel="noopener"
               style="display:none;">Ver en Google Maps</a>
        </div>
    </div>
@endif

@once
    @push('scripts')
        <script src="{{ asset('js/crewcare-geo.js') }}?v=5"></script>
    @endpush
@endonce
