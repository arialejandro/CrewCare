@extends('layouts.app')
@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

<div class="newuser-page container-fluid py-4" style="max-width: 1080px;">

    @if(session('status'))
        <div class="alert alert-success shadow-sm d-flex align-items-start gap-2">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18 mt-1', 'label' => null])
            <div>{{ session('status') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger shadow-sm d-flex align-items-start gap-2">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-18 mt-1', 'label' => null])
            <div>{{ session('error') }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('newuser') }}" data-cc-autosave="newuser">
        @csrf

        {{-- ============ Encabezado de página ============ --}}
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div>
                    <h1 class="newuser-title mb-0">{{ __('Nuevo integrante') }}</h1>
                    <p class="cc-muted mb-0 small">{{ __('Da de alta un miembro de crew con sus datos, rol y credenciales de acceso.') }}</p>
                </div>
            </div>
            <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
                <a href="/importcrew" class="cc-btn-ghost justify-content-center">
                    @include('componentes._icon', ['name' => 'upload', 'class' => 'cc-ico-16', 'label' => null]) {{ __('Importar') }}
                </a>
                <button type="submit" class="btn btn-primary cc-cta"
                    @if(($restricted ?? false) && empty($lockedDeptId)) disabled @endif>
                    @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico-18', 'label' => null]) {{ __('Registrar miembro') }}
                </button>
            </div>
        </div>

        {{-- ============ Datos personales ============ --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('Datos personales') }}</h2>
                    <p class="cc-form-card__sub">{{ __('Identificación del miembro de crew.') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="row g-3">
                    {{-- Nombre --}}
                    <div class="col-12">
                        <div class="cc-field">
                            <label for="name" class="cc-label">{{ __('Nombre(s)') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input id="name" type="text" class="form-control cc-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="given-name" autofocus placeholder="{{ __('Nombre (s)') }}">
                            @error('name')
                                <div class="invalid-feedback"><strong>{{ $message }}</strong></div>
                            @enderror
                        </div>
                    </div>

                    {{-- Apellidos --}}
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="lname" class="cc-label">{{ __('Primer apellido') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input id="lname" type="text" class="form-control cc-control @error('lname') is-invalid @enderror" name="lname" value="{{ old('lname') }}" required autocomplete="family-name" placeholder="{{ __('Primer Apellido') }}">
                            @error('lname')
                                <div class="invalid-feedback"><strong>{{ $message }}</strong></div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="lname2" class="cc-label">{{ __('Segundo apellido') }}</label>
                            <input id="lname2" type="text" class="form-control cc-control @error('lname2') is-invalid @enderror" name="lname2" value="{{ old('lname2') }}" autocomplete="additional-name" placeholder="{{ __('Segundo Apellido') }}">
                            @error('lname2')
                                <div class="invalid-feedback"><strong>{{ $message }}</strong></div>
                            @enderror
                        </div>
                    </div>

                    {{-- Nombre en créditos --}}
                    <div class="col-12">
                        <div class="cc-field">
                            <label for="ncreditos" class="cc-label">{{ __('Nombre en créditos') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input id="ncreditos" type="text" class="form-control cc-control @error('ncreditos') is-invalid @enderror" name="ncreditos" value="{{ old('ncreditos') }}" required autocomplete="off" placeholder="{{ __('Nombre en créditos') }}">
                            <span class="cc-help">{{ __('Nombre como debe aparecer en los créditos de la producción.') }}</span>
                            @error('ncreditos')
                                <div class="invalid-feedback"><strong>{{ $message }}</strong></div>
                            @enderror
                        </div>
                    </div>

                    {{-- Fecha de nacimiento --}}
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="borndate" class="cc-label">{{ __('Fecha de nacimiento') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input id="borndate" type="date" class="form-control cc-control @error('borndate') is-invalid @enderror" name="borndate" value="{{ old('borndate') }}" required autocomplete="bday">
                            @error('borndate')
                                <div class="invalid-feedback"><strong>{{ $message }}</strong></div>
                            @enderror
                        </div>
                    </div>

                    {{-- Sexo --}}
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="sex" class="cc-label">{{ __('Sexo') }}</label>
                            <select id="sex" name="sex" class="form-select cc-select">
                                <option value="" disabled @selected(old('sex') === null)>{{ __('Sexo') }}</option>
                                <option value="F" @selected(old('sex') === 'F')>{{ __('Mujer') }}</option>
                                <option value="M" @selected(old('sex') === 'M')>{{ __('Hombre') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ Rol y departamento ============ --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('Rol y departamento') }}</h2>
                    <p class="cc-form-card__sub">{{ __('Área y puesto del miembro dentro de la producción.') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="row g-3">
                    {{-- Departamento (catálogo) --}}
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="departmentSelect" class="cc-label">{{ __('Departamento') }}</label>
                            @if(($restricted ?? false) && empty($lockedDeptId))
                            {{-- Rol restringido (HOD) SIN departamento asignado: NO se muestra la lista
                                 completa (evita capturar en el depto equivocado). Alta bloqueada hasta
                                 que le asignen un departamento. --}}
                            <div class="alert alert-warning mb-0 py-2 d-flex align-items-start gap-2">
                                @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-16 mt-1', 'label' => null])
                                <span>{{ __('No tienes un departamento asignado. Pide a un coordinador que te asigne uno para dar de alta crew de tu área.') }}</span>
                            </div>
                            @elseif(!empty($lockedDeptId))
                            {{-- HOD con depto: FIJO a su área. El hidden envía su id y el campo
                                 visible es de solo lectura; el servidor lo fuerza igual. --}}
                            <input type="text" class="form-control cc-control" value="{{ $lockedDept }}" readonly title="{{ __('Tu departamento (fijo)') }}">
                            <input type="hidden" name="department_id" value="{{ $lockedDeptId }}">
                            <span class="cc-help d-flex align-items-center gap-1">
                                @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-14', 'label' => null])
                                {{ __('Departamento fijo asignado a tu rol.') }}
                            </span>
                            @else
                            <select name="department_id" id="departmentSelect" class="form-select cc-select" required>
                                <option value="" disabled @selected(old('department_id') === null)>{{ __('Departamento') }}</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d->id }}" @selected((string) old('department_id') === (string) $d->id)>{{ $d->name }}</option>
                                @endforeach
                            </select>
                            @endif
                        </div>
                    </div>

                    {{-- Puesto (catálogo, depende del departamento) --}}
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="positionSelect" class="cc-label">{{ __('Puesto') }} <span class="cc-optional">({{ __('opcional') }})</span></label>
                            <select name="position_id" id="positionSelect" class="form-select cc-select">
                                <option value="">{{ __('— Puesto (opcional) —') }}</option>
                                {{-- Lo llena el JS con los puestos del catálogo del departamento elegido. --}}
                            </select>
                        </div>
                    </div>

                    {{-- Rol de acceso (2026-07-24).
                         El PUESTO es una etiqueta del catálogo; el ROL es lo que abre puertas en la
                         app. Antes todo el mundo nacía como `crew`, así que dar de alta a alguien
                         como "Doctor en Set" NO le daba el panel médico y había que corregirlo
                         después en Roles y departamentos. Este campo cierra ese hueco.
                         Sólo lo ve quien puede asignar roles; el resto sigue creando `crew`. --}}
                    @if(!empty($assignableRoles))
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="roleSelect" class="cc-label">{{ __('Rol de acceso') }}</label>
                            <select name="role" id="roleSelect" class="form-select cc-select">
                                @foreach($assignableRoles as $r)
                                    <option value="{{ $r }}" {{ old('role', 'crew') === $r ? 'selected' : '' }}>{{ $r }}</option>
                                @endforeach
                            </select>
                            <span class="cc-help">{{ __('Define qué puede ver y hacer en CrewCare. El puesto NO otorga accesos: un “Doctor en Set” necesita el rol médico para entrar al expediente clínico.') }}</span>
                        </div>
                    </div>
                    @endif

                </div>
            </div>
        </div>

        {{-- ============ Contacto ============ --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'phone', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('Contacto') }}</h2>
                    <p class="cc-form-card__sub">{{ __('Datos para localizar al miembro de crew.') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="row g-3">
                    {{-- Teléfono --}}
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="phone" class="cc-label">{{ __('Teléfono') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input id="phone" type="tel" inputmode="numeric" class="form-control cc-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="555555555">
                            @error('phone')
                                <div class="invalid-feedback"><strong>{{ $message }}</strong></div>
                            @enderror
                        </div>
                    </div>

                    {{-- Email --}}
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="email" class="cc-label">{{ __('Correo electrónico') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input id="email" type="email" class="form-control cc-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="usuario@dominio.com">
                            @error('email')
                                <div class="invalid-feedback"><strong>{{ $message }}</strong></div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ Credenciales de acceso ============ --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('Credenciales de acceso') }}</h2>
                    <p class="cc-form-card__sub">{{ __('Contraseña con la que el miembro iniciará sesión.') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="row g-3">
                    {{-- Contraseña --}}
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="password" class="cc-label">{{ __('Contraseña') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input id="password" type="password" class="form-control cc-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password" placeholder="{{ __('Contraseña') }}">
                            @error('password')
                                <div class="invalid-feedback"><strong>{{ $message }}</strong></div>
                            @enderror
                        </div>
                    </div>

                    {{-- Confirmar contraseña --}}
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="password-confirm" class="cc-label">{{ __('Confirmar contraseña') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input id="password-confirm" type="password" class="form-control cc-control" name="password_confirmation" required autocomplete="new-password" placeholder="{{ __('Confirma Contraseña') }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ Acción final ============ --}}
        <div class="d-grid d-md-flex justify-content-md-end mb-4">
            <button type="submit" class="btn btn-primary cc-cta w-100 w-md-auto"
                @if(($restricted ?? false) && empty($lockedDeptId)) disabled @endif>
                @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico-18', 'label' => null]) {{ __('Registrar Miembro de Crew') }}
            </button>
        </div>

    </form>
</div>

@endsection

@push('styles')
<style>
    .newuser-title {
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 1.5rem;
        color: var(--text);
    }
    /* En escritorio el CTA no necesita ocupar todo el ancho. */
    @media (min-width: 768px) { .w-md-auto { width: auto !important; } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var positions = @json($positions ?? []);
    var posSel  = document.getElementById('positionSelect');
    var deptSel = document.getElementById('departmentSelect');
    var lockedDeptId = @json($lockedDeptId ?? null);

    // Llena el select de Puesto con los puestos del catálogo que pertenecen al depto elegido.
    function fillPositions(deptId) {
        if (!posSel) return;
        posSel.innerHTML = '<option value="">— Puesto (opcional) —</option>';
        positions.filter(function (p) { return String(p.department_id) === String(deptId); })
                 .forEach(function (p) {
                     var o = document.createElement('option');
                     o.value = p.id;
                     o.textContent = p.name;
                     posSel.appendChild(o);
                 });
    }

    if (deptSel) {
        deptSel.addEventListener('change', function () { fillPositions(this.value); });
        if (deptSel.value) { fillPositions(deptSel.value); }
    } else if (lockedDeptId) {
        // HOD: depto fijo → carga directamente sus puestos.
        fillPositions(lockedDeptId);
    }
})();
</script>
@endpush
