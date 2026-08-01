@extends('layouts.app')
@section('title', 'Historial de consultas - ' . ($branding['brand_name'] ?? 'CrewCare'))
@section('content')
{{-- HISTORIAL COMPLETO del paciente SIN CUENTA (2026-07-25). Antes NO existía: sólo el cintillo al
     atender. UN SOLO CAMINO con el crew — misma query (cmedic::historyForPatient), mismo componente
     "atendió", mismo documento por consulta. Une la persona y sus duplicados fundidos (identityGroupIds).
     GATE doctor-only en el controlador. Lista la reconciliación; la nota privada va en el documento. --}}
@include('componentes._form-kit')

<div class="container py-4" style="max-width: 980px;">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'history', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">{{ $paciente->displayName() }}</h1>
                <div class="cc-muted small">{{ __('Historial de consultas') }} · {{ $consultas->count() }}</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ url('/medicocrud') }}" class="btn btn-sm btn-outline-secondary">← {{ __('Volver') }}</a>
            <a href="{{ route('lite.consulta.create', $paciente->id) }}" class="btn btn-sm btn-primary">{{ __('Atender') }}</a>
        </div>
    </div>

    <div class="cc-form-card">
        <div class="cc-form-card__body">
            @if($consultas->isEmpty())
                <p class="cc-muted mb-0">{{ __('Sin atenciones previas registradas') }}</p>
            @else
                <div style="overflow-x:auto">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="cc-muted small">
                                <th>{{ __('Fecha') }}</th>
                                <th>{{ __('Atendió') }}</th>
                                <th>{{ __('Diagnóstico') }}</th>
                                <th>{{ __('Medicamento') }}</th>
                                <th>{{ __('Documento') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($consultas as $consulta)
                                @php($fecha = $consulta->consultation_date ?: $consulta->created_at)
                                @php($med = $consulta->medsLine())
                                @php($mgmt = implode(' · ', $consulta->managementLabels()))
                                <tr>
                                    <td class="small">{{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y H:i') : '—' }}</td>
                                    <td class="small">@include('componentes._consult-attended-by', ['consulta' => $consulta, 'medicos' => $medicos])</td>
                                    <td class="small">{{ trim((string) $consulta->diagnosis) ?: '—' }}</td>
                                    <td class="small">
                                        {{ $med !== '' ? $med : ($mgmt !== '' ? $mgmt : '—') }}
                                        @if($med !== '' && $mgmt !== '')<div class="cc-muted">{{ $mgmt }}</div>@endif
                                    </td>
                                    <td>
                                        <a href="{{ route('consulta.documento', $consulta->id_cmedic) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                                            @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico-16']) {{ __('Ver / PDF') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
