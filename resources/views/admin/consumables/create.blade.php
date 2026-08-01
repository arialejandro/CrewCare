@extends('layouts.app')
@section('title', 'Nuevo consumible / SDS')

@feature('sds_sfx')
@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

<div class="container py-4" style="max-width: 960px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'flask-conical', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">Nuevo consumible / SDS</h1>
                <div class="cc-muted small">Hoja de datos de seguridad de un material de efectos especiales.</div>
            </div>
        </div>
        <a href="{{ route('consumables.index') }}" class="cc-btn-ghost">
            @include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico-16', 'label' => null])
            Volver
        </a>
    </div>

    <form method="POST" action="{{ route('consumables.store') }}">
        @csrf
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'flask-conical', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">Datos del consumible</h2>
                    <p class="cc-form-card__sub">Identificación, riesgos y ficha SDS/MSDS del material.</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                @include('admin.consumables._form', ['consumable' => null])
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
            <a href="{{ route('consumables.index') }}" class="cc-btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                Guardar
            </button>
        </div>
    </form>

</div>
@endsection
@endfeature
