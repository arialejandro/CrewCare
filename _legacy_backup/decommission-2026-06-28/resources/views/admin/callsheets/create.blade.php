@extends('layouts.app')
@section('title', 'Nuevo Llamado - CrewCare')

@section('content')
<div class="container-fluid mt-4 mb-5">

    <div class="row mb-4 align-items-center">
        <div class="col-12">
            <a href="{{ route('call_sheets.index') }}" class="text-decoration-none text-muted small mb-2 d-inline-block">
                <i class="fas fa-arrow-left me-1"></i> Volver a Llamados
            </a>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">
                <i class="fas fa-bullhorn text-primary me-2"></i>Nuevo Llamado del Día
            </h1>
            <p class="text-muted small mb-0">El llamado adjunta automáticamente los boletines de seguridad de los riesgos del día.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger shadow-sm">
            <strong>Revisa el formulario:</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('call_sheets.store') }}" method="POST">
        @csrf

        {{-- ===== 1. METADATOS ===== --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold text-uppercase text-secondary" style="font-size: 0.8rem;">
                <i class="fas fa-info-circle me-1"></i> 1. Metadatos
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Producción</label>
                        <select name="production_id" class="form-select">
                            <option value="">— Sin producción —</option>
                            @foreach($productions as $production)
                                <option value="{{ $production->id }}" {{ old('production_id') == $production->id ? 'selected' : '' }}>
                                    {{ $production->name }}@if($production->code) ({{ $production->code }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Daily Report (auto-jala sus riesgos)</label>
                        <select name="daily_report_id" class="form-select border-warning">
                            <option value="">— No vincular —</option>
                            @foreach($dailyReports as $dr)
                                <option value="{{ $dr->id }}" {{ old('daily_report_id') == $dr->id ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($dr->report_date)->format('d/m/Y') }} — {{ $dr->location_name }} (Día {{ $dr->shoot_day }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">Al seleccionar un daily report, sus riesgos (logs) se agregan al llamado.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Título</label>
                        <input type="text" name="title" class="form-control" maxlength="150" value="{{ old('title') }}" placeholder="Ej: Llamado Día 5 — Escena Persecución">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Fecha del llamado *</label>
                        <input type="date" name="sheet_date" class="form-control" value="{{ old('sheet_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Shoot Day</label>
                        <input type="text" name="shoot_day" class="form-control" maxlength="30" value="{{ old('shoot_day') }}" placeholder="Ej: 5">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">General Call</label>
                        <input type="time" name="general_call" class="form-control" value="{{ old('general_call') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== 2. LOCACIÓN ===== --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold text-uppercase text-secondary" style="font-size: 0.8rem;">
                <i class="fas fa-map-marker-alt me-1"></i> 2. Locación
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Nombre de locación</label>
                        <input type="text" name="location_name" class="form-control" maxlength="255" value="{{ old('location_name') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Dirección</label>
                        <input type="text" name="location_address" class="form-control" maxlength="500" value="{{ old('location_address') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Set (INT/EXT)</label>
                        <select name="set_setting" class="form-select">
                            <option value="">—</option>
                            <option value="INT" {{ old('set_setting') == 'INT' ? 'selected' : '' }}>INT</option>
                            <option value="EXT" {{ old('set_setting') == 'EXT' ? 'selected' : '' }}>EXT</option>
                            <option value="INT/EXT" {{ old('set_setting') == 'INT/EXT' ? 'selected' : '' }}>INT/EXT</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Momento del día</label>
                        <select name="day_part" class="form-select">
                            <option value="">—</option>
                            <option value="DAY" {{ old('day_part') == 'DAY' ? 'selected' : '' }}>DÍA</option>
                            <option value="NIGHT" {{ old('day_part') == 'NIGHT' ? 'selected' : '' }}>NOCHE</option>
                            <option value="DAWN" {{ old('day_part') == 'DAWN' ? 'selected' : '' }}>AMANECER</option>
                            <option value="DUSK" {{ old('day_part') == 'DUSK' ? 'selected' : '' }}>ATARDECER</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Clima</label>
                        <input type="text" name="weather_note" class="form-control" maxlength="255" value="{{ old('weather_note') }}" placeholder="Ej: Soleado, 24°C, viento ligero">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Amanecer</label>
                        <input type="time" name="sunrise" class="form-control" value="{{ old('sunrise') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Atardecer</label>
                        <input type="time" name="sunset" class="form-control" value="{{ old('sunset') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== 3. EMERGENCIA ===== --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold text-uppercase text-danger" style="font-size: 0.8rem;">
                <i class="fas fa-ambulance me-1"></i> 3. Emergencia
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Hospital más cercano</label>
                        <input type="text" name="nearest_hospital" class="form-control" maxlength="255" value="{{ old('nearest_hospital') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Dirección del hospital</label>
                        <input type="text" name="hospital_address" class="form-control" maxlength="500" value="{{ old('hospital_address') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Compañía de ambulancia</label>
                        <input type="text" name="ambulance_company" class="form-control" maxlength="255" value="{{ old('ambulance_company') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Teléfono de emergencia</label>
                        <input type="text" name="emergency_phone" class="form-control" maxlength="50" value="{{ old('emergency_phone') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Punto de reunión (assembly)</label>
                        <input type="text" name="assembly_point" class="form-control" maxlength="255" value="{{ old('assembly_point') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== 4. RIESGOS DEL DÍA (AUTO-ATTACH) ===== --}}
        <div class="card shadow-sm border-0 mb-4 border-start border-danger border-4">
            <div class="card-header bg-white fw-bold text-uppercase text-danger" style="font-size: 0.8rem;">
                <i class="fas fa-shield-alt me-1"></i> 4. Riesgos del día — Boletines de Seguridad
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Selecciona las categorías de riesgo aplicables. Por cada una se adjuntará
                    <strong>automáticamente</strong> su boletín normativo (CSATF / OSHA / STPS).
                    Si vinculaste un Daily Report arriba, sus riesgos se agregan también.
                </p>
                <div class="row g-2">
                    @forelse($standards as $standard)
                    <div class="col-md-6 col-lg-4">
                        <div class="form-check border rounded p-2 ps-4 h-100">
                            <input class="form-check-input" type="checkbox" name="category_name[]"
                                   value="{{ $standard->category_name }}"
                                   id="risk_{{ $standard->id }}"
                                   {{ (is_array(old('category_name')) && in_array($standard->category_name, old('category_name'))) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="risk_{{ $standard->id }}">
                                <span class="fw-bold d-block">{{ $standard->category_name }}</span>
                                <span class="badge bg-secondary">{{ $standard->regulation_badge }}</span>
                                <span class="text-muted">{{ $standard->regulation_code }}</span>
                            </label>
                        </div>
                    </div>
                    @empty
                    <div class="col-12">
                        <div class="alert alert-warning mb-0 small">No hay categorías de seguridad en el catálogo todavía.</div>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ===== 5. NOTAS DE SEGURIDAD ===== --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold text-uppercase text-secondary" style="font-size: 0.8rem;">
                <i class="fas fa-sticky-note me-1"></i> 5. Notas de seguridad
            </div>
            <div class="card-body">
                <textarea name="safety_notes" class="form-control" rows="3" placeholder="Indicaciones especiales de seguridad para la jornada...">{{ old('safety_notes') }}</textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-5">
            <a href="{{ route('call_sheets.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary fw-bold shadow-sm">
                <i class="fas fa-save me-1"></i> Crear Llamado
            </button>
        </div>
    </form>

</div>
@endsection
