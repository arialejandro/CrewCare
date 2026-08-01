{{-- _lite-register.blade.php — ALTA de persona SIN CUENTA (no-crew) + FUSIÓN de duplicados.
     (2026-07-31) Movido desde admin/lite/index al home médico unificado (/medicocrud): es el
     "registrar si no aparece" del buscador. Sólo lo ve un clínico (quien puede dar de alta y
     atender). Postea a lite.store (que redirige a atender) y la fusión a lite.merge.

     Recibe: $litePatients (colección de supervivientes, para el selector de fusión). --}}
@php $litePatients = $litePatients ?? collect(); @endphp

@if(auth()->check() && auth()->user()->isClinician())
<details id="reg-nocrew" class="cc-form-card mb-3" @if($errors->any()) open @endif>
    <summary class="cc-form-card__body fw-bold d-flex align-items-center gap-2" style="cursor:pointer; list-style:none;">
        @include('componentes._icon', ['name' => 'user-plus', 'class' => 'cc-ico-18', 'label' => null])
        {{ __('Registrar persona fuera del crew') }}
        <span class="cc-optional ms-1">({{ __('extras, visitantes, proveedores — no aparece arriba') }})</span>
    </summary>
    <div class="cc-form-card__body pt-0">
        <p class="cc-help mb-3">{{ __('Sólo para quien NO forma parte del crew. Si buscas a un integrante del crew, aparece en la lista de arriba: no lo registres aquí (crearías un expediente partido).') }}</p>
        <form method="POST" action="{{ route('lite.store') }}">
            @csrf
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <label class="cc-label">{{ __('Nombre completo') }} <span class="cc-req">*</span></label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" class="form-control cc-control @error('full_name') is-invalid @enderror" required maxlength="255">
                    @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="cc-label">{{ __('Teléfono') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="form-control cc-control" maxlength="30">
                </div>
                <div class="col-6 col-md-3">
                    <label class="cc-label">{{ __('Sexo') }}</label>
                    <select name="sex" class="form-select cc-select">
                        <option value="">—</option>
                        <option value="F" @if(old('sex')==='F') selected @endif>{{ __('Femenino') }}</option>
                        <option value="M" @if(old('sex')==='M') selected @endif>{{ __('Masculino') }}</option>
                        <option value="O" @if(old('sex')==='O') selected @endif>{{ __('Otro') }}</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="cc-label">{{ __('Fecha de nacimiento') }}</label>
                    <input type="date" name="dob" value="{{ old('dob') }}" class="form-control cc-control @error('dob') is-invalid @enderror" max="{{ date('Y-m-d') }}">
                    @error('dob')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="cc-label">{{ __('o Edad') }}</label>
                    <input type="number" name="age" value="{{ old('age') }}" class="form-control cc-control @error('age') is-invalid @enderror" min="0" max="130">
                    @error('age')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="cc-label">{{ __('Puesto o área') }} <span class="cc-optional">({{ __('texto libre') }})</span></label>
                    <input type="text" name="area" value="{{ old('area') }}" class="form-control cc-control" maxlength="120" placeholder="{{ __('Extra, staff de catering, chofer…') }}">
                </div>
                <div class="col-6 col-md-6">
                    <label class="cc-label">{{ __('Contacto de emergencia') }}</label>
                    <input type="text" name="emergency_contact" value="{{ old('emergency_contact') }}" class="form-control cc-control" maxlength="255">
                </div>
                <div class="col-6 col-md-3">
                    <label class="cc-label">{{ __('Tel. de emergencia') }}</label>
                    <input type="text" name="emergency_phone" value="{{ old('emergency_phone') }}" class="form-control cc-control" maxlength="30">
                </div>
                <div class="col-12">
                    <label class="cc-label">{{ __('Procedencia') }} <span class="cc-optional">({{ __('de dónde viene — texto libre') }})</span></label>
                    <input type="text" name="origin" value="{{ old('origin') }}" class="form-control cc-control" maxlength="255" placeholder="{{ __('Agencia de extras, proveedor, visita…') }}">
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary cc-cta">
                    @include('componentes._icon', ['name' => 'user-plus', 'class' => 'cc-ico-18', 'label' => null])
                    {{ __('Registrar y atender') }}
                </button>
            </div>
        </form>

        {{-- Fusión de duplicados: si la misma persona quedó registrada dos veces, se unen SIN perder
             consultas (LitePatientController::fusionar). Sólo con 2+ supervivientes que elegir. --}}
        @if($litePatients->count() > 1)
            <details class="mt-3">
                <summary class="cc-muted small" style="cursor:pointer">{{ __('¿Alguien quedó registrado dos veces? Fusionar duplicados') }}</summary>
                <form method="POST" action="#" id="cc-merge-form" class="d-flex flex-wrap gap-2 align-items-end mt-2"
                      onsubmit="this.action='{{ url('pacientes-lite') }}/'+document.getElementById('cc-merge-dup').value+'/fusionar';">
                    @csrf
                    <div>
                        <label class="cc-label">{{ __('Duplicado (se absorbe)') }}</label>
                        <select id="cc-merge-dup" class="form-select form-select-sm cc-control-sm">
                            @foreach($litePatients as $p)
                                <option value="{{ $p->id }}">{{ $p->displayName() }} @if($p->phone)· {{ $p->phone }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="cc-label">{{ __('Se conserva') }}</label>
                        <select name="survivor_id" class="form-select form-select-sm cc-control-sm">
                            @foreach($litePatients as $p)
                                <option value="{{ $p->id }}">{{ $p->displayName() }} @if($p->phone)· {{ $p->phone }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary"
                            onclick="return confirm('{{ __('Fusionar: ambas historias quedarán juntas y ninguna consulta se pierde. ¿Continuar?') }}');">
                        {{ __('Fusionar') }}
                    </button>
                </form>
            </details>
        @endif
    </div>
</details>
@endif
