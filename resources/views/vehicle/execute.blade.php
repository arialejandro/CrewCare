@extends('layouts.app')
@section('content')
@php
    $classTag = ['critical' => ['Crítico', 'danger'], 'major' => ['Mayor', 'warning'], 'minor' => ['Menor', 'secondary']];
    $moduleNames = [
        'nucleo' => 'Núcleo', 'carga' => 'Carga', 'ocupacion' => 'Alta ocupación',
        'habitables' => 'Instalaciones habitables', 'energia' => 'Energía y aparatos',
        'agua' => 'Agua a bordo', 'remolque' => 'Remolque', 'electrica' => 'Tracción eléctrica',
    ];
    $groups = [];
    foreach ($points as $p) { $groups[$p->module][] = $p; }
    $originFailed = [];
    if (isset($origin) && $origin) {
        foreach ((array) $origin->checklist_snapshot as $s) {
            if (($s['answer'] ?? '') === 'fail') { $originFailed[$s['code']] = true; }
        }
    }
    $isReeval = isset($origin) && $origin;
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:960px">

        <div class="crew-header d-flex align-items-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ $isReeval ? __('Reevaluación de vehículo') : __('Verificar vehículo') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('En prep, con el vehículo detenido. Un punto sin contestar no cierra el acta.') }}</p>
                </div>
            </div>
            <a href="{{ route('transport.index') }}" class="btn btn-crew-soft">{{ __('Volver') }}</a>
        </div>

        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        {{-- Selector de vehículo (si no viene preseleccionado) --}}
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
            <form method="get" action="{{ route('transport.inspect.form') }}" class="row g-2 align-items-end">
                @if ($isReeval)<input type="hidden" name="reeval" value="{{ $origin->id }}">@endif
                <div class="col-md-9">
                    <label class="form-label mb-1 small text-muted">{{ __('Vehículo') }}</label>
                    <select name="vehicle_id" class="form-select js-typeahead" data-ta-placeholder="{{ __('Elige un vehículo…') }}">
                        <option value="">{{ __('— Elige un vehículo —') }}</option>
                        @foreach ($vehicles as $vh)
                            <option value="{{ $vh->id }}" @selected($vehicle && $vehicle->id === $vh->id)>{{ trim(($vh->make ?: '') . ' ' . ($vh->model ?: '')) ?: ($vh->type->name_es ?? '—') }} — {{ $vh->plate ?: __('sin placas') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><button type="submit" class="btn btn-crew-soft w-100">{{ __('Cargar checklist') }}</button></div>
            </form>
        </div>

        @if (! $vehicle)
            <p class="text-muted">{{ __('Elige un vehículo para cargar los puntos que le aplican por sus atributos.') }}</p>
        @elseif ($points->isEmpty())
            <div class="alert alert-warning">{{ __('Este vehículo no tiene puntos de verificación aplicables. Revisa sus atributos.') }}</div>
        @else
            @if ($isReeval)
                <div class="alert alert-info">{{ __('Reevaluación de') }} <strong>{{ $origin->folio() }}</strong>. {{ __('Los documentos ya validados y vigentes no se vuelven a pedir. Marca los puntos reparados.') }}</div>
            @endif

            <form method="post" action="{{ route('transport.inspect.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                @if ($isReeval)<input type="hidden" name="reeval" value="{{ $origin->id }}">@endif

                @foreach ($groups as $mod => $rows)
                    <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                        <h6 class="mb-3 text-uppercase text-muted" style="letter-spacing:.06em;font-size:.72rem">{{ $moduleNames[$mod] ?? ucfirst($mod) }}</h6>
                        @foreach ($rows as $p)
                            @php $ct = $classTag[$p->class] ?? ['Menor', 'secondary']; @endphp
                            <div class="py-2 border-bottom">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                    <div class="pe-md-3">
                                        <span class="fw-semibold small">{{ $p->text_es }}</span>
                                        <span class="badge bg-{{ $ct[1] }} ms-1">{{ $ct[0] }}</span>
                                        <span class="text-muted small ms-1 font-monospace">{{ $p->code }}</span>
                                        @if ($p->requires_photo)<span class="badge bg-light text-dark border ms-1">{{ __('Foto obligatoria') }}</span>@endif
                                        @if (! empty($originFailed[$p->code]))<span class="badge bg-warning text-dark ms-1">{{ __('Falló antes') }}</span>@endif
                                    </div>
                                    <div class="d-flex align-items-center gap-3 flex-shrink-0">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <input type="radio" class="btn-check" name="answers[{{ $p->code }}]" id="ok-{{ $p->code }}" value="ok" autocomplete="off">
                                            <label class="btn btn-outline-success" for="ok-{{ $p->code }}">{{ __('Cumple') }}</label>
                                            <input type="radio" class="btn-check" name="answers[{{ $p->code }}]" id="bad-{{ $p->code }}" value="fail" autocomplete="off">
                                            <label class="btn btn-outline-danger" for="bad-{{ $p->code }}">{{ __('Falla') }}</label>
                                        </div>
                                        @if ($isReeval)
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" name="reparado[{{ $p->code }}]" value="1" id="rep-{{ $p->code }}">
                                                <label class="form-check-label small" for="rep-{{ $p->code }}">{{ __('Reparado') }}</label>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <input type="file" name="point_photos[{{ $p->code }}]" class="form-control form-control-sm" accept="image/*"
                                           title="{{ __('Foto del punto (obligatoria si reprueba o si el punto la exige)') }}">
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                {{-- Cierre --}}
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Kilometraje') }}</label>
                            <input type="number" name="km" value="{{ old('km', $vehicle->initial_km) }}" class="form-control" min="0" @if($isReeval) readonly @endif>
                            @if ($isReeval)<div class="form-text">{{ __('Se conserva el de la primera inspección.') }}</div>@endif
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">{{ __('Foto general de la unidad (opcional)') }}</label>
                            <input type="file" name="unit_photo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Resumen y recomendaciones (opcional)') }}</label>
                            <textarea name="observations" class="form-control" rows="3" maxlength="4000">{{ old('observations') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-5">
                    <button type="submit" class="btn btn-crew-accent">{{ __('Cerrar y sellar acta') }}</button>
                    <a href="{{ route('transport.vehicle.show', $vehicle) }}" class="btn btn-crew-soft">{{ __('Cancelar') }}</a>
                </div>
            </form>
        @endif

    </div>
</div>

@push('scripts')
    @include('componentes._typeahead')
@endpush
@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
