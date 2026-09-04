@extends('layouts.app')

@section('content')
{{-- Confirmación de submits destructivos por delegación (data-confirm), sin onsubmit inline (CSP). --}}
@include('componentes._confirm-submit')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

{{-- CSS específico de ESTA vista: sólo lo que el kit no cubre (tabla del catálogo,
     chip neutro, botones de acción de fila y estado vacío). Todo con tokens de marca. --}}
@push('styles')
<style>
    .cc-cat-table { color: var(--text); margin: 0; }
    .cc-cat-table thead th {
        font-size: .72rem; text-transform: uppercase; letter-spacing: .04em;
        color: var(--text-muted); font-weight: 700; background: transparent;
        border-bottom: 1px solid var(--stroke, var(--border)); padding: .7rem .75rem; white-space: nowrap;
    }
    .cc-cat-table tbody td {
        border-bottom: 1px solid var(--stroke, var(--border)); color: var(--text);
        vertical-align: middle; padding: .65rem .75rem;
    }
    .cc-cat-table tbody tr:last-child td { border-bottom: 0; }
    .cc-cat-table tbody tr:hover td { background: rgba(var(--brand-primary-rgb), .06); }
    .cc-cat-name { font-weight: 600; color: var(--text); }

    /* El kit trae chips --danger/--ok/--brand; sumamos el neutro para "Inactivo". */
    .cc-chip--muted { color: var(--text-muted); background: var(--surface-2); border: 1px solid var(--stroke-2, var(--border)); }

    /* Botón de acción de fila: cuadro compacto, icono 16px (mismo lenguaje que la consulta). */
    .cc-cat-act {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border-radius: 9px;
        border: 1px solid var(--stroke-2, var(--border)); background: transparent;
        color: var(--text-muted); text-decoration: none;
        transition: color .15s ease, border-color .15s ease, background-color .15s ease;
    }
    .cc-cat-act:hover { color: var(--brand-primary); border-color: var(--brand-primary); background: rgba(var(--brand-primary-rgb), .06); }
    .cc-cat-act--ok:hover { color: var(--ok); border-color: var(--ok); background: color-mix(in srgb, var(--ok) 10%, transparent); }
    .cc-cat-act--danger:hover { color: var(--danger); border-color: var(--danger); background: color-mix(in srgb, var(--danger) 10%, transparent); }

    .cc-cat-empty { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); }
    .cc-cat-empty .cc-ico-24 { opacity: .5; }
</style>
@endpush

<div class="container py-4" style="max-width: 1000px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">Departamentos</h1>
                <div class="cc-muted small">Catálogo de departamentos, su canal de radio y orden de aparición.</div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3 d-flex align-items-center gap-2" role="alert">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @can('catalogs.manage')
    {{-- ===== Alta ===== --}}
    <div class="cc-form-card">
        <div class="cc-form-card__head">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div class="cc-form-card__titles">
                <h2 class="cc-form-card__title">Agregar departamento</h2>
                <p class="cc-form-card__sub">Nombre, canal de radio y orden en el que aparece en el llamado.</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            <form method="POST" action="{{ route('creardepartamento') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-md-5">
                        <div class="cc-field mb-0">
                            <label for="name" class="cc-label">Nombre <span class="cc-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control cc-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="cc-field mb-0">
                            <label for="radio_channel" class="cc-label">Canal de radio <span class="cc-optional">(opcional)</span></label>
                            <input type="text" class="form-control cc-control @error('radio_channel') is-invalid @enderror"
                                   id="radio_channel" name="radio_channel" value="{{ old('radio_channel') }}">
                            @error('radio_channel')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="cc-field mb-0">
                            <label for="sort_order" class="cc-label">Orden</label>
                            <input type="number" class="form-control cc-control @error('sort_order') is-invalid @enderror"
                                   id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}">
                            @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary cc-cta">
                        @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-18', 'label' => null])
                        Crear
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    {{-- ===== Listado ===== --}}
    <div class="cc-form-card">
        <div class="table-responsive">
            <table class="table cc-cat-table cc-stack align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3">Departamento</th>
                        <th scope="col">Canal de radio</th>
                        <th scope="col">Orden</th>
                        <th scope="col">Estado</th>
                        @can('catalogs.manage')<th scope="col" class="text-end pe-3">Acciones</th>@endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $dept)
                    <tr>
                        <td data-label="Departamento" class="ps-3 cc-cat-name">{{ $dept->name }}</td>
                        <td data-label="Canal de radio">{{ $dept->radio_channel ?? '—' }}</td>
                        <td data-label="Orden">{{ $dept->sort_order }}</td>
                        <td data-label="Estado">
                            @if($dept->active)
                                <span class="cc-chip cc-chip--ok">@include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-14', 'label' => null]) Activo</span>
                            @else
                                <span class="cc-chip cc-chip--muted">Inactivo</span>
                            @endif
                        </td>
                        @can('catalogs.manage')
                        <td class="text-end pe-3">
                            <div class="d-inline-flex gap-2">
                                <a class="cc-cat-act" href="{{ route('editardepartamento', $dept->id) }}"
                                   title="Editar" aria-label="Editar">
                                    @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico-16', 'label' => null])
                                </a>
                                @if($dept->active)
                                <form method="POST" action="{{ route('desactivardepartamento', $dept->id) }}"
                                      data-confirm="¿Desactivar este departamento?" class="d-inline">
                                    @csrf
                                    <button type="submit" class="cc-cat-act cc-cat-act--danger" title="Desactivar" aria-label="Desactivar">
                                        @include('componentes._icon', ['name' => 'x-circle', 'class' => 'cc-ico-16', 'label' => null])
                                    </button>
                                </form>
                                @else
                                <form method="POST" action="{{ route('activardepartamento', $dept->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="cc-cat-act cc-cat-act--ok" title="Activar" aria-label="Activar">
                                        @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-16', 'label' => null])
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                        @endcan
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="cc-cat-empty">
                                @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-ico-24 d-block mx-auto mb-2', 'label' => null])
                                <div class="fw-semibold">No hay departamentos registrados.</div>
                                <small>Agrega el primero con el formulario de arriba.</small>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {!! $departments->links() !!}
    </div>
</div>
@endsection
