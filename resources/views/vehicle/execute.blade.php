@extends('layouts.app')
@include('componentes._confirm-submit')
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

    // BORRADOR (§1): retoma respuestas/fotos guardadas por el AUTOR. old() gana sobre el borrador
    // tras un error de validación.
    $draft        = $draft ?? null;
    $draftAnswers = $draft ? (array) $draft->answers : [];
    $draftPhotos  = $draft ? (array) $draft->point_photos : [];
    $ansVal = function ($code) use ($draftAnswers) {
        return old('answers.' . $code, $draftAnswers[$code] ?? null);
    };
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

            {{-- Borrador retomado (§1): se puede descartar. El form de descarte va FUERA del form principal. --}}
            @if ($draft)
                <div class="alert alert-secondary d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>@include('componentes._icon', ['name' => 'clock', 'label' => null]) {{ __('Retomando un borrador guardado') }} · {{ optional($draft->updated_at)->format('d/m/Y H:i') }}</span>
                    <form method="post" action="{{ route('transport.inspect.draft.discard', $vehicle) }}" data-confirm="{{ __('¿Descartar el borrador? Se perderá lo capturado.') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Descartar borrador') }}</button>
                    </form>
                </div>
            @endif

            <form method="post" action="{{ route('transport.inspect.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                @if ($isReeval)<input type="hidden" name="reeval" value="{{ $origin->id }}">@endif

                @foreach ($groups as $mod => $rows)
                    @php
                        $modTotal = count($rows);
                        $modDone  = 0;
                        foreach ($rows as $rp) { if (in_array($ansVal($rp->code), ['ok', 'fail'], true)) { $modDone++; } }
                    @endphp
                    <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                        <h6 class="mb-3 text-uppercase text-muted d-flex align-items-center gap-2" style="letter-spacing:.06em;font-size:.72rem">
                            {{ $moduleNames[$mod] ?? ucfirst($mod) }}
                            <span class="badge {{ $modDone === $modTotal ? 'bg-success' : 'bg-secondary' }}">{{ $modDone }}/{{ $modTotal }}</span>
                        </h6>
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
                                            <input type="radio" class="btn-check" name="answers[{{ $p->code }}]" id="ok-{{ $p->code }}" value="ok" autocomplete="off" @checked($ansVal($p->code) === 'ok')>
                                            <label class="btn btn-outline-success" for="ok-{{ $p->code }}">{{ __('Cumple') }}</label>
                                            <input type="radio" class="btn-check" name="answers[{{ $p->code }}]" id="bad-{{ $p->code }}" value="fail" autocomplete="off" @checked($ansVal($p->code) === 'fail')>
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
                                    <input type="file" name="point_photos[{{ $p->code }}]" class="form-control form-control-sm" accept="image/*,.heic,.heif"
                                           title="{{ __('Foto del punto (obligatoria si reprueba o si el punto la exige)') }}">
                                    @if (! empty($draftPhotos[$p->code]))
                                        <div class="form-text text-success">@include('componentes._icon', ['name' => 'file-check', 'label' => null]) {{ __('Foto guardada en el borrador (no hace falta re-subirla).') }}</div>
                                    @endif
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
                            <input type="number" name="km" value="{{ old('km', ($draft && $draft->km !== null) ? $draft->km : $vehicle->initial_km) }}" class="form-control" min="0" @if($isReeval) readonly @endif>
                            @if ($isReeval)<div class="form-text">{{ __('Se conserva el de la primera inspección.') }}</div>@endif
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">{{ __('Foto general de la unidad (opcional)') }}</label>
                            <input type="file" name="unit_photo" class="form-control" accept="image/*,.heic,.heif">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Resumen y recomendaciones (opcional)') }}</label>
                            <textarea name="observations" class="form-control" rows="3" maxlength="4000">{{ old('observations', $draft->observations ?? '') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-5 flex-wrap">
                    <button type="submit" class="btn btn-crew-accent">{{ __('Cerrar y sellar acta') }}</button>
                    {{-- Guardar borrador: mismo form, otra acción. formnovalidate = no exige checklist completo. --}}
                    <button type="submit" class="btn btn-crew-soft" formaction="{{ route('transport.inspect.draft') }}" formnovalidate>{{ __('Guardar borrador') }}</button>
                    <a href="{{ route('transport.vehicle.show', $vehicle) }}" class="btn btn-crew-soft">{{ __('Cancelar') }}</a>
                </div>
                <p class="text-muted small mb-5">{{ __('Guardar borrador conserva respuestas y fotos en el servidor; puedes retomarlo desde otro dispositivo. Cerrar y sellar exige el checklist completo y ya no se edita.') }}</p>
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
