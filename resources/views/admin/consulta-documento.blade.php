@extends('layouts.app')
@section('title', 'Documento de consulta - ' . ($branding['brand_name'] ?? 'CrewCare'))
@section('content')
@push('scripts')
<script>
    // Exportar PDF vía window.print (CSP: sin onclick inline).
    document.addEventListener('click', function (e) { if (e.target.closest('[data-doc-print]')) { window.print(); } });
</script>
@endpush
{{-- DOCUMENTO SELLADO de UNA consulta (crew o lite) — 2026-07-25. Se abre desde el historial; se
     exporta a PDF con window.print (convención del módulo). Reconciliación + sello a cualquier médico;
     la nota privada sólo si $canSeeNotes (propiedad / key medic). GATE doctor-only en el controlador. --}}
@include('componentes._form-kit')

@php
    $folio          = 'MED-' . str_pad((string) $consulta->id_cmedic, 4, '0', STR_PAD_LEFT);
    $fecha          = $consulta->consultation_date ?: $consulta->created_at;
    $pacienteNombre = $paciente
        ? trim($paciente->name . ' ' . ($paciente->lname ?? '') . ' ' . ($paciente->lname2 ?? ''))
        : ($litePaciente ? $litePaciente->displayName() : '—');
    $med  = $consulta->medsLine();
    $mgmt = implode(' · ', $consulta->managementLabels());
@endphp

<div class="container py-4" style="max-width: 820px;">

    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary">← {{ __('Volver') }}</a>
        <button type="button" class="btn btn-sm btn-primary" data-doc-print>
            @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico-16']) {{ __('Exportar PDF') }}
        </button>
    </div>

    <div class="cc-form-card">
        <div class="cc-form-card__body" style="color: var(--text);">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h1 class="h5 fw-bold mb-1">{{ __('Consulta médica') }} · {{ $folio }}</h1>
                    <div class="cc-muted small">{{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y H:i') : '—' }}</div>
                </div>
                @if($litePaciente)
                    <span class="cc-chip cc-chip-warn">{{ __('Paciente sin expediente') }}</span>
                @endif
            </div>

            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('Paciente') }}</dt>
                <dd class="col-sm-9">{{ $pacienteNombre }}</dd>

                <dt class="col-sm-3">{{ __('Diagnóstico') }}</dt>
                <dd class="col-sm-9">{{ trim((string) $consulta->diagnosis) ?: '—' }}</dd>

                <dt class="col-sm-3">{{ __('Medicamento') }}</dt>
                <dd class="col-sm-9">{{ $med !== '' ? $med : '—' }}</dd>

                <dt class="col-sm-3">{{ __('Manejo / conducta') }}</dt>
                <dd class="col-sm-9">{{ $mgmt !== '' ? $mgmt : '—' }}</dd>

                <dt class="col-sm-3">{{ __('Atendió') }}</dt>
                <dd class="col-sm-9">
                    @include('componentes._consult-attended-by', [
                        'consulta' => $consulta,
                        'medicos'  => $autor ? collect([$autor->id => $autor]) : collect(),
                    ])
                </dd>
            </dl>

            <hr>
            @if($canSeeNotes)
                <dl class="row mb-0">
                    <dt class="col-sm-3">{{ __('Observaciones') }}</dt>
                    <dd class="col-sm-9">{{ trim((string) $consulta->observations) ?: __('Ninguna') }}</dd>
                    <dt class="col-sm-3">{{ __('Información adicional') }}</dt>
                    <dd class="col-sm-9">{{ trim((string) $consulta->aditional) ?: __('Ninguna') }}</dd>
                </dl>
            @else
                <p class="cc-muted small mb-0">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-16'])
                    {{ __('La nota clínica (observaciones e información adicional) es privada del médico que atendió.') }}
                </p>
            @endif

            <hr>
            {{-- Sello SHA-256 + cadena CFDI cotejable de ESTA consulta (mismo componente que el historial). --}}
            @include('componentes._seal-cfdi', [
                'doc'    => $consulta,
                'folio'  => $folio,
                'prefix' => 'CREWCARE-MED',
            ])
        </div>
    </div>
</div>
@endsection
