@extends('layouts.app')
@section('title', 'Nueva Consulta Médica - ' . ($branding['brand_name'] ?? 'CrewCare'))
@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

{{-- (2026-07-31) La tarjeta "Datos de la consulta", su CSS y su JS viven ahora en el parcial
     compartido componentes/_consulta-campos — FUENTE ÚNICA con la consulta de paciente sin cuenta
     (admin/lite/consulta). Aquí sólo queda lo PROPIO del crew: ficha del expediente, antecedentes,
     avisos de intake y liga a accidente. --}}

<div class="cc-consulta container-fluid py-4" style="max-width: 1080px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">{{ __('Nueva Consulta Médica') }}</h1>
                <div class="cc-muted small">{{ __('Paciente') }}: <strong>{{ $usuario->name }} {{ $usuario->lname }} {{ $usuario->lname2 }}</strong></div>
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

    {{-- ===== Aviso de expediente (2026-07-24 · PASO 3/3, item 1) =====
         La consulta YA NO se bloquea por falta de cuestionario — nunca se niega atención por un
         trámite. Lo que sí hace el sistema es DECÍRSELO al médico en el momento y dejar constancia
         en la consulta (columna without_record). Dos estados distintos, dos avisos distintos:
         sin expediente (a ciegas) vs expediente sin alergias capturadas (media ceguera). --}}
    @if(($intakeState ?? 'ok') === 'missing')
        <div class="alert alert-warning shadow-sm d-flex align-items-start gap-2" role="alert">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-18 mt-1', 'label' => null])
            <div>
                <strong>{{ __('Atendiendo sin expediente clínico') }}</strong>
                <div class="small mb-0">{{ __('Esta persona no ha llenado el cuestionario médico: no hay información de alergias ni antecedentes. Puedes registrar la consulta; quedará marcada como atendida sin expediente.') }}</div>
            </div>
        </div>
    @elseif(($intakeState ?? 'ok') === 'incomplete')
        <div class="alert alert-warning shadow-sm d-flex align-items-start gap-2" role="alert">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-18 mt-1', 'label' => null])
            <div>
                <strong>{{ __('Expediente sin alergias registradas') }}</strong>
                <div class="small mb-0">{{ __('Hay expediente, pero el campo de alergias está vacío. No asumas que no tiene alergias: no se capturaron.') }}</div>
            </div>
        </div>
    @endif

    {{-- (2026-07-24 · PIEZA 3) ACTUALIZAR EL EXPEDIENTE, DESDE DONDE SE DETECTA EL ERROR.
         El momento real en que se descubre un dato mal capturado es ÉSTE: el médico abre la
         consulta, lee "alergias: ninguna" y la persona le refiere una. Tener el acceso sólo en
         el historial obligaba a salir de la consulta, buscar al paciente otra vez y volver —
         con la corrección a medio pensar. Aquí el anexo queda a un clic del dato que lo motiva.

         El expediente NO se edita: esto abre el formulario de ANEXO. Y ocultar o mostrar este
         enlace no autoriza nada — HealthRecordAddendumController vuelve a exigir rol médico y
         alcance por departamento en las dos rutas. --}}
    @if($datos && auth()->check() && auth()->user()->isMedic() && \App\Models\HealthRecordAddendum::supported())
        <div class="mb-3">
            <a href="{{ route('expediente.anexo.create', $usuario->id) }}" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'file-plus', 'class' => 'cc-ico-16', 'label' => null])
                {{ __('health.trace_add') }}
            </a>
            <span class="cc-help d-block mt-1">{{ __('health.trace_add_help') }}</span>
        </div>
    @endif

    @php $form = $formulario->first(); @endphp

    {{-- La ficha sólo se pinta si hay expediente: antes el controlador redirigía cuando faltaba,
         así que la vista daba por hecho que $datos existía y reventaba con null. --}}
    @if($datos)
    {{-- ===== Ficha del paciente ===== --}}
    <div class="cc-form-card">
        <div class="cc-form-card__head">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div class="cc-form-card__titles">
                <h2 class="cc-form-card__title">{{ __('Ficha del paciente') }}</h2>
                <p class="cc-form-card__sub">{{ __('Datos de referencia del expediente (solo lectura)') }}</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            <div class="cc-info">
                <div class="cc-info-item">
                    <span class="cc-info-lbl">{{ __('Nombre') }}</span>
                    <span class="cc-info-val">{{ $usuario->name }} {{ $usuario->lname }} {{ $usuario->lname2 }}</span>
                </div>
                <div class="cc-info-item">
                    <span class="cc-info-lbl">{{ __('Puesto') }}</span>
                    <span class="cc-info-val">{{ $usuario->positionName() }}</span>
                </div>
                <div class="cc-info-item">
                    <span class="cc-info-lbl">{{ __('Edad') }}</span>
                    <span class="cc-info-val">{{ \Carbon\Carbon::parse($datos->borndate)->age }} {{ __('años') }}</span>
                </div>
                <div class="cc-info-item">
                    <span class="cc-info-lbl">{{ __('Teléfono') }}</span>
                    <span class="cc-info-val">{{ $usuario->phone }}</span>
                </div>
                <div class="cc-info-item cc-info-item--wide">
                    <span class="cc-info-lbl">{{ __('Email') }}</span>
                    <span class="cc-info-val">{{ $usuario->email }}</span>
                </div>
                @if($form)
                    <div class="cc-info-item">
                        <span class="cc-info-lbl">{{ __('Tipo de sangre') }}</span>
                        <span class="cc-info-val">{{ $form->blod_type ?: '—' }}</span>
                    </div>
                    <div class="cc-info-item">
                        <span class="cc-info-lbl">{{ __('IMC') }}</span>
                        <span class="cc-info-val">{{ empty($form->size) ? 'N/D' : round($form->height / ($form->size * $form->size), 1) }}</span>
                    </div>
                    <div class="cc-info-item">
                        <span class="cc-info-lbl">{{ __('Peso') }}</span>
                        <span class="cc-info-val">{{ $form->height }} kg</span>
                    </div>
                    <div class="cc-info-item">
                        <span class="cc-info-lbl">{{ __('Estatura') }}</span>
                        <span class="cc-info-val">{{ $form->size }} m</span>
                    </div>
                    <div class="cc-info-item cc-info-item--wide">
                        <span class="cc-info-lbl">{{ __('Contacto de emergencia') }}</span>
                        <span class="cc-info-val">{{ $form->c_emer }} ({{ $form->relation }}) — {{ $form->p_emer }}</span>
                    </div>
                    @if(!empty($form->alergy))
                    <div class="cc-info-item cc-info-item--wide">
                        <span class="cc-info-lbl">{{ __('Alergias') }}</span>
                        <span>
                            <span class="cc-chip cc-chip--danger">
                                @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-14', 'label' => null])
                                {{ $form->alergy }}
                            </span>
                        </span>
                    </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- ===== Antecedentes (colapsable) ===== --}}
    @if($form)
    <div class="cc-form-card">
        <button class="cc-collapse-head" type="button" data-bs-toggle="collapse" data-bs-target="#antecedentes" aria-expanded="false" aria-controls="antecedentes">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <span class="cc-form-card__titles">
                <span class="cc-form-card__title d-block">{{ __('Antecedentes, no patológicos y vacunación') }}</span>
                <span class="cc-form-card__sub d-block">{{ __('Toca para ver el detalle') }}</span>
            </span>
            @include('componentes._icon', ['name' => 'chevron-down', 'class' => 'cc-collapse-caret cc-ico-18', 'label' => null])
        </button>
        <div class="collapse" id="antecedentes">
            <div class="cc-form-card__body border-top" style="border-color: var(--stroke, var(--border)) !important;">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="cc-group-title">
                            @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-14', 'label' => null])
                            {{ __('Patológicos') }}
                        </div>
                        <div class="cc-info">
                            <div class="cc-info-item cc-info-item--wide"><span class="cc-info-lbl">{{ __('Cirugías') }}</span><span class="cc-info-val">{{ $form->cirugy ?: '—' }}</span></div>
                            <div class="cc-info-item cc-info-item--wide"><span class="cc-info-lbl">{{ __('Patológicas') }}</span><span class="cc-info-val">{{ $form->pathology ?: '—' }}</span></div>
                            <div class="cc-info-item cc-info-item--wide"><span class="cc-info-lbl">{{ __('Traumáticos') }}</span><span class="cc-info-val">{{ $form->trauma ?: '—' }}</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cc-group-title">
                            @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-ico-14', 'label' => null])
                            {{ __('No patológicos') }}
                        </div>
                        <div class="cc-info">
                            <div class="cc-info-item cc-info-item--wide"><span class="cc-info-lbl">{{ __('Tabaquismo') }}</span><span class="cc-info-val">{{ ($form->pers_nopat1 ?? 0) === 1 ? __('Sí') : 'No' }}</span></div>
                            <div class="cc-info-item cc-info-item--wide"><span class="cc-info-lbl">{{ __('Alcoholismo') }}</span><span class="cc-info-val">{{ ($form->pers_nopat2 ?? 0) === 1 ? __('Sí') : 'No' }}</span></div>
                            <div class="cc-info-item cc-info-item--wide"><span class="cc-info-lbl">{{ __('Toxicomanías') }}</span><span class="cc-info-val">{{ ($form->pers_nopat3 ?? 0) === 1 ? __('Sí') : 'No' }}</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cc-group-title">
                            @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-14', 'label' => null])
                            {{ __('Vacunación') }}
                        </div>
                        <div class="cc-info">
                            <div class="cc-info-item cc-info-item--wide"><span class="cc-info-lbl">COVID-19</span><span class="cc-info-val">{{ ($form->vacci1 ?? 0) === 1 ? __('Sí') : 'No' }}</span></div>
                            <div class="cc-info-item cc-info-item--wide"><span class="cc-info-lbl">{{ __('Influenza') }}</span><span class="cc-info-val">{{ ($form->vacci2 ?? 0) === 1 ? __('Sí') : 'No' }}</span></div>
                            <div class="cc-info-item cc-info-item--wide"><span class="cc-info-lbl">{{ __('Tétanos') }}</span><span class="cc-info-val">{{ ($form->vacci3 ?? 0) === 1 ? __('Sí') : 'No' }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endif {{-- cierra @if($datos): ficha + antecedentes sólo cuando hay expediente --}}

    {{-- ===== Cintillo de tratamiento previo — COMPONENTE COMPARTIDO (2026-07-25) =====
         SÓLO la última consulta del paciente, de CUALQUIER médico, legible y sin recorte (o el
         mensaje "sin atenciones previas"). El MISMO componente sirve a la consulta de paciente sin
         cuenta (admin.lite.consulta): una sola fuente, no dos copias que divergen. --}}
    @include('componentes._cintillo-previo', ['prev' => $prevConsult ?? null])

    {{-- ===== Datos de la consulta — PARCIAL COMPARTIDO (2026-07-31) =====
         El mismo formulario rico que la consulta de paciente sin cuenta. Los datos propios del crew
         (liga a accidente) viajan por $recentInjuries; lite no lo pasa, así que allá no se pinta. --}}
    <form method="POST" action="{{ route('cmedica.store', $usuario->id) }}">
        @csrf
        @include('componentes._consulta-campos', [
            'catalog'           => $catalog,
            'presentations'     => $presentations,
            'managementOptions' => $managementOptions ?? null,
            'recentInjuries'    => $recentInjuries ?? null,
            'cancelUrl'         => url('/medicocrud'),
            'submitLabel'       => __('Guardar consulta'),
        ])
    </form>
</div>
@endsection
