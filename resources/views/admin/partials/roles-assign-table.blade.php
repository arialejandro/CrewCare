{{-- Tabla de asignación de roles. Se usa en DOS lugares con el MISMO HTML:
       1) la página completa (@include desde admin/roles-assign.blade.php), y
       2) la respuesta AJAX del buscador en vivo (RoleAssignmentController@index con ?partial=1),
          que el front inyecta en #rolesResults.
     Recibe: $users (paginado), $roles, $departments, $deptByUser, $q. --}}
<div class="d-flex align-items-center px-3 pt-3 mb-2">
    <span class="roles-count">
        {{ $users->total() }} usuario(s){{ $q !== '' ? ' — filtro: «'.$q.'»' : '' }}
    </span>
</div>

<div class="table-responsive">
    <table class="table roles-table cc-stack table-sm table-hover align-middle mb-0">
        <thead>
            <tr>
                <th class="ps-3">Crew</th>
                <th>Email</th>
                <th style="min-width: 160px;">Rol</th>
                <th style="min-width: 180px;">Departamento</th>
                @if($isSuperAdmin ?? false)<th style="min-width: 150px;" title="Acceso DIRECTO al expediente clínico (medical.view) y, en los médicos, el key medic (medical.consolidate). Solo el super-admin los otorga/revoca; queda registrado.">Acceso clínico</th>@endif
                <th class="text-end pe-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $u)
                @php
                    $roleNames   = $u->getRoleNames();
                    $currentRole = $roleNames->first();
                    // El super-admin es el ROL MÁXIMO: no está en la lista asignable, así que NO se
                    // reasigna desde aquí (si se pintara el <select>, saldría "line-producer" por
                    // defecto y un operador podría degradarlo). Fila en modo LECTURA + badge bloqueado.
                    $isRowSuper  = $roleNames->contains('super-admin');
                    $currentDept = $deptByUser[$u->id] ?? null;
                    $fid = 'rolef-'.$u->id;
                @endphp
                <tr>
                    <td data-label="Crew" class="ps-3 fw-semibold">
                        {{ $u->name }} {{ $u->lname }}
                        {{-- form vacío (solo CSRF); los selects/botón se asocian por `form="..."`. --}}
                        <form id="{{ $fid }}" method="POST" action="{{ route('roles.update', $u->id) }}">
                            @csrf
                        </form>
                    </td>
                    <td data-label="Email" class="small cc-muted">{{ $u->email }}</td>
                    <td data-label="Rol">
                        @if($isRowSuper)
                            <span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1"
                                  title="Rol máximo: gestiona permisos, marca y feature flags. No se reasigna desde esta pantalla.">
                                @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico-16', 'label' => null])
                                super-admin
                            </span>
                        @else
                            <select name="role" form="{{ $fid }}" class="form-select form-select-sm">
                                @foreach($roles as $r)
                                    <option value="{{ $r }}" {{ $currentRole === $r ? 'selected' : '' }}>{{ $r }}</option>
                                @endforeach
                            </select>
                        @endif
                    </td>
                    <td data-label="Departamento">
                        @if($isRowSuper)
                            <span class="cc-muted small">Acceso total</span>
                        @else
                            <select name="department_id" form="{{ $fid }}" class="form-select form-select-sm">
                                <option value="">— sin departamento —</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d->id }}" {{ (int) $currentDept === (int) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </td>
                    @if($isSuperAdmin ?? false)
                    <td data-label="Acceso clínico">
                        @if($isRowSuper)
                        <span class="badge bg-secondary" title="El super-admin ve y consolida todo el expediente por diseño (Gate::before).">Acceso total</span>
                        @else
                        @php
                            // Acceso DIRECTO (permiso Spatie sobre la persona) vs por ROL. El toggle
                            // solo controla el directo; si viene por rol se avisa (cambiar el rol es
                            // lo que lo retira). Ver RoleAssignmentController::grantMedical/revokeMedical.
                            $hasDirect = in_array($u->id, $directMedicalIds ?? [], false);
                            $viaRole   = in_array($currentRole, $rolesWithMedical ?? [], true);
                            $isKey     = in_array($u->id, $keyMedicIds ?? [], false);
                        @endphp
                        @if($viaRole)
                            <span class="badge bg-secondary" title="Su ROL ya concede el acceso clínico; el otorgamiento directo sería redundante.">Por rol</span>
                        @elseif($hasDirect)
                            <span class="badge bg-success me-1">Con acceso</span>
                            <form method="POST" action="{{ route('roles.medical.revoke', $u->id) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">Revocar</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('roles.medical.grant', $u->id) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2">Otorgar</button>
                            </form>
                        @endif

                        {{-- (2026-08-11 · BUG-01 opción B / BUG-04) CONSOLIDACIÓN DE LA BITÁCORA
                             (medical.consolidate): ve TODAS las consultas y emite la bitácora y el
                             conteo semanal. Permiso DIRECTO, nunca por rol, sólo el super-admin lo
                             da/quita, con registro. Aplica al MÉDICO y también a safety-officer /
                             producción — el super-admin decide quién consolida (respaldo si no hay
                             médico key). Si el permiso no existe aún (SQL owner-apply) no se pinta. --}}
                        @if(in_array($currentRole, ['medic', 'safety-officer', 'line-producer', 'coordinator', 'hod'], true) && ($keyMedicPermReady ?? false))
                            <div class="mt-1">
                                @if($isKey)
                                    <span class="badge bg-primary me-1" title="Ve todas las consultas y emite la bitácora y el conteo.">Consolida bitácora</span>
                                    <form method="POST" action="{{ route('roles.medical.revoke', $u->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="permission" value="medical.consolidate">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">Quitar consolidación</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('roles.medical.grant', $u->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="permission" value="medical.consolidate">
                                        <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2" title="Deja ver todas las consultas y emitir la bitácora y el conteo semanal.">Dar consolidación</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                        @endif {{-- cierra @if($isRowSuper) --}}
                    </td>
                    @endif
                    <td class="text-end pe-3">
                        @unless($isRowSuper)
                        <button form="{{ $fid }}" type="submit" class="btn btn-sm btn-primary">Guardar</button>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ ($isSuperAdmin ?? false) ? 6 : 5 }}" class="text-center cc-muted py-3">Sin resultados{{ $q !== '' ? ' para «'.$q.'»' : '' }}.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="px-3 py-3">
    {{ $users->links() }}
</div>
