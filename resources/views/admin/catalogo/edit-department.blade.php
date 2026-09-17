@extends('layouts.app')

@section('content')
@include('componentes._form-kit')

<div style="max-width:560px;margin:0 auto;">
    <div class="cc-form-hero" style="margin-bottom:1rem;">
        <h1 class="cc-form-title">{{ __('Editar departamento') }}</h1>
    </div>

    @if ($errors->any())
        <div class="cc-alert cc-alert--danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('catalogo.dept.update', $item->id) }}" class="cc-form-card">
        @csrf
        <div class="cc-field">
            <label class="cc-label">{{ __('Nombre (ES)') }} <span class="cc-req" aria-hidden="true">*</span></label>
            <input type="text" name="name" class="form-control cc-control" required maxlength="120" value="{{ old('name', $item->name) }}">
        </div>
        <div class="cc-field">
            <label class="cc-label">{{ __('Nombre (EN)') }}</label>
            <input type="text" name="name_en" class="form-control cc-control" maxlength="255" value="{{ old('name_en', $item->name_en) }}">
        </div>
        <div class="cc-form-actions">
            <a href="{{ route('catalogo.index') }}" class="cc-btn cc-btn--ghost">{{ __('Cancelar') }}</a>
            <button type="submit" class="cc-btn cc-btn--primary">{{ __('Guardar') }}</button>
        </div>
    </form>
</div>
@endsection
