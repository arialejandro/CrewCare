@extends('layouts.app')

@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

<div class="container py-4" style="max-width: 640px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">Editar posición</h1>
                <div class="cc-muted small">Actualiza el nombre, departamento, jefatura y orden.</div>
            </div>
        </div>
        <a href="{{ route('positionscrud') }}" class="cc-btn-ghost">
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

    <form method="POST" action="{{ route('saveposition', $item->id) }}">
        @csrf
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">Datos de la posición</h2>
                    <p class="cc-form-card__sub">Cómo se muestra este puesto en el alta de crew y en el llamado.</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                <div class="cc-field">
                    <label for="name" class="cc-label">Nombre <span class="cc-req" aria-hidden="true">*</span></label>
                    <input type="text" class="form-control cc-control @error('name') is-invalid @enderror"
                           id="name" name="name" value="{{ old('name', $item->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="cc-field">
                    <label for="department_id" class="cc-label">Departamento</label>
                    <select name="department_id" id="department_id" class="form-select cc-select @error('department_id') is-invalid @enderror">
                        <option value="">— Seleccione —</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" {{ $d->id == old('department_id', $item->department_id) ? 'selected' : '' }}>{{ $d->name }}</option>
                        @endforeach
                    </select>
                    @error('department_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="cc-field mb-0">
                            <label for="sort_order" class="cc-label">Orden</label>
                            <input type="number" class="form-control cc-control @error('sort_order') is-invalid @enderror"
                                   id="sort_order" name="sort_order" value="{{ old('sort_order', $item->sort_order) }}">
                            @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <span class="cc-help">Menor número aparece primero.</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="cc-field mb-0">
                            <label class="cc-label">Jefatura</label>
                            <div class="form-check pt-1">
                                <input class="form-check-input" type="checkbox" value="1" id="is_hod" name="is_hod"
                                       {{ old('is_hod', $item->is_hod) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_hod">HOD (Head of Department)</label>
                            </div>
                            <span class="cc-help">Marca si esta posición encabeza el departamento.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('positionscrud') }}" class="cc-btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                Guardar
            </button>
        </div>
    </form>
</div>
@endsection
