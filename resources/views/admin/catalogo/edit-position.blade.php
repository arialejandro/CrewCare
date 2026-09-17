@extends('layouts.app')

@section('content')
@include('componentes._form-kit')

<div style="max-width:640px;margin:0 auto;">
    <div class="cc-form-hero" style="margin-bottom:1rem;">
        <h1 class="cc-form-title">{{ __('Editar puesto') }}</h1>
        <p class="cc-help">{{ __('La marca de jefatura (HOD) se cambia desde el listado, no aquí.') }}</p>
    </div>

    @if ($errors->any())
        <div class="cc-alert cc-alert--danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('catalogo.position.update', $item->id) }}" class="cc-form-card">
        @csrf
        <div class="cc-field">
            <label class="cc-label">{{ __('Nombre (ES)') }} <span class="cc-req" aria-hidden="true">*</span></label>
            <input type="text" name="name" class="form-control cc-control" required maxlength="150" value="{{ old('name', $item->name) }}">
        </div>
        <div class="cc-field">
            <label class="cc-label">{{ __('Nombre (EN)') }}</label>
            <input type="text" name="name_en" class="form-control cc-control" maxlength="255" value="{{ old('name_en', $item->name_en) }}">
        </div>
        <div class="cc-field">
            <label class="cc-label">{{ __('Departamento') }} <span class="cc-req" aria-hidden="true">*</span></label>
            <select name="department_id" class="form-select cc-control" required>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @if((int)old('department_id', $item->department_id) === (int)$d->id) selected @endif>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="cc-field">
            <label class="cc-label">{{ __('Rango') }} <span class="cc-req" aria-hidden="true">*</span></label>
            <input type="number" name="rank" class="form-control cc-control" required min="1" max="99" value="{{ old('rank', $item->rank) }}">
            <span class="cc-help">{{ __('10 jefatura · 20 segundo mando/diseño · 30 coordinación · 40 key/1er asistente · 50 asistente · 60 operativo.') }}</span>
        </div>
        <div class="cc-field">
            <label class="cc-label">{{ __('Binding') }} <span class="cc-req" aria-hidden="true">*</span></label>
            <select name="binding" class="form-select cc-control" required>
                @foreach ($bindings as $b)
                    <option value="{{ $b }}" @if(old('binding', $item->binding) === $b) selected @endif>{{ $b }}</option>
                @endforeach
            </select>
            <span class="cc-help">{{ __('Qué se duplica por unidad. La entidad “unidad” aún no existe; el campo se guarda para cuando se construya.') }}</span>
        </div>
        <div class="cc-field">
            <label class="cc-label">{{ __('Grado') }}</label>
            <select name="grade" class="form-select cc-control">
                <option value="">{{ __('—') }}</option>
                @foreach ($grades as $g)
                    <option value="{{ $g }}" @if(old('grade', $item->grade) === $g) selected @endif>{{ $g }}</option>
                @endforeach
            </select>
        </div>
        <div class="cc-form-actions">
            <a href="{{ route('catalogo.index') }}" class="cc-btn cc-btn--ghost">{{ __('Cancelar') }}</a>
            <button type="submit" class="cc-btn cc-btn--primary">{{ __('Guardar') }}</button>
        </div>
    </form>
</div>
@endsection
