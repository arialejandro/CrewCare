<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * RBAC foundation seeder — 8 GLOBAL roles + granular permission catalog + matrix.
 *
 * Source of truth: AUTH-RBAC-PLAN.md B.1.2 (permission catalog), B.1.3 (role->permission
 * matrix). Idempotent (firstOrCreate / syncPermissions) so it is safe to re-run.
 *
 * Design notes:
 *  - Roles are DATA, not code (PROGRESS.md). New roles = new row + permission assignment,
 *    no schema change. The investment is the granular, well-named PERMISSION catalog;
 *    each role is just a combination of permissions. Code checks PERMISSIONS, never roles.
 *  - super-admin gets EVERY permission directly (also wired to a Gate::before in the app
 *    later). auditor gets ONLY the *.view permissions (read-only platform/compliance role).
 *  - crew.register exists so onboarding is a PERMISSION (a coordinator/PA can register
 *    externals / register on behalf of self-unmanaged departments) — the FIRM requirement.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles/permissions before seeding.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        // ---- 1. Granular permission catalog (AUTH-RBAC-PLAN.md B.1.2 + crew.register) ----
        $permissions = [
            // Productions
            'productions.view', 'productions.create', 'productions.update', 'productions.delete',
            'productions.manage-members',
            // Crew / Users (incl. onboarding)
            'users.view', 'users.create', 'users.update', 'users.deactivate',
            'users.assign-role', 'users.assign-department',
            'crew.register', 'crew.view',
            // Crew search — role-aware field/scope visibility (crew.view is the gate to search;
            // these refine WHAT is shown. Clinical data stays behind medical.view).
            'crew.view.contact',          // ver teléfono/email en resultados de búsqueda
            'crew.view.personal',         // ver fecha de nacimiento / sexo
            'crew.view.all-departments',  // alcance: ver TODOS los departamentos (ausencia ⇒ solo su propio departamento)
            // Catalogs (departments / positions / notifications)
            'catalogs.view', 'catalogs.manage',
            // H&S — Injuries
            'injury.view', 'injury.create', 'injury.manage',
            // H&S — Hazards / unsafe conditions
            'hazards.view', 'hazards.create', 'hazards.manage',
            // H&S — Locations (audit)
            'locations.view', 'locations.create', 'locations.manage',
            // H&S — DSR (daily safety report)
            'dsr.view', 'dsr.create', 'dsr.update', 'dsr.export',
            // Reports (cross-cutting view/export — auditor relevant)
            'reports.view', 'reports.export',
            // Medical (sensitive)
            'medical.view', 'medical.create', 'medical.update',
            'medical.materials', // conteo interno de medicamentos (presupuesto/materialidad) — 2026-07-06
            // (2026-07-24 · PASO 2/3 médico, item 5) KEY MEDIC. NO se asigna a NINGÚN rol a
            // propósito — ni siquiera a `medic`: nace apagado y el super-admin lo enciende
            // deliberadamente a la PERSONA desde /rolescrud (mismo patrón que el acceso clínico
            // del safety-officer). Efecto: el médico ve TODAS las consultas (no sólo las suyas)
            // y puede emitir la bitácora y el conteo consolidados.
            // NO se usó `production_user.is_lead`: los médicos viven en "Salud y Seguridad",
            // depto que comparten con los safety officers → el is_lead de esa área casi nunca
            // es un médico (verificado en vivo: los 3 médicos tienen is_lead=0).
            'medical.consolidate',
            // Documents (future module — vocabulary fixed from day 1)
            'documents.view', 'documents.create', 'documents.assign', 'documents.sign',
            'documents.manage-templates',
            // Contract Builder — REDACTAR/ensamblar plantillas de contrato. Gate del builder; NO es
            // settings.manage. Solo Line Producer / representante legal: el contenido LEGAL es de la
            // PRODUCTORA (CrewCare solo ensambla, numera y estampa firmas; no redacta). 2026-08-14
            'contracts.author',
            // Self profile
            'profile.update-own',
            // RBAC self-management — editar la matriz rol→permiso EN VIVO desde la UI.
            // Solo super-admin por defecto (god-mode). Permite ajustar permisos a media
            // producción sin re-desplegar código. Gate de la pantalla "Permisos por rol".
            'roles.manage-permissions',
            // Configuración / Branding (solo super-admin) — panel de Marca en vivo.
            'settings.manage',
            // Diseño de gafete (plantilla configurable) — super-admin + line-producer + coordinador. 2026-07-06
            'badge.design',
            // SDS / consumibles SFX (Pilar 3, flag 'sds_sfx') — 2026-07-16
            // sds.manage = autoridad verificadora (valida fichas de campo) + depuración del catálogo.
            'sds.view', 'sds.create', 'sds.manage',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        // ---- 2. Roles (9: 7 org-chart roles + auditor + representante-legal) ----
        // representante-legal (2026-08-14): figura LEGAL de la productora — FIRMA documentos y
        // CREA/ensambla contratos (Contract Builder). Roles son DATA: un rol nuevo = una fila + su set
        // de permisos, sin cambio de esquema.
        $roleNames = [
            'super-admin', 'line-producer', 'coordinator', 'hod',
            'medic', 'safety-officer', 'crew', 'auditor', 'representante-legal',
        ];
        $roles = [];
        foreach ($roleNames as $r) {
            $roles[$r] = Role::firstOrCreate(['name' => $r, 'guard_name' => $guard]);
        }

        // ---- PROTECCIÓN: no pisar una matriz YA personalizada en vivo ----
        // Modelo de despliegue: este seeder corre UNA vez sobre BD vacía al levantar la
        // instancia. Si se re-corre por error sobre una instancia VIVA (donde un super-admin
        // ya ajustó permisos desde la pantalla "Permisos por rol"), NO debemos resetear esos
        // grants a fábrica. Detectamos "ya sembrado" = algún rol ya tiene permisos. Los
        // permisos y roles nuevos del catálogo SÍ quedaron registrados arriba (firstOrCreate),
        // pero NO re-sincronizamos los grants existentes. Override explícito para un reset
        // intencional a fábrica: RBAC_FORCE_RESEED=true en el entorno.
        if (Role::has('permissions')->exists() && ! env('RBAC_FORCE_RESEED', false)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->command->warn('RBAC: la matriz ya existe en BD — NO se re-aplica (se preservan los cambios en vivo).');
            $this->command->warn('      Reset intencional a fábrica: RBAC_FORCE_RESEED=true php artisan db:seed --class=RolesAndPermissionsSeeder');
            $this->command->info('Roles: '.Role::count().' | Permissions: '.Permission::count().' (sin cambios en grants)');

            return;
        }

        // ---- 3. Role -> permission matrix (AUTH-RBAC-PLAN.md B.1.3) ----
        // super-admin: ALL permissions (also reinforced by Gate::before in app).
        $roles['super-admin']->syncPermissions(Permission::all());

        // line-producer
        $roles['line-producer']->syncPermissions([
            'productions.view', 'productions.create', 'productions.update', 'productions.delete',
            'productions.manage-members',
            'users.view', 'users.create', 'users.update', 'users.deactivate',
            'users.assign-role', 'users.assign-department',
            'crew.register', 'crew.view',
            'crew.view.contact', 'crew.view.all-departments',
            'catalogs.view', 'catalogs.manage',
            'injury.view', 'injury.create', 'injury.manage',
            'hazards.view', 'hazards.create', 'hazards.manage',
            'locations.view', 'locations.create', 'locations.manage',
            'dsr.view', 'dsr.create', 'dsr.update', 'dsr.export',
            'reports.view', 'reports.export',
            'medical.view', // ve consultas médicas (matriz de menú, owner 2026-06-24) — dato sensible
            'medical.materials', // conteo interno de medicamentos (presupuesto/materialidad) — 2026-07-06
            'documents.view', 'documents.create', 'documents.assign', 'documents.sign',
            'documents.manage-templates',
            'contracts.author', // redactar/ensamblar plantillas de contrato (2026-08-14)
            'badge.design', // diseñar plantilla de gafete (2026-07-06)
            'sds.view', 'sds.create', 'sds.manage', // SDS/consumibles SFX: autoridad verificadora (2026-07-16)
            'profile.update-own',
        ]);

        // coordinator
        $roles['coordinator']->syncPermissions([
            'productions.view', 'productions.manage-members',
            'users.view', 'users.create', 'users.update', 'users.assign-department',
            'crew.register', 'crew.view',
            'crew.view.contact', 'crew.view.all-departments',
            'catalogs.view',
            'injury.view', 'hazards.view',
            'locations.view', 'locations.create', // Scoutings / crear scouting (matriz de menú, owner 2026-06-24)
            'dsr.view',
            'reports.view',
            'medical.view', // ve consultas médicas / expediente (grant de menú médico, 2026-07-06) — dato sensible
            'documents.view', 'documents.create', 'documents.assign', 'documents.sign',
            'badge.design', // diseñar plantilla de gafete (2026-07-06)
            'profile.update-own',
        ]);

        // hod (head of department)
        $roles['hod']->syncPermissions([
            'productions.view',
            'users.view', 'users.create', 'crew.view', // + alta de crew de su área (matriz de menú, owner 2026-06-24)
            'crew.view.contact', // HOD restringido a su propio departamento (sin all-departments)
            'catalogs.view',
            'injury.view', 'hazards.view',
            'dsr.view',
            'reports.view',
            'medical.view', // ve consultas médicas / expediente (grant de menú médico, 2026-07-06) — dato sensible
            'documents.view', 'documents.create', 'documents.assign', 'documents.sign',
            'profile.update-own',
        ]);

        // medic
        $roles['medic']->syncPermissions([
            'crew.view',
            'crew.view.contact', 'crew.view.personal', 'crew.view.all-departments',
            'injury.view', 'injury.create', // reportar accidentes (requisito del owner)
            'hazards.create', 'hazards.manage', // gestiona peligros (grant historico de MedicRolePermissionsSeeder, unificado al base 2026-08-11; sin .view, igual que el vivo)
            'medical.view', 'medical.create', 'medical.update', 'medical.materials',
            'documents.view', 'documents.sign',
            'reports.view',
            'profile.update-own',
        ]);

        // safety-officer
        $roles['safety-officer']->syncPermissions([
            'productions.view',
            'crew.view',
            'crew.view.contact', 'crew.view.all-departments',
            'catalogs.view',
            'injury.view', 'injury.create', 'injury.manage',
            'hazards.view', 'hazards.create', 'hazards.manage',
            'locations.view', 'locations.create', 'locations.manage',
            'dsr.view', 'dsr.create', 'dsr.update', 'dsr.export',
            'reports.view', 'reports.export',
            // (2026-07-24 · PASO 1/3 médico, item 7) medical.view YA NO viene por rol: el acceso al
            // EXPEDIENTE CLÍNICO del safety-officer depende de la CONFIANZA DEL PROYECTO, no del puesto.
            // Nace SIN acceso clínico; el super-admin se lo otorga DIRECTO a la persona (permiso Spatie
            // directo) desde /rolescrud, con registro en medical_access_grants. Ve a TODO el crew cuando
            // se le otorga (no acotado por depto, a diferencia del HOD). medical.materials SÍ sigue por
            // rol: es logística/presupuesto de medicamentos, sin dato clínico.
            'medical.materials', // conteo interno de medicamentos (presupuesto/materialidad) — 2026-07-06
            'documents.view', 'documents.sign',
            'sds.view', 'sds.create', // captura fichas SDS en campo; las valida un sds.manage (2026-07-16)
            'profile.update-own',
        ]);

        // crew (self-service; can file own injury/hazard reports. Tras crear, aterriza en /home
        // —NO ve la ficha, que exige injury.view— ver BUG-INC-01. El silo médico lo gatea
        // InjuryReportPolicy::viewMedical.)
        $roles['crew']->syncPermissions([
            'injury.create', 'hazards.create',
            'documents.view', 'documents.sign',
            'profile.update-own',
        ]);

        // representante-legal — figura LEGAL de la productora: FIRMA documentos y CREA/ensambla
        // contratos (Contract Builder). NO es producción operativa: su acceso se acota a eso + su
        // perfil. (Quién FIRMA cada sobre lo define el PUESTO en la ruta, no este rol; el rol solo da
        // acceso a la app.) Si además debe ORIGINAR sobres para un payee, se le suma payees.view/capture.
        $roles['representante-legal']->syncPermissions([
            'contracts.author',
            'documents.view', 'documents.sign',
            'profile.update-own',
        ]);

        // auditor: ONLY *.view permissions (read-only platform/compliance).
        // EXCEPTO `medical.view` — el expediente clínico es dato sensible; el auditor NO lo ve.
        $viewOnly = Permission::where('name', 'like', '%.view')
            ->where('name', '!=', 'medical.view')
            ->pluck('name')->all();
        // Auditor read-only: + ver TODOS los departamentos, pero SIN PII (contact/personal).
        $roles['auditor']->syncPermissions(array_merge($viewOnly, ['crew.view.all-departments']));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('Roles: '.Role::count().' | Permissions: '.Permission::count());
        $this->command->info('auditor view-only permissions: '.count($viewOnly));
    }
}
