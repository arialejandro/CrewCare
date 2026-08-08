@extends('layouts.app')
@section('content')

{{--
    Crew List — listado interno de gestión de crew (LA JOYA: se conserva íntegra).
    Homologación de esta pasada: iconos migrados a componentes._icon, estilos movidos al
    parcial COMPARTIDO componentes._crew-list-styles (tokenizado + dark), buscador con label
    sr-only + debounce, #usertable con aria-live, y colapso a tarjetas en móvil vía .cc-stack.

    La búsqueda AJAX (#search) reemplaza #usertable con componentes.search-results, que
    renderiza EXACTAMENTE la misma fila (mismo <tr>, mismos data-label) para que el swap sea
    invisible. Flags de visibilidad true = la lista completa muestra todas las columnas.
--}}
@php
    $canPersonal = true;   // F.Nac. — la lista completa siempre lo muestra (Sexo retirado 2026-08-07: 1 letra, se veía mal en móvil)
    $canContact  = true;   // Teléfono / Email — la lista completa siempre los muestra
@endphp

<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4">

        {{-- Encabezado + buscador --}}
        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">Crew</h1>
                    <p class="text-muted mb-0 small">{{ $usuarios->total() }} miembros activos</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-grow-1 flex-lg-grow-0">
                <div class="crew-search flex-grow-1">
                    <label for="search" class="visually-hidden">Buscar por nombre, apellido o email</label>
                    <form onsubmit="return false;">
                        <div class="input-group">
                            <span class="input-group-text border-end-0">
                                @include('componentes._icon', ['name' => 'search', 'class' => 'cc-ico', 'label' => null])
                            </span>
                            <input class="form-control border-start-0 ps-0" id="search" type="search" autocomplete="off" placeholder="Buscar por nombre, apellido o email...">
                        </div>
                    </form>
                </div>

                {{-- Exportar como DOCUMENTO (crew list vertical, no CSV). Menú con propósito
                     opcional de marca de agua elegido al exportar. --}}
                <div class="dropdown">
                    <button class="btn btn-crew-soft text-nowrap d-inline-flex align-items-center gap-1 dropdown-toggle"
                            type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico', 'label' => null])
                        <span>Exportar</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm p-3" style="min-width: 15rem;" aria-label="Exportar Crew List">
                        <form method="GET" action="{{ route('crew.export') }}" target="_blank">
                            <label for="crew-export-purpose" class="form-label small fw-semibold mb-1">Propósito (opcional)</label>
                            <input type="text" name="purpose" id="crew-export-purpose" class="form-control form-control-sm mb-1"
                                   maxlength="60" autocomplete="off" placeholder="p. ej. Crew List para créditos">
                            <div class="form-text small mb-2">Si lo escribes, aparece como marca de agua en el documento.</div>
                            <button type="submit" class="btn btn-crew-accent btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1">
                                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
                                <span>Abrir documento</span>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- ACCIÓN PRIMARIA: crear miembro (antes vivía duplicada en el sidebar). --}}
                @can('users.create')
                <a href="/adduser" class="btn btn-crew-accent text-nowrap d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico', 'label' => null])
                    <span>Nuevo miembro</span>
                </a>
                @endcan
            </div>
        </div>

        {{-- Tabla --}}
        <div class="card border-0 shadow-sm rounded-3">
            <div id="usertable" class="table-responsive" aria-live="polite">
                <table class="table align-middle table-hover mb-0 crew-table cc-stack">
                    <thead>
                        <tr>
                            <th scope="col" class="ps-4">Miembro</th>
                            @if($canPersonal)
                            <th scope="col">F.Nac.</th>
                            @endif
                            @if($canContact)
                            <th scope="col">Teléfono</th>
                            @endif
                            @if($canContact)
                            <th scope="col">Email</th>
                            @endif
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usuarios as $user)
                            <tr>
                                <td class="ps-4" data-label="Miembro">
                                    <div class="d-flex align-items-center gap-3">
                                        {{-- Avatar::has = ¿el archivo existe en disco? Sin esto, una
                                             columna llena que apunta a una foto borrada pintaba el
                                             icono roto en lugar de caer a las iniciales. --}}
                                        @if(\App\Support\Avatar::has($user))
                                            <img src="{{ \App\Support\Avatar::url($user) }}" alt="{{ $user->name }}" class="crew-avatar rounded-circle">
                                        @else
                                            <span class="crew-avatar crew-avatar-initials rounded-circle d-inline-flex align-items-center justify-content-center">
                                                {{ strtoupper(mb_substr($user->name ?? '', 0, 1)) }}{{ strtoupper(mb_substr($user->lname ?? '', 0, 1)) }}
                                            </span>
                                        @endif
                                        <div class="crew-name-cell">
                                            {{-- Nombre a mostrar: crédito o nombre corto (1ª palabra + 1er apellido).
                                                 Sustituye a `name` (parcial) y hace redundante la columna "Apellido". --}}
                                            <span class="crew-name d-block">{{ \App\Models\User::displayName($user) }}</span>
                                            <span class="crew-sub d-block text-muted small">{{ \App\Models\User::positionNameFor($user->id ?? null, $user->puestodepartamento ?? null) }}</span>
                                        </div>
                                    </div>
                                </td>
                                @if($canPersonal)
                                <td class="text-muted" data-label="F.Nac.">{{ $user->borndate }}</td>
                                @endif
                                @if($canContact)
                                <td class="text-muted" data-label="Teléfono">{{ $user->phone }}</td>
                                @endif
                                @if($canContact)
                                <td class="text-muted" data-label="Email">{{ $user->email }}</td>
                                @endif
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones">
                                            @include('componentes._icon', ['name' => 'more-vertical', 'class' => 'cc-ico', 'label' => null])
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a title="ID Card" class="dropdown-item" href="{{ url('/idcard/'.$user->id) }}">
                                                    @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico', 'label' => null]) Credencial
                                                </a>
                                            </li>
                                            <li>
                                                <a title="Edit" class="dropdown-item" href="{{ url('/useredit/'.$user->id) }}">
                                                    @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico', 'label' => null]) Editar
                                                </a>
                                            </li>
                                            @can('medical.view')
                                            <li>
                                                <a title="History" class="dropdown-item" href="{{ url('/historialWR/'.$user->id) }}">
                                                    @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-ico', 'label' => null]) Historial Médico
                                                </a>
                                            </li>
                                            @endcan
                                            {{-- (2026-08-07) RETIRADAS del menú de acciones: "Convertir/Quitar Admin"
                                                 y "Supervisor" (banderas legacy).
                                                 · admin (users.admin) SIGUE VIVA — gatea /importcrew (AdminMiddleware),
                                                   User::canSeePanel() y el rótulo del sidebar; se quitó SOLO del menú, su
                                                   ruta/controlador/columna se conservan intactos.
                                                 · "Supervisor" (users.daytest) era bandera MUERTA (nadie leía el valor):
                                                   se borró su parcial, sus rutas (putsup/putadm) y sus métodos. --}}
                                            <li>
                                                @if($user->encuestadiaria === 1)
                                                    <form method="post" action="{{ url('/activarencuesta/'.$user->id) }}">
                                                        @csrf
                                                        <button type="submit" title="Activate WR" class="dropdown-item">
                                                            @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null]) Activate WR
                                                        </button>
                                                    </form>
                                                @endif
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                @if($user->activo === 1)
                                                    <form method="post" action="{{ url('/desactivarusuario/'.$user->id) }}">
                                                        {{ csrf_field() }}
                                                        <button type="submit" title="Deactivate" class="dropdown-item text-danger" onclick="return confirm('¿Desea desactivar el usuario?');">
                                                            @include('componentes._icon', ['name' => 'x-circle', 'class' => 'cc-ico', 'label' => null]) Desactivar
                                                        </button>
                                                    </form>
                                                @else
                                                    <form method="post" action="{{ url('/activarusuario/'.$user->id) }}">
                                                        @csrf
                                                        <button type="submit" title="Activate" class="dropdown-item text-success">
                                                            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico', 'label' => null]) Activar
                                                        </button>
                                                    </form>
                                                @endif
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="crew-empty text-center py-5">
                                        <div class="crew-empty-icon mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle">
                                            @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico', 'label' => null])
                                        </div>
                                        <h5 class="mb-1">Sin miembros de crew</h5>
                                        <p class="text-muted mb-0">No hay usuarios activos que mostrar.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($usuarios->hasPages())
            <div class="card-footer border-0 py-3">
                {!! $usuarios->links() !!}
            </div>
            @endif
        </div>

    </div>
</div>

<script>
    $( document ).ready(function() {
        var searchTimer = null;
        function runSearch() {
            var valor = document.getElementById("search").value;
            if (valor === "") { valor = "vacio"; }
            fetch('/searchusers/'+encodeURIComponent(valor)+'/?page=1',{
                method: 'get'
            }).then(function(response){
                return response.text();
            }).then(function(htmlContent){
                if(htmlContent === ""){
                    $('#usertable').html('<div class="crew-empty text-center py-5"><h5 class="mb-1">No se encontraron usuarios.</h5></div>');
                }else{
                    $('#usertable').html(htmlContent);
                }
            }).catch(function(err){
                console.log(err);
            });
        }
        // Debounce ~250ms: antes disparaba 1 fetch por tecla.
        $( "#search" ).keyup(function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(runSearch, 250);
        });
    });
</script>

@push('styles')
    @include('componentes._crew-list-styles')
@endpush

@endsection
