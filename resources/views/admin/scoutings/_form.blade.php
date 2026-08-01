{{-- Parcial COMPARTIDO del formulario de Scouting H&S (crear + editar).
     Funciona en dos modos según la variable $report:
       - $report = null   → CREAR  (action scoutings.store, POST)
       - $report = modelo → EDITAR (action scoutings.update, PUT)
     Cada campo usa old('campo', valor_guardado): así un error de validación
     no pierde lo tecleado, y en edición se precarga lo ya guardado. --}}
@php
    $report = $report ?? null;
    $isEdit = !is_null($report);

    // TABLA DE PELIGROS (estilo Amazon MGM). risk_assessment es una LISTA de filas.
    // Se arman $hazardRows con esta prioridad:
    //   1) old() tras un error de validación (no perder lo tecleado);
    //   2) reporte guardado en EDICIÓN (tolerando el esquema viejo
    //      label/answer/risk(Bajo/Medio/Alto)/note → hazard/rating/control);
    //   3) al CREAR: se siembran las 13 categorías como filas iniciales sugeridas.
    // La casilla "actividades especiales" (SB132) se maneja aparte, NO como fila.
    $oldRiskMap = ['Bajo' => 'L', 'Medio' => 'M', 'Alto' => 'H', 'Extremo' => 'E'];
    $hazardRows = [];

    if (is_array(old('hz_hazard'))) {
        foreach (old('hz_hazard') as $i => $hz) {
            $hazardRows[] = [
                // (2026-07-22) event_id se repone en AMBAS ramas (old + edición). Sin él,
                // _hazard-row.blade.php no preselecciona el <select> del evento → el POST manda
                // hz_event_id vacío → buildHazards() no resuelve evento → se pierden evento,
                // categoría y normas, y syncStandards([]) desvincula el N:M. Aquí (old) evita
                // que un simple error de validación borre la selección sin haber guardado.
                'event_id'      => old('hz_event_id.' . $i),
                'key'           => old('hz_key.' . $i),
                'hazard'        => $hz,
                'likelihood'    => old('hz_likelihood.' . $i),
                'consequence'   => old('hz_consequence.' . $i),
                'rating'        => null,
                'control'       => old('hz_control.' . $i),
                'residual'      => old('hz_residual.' . $i),
                'personnel'     => old('hz_personnel.' . $i),
                'category_name' => old('hz_category_name.' . $i, ''),
            ];
        }
    } elseif ($isEdit && is_array($report->risk_assessment)) {
        foreach ($report->risk_assessment as $h) {
            if (($h['key'] ?? null) === 'special') {
                continue; // el viejo renglón "especial" ahora es la casilla SB132
            }
            $hazardRows[] = [
                // (2026-07-22) event_id: sin esto, editar un scouting y guardar SIN tocar nada
                // evaporaba el evento del catálogo y sus normas N:M (bomba armada). Las filas
                // legadas (#1/#6, anteriores al 2026-07-13) no traen la clave → null → el select
                // queda vacío, que es lo correcto: nunca tuvieron evento.
                'event_id'    => $h['event_id'] ?? null,
                'key'         => $h['key'] ?? null,
                'hazard'      => $h['hazard'] ?? ($h['label'] ?? ''),
                'likelihood'  => $h['likelihood'] ?? null,
                'consequence' => $h['consequence'] ?? null,
                'rating'      => $h['rating'] ?? ($oldRiskMap[$h['risk'] ?? ''] ?? null),
                'control'     => $h['control'] ?? ($h['note'] ?? null),
                'residual'    => $h['residual'] ?? null,
                'personnel'   => $h['personnel'] ?? null,
                'badge'       => $h['badge'] ?? null,
                'code'        => $h['code'] ?? null,
            ];
        }
    }

    // (2026-08-01 · captura fluida, Paso 4) La tabla ARRANCA VACÍA. Ya NO se siembran las
    // ~37 categorías como filas: el safety no piensa por categoría sino por ACTIVIDAD, y
    // 37 filas de 7 campos son el peso real. Una fila existe cuando se AGREGA ese peligro
    // —desde el selector por actividad (que trae control/EPP/norma pre-propuestos) o a
    // mano—. En edición y al rebotar validación, las filas guardadas se conservan (ramas
    // de arriba). $categories sigue disponible para el resto del formulario (casilla SB132).

    // Precargar el select de norma por fila: si viene por badge+code (snapshot),
    // se re-resuelve el category_name contra el catálogo.
    foreach ($hazardRows as $i => $hr) {
        if (!isset($hazardRows[$i]['category_name'])) {
            $hazardRows[$i]['category_name'] = '';
            if (!empty($hr['badge']) || !empty($hr['code'])) {
                foreach ($standards as $standard) {
                    if ($standard->regulation_badge === ($hr['badge'] ?? null)
                        && $standard->regulation_code === ($hr['code'] ?? null)) {
                        $hazardRows[$i]['category_name'] = $standard->category_name;
                        break;
                    }
                }
            }
        }
    }

    // Estado de la casilla SB132 (actividades especiales declaradas).
    $specialChecked = old('special_activities', ($isEdit && $report->requires_specific_ra) ? '1' : '') ? true : false;

    // (2026-07-09) Desglose SB132 (obligatorio si $specialChecked): tipo(s) de actividad,
    // número(s) de escena y personal certificado requerido. old() primero; luego lo guardado.
    $sb132 = old('sb132_details', ($isEdit && is_array($report->sb132_details ?? null)) ? $report->sb132_details : []);
    $sb132Activities = (array) ($sb132['activity_type'] ?? []);
    $sb132Scene      = $sb132['scene_number'] ?? '';
    $sb132Personnel  = $sb132['certified_personnel_required'] ?? '';

    // (2026-08-01 · captura fluida) Viabilidad y Acuerdos ARRANCAN VACÍOS y se
    // auto-agregan (botón + fila plantilla), como la tabla de peligros. Ya no se
    // pintan 4 filas fijas. Prioridad de origen: old() (preserva TODAS las filas al
    // rebotar validación) → reporte en edición → vacío al crear.
    if (is_array(old('viab_area'))) {
        $viabRows = [];
        foreach (old('viab_area') as $i => $va) {
            $viabRows[] = [
                'area'        => $va,
                'status'      => old('viab_status.' . $i),
                'responsible' => old('viab_responsible.' . $i),
                'note'        => old('viab_note.' . $i),
            ];
        }
    } else {
        $viabRows = ($isEdit && is_array($report->viability_checklist)) ? array_values($report->viability_checklist) : [];
    }
    if (is_array(old('agr_item'))) {
        $agrRows = [];
        foreach (old('agr_item') as $i => $ai) {
            $agrRows[] = [
                'item'        => $ai,
                'responsible' => old('agr_responsible.' . $i),
                'date'        => old('agr_date.' . $i),
                'status'      => old('agr_status.' . $i),
            ];
        }
    } else {
        $agrRows = ($isEdit && is_array($report->agreements)) ? array_values($report->agreements) : [];
    }
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form action="{{ $isEdit ? route('scoutings.update', $report->id) : route('scoutings.store') }}" method="POST" enctype="multipart/form-data" data-cc-autosave="scouting-report" data-cc-sections>
    @csrf

    {{-- (2026-08-01 · captura fluida, Paso 7) Secciones plegables + estado por sección.
         NO invasivo: marca este <form> con data-cc-sections y el comportamiento convierte
         cada encabezado de tarjeta en plegador con chip de estado (Revisar/Falta/Listo/…),
         barra Expandir/Contraer todo y auto-expansión de la sección con error al enviar.
         Para arrancar contraído deja `data-cc-sections="collapsed"` en el <form> de arriba. --}}
    @include('componentes._collapsible-sections')
    @if($isEdit)
        @method('PUT')
    @endif

    {{-- ============ SECCIÓN: GENERAL ============ --}}
    <div class="card shadow-sm mb-4">
        <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico'])
            <span>General</span>
        </h3>
        <div class="card-body">
            <div class="row g-3">
                @php
                    // Producción SIN dropdown: el nombre del proyecto vive en Configuración → Marca.
                    // (2026-07-31) El campo VISIBLE muestra SIEMPRE el brand_name EN VIVO de la Marca
                    // ($branding global), igual que los reportes ($heroProject = $brandName). Antes
                    // pintaba $report->production_name, que en EDICIÓN conservaba un valor viejo/estático
                    // (p. ej. "Producción Demo") y confundía. Cambio SOLO de presentación.
                    $prodBrand = $branding['brand_name'] ?? 'CrewCare';
                    // El hidden que SE PERSISTE no cambia: sigue mandando el production_name guardado
                    // (old() primero). Así no se altera lo ya escrito ni el hash/sello del reporte.
                    $prodName  = old('production_name', $report->production_name ?? ($branding['brand_name'] ?? ''));
                @endphp
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Producción</label>
                    <input type="text" class="form-control bg-light" value="{{ $prodBrand !== '' ? $prodBrand : 'CrewCare' }}" readonly>
                    <small class="cc-muted">Definida en Configuración → Marca.</small>
                    <input type="hidden" name="production_name" value="{{ $prodName }}">
                </div>
                <div class="col-md-8">
                    <label for="location_name" class="form-label fw-semibold">Locación <span class="text-danger">*</span></label>
                    <input type="text" id="location_name" name="location_name" class="form-control @error('location_name') is-invalid @enderror" value="{{ old('location_name', $report->location_name ?? '') }}" required>
                    @error('location_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Dirección MANDA: el parcial en modo 'address' renderiza el campo Dirección con
                     botón 📍 discreto (input-group). Lat/lng viven en hidden y se llenan solos, tanto
                     al usar 📍 como al escribir la dirección a mano (geocodifica al salir del campo).
                     El buscador de hospitales de Emergencia lee esos hidden ([name=latitude]). --}}
                <div class="col-md-8">
                    @include('componentes._geo-capture', [
                        'mode'         => 'address',
                        'required'     => false,
                        'latValue'     => old('latitude', $report->latitude ?? ''),
                        'lngValue'     => old('longitude', $report->longitude ?? ''),
                        'addressValue' => old('location_address', $report->location_address ?? ''),
                    ])
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Escena</label>
                    <input type="text" name="scene" class="form-control" value="{{ old('scene', $report->scene ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fecha Prep</label>
                    <input type="date" name="date_prep" class="form-control" value="{{ old('date_prep', $isEdit ? optional($report->date_prep)->format('Y-m-d') : '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fecha Shoot</label>
                    <input type="date" name="date_shoot" class="form-control" value="{{ old('date_shoot', $isEdit ? optional($report->date_shoot)->format('Y-m-d') : '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fecha Wrap</label>
                    <input type="date" name="date_wrap" class="form-control" value="{{ old('date_wrap', $isEdit ? optional($report->date_wrap)->format('Y-m-d') : '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tipo de locación</label>
                    <select name="loc_setting" class="form-select">
                        <option value="">—</option>
                        <option value="Interior" {{ old('loc_setting', $report->loc_setting ?? '') === 'Interior' ? 'selected' : '' }}>Interior</option>
                        <option value="Exterior" {{ old('loc_setting', $report->loc_setting ?? '') === 'Exterior' ? 'selected' : '' }}>Exterior</option>
                        {{-- "Int./Ext." (antes "Mixto") — se renombró para no chocar con el "Mixto" de Horario.
                             Selecciona también el valor legado "Mixto" para que reportes viejos se migren al guardar. --}}
                        <option value="Int./Ext." {{ in_array(old('loc_setting', $report->loc_setting ?? ''), ['Int./Ext.', 'Mixto'], true) ? 'selected' : '' }}>Int./Ext.</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Horario</label>
                    <select name="shoot_time" class="form-select">
                        <option value="">—</option>
                        <option value="Día" {{ old('shoot_time', $report->shoot_time ?? '') === 'Día' ? 'selected' : '' }}>Día</option>
                        <option value="Noche" {{ old('shoot_time', $report->shoot_time ?? '') === 'Noche' ? 'selected' : '' }}>Noche</option>
                        <option value="Mixto" {{ old('shoot_time', $report->shoot_time ?? '') === 'Mixto' ? 'selected' : '' }}>Mixto</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Complejidad</label>
                    <select name="complexity" class="form-select">
                        <option value="">—</option>
                        <option value="Baja" {{ old('complexity', $report->complexity ?? '') === 'Baja' ? 'selected' : '' }}>Baja</option>
                        <option value="Media" {{ old('complexity', $report->complexity ?? '') === 'Media' ? 'selected' : '' }}>Media</option>
                        <option value="Alta" {{ old('complexity', $report->complexity ?? '') === 'Alta' ? 'selected' : '' }}>Alta</option>
                    </select>
                </div>

                {{-- Encabezado del formato oficial Amazon MGM (OPCIONAL): tipo de producción
                     y responsables. Se capturan una vez y el documento Amazon los usa; el
                     resto del encabezado ya sale de la Marca / autofirma. No son obligatorios. --}}
                <div class="col-12"><hr class="my-1"><small class="cc-muted fw-semibold">Encabezado Amazon MGM <span class="fw-normal">(opcional)</span></small></div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tipo de producción</label>
                    <select name="production_type" class="form-select">
                        <option value="">—</option>
                        @foreach(['TV', 'Película', 'Comercial', 'Game Show', 'Documental', 'Otro'] as $pt)
                            <option value="{{ $pt }}" {{ old('production_type', $report->production_type ?? '') === $pt ? 'selected' : '' }}>{{ $pt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Gerente de producción</label>
                    <input type="text" name="manager_name" class="form-control" value="{{ old('manager_name', $report->manager_name ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Rep. de seguridad</label>
                    <input type="text" name="safety_rep_name" class="form-control" value="{{ old('safety_rep_name', $report->safety_rep_name ?? '') }}">
                </div>
            </div>
        </div>
    </div>

    {{-- ============ SECCIÓN: EMERGENCIA ============ --}}
    <div class="card shadow-sm mb-4">
        <h3 class="card-header bg-danger text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'heart-pulse', 'class' => 'cc-ico'])
            <span>Encabezado de Emergencia</span>
        </h3>
        <div class="card-body">

            {{-- Buscador de hospitales cercanos (Overpass/OSM + ETA por OSRM).
                 Privados primero por política de producción. Lee lat/lng de los hidden
                 que llena el campo Dirección de la sección General. --}}
            <div class="mb-3" data-hospital-finder
                 data-lat="[name=latitude]" data-lng="[name=longitude]"
                 data-name-target="[name=nearest_hospital]"
                 data-address-target="[name=hospital_address]"
                 data-eta-target="[name=hospital_eta]"
                 data-distance-target="[name=hospital_distance_km]">
                <button type="button" class="btn btn-outline-danger js-hosp-search d-inline-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico'])
                    <span>Buscar hospitales cercanos</span>
                </button>
                <small class="js-hosp-help small cc-muted d-block mt-1">
                    Usa la ubicación del campo Dirección (sección General) para listar hospitales (privados primero) y calcular el ETA real en auto.
                </small>
                <div class="js-hosp-results list-group mt-2" style="display:none; max-height: 320px; overflow-y: auto;"></div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Hospital más cercano</label>
                    <input type="text" name="nearest_hospital" class="form-control" value="{{ old('nearest_hospital', $report->nearest_hospital ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Dirección del hospital</label>
                    <input type="text" name="hospital_address" class="form-control" value="{{ old('hospital_address', $report->hospital_address ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">ETA al hospital</label>
                    <input type="text" name="hospital_eta" class="form-control" placeholder="Ej: 12 min" value="{{ old('hospital_eta', $report->hospital_eta ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Distancia al hospital (km)</label>
                    <input type="number" step="0.01" min="0" inputmode="decimal" name="hospital_distance_km" class="form-control" placeholder="Ej: 3.2" value="{{ old('hospital_distance_km', $report->hospital_distance_km ?? '') }}">
                    <small class="cc-muted d-block mt-1">La llena el buscador (ruta real en auto). Ajústala si hace falta.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Compañía de ambulancia</label>
                    <input type="text" name="ambulance_company" class="form-control" value="{{ old('ambulance_company', $report->ambulance_company ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Teléfono de emergencia</label>
                    <input type="tel" inputmode="numeric" name="emergency_phone" class="form-control" value="{{ old('emergency_phone', $report->emergency_phone ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Punto de reunión</label>
                    <input type="text" name="assembly_point" class="form-control" value="{{ old('assembly_point', $report->assembly_point ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Acceso de emergencia</label>
                    <input type="text" name="emergency_access" class="form-control" placeholder="Ruta de acceso para servicios de emergencia" value="{{ old('emergency_access', $report->emergency_access ?? '') }}">
                </div>
            </div>
        </div>
    </div>

    {{-- ============ SECCIÓN: INVENTARIO, LOGÍSTICA Y EPP (módulos 8 y 9) ============
         Todo CAPTURA (nada obligatorio). Cada bloque va GATED por Schema::hasColumn: en
         prod el SQL de cimientos aún no está aplicado; sin la columna, el bloque no se
         renderiza (así no se muestran campos que no se podrían guardar). Los inputs usan
         nombres tipo emergency_equipment_inventory[fire_extinguishers] / required_ppe[]. --}}
    @php
        $hasMaxHeadcount = \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'max_headcount');
        $hasEmergInv     = \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'emergency_equipment_inventory');
        $hasLogistics    = \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'logistics_facilities');
        $hasReqPpe       = \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'required_ppe');

        // Valores: old() primero (no perder lo tecleado tras error), luego lo guardado al editar.
        $eei     = old('emergency_equipment_inventory', ($isEdit && is_array($report->emergency_equipment_inventory ?? null)) ? $report->emergency_equipment_inventory : []);
        $eei     = is_array($eei) ? $eei : [];
        $eeiFire = $eei['fire_extinguishers'] ?? '';
        $eeiAed  = !empty($eei['aed']);
        $eeiKits = $eei['first_aid_kits'] ?? '';

        $lf      = old('logistics_facilities', ($isEdit && is_array($report->logistics_facilities ?? null)) ? $report->logistics_facilities : []);
        $lf      = is_array($lf) ? $lf : [];
        $lfRest  = !empty($lf['restrooms']);
        $lfHyd   = $lf['hydration_stations'] ?? '';
        $lfShade = !empty($lf['shade_areas']);

        $ppeSel     = old('required_ppe', ($isEdit && is_array($report->required_ppe ?? null)) ? $report->required_ppe : []);
        $ppeSel     = is_array($ppeSel) ? $ppeSel : [];
        $ppeOptions = ['Casco', 'Chaleco', 'Botas', 'Guantes', 'Lentes', 'Arnés', 'Protección auditiva', 'Bloqueador'];
    @endphp
    @if($hasMaxHeadcount || $hasEmergInv || $hasLogistics || $hasReqPpe)
    <div class="card shadow-sm mb-4">
        <h3 class="card-header bg-secondary text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'package', 'class' => 'cc-ico'])
            <span>Inventario, logística y EPP</span>
        </h3>
        <div class="card-body">
            @if($hasMaxHeadcount)
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Aforo máximo permitido</label>
                        <input type="number" min="0" name="max_headcount" class="form-control" value="{{ old('max_headcount', $report->max_headcount ?? '') }}" placeholder="Ej: 150">
                        <small class="cc-muted">Personas máximas permitidas en la locación.</small>
                    </div>
                </div>
            @endif

            @if($hasEmergInv)
                <hr class="my-3">
                <h6 class="fw-semibold cc-muted mb-2">Inventario de equipo de emergencia</h6>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Extintores</label>
                        <input type="number" min="0" name="emergency_equipment_inventory[fire_extinguishers]" class="form-control" value="{{ $eeiFire }}" placeholder="Número">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Botiquines</label>
                        <input type="number" min="0" name="emergency_equipment_inventory[first_aid_kits]" class="form-control" value="{{ $eeiKits }}" placeholder="Número">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="emergency_equipment_inventory[aed]" value="1" id="eei_aed" {{ $eeiAed ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="eei_aed">DEA / AED disponible</label>
                        </div>
                    </div>
                </div>
            @endif

            @if($hasLogistics)
                <hr class="my-3">
                <h6 class="fw-semibold cc-muted mb-2">Instalaciones y logística</h6>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Estaciones de hidratación</label>
                        <input type="number" min="0" name="logistics_facilities[hydration_stations]" class="form-control" value="{{ $lfHyd }}" placeholder="Número">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="logistics_facilities[restrooms]" value="1" id="lf_restrooms" {{ $lfRest ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="lf_restrooms">Sanitarios</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="logistics_facilities[shade_areas]" value="1" id="lf_shade" {{ $lfShade ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="lf_shade">Áreas de sombra</label>
                        </div>
                    </div>
                </div>
            @endif

            @if($hasReqPpe)
                <hr class="my-3">
                <h6 class="fw-semibold cc-muted mb-2">EPP requerido</h6>
                <div class="d-flex flex-wrap gap-3">
                    @foreach($ppeOptions as $i => $ppe)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="required_ppe[]" value="{{ $ppe }}" id="ppe_{{ $i }}" {{ in_array($ppe, $ppeSel) ? 'checked' : '' }}>
                            <label class="form-check-label" for="ppe_{{ $i }}">{{ $ppe }}</label>
                        </div>
                    @endforeach
                </div>
                <small class="cc-muted d-block mt-1">EPP recomendado para el personal en esta locación.</small>
            @endif
        </div>
    </div>
    @endif

    {{-- ============ SECCIÓN: TABLA DE PELIGROS (Amazon MGM Risk Assessment) ============ --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="h6 mb-0 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-ico'])
                <span>Evaluación de Riesgos H&amp;S — Tabla de peligros</span>
            </h3>
            <button type="button" class="btn btn-sm btn-light fw-semibold" id="rmx-toggle">Ver matriz de riesgo</button>
        </div>
        <div class="card-body">
            <p class="cc-muted small mb-2">
                Una fila por peligro. Elige el <strong>Evento</strong> del catálogo (agrupado por contexto): su
                <strong>Norma</strong> se etiqueta sola y se sugiere la Probabilidad/Consecuencia típicas. Ajusta
                <strong>Probabilidad</strong> (A–E) y <strong>Consecuencia</strong> (1–5) — la <strong>Clasificación</strong>
                se calcula sola con la matriz 5×5. Escribe las medidas de control, el riesgo residual (tras controles)
                y el personal requerido. Agrega o quita filas según necesites.
            </p>

            {{-- Matriz de riesgo (referencia colapsable, sin dependencia de Bootstrap JS) --}}
            <div id="rmx-ref" class="mb-3" style="display:none;">
                <div class="border rounded p-3 bg-light">
                    @include('componentes._risk-matrix', ['lang' => 'es', 'interactive' => true, 'legend' => true])
                </div>
            </div>

            {{-- (2026-07-15) Móvil: la tabla de peligros (8 columnas, min-width 1200px) generaba
                 scroll horizontal justo en la evaluación de riesgo. En <768px cada fila se vuelve
                 una tarjeta (etiqueta arriba, control a lo ancho). Las etiquetas se inyectan por
                 nth-child porque las <td> viven en el parcial _hazard-row (fuera de esta superficie);
                 el orden de columnas es fijo. En desktop la tabla se ve normal. --}}
            <style>
                @media (max-width: 767.98px) {
                    #hazards-table { min-width: 0 !important; }
                    #hazards-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
                    #hazards-table, #hazards-table tbody, #hazards-table tr, #hazards-table td { display: block; width: 100%; }
                    #hazards-table tr.hz-row { border: 1px solid var(--border); border-radius: 12px; background: var(--surface-2); padding: .6rem .75rem; margin-bottom: .85rem; }
                    #hazards-table td { border: 0 !important; padding: .3rem 0; text-align: left; }
                    #hazards-table td::before { display: block; content: attr(data-cc-label); font-size: .72rem; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; color: var(--text-muted); margin-bottom: .2rem; }
                    #hazards-table td:nth-child(1)::before { content: "Peligro potencial"; }
                    #hazards-table td:nth-child(2)::before { content: "Probabilidad (A–E)"; }
                    #hazards-table td:nth-child(3)::before { content: "Consecuencia (1–5)"; }
                    #hazards-table td:nth-child(4) { text-align: center; }
                    #hazards-table td:nth-child(4)::before { content: "Clasificación"; text-align: center; }
                    #hazards-table td:nth-child(5)::before { content: "Medidas de control"; }
                    #hazards-table td:nth-child(6)::before { content: "Riesgo residual"; }
                    #hazards-table td:nth-child(7)::before { content: "Personal requerido"; }
                    #hazards-table td:nth-child(8)::before { content: "Norma aplicable"; }
                    #hazards-table td:nth-child(9) { text-align: right; padding-top: .1rem; }
                    #hazards-table td:nth-child(9)::before { content: none; }
                }
            </style>

            {{-- (Paso 1b) Color de los marcos (.badge-XXX) a nivel de MÓDULO (fuera de la
                 tabla): lo consumen los chips del filtro de aquí abajo y los chips que el
                 typeahead rico pinta en cada opción de evento. @once deduplica. --}}
            @include('componentes._badge-tokens')

            {{-- (Paso 1b) Filtro GLOBAL por marco normativo. Una sola fila de chips que
                 restringe la VISTA del buscador (typeahead) de TODAS las filas de peligro a
                 la vez, vía window.CCTypeahead.setFacet. Es filtro de VISTA: se intersecta
                 con lo escrito y NUNCA cambia el evento elegido. Labels literales estáticos;
                 el color sale de .badge-XXX. --}}
            <style>
                .hz-facets { display:flex; flex-wrap:wrap; align-items:center; gap:.4rem; margin-bottom:.6rem; }
                .hz-facets .hzf-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; }
                .hzf-chip { border:none; border-radius:999px; padding:.4rem .85rem; font-size:.72rem; font-weight:700; letter-spacing:.03em; line-height:1; cursor:pointer; opacity:.42; transition:opacity .12s ease, box-shadow .12s ease; }
                .hzf-chip:hover { opacity:.75; }
                .hzf-chip[aria-pressed="true"] { opacity:1; box-shadow:0 0 0 2px rgba(15,23,42,.18); }
                @media (pointer: coarse) { .hzf-chip { min-height:44px; } }
            </style>
            <div class="hz-facets" id="hz-facets" role="group" aria-label="Filtrar peligros por marco normativo">
                <span class="hzf-label">Filtrar:</span>
                @foreach(['CSATF', 'OSHA', 'STPS', 'DOT', 'SCT'] as $mk)
                    <button type="button" class="hzf-chip badge badge-{{ $mk }}" data-marco="{{ $mk }}" aria-pressed="false">{{ $mk }}</button>
                @endforeach
            </div>

            {{-- (2026-08-01 · captura fluida, Pasos 1-3) Entrar por ACTIVIDAD: elige la(s)
                 actividad(es) del día y agrega sus peligros; cada uno llega con su medida de
                 control y su norma pre-propuestas (editables). La tabla de abajo arranca vacía:
                 una fila existe cuando agregas ese peligro aquí. Para algo fuera de las
                 actividades, escribe en el buscador (llega al catálogo completo). --}}
            <div class="mb-3">
                @include('componentes._hazard-activity-picker', ['hazardEvents' => $hazardEvents ?? collect(), 'pickerId' => 'hzpick-scouting'])
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle" style="min-width: 1200px;" id="hazards-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:20%">Peligro potencial</th>
                            <th style="width:12%" class="text-center" title="Probabilidad (A–E)">Prob.</th>
                            <th style="width:12%" class="text-center" title="Consecuencia (1–5)">Cons.</th>
                            <th style="width:9%" class="text-center">Clasif.</th>
                            <th style="width:15%">Medidas de control</th>
                            <th style="width:8%" class="text-center" title="Riesgo tras controles">Residual</th>
                            <th style="width:10%">Personal req.</th>
                            <th style="width:14%">Norma</th>
                            <th style="width:32px"></th>
                        </tr>
                    </thead>
                    <tbody id="hazards-body">
                        @foreach($hazardRows as $hz)
                            @include('admin.scoutings._hazard-row', ['hz' => $hz, 'hazardEvents' => $hazardEvents ?? collect()])
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary" id="hz-add">+ Agregar peligro</button>

            {{-- (2026-07-24) AVISO DE PELIGROS SIN CLASIFICAR.
                 El peligro debe llevar su evento del catálogo: es la ÚNICA llave común entre lo
                 que el scouting predice y lo que después ocurre en el set. Sin ella el reporte
                 de wrap no puede contrastar nada.
                 Y aun así el select NO es obligatorio, a propósito: obligar produce
                 clasificaciones falsas —se elige "lo más parecido" con tal de poder guardar— y
                 una llave inventada envenena el contraste más que un hueco declarado. Así que
                 esto avisa, no bloquea, y lo que se guarde sin evento queda marcado para que el
                 wrap lo reporte como "sin clasificar". --}}
            <div id="hz-sin-evento" class="alert alert-warning d-none mt-3 py-2 px-3 small mb-0" role="status" aria-live="polite">
                <strong><span id="hz-sin-evento-n">0</span></strong>
                <span id="hz-sin-evento-txt">peligros sin evento del catálogo.</span>
                Se guardarán como <strong>sin clasificar</strong> y no podrán cruzarse contra lo que ocurra en el set.
            </div>

            {{-- Casilla SB132: actividades especiales declaradas (gatilla el RA específico) --}}
            <div class="mt-3 p-3 border rounded {{ $specialChecked ? 'border-danger' : '' }}" id="sb132-box">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="special_activities" value="1" id="special_activities" {{ $specialChecked ? 'checked' : '' }} onchange="toggleSpecial()">
                    <label class="form-check-label fw-semibold" for="special_activities">
                        Se declaran <u>actividades especiales</u> (armas / pirotecnia / stunts / aéreo / agua / off-road / fuego abierto / altura)
                    </label>
                </div>
                <div id="sb132-alert" class="alert alert-danger fw-bold mt-2 mb-0 d-flex align-items-center gap-2" style="display:none;">
                    @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico'])
                    <span>Este reporte requerirá un <u>Specific Risk Assessment (SB132)</u>.</span>
                </div>

                {{-- (2026-07-09) Desglose OBLIGATORIO cuando se declaran actividades especiales. --}}
                <div id="sb132-details" class="mt-3" style="{{ $specialChecked ? '' : 'display:none;' }}">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small fw-semibold d-block mb-1">Tipo(s) de actividad especial <span class="text-danger">*</span></label>
                            @foreach(['armas'=>'Armas de fuego','pirotecnia'=>'Pirotecnia / SFX','stunts'=>'Stunts','aereo'=>'Aéreo (helicóptero/dron)','agua'=>'Agua','off-road'=>'Off-road / vehículos','fuego'=>'Fuego abierto / llamas','altura'=>'Trabajo en altura / rigging'] as $k=>$lbl)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="sb132_details[activity_type][]" value="{{ $k }}" id="sb132_act_{{ $loop->index }}" {{ in_array($k, $sb132Activities) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="sb132_act_{{ $loop->index }}">{{ $lbl }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Número(s) de escena <span class="text-danger">*</span></label>
                            <input type="text" name="sb132_details[scene_number]" class="form-control form-control-sm" value="{{ $sb132Scene }}" placeholder="Ej: 12, 14B">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Personal certificado requerido <span class="text-danger">*</span></label>
                            <input type="text" name="sb132_details[certified_personnel_required]" class="form-control form-control-sm" value="{{ $sb132Personnel }}" placeholder="Ej: Armero certificado, técnico pirotécnico">
                        </div>
                    </div>
                    <small class="cc-muted d-block mt-1">Obligatorio al declarar actividades especiales (SB-132 Specific Risk Assessment).</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Plantilla oculta para clonar filas nuevas (su contenido no se envía) --}}
    <template id="hz-template">
        @include('admin.scoutings._hazard-row', ['hz' => [], 'hazardEvents' => $hazardEvents ?? collect()])
    </template>

    {{-- Buscador (typeahead) para los selects de evento de cada fila de peligro. --}}
    @include('componentes._typeahead')

    {{-- ============ SECCIÓN: MAPEO DE LA LOCACIÓN (delta #48) ============
         Pines sobre lienzos (satelital/foto/plano/aéreo): dónde están los peligros y
         los recursos de emergencia. Insumo del PAE. Vive en su PROPIA página, con su
         propio guardado (AJAX), no dentro de este <form>, para no mezclar el guardado
         del scouting con el de los lienzos. Ocupa el antiguo stub "mapeo aéreo". --}}
    <div class="card shadow-sm mb-4">
        <h3 class="card-header bg-secondary text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico'])
            <span>Mapeo de la locación</span>
        </h3>
        <div class="card-body">
            <p class="text-muted mb-2">Localiza sobre una imagen (satelital, foto, plano o aéreo de dron) dónde están los peligros y los recursos de emergencia: extintores, botiquín, salidas, punto de reunión, tablero eléctrico… Es opcional y es el insumo del PAE.</p>
            @if($isEdit)
                <a href="{{ route('scoutings.mapping', $report->id) }}" target="_blank" rel="noopener" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico'])
                    <span>Abrir mapeo de la locación</span>
                </a>
                <div class="form-text">Se abre en otra pestaña para no perder lo que estás editando aquí. Si acabas de cambiar datos de la locación, guárdalos antes.</div>
            @else
                <div class="alert alert-info mb-0 py-2">Guarda primero la locación para poder mapearla (agregar lienzos y colocar pines).</div>
            @endif
        </div>
    </div>

    {{-- (Pilar 5) Módulo de gran escala pendiente — estructura lista, OCULTA tras su
         flag (apagado por defecto; se enciende en Ajustes › Feature Flags). --}}
    @include('componentes._handover-stub')

    {{-- Comportamiento compartido de filas que se auto-agregan (Viabilidad y Acuerdos). --}}
    @include('componentes._repeatable-rows')

    {{-- ============ SECCIÓN: VIABILIDAD ============ --}}
    <div class="card shadow-sm mb-4">
        <h3 class="card-header bg-secondary text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico'])
            <span>Viabilidad</span>
        </h3>
        <div class="card-body">
            {{-- (2026-07-15) cc-stack: en <768px cada fila se apila como tarjeta (data-label
                 arriba) para matar el scroll horizontal; en desktop se ve como tabla normal. --}}
            <div data-cc-repeat>
                <div class="table-responsive">
                    <table class="table table-sm align-middle cc-stack" style="min-width: 600px;">
                        <thead class="table-light">
                            <tr>
                                <th>Área</th>
                                <th style="width:18%">Estatus</th>
                                <th>Responsable</th>
                                <th>Nota</th>
                                <th style="width:36px"></th>
                            </tr>
                        </thead>
                        <tbody data-cc-repeat-body>
                            @foreach($viabRows as $v)
                                <tr data-cc-repeat-row>
                                    <td data-label="Área"><input type="text" name="viab_area[]" class="form-control form-control-sm" value="{{ $v['area'] ?? '' }}"></td>
                                    <td data-label="Estatus">
                                        <select name="viab_status[]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            <option value="OK" {{ ($v['status'] ?? '') === 'OK' ? 'selected' : '' }}>OK</option>
                                            <option value="PorAsignar" {{ ($v['status'] ?? '') === 'PorAsignar' ? 'selected' : '' }}>Por asignar</option>
                                            <option value="Pendiente" {{ ($v['status'] ?? '') === 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
                                        </select>
                                    </td>
                                    <td data-label="Responsable"><input type="text" name="viab_responsible[]" class="form-control form-control-sm" value="{{ $v['responsible'] ?? '' }}"></td>
                                    <td data-label="Nota"><input type="text" name="viab_note[]" class="form-control form-control-sm" value="{{ $v['note'] ?? '' }}"></td>
                                    <td data-label="" class="text-center align-middle"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-cc-repeat-remove title="Quitar" aria-label="Quitar fila">&times;</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <template data-cc-repeat-tpl>
                    <tr data-cc-repeat-row>
                        <td data-label="Área"><input type="text" name="viab_area[]" class="form-control form-control-sm"></td>
                        <td data-label="Estatus">
                            <select name="viab_status[]" class="form-select form-select-sm">
                                <option value="">—</option>
                                <option value="OK">OK</option>
                                <option value="PorAsignar">Por asignar</option>
                                <option value="Pendiente">Pendiente</option>
                            </select>
                        </td>
                        <td data-label="Responsable"><input type="text" name="viab_responsible[]" class="form-control form-control-sm"></td>
                        <td data-label="Nota"><input type="text" name="viab_note[]" class="form-control form-control-sm"></td>
                        <td data-label="" class="text-center align-middle"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-cc-repeat-remove title="Quitar" aria-label="Quitar fila">&times;</button></td>
                    </tr>
                </template>
                <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" data-cc-repeat-add>
                    @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico'])
                    <span>Agregar área</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ============ SECCIÓN: ACUERDOS ============ --}}
    <div class="card shadow-sm mb-4">
        <h3 class="card-header bg-secondary text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico'])
            <span>Acuerdos</span>
        </h3>
        <div class="card-body">
            {{-- (2026-07-15) cc-stack: filas apiladas como tarjeta en <768px (data-label arriba). --}}
            <div data-cc-repeat>
                <div class="table-responsive">
                    <table class="table table-sm align-middle cc-stack" style="min-width: 600px;">
                        <thead class="table-light">
                            <tr>
                                <th>Acuerdo</th>
                                <th>Responsable</th>
                                <th style="width:18%">Fecha</th>
                                <th style="width:18%">Estatus</th>
                                <th style="width:36px"></th>
                            </tr>
                        </thead>
                        <tbody data-cc-repeat-body>
                            @foreach($agrRows as $a)
                                <tr data-cc-repeat-row>
                                    <td data-label="Acuerdo"><input type="text" name="agr_item[]" class="form-control form-control-sm" value="{{ $a['item'] ?? '' }}"></td>
                                    <td data-label="Responsable"><input type="text" name="agr_responsible[]" class="form-control form-control-sm" value="{{ $a['responsible'] ?? '' }}"></td>
                                    <td data-label="Fecha"><input type="date" name="agr_date[]" class="form-control form-control-sm" value="{{ $a['date'] ?? '' }}"></td>
                                    <td data-label="Estatus">
                                        <select name="agr_status[]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            <option value="Pendiente" {{ ($a['status'] ?? '') === 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
                                            <option value="EnProceso" {{ ($a['status'] ?? '') === 'EnProceso' ? 'selected' : '' }}>En proceso</option>
                                            <option value="Cerrado" {{ ($a['status'] ?? '') === 'Cerrado' ? 'selected' : '' }}>Cerrado</option>
                                        </select>
                                    </td>
                                    <td data-label="" class="text-center align-middle"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-cc-repeat-remove title="Quitar" aria-label="Quitar fila">&times;</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <template data-cc-repeat-tpl>
                    <tr data-cc-repeat-row>
                        <td data-label="Acuerdo"><input type="text" name="agr_item[]" class="form-control form-control-sm"></td>
                        <td data-label="Responsable"><input type="text" name="agr_responsible[]" class="form-control form-control-sm"></td>
                        <td data-label="Fecha"><input type="date" name="agr_date[]" class="form-control form-control-sm"></td>
                        <td data-label="Estatus">
                            <select name="agr_status[]" class="form-select form-select-sm">
                                <option value="">—</option>
                                <option value="Pendiente">Pendiente</option>
                                <option value="EnProceso">En proceso</option>
                                <option value="Cerrado">Cerrado</option>
                            </select>
                        </td>
                        <td data-label="" class="text-center align-middle"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-cc-repeat-remove title="Quitar" aria-label="Quitar fila">&times;</button></td>
                    </tr>
                </template>
                <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" data-cc-repeat-add>
                    @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico'])
                    <span>Agregar acuerdo</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ============ SECCIÓN: RESÚMENES ============ --}}
    <div class="card shadow-sm mb-4">
        <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico'])
            <span>Resúmenes</span>
        </h3>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Resumen ejecutivo</label>
                <textarea name="exec_summary" class="form-control" rows="3">{{ old('exec_summary', $report->exec_summary ?? '') }}</textarea>
            </div>
            <div class="mb-0">
                <label class="form-label fw-semibold">Notas operativas</label>
                <textarea name="operational_notes" class="form-control" rows="3">{{ old('operational_notes', $report->operational_notes ?? '') }}</textarea>
            </div>
        </div>
    </div>

    {{-- ============ SECCIÓN: IMÁGENES ============ --}}
    <style>
        .ai-add-btn { border:1px dashed #cbd2da; background:#fff; color:#334155; border-radius:10px; padding:.5rem .9rem; font-weight:600; font-size:.88rem; cursor:pointer; transition:border-color .15s ease, background .15s ease, color .15s ease; }
        .ai-add-btn:hover { border-color:#0f172a; background:#f8fafc; color:#0f172a; }
        .ai-add-btn:disabled { opacity:.6; cursor:progress; }
        .ai-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:.6rem; }
        .ai-cell { display:flex; flex-direction:column; }
        .ai-thumb { position:relative; border-radius:10px; overflow:hidden; border:1px solid #eceef1; background:#f3f4f6; aspect-ratio:4/3; min-height:70px; }
        .ai-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .ai-thumb .ai-rm { position:absolute; top:5px; right:5px; width:22px; height:22px; padding:0; border:none; border-radius:999px; background:rgba(15,23,42,.72); color:#fff; font-size:.9rem; line-height:1; cursor:pointer; opacity:0; transition:opacity .15s ease, background .15s ease, transform .15s ease; }
        .ai-thumb:hover .ai-rm, .ai-thumb:focus-within .ai-rm { opacity:1; }
        .ai-thumb .ai-rm:hover { background:#e24b4a; transform:scale(1.08); }
        .ai-thumb .ai-sz { position:absolute; left:0; right:0; bottom:0; font-size:.62rem; color:#fff; text-align:center; padding:.5rem .3rem .25rem; background:linear-gradient(transparent, rgba(0,0,0,.62)); }
        .ai-thumb .ai-sz.ai-over { color:#ffd7d5; font-weight:600; }
        .ai-cap { font-size:.78rem; padding:.28rem .5rem; }
        .ai-cap::placeholder { color:#9aa3af; }
        .ai-existing-title { font-size:.8rem; }
        @media (hover: none) { .ai-thumb .ai-rm { opacity:.92; } }
    </style>
    <div class="card shadow-sm mb-4">
        <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico'])
            <span>Imágenes</span>
        </h3>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Imagen principal</label>
                    @if($isEdit && $report->main_image_path)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img src="{{ $report->main_image_path }}" alt="Imagen principal actual"
                                 class="rounded border" style="height:60px;width:90px;object-fit:cover;">
                            <small class="cc-muted">Imagen actual — sube una nueva para <strong>reemplazarla</strong>.</small>
                        </div>
                    @endif
                    <input type="file" id="main-image-input" name="main_image" class="form-control" accept="image/*">
                    <small class="cc-muted d-block mt-1">JPG, PNG o GIF · hasta 12 MB. Se optimiza sola al subir.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Imágenes adicionales</label>
                    {{-- Señal de que esta sección gestiona las imágenes: update() reconstruye el set
                         (conservadas + nuevas), lo que permite editar pies de foto y quitar existentes. --}}
                    <input type="hidden" name="images_managed" value="1">

                    @if($isEdit)
                        @php $existingImgs = $report->additionalImagesList(); @endphp
                        @if(count($existingImgs))
                            <div id="ai-existing" class="mb-3">
                                <div class="cc-muted ai-existing-title mb-2">Ya guardadas — edita el pie de foto o quítalas con&nbsp;×:</div>
                                <div class="ai-grid">
                                    @foreach($existingImgs as $ei)
                                        <div class="ai-cell">
                                            <div class="ai-thumb">
                                                <img src="{{ $ei['path'] }}" alt="Imagen adicional">
                                                <button type="button" class="ai-rm ai-ex-rm" title="Quitar">&times;</button>
                                            </div>
                                            <input type="hidden" name="existing_images[]" value="{{ $ei['path'] }}">
                                            <input type="text" name="existing_images_captions[]"
                                                   class="form-control form-control-sm ai-cap mt-1" maxlength="300"
                                                   placeholder="Pie de foto (hallazgo / acción)…" value="{{ $ei['caption'] }}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                    {{-- Uploader múltiple con mejora progresiva: el input real funciona solo;
                         el JS (si el navegador lo permite) lo oculta y muestra botón + miniaturas con pie de foto. --}}
                    <div id="ai-uploader">
                        <input type="file" id="ai-input" name="additional_images[]" accept="image/*" multiple class="form-control">
                        <button type="button" id="ai-add" class="ai-add-btn d-inline-flex align-items-center gap-1" style="display:none;">
                            @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico'])
                            <span>Agregar imágenes</span>
                        </button>
                        <span id="ai-count" class="cc-muted small ms-2"></span>
                        <div id="ai-grid" class="ai-grid mt-2"></div>
                        <small id="ai-help" class="cc-muted d-block mt-1" style="display:none;">
                            Agrega varias imágenes y escribe un <strong>pie de foto</strong> en cada una para señalar hallazgos o urgir acciones. Puedes volver a pulsar el botón para sumar más; se optimizan solas para subir más rápido.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ SECCIÓN: FIRMA (AUTOFIRMA — sistema cerrado) ============ --}}
    <div class="card shadow-sm mb-4">
        <h3 class="card-header bg-dark text-white fw-bold h6 mb-0 d-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico'])
            <span>Firma</span>
        </h3>
        <div class="card-body">
            @if($isEdit)
                <div class="alert alert-secondary small mb-3 d-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico'])
                    <span>La <strong>autofirma original no cambia</strong>: el reporte conserva quién y cuándo lo capturó
                    por primera vez. La fecha de esta edición queda registrada automáticamente por el sistema.</span>
                </div>
            @else
                <div class="alert alert-secondary small mb-3 d-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico'])
                    <span>Este reporte se firma <strong>automáticamente</strong> con tu usuario y la fecha de hoy.
                    No es editable: así queda registro fiable de <strong>quién</strong> y <strong>cuándo</strong> capturó la información.</span>
                </div>
            @endif
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Elaborado por</label>
                    <input type="text" class="form-control bg-light" value="{{ $isEdit ? $report->make_by : auth()->user()->name }}" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Fecha</label>
                    <input type="text" class="form-control bg-light" value="{{ $isEdit ? optional($report->make_date)->format('d/m/Y') : date('d/m/Y') }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Estatus</label>
                    <select name="status" class="form-select">
                        <option value="draft" {{ old('status', $report->status ?? 'draft') === 'draft' ? 'selected' : '' }}>Borrador</option>
                        <option value="revision" {{ old('status', $report->status ?? 'draft') === 'revision' ? 'selected' : '' }}>Revisión</option>
                        <option value="final" {{ old('status', $report->status ?? 'draft') === 'final' ? 'selected' : '' }}>Final</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="{{ $isEdit ? route('scoutings.show', $report->id) : route('scoutings.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary fw-bold">{{ $isEdit ? 'Guardar cambios' : 'Guardar Scouting' }}</button>
    </div>

</form>

<script>
    // Aviso SB132: muestra/oculta según la casilla de actividades especiales.
    function toggleSpecial() {
        var cb      = document.getElementById('special_activities');
        var alert   = document.getElementById('sb132-alert');
        var box     = document.getElementById('sb132-box');
        var details = document.getElementById('sb132-details');
        if (!cb) return;
        if (alert) alert.style.display = cb.checked ? 'block' : 'none';
        if (details) details.style.display = cb.checked ? 'block' : 'none';
        if (box) box.classList.toggle('border-danger', cb.checked);
    }
    document.addEventListener('DOMContentLoaded', toggleSpecial);

    // Tabla de peligros: auto-clasificación (matriz Amazon), agregar y quitar filas.
    (function () {
        var MX = { A:['M','H','H','E','E'], B:['M','M','H','H','E'], C:['L','M','M','H','E'], D:['L','M','M','H','H'], E:['L','L','M','M','H'] };
        var RSTYLE = { L:'background:#C0DD97;color:#173404;', M:'background:#FAC775;color:#412402;', H:'background:#F0997B;color:#4A1B0C;', E:'background:#E24B4A;color:#fff;' };
        var RWORD  = { L:'Bajo', M:'Medio', H:'Alto', E:'Muy alto' };
        function rate(l, c) {
            if (!l || !c) return null;
            var row = MX[l]; if (!row) return null;
            var ci = parseInt(c, 10) - 1; if (ci < 0 || ci > 4) return null;
            return row[ci];
        }
        function refreshRow(tr, force) {
            var l = tr.querySelector('.hz-l'), c = tr.querySelector('.hz-c'), out = tr.querySelector('.hz-rating');
            if (!out) return;
            var lv = l ? l.value : '', cv = c ? c.value : '';
            if (!lv && !cv && !force) return; // conserva la clasificación del servidor si aún no hay P/C
            var r = rate(lv, cv);
            if (r) {
                out.className = 'hz-rating badge rounded-pill';
                out.setAttribute('style', RSTYLE[r]);
                out.textContent = r + ' · ' + RWORD[r];
            } else {
                out.className = 'hz-rating badge rounded-pill bg-light cc-muted border';
                out.removeAttribute('style');
                out.textContent = '—';
            }
        }
        var body = document.getElementById('hazards-body');
        if (!body) return;
        function wire(tr) {
            var sels = tr.querySelectorAll('.hz-l, .hz-c');
            sels.forEach(function (s) { s.addEventListener('change', function () { refreshRow(tr, true); }); });
            // (2026-07-13) Evento del catálogo: al elegirlo muestra su(s) norma(s) y
            // sugiere Prob/Cons típicas si están vacías (menos llenado manual).
            var ev = tr.querySelector('.hz-event');
            if (ev) {
                ev.addEventListener('change', function () {
                    var opt = ev.options[ev.selectedIndex];
                    var norm = tr.querySelector('.hz-norm');
                    var badges = opt ? (opt.getAttribute('data-badges') || '') : '';
                    var codes  = opt ? (opt.getAttribute('data-codes')  || '') : '';
                    if (norm) {
                        // (2026-07-18) Sink endurecido contra XSS de DOM: CERO innerHTML con
                        // datos de BD. Calca el patrón seguro de componentes/_event-picker
                        // (createElement + textContent). Los marcos se validan contra un enum
                        // BLANCO antes de usarse como sufijo .badge-XXX; los codes y cualquier
                        // token desconocido se pintan como texto literal (nunca interpretado).
                        var BADGE_ENUM = { CSATF: 1, OSHA: 1, STPS: 1, DOT: 1, SCT: 1, GENERAL: 1, AMAZON: 1 };
                        while (norm.firstChild) { norm.removeChild(norm.firstChild); }
                        if (!ev.value || (!badges && !codes)) {
                            norm.textContent = '—';
                        } else {
                            if (badges) {
                                var marcos = badges.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s; });
                                marcos.forEach(function (m, i) {
                                    if (i > 0) { norm.appendChild(document.createTextNode(' ')); }
                                    var sp = document.createElement('span');
                                    if (BADGE_ENUM[m]) { sp.className = 'badge badge-' + m; } // m ∈ enum blanco
                                    sp.textContent = m; // literal, nunca interpretado
                                    norm.appendChild(sp);
                                });
                            }
                            if (codes) { norm.appendChild(document.createTextNode(' ' + codes)); } // NUNCA innerHTML
                        }
                    }
                    var dl = opt ? opt.getAttribute('data-l') : '';
                    var dc = opt ? opt.getAttribute('data-c') : '';
                    var lSel = tr.querySelector('.hz-l'), cSel = tr.querySelector('.hz-c');
                    if (lSel && dl && !lSel.value) { lSel.value = dl; }
                    if (cSel && dc && !cSel.value) { cSel.value = dc; }
                    refreshRow(tr, true);
                });
            }
            var del = tr.querySelector('.hz-del');
            if (del) del.addEventListener('click', function () {
                if (body.querySelectorAll('.hz-row').length > 1) {
                    tr.remove();
                } else {
                    tr.querySelectorAll('input, select').forEach(function (el) { el.value = ''; });
                    refreshRow(tr, true);
                }
            });
            refreshRow(tr, false);
        }
        body.querySelectorAll('.hz-row').forEach(wire);

        // ── (Paso 1b) Filtro GLOBAL por marco: intersecta la VISTA del typeahead de TODAS
        //    las filas de peligro. setFacet NUNCA muta el valor elegido (si el evento queda
        //    fuera del filtro, se conserva). NO-OP seguro si el typeahead aún no montó. ──
        var facetRow = document.getElementById('hz-facets');
        var activeFrames = [];
        function applyFacetsAll(root) {
            if (!window.CCTypeahead || !window.CCTypeahead.setFacet) { return; }
            (root || body).querySelectorAll('select.hz-event').forEach(function (sel) {
                window.CCTypeahead.setFacet(sel, activeFrames);
            });
        }
        if (facetRow) {
            facetRow.addEventListener('click', function (e) {
                var chip = e.target && e.target.closest ? e.target.closest('.hzf-chip') : null;
                if (!chip) { return; }
                var pressed = chip.getAttribute('aria-pressed') === 'true';
                chip.setAttribute('aria-pressed', pressed ? 'false' : 'true');
                activeFrames = [];
                facetRow.querySelectorAll('.hzf-chip[aria-pressed="true"]').forEach(function (ch) {
                    activeFrames.push(ch.getAttribute('data-marco'));
                });
                applyFacetsAll(body);
            });
        }

        var addBtn = document.getElementById('hz-add');
        var tpl = document.getElementById('hz-template');
        if (addBtn && tpl && tpl.content) {
            addBtn.addEventListener('click', function () {
                body.appendChild(tpl.content.cloneNode(true));
                var rows = body.querySelectorAll('.hz-row');
                var newRow = rows[rows.length - 1];
                wire(newRow);
                if (window.CCTypeahead) { window.CCTypeahead.enhanceAll(newRow); }
                // Propaga el filtro activo a la .hz-event recién mejorada (clones).
                if (activeFrames.length) { applyFacetsAll(newRow); }
                contarSinEvento();
            });
        }

        // (2026-08-01 · captura fluida) El selector por ACTIVIDAD agrega una fila con el
        // evento elegido y su control/norma pre-propuestos. Reusa el mismo template y wire().
        function addRowForEvent(ev) {
            if (!tpl || !tpl.content) { return; }
            body.appendChild(tpl.content.cloneNode(true));
            var rows = body.querySelectorAll('.hz-row');
            var tr = rows[rows.length - 1];
            wire(tr);
            var sel = tr.querySelector('.hz-event');
            if (sel && ev && ev.id) { sel.value = String(ev.id); }
            if (window.CCTypeahead) { window.CCTypeahead.enhanceAll(tr); }
            // change → norma + prob/cons sugeridas (handler existente de wire()).
            if (sel && ev && ev.id) { sel.dispatchEvent(new Event('change', { bubbles: true })); }
            // Paso 3: control PRE-PROPUESTO (editable) sólo si el evento lo trae y está vacío.
            // Vacío = vacío: nunca texto inventado.
            var ctrl = tr.querySelector('input[name="hz_control[]"]');
            if (ctrl && ev && ev.control && !ctrl.value) { ctrl.value = ev.control; }
            if (activeFrames.length) { applyFacetsAll(tr); }
            contarSinEvento();
            if (tr.scrollIntoView) { tr.scrollIntoView({ block: 'nearest' }); }
        }
        var pickerRoot = document.getElementById('hzpick-scouting');
        if (pickerRoot) {
            pickerRoot.addEventListener('cc:hazard-pick', function (e) {
                if (e.detail && e.detail.event) { addRowForEvent(e.detail.event); }
            });
        }

        // (2026-07-24) Cuenta las filas CON contenido pero SIN evento del catálogo. Sólo cuentan
        // las que llevan algo escrito: una fila recién agregada y todavía vacía no es un hueco,
        // es una fila que se está llenando — avisar ahí sería ruido y enseñaría a ignorar el aviso.
        function contarSinEvento() {
            var caja = document.getElementById('hz-sin-evento');
            if (!caja) { return; }
            var n = 0;
            body.querySelectorAll('.hz-row').forEach(function (tr) {
                var ev = tr.querySelector('.hz-event');
                if (!ev || ev.value) { return; }
                var conContenido = false;
                tr.querySelectorAll('input[type="text"], select').forEach(function (c) {
                    if (c !== ev && c.value && String(c.value).trim() !== '') { conContenido = true; }
                });
                if (conContenido) { n++; }
            });
            if (n === 0) {
                caja.classList.add('d-none');
                return;
            }
            document.getElementById('hz-sin-evento-n').textContent = n;
            document.getElementById('hz-sin-evento-txt').textContent =
                n === 1 ? 'peligro sin evento del catálogo.' : 'peligros sin evento del catálogo.';
            caja.classList.remove('d-none');
        }
        body.addEventListener('change', contarSinEvento);
        body.addEventListener('input', contarSinEvento);
        body.addEventListener('click', function (e) {
            // Quitar una fila también cambia la cuenta; el borrado ocurre en otro handler,
            // así que se recuenta al siguiente tick.
            if (e.target && e.target.classList && e.target.classList.contains('hz-del')) {
                setTimeout(contarSinEvento, 0);
            }
        });
        contarSinEvento();
    })();

    // Toggle de la matriz de riesgo de referencia (sin depender de Bootstrap JS).
    (function () {
        var btn = document.getElementById('rmx-toggle'), ref = document.getElementById('rmx-ref');
        if (!btn || !ref) return;
        btn.addEventListener('click', function () {
            var open = ref.style.display !== 'none';
            ref.style.display = open ? 'none' : 'block';
            btn.textContent = open ? 'Ver matriz de riesgo' : 'Ocultar matriz';
        });
    })();

    // ====== Subida de imágenes: optimización en el navegador + galería múltiple ======
    (function () {
        var LIMIT = 12 * 1024 * 1024; // 12 MB (debe coincidir con la validación del backend)

        // ¿El navegador puede construir un FileList a mano? (necesario para acumular/quitar)
        var canDT = false;
        try { var _probe = new DataTransfer(); void _probe.items; canDT = true; } catch (e) { canDT = false; }

        // Reduce peso/dimensiones de una imagen en el navegador. Best-effort: ante CUALQUIER
        // problema (o navegador viejo) devuelve el archivo ORIGINAL, así nunca rompe la subida.
        function processFile(file) {
            return new Promise(function (resolve) {
                if (!file || !file.type || file.type.indexOf('image/') !== 0 || file.type === 'image/gif') {
                    return resolve(file); // no-imagen o GIF (posible animación) → sin tocar
                }
                if (typeof createImageBitmap !== 'function') { return resolve(file); }
                var p;
                try { p = createImageBitmap(file, { imageOrientation: 'from-image' }); }
                catch (e) { p = createImageBitmap(file); } // navegadores sin la opción de orientación
                Promise.resolve(p).then(function (bmp) {
                    var MAX = 2200, w = bmp.width, h = bmp.height;
                    var big = Math.max(w, h) > MAX;
                    // Ya es chica y liviana → no vale la pena recomprimir.
                    if (!big && file.size <= 2 * 1024 * 1024) { if (bmp.close) bmp.close(); return resolve(file); }
                    var scale = big ? MAX / Math.max(w, h) : 1;
                    var nw = Math.round(w * scale), nh = Math.round(h * scale);
                    var canvas = document.createElement('canvas');
                    canvas.width = nw; canvas.height = nh;
                    canvas.getContext('2d').drawImage(bmp, 0, 0, nw, nh);
                    if (bmp.close) bmp.close();
                    canvas.toBlob(function (blob) {
                        if (!blob || blob.size >= file.size) { return resolve(file); } // no mejoró
                        var base = file.name.replace(/\.[^.]+$/, '');
                        try {
                            resolve(new File([blob], base + '.jpg', { type: 'image/jpeg', lastModified: file.lastModified }));
                        } catch (e2) { resolve(file); }
                    }, 'image/jpeg', 0.82);
                }, function () { resolve(file); });
            });
        }

        function fmtSize(bytes) {
            if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
            return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        }

        // ---- Imagen principal: solo optimizar (un archivo) ----
        var mainInput = document.getElementById('main-image-input');
        if (mainInput && canDT) {
            var mainBusy = false;
            mainInput.addEventListener('change', function () {
                if (mainBusy) return;
                var f = mainInput.files && mainInput.files[0];
                if (!f) return;
                processFile(f).then(function (out) {
                    if (out === f) return; // sin cambios
                    var dt = new DataTransfer();
                    dt.items.add(out);
                    mainBusy = true; mainInput.files = dt.files; mainBusy = false;
                });
            });
        }

        // ---- Imágenes adicionales: acumular, miniaturas y quitar ----
        var aiInput = document.getElementById('ai-input');
        var aiAdd   = document.getElementById('ai-add');
        var aiGrid  = document.getElementById('ai-grid');
        var aiCount = document.getElementById('ai-count');
        var aiHelp  = document.getElementById('ai-help');

        if (aiInput && aiAdd && canDT) {
            var store = new DataTransfer(); // acumulador real que alimenta al <input name="additional_images[]">
            var seen  = {};                 // dedupe por nombre+tamaño
            var captions = [];              // pie de foto por archivo, índice-alineado con store.files

            // Mejora progresiva: ocultar el input plano, mostrar botón + ayuda.
            aiInput.classList.remove('form-control');
            aiInput.classList.add('d-none');
            aiAdd.style.display = 'inline-block';
            if (aiHelp) aiHelp.style.display = 'block';

            function keyOf(f) { return f.name + '|' + f.size; }
            function syncInput() { aiInput.files = store.files; }

            function render() {
                aiGrid.innerHTML = '';
                var files = Array.prototype.slice.call(store.files);
                aiCount.textContent = files.length
                    ? files.length + (files.length === 1 ? ' imagen lista' : ' imágenes listas')
                    : '';
                files.forEach(function (f, i) {
                    var cell = document.createElement('div'); cell.className = 'ai-cell';

                    var thumb = document.createElement('div'); thumb.className = 'ai-thumb';
                    var img = document.createElement('img');
                    var url = URL.createObjectURL(f);
                    img.src = url; img.onload = function () { URL.revokeObjectURL(url); };
                    var sz = document.createElement('span'); sz.className = 'ai-sz'; sz.textContent = fmtSize(f.size);
                    if (f.size > LIMIT) { sz.classList.add('ai-over'); sz.textContent += ' · muy grande'; }
                    var rm = document.createElement('button');
                    rm.type = 'button'; rm.className = 'ai-rm'; rm.innerHTML = '&times;'; rm.title = 'Quitar';
                    rm.addEventListener('click', function () { removeAt(i); });
                    thumb.appendChild(img); thumb.appendChild(sz); thumb.appendChild(rm);

                    // Pie de foto: name índice-alineado con additional_images[] (mismo orden del DOM).
                    var cap = document.createElement('input');
                    cap.type = 'text'; cap.name = 'additional_images_captions[]';
                    cap.className = 'form-control form-control-sm ai-cap mt-1';
                    cap.maxLength = 300; cap.placeholder = 'Pie de foto (hallazgo / acción)…';
                    cap.value = captions[i] || '';
                    cap.addEventListener('input', function () { captions[i] = cap.value; });

                    cell.appendChild(thumb); cell.appendChild(cap);
                    aiGrid.appendChild(cell);
                });
            }

            function removeAt(i) {
                var files = Array.prototype.slice.call(store.files);
                var removed = files.splice(i, 1)[0];
                if (removed) delete seen[keyOf(removed)];
                captions.splice(i, 1);
                store.items.clear();
                files.forEach(function (f) { store.items.add(f); });
                syncInput(); render();
            }

            var busy = false;
            aiAdd.addEventListener('click', function () { aiInput.click(); });
            aiInput.addEventListener('change', function () {
                if (busy) return; // ignora eventos disparados por nuestra propia reasignación
                var picked = Array.prototype.slice.call(aiInput.files || []);
                if (!picked.length) return;
                aiAdd.disabled = true;
                var chain = Promise.resolve();
                picked.forEach(function (f) {
                    chain = chain.then(function () {
                        return processFile(f).then(function (out) {
                            var k = keyOf(out);
                            if (!seen[k]) { seen[k] = true; store.items.add(out); captions.push(''); }
                        });
                    });
                });
                chain.then(function () {
                    busy = true; syncInput(); busy = false;
                    aiAdd.disabled = false;
                    render();
                });
            });
        }

        // ---- Existentes (edición): quitar una imagen ya guardada (fuera del gate canDT
        //      para que funcione aunque el uploader avanzado no se active). ----
        var aiExisting = document.getElementById('ai-existing');
        if (aiExisting) {
            aiExisting.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest ? e.target.closest('.ai-ex-rm') : null;
                if (!btn) return;
                var cell = btn.closest ? btn.closest('.ai-cell') : null;
                if (cell && cell.parentNode) { cell.parentNode.removeChild(cell); }
            });
        }
    })();
    // La lógica GPS + hospitales vive en public/js/crewcare-geo.js (incluido por el parcial _geo-capture).

    // (2026-07-15) Tras un error de validación: lleva al primer campo marcado y enfócalo,
    // para no dejar al usuario adivinando dónde está el problema en un form largo.
    (function () {
        var first = document.querySelector('.is-invalid') || document.querySelector('.alert-danger');
        if (!first) return;
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (first.classList.contains('is-invalid') && typeof first.focus === 'function') {
            try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
        }
    })();
</script>
