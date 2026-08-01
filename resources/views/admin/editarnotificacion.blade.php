@extends('layouts.app')

@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

<div class="container py-4" style="max-width: 640px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'bell', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">Editar usuario de notificaciones</h1>
                <div class="cc-muted small">Actualiza el contacto que recibe los avisos por correo.</div>
            </div>
        </div>
        <a href="{{ route('notificacioncrud') }}" class="cc-btn-ghost">
            @include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico-16', 'label' => null])
            Volver
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3 d-flex align-items-center gap-2" role="alert">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('savenotificacion', $item->id_usernotificacion) }}">
        @csrf
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'mail', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">Datos del contacto</h2>
                    <p class="cc-form-card__sub">Nombre y correo del destinatario de las notificaciones.</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="cc-field">
                    <label for="nombre" class="cc-label">Nombre <span class="cc-req" aria-hidden="true">*</span></label>
                    <input type="text" class="form-control cc-control @error('nombre') is-invalid @enderror"
                           id="nombre" name="nombre" value="{{ old('nombre', $item->nombre) }}" required>
                    @error('nombre')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="cc-field">
                    <label for="correo" class="cc-label">Correo <span class="cc-req" aria-hidden="true">*</span></label>
                    <input type="email" class="form-control cc-control @error('correo') is-invalid @enderror"
                           id="correo" name="correo" value="{{ old('correo', $item->correo) }}" required>
                    @error('correo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('notificacioncrud') }}" class="cc-btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                Guardar
            </button>
        </div>
    </form>
</div>
@endsection
