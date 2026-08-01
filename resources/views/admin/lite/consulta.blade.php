@extends('layouts.app')

@section('content')
{{-- CONSULTA DE PACIENTE SIN CUENTA (no-crew). (2026-07-31) Ahora usa el MISMO formulario rico que
     la consulta de crew, vía el parcial compartido componentes/_consulta-campos — se acabó la vista
     pobre con inputs planos. Lo PROPIO de lite se queda aquí: el banner "sin expediente" y el aviso
     de cédula. No finge secciones de expediente/antecedentes que aquí no existen. --}}
@include('componentes._form-kit')

<div class="cc-consulta container-fluid py-4" style="max-width: 1080px;">

    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">{{ $paciente->displayName() }}</h1>
                <div class="cc-muted small">
                    @php $edad = $paciente->ageDisplay(); @endphp
                    @if($edad !== null){{ $edad }} años · @endif
                    @if($paciente->sex){{ ['F'=>'Femenino','M'=>'Masculino','O'=>'Otro'][$paciente->sex] ?? $paciente->sex }} · @endif
                    {{ $paciente->area ?: 'Sin área' }}
                    @if($paciente->phone) · Tel. {{ $paciente->phone }}@endif
                    @if($paciente->origin) · Procedencia: {{ $paciente->origin }}@endif
                </div>
            </div>
        </div>
        <a href="{{ url('/medicocrud') }}" class="cc-btn-ghost">
            @include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico-16', 'label' => null])
            {{ __('Volver') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm d-flex align-items-start gap-2">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-18 mt-1', 'label' => null])
            <div>
                <strong>{{ __('Revisa los errores') }}:</strong>
                <ul class="mb-0 mt-1 small">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
    @endif
    @include('componentes._form-feedback')

    {{-- BANNER OBLIGATORIO: se atiende SIN expediente clínico. Mismo texto que llevará el PDF. --}}
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-18 mt-1', 'label' => null])
        <span style="font-size:.9rem"><strong>{{ __('Paciente sin expediente clínico') }}</strong> — {{ __('no se revisaron antecedentes ni alergias declaradas.') }}</span>
    </div>

    @unless($tieneCedula)
        <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-18 mt-1', 'label' => null])
            <span style="font-size:.9rem">{{ __('No tienes una') }} <strong>{{ __('cédula profesional registrada') }}</strong>.
                {{ __('Puedes registrar la atención, pero') }} <strong>{{ __('no podrás emitir el documento sellado') }}</strong>
                {{ __('hasta que la registres en tu perfil: el documento clínico se firma con ella.') }}</span>
        </div>
    @endunless

    {{-- CINTILLO DE TRATAMIENTO PREVIO — COMPONENTE COMPARTIDO. El MISMO que la consulta de crew:
         la última atención (une la persona y sus duplicados fundidos), legible y sin recorte. --}}
    @include('componentes._cintillo-previo', ['prev' => $prevConsult ?? null])

    {{-- ===== Datos de la consulta — PARCIAL COMPARTIDO (2026-07-31) =====
         Idéntico al de crew. Sin liga a accidente (no se pasa $recentInjuries): un paciente lite no
         inyecta al DSR. La cédula obligatoria la sigue exigiendo storeLite() al emitir. --}}
    <form method="POST" action="{{ route('lite.consulta.store', $paciente->id) }}">
        @csrf
        @include('componentes._consulta-campos', [
            'catalog'           => $catalog,
            'presentations'     => $presentations,
            'managementOptions' => $managementOptions ?? null,
            'cancelUrl'         => url('/medicocrud'),
            'submitLabel'       => __('Registrar y sellar consulta'),
        ])
    </form>

</div>
@endsection
