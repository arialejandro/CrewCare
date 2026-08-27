@extends('layouts.app')
@section('content')
{{-- CONFIG DE TRANSPO (Fase 2). Todo por producción, nada en código:
     A) puestos: jefatura (habilita discreto; default = jefe de depto) + "lleva pick up siempre" (LIGERO).
     B) asignación fija de vehículo: puesto→vehículo (el pasajero cambia, el puesto no) o persona→vehículo. --}}
<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'truck', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Configuración de transportación') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Puestos y asignación fija de vehículos, por producción.') }}</p>
            </div>
        </div>

        @if (session('ok'))<div class="alert alert-success py-2">{{ session('ok') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger py-2">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

        {{-- ===== A · PUESTOS ===== --}}
        <h2 class="h6 text-uppercase text-muted mb-2">{{ __('Puestos') }}</h2>
        <p class="text-muted small">{{ __('Jefatura habilita el modo discreto (arranca en los jefes de departamento). “Lleva pick up siempre” define el modo ligero.') }}</p>
        <form method="POST" action="{{ route('transport.config.positions') }}">
            @csrf
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr class="text-muted small text-uppercase"><th>{{ __('Puesto') }}</th><th class="text-center">{{ __('Jefatura') }}</th><th class="text-center">{{ __('Lleva pick up siempre') }}</th></tr></thead>
                    <tbody>
                        @forelse ($positions as $pos)
                            @php
                                $row  = $cfg->get($pos->id);
                                $lead = $row ? $row->is_leadership : (bool) $pos->is_hod;   // default = jefe de depto
                                $alw  = $row ? $row->always_pickup : false;
                            @endphp
                            <tr>
                                <td>{{ $pos->name }}@if($pos->is_hod)<span class="badge bg-light text-dark border ms-1">HOD</span>@endif
                                    <input type="hidden" name="pos_ids[]" value="{{ $pos->id }}"></td>
                                <td class="text-center"><input type="checkbox" class="form-check-input" name="leadership[]" value="{{ $pos->id }}" @checked($lead)></td>
                                <td class="text-center"><input type="checkbox" class="form-check-input" name="always[]" value="{{ $pos->id }}" @checked($alw)></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">{{ __('Sin puestos.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <button class="btn btn-sm btn-primary">{{ __('Guardar puestos') }}</button>
        </form>

        {{-- ===== B · ASIGNACIÓN FIJA ===== --}}
        <h2 class="h6 text-uppercase text-muted mb-2 mt-4">{{ __('Asignación fija de vehículos') }}</h2>
        <p class="text-muted small">{{ __('El vehículo se congela a un puesto (o a una persona como excepción). El conductor es del vehículo, no de aquí.') }}</p>

        <div class="border rounded-3 p-3 bg-body-tertiary mb-3">
            <form method="POST" action="{{ route('transport.config.assign.store') }}" class="row g-2 align-items-end" id="cc-assign-form">
                @csrf
                <div class="col-md-2">
                    <label class="form-label small mb-0">{{ __('Sujeto') }}</label>
                    <select name="subject_kind" class="form-select form-select-sm cc-subject">
                        <option value="position">{{ __('Puesto') }}</option>
                        <option value="person">{{ __('Persona') }}</option>
                    </select>
                </div>
                <div class="col-md-4 cc-by-position">
                    <label class="form-label small mb-0">{{ __('Puesto') }}</label>
                    <select name="position_id" class="form-select form-select-sm js-typeahead">
                        <option value="">{{ __('Elige puesto…') }}</option>
                        @foreach ($positions as $pos)<option value="{{ $pos->id }}">{{ $pos->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4 cc-by-person d-none">
                    <label class="form-label small mb-0">{{ __('Persona') }}</label>
                    <select name="user_id" class="form-select form-select-sm js-typeahead">
                        <option value="">{{ __('Elige persona…') }}</option>
                        @foreach ($people as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-0">{{ __('Vehículo') }}</label>
                    <select name="vehicle_id" class="form-select form-select-sm js-typeahead" required>
                        <option value="">{{ __('Elige vehículo…') }}</option>
                        @foreach ($vehicles as $v)<option value="{{ $v['id'] }}">{{ $v['label'] }}@if($v['plate']) — {{ $v['plate'] }}@endif</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary">{{ __('Asignar') }}</button></div>
            </form>
        </div>

        @forelse ($assignments as $a)
            <div class="border rounded-3 p-2 mb-2 d-flex align-items-center justify-content-between">
                <div>
                    <span class="fw-semibold">{{ $a->subjectLabel() }}</span>
                    <span class="text-muted">→ {{ trim((optional($a->vehicle)->make ?: '') . ' ' . (optional($a->vehicle)->model ?: '')) ?: ('#' . $a->vehicle_id) }}</span>
                    @if(optional($a->vehicle)->plate)<span class="font-monospace text-muted small">{{ $a->vehicle->plate }}</span>@endif
                    <span class="badge bg-light text-dark border ms-1">{{ $a->user_id ? __('persona') : __('puesto') }}</span>
                </div>
                <form method="POST" action="{{ route('transport.config.assign.destroy', $a) }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('Baja') }}</button></form>
            </div>
        @empty
            <p class="text-muted small">{{ __('Sin asignaciones fijas.') }}</p>
        @endforelse

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@push('scripts')
    @include('componentes._typeahead')
    <script>
    (function () {
        var f = document.getElementById('cc-assign-form');
        if (!f) return;
        var sub = f.querySelector('.cc-subject'),
            byPos = f.querySelector('.cc-by-position'),
            byPer = f.querySelector('.cc-by-person');
        function sync() {
            var isPos = sub.value === 'position';
            byPos.classList.toggle('d-none', !isPos);
            byPer.classList.toggle('d-none', isPos);
        }
        sub.addEventListener('change', sync); sync();
    })();
    </script>
@endpush
@endsection
