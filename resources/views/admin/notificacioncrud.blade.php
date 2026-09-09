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

<div class="container py-4" style="max-width: 960px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'bell', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">Usuarios de notificaciones</h1>
                <div class="cc-muted small">Contactos que reciben los avisos por correo del sistema.</div>
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
                <h2 class="cc-form-card__title">Agregar usuario de notificaciones</h2>
                <p class="cc-form-card__sub">Registra un contacto que recibirá los avisos por correo.</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            <form method="POST" action="{{ route('crearnotificacion') }}">
                @csrf
                @if(!empty($crewUsers) && count($crewUsers))
                <div class="cc-field mb-3">
                    <label for="crewPicker" class="cc-label">Elegir de la lista de usuarios <span class="cc-muted">(opcional)</span></label>
                    <select id="crewPicker" class="form-select cc-control">
                        <option value="">— Escribir manualmente —</option>
                        @foreach($crewUsers as $cu)
                            <option value="{{ $cu->id }}" data-name="{{ $cu->name }}" data-email="{{ $cu->email }}">{{ $cu->name }} — {{ $cu->email }}</option>
                        @endforeach
                    </select>
                    <small class="cc-muted d-block mt-1">Al elegir un usuario se llenan Nombre y Correo; puedes ajustarlos antes de crear.</small>
                </div>
                @endif
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="cc-field mb-0">
                            <label for="nombre" class="cc-label">Nombre <span class="cc-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control cc-control @error('nombre') is-invalid @enderror"
                                   id="nombre" name="nombre" value="{{ old('nombre') }}" required>
                            @error('nombre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field mb-0">
                            <label for="correo" class="cc-label">Correo <span class="cc-req" aria-hidden="true">*</span></label>
                            <input type="email" class="form-control cc-control @error('correo') is-invalid @enderror"
                                   id="correo" name="correo" value="{{ old('correo') }}" required>
                            @error('correo')
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
                        <th scope="col" class="ps-3">Nombre</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Estado</th>
                        @can('catalogs.manage')<th scope="col" class="text-end pe-3">Acciones</th>@endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notificaciones as $n)
                    <tr>
                        <td data-label="Nombre" class="ps-3 cc-cat-name">{{ $n->nombre }}</td>
                        <td data-label="Correo">{{ $n->correo }}</td>
                        <td data-label="Estado">
                            @if($n->activo)
                                <span class="cc-chip cc-chip--ok">@include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-14', 'label' => null]) Activo</span>
                            @else
                                <span class="cc-chip cc-chip--muted">Inactivo</span>
                            @endif
                        </td>
                        @can('catalogs.manage')
                        <td class="text-end pe-3">
                            <div class="d-inline-flex gap-2">
                                <a class="cc-cat-act" href="{{ route('editarnotificacion', $n->id_usernotificacion) }}"
                                   title="Editar" aria-label="Editar">
                                    @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico-16', 'label' => null])
                                </a>
                                @if($n->activo)
                                <form method="POST" action="{{ route('desactivarnotificacion', $n->id_usernotificacion) }}"
                                      data-confirm="¿Desactivar este usuario?" class="d-inline">
                                    @csrf
                                    <button type="submit" class="cc-cat-act cc-cat-act--danger" title="Desactivar" aria-label="Desactivar">
                                        @include('componentes._icon', ['name' => 'x-circle', 'class' => 'cc-ico-16', 'label' => null])
                                    </button>
                                </form>
                                @else
                                <form method="POST" action="{{ route('activarnotificacion', $n->id_usernotificacion) }}" class="d-inline">
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
                        <td colspan="4">
                            <div class="cc-cat-empty">
                                @include('componentes._icon', ['name' => 'bell', 'class' => 'cc-ico-24 d-block mx-auto mb-2', 'label' => null])
                                <div class="fw-semibold">No hay usuarios de notificaciones registrados.</div>
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
        {!! $notificaciones->links() !!}
    </div>
</div>

@push('scripts')
<script>
(function () {
    var picker = document.getElementById('crewPicker');
    if (!picker) return;
    picker.addEventListener('change', function () {
        var opt = picker.options[picker.selectedIndex];
        if (!opt) return;
        var name  = opt.getAttribute('data-name')  || '';
        var email = opt.getAttribute('data-email') || '';
        var n = document.getElementById('nombre');
        var c = document.getElementById('correo');
        if (n) n.value = name;
        if (c) c.value = email;
    });
})();
</script>
@endpush
@endsection
