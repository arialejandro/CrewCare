@extends('layouts.app')
@section('content')

{{-- EXPEDIENTE CLÍNICO del crew. Se llena UNA VEZ por producción y (a partir de la corrida 2/2)
     queda sellado: nadie lo edita, ni su titular. Guarda en POST /formularios/registro →
     FormulariosController@newformulario, validado por StoreHealthRecordRequest.

     (2026-07-24 · PIEZA 3) Esta vista se corrigió en cinco frentes SIN tocar qué se pregunta:
       · i18n: estaba 100 % en español clavado en el HTML (resources/lang/{es,en}/health.php).
       · Se eliminaron TRES bloques <script> muertos: el `fetch('/registrarformulario/…')` a una
         ruta inexistente (disparado por un onclick que convivía con el submit normal, así que
         cada guardado exitoso podía terminar con un alert "ocurrio un error"), un segundo
         `const dropdown` que provocaba SyntaxError en consola, y su gemelo hacia un `#cirugys`
         que no existe. Además ese JS leía por `#id` y las casillas no tenían id: siempre 0.
       · Interruptor "sin antecedentes" en los 4 campos de texto libre: antes había que teclear
         "ninguna" cuatro veces para poder guardar.
       · Vivo/Fallecido dejaron de poder marcarse los DOS a la vez.
       · La influenza pide fecha (la etiqueta promete vigencia; sin fecha no se sostiene).

     El tipo de sangre pasó de texto libre a lista cerrada: es el dato que se consulta en una
     urgencia y "0+" (cero) se ve idéntico a "O+" (letra). --}}

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

{{-- CSS específico de ESTA vista: el checkbox tappable (casilla-tarjeta) que el kit no cubre,
     el interruptor "sin antecedentes" y los bloques que se revelan. --}}
@push('styles')
<style>
    .cc-check {
        display: flex; align-items: center; gap: .55rem; width: 100%;
        min-height: 44px; margin: 0; padding: .45rem .7rem;
        border: 1px solid var(--stroke-2, var(--border)); border-radius: var(--radius-sm, 11px);
        background: var(--surface); cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease;
    }
    .cc-check:hover { border-color: var(--brand-primary); background: rgba(var(--brand-primary-rgb), .05); }
    .cc-check .form-check-input { flex: none; margin: 0; float: none; }
    .cc-check .form-check-label { margin: 0; color: var(--text); font-size: .9rem; line-height: 1.3; cursor: pointer; }
    .cc-unit { color: var(--text-muted); font-weight: 500; }

    /* Interruptor "sin antecedentes": vive PEGADO a la etiqueta de su campo, no en una
       barra aparte, para que se lea como parte de la pregunta y no como una opción global. */
    .cc-none {
        display: inline-flex; align-items: center; gap: .4rem;
        min-height: 32px; padding: .15rem .55rem; margin-left: auto;
        border: 1px solid var(--stroke-2, var(--border)); border-radius: 999px;
        background: var(--surface); cursor: pointer;
        font-size: .78rem; color: var(--text-muted); white-space: nowrap;
    }
    .cc-none:hover { border-color: var(--brand-primary); color: var(--text); }
    .cc-none input { margin: 0; }
    .cc-label-row { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
    .cc-control[readonly] { opacity: .72; }

    /* Bloques que se revelan por JS (fecha de vacuna, campos de hospitalización). */
    .cc-reveal { display: none; }
    .cc-reveal.is-on { display: block; }
</style>
@endpush

<div class="container py-4" style="max-width: 960px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('health.title') }}</h1>
            <div class="cc-muted small">{{ auth()->user()->name }} {{ auth()->user()->lname }}</div>
        </div>
    </div>

    <p class="cc-muted small mb-3">{{ __('health.intro') }} {{ __('health.required_note') }}</p>

    {{-- Feedback de validación: el formulario ahora puede REBOTAR desde el servidor. Antes no
         validaba nada, así que nunca volvía — y no había dónde mostrar el error. --}}
    @include('componentes._form-feedback')

    <form action="{{ route('expediente.store') }}" method="POST">
        @csrf

        {{-- ===== Datos generales ===== --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('health.s_general') }}</h2>
                    <p class="cc-form-card__sub">{{ __('health.s_general_sub') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="cc-field">
                            <label for="blod_type" class="cc-label">{{ __('health.f_blood') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <select class="form-select cc-select @error('blod_type') is-invalid @enderror" name="blod_type" id="blod_type" required>
                                <option value="">{{ __('health.f_blood_choose') }}</option>
                                @foreach(\App\Http\Requests\StoreHealthRecordRequest::BLOOD_TYPES as $tipo)
                                    <option value="{{ $tipo }}" @if(old('blod_type') === $tipo) selected @endif>{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="cc-field">
                            <label for="height" class="cc-label">{{ __('health.f_weight') }} <span class="cc-unit">{{ __('health.f_weight_unit') }}</span> <span class="cc-unit">· {{ __('health.f_optional') }}</span></label>
                            <input class="form-control cc-control @error('height') is-invalid @enderror" placeholder="68.5" name="height" id="height" type="text" inputmode="decimal" value="{{ old('height') }}"/>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="cc-field">
                            <label for="size" class="cc-label">{{ __('health.f_size') }} <span class="cc-unit">{{ __('health.f_size_unit') }}</span> <span class="cc-unit">· {{ __('health.f_optional') }}</span></label>
                            <input class="form-control cc-control @error('size') is-invalid @enderror" placeholder="1.68" name="size" id="size" type="text" inputmode="decimal" value="{{ old('size') }}"/>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="c_emer" class="cc-label">{{ __('health.f_contact') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input class="form-control cc-control @error('c_emer') is-invalid @enderror" name="c_emer" id="c_emer" type="text" value="{{ old('c_emer') }}" required/>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="relation" class="cc-label">{{ __('health.f_relation') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input class="form-control cc-control @error('relation') is-invalid @enderror" name="relation" id="relation" placeholder="{{ __('health.f_relation_ph') }}" type="text" value="{{ old('relation') }}" required/>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="p_emer" class="cc-label">{{ __('health.f_phone') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input class="form-control cc-control @error('p_emer') is-invalid @enderror" name="p_emer" id="p_emer" placeholder="5555555555" type="tel" inputmode="tel" value="{{ old('p_emer') }}" required/>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Antecedentes patológicos ===== --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('health.s_pathological') }}</h2>
                    <p class="cc-form-card__sub">{{ __('health.s_pathological_sub') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="cc-field">
                    <label for="hospitals" class="cc-label">{{ __('health.f_hospitalizations') }}</label>
                    <select class="form-select cc-select" name="hospitals" id="hospitals">
                        @for($n = 0; $n <= 10; $n++)
                            <option value="{{ $n }}" @if((string) old('hospitals') === (string) $n) selected @endif>{{ trans_choice('health.n_hospitalizations', $n, ['count' => $n]) }}</option>
                        @endfor
                    </select>
                    <span class="cc-help">{{ __('health.h_hospitalizations') }}</span>
                </div>

                {{-- Un campo por hospitalización. VARCHAR(50) en BD: el maxlength lo dice en el
                     navegador y el FormRequest lo repite en el servidor. Sin ese tope, describir
                     bien una hospitalización tumbaba el guardado con un 500 (MySQL estricto). --}}
                <div class="row g-2 mt-1">
                    @for($n = 1; $n <= 10; $n++)
                        <div class="col-12 col-md-6 cc-reveal hospi" data-hospi="{{ $n }}">
                            <label for="hsp{{ $n }}" class="cc-label">{{ __('health.f_hospitalization_n', ['n' => $n]) }}</label>
                            <input class="form-control cc-control @error('hsp'.$n) is-invalid @enderror" name="hsp{{ $n }}" id="hsp{{ $n }}" maxlength="50" placeholder="{{ __('health.f_hospitalization_ph') }}" type="text" value="{{ old('hsp'.$n) }}"/>
                        </div>
                    @endfor
                </div>

                <div class="row g-3 mt-1">
                    @php
                        $librePatologicos = [
                            ['campo' => 'cirugy',    'etiqueta' => 'health.f_surgery',   'ayuda' => 'health.f_surgery_ph',   'filas' => 4],
                            ['campo' => 'pathology', 'etiqueta' => 'health.f_pathology', 'ayuda' => 'health.f_pathology_ph', 'filas' => 3],
                            ['campo' => 'alergy',    'etiqueta' => 'health.f_allergy',   'ayuda' => 'health.f_allergy_ph',   'filas' => 3],
                            ['campo' => 'trauma',    'etiqueta' => 'health.f_trauma',    'ayuda' => 'health.f_trauma_ph',    'filas' => 3],
                        ];
                    @endphp
                    @foreach($librePatologicos as $lp)
                        <div class="col-12">
                            <div class="cc-field">
                                <div class="cc-label-row">
                                    <label for="{{ $lp['campo'] }}" class="cc-label mb-0">{{ __($lp['etiqueta']) }} <span class="cc-req" aria-hidden="true">*</span></label>
                                    {{-- Interruptor "sin antecedentes": llena el campo por la persona. El texto
                                         libre sigue disponible para quien SÍ tiene qué declarar. --}}
                                    <label class="cc-none" title="{{ __('health.h_none_hint') }}">
                                        <input type="checkbox" class="form-check-input" data-none-for="{{ $lp['campo'] }}" data-none-value="{{ __('health.h_none_value') }}">
                                        <span>{{ __('health.h_none_switch') }}</span>
                                    </label>
                                </div>
                                <textarea class="form-control cc-control @error($lp['campo']) is-invalid @enderror" name="{{ $lp['campo'] }}" id="{{ $lp['campo'] }}" rows="{{ $lp['filas'] }}" placeholder="{{ __($lp['ayuda']) }}" required>{{ old($lp['campo']) }}</textarea>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ===== Antecedentes heredo-familiares ===== --}}
        @php
            // Los índices 3..7 son los padecimientos; 1 y 2 (vivo/fallecido) se pintan aparte
            // porque son EXCLUYENTES entre sí y el resto no.
            $padecimientos = [
                3 => 'health.c_diabetes',
                4 => 'health.c_hypertension',
                5 => 'health.c_heart',
                6 => 'health.c_kidney',
                7 => 'health.c_cancer',
            ];
            $lineas = [
                ['prefijo' => 'momdat', 'titulo' => 'health.s_mother'],
                ['prefijo' => 'daddat', 'titulo' => 'health.s_father'],
            ];
        @endphp
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('health.s_family') }}</h2>
                    <p class="cc-form-card__sub">{{ __('health.s_family_sub') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                @foreach($lineas as $i => $linea)
                    @if($i > 0)
                        <hr style="border:0; border-top:1px solid var(--stroke, var(--border)); margin:1rem 0;">
                    @endif
                    <div class="cc-group-title">
                        @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-ico-14', 'label' => null])
                        {{ __($linea['titulo']) }}
                    </div>

                    {{-- Vivo/Fallecido: EXCLUYENTES. Se marcan como grupo con data-exclusive; el JS
                         apaga el hermano y el servidor lo vuelve a resolver (formulario::EXCLUSIVOS)
                         por si el navegador no ejecutó el script. Dejar los DOS sin marcar sigue
                         siendo válido: significa "no lo sé", que es lo que decían antes. --}}
                    <div class="row g-2 mb-2" data-exclusive="{{ $linea['prefijo'] }}">
                        <div class="col-12 col-sm-6 col-lg-4">
                            <label class="cc-check">
                                <input class="form-check-input" name="{{ $linea['prefijo'] }}1" type="checkbox" @if(old($linea['prefijo'].'1')) checked @endif/>
                                <span class="form-check-label">{{ __('health.c_alive') }}</span>
                            </label>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-4">
                            <label class="cc-check">
                                <input class="form-check-input" name="{{ $linea['prefijo'] }}2" type="checkbox" @if(old($linea['prefijo'].'2')) checked @endif/>
                                <span class="form-check-label">{{ __('health.c_dead') }}</span>
                            </label>
                        </div>
                        <div class="col-12 col-lg-4 d-flex align-items-center">
                            <span class="cc-help">{{ __('health.h_alive_dead') }}</span>
                        </div>
                    </div>

                    <div class="row g-2">
                        {{-- Sano (índice 8): SEPARADO de "Vivo" (2026-08-12). Antes iban juntos en el
                             índice 1 ("Vivo/Sano"). Encabeza los padecimientos: sano = sin condiciones. --}}
                        <div class="col-12 col-sm-6 col-lg-4">
                            <label class="cc-check">
                                <input class="form-check-input" name="{{ $linea['prefijo'] }}8" type="checkbox" @if(old($linea['prefijo'].'8')) checked @endif/>
                                <span class="form-check-label">{{ __('health.c_healthy') }}</span>
                            </label>
                        </div>
                        @foreach($padecimientos as $idx => $clave)
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="cc-check">
                                    <input class="form-check-input" name="{{ $linea['prefijo'] }}{{ $idx }}" type="checkbox" @if(old($linea['prefijo'].$idx)) checked @endif/>
                                    <span class="form-check-label">{{ __($clave) }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ===== Personales no patológicos ===== --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('health.s_habits') }}</h2>
                    <p class="cc-form-card__sub">{{ __('health.s_habits_sub') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="row g-2">
                    @foreach(['pers_nopat1' => 'health.c_tobacco', 'pers_nopat2' => 'health.c_alcohol', 'pers_nopat3' => 'health.c_drugs'] as $campo => $clave)
                        <div class="col-12 col-sm-6 col-lg-4">
                            <label class="cc-check">
                                <input class="form-check-input" name="{{ $campo }}" type="checkbox" @if(old($campo)) checked @endif/>
                                <span class="form-check-label">{{ __($clave) }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ===== Vacunación ===== --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('health.s_vaccines') }}</h2>
                    <p class="cc-form-card__sub">{{ __('health.s_vaccines_sub') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="row g-2">
                    @foreach(['vacci1' => 'health.c_covid', 'vacci2' => 'health.c_flu', 'vacci3' => 'health.c_tetanus', 'vacci4' => 'health.c_pneumococcus', 'vacci5' => 'health.c_hepatitis'] as $campo => $clave)
                        <div class="col-12 col-sm-6 col-lg-4">
                            <label class="cc-check">
                                <input class="form-check-input" name="{{ $campo }}" id="{{ $campo }}" type="checkbox" @if(old($campo)) checked @endif/>
                                <span class="form-check-label">{{ __($clave) }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>

                {{-- La casilla de influenza afirma "no mayor a un año": sin fecha, un sí de hace
                     tres años se lee igual que uno de hace un mes. Se revela al marcarla. --}}
                @if(\App\Models\formulario::soportaFechaInfluenza())
                    <div class="cc-reveal mt-3 @if(old('vacci2')) is-on @endif" id="vacci2_date_wrap">
                        <div class="cc-field" style="max-width: 320px;">
                            <label for="vacci2_date" class="cc-label">{{ __('health.f_flu_date') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input class="form-control cc-control @error('vacci2_date') is-invalid @enderror" type="date" name="vacci2_date" id="vacci2_date" max="{{ now()->format('Y-m-d') }}" value="{{ old('vacci2_date') }}"/>
                            <span class="cc-help">{{ __('health.h_flu_date') }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== Gineco-obstétricos (solo si aplica) ===== --}}
        @if ($user->sex === 'F')
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'heart-pulse', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('health.s_gyneco') }}</h2>
                    <p class="cc-form-card__sub">{{ __('health.s_gyneco_sub') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="rythm" class="cc-label">{{ __('health.f_rythm') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input class="form-control cc-control @error('rythm') is-invalid @enderror" placeholder="{{ __('health.f_rythm_ph') }}" name="rythm" id="rythm" type="text" value="{{ old('rythm') }}" required/>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="pregnant" class="cc-label">{{ __('health.f_pregnant') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input class="form-control cc-control @error('pregnant') is-invalid @enderror" placeholder="{{ __('health.f_pregnant_ph') }}" name="pregnant" id="pregnant" type="text" value="{{ old('pregnant') }}" required/>
                        </div>
                    </div>
                </div>

                <div class="cc-group-title mt-3">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-14', 'label' => null])
                    {{ __('health.s_prevention') }}
                </div>
                <div class="row g-2">
                    @foreach(['prevent1' => 'health.c_pap', 'prevent2' => 'health.c_mammography'] as $campo => $clave)
                        <div class="col-12 col-sm-6">
                            <label class="cc-check">
                                <input class="form-check-input" name="{{ $campo }}" type="checkbox" @if(old($campo)) checked @endif/>
                                <span class="form-check-label">{{ __($clave) }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                {{ __('health.save') }}
            </button>
        </div>
    </form>

</div>

@push('scripts')
<script>
(function () {
    'use strict';

    // --- Hospitalizaciones: revela tantos campos como haya declarado la persona. -------------
    var selectorHosp = document.getElementById('hospitals');
    var camposHosp = document.querySelectorAll('.hospi');
    function pintarHosp() {
        var cuantas = parseInt(selectorHosp.value, 10) || 0;
        for (var i = 0; i < camposHosp.length; i++) {
            var n = parseInt(camposHosp[i].getAttribute('data-hospi'), 10);
            camposHosp[i].classList.toggle('is-on', n <= cuantas);
        }
    }
    if (selectorHosp) {
        selectorHosp.addEventListener('change', pintarHosp);
        pintarHosp(); // al volver del servidor con errores, repone lo ya declarado
    }

    // --- "Sin antecedentes": llena el campo y lo deja en solo-lectura. -----------------------
    // readonly (no disabled) a propósito: un campo deshabilitado NO se envía, y el valor
    // "Ninguna" tiene que llegar al servidor — es una declaración, no una omisión.
    var interruptores = document.querySelectorAll('[data-none-for]');
    for (var k = 0; k < interruptores.length; k++) {
        (function (sw) {
            var campo = document.getElementById(sw.getAttribute('data-none-for'));
            if (!campo) { return; }
            var texto = sw.getAttribute('data-none-value') || 'Ninguna';
            function aplicar() {
                if (sw.checked) {
                    sw.setAttribute('data-prev', campo.value);
                    campo.value = texto;
                    campo.readOnly = true;
                } else {
                    campo.value = sw.getAttribute('data-prev') || '';
                    campo.readOnly = false;
                    campo.focus();
                }
            }
            sw.addEventListener('change', aplicar);
            // Si el formulario rebotó del servidor con el valor ya puesto, refleja el estado.
            if (campo.value.trim().toLowerCase() === texto.trim().toLowerCase()) {
                sw.checked = true;
                campo.readOnly = true;
            }
        })(interruptores[k]);
    }

    // --- Vivo / Fallecido: excluyentes. ------------------------------------------------------
    // El servidor NO confía en esto (formulario::EXCLUSIVOS lo vuelve a resolver): un POST
    // directo no ejecuta JavaScript.
    var grupos = document.querySelectorAll('[data-exclusive]');
    for (var g = 0; g < grupos.length; g++) {
        (function (grupo) {
            var prefijo = grupo.getAttribute('data-exclusive');
            var vivo = grupo.querySelector('[name="' + prefijo + '1"]');
            var muerto = grupo.querySelector('[name="' + prefijo + '2"]');
            if (!vivo || !muerto) { return; }
            vivo.addEventListener('change', function () { if (vivo.checked) { muerto.checked = false; } });
            muerto.addEventListener('change', function () { if (muerto.checked) { vivo.checked = false; } });
        })(grupos[g]);
    }

    // --- Influenza: la fecha aparece al marcar la casilla. -----------------------------------
    var casillaFlu = document.getElementById('vacci2');
    var cajaFecha = document.getElementById('vacci2_date_wrap');
    if (casillaFlu && cajaFecha) {
        var campoFecha = document.getElementById('vacci2_date');
        casillaFlu.addEventListener('change', function () {
            cajaFecha.classList.toggle('is-on', casillaFlu.checked);
            if (!casillaFlu.checked && campoFecha) { campoFecha.value = ''; }
        });
        cajaFecha.classList.toggle('is-on', casillaFlu.checked);
    }
})();
</script>
@endpush

@endsection
