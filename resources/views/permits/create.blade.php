@extends('layouts.app')
@section('content')
@php
    $points = $permit->points;
    $budget = is_array($permit->budget ?? null) ? $permit->budget : [];
    // Autorización externa: obligatoria (bloquea) u opcional (municipal, se captura si existe).
    $extRequired = (bool) $permit->ext_auth_mandatory;
    $hasExtAuth  = $extRequired || ! empty($permit->ext_auth_authority);
    $scopeLabel = [
        'indiferente'      => __('Vale por la jornada, sin importar dónde.'),
        'reverificacion'   => __('Vale mientras no cambie el sitio ni las condiciones; al mover se reverifican sólo los puntos sensibles.'),
        'ligado_al_sitio'  => __('Cambiar de sitio exige emisión nueva.'),
    ];
    $execLabel = ['safety' => __('Safety'), 'especialista' => __('Especialista')];
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 860px;">

        <a href="{{ route('permits.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-3" style="text-decoration:none;">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null]) {{ __('Volver a permisos') }}
        </a>

        <div class="d-flex align-items-start gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-check', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ $permit->name }}</h1>
                <p class="text-muted mb-0 small">{{ $permit->code }} · {{ $points->count() }} {{ __('puntos (todos compuerta)') }}@if(!is_null($shootDay)) · {{ __('Día') }} {{ $shootDay }}@endif</p>
            </div>
        </div>

        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @if ($permit->definition)
            <div class="alert alert-light border small">{{ $permit->definition }}</div>
        @endif

        {{-- Alcance de sitio: cómo se comportará la vigencia. --}}
        <div class="alert alert-info d-flex align-items-start gap-2 py-2 small">
            @include('componentes._icon', ['name' => 'map-pin', 'label' => null])
            <span><strong>{{ __('Alcance de sitio') }}:</strong> {{ $scopeLabel[$permit->site_scope] ?? $permit->site_scope }}</span>
        </div>

        @if (! empty($vigente))
            <div class="alert alert-warning d-flex align-items-center justify-content-between gap-2 py-2 flex-wrap">
                <span>{{ __('Ya hay un permiso vigente de este tipo hoy') }} ({{ $vigente->folio() }}).</span>
                <a href="{{ route('permits.show', $vigente->uuid) }}" class="btn btn-sm btn-outline-warning">{{ __('Ver') }}</a>
            </div>
        @endif

        <form method="post" action="{{ route('permits.store', $permit->id) }}" enctype="multipart/form-data">
            @csrf
            @if (! empty($prefill['tool_id']))<input type="hidden" name="tool_id" value="{{ $prefill['tool_id'] }}">@endif
            @if (! empty($supersedes))<input type="hidden" name="supersedes" value="{{ $supersedes }}">@endif

            {{-- ACTIVIDAD + SITIO + JORNADA --}}
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold">{{ __('Actividad que se autoriza') }} *</label>
                        <textarea name="activity_description" class="form-control" rows="2" maxlength="2000" required
                                  placeholder="{{ __('p. ej. corte y soldadura de estructura en el set B') }}">{{ old('activity_description', $prefill['activity'] ?? '') }}</textarea>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">{{ __('Sitio / locación') }} *</label>
                        <input type="text" name="site_label" class="form-control" maxlength="255" required
                               value="{{ old('site_label', $prefill['site'] ?? '') }}" placeholder="{{ __('dónde se ejecuta') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Día de rodaje') }}</label>
                        <input type="text" class="form-control" value="{{ is_null($shootDay) ? '—' : $shootDay }}" disabled>
                        <div class="form-text">{{ __('La vigencia se ata a la jornada (cruza la medianoche).') }}</div>
                    </div>
                    @if ($tool)
                        <div class="col-12">
                            <span class="insp-tag">{{ __('Disparado por') }}: {{ $tool->code }} · {{ $tool->name }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- AUTORIZACIÓN EXTERNA (Paso 2): DECLARACIÓN, no verificación. --}}
            @if ($hasExtAuth)
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3" style="border-left:4px solid {{ $extRequired ? '#b42318' : '#b45309' }} !important;">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        @include('componentes._icon', ['name' => 'stamp', 'label' => null])
                        <strong>{{ $extRequired ? __('Autorización externa OBLIGATORIA') : __('Autorización externa (opcional)') }}</strong>
                    </div>
                    <p class="small text-muted mb-3">
                        {{ __('La app NO verifica este documento. Es una DECLARACIÓN bajo la responsabilidad de quien la captura; el permiso lo dirá con esas palabras. Nunca afirma "autorización verificada".') }}
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Autoridad') }} {!! $extRequired ? '*' : '' !!}</label>
                            <input type="text" name="ext_auth_authority" class="form-control" maxlength="120"
                                   value="{{ old('ext_auth_authority', $permit->ext_auth_authority) }}" placeholder="SEDENA / AFAC / municipal…">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Folio del documento') }} {!! $extRequired ? '*' : '' !!}</label>
                            <input type="text" name="ext_auth_folio" class="form-control" maxlength="120" value="{{ old('ext_auth_folio') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Vigencia hasta') }} {!! $extRequired ? '*' : '' !!}</label>
                            <input type="date" name="ext_auth_valid_until" class="form-control" value="{{ old('ext_auth_valid_until') }}">
                            <div class="form-text">{{ __('Si termina antes que la actividad, se te advertirá al emitir.') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Quién lo declara') }} {!! $extRequired ? '*' : '' !!}</label>
                            <input type="text" name="ext_auth_declared_by" class="form-control" maxlength="255" value="{{ old('ext_auth_declared_by') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">{{ __('Qué autoriza / observaciones') }}</label>
                            <input type="text" name="ext_auth_note" class="form-control" maxlength="2000" value="{{ old('ext_auth_note') }}">
                        </div>
                    </div>
                </div>
            @endif

            {{-- PUNTOS COMPUERTA: texto legible COMPLETO, sin recortes; binario. --}}
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="insp-tag">{{ $points->count() }} {{ __('puntos') }}</span>
                    <span class="insp-tag insp-tag--gate">{{ __('todos compuerta') }}</span>
                    @if (! empty($budget['del_safety']))<span class="insp-tag">{{ __('Safety') }}: {{ $budget['del_safety'] }}</span>@endif
                    @if (! empty($budget['del_especialista']))<span class="insp-tag">{{ __('Especialista') }}: {{ $budget['del_especialista'] }}</span>@endif
                </div>

                @foreach ($points as $p)
                    <div class="insp-point is-gate">
                        <p class="insp-point__text">{{ $p->text_es }}</p>
                        <div class="insp-point__meta">
                            <span class="insp-tag">{{ $p->code }}</span>
                            <span class="insp-tag insp-tag--gate">{{ __('Compuerta') }}</span>
                            @if (! empty($p->executor))<span class="insp-tag">{{ $execLabel[$p->executor] ?? $p->executor }}</span>@endif
                            @if ($p->site_sensitive)<span class="insp-tag" title="{{ __('Se reverifica al mover') }}">{{ __('sensible al sitio') }}</span>@endif
                            @if ($p->requires_contact)<span class="insp-tag" title="{{ __('El Safety NO lo ejecuta; lo confirma el especialista') }}">{{ __('lo confirma el especialista') }}</span>@endif
                        </div>
                        <div class="insp-answer">
                            <label>
                                <input type="radio" name="answers[{{ $p->code }}]" value="cumple" required @checked(old('answers.'.$p->code) === 'cumple')>
                                <span class="btn-ans">@include('componentes._icon', ['name' => 'circle-check', 'label' => null]) {{ __('Cumple') }}</span>
                            </label>
                            <label>
                                <input type="radio" name="answers[{{ $p->code }}]" value="no_cumple" @checked(old('answers.'.$p->code) === 'no_cumple')>
                                <span class="btn-ans">@include('componentes._icon', ['name' => 'octagon-alert', 'label' => null]) {{ __('No cumple') }}</span>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- DOS FIRMAS: emite el safety (sesión), ACEPTA el ejecutante designado. --}}
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    @include('componentes._icon', ['name' => 'pen-line', 'label' => null])
                    <strong>{{ __('Aceptación del ejecutante designado') }}</strong>
                </div>
                <p class="small text-muted mb-3">{{ __('Un permiso que sólo firma quien lo emite es una nota, no una autorización. Tú emites; el ejecutante acepta, con su identificación.') }}</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Nombre de quien acepta') }} *</label>
                        <input type="text" name="acceptor_name" class="form-control" maxlength="255" required value="{{ old('acceptor_name') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Rol / especialidad') }}</label>
                        <input type="text" name="acceptor_role" class="form-control" maxlength="100" value="{{ old('acceptor_role') }}" placeholder="{{ __('operador, pirotécnico…') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Tipo de identificación') }}</label>
                        <input type="text" name="acceptor_id_label" class="form-control" maxlength="60" value="{{ old('acceptor_id_label') }}" placeholder="{{ __('cédula / INE / pasaporte') }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">{{ __('Número de identificación') }}</label>
                        <input type="text" name="acceptor_id_value" class="form-control" maxlength="120" value="{{ old('acceptor_id_value') }}">
                    </div>
                </div>
            </div>

            {{-- FOTOGRAFÍAS (opcional): prueba del sitio / montaje / autorización en papel.
                 Sus rutas se CONGELAN con el sello (cambiarlas en un permiso emitido = ALTERADO). --}}
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    @include('componentes._icon', ['name' => 'camera', 'label' => null])
                    <strong>{{ __('Fotografías (opcional)') }}</strong>
                </div>
                <p class="small text-muted mb-3">{{ __('Adjunta fotos del sitio, el montaje o la autorización en papel. Quedan congeladas dentro del permiso sellado. Hasta 6, 8 MB cada una.') }}</p>
                <input type="file" id="permit_photos" name="permit_photos[]" class="form-control" accept="image/*" multiple>
                <div id="permit_photos_preview" class="d-flex flex-wrap gap-2 mt-3"></div>
            </div>

            @if ($requiresFireWatch)
                <div class="alert alert-warning d-flex align-items-start gap-2 py-2 small">
                    @include('componentes._icon', ['name' => 'flame', 'label' => null])
                    <span>{{ __('Trabajo en caliente: la vigilancia posterior a la actividad es PARTE del permiso. Al cerrar se te exigirá declararla cumplida.') }}</span>
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('permits.index') }}" class="btn btn-link text-muted">{{ __('Abandonar sin guardar') }}</a>
                <button type="submit" class="btn btn-crew-accent d-inline-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'shield-check', 'label' => null])
                    {{ __('Emitir y sellar') }}
                </button>
            </div>
        </form>

    </div>
</div>

{{-- Miniaturas de las fotos seleccionadas (mejora progresiva; sin directivas Blade adentro). --}}
<script>
(function () {
    var input = document.getElementById('permit_photos');
    var box   = document.getElementById('permit_photos_preview');
    if (!input || !box || typeof FileReader === 'undefined') { return; }
    input.addEventListener('change', function () {
        box.innerHTML = '';
        Array.prototype.slice.call(input.files || []).slice(0, 6).forEach(function (file) {
            if (!/^image\//.test(file.type) && !/\.(jpe?g|png|webp|heic|heif)$/i.test(file.name)) { return; }
            var img = document.createElement('img');
            img.alt = file.name;
            img.style.cssText = 'width:84px;height:84px;object-fit:cover;border-radius:8px;border:1px solid rgba(0,0,0,.12)';
            var reader = new FileReader();
            reader.onload = function (e) { img.src = e.target.result; };
            reader.readAsDataURL(file);
            box.appendChild(img);
        });
    });
})();
</script>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
