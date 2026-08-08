@extends('layouts.app')
@section('content')
@php
    $scopeLabels = [
        'universal' => __('Universal'),
        'universal_energizada' => __('Energizada'),
        'familia' => __('Familia'),
        'tipo' => __('Tipo'),
        'actividad' => __('Actividad (habilitante)'),
    ];
    $totalSecs = 0; $paroCount = 0;
    foreach ($points as $p) { $totalSecs += (int) ($p->estimated_seconds ?? 0);
        if ($p->is_gate && in_array($p->outcome_if_fail, ['correccion_mismo_dia','reemplazo'])) $paroCount++; }
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 820px;">

        <a href="{{ route('tools.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-3" style="text-decoration:none;">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null]) {{ __('Volver al catálogo') }}
        </a>

        <div class="d-flex align-items-start gap-3 mb-3">
            {{-- Imagen GENÉRICA del tipo (referencia). Si aún no se sube, cae al icono (placeholder). --}}
            @if ($tool->imageUrl())
                <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->name }}"
                     class="rounded-3" style="width:56px;height:56px;object-fit:contain;background:var(--surface-2);padding:4px;">
            @else
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'wrench', 'class' => 'cc-ico', 'label' => null])
                </span>
            @endif
            <div>
                <h1 class="crew-title mb-0">{{ $tool->name }}</h1>
                <p class="text-muted mb-0 small">{{ $tool->code }} @if($tool->name_en) · {{ $tool->name_en }} @endif</p>
            </div>
        </div>

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        {{-- A6 · SEÑAL DE PERMISO + Paso 6 (delta #44): además de "requiere permiso: X", dice si hay
             permiso de trabajo VIGENTE hoy y ofrece emitirlo. NUNCA bloquea la inspección: son dos
             actos distintos y la inspección debe poder hacerse siempre. --}}
        @if (! empty($tool->triggers_permit_name) || ! empty($permit))
            <div class="alert alert-warning d-flex flex-column gap-1 py-2">
                <span class="d-inline-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'file-check', 'label' => null])
                    {{ __('Esta herramienta requiere permiso de actividad') }}: <strong>{{ $permit->name ?? $tool->triggers_permit_name }}</strong>.
                </span>
                @if (! empty($permit))
                    <span class="d-inline-flex align-items-center gap-2 flex-wrap ms-4">
                        @if (! empty($permitVigente))
                            <span class="badge bg-success">{{ __('Permiso vigente hoy') }}</span>
                            <a href="{{ route('permits.show', $permitVigente->uuid) }}" class="small">{{ $permitVigente->folio() }} · {{ __('ver') }}</a>
                        @else
                            <span class="badge bg-secondary">{{ __('Sin permiso vigente hoy') }}</span>
                            @can('permits.issue')
                                <a href="{{ route('permits.create', ['permit' => $permit->id, 'tool_id' => $tool->id]) }}" class="small">{{ __('Emitir permiso') }}</a>
                            @endcan
                        @endif
                        <span class="text-muted small">— {{ __('la inspección se puede hacer igual.') }}</span>
                    </span>
                @endif
            </div>
        @endif

        {{-- ¿Ya se inspeccionó y sigue vigente? (no impide inspeccionar de nuevo). --}}
        @if (! empty($vigente))
            <div class="alert alert-success d-flex align-items-center justify-content-between gap-2 py-2 flex-wrap">
                <span class="d-inline-flex align-items-center gap-2">
                    @include('componentes._icon', ['name' => 'circle-check', 'label' => null])
                    {{ __('Ya hay un acta vigente') }} ({{ $vigente->folio() }})@if($vigente->tool_family_key) · {{ $vigente->tool_family_key }}@endif
                </span>
                <a href="{{ route('tools.inspection.show', $vigente->uuid) }}" class="btn btn-sm btn-outline-success">{{ __('Ver acta') }}</a>
            </div>
        @endif

        {{-- Comodín sin familia elegida: se elige al vuelo antes del checklist. --}}
        @if ($isWildcard && ! $familyKey)
            <div class="card border-0 shadow-sm rounded-3 p-4">
                <h5 class="mb-1">{{ __('Elige la familia') }}</h5>
                <p class="text-muted small">{{ __('Esta herramienta no está catalogada. Elige su familia para armar el checklist.') }}</p>
                <form method="get" action="{{ route('tools.inspect.form', $tool->id) }}" class="d-flex gap-2 flex-wrap">
                    <select name="family" class="form-select" style="max-width: 340px;" required>
                        <option value="">{{ __('— Familia —') }}</option>
                        @foreach ($families as $f)
                            <option value="{{ $f->family_key }}">{{ $f->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-crew-accent">{{ __('Continuar') }}</button>
                </form>
            </div>
        @else
            {{-- Presupuesto de tiempo: qué le espera al safety antes de empezar. --}}
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="insp-tag">⏱ ≈ {{ $totalSecs }} s</span>
                <span class="insp-tag">{{ $points->count() }} {{ __('puntos') }}</span>
                @if ($paroCount > 0)<span class="insp-tag insp-tag--gate">{{ $paroCount }} {{ __('de paro') }}</span>@endif
                @if ($isWildcard)<span class="insp-tag">{{ __('Familia') }}: {{ $familyKey }}</span>@endif
            </div>

            <form method="post" action="{{ route('tools.inspect.store', $tool->id) }}" enctype="multipart/form-data">
                @csrf
                @if ($isWildcard)<input type="hidden" name="family_key" value="{{ $familyKey }}">@endif
                @if (! empty($origin))<input type="hidden" name="origin" value="{{ $origin }}"><input type="hidden" name="origin_id" value="{{ $originId }}">@endif

                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
                    {{-- A2 · MOMENTO: cambia cómo se REDACTA el veredicto, no cómo se calcula. --}}
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">{{ __('Momento de la inspección') }}</label>
                        <select name="inspection_moment" class="form-select" id="inspMoment">
                            <option value="llegada_equipo" @selected(old('inspection_moment', $moment)==='llegada_equipo')>{{ __('Llegada del equipo (nada corriendo)') }}</option>
                            <option value="previo_al_uso" @selected(old('inspection_moment', $moment)==='previo_al_uso')>{{ __('Previo al uso') }}</option>
                            <option value="en_uso" @selected(old('inspection_moment', $moment)==='en_uso')>{{ __('En uso (la operación está corriendo)') }}</option>
                            <option value="por_hallazgo" @selected(old('inspection_moment', $moment)==='por_hallazgo')>{{ __('Por hallazgo') }}</option>
                        </select>
                        <div class="form-text" id="momentHint"></div>
                    </div>
                    <div class="mb-3" id="plannedUseWrap">
                        <label class="form-label small fw-semibold">{{ __('Uso previsto (opcional)') }}</label>
                        <input type="datetime-local" name="planned_use_at" class="form-control" value="{{ old('planned_use_at') }}" style="max-width:280px;">
                        <div class="form-text">{{ __('Si falla en pre-uso, la corrección se compromete antes de esta hora.') }}</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Departamento a notificar') }} *</label>
                            <select name="department_id" class="form-select js-typeahead" required>
                                <option value="">{{ __('— Departamento —') }}</option>
                                @foreach ($departments as $d)
                                    <option value="{{ $d->id }}" @selected(old('department_id') == $d->id)>{{ $d->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('El HOD de este depto recibe aviso si hay PARO.') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Marca') }}</label>
                            <input type="text" name="tool_brand" class="form-control" value="{{ old('tool_brand') }}"
                                   maxlength="120" placeholder="{{ __('opcional') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Modelo') }}</label>
                            <input type="text" name="tool_model" class="form-control" value="{{ old('tool_model') }}"
                                   maxlength="255" placeholder="{{ __('opcional') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('N.º de serie') }}</label>
                            <input type="text" name="tool_serial" class="form-control" value="{{ old('tool_serial') }}"
                                   maxlength="120" placeholder="{{ __('identifica la unidad física') }}">
                            <div class="form-text">{{ __('Con la serie se agrupa el historial de ESTA herramienta.') }}</div>
                        </div>
                        {{-- Dueño / responsable de la herramienta (más allá del departamento):
                             crew de la lista, o texto libre para renta / externo. Si eliges crew,
                             ese nombre manda; si no, se usa el texto. --}}
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">{{ __('Dueño (responsable)') }}</label>
                            <select name="owner_user_id" class="form-select js-typeahead">
                                <option value="">{{ __('— Miembro de crew —') }}</option>
                                @foreach ($crew as $c)
                                    <option value="{{ $c->id }}" @selected((int) old('owner_user_id') === (int) $c->id)>{{ \App\Models\User::displayName($c) }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="owner_name" class="form-control mt-2" value="{{ old('owner_name') }}"
                                   maxlength="160" placeholder="{{ __('o escribe (casa de renta / externo)') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">{{ __('Foto de la herramienta (opcional)') }}</label>
                            <input type="file" name="tool_photo" class="form-control" accept="image/*,.heic,.heif" capture="environment" data-cc-photo>
                            <div class="form-text">{{ __('La unidad real; queda sellada en el acta.') }}</div>
                        </div>
                        @if ($tool->requires_designated_operator)
                        <div class="col-12">
                            <label class="form-label small fw-semibold">{{ __('¿Quién corre este checklist?') }}</label>
                            <div class="d-flex gap-3">
                                <label class="d-inline-flex align-items-center gap-1">
                                    <input type="radio" name="checklist_mode" value="safety" @checked(old('checklist_mode', 'safety') === 'safety')> {{ __('Safety (al detectar)') }}
                                </label>
                                <label class="d-inline-flex align-items-center gap-1">
                                    <input type="radio" name="checklist_mode" value="operator" @checked(old('checklist_mode', 'safety') === 'operator')> {{ __('Operador (antes del turno)') }}
                                </label>
                            </div>
                        </div>
                        @else
                            <input type="hidden" name="checklist_mode" value="safety">
                        @endif
                    </div>
                </div>

                {{-- El checklist: punto legible COMPLETO, binario, agrupado por ámbito. --}}
                @php $lastScope = null; @endphp
                @foreach ($points as $p)
                    @if ($p->scope !== $lastScope)
                        <div class="insp-scope-head">{{ $scopeLabels[$p->scope] ?? $p->scope }}</div>
                        @php $lastScope = $p->scope; @endphp
                    @endif
                    <div class="insp-point {{ $p->is_gate ? 'is-gate' : 'is-info' }}">
                        <p class="insp-point__text">{{ $p->text_es }}</p>
                        <div class="insp-point__meta">
                            <span class="insp-tag">{{ $p->code }}</span>
                            @if ($p->is_gate)
                                <span class="insp-tag insp-tag--gate">{{ __('Compuerta') }}</span>
                            @else
                                <span class="insp-tag">{{ __('Informativo') }}</span>
                            @endif
                            @foreach (($p->std_codes ?? []) as $sc)
                                <span class="insp-tag">{{ $sc }}</span>
                            @endforeach
                        </div>
                        <div class="insp-answer">
                            <label>
                                <input type="radio" name="answers[{{ $p->code }}]" value="ok" required @checked(old('answers.'.$p->code) === 'ok')>
                                <span class="btn-ans">@include('componentes._icon', ['name' => 'circle-check', 'label' => null]) {{ __('Cumple') }}</span>
                            </label>
                            <label>
                                <input type="radio" name="answers[{{ $p->code }}]" value="fail" @checked(old('answers.'.$p->code) === 'fail')>
                                <span class="btn-ans">@include('componentes._icon', ['name' => 'octagon-alert', 'label' => null]) {{ __('Falla') }}</span>
                            </label>
                        </div>
                    </div>
                @endforeach

                <div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
                    <label class="form-label small fw-semibold">{{ __('Observaciones (opcional)') }}</label>
                    <textarea name="note" class="form-control" rows="2" maxlength="2000">{{ old('note') }}</textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="{{ route('tools.index') }}" class="btn btn-link text-muted">{{ __('Abandonar sin guardar') }}</a>
                    <button type="submit" class="btn btn-crew-accent d-inline-flex align-items-center gap-2">
                        @include('componentes._icon', ['name' => 'shield-check', 'label' => null])
                        {{ __('Cerrar inspección y sellar') }}
                    </button>
                </div>
            </form>

            <script>
                (function () {
                    var sel = document.getElementById('inspMoment');
                    var hint = document.getElementById('momentHint');
                    var planned = document.getElementById('plannedUseWrap');
                    if (!sel) return;
                    var preUse = ['llegada_equipo', 'previo_al_uso'];
                    function sync() {
                        var pre = preUse.indexOf(sel.value) !== -1;
                        hint.textContent = pre
                            ? '{{ __('Un fallo se comunica como EQUIPO NO AUTORIZADO, con ventana para corregir o sustituir.') }}'
                            : '{{ __('Un fallo es PARO INMEDIATO: detiene el set.') }}';
                        planned.style.display = pre ? '' : 'none';
                    }
                    sel.addEventListener('change', sync); sync();
                })();
            </script>
        @endif

    </div>
</div>

{{-- Cámara del set: convierte HEIC (iPad/iPhone) a JPEG y comprime EN EL NAVEGADOR antes de subir. --}}
<script src="/js/cc-photo.js"></script>
<script src="/js/cc-photo-auto.js"></script>

{{-- Buscador "escribe-y-filtra" para los <select> largos de crew/depto (Dueño, Departamento):
     el <select> nativo sigue siendo el control real (su name se envía) y el fallback sin JS. --}}
@include('componentes._typeahead')

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
