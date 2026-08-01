@extends('layouts.app')

@push('styles')
<style>
    /* ===== Permisos por rol · "Cinematic Dark Glass" ===== */
    .adm-header { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .adm-icon {
        width:48px; height:48px; border-radius:14px; flex:none;
        display:inline-flex; align-items:center; justify-content:center;
        color:var(--brand-primary);
        background:color-mix(in srgb, var(--brand-primary) 16%, transparent);
        border:1px solid var(--stroke);
    }
    .adm-icon .cc-ico { width:22px; height:22px; }
    .adm-title { font-family:'Poppins',sans-serif; font-weight:600; letter-spacing:-.02em; margin:0; color:var(--text); font-size:1.35rem; }
    .adm-subtitle { color:var(--text-muted); font-size:.9rem; margin:.15rem 0 0; }

    /* Nota "cambios en vivo" como panel de vidrio (en vez de alert-warning horneado). */
    .rp-note {
        border:1px solid var(--stroke); border-left:3px solid var(--warn);
        border-radius:var(--radius); background:var(--glass);
        padding:.9rem 1.05rem; color:var(--text); font-size:.9rem;
    }
    .rp-note strong { color:var(--text); }
    .rp-note small { color:var(--text-muted); }

    /* Matriz */
    .rp-matrix { color:var(--text); font-size:.85rem; margin-bottom:0; }
    .rp-matrix thead th { background:transparent; border-color:var(--stroke); color:var(--text); font-weight:700; vertical-align:bottom; }
    .rp-matrix td, .rp-matrix th { border-color:var(--stroke); }
    .rp-matrix tbody td { color:var(--text); }
    /* Columna congelada (esquina + nombre del permiso): fondo OPACO para tapar lo que se
       desplaza por debajo al hacer scroll horizontal. */
    .rp-sticky { position:sticky; left:0; z-index:2; background:var(--bg-2); }
    thead .rp-sticky { z-index:3; }
    .rp-group td {
        background:color-mix(in srgb, var(--brand-primary) 10%, var(--bg-2));
        color:var(--text); font-weight:700; text-transform:uppercase;
        letter-spacing:.05em; font-size:.72rem;
    }
    .rp-perm-key { color:var(--text-muted); font-size:.78em; }
    .rp-lock { color:var(--text-muted); }
    .badge.rp-god {
        background:color-mix(in srgb, var(--text-muted) 18%, transparent);
        color:var(--text-muted); border:1px solid var(--stroke-2); font-weight:600;
    }

    @media (max-width:767px){ .adm-title { font-size:1.15rem; } }
</style>
@endpush

@section('content')

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row">
        <div class="col-12">

            <div class="adm-header mb-4">
                <span class="adm-icon">@include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico', 'label' => null])</span>
                <div class="me-auto">
                    <h4 class="adm-title">Permisos por rol</h4>
                    <p class="adm-subtitle">Controla qué puede hacer cada rol; los cambios aplican en vivo.</p>
                </div>
                <a href="{{ route('roles.index') }}" class="btn btn-outline-primary btn-sm">
                    @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico me-1', 'label' => null]) Asignar roles a personas
                </a>
            </div>

            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            {{-- Aviso clave: estado VIVO vs valor de fábrica (el seeder). --}}
            <div class="rp-note mb-3">
                <strong>Cambios en vivo.</strong> Marca o desmarca un permiso y guarda: aplica de
                inmediato a todos los usuarios con ese rol, sin re-desplegar.
                <br>
                <small>
                    La columna <strong>super-admin</strong> es de solo lectura (acceso total).
                    Los valores de fábrica viven en el seeder; re-correrlo restablece esta matriz.
                </small>
            </div>

            <form method="POST" action="{{ route('roles.permissions.update') }}">
                @csrf

                <div class="d-flex justify-content-end mb-2">
                    <button type="submit" class="btn btn-primary">
                        @include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico me-1', 'label' => null]) Guardar cambios
                    </button>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table rp-matrix table-sm table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="rp-sticky" style="min-width:260px;">Permiso</th>
                                        @foreach($roles as $role)
                                            <th class="text-center text-nowrap">
                                                {{ $role->name }}
                                                @if($role->name === 'super-admin')
                                                    <br><span class="badge rp-god" title="Acceso total, no editable">god-mode</span>
                                                @endif
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($groups as $groupLabel => $perms)
                                        <tr class="rp-group">
                                            <td colspan="{{ count($roles) + 1 }}">
                                                {{ $groupLabel }}
                                            </td>
                                        </tr>
                                        @foreach($perms as $permName => $permLabel)
                                            <tr>
                                                <td class="rp-sticky">
                                                    {{ $permLabel }}
                                                    <br><small class="rp-perm-key">{{ $permName }}</small>
                                                </td>
                                                @foreach($roles as $role)
                                                    @php
                                                        $checked   = isset($matrix[$role->name][$permName]);
                                                        $editable  = in_array($role->name, $editableRoles, true);
                                                    @endphp
                                                    <td class="text-center">
                                                        @if($editable)
                                                            <input type="checkbox"
                                                                   class="form-check-input"
                                                                   name="perms[{{ $role->name }}][]"
                                                                   value="{{ $permName }}"
                                                                   {{ $checked ? 'checked' : '' }}>
                                                        @else
                                                            {{-- super-admin: bloqueado y siempre concedido --}}
                                                            <i class="fa-solid fa-lock rp-lock" title="Acceso total (no editable)"></i>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary">
                        @include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico me-1', 'label' => null]) Guardar cambios
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

@endsection
