@extends('layouts.app')

@section('content')

{{-- ANEXO AL EXPEDIENTE CLÍNICO — sólo médico (2026-07-24 · PIEZA 3, corrida 2/2).

     Cada campo llega con su VALOR VIGENTE ya puesto. El médico cambia lo que tiene que cambiar y
     el servidor guarda ÚNICAMENTE la diferencia (StoreHealthAddendumRequest::cambiosRespectoA):
     lo que no se toca, no se anexa. Así el anexo dice exactamente qué cambió, sin que el médico
     tenga que marcar campos ni recordar qué había antes.

     El expediente original NO se toca: conserva su folio, su hash y su QR. --}}

@include('componentes._form-kit')

@push('styles')
<style>
    .cc-check { display:flex; align-items:center; gap:.55rem; width:100%; min-height:44px; margin:0;
        padding:.45rem .7rem; border:1px solid var(--stroke-2, var(--border));
        border-radius:var(--radius-sm, 11px); background:var(--surface); cursor:pointer; }
    .cc-check .form-check-input { flex:none; margin:0; float:none; }
    .cc-check .form-check-label { margin:0; color:var(--text); font-size:.9rem; line-height:1.3; cursor:pointer; }
    .anx-warn { border:1px solid color-mix(in srgb, var(--warn, #b45309) 35%, transparent);
        background: color-mix(in srgb, var(--warn, #b45309) 8%, transparent);
        border-radius: var(--radius-sm, 11px); padding:.85rem 1rem; font-size:.88rem; line-height:1.5; }
</style>
@endpush

<div class="container py-4" style="max-width: 900px;">

    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'file-plus', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('health.addendum_title') }}</h1>
            <div class="cc-muted small">
                {{ trim($paciente->name . ' ' . $paciente->lname) }}
                · {{ $expediente->folio() }}
                · {{ __('health.addendum_declared_on', ['fecha' => $expediente->fechaLlenado()]) }}
            </div>
        </div>
    </div>

    @include('componentes._form-feedback')

    {{-- Por qué esto existe y qué implica firmarlo. Va ARRIBA del formulario: quien va a anexar
         tiene que leerlo antes de escribir, no después de enviar. --}}
    <div class="anx-warn mb-4">
        {{ __('health.addendum_notice') }}
    </div>

    <form method="POST" action="{{ route('expediente.anexo.store', $paciente->id) }}">
        @csrf

        {{-- ===== Motivo y nota ===== --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'clipboard-check', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('health.addendum_s_why') }}</h2>
                    <p class="cc-form-card__sub">{{ __('health.addendum_s_why_sub') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="cc-field">
                    <label for="reason" class="cc-label">{{ __('health.addendum_f_reason') }} <span class="cc-req" aria-hidden="true">*</span></label>
                    <select class="form-select cc-select @error('reason') is-invalid @enderror" name="reason" id="reason" required>
                        @foreach($motivos as $clave => $etiqueta)
                            <option value="{{ $clave }}" @if(old('reason') === $clave) selected @endif>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cc-field mt-3">
                    <label for="notes" class="cc-label">{{ __('health.addendum_f_notes') }} <span class="cc-req" aria-hidden="true">*</span></label>
                    <textarea class="form-control cc-control @error('notes') is-invalid @enderror" name="notes" id="notes" rows="4"
                              placeholder="{{ __('health.addendum_f_notes_ph') }}" required>{{ old('notes') }}</textarea>
                    <span class="cc-help">{{ __('health.addendum_f_notes_help') }}</span>
                </div>
            </div>
        </div>

        {{-- ===== Los datos ===== --}}
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('health.addendum_s_data') }}</h2>
                    <p class="cc-form-card__sub">{{ __('health.addendum_s_data_sub') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                @php
                    $v = function ($campo) use ($valores) {
                        return old($campo, isset($valores->$campo) ? $valores->$campo : null);
                    };
                    $marcada = function ($campo) use ($valores) {
                        return old() ? (bool) old($campo) : ! empty($valores->$campo);
                    };
                @endphp

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="cc-field">
                            <label for="blod_type" class="cc-label">{{ __('health.f_blood') }}</label>
                            <select class="form-select cc-select @error('blod_type') is-invalid @enderror" name="blod_type" id="blod_type">
                                <option value="">{{ __('health.f_blood_choose') }}</option>
                                @foreach(\App\Http\Requests\StoreHealthRecordRequest::BLOOD_TYPES as $tipo)
                                    <option value="{{ $tipo }}" @if($v('blod_type') === $tipo) selected @endif>{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="cc-field">
                            <label for="height" class="cc-label">{{ __('health.f_weight') }} <span class="cc-unit">{{ __('health.f_weight_unit') }}</span></label>
                            <input class="form-control cc-control @error('height') is-invalid @enderror" type="text" inputmode="decimal" name="height" id="height" value="{{ $v('height') }}"/>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="cc-field">
                            <label for="size" class="cc-label">{{ __('health.f_size') }} <span class="cc-unit">{{ __('health.f_size_unit') }}</span></label>
                            <input class="form-control cc-control @error('size') is-invalid @enderror" type="text" inputmode="decimal" name="size" id="size" value="{{ $v('size') }}"/>
                        </div>
                    </div>

                    @foreach(['alergy' => 'health.f_allergy', 'pathology' => 'health.f_pathology', 'cirugy' => 'health.f_surgery', 'trauma' => 'health.f_trauma'] as $campo => $clave)
                        <div class="col-12">
                            <div class="cc-field">
                                <label for="{{ $campo }}" class="cc-label">{{ __($clave) }}</label>
                                <textarea class="form-control cc-control @error($campo) is-invalid @enderror" name="{{ $campo }}" id="{{ $campo }}" rows="2">{{ $v($campo) }}</textarea>
                            </div>
                        </div>
                    @endforeach

                    @foreach(['c_emer' => 'health.f_contact', 'relation' => 'health.f_relation', 'p_emer' => 'health.f_phone'] as $campo => $clave)
                        <div class="col-12 col-md-4">
                            <div class="cc-field">
                                <label for="{{ $campo }}" class="cc-label">{{ __($clave) }}</label>
                                <input class="form-control cc-control @error($campo) is-invalid @enderror" type="text" name="{{ $campo }}" id="{{ $campo }}" value="{{ $v($campo) }}"/>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="cc-group-title mt-4">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-14', 'label' => null])
                    {{ __('health.s_vaccines') }}
                </div>
                <div class="row g-2">
                    @foreach(['vacci1' => 'health.c_covid', 'vacci2' => 'health.c_flu', 'vacci3' => 'health.c_tetanus', 'vacci4' => 'health.c_pneumococcus', 'vacci5' => 'health.c_hepatitis'] as $campo => $clave)
                        <div class="col-12 col-sm-6 col-lg-4">
                            <label class="cc-check">
                                <input class="form-check-input" type="checkbox" name="{{ $campo }}" value="1" @if($marcada($campo)) checked @endif/>
                                <span class="form-check-label">{{ __($clave) }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
                @if(\App\Models\formulario::soportaFechaInfluenza())
                    <div class="cc-field mt-3" style="max-width:320px">
                        <label for="vacci2_date" class="cc-label">{{ __('health.f_flu_date') }}</label>
                        <input class="form-control cc-control @error('vacci2_date') is-invalid @enderror" type="date" name="vacci2_date" id="vacci2_date"
                               max="{{ now()->format('Y-m-d') }}"
                               value="{{ old('vacci2_date', (isset($valores->vacci2_date) && $valores->vacci2_date) ? \Carbon\Carbon::parse($valores->vacci2_date)->format('Y-m-d') : '') }}"/>
                    </div>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2">
            <a href="{{ url('/historialWR/' . $paciente->id) }}" class="btn btn-outline-secondary">{{ __('health.addendum_cancel') }}</a>
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'file-check', 'class' => 'cc-ico-18', 'label' => null])
                {{ __('health.addendum_save') }}
            </button>
        </div>
    </form>

</div>
@endsection
