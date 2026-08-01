@extends('layouts.app')
@section('content')
@php
    $groupOrder = array_keys($groups);
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 900px;">

        <a href="{{ route('epi.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-3" style="text-decoration:none;">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null]) {{ __('Volver al panel') }}
        </a>

        <div class="d-flex align-items-start gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Estudio de brote') }}</h1>
                <p class="text-muted mb-0 small">{{ __('NOM-017-SSA2-2012. El sistema aporta los conteos; el criterio lo pones tú.') }}</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="alert alert-light border d-flex align-items-start gap-2 small">
            @include('componentes._icon', ['name' => 'info', 'label' => null])
            <span>{{ __('El sistema no declara brotes ni sugiere que lo haya. Emites este documento porque TÚ, como médico, lo decides. Este documento SÍ puede llevar nombres: es clínico y lo firmas tú.') }}</span>
        </div>

        {{-- Conteos que aporta el sistema (contexto). Se congelan al emitir. --}}
        @if ($epi['has_data'])
            <div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
                <div class="small text-muted mb-2">{{ __('Conteos actuales (se congelan en el estudio)') }}</div>
                <div style="overflow-x:auto;">
                    <table class="table table-sm small mb-0">
                        <thead><tr>
                            <th>{{ __('Día') }}</th><th>{{ __('Personas') }}</th>
                            @foreach ($groupOrder as $gk)<th title="{{ $groups[$gk] }}">{{ mb_substr($groups[$gk],0,4) }}</th>@endforeach
                            <th>{{ __('S/C') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($epi['days'] as $i => $day)
                            <tr>
                                <td>{{ $day['label'] }}</td>
                                <td>{{ $day['crew_count'] === null ? '—' : $day['crew_count'] }}</td>
                                @foreach ($groupOrder as $gk)<td>{{ $epi['series'][$gk][$i] ?? 0 }}</td>@endforeach
                                <td>{{ $epi['series']['_unclassified'][$i] ?? 0 }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <form method="post" action="{{ route('epi.outbreak.store') }}">
            @csrf
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">{{ __('Título del estudio') }} *</label>
                        <input type="text" name="title" class="form-control" maxlength="255" required value="{{ old('title') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Grupo bajo estudio') }}</label>
                        <select name="group_key" class="form-select">
                            <option value="">{{ __('Todos / mixto') }}</option>
                            @foreach ($groups as $gk => $label)
                                <option value="{{ $gk }}" @selected(old('group_key', $groupKey) === $gk)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Periodo desde') }}</label>
                        <input type="date" name="period_from" class="form-control" value="{{ old('period_from') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Periodo hasta') }}</label>
                        <input type="date" name="period_to" class="form-control" value="{{ old('period_to') }}">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <h6 class="mb-3">{{ __('NOM-017-SSA2-2012') }}</h6>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">{{ __('Definición operacional de casos') }} *</label>
                    <textarea name="case_definition" class="form-control" rows="2" maxlength="4000" required placeholder="{{ __('qué cuenta como caso') }}">{{ old('case_definition') }}</textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Tiempo') }}</label>
                        <textarea name="time_description" class="form-control" rows="3" maxlength="4000" placeholder="{{ __('curva epidémica, fechas') }}">{{ old('time_description') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Lugar') }}</label>
                        <textarea name="place_description" class="form-control" rows="3" maxlength="4000" placeholder="{{ __('locación, set, base camp') }}">{{ old('place_description') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Persona') }}</label>
                        <textarea name="person_description" class="form-control" rows="3" maxlength="4000" placeholder="{{ __('departamento, roles; aquí SÍ pueden ir nombres') }}">{{ old('person_description') }}</textarea>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">{{ __('Casos') }}</label>
                        <input type="number" name="attack_rate_cases" class="form-control" min="0" value="{{ old('attack_rate_cases') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">{{ __('Población en riesgo') }}</label>
                        <input type="number" name="attack_rate_population" class="form-control" min="0" value="{{ old('attack_rate_population') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Tasa de ataque (nota)') }}</label>
                        <input type="text" name="attack_rate_note" class="form-control" maxlength="255" value="{{ old('attack_rate_note') }}" placeholder="{{ __('cómo la enuncias') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">{{ __('Hipótesis') }}</label>
                        <textarea name="hypothesis" class="form-control" rows="2" maxlength="4000">{{ old('hypothesis') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">{{ __('Medidas de control') }}</label>
                        <textarea name="control_measures" class="form-control" rows="2" maxlength="4000">{{ old('control_measures') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('epi.index') }}" class="btn btn-link text-muted">{{ __('Abandonar sin guardar') }}</a>
                <button type="submit" class="btn btn-crew-accent d-inline-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'shield-check', 'label' => null])
                    {{ __('Emitir y sellar') }}
                </button>
            </div>
        </form>

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
