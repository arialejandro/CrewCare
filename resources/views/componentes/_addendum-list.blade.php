{{--
    Addendums médicos (Pilar 4) — partial incluido por el orquestador en la vista
    SHOW del accidente (admin/injuryreport.blade.php). Recibe $injuryReport.

    APPEND-ONLY: estos addendums NO alteran el reporte de lesión firmado original
    ni su PDF; sólo registran cambios posteriores de diagnóstico/tratamiento. Las
    estadísticas "efectivas" del tablero se leen del ÚLTIMO addendum.

    Bootstrap 5. Guardado por el feature flag 'medical_addendum' y por la existencia
    de la tabla addendums (defensivo: prod sin el SQL no truena).
--}}
@feature('medical_addendum')
@if(\Illuminate\Support\Facades\Schema::hasTable('addendums'))
    @php
        $addendumTypeLabels = \App\Models\Addendum::typeLabels();
        $treatmentLabels = [
            'first_aid'         => __('reports.treatment_first_aid'),
            'medical_treatment' => __('reports.treatment_medical'),
            'hospitalization'   => __('reports.treatment_hospitalization'),
            'fatality'          => __('reports.treatment_fatality'),
        ];
        $addendums = $injuryReport->addendums;
    @endphp

    <div class="card shadow-sm my-4" id="addendums-medicos">
        <div class="card-header bg-white">
            <h5 class="mb-1">Addendums médicos</h5>
            <p class="text-muted small mb-0">
                Un addendum registra un cambio posterior de diagnóstico o tratamiento
                <strong>sin alterar el reporte de lesión original</strong> (que ya quedó
                firmado). El expediente base y su PDF permanecen intactos; las
                estadísticas efectivas se toman del addendum más reciente.
            </p>
        </div>

        <div class="card-body">
            @include('componentes._form-feedback')

            {{-- (a) Historial de addendums --}}
            @if($addendums->isEmpty())
                <p class="text-muted small mb-4">Aún no hay addendums para este reporte.</p>
            @else
                <ul class="list-group mb-4">
                    @foreach($addendums as $addendum)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <span class="badge bg-secondary">
                                    {{ $addendumTypeLabels[$addendum->type] ?? $addendum->type }}
                                </span>
                                <small class="text-muted">
                                    {{ optional($addendum->created_at)->format('Y-m-d H:i') }}
                                    @if($addendum->createdBy)
                                        &middot; {{ $addendum->createdBy->name }}
                                        {{-- PASO B: la cédula del médico que firma el addendum. Este
                                             es el ÚNICO punto del accidente con relación REAL a un
                                             médico (`addendums.created_by_id`); en el reporte, el
                                             médico tratante sigue siendo texto libre en `hospital`.

                                             Va con clases de Bootstrap y NO con el parcial
                                             _medic-credential-badge a propósito: ese parcial exige
                                             .cc-chip/.cc-chip-ok/.cc-chip-warn, que esta vista no
                                             define, y el resto de este archivo ya es Bootstrap puro
                                             (`badge bg-secondary` arriba). Mismo dato, idioma local. --}}
                                        @php
                                            $addCred = (\App\Models\MedicCredential::supportsCredentials()
                                                        && $addendum->createdBy->isMedic())
                                                ? $addendum->createdBy->medicCredential : null;
                                        @endphp
                                        @if($addCred)
                                            @if($addCred->isVerified())
                                                <span class="badge bg-success" title="Cédula cotejada contra el registro de la SEP.">Céd. {{ $addCred->cedula }} ✓</span>
                                            @else
                                                <span class="badge bg-warning text-dark" title="Nadie ha cotejado esta cédula contra el registro oficial.">Céd. {{ $addCred->cedula }} · sin verificar</span>
                                            @endif
                                        @endif
                                    @endif
                                </small>
                            </div>

                            <p class="mb-2 mt-2" style="white-space:pre-line;">{{ $addendum->body }}</p>

                            @php
                                $hasChanges = ($addendum->new_treatment_level !== null && $addendum->new_treatment_level !== '')
                                    || $addendum->new_is_recordable !== null
                                    || $addendum->new_days_away !== null
                                    || $addendum->new_days_restricted !== null;
                            @endphp
                            @if($hasChanges)
                                <div class="mt-1">
                                    @if($addendum->new_treatment_level !== null && $addendum->new_treatment_level !== '')
                                        <span class="badge bg-light text-dark border me-1">
                                            Nivel de tratamiento:
                                            {{ $treatmentLabels[$addendum->new_treatment_level] ?? $addendum->new_treatment_level }}
                                        </span>
                                    @endif
                                    @if($addendum->new_is_recordable !== null)
                                        <span class="badge bg-light text-dark border me-1">
                                            Registrable OSHA: {{ $addendum->new_is_recordable ? 'Sí' : 'No' }}
                                        </span>
                                    @endif
                                    @if($addendum->new_days_away !== null)
                                        <span class="badge bg-light text-dark border me-1">
                                            Días perdidos: {{ $addendum->new_days_away }}
                                        </span>
                                    @endif
                                    @if($addendum->new_days_restricted !== null)
                                        <span class="badge bg-light text-dark border me-1">
                                            Días restringidos: {{ $addendum->new_days_restricted }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- (c) Formulario para agregar un addendum --}}
            <h6 class="mb-3">Agregar addendum</h6>
            <form method="POST" action="{{ route('addendums.store', $injuryReport->id) }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="addendum_type" class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="type" id="addendum_type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach($addendumTypeLabels as $value => $label)
                                <option value="{{ $value }}" {{ old('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="addendum_body" class="form-label">Detalle <span class="text-danger">*</span></label>
                        <textarea name="body" id="addendum_body" rows="3"
                                  class="form-control @error('body') is-invalid @enderror"
                                  maxlength="4000" required
                                  placeholder="Describe el cambio de diagnóstico o tratamiento...">{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <hr class="my-3">
                <p class="text-muted small mb-2">
                    Cambios opcionales de estadísticas (déjalos vacíos si sólo es una nota).
                    Se aplican como valores "efectivos"; no reescriben el reporte firmado.
                </p>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="new_treatment_level" class="form-label">Nuevo nivel de tratamiento</label>
                        <select name="new_treatment_level" id="new_treatment_level"
                                class="form-select @error('new_treatment_level') is-invalid @enderror">
                            <option value="">— Sin cambio —</option>
                            @foreach($treatmentLabels as $value => $label)
                                <option value="{{ $value }}" {{ old('new_treatment_level') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('new_treatment_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="new_days_away" class="form-label">Nuevos días perdidos</label>
                        <input type="number" name="new_days_away" id="new_days_away" min="0"
                               class="form-control @error('new_days_away') is-invalid @enderror"
                               value="{{ old('new_days_away') }}" placeholder="Sin cambio">
                        @error('new_days_away')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="new_days_restricted" class="form-label">Nuevos días restringidos</label>
                        <input type="number" name="new_days_restricted" id="new_days_restricted" min="0"
                               class="form-control @error('new_days_restricted') is-invalid @enderror"
                               value="{{ old('new_days_restricted') }}" placeholder="Sin cambio">
                        @error('new_days_restricted')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="new_is_recordable" id="new_is_recordable" value="1"
                                   class="form-check-input" {{ old('new_is_recordable') ? 'checked' : '' }}>
                            <label class="form-check-label" for="new_is_recordable">
                                Marcar como registrable OSHA (déjalo sin marcar para no cambiar la registrabilidad)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Guardar addendum</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endfeature
