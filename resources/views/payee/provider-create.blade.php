@extends('layouts.app')
@section('content')
{{-- ALTA DE PROVEEDOR · carril "proveedor puro" (fuera del llamado). La decisión de arriba (¿está en
     el llamado?) enruta: si SÍ, es crew (perfil) y va por otro lado; si NO, es proveedor de pura
     facturación. Crea la identidad (Payee física/moral) + su primer contrato de renta/servicio. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 820px;">

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'wallet', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Alta de proveedor') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Proveedores que solo facturan (no están en el llamado).') }}</p>
            </div>
        </div>

        @if(session('error'))
            <div class="alert alert-warning">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('providers.store') }}" novalidate>
            @csrf

            {{-- ── Decisión CrewList (la regla que enruta) ── --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="in_crewlist" name="in_crewlist" value="1" {{ old('in_crewlist') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="in_crewlist">{{ __('¿Es parte del CrewList?') }}</label>
                    </div>
                    <p class="text-muted small mb-0 mt-1">
                        {{ __('Ser parte del CrewList = ser susceptible a auditorías de Safety, Atención Médica y Logística de Producción.') }}
                    </p>
                    <div id="crewlistWarn" class="alert alert-info small mt-2 mb-0" style="display:none">
                        {{ __('Entonces es CREW y necesita perfil. Dalo de alta como crew (no como proveedor).') }}
                        <a href="{{ route('usuarioscrud') }}" class="alert-link">{{ __('Ir al alta de crew') }}</a>
                    </div>
                </div>
            </div>

            {{-- ── Identidad ── --}}
            <div class="card mb-3">
                <div class="card-header fw-semibold">{{ __('Identidad') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label d-block">{{ __('Tipo') }} *</label>
                        <div class="btn-group" role="group" aria-label="tipo">
                            <input type="radio" class="btn-check" name="legal_nature" id="nat_fisica" value="fisica" {{ old('legal_nature', 'fisica') === 'fisica' ? 'checked' : '' }}>
                            <label class="btn btn-outline-secondary" for="nat_fisica">{{ __('Persona física') }}</label>
                            <input type="radio" class="btn-check" name="legal_nature" id="nat_moral" value="moral" {{ old('legal_nature') === 'moral' ? 'checked' : '' }}>
                            <label class="btn btn-outline-secondary" for="nat_moral">{{ __('Persona moral (empresa)') }}</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="name"><span data-label-fisica>{{ __('Nombre completo') }}</span><span data-label-moral style="display:none">{{ __('Razón social') }}</span> *</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3" data-moral-only style="display:none">
                        <label class="form-label" for="legal_representative">{{ __('Representante legal') }} *</label>
                        <input type="text" class="form-control @error('legal_representative') is-invalid @enderror" id="legal_representative" name="legal_representative" value="{{ old('legal_representative') }}">
                        @error('legal_representative')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="rfc">{{ __('RFC') }}</label>
                        <input type="text" class="form-control @error('rfc') is-invalid @enderror" id="rfc" name="rfc" value="{{ old('rfc') }}" maxlength="20" style="text-transform:uppercase">
                        @error('rfc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('Los demás documentos fiscales se completan después, en su ficha.') }}</div>
                    </div>
                </div>
            </div>

            {{-- ── Contacto ── --}}
            <div class="card mb-3">
                <div class="card-header fw-semibold">{{ __('Contacto') }}</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="email">{{ __('Correo') }}</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">{{ __('Teléfono') }}</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone') }}" maxlength="40">
                    </div>
                </div>
            </div>

            {{-- ── Departamento + primer contrato ── --}}
            <div class="card mb-3">
                <div class="card-header fw-semibold">{{ __('Contratación') }}</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="department_id">{{ __('Departamento') }} *</label>
                        <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id" required>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}" {{ (int) old('department_id') === (int) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="concept">{{ __('Concepto') }} *</label>
                        <select class="form-select @error('concept') is-invalid @enderror" id="concept" name="concept" required>
                            <option value="service" {{ old('concept', 'service') === 'service' ? 'selected' : '' }}>{{ __('Servicio') }}</option>
                            <option value="equipment_rental" {{ old('concept') === 'equipment_rental' ? 'selected' : '' }}>{{ __('Renta de equipo') }}</option>
                        </select>
                        @error('concept')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="title">{{ __('Descripción de lo contratado') }}</label>
                        <input type="text" class="form-control" id="title" name="title" value="{{ old('title') }}" maxlength="191" placeholder="{{ __('Ej.: renta de cámara, servicio de jardinería…') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fee_amount">{{ __('Monto') }}</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="fee_amount" name="fee_amount" value="{{ old('fee_amount') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fee_currency">{{ __('Moneda') }}</label>
                        <select class="form-select" id="fee_currency" name="fee_currency">
                            <option value="MXN" {{ old('fee_currency', 'MXN') === 'MXN' ? 'selected' : '' }}>MXN</option>
                            <option value="USD" {{ old('fee_currency') === 'USD' ? 'selected' : '' }}>USD</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="payment_frequency">{{ __('Frecuencia de pago') }}</label>
                        <select class="form-select" id="payment_frequency" name="payment_frequency">
                            <option value="">{{ __('— sin definir —') }}</option>
                            @foreach($frequencies as $k => $label)
                                <option value="{{ $k }}" {{ old('payment_frequency') === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-crew" id="submitBtn">{{ __('Dar de alta') }}</button>
                <a href="{{ route('payees.index') }}" class="btn btn-crew-soft">{{ __('Cancelar') }}</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var nat = document.querySelectorAll('input[name="legal_nature"]');
    var moralOnly = document.querySelectorAll('[data-moral-only]');
    var labF = document.querySelectorAll('[data-label-fisica]');
    var labM = document.querySelectorAll('[data-label-moral]');
    function syncNat() {
        var moral = document.getElementById('nat_moral').checked;
        moralOnly.forEach(function (el) { el.style.display = moral ? '' : 'none'; });
        labF.forEach(function (el) { el.style.display = moral ? 'none' : ''; });
        labM.forEach(function (el) { el.style.display = moral ? '' : 'none'; });
    }
    nat.forEach(function (r) { r.addEventListener('change', syncNat); });
    syncNat();

    // Regla del CrewList: al marcarlo, avisa que eso es crew (perfil) y no un proveedor puro.
    var toggle = document.getElementById('in_crewlist');
    var warn = document.getElementById('crewlistWarn');
    var submit = document.getElementById('submitBtn');
    function syncCrew() {
        var on = toggle.checked;
        warn.style.display = on ? '' : 'none';
        submit.disabled = on;
    }
    toggle.addEventListener('change', syncCrew);
    syncCrew();
})();
</script>
@endpush
@endsection
