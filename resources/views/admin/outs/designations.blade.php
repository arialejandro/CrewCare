@extends('layouts.app')

@section('content')
@include('componentes._form-kit')
@include('componentes._confirm-submit')
@include('componentes._typeahead')

<div class="container-fluid py-4" style="max-width: 900px;">
    @include('componentes._form-feedback')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">{{ __('Designados para reportar salidas') }}</h1>
                <div class="cc-muted small">{{ __('Quién puede reportar la salida de un departamento (por WhatsApp o pegando el mensaje). Es por designación, no por puesto: puede ser cualquiera del equipo.') }}</div>
            </div>
        </div>
        <a href="{{ route('outs.index') }}" class="btn btn-outline-secondary btn-sm">← {{ __('Salidas') }}</a>
    </div>

    @if (empty($departments))
        <div class="alert alert-info">{{ __('No hay departamentos dentro de tu alcance.') }}</div>
    @else
        @foreach ($departments as $deptId => $deptName)
            @php $crewOfDept = array_values(array_filter($crew, fn ($c) => (int) $c['department_id'] === (int) $deptId)); @endphp
            <div class="cc-form-card mb-3">
                <div class="cc-form-card__body">
                    <div class="fw-semibold mb-2">{{ $deptName }}</div>

                    @if (!empty($reporters[$deptId]))
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach ($reporters[$deptId] as $r)
                                <span class="badge bg-secondary d-inline-flex align-items-center gap-2" style="padding:.5rem .7rem;">
                                    {{ $r['name'] }}
                                    <form method="POST" action="{{ route('outs.designations.destroy', $r['row_id']) }}"
                                          data-confirm="{{ __('¿Retirar a') }} {{ $r['name'] }}?" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent text-white" title="{{ __('Retirar') }}" style="line-height:1;">&times;</button>
                                    </form>
                                </span>
                            @endforeach
                        </div>
                    @else
                        <div class="cc-muted small mb-3">{{ __('Sin designados. Nadie puede reportar por este departamento todavía.') }}</div>
                    @endif

                    <form method="POST" action="{{ route('outs.designations.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="department_id" value="{{ $deptId }}">
                        <div class="col-12 col-md-8">
                            <select name="user_id" class="form-select form-select-sm js-typeahead" required>
                                <option value="">{{ __('Designar a…') }}</option>
                                @foreach ($crewOfDept as $c)
                                    <option value="{{ $c['user_id'] }}">{{ $c['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('Designar') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
