<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Editor EN VIVO de la matriz rol → permiso (tabla role_has_permissions de spatie).
 *
 * Por qué existe: la matriz "de fábrica" vive en RolesAndPermissionsSeeder.php (código).
 * Cambiarla a media producción implicaría editar y re-desplegar, lo cual es arriesgado en
 * una app con datos sensibles. spatie ya persiste la matriz en la BD, así que esta pantalla
 * permite ajustar permisos sin tocar código. El seeder pasa a ser el VALOR DE FÁBRICA; la
 * BD es el ESTADO VIVO. (Re-correr el seeder restablece la matriz a fábrica — documentado.)
 *
 * Protegida por `permission:roles.manage-permissions` (la ruta). Por defecto SOLO super-admin
 * tiene ese permiso; desde esta misma pantalla el dueño puede concedérselo a line-producer.
 *
 * Salvaguardas:
 *  - super-admin NO es editable (god-mode vía Gate::before; su columna se muestra bloqueada).
 *  - Anti-auto-bloqueo: un no-super-admin no puede guardar una matriz que se quite a sí mismo
 *    `roles.manage-permissions` (se quedaría sin acceso a esta pantalla).
 */
class RolePermissionController extends Controller
{
    /**
     * Roles EDITABLES (columnas con casillas activas). super-admin se excluye: es god-mode.
     */
    private const EDITABLE_ROLES = [
        'line-producer', 'coordinator', 'hod', 'medic', 'safety-officer', 'crew', 'auditor',
    ];

    /** Permiso que protege esta pantalla (para el guard anti-auto-bloqueo). */
    private const SELF_PERMISSION = 'roles.manage-permissions';

    /**
     * Agrupación + etiquetas en español de los permisos, para que la matriz sea legible.
     * El ORDEN aquí define el orden en pantalla. Cualquier permiso de la BD que NO esté
     * listado aquí se agrega automáticamente a un grupo "Otros" (nada queda oculto).
     */
    private const GROUPS = [
        'Producciones' => [
            'productions.view'           => 'Ver producciones',
            'productions.create'         => 'Crear producciones',
            'productions.update'         => 'Editar producciones',
            'productions.delete'         => 'Eliminar producciones',
            'productions.manage-members' => 'Gestionar miembros de la producción',
        ],
        'Crew y usuarios' => [
            'users.view'                 => 'Ver usuarios',
            'users.create'              => 'Crear usuarios',
            'users.update'              => 'Editar usuarios',
            'users.deactivate'          => 'Desactivar usuarios',
            'users.assign-role'         => 'Asignar roles',
            'users.assign-department'   => 'Asignar departamento',
            'crew.register'             => 'Registrar crew (onboarding)',
            'crew.view'                 => 'Ver / buscar crew',
            'crew.view.contact'         => 'Ver contacto (tel/email)',
            'crew.view.personal'        => 'Ver datos personales (nacimiento/sexo)',
            'crew.view.all-departments' => 'Ver TODOS los departamentos (sin esto: solo el suyo)',
        ],
        'Catálogos' => [
            'catalogs.view'   => 'Ver catálogos',
            'catalogs.manage' => 'Gestionar catálogos (depto/puesto/notif.)',
        ],
        'Catálogo: Normas y Eventos' => [
            'standards.view'        => 'Ver normas',
            'standards.create'      => 'Agregar normas',
            'standards.manage'      => 'Gestionar/verificar normas',
            'hazardevents.view'     => 'Ver eventos',
            'hazardevents.create'   => 'Agregar eventos',
            'hazardevents.manage'   => 'Gestionar/verificar eventos',
        ],
        'Seguridad (H&S)' => [
            'injury.view'      => 'Ver lesiones',
            'injury.create'    => 'Reportar lesiones',
            'injury.manage'    => 'Gestionar lesiones',
            'hazards.view'     => 'Ver condiciones inseguras',
            'hazards.create'   => 'Reportar condiciones inseguras',
            'hazards.manage'   => 'Gestionar condiciones inseguras',
            'locations.view'   => 'Ver locaciones',
            'locations.create' => 'Crear locaciones / scoutings',
            'locations.manage' => 'Gestionar locaciones',
            'dsr.view'         => 'Ver reporte diario de seguridad',
            'dsr.create'       => 'Crear reporte diario de seguridad',
            'dsr.update'       => 'Editar reporte diario de seguridad',
            'dsr.export'       => 'Exportar reporte diario de seguridad',
        ],
        'Médico (sensible)' => [
            'medical.view'   => 'Ver consultas médicas',
            'medical.create' => 'Crear consultas médicas',
            'medical.update' => 'Editar consultas médicas',
            // OJO: valida la cédula profesional de OTROS. NO dárselo al rol `medic` —
            // se acreditaría a sí mismo y el badge dejaría de significar nada
            // (ver MedicCredentialPermissionsSeeder).
            'medic.credential.manage' => 'Validar cédulas profesionales',
        ],
        'Reportes' => [
            'reports.view'   => 'Ver reportes',
            'reports.export' => 'Exportar reportes',
        ],
        'Documentos' => [
            'documents.view'             => 'Ver documentos',
            'documents.create'           => 'Crear documentos',
            'documents.assign'           => 'Asignar documentos',
            'documents.sign'             => 'Firmar documentos',
            'documents.manage-templates' => 'Gestionar plantillas',
        ],
        'Perfil' => [
            'profile.update-own' => 'Editar su propio perfil',
        ],
        'Administración' => [
            'roles.manage-permissions' => 'Editar la matriz de permisos (esta pantalla)',
        ],
    ];

    public function edit()
    {
        // Roles a mostrar como columnas: super-admin primero (bloqueado), luego los editables
        // en el orden de EDITABLE_ROLES (estable, no alfabético).
        $roleOrder = array_merge(['super-admin'], self::EDITABLE_ROLES);
        $roles = Role::with('permissions')->get()
            ->sortBy(function ($r) use ($roleOrder) {
                $i = array_search($r->name, $roleOrder, true);
                return $i === false ? 999 : $i;
            })
            ->values();

        // Matriz: [roleName][permName] => bool
        $matrix = [];
        foreach ($roles as $role) {
            $owned = $role->permissions->pluck('name')->all();
            $matrix[$role->name] = array_fill_keys($owned, true);
        }

        // Grupos para la vista: arrancamos de GROUPS y agregamos cualquier permiso de la BD
        // que no esté catalogado (defensa: nada queda oculto si alguien agrega un permiso).
        $groups = self::GROUPS;
        $catalogued = [];
        foreach ($groups as $perms) {
            $catalogued = array_merge($catalogued, array_keys($perms));
        }
        $uncatalogued = Permission::whereNotIn('name', $catalogued)->pluck('name')->all();
        if (! empty($uncatalogued)) {
            $groups['Otros'] = array_combine($uncatalogued, $uncatalogued);
        }

        return view('admin.role-permissions', [
            'roles'         => $roles,
            'editableRoles' => self::EDITABLE_ROLES,
            'matrix'        => $matrix,
            'groups'        => $groups,
        ]);
    }

    public function update(Request $request)
    {
        // perms[roleName][] = nombrePermiso. Los roles sin ninguna casilla NO llegan en el
        // payload (HTML no envía checkboxes desmarcados); por eso iteramos EDITABLE_ROLES
        // y no las llaves del request (así un rol vaciado se limpia de verdad).
        $submitted = (array) $request->input('perms', []);

        // Conjunto válido de permisos (whitelist desde la BD) para no confiar en el cliente.
        $validNames = Permission::pluck('name')->all();

        // --- Guard anti-auto-bloqueo (solo aplica a no-super-admin) ---
        $actor = auth()->user();
        if (! $actor->hasRole('super-admin')) {
            $actorRoles = $actor->getRoleNames()->intersect(self::EDITABLE_ROLES);
            $keepsAccess = false;
            foreach ($actorRoles as $r) {
                $wanted = array_values(array_intersect((array) ($submitted[$r] ?? []), $validNames));
                if (in_array(self::SELF_PERMISSION, $wanted, true)) {
                    $keepsAccess = true;
                    break;
                }
            }
            if ($actorRoles->isNotEmpty() && ! $keepsAccess) {
                return back()->with('error',
                    'No puedes quitarte a ti mismo el permiso «'.self::SELF_PERMISSION.
                    '»: te dejaría sin acceso a esta pantalla.');
            }
        }

        // Aplicar la matriz: syncPermissions deja EXACTAMENTE lo enviado por cada rol editable.
        foreach (self::EDITABLE_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                continue;
            }
            $wanted = array_values(array_intersect((array) ($submitted[$roleName] ?? []), $validNames));
            $role->syncPermissions($wanted);
        }

        // spatie cachea permisos: hay que invalidar para que el cambio aplique YA.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'Matriz de permisos actualizada. Los cambios aplican de inmediato.');
    }
}
