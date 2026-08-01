@extends('layouts.app')
@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

<div class="useredit-page container-fluid py-4" style="max-width: 960px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('Editar Usuario') }}</h1>
            <div class="cc-muted small">{{ $users->name }} {{ $users->lname }} {{ $users->lname2 }}</div>
        </div>
    </div>

    {{-- Feedback de validación + flash. El parcial cubre session('success'), session('error')
         y $errors. El bloque de abajo añade session('warning'), que el parcial NO pinta y que
         SÍ emite MedicCredentialController (p.ej. enlace de cotejo fuera del dominio SEP).
         Va aquí arriba para que cubra los DOS formularios hermanos (datos y cédula): el flash
         y los errores viven en la sesión, así que se ven sin importar cuál rebotó. --}}
    @include('componentes._form-feedback')

    @if(session('warning'))
        <div style="margin-bottom:1rem;border:1px solid #fde68a;background:#fffbeb;color:#92400e;padding:.75rem 1rem;border-radius:.5rem;font-size:.875rem;line-height:1.4;">
            {{ session('warning') }}
        </div>
    @endif

    <form action="{{ route('account.update',['id' => $users->id]) }}" enctype='multipart/form-data' method="POST">
        {{ csrf_field() }}
        {{ method_field('POST') }}

        {{-- ===== Datos personales ===== --}}
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
                <div class="row g-4 align-items-start">
                    {{-- Foto de perfil (solo lectura) --}}
                    <div class="col-12 col-md-auto">
                        <div class="ue-photo mx-auto mx-md-0">
                            {{-- La comprobación en disco vive ahora en Avatar (fuente única); esta
                                 vista era la única que ya la hacía, y con otra ruta de silueta. --}}
                            <img src="{{ \App\Support\Avatar::url($users) }}"
                                 alt="{{ \App\Support\Avatar::has($users) ? __('Foto de perfil') : __('Sin foto') }}">
                        </div>
                    </div>

                    {{-- Campos --}}
                    <div class="col-12 col-md">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="cc-field">
                                    <label for="name" class="cc-label">{{ __('Nombre') }}</label>
                                    <input id="name" type="text" name="name" class="form-control form-control-edit validate cc-control" value="{{ old('name', $users->name) }}">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="cc-field">
                                    <label for="lname" class="cc-label">{{ __('Apellido') }}</label>
                                    <input id="lname" type="text" name="lname" class="form-control form-control-edit validate cc-control" value="{{ old('lname', $users->lname) }}">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="cc-field">
                                    <label for="lname2" class="cc-label">{{ __('Segundo apellido') }}</label>
                                    <input id="lname2" type="text" name="lname2" class="form-control form-control-edit validate cc-control" value="{{ old('lname2', $users->lname2) }}">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="cc-field">
                                    <label for="ncreditos" class="cc-label">{{ __('Nombre en créditos') }}</label>
                                    <input id="ncreditos" type="text" name="ncreditos" class="form-control form-control-edit validate cc-control" value="{{ old('ncreditos', $users->ncreditos) }}">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="cc-field">
                                    <label for="borndate" class="cc-label">{{ __('Fecha de nacimiento') }}</label>
                                    <input id="borndate" type="date" name="borndate" class="form-control form-control-edit validate cc-control" value="{{ old('borndate', $users->borndate) }}">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="cc-field">
                                    <label for="sex" class="cc-label">{{ __('Sexo') }}</label>
                                    <select id="sex" name="sex" class="form-select cc-select">
                                        <option value="M" @if(old('sex', $users->sex) == 'M') selected @endif>M</option>
                                        <option value="F" @if(old('sex', $users->sex) == 'F') selected @endif>F</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Contacto ===== --}}
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
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="phone" class="cc-label">{{ __('Teléfono') }}</label>
                            <input id="phone" type="text" name="phone" class="form-control form-control-edit validate cc-control" value="{{ old('phone', $users->phone) }}">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="email" class="cc-label">{{ __('Email') }}</label>
                            <input id="email" type="text" name="email" class="form-control form-control-edit validate cc-control" value="{{ old('email', $users->email) }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Rol y departamento ===== --}}
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
                    {{-- PASO A (2026-07-19): estos dos campos eran un <input type=text> libre para
                         el puesto y un <select> de 21 departamentos HARDCODEADOS que no cuadraban
                         con el catálogo real (6 nombres no existían en `departments`, faltaban 23).
                         Encima nada de eso escribía production_user, así que editar a alguien NO
                         cambiaba su puesto real. Ahora son los MISMOS selects de catálogo del alta
                         (admin/newuser.blade.php) y el servidor los re-resuelve antes de guardar. --}}
                    @if($isSelf)
                        {{-- Ficha propia: sin selects. Un operador no reasigna su propio
                             departamento/puesto (acountupdate lo rechaza igualmente en servidor). --}}
                        <div class="col-12">
                            <div class="cc-field">
                                <label class="cc-label">{{ __('Departamento y puesto') }}</label>
                                <p class="cc-form-card__sub mb-0">
                                    {{ $users->departmentName() ?: '—' }}@if($users->positionName()) · {{ $users->positionName() }}@endif
                                    <br><small>{{ __('Para cambiar tu propio departamento o puesto, pídelo a un coordinador.') }}</small>
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="col-12 col-md-6">
                            <div class="cc-field">
                                <label for="departmentSelect" class="cc-label">{{ __('Departamento') }}</label>
                                <select name="department_id" id="departmentSelect" class="form-select form-select-cr form-select-crw cc-select">
                                    <option value="">{{ __('— Sin departamento —') }}</option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d->id }}" @if((int) old('department_id', $currentDeptId ?? 0) === (int) $d->id) selected @endif>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="cc-field">
                                <label for="positionSelect" class="cc-label">{{ __('Puesto') }}</label>
                                <select name="position_id" id="positionSelect" class="form-select form-select-cr form-select-crw cc-select">
                                    <option value="">{{ __('— Puesto (opcional) —') }}</option>
                                    {{-- Lo llena el JS con los puestos del catálogo del departamento elegido. --}}
                                </select>
                            </div>
                        </div>
                    @endif
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="labn" class="cc-label">{{ __('Jerarquía') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input id="labn" type="text" class="form-control validate cc-control" name="labn" required autocomplete="labn" value="{{ old('labn', $users->labn) }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Credenciales de acceso ===== --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('Credenciales de acceso') }}</h2>
                    <p class="cc-form-card__sub">{{ __('Contraseña de acceso del miembro de crew.') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="cc-field">
                            <label for="password" class="cc-label">{{ __('Contraseña') }}</label>
                            <input id="password" type="password" name="password" class="form-control form-control-edit validate cc-control">
                            <span class="cc-help">{{ __('Nota: Sí no deseas cambiar la contraseña, deja el espacio vacio.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Acción final ===== --}}
        <div class="d-grid d-md-flex justify-content-md-end mb-4">
            <button type="submit" class="btn btn-primary cc-cta w-100 w-md-auto">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                {{ __('Guardar perfil') }}
            </button>
        </div>

    </form>

    {{-- ===================================================================================
         CÉDULA PROFESIONAL (PASO B, 2026-07-19)

         FUERA del <form> de arriba a propósito: son formularios HERMANOS, no anidados (el
         HTML no permite anidar <form> y el navegador se comería el interior en silencio).
         Por eso tampoco pasa por acountupdate(): tiene su propia ruta, su propia whitelist
         de validación y su propia autorización asimétrica.

         Solo existe para MÉDICOS (rol `medic`, fuente única User::isMedic()); la cédula profesional
         es un concepto de médico. Solo se muestra a quien puede tocarla: el titular o alguien con
         medic.credential.manage.
    =================================================================================== --}}
    @if($users->isMedic() && ($canManageCredential || $isSelf))
        @php
            $credSupported = \App\Models\MedicCredential::supportsCredentials();
            // Enlace de cotejo capturado, YA saneado por la doble lista blanca (esquema +
            // dominio SEP). null = no se pinta como <a>. Ver App\Support\SepRegistry.
            $credHref = $credential !== null ? $credential->safeVerificationUrl() : null;
        @endphp

        <div class="cc-form-card mb-4">
            <div class="cc-form-card__head">
                <div>
                    <h2 class="cc-form-card__title">
                        @include('componentes._icon', ['name' => 'badge-check', 'class' => 'cc-ico-18', 'label' => null])
                        {{ __('Cédula profesional') }}
                    </h2>
                    <p class="cc-form-card__sub">{{ __('Licencia que respalda cada firma médica de esta persona. Se coteja contra el Registro Nacional de Profesionistas de la SEP.') }}</p>
                </div>
            </div>

            <div class="cc-form-card__body">

                @if(! $credSupported)
                    {{-- Sin el SQL aplicado el módulo no existe. Se avisa SOLO a quien podría
                         actuar (no al médico que mira su propia ficha y no puede hacer nada). --}}
                    @if($canManageCredential)
                        <p class="cc-help mb-0">
                            {{ __('El módulo de cédula profesional aún no está disponible en esta instancia: falta aplicar la migración de base de datos (database/owner-apply/2026-07-19-medic-credentials.sql).') }}
                        </p>
                    @endif
                @else

                    {{-- Lo PENDIENTE se marca arriba y en ámbar, ANTES de los datos: quien abre
                         esta ficha tiene que saber que la licencia no está avalada antes de leerla.
                         Mismo criterio que admin/consumables/show. --}}
                    @if($credential !== null && $credential->isPendingVerification())
                        <div class="cc-pending mb-3">
                            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico', 'label' => 'Atención'])
                            <div>
                                <strong>{{ __('Cédula pendiente de verificación') }}</strong>
                                <span>{{ __('Nadie ha cotejado este número contra el registro oficial. Hasta que se valide, esta licencia NO respalda las firmas médicas de esta persona.') }}</span>
                            </div>
                        </div>
                    @endif

                    @if($credential !== null && $credential->isVerified())
                        <p class="mb-3">
                            @include('componentes._medic-credential-badge', ['credential' => $credential])
                            <span class="cc-help d-block mt-1">
                                {{ $credential->verificationSourceLabel() }}
                                @if($credential->verified_at)· {{ $credential->verified_at->format('d/m/Y H:i') }}@endif
                            </span>
                        </p>
                    @endif

                    <form action="{{ route('medic-credential.save', ['id' => $users->id]) }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <div class="cc-field">
                                    <label for="cedula" class="cc-label">{{ __('Número de cédula') }}</label>
                                    <input id="cedula" type="text" name="cedula" maxlength="40" required
                                           class="form-control form-control-edit cc-control"
                                           value="{{ old('cedula', $credential !== null ? $credential->cedula : '') }}">
                                    <span class="cc-help">{{ __('Cambiar este número devuelve la cédula a PENDIENTE: hay que cotejarla de nuevo.') }}</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="cc-field">
                                    <label for="profession" class="cc-label">{{ __('Profesión') }}</label>
                                    <input id="profession" type="text" name="profession" maxlength="150"
                                           class="form-control form-control-edit cc-control"
                                           value="{{ old('profession', $credential !== null ? $credential->profession : '') }}">
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="cc-field">
                                    <label for="specialty" class="cc-label">{{ __('Especialidad') }}</label>
                                    <input id="specialty" type="text" name="specialty" maxlength="150"
                                           class="form-control form-control-edit cc-control"
                                           value="{{ old('specialty', $credential !== null ? $credential->specialty : '') }}">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="cc-field">
                                    <label for="registered_name" class="cc-label">{{ __('Nombre registrado (según la SEP)') }}</label>
                                    <input id="registered_name" type="text" name="registered_name" maxlength="255"
                                           class="form-control form-control-edit cc-control"
                                           value="{{ old('registered_name', $credential !== null ? $credential->registered_name : '') }}">
                                    <span class="cc-help">
                                        {{ __('Cópialo TAL CUAL lo devuelve el registro. Se compara contra') }}
                                        «{{ $users->fullName() }}»:
                                        {{ __('si no coinciden, la cédula NO se valida.') }}
                                    </span>
                                </div>
                            </div>
                            {{-- (2026-07-24) SE RETIRÓ el campo «Enlace de cotejo (SEP)».
                                 Pedía algo que NO EXISTE: el Registro Nacional de Profesionistas
                                 es un buscador por POST, no publica una URL permanente por cédula.
                                 Nadie podía llenarlo con un enlace honesto, así que sólo añadía
                                 fricción — y quien lo llenaba pegaba un dominio de terceros que la
                                 lista blanca rechazaba, reforzando la sensación de que "no sirve".
                                 La columna `verification_url` SE CONSERVA en la BD (hay datos
                                 históricos y entra en el snapshot de las cédulas ya validadas);
                                 simplemente dejó de capturarse. El cotejo real es: abrir el
                                 registro, buscar el número, copiar el nombre que devuelve. --}}
                            <div class="col-12">
                                <a class="cc-btn-ghost" href="{{ \App\Support\SepRegistry::SEARCH_URL }}" target="_blank" rel="noopener">
                                    @include('componentes._icon', ['name' => 'external-link', 'class' => 'cc-ico-16', 'label' => null])
                                    {{ __('Abrir el Registro Nacional de Profesionistas (SEP)') }}
                                </a>
                                <span class="cc-help d-block mt-1">
                                    {{ __('El registro no genera un enlace por cédula: se consulta buscando el número. Copia de ahí el nombre y pégalo arriba.') }}
                                </span>
                            </div>
                        </div>

                        <div class="d-grid d-md-flex justify-content-md-end gap-2 mt-3">
                            <button type="submit" class="btn btn-outline-primary w-100 w-md-auto">
                                @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico-18', 'label' => null])
                                {{ __('Guardar cédula') }}
                            </button>
                        </div>
                    </form>

                    {{-- VALIDAR es un acto de autoridad, por eso va en su propio formulario y con
                         su propia condición. NO aparece para el titular: nadie valida la suya
                         (el controlador lo vuelve a comprobar; esto es solo la UI). --}}
                    @if($canManageCredential && ! $isSelf && $credential !== null && $credential->isPendingVerification())
                        <form action="{{ route('medic-credential.verify', ['id' => $users->id]) }}" method="POST" class="mt-3">
                            @csrf
                            {{-- (2026-07-24) DECLARACIÓN DE COTEJO. Validar una cédula no es marcar
                                 una casilla de sistema: es AVALAR que esta persona puede ejercer
                                 medicina en el set, y ese aval queda con nombre y fecha en el
                                 expediente clínico y en cada consulta que firme. La app no puede
                                 comprobar que de verdad abriste el registro, así que lo que hace es
                                 dejar constancia de QUIÉN lo afirmó. --}}
                            <div class="cc-field">
                                <label class="d-flex align-items-start gap-2" for="attestation">
                                    <input type="checkbox" id="attestation" name="attestation" value="1" required class="mt-1">
                                    <span class="cc-help">
                                        <strong>{{ __('Declaro que consulté el Registro Nacional de Profesionistas (SEP)') }}</strong>
                                        {{ __('y que el número de cédula corresponde a esta persona.') }}
                                        {{ __('Entiendo que esta validación queda registrada con mi nombre y la fecha, que acredita a esta persona para ejercer y firmar actos médicos dentro de la producción, y que responder por ella es mi responsabilidad.') }}
                                    </span>
                                </label>
                            </div>
                            <div class="d-grid d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary cc-cta w-100 w-md-auto">
                                    @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico-18', 'label' => null])
                                    {{ __('Validar bajo mi responsabilidad') }}
                                </button>
                            </div>
                            <p class="cc-help text-md-end mt-1 mb-0">
                                {{ __('Antes de pulsar: abre el registro, busca el número y copia el nombre que devuelve en «Nombre registrado».') }}
                            </p>
                        </form>
                    @elseif($isSelf && $credential !== null && $credential->isPendingVerification())
                        <p class="cc-help mt-3 mb-0">
                            {{ __('No puedes validar tu propia cédula: tiene que hacerlo otra persona con permiso de compliance.') }}
                        </p>
                    @endif

                @endif
            </div>
        </div>
    @endif
</div>

@endsection

@push('styles')
<style>
    .ue-photo {
        width: 96px; height: 96px; border-radius: 50%; overflow: hidden;
        border: 2px solid var(--stroke, var(--border)); background: var(--surface-2);
    }
    .ue-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    @media (min-width: 768px) { .w-md-auto { width: auto !important; } }

    /* ---- Cédula profesional (PASO B) ----
       Chips y banner de pendiente. Copiados del patrón vivo (admin/standards/index,
       admin/consumables/show) porque en esta app cada pantalla los lleva inline: NO hay
       hoja compartida donde vivan. El parcial componentes/_medic-credential-badge los
       DA POR EXISTENTES, así que si un día se mueve este bloque, el chip pierde formato. */
    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.28rem .58rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}

    .cc-pending { display:flex; align-items:flex-start; gap:.7rem; padding:.85rem 1rem;
        border-radius:var(--radius-sm); color:var(--warn);
        background:color-mix(in srgb, var(--warn) 14%, transparent);
        border:1px solid color-mix(in srgb, var(--warn) 34%, transparent); }
    .cc-pending svg { width:20px; height:20px; flex:none; margin-top:.1rem; }
    .cc-pending strong { display:block; font-size:.95rem; }
    .cc-pending span { display:block; margin-top:.2rem; color:var(--text); font-size:.85rem; font-weight:500; }
</style>
@endpush

@push('scripts')
{{-- Espejo del JS del alta (admin/newuser.blade.php): el catálogo completo viaja serializado
     y el select de Puesto se filtra en cliente por departamento. DIFERENCIA con el alta:
     aquí hace falta PRESELECCIONAR el puesto guardado en la primera carga (keepId). --}}
<script>
(function () {
    var positions    = @json($positions ?? []);
    var currentPosId = @json(old('position_id', $currentPosId ?? null));
    var posSel  = document.getElementById('positionSelect');
    var deptSel = document.getElementById('departmentSelect');

    if (!posSel || !deptSel) { return; }   // ficha propia: los selects no se renderizan

    function fillPositions(deptId, keepId) {
        posSel.innerHTML = '<option value="">— Puesto (opcional) —</option>';
        positions.filter(function (p) { return String(p.department_id) === String(deptId); })
                 .forEach(function (p) {
                     var o = document.createElement('option');
                     o.value = p.id;
                     o.textContent = p.name;   // textContent, no innerHTML (mismo criterio del alta)
                     if (keepId !== null && String(keepId) === String(p.id)) { o.selected = true; }
                     posSel.appendChild(o);
                 });
    }

    // Al CAMBIAR de departamento el puesto previo pertenece a otro depto → no se preserva
    // (el servidor lo rechazaría y el pivote quedaría con position_id NULL sin avisar).
    deptSel.addEventListener('change', function () { fillPositions(this.value, null); });

    if (deptSel.value) { fillPositions(deptSel.value, currentPosId); }
})();
</script>
@endpush
