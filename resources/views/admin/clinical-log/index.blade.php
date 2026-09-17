@extends('layouts.app')

@section('content')
@include('componentes._form-kit')

@php
    $typeLabels = [
        'expediente'      => __('Expediente'),
        'consulta'        => __('Consulta'),
        'expediente_lite' => __('Expediente (lite)'),
        'injury_completo' => __('Lesión (completo)'),
    ];
@endphp

<div class="container-fluid py-4" style="max-width: 1100px;">
    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('Bitácora de lectura clínica') }}</h1>
            <div class="cc-muted small">{{ __('Quién abrió qué expediente y cuándo. Es el registro de las lecturas, no el expediente.') }}</div>
        </div>
    </div>

    {{-- Filtros: por lector, por paciente, por rango de fechas. --}}
    <form method="GET" class="cc-form-card mb-3">
        <div class="cc-form-card__body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">{{ __('Lector') }}</label>
                    <input type="text" name="reader" value="{{ $filters['reader'] }}" class="form-control form-control-sm" placeholder="{{ __('nombre o id') }}">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">{{ __('Paciente') }}</label>
                    <input type="text" name="patient" value="{{ $filters['patient'] }}" class="form-control form-control-sm" placeholder="{{ __('nombre o id') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">{{ __('Desde') }}</label>
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">{{ __('Hasta') }}</label>
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Filtrar') }}</button>
                    <a href="{{ route('clinical_log.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Limpiar') }}</a>
                </div>
            </div>
        </div>
    </form>

    <div class="cc-form-card">
        <div class="cc-form-card__body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Cuándo') }}</th>
                            <th>{{ __('Lector') }}</th>
                            <th>{{ __('¿Clínico?') }}</th>
                            <th>{{ __('Paciente') }}</th>
                            <th>{{ __('Tipo') }}</th>
                            <th>{{ __('Desde (IP)') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $l)
                            <tr>
                                <td class="mono small">{{ \Carbon\Carbon::parse($l->opened_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    {{ trim(($l->reader_name ?? '') . ' ' . ($l->reader_lname ?? '')) ?: ('#' . ($l->reader_id ?? '—')) }}
                                </td>
                                <td>
                                    @if ($l->reader_is_clinical)
                                        <span class="badge bg-info">{{ __('Clínico') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __('No clínico') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($l->record_type === 'expediente_lite')
                                        {{ __('Lite') }} #{{ $l->patient_ref }}
                                    @else
                                        {{ trim(($l->patient_name ?? '') . ' ' . ($l->patient_lname ?? '')) ?: ('#' . ($l->patient_ref ?? '—')) }}
                                    @endif
                                </td>
                                <td>{{ $typeLabels[$l->record_type] ?? $l->record_type }}</td>
                                <td class="mono small">{{ $l->ip_address ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="cc-muted text-center py-3">{{ __('Sin lecturas registradas para esos filtros.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
