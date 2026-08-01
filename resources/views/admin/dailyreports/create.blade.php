@extends('layouts.app')
@section('title', 'Nuevo Daily Report - ' . ($branding['brand_name'] ?? 'CrewCare'))
@section('content')

<div class="container-fluid mt-5 no-print" style="margin-bottom: 5rem;">
    <div class="row mb-4 align-items-center">
        <div class="col-8">
            <h1 class="h3 mb-0 fw-bold" style="color: var(--text);">Daily Safety Report</h1>
            <p class="cc-muted small">Crear nuevo encabezado del día</p>
        </div>
        <div class="col-4 text-end">
            <a href="{{ route('daily_reports.index') }}" class="btn btn-outline-secondary btn-sm">
                @include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico']) Volver
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">

            @if ($errors->any())
    <div class="alert alert-danger shadow-sm mb-4">
        <strong class="fw-bold">@include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico']) Revisa los siguientes errores:</strong>
        <ul class="mb-0 mt-2 text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

            <form action="{{ route('daily_reports.store') }}" method="POST" enctype="multipart/form-data" data-cc-autosave="daily-report">
                @csrf

                @php
                    // Repoblado tras un rebote de validación (ahora el EPP puede fallar → módulo 8).
                    $oldFactors = old('day_risk_factors', []);
                    if (!is_array($oldFactors)) { $oldFactors = []; }
                    $oldPpe = old('required_ppe', []);
                    if (!is_array($oldPpe)) { $oldPpe = []; }
                    // safety_meeting_topics: checkboxes (antes texto libre). old() puede volver como
                    // array (rebote) o, para datos viejos, string CSV → normalizamos a array.
                    $oldTopics = old('safety_meeting_topics', []);
                    if (is_string($oldTopics)) { $oldTopics = array_filter(array_map('trim', explode(',', $oldTopics))); }
                    if (!is_array($oldTopics)) { $oldTopics = []; }
                @endphp

                <h5 class="fw-bold text-primary mb-3 border-bottom pb-2">1. Metadatos del Llamado</h5>

                {{-- (2026-07-25) UN SOLO CAMPO DE LOCACIÓN. Antes aquí vivía un <select> de scouting
                     ("Locación scouteada") separado del texto libre "Locación" de más abajo. Elegir el
                     scouting no es decisión del safety —el sistema RECONOCE la locación—, así que se
                     fusionaron: el único campo "Locación" (fila de abajo, con datalist de las locaciones
                     ya scouteadas) acepta texto libre y, cuando el nombre coincide con una locación
                     scouteada, el SERVIDOR amarra scouting_report_id por NOMBRE (StoreDailyReportRequest::
                     prepareForValidation → ScoutingLocator::idByLocationName, hermano por-nombre del
                     nearestId por-GPS de los gemelos) y hereda hospital/ambulancia EN SILENCIO sólo en
                     los vacíos. Sin coincidencia → sin vínculo, captura manual (arranque en frío). El
                     arrastre del DSR anterior sigue retirado. --}}

                <div class="row g-3 mb-4">
                    <div class="col-md-4 col-6">
                        <label class="form-label small fw-bold">Fecha *</label>
                        <input type="date" name="report_date" class="form-control" value="{{ old('report_date', date('Y-m-d')) }}" required>
                    </div>
                    {{-- (2026-07-25) DÍA DE RODAJE = CAPTURA MANUAL PRELLENADA (arranque en frío).
                         Nace con el número que deriva la fecha (Día N, o 1 en frío sin producción); el
                         JS de abajo lo re-sugiere al mover la fecha y deja de pisarlo en cuanto se
                         teclea a mano. El servidor RESPETA lo que llegue (resolveShootDay). --}}
                    <div class="col-md-4 col-6">
                        <label class="form-label small fw-bold">Día de rodaje *</label>
                        <input type="number" step="1" name="shoot_day" id="cc-dia-input" class="form-control"
                               value="{{ old('shoot_day', $diaSugerido ?? 1) }}" required>
                        <div class="form-text" id="cc-dia-hint">Se sugiere por la fecha (la prep va en negativo, el domingo no cuenta). Puedes ajustarlo.</div>
                    </div>
                    <div class="col-md-4 col-12">
                        <label class="form-label small fw-bold">Call Time</label>
                        <input type="time" name="call_time" class="form-control" value="{{ old('call_time') }}">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4 col-6">
                        <label class="form-label small fw-bold">Entorno *</label>
                        <select name="slug_setting" class="form-select" required>
                            <option value="INT." {{ old('slug_setting') == 'INT.' ? 'selected' : '' }}>INT.</option>
                            <option value="EXT." {{ old('slug_setting') == 'EXT.' ? 'selected' : '' }}>EXT.</option>
                            <option value="INT./EXT." {{ old('slug_setting') == 'INT./EXT.' ? 'selected' : '' }}>INT./EXT.</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label small fw-bold">Luz *</label>
                        <select name="slug_time" class="form-select" required>
                            <option value="DÍA" {{ old('slug_time') == 'DÍA' ? 'selected' : '' }}>DÍA</option>
                            <option value="NOCHE" {{ old('slug_time') == 'NOCHE' ? 'selected' : '' }}>NOCHE</option>
                            <option value="AMANECER" {{ old('slug_time') == 'AMANECER' ? 'selected' : '' }}>AMANECER</option>
                            <option value="ATARDECER" {{ old('slug_time') == 'ATARDECER' ? 'selected' : '' }}>ATARDECER</option>
                            <option value="MIXTO" {{ old('slug_time') == 'MIXTO' ? 'selected' : '' }}>MIXTO</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-12">
                        <label class="form-label small fw-bold">Locación *</label>
                        {{-- Campo ÚNICO: texto libre + datalist de locaciones ya scouteadas. Escribir o
                             elegir una reconocida hereda hospital/ambulancia y ata el scouting (server-side,
                             ver el comentario de la sección 1 y el <script> del final). --}}
                        <input type="text" name="location_name" id="cc-location-input" class="form-control"
                               list="cc-locaciones-list" autocomplete="off"
                               placeholder="Ej: Teatro de la Ciudad" value="{{ old('location_name') }}" required>
                        @if($scoutings->isNotEmpty())
                            <datalist id="cc-locaciones-list">
                                @foreach($scoutings->unique('location_name') as $s)
                                    <option value="{{ $s->location_name }}"></option>
                                @endforeach
                            </datalist>
                            <div class="form-text small">Escribe la locación o elígela de la lista.</div>
                        @endif
                    </div>
                </div>

                <h5 class="fw-bold text-primary mb-3 border-bottom pb-2">2. Clima y Crew</h5>
                
                <div class="row g-3 mb-3">
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">Total Crew *</label>
                        <input type="number" name="crew_count" class="form-control" placeholder="Ej: 150" value="{{ old('crew_count') }}" required>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">Clima *</label>
                        {{-- Los VALUES casan con las palabras clave de StoreDailyReportRequest::isExtremeWeather()
                             (rainy/storm/hail/snow/windy/extreme_heat) → esas condiciones OBLIGAN a declarar EPP. --}}
                        <select name="weather_condition" class="form-select" required>
                            <option value="sunny" {{ old('weather_condition') == 'sunny' ? 'selected' : '' }}>☀️ Soleado</option>
                            <option value="cloudy" {{ old('weather_condition') == 'cloudy' ? 'selected' : '' }}>☁️ Nublado</option>
                            <option value="rainy" {{ old('weather_condition') == 'rainy' ? 'selected' : '' }}>🌧️ Lluvioso</option>
                            <option value="storm" {{ old('weather_condition') == 'storm' ? 'selected' : '' }}>⛈️ Tormenta eléctrica</option>
                            <option value="hail" {{ old('weather_condition') == 'hail' ? 'selected' : '' }}>🌨️ Granizo</option>
                            <option value="snow" {{ old('weather_condition') == 'snow' ? 'selected' : '' }}>❄️ Nieve</option>
                            <option value="windy" {{ old('weather_condition') == 'windy' ? 'selected' : '' }}>💨 Viento fuerte</option>
                            <option value="extreme_heat" {{ old('weather_condition') == 'extreme_heat' ? 'selected' : '' }}>🥵 Calor extremo</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">Temp. Min (°C)</label>
                        <input type="number" name="weather_min_temp" class="form-control" placeholder="Ej: 12" value="{{ old('weather_min_temp') }}">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">Temp. Max (°C)</label>
                        <input type="number" name="weather_max_temp" class="form-control" placeholder="Ej: 28" value="{{ old('weather_max_temp') }}">
                    </div>
                </div>

                {{-- MÓDULO 9 (ambientales): humedad, viento e índice de calor. El índice de calor alto
                     (>= 39°) y la temp. máx (>= 38°) además DISPARAN la obligatoriedad de EPP (módulo 8). --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4 col-6">
                        <label class="form-label small fw-bold">Humedad (%)</label>
                        <input type="number" name="humidity" class="form-control" min="0" max="100" placeholder="Ej: 65" value="{{ old('humidity') }}">
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label small fw-bold">Velocidad del viento (km/h)</label>
                        <input type="number" name="wind_speed" step="0.1" min="0" class="form-control" placeholder="Ej: 18" value="{{ old('wind_speed') }}">
                    </div>
                    <div class="col-md-4 col-12">
                        <label class="form-label small fw-bold">Índice de calor (°C)</label>
                        <input type="number" name="heat_index" step="0.1" class="form-control" placeholder="Ej: 41" value="{{ old('heat_index') }}">
                    </div>
                </div>

                {{-- (2026-07-22) EL SAFETY MEETING TIENE SECCIÓN PROPIA. Antes vivía dentro de
                     "Protocolos de Emergencia", y son dos cosas distintas: la junta es el RITUAL
                     DE INICIO del día (ocurre, tiene hora, tiene temas y deja evidencia), mientras
                     que el hospital y la ambulancia son INFRAESTRUCTURA que está ahí pase lo que
                     pase. Mezclarlas escondía la junta dentro de un bloque logístico. --}}
                <h5 class="fw-bold text-primary mb-3 border-bottom pb-2">3. Safety Meeting</h5>

                {{-- (2026-07-21) La junta se DECLARA. Antes solo había una hora suelta, así que
                     no existía forma de decir que no se realizó — y cuando los temas eran texto
                     libre alguien lo escribía ahí, produciendo en el documento la contradicción
                     "No hubo safety meeting · 14:00 HRS". Ahora se declara y el acta es coherente:
                     los estudios internacionales exigen la junta, los locales muchas veces no la
                     hacen, y el mismo documento sirve a los dos SIN mentir en ninguno. --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-4 col-12">
                        <label class="form-label small fw-bold">{{ __('reports.dsr_meeting_held_label') }}</label>
                        <select name="safety_meeting_held" class="form-select">
                            <option value="1" {{ old('safety_meeting_held', '1') == '1' ? 'selected' : '' }}>{{ __('reports.dsr_meeting_held_yes') }}</option>
                            <option value="0" {{ old('safety_meeting_held') === '0' ? 'selected' : '' }}>{{ __('reports.dsr_meeting_held_no') }}</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-12">
                        <label class="form-label small fw-bold">Safety Meeting (Hora)</label>
                        <input type="time" name="safety_meeting_time" class="form-control" value="{{ old('safety_meeting_time') }}">
                    </div>
                    <div class="col-md-4 col-12">
                        <label class="form-label small fw-bold">{{ __('reports.dsr_meeting_photo_label') }}</label>
                        <input type="file" name="safety_meeting_photo" class="form-control" accept="image/*" capture="environment">
                        <div class="form-text small">{{ __('reports.dsr_meeting_photo_hint') }}</div>
                    </div>
                </div>

                {{-- TEMAS TRATADOS — derivados del CATÁLOGO DE EVENTOS (2026-07-22).
                     Antes eran 9 casillas fijas en lenguaje de seguridad industrial genérica
                     ("espacios confinados", "manejo de químicos"): vocabulario de fábrica, no
                     de set. Ahora salen de las categorías del catálogo, que ya hablan de cine
                     (stunts vehiculares, wire work, pirotecnia, armas de fuego, trabajo en agua,
                     camera car, menores…). Ventaja de fondo: es UNA sola fuente de vocabulario
                     — si mañana se enriquece el catálogo, los temas del meeting se enriquecen
                     solos, sin una segunda lista que se desincronice.

                     ⚠ SE GUARDA LA CLAVE, NO LA ETIQUETA: safety_meeting_topics es varchar(255)
                       y la conexión está en modo estricto. Con etiquetas, 8 temas de longitud
                       media suman ~275 caracteres → error 1406 y pantalla de error. Con claves,
                       caben ~21 temas. Además, guardar la clave hace que cambiar una etiqueta
                       no reescriba el histórico. La vista traduce clave → etiqueta al pintar. --}}
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Temas Tratados</label>
                        <div class="form-text small mb-2">{{ __('reports.dsr_meeting_topics_hint') }}</div>

                        {{-- (2026-08-01 · captura fluida, Paso 5) Los ~38 temas ya no van en una
                             rejilla plana que no se recorre: se pliegan por ACTIVIDAD, a un toque.
                             Nada se marca solo; un grupo se abre solo si ya trae temas marcados (al
                             rebotar validación) para no esconder lo elegido. --}}
                        <style>
                            .smt-groups { display:flex; flex-direction:column; gap:.4rem; }
                            .smt-act { border:1px solid var(--border,#dee2e6); border-radius:10px; background:var(--surface,#fff); }
                            .smt-act > summary { list-style:none; cursor:pointer; padding:.6rem .8rem; min-height:44px;
                                display:flex; align-items:center; gap:.5rem; font-weight:600; font-size:.9rem; }
                            .smt-act > summary::-webkit-details-marker { display:none; }
                            .smt-act > summary::before { content:'▸'; color:var(--text-muted,#6c757d); transition:transform .12s ease; }
                            .smt-act[open] > summary::before { transform:rotate(90deg); }
                            .smt-count { margin-left:auto; font-size:.72rem; font-weight:700; color:var(--text-muted,#6c757d); }
                            .smt-checked { display:none; font-size:.72rem; font-weight:700; color:#0e6f6c;
                                background:color-mix(in srgb,#0e6f6c 12%,transparent); border-radius:999px; padding:.05rem .45rem; }
                            .smt-checked.on { display:inline-block; }
                            .smt-act .form-check { min-height:38px; }
                            .smt-act > div { padding:0 .8rem .7rem; }
                        </style>
                        <div class="smt-groups" id="smt-groups">
                            @foreach(\App\Support\HazardActivities::categoriesGrouped() as $actKey => $grp)
                                @php $grpHasChecked = (bool) array_intersect(array_keys($grp['categories']), (array) $oldTopics); @endphp
                                <details class="smt-act" @if($grpHasChecked) open @endif>
                                    <summary>
                                        <span>{{ $grp['label'] }}</span>
                                        <span class="smt-checked"></span>
                                        <span class="smt-count">{{ count($grp['categories']) }}</span>
                                    </summary>
                                    <div class="row g-1">
                                        @foreach($grp['categories'] as $key => $label)
                                            <div class="col-lg-4 col-md-6 col-12">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="safety_meeting_topics[]" value="{{ $key }}" id="smt_{{ $key }}" {{ in_array($key, $oldTopics) ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="smt_{{ $key }}" title="{{ $label }}">{{ $label }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                        <script>
                            (function () {
                                var wrap = document.getElementById('smt-groups');
                                if (!wrap) return;
                                function refresh(det) {
                                    var n = det.querySelectorAll('input[type="checkbox"]:checked').length;
                                    var b = det.querySelector('.smt-checked');
                                    if (b) { b.textContent = n + ' marcado' + (n === 1 ? '' : 's'); b.classList.toggle('on', n > 0); }
                                }
                                wrap.querySelectorAll('details.smt-act').forEach(function (det) {
                                    refresh(det);
                                    det.addEventListener('change', function () { refresh(det); });
                                });
                            })();
                        </script>
                    </div>
                </div>

                <h5 class="fw-bold text-primary mb-3 border-bottom pb-2">4. Protocolos de Emergencia</h5>

                <div class="row g-3 mb-3">
                    {{-- (2026-07-25) HOSPITAL DESIGNADO — OBLIGATORIO y con CADENA DE ORIGEN. Se RETIRÓ
                         el arrastre del DSR anterior (heredaba el hospital de otra locación). Ahora se
                         hereda del SCOUTING elegido arriba (paso 1), o se busca por las coordenadas de
                         ESE scouting con el buscador de abajo (paso 2), o se teclea (paso 3, frío).
                         Prellenado por JS al elegir scouting; queda EDITABLE — lo escrito es lo que se
                         guarda. Obligatorio sólo en el alta; el cierre de día no lo revalida. --}}
                    <div class="col-md-4 col-12">
                        <label class="form-label small fw-bold">Hospital Designado *</label>
                        <input type="text" name="nearest_hospital" id="cc-hospital-input" class="form-control" placeholder="Nombre y Tiempo (Ej: Hosp. ABC - 15 min)" value="{{ old('nearest_hospital') }}" required>
                    </div>
                    <div class="col-md-4 col-12">
                        <label class="form-label small fw-bold">Compañía Ambulancia</label>
                        <input type="text" name="ambulance_company" class="form-control" placeholder="Ej: LifeOne" value="{{ old('ambulance_company') }}">
                    </div>
                    <div class="col-md-4 col-12">
                        <label class="form-label small fw-bold">Médico / Paramédico en Set</label>
                        <input type="text" name="medic_name" class="form-control" placeholder="Ej: Gabriel Galicia" value="{{ old('medic_name') }}">
                    </div>
                </div>

                {{-- (2026-07-25) CADENA DE ORIGEN — paso 2: buscador de hospitales cercanos.
                     Reusa TAL CUAL public/js/crewcare-geo.js (initHospitalFinder), el mismo del Scouting:
                     lista hospitales PRIVADOS primero y llena "Hospital Designado". El DSR es el único
                     reporte de campo SIN GPS propio: sus ÚNICAS coordenadas salen del SCOUTING elegido
                     arriba. Por eso el buscador SÓLO aparece cuando ese scouting trae coordenadas; sin
                     scouting no hay punto de partida y la captura es manual (paso 3). Los lat/lng viven
                     en hidden con id (NO name) → no se envían ni se guardan; son sólo para el buscador. --}}
                <input type="hidden" id="cc-dsr-lat" value="">
                <input type="hidden" id="cc-dsr-lng" value="">
                <div class="mb-4" id="cc-hosp-finder-wrap" style="display:none;"
                     data-hospital-finder
                     data-lat="#cc-dsr-lat" data-lng="#cc-dsr-lng"
                     data-name-target="#cc-hospital-input">
                    <button type="button" class="btn btn-outline-secondary btn-sm js-hosp-search">
                        @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico me-1']) Buscar hospitales cercanos a la locación
                    </button>
                    <span class="js-hosp-help small text-muted d-block mt-1">Lista los hospitales cercanos (primero los privados) y llena el campo de arriba.</span>
                    <div class="list-group js-hosp-results mt-2" style="display:none;"></div>
                </div>

                <h5 class="fw-bold text-primary mb-3 border-bottom pb-2">5. Factores de Riesgo y EPP del Día</h5>

                <div class="alert alert-light border small mb-3">
                    @include('componentes._icon', ['name' => 'info', 'class' => 'cc-ico text-brand'])
                    El <strong>EPP requerido</strong> es obligatorio cuando el día implica clima extremo/lluvia
                    (índice de calor ≥ 39°, temp. máx ≥ 38°) o <strong>trabajo en altura</strong>.
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Factores de riesgo del día</label>
                    <div class="row g-2">
                        @foreach(['Trabajo en altura', 'Trabajo eléctrico', 'Espacios confinados', 'Manejo de químicos', 'Tráfico/vialidad', 'Cargas suspendidas'] as $i => $factor)
                            <div class="col-md-4 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="day_risk_factors[]" value="{{ $factor }}" id="drf{{ $i }}" {{ in_array($factor, $oldFactors) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="drf{{ $i }}">{{ $factor }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">EPP requerido</label>
                    <div class="row g-2">
                        @foreach(['Casco', 'Chaleco', 'Botas', 'Guantes', 'Lentes', 'Arnés', 'Protección auditiva', 'Cubrebocas', 'Bloqueador'] as $i => $ppe)
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="required_ppe[]" value="{{ $ppe }}" id="ppe{{ $i }}" {{ in_array($ppe, $oldPpe) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="ppe{{ $i }}">{{ $ppe }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text">Selecciona el equipo de protección personal exigido para las actividades del día.</div>
                </div>

                <h5 class="fw-bold text-primary mb-3 border-bottom pb-2">6. Cierre del Reporte</h5>

                <div class="mb-4">
                    <label class="form-label small fw-bold">Resumen Ejecutivo del Día</label>
                    <textarea name="executive_summary" class="form-control" rows="3" placeholder="Describe brevemente cómo transcurrió la seguridad en el set hoy...">{{ old('executive_summary') }}</textarea>
                    <div class="form-text">Puedes dejarlo en blanco por ahora y llenarlo al final del día.</div>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-12">
                        <label class="form-label small fw-bold">Hero Image (Portada)</label>
                        <input type="file" name="hero_image" class="form-control" accept="image/*">
                        <div class="form-text">La foto principal del set que saldrá en la cabecera.</div>
                    </div>
                    <div class="col-md-6 col-12">
                        <label class="form-label small fw-bold">Reporte elaborado por</label>
                        <input type="text" class="form-control" value="{{ auth()->user()->name ?? '' }}" readonly>
                        <div class="form-text">@include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico']) Autofirma: se registra con tu usuario (no editable).</div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold shadow-sm">
                        @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-2']) Crear Reporte y Abrir Bitácora
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

{{-- (2026-07-25) ESPEJO EN CLIENTE del contador de días (App\Support\ProductionCalendar). El campo
     es EDITABLE: re-sugiere el número al mover la fecha y lo escribe en el input HASTA que el usuario
     lo teclea a mano (bandera `manual`), entonces sólo actualiza el texto de ayuda. La cuenta que
     MANDA la respeta el servidor (resolveShootDay). ⚠ Si cambia la regla en PHP, cambiarla aquí. --}}
<script>
(function () {
    var ANCLA  = @json($calAncla ?? null);
    var FECHAS = @json($calFechas ?? []);
    var fecha  = document.querySelector('input[name="report_date"]');
    var dia    = document.getElementById('cc-dia-input');
    var hint   = document.getElementById('cc-dia-hint');
    if (!fecha || !dia) { return; }

    var manual = false;
    dia.addEventListener('input', function () { manual = true; });

    function numeroDeDia(d) {
        if (!ANCLA) { return null; }
        if (d < ANCLA) {
            var cur = new Date(d + 'T00:00:00');
            var fin = new Date(ANCLA + 'T00:00:00');
            if (isNaN(cur) || isNaN(fin)) { return null; }
            if (cur.getDay() === 0) { return null; }          // el domingo no cuenta
            // Guardarraíl: una fecha absurda (año 1900) no puede congelar el navegador.
            if ((fin - cur) / 86400000 > 3650) { return null; }
            var n = 0;
            while (cur < fin) {
                if (cur.getDay() !== 0) { n++; }
                cur.setDate(cur.getDate() + 1);
            }
            return -n;
        }
        var idx = FECHAS.indexOf(d);
        if (idx !== -1) { return idx + 1; }
        var previos = 0;
        for (var i = 0; i < FECHAS.length; i++) { if (FECHAS[i] < d) { previos++; } }
        return previos + 1;
    }

    function etiqueta(n) {
        if (n > 0) { return 'Día ' + n; }
        var a = -n;
        return a <= 6 ? 'Prep -' + a : 'Semana -' + Math.ceil(a / 6);
    }

    function sugerir(pisarValor) {
        var d = fecha.value;
        var n = d ? (ANCLA ? numeroDeDia(d) : 1) : null;
        if (n === null) {
            if (hint) { hint.textContent = d
                ? 'Ese día cae en domingo (no cuenta como día de rodaje). Ajústalo si aplica.'
                : 'Elige la fecha y se sugiere el día de rodaje.'; }
            return;
        }
        if (pisarValor && !manual) { dia.value = n; }
        if (hint) { hint.textContent = 'Sugerido por la fecha: ' + etiqueta(n) + '. Puedes ajustarlo.'; }
    }

    fecha.addEventListener('change', function () { sugerir(true); });
    fecha.addEventListener('input',  function () { sugerir(true); });
    sugerir(false);
})();
</script>

{{-- (2026-07-25) Buscador de hospitales cercanos (mismo motor que el Scouting). Auto-init por
     [data-hospital-finder]; sin GPS propio en el DSR, sólo actúa con las coordenadas del scouting. --}}
<script src="{{ asset('js/crewcare-geo.js') }}?v=4"></script>

{{-- (2026-07-25) UN SOLO CAMPO DE LOCACIÓN — reconocimiento por NOMBRE en el cliente (espejo del
     servidor, sólo UX). Al escribir/elegir una locación ya scouteada, hereda hospital (nombre + ETA)
     y ambulancia (EDITABLES) y vuelca sus coordenadas a los hidden que alimentan el buscador de
     arriba, revelándolo sólo si esa locación trae coordenadas. El SERVIDOR reconfirma y amarra el
     vínculo (prepareForValidation), así que si el JS no corre, el back igual lo ata y hereda. --}}
<script>
(function () {
    var input = document.getElementById('cc-location-input');
    if (!input) { return; }
    var hosp = document.getElementById('cc-hospital-input');
    var amb  = document.querySelector('input[name="ambulance_company"]');
    var lat  = document.getElementById('cc-dsr-lat');
    var lng  = document.getElementById('cc-dsr-lng');
    var wrap = document.getElementById('cc-hosp-finder-wrap');

    // Mapa nombre-normalizado → datos de la locación reconocida (el más reciente gana; la lista ya
    // viene ordenada id desc). Mismas columnas públicas que alimentan el datalist.
    var MAP = {};
    (@json($scoutings ?? [])).forEach(function (s) {
        var k = (s.location_name || '').trim().toLowerCase();
        if (k && !MAP[k]) { MAP[k] = s; }
    });

    // Recordamos lo que NOSOTROS prellenamos para refrescarlo/limpiarlo sin pisar lo que el safety
    // escribió a mano (si el valor sigue siendo el nuestro, es nuestro para cambiar).
    var autoHosp = null, autoAmb = null;

    function norm(v) { return (v || '').trim().toLowerCase(); }

    function apply() {
        var s = MAP[norm(input.value)] || null;

        // Coordenadas: SÓLO de la locación reconocida (el DSR no captura GPS). Alimentan y revelan
        // el buscador de hospitales de arriba.
        var dlat = s ? (s.latitude  || '') : '';
        var dlng = s ? (s.longitude || '') : '';
        if (lat) { lat.value = dlat; }
        if (lng) { lng.value = dlng; }
        if (wrap) { wrap.style.display = (dlat && dlng) ? 'block' : 'none'; }

        // Hospital heredado (nombre + ETA), EDITABLE. Sólo escribimos si el campo está vacío o si aún
        // conserva NUESTRO último prellenado (no pisamos lo que el usuario tecleó).
        var full = (s && s.nearest_hospital)
            ? s.nearest_hospital + (s.hospital_eta ? ' - ' + s.hospital_eta : '')
            : '';
        if (hosp && (hosp.value.trim() === '' || hosp.value === autoHosp)) {
            hosp.value = full;
            autoHosp = full;
        }

        var da = (s && s.ambulance_company) ? s.ambulance_company : '';
        if (amb && (amb.value.trim() === '' || amb.value === autoAmb)) {
            amb.value = da;
            autoAmb = da;
        }
    }

    input.addEventListener('input',  apply);
    input.addEventListener('change', apply);
    apply(); // carga inicial (rebote de validación): fija coords/visibilidad y respeta lo tecleado
})();
</script>
@endsection