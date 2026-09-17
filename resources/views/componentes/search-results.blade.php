{{--
    Resultados AJAX de /searchusers/{valor} (SearchController).
    Reemplaza el contenido de #usertable en admin/usuarioscrud.
    La fila (<tr>) es IDÉNTICA a la de usuarioscrud (mismos data-label, mismos iconos _icon,
    misma clase cc-stack) para que el swap sea invisible.
    $canPersonal / $canContact llegan desde SearchController según permisos del buscador.
--}}
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
                        @if(\App\Support\Avatar::has($user))
                            <img src="{{ \App\Support\Avatar::url($user) }}" alt="{{ $user->name }}" class="crew-avatar rounded-circle">
                        @else
                            <span class="crew-avatar crew-avatar-initials rounded-circle d-inline-flex align-items-center justify-content-center">
                                {{ strtoupper(mb_substr($user->name ?? '', 0, 1)) }}{{ strtoupper(mb_substr($user->lname ?? '', 0, 1)) }}
                            </span>
                        @endif
                        <div class="crew-name-cell">
                            {{-- Nombre a mostrar: crédito o nombre corto (ver User::displayName). --}}
                            <span class="crew-name d-block">{{ \App\Models\User::displayName($user) }}</span>
                            <span class="crew-sub d-block text-muted small">{{ \App\Models\User::positionNameFor($user->id ?? null, $user->puestodepartamento ?? null) }}</span>
                            @isset($contractStatus[$user->id])
                                <span class="d-inline-block mt-1">@include('componentes._contract-badge', ['cs' => $contractStatus[$user->id]])</span>
                            @endisset
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
                            {{-- (2026-08-07) Retiradas: "Convertir/Quitar Admin" (admin sigue viva,
                                 solo fuera del menú) y "Supervisor" (daytest, código muerto borrado).
                                 Ver nota en admin/usuarioscrud.blade.php. --}}
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
                                    <form method="post" action="{{ url('/desactivarusuario/'.$user->id) }}" data-confirm="¿Desea desactivar el usuario?">
                                        {{ csrf_field() }}
                                        <button type="submit" title="Deactivate" class="dropdown-item text-danger">
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
                        <h5 class="mb-1">Sin resultados</h5>
                        <p class="text-muted mb-0">No se encontraron miembros de crew.</p>
                    </div>
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
@if($usuarios->hasPages())
<div class="card-footer border-0 py-3">
    {!! $usuarios->links() !!}
</div>
@endif
