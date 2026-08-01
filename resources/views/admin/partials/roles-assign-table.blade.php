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
                    $currentRole = $u->getRoleNames()->first();
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
                        <select name="role" form="{{ $fid }}" class="form-select form-select-sm">
                            @foreach($roles as $r)
                                <option value="{{ $r }}" {{ $currentRole === $r ? 'selected' : '' }}>{{ $r }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td data-label="Departamento">
                        <select name="department_id" form="{{ $fid }}" class="form-select form-select-sm">
                            <option value="">— sin departamento —</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}" {{ (int) $currentDept === (int) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    @if($isSuperAdmin ?? false)
                    <td data-label="Acceso clínico">
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

                        {{-- (2026-07-24 · PASO 2/3, item 5) KEY MEDIC. Sólo tiene sentido sobre un
                             MÉDICO: le deja ver TODAS las consultas (no sólo las suyas) y emitir la
                             bitácora y el conteo consolidados. Mismo patrón que arriba: permiso
                             DIRECTO, nunca por rol, sólo el super-admin, con registro. Si el SQL
                             owner-apply no se ha aplicado el permiso no existe → no se pinta. --}}
                        @if($currentRole === 'medic' && ($keyMedicPermReady ?? false))
                            <div class="mt-1">
                                @if($isKey)
                                    <span class="badge bg-primary me-1" title="Ve todas las consultas y emite la bitácora y el conteo.">Key medic</span>
                                    <form method="POST" action="{{ route('roles.medical.revoke', $u->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="permission" value="medical.consolidate">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">Quitar key</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('roles.medical.grant', $u->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="permission" value="medical.consolidate">
                                        <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2" title="Consolida la función semanal: ve todas las consultas y emite los reportes.">Hacer key medic</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </td>
                    @endif
                    <td class="text-end pe-3">
                        <button form="{{ $fid }}" type="submit" class="btn btn-sm btn-primary">Guardar</button>
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
