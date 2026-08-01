@extends('layouts.app')
@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

<div class="changepass-page container-fluid py-4" style="max-width: 560px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('Cambiar contraseña') }}</h1>
            <div class="cc-muted small">{{ __('messages.updatepass') }}</div>
        </div>
    </div>

    <form action="{{ route('updatepassword') }}" enctype='multipart/form-data' method="POST">
        {{ csrf_field() }}
        {{ method_field('POST') }}

        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">{{ __('Nueva contraseña') }}</h2>
                    <p class="cc-form-card__sub">{{ __('Se aplicará a tu propia cuenta de acceso.') }}</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="cc-field">
                    <label for="password" class="cc-label">{{ __('Password') }}</label>
                    <input id="password" type="password" name="password" class="form-control validate cc-control" autocomplete="new-password">
                </div>
            </div>
        </div>

        <div class="d-grid d-md-flex justify-content-md-end mb-4">
            <button type="submit" class="btn btn-primary cc-cta w-100 w-md-auto">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                {{ __('Save') }}
            </button>
        </div>
    </form>
</div>

@endsection

@push('styles')
<style>
    @media (min-width: 768px) { .w-md-auto { width: auto !important; } }
</style>
@endpush
