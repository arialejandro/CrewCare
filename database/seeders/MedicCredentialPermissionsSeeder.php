<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * MedicCredentialPermissionsSeeder — PASO B: quién puede VALIDAR una cédula. 2026-07-19.
 *
 * Crea y reparte UN permiso: `medic.credential.manage`.
 *
 * ── POR QUÉ UN PERMISO NUEVO Y NO UNO EXISTENTE ──────────────────────────────────
 * El brief del Paso B proponía colgar el módulo de `documents.view` / `documents.sign`,
 * "ya sembrados en el RBAC". Se comprobó y esa premisa NO se sostiene: esos dos permisos
 * son FANTASMA — se declaran (RolesAndPermissionsSeeder.php:65), se reparten a seis roles
 * y se etiquetan en la matriz de la UI (RolePermissionController.php:102,105), pero NO
 * aparecen en NINGUNA ruta, `@can`, middleware ni policy. Cero usos funcionales en todo el
 * repo; el propio AUTH-RBAC-PLAN.md los marca "(futuro, ROADMAP Fase 2)". Colgar de ahí la
 * validación de cédulas sería colgarla del aire: cualquiera pasaría.
 *
 * Tampoco sirve reutilizar `medical.*`: el rol `medic` tiene `medical.update`, y con eso un
 * médico podría VALIDAR SU PROPIA CÉDULA. Eso vacía el módulo de sentido (ver el punto 1 del
 * docblock de MedicCredentialController).
 *
 * ── QUIÉN LO RECIBE, Y QUIÉN NO ──────────────────────────────────────────────────
 * Lo reciben producción/compliance: `line-producer` y `coordinator` (más `super-admin`,
 * explícito aunque Gate::before ya lo deje pasar todo — la matriz de la UI debe decir la
 * verdad sobre quién tiene qué).
 *
 * NO lo recibe `medic`, Y ESO ES EL PUNTO: quien ejerce como médico no acredita su propia
 * licencia. Puede CAPTURAR su número (MedicCredentialController::canCapture) pero nace
 * pendiente y no puede encender su propio badge.
 * NO lo recibe `hod`: un jefe de departamento no es autoridad de compliance profesional.
 * NO lo recibe `auditor`: audita, no avala.
 *
 * ⚠️ AL PROBARLO: `Gate::before` (AuthServiceProvider.php:34) hace que super-admin pase
 * TODOS los permisos. Probar el gate SOLO con un usuario no-super-admin, o parecerá abierto.
 *
 * ADITIVO E IDEMPOTENTE: givePermissionTo(), NUNCA syncPermissions() (borraría la matriz
 * viva de cada rol). Re-correrlo no cambia nada. Roles ausentes se omiten sin tronar.
 * Mismo molde que MedicRolePermissionsSeeder y SdsPermissionsSeeder.
 *
 * Correr:  php artisan db:seed --class=MedicCredentialPermissionsSeeder
 *          (en prod, después:  php artisan cache:clear  — store de permisos `file`, 24 h)
 *
 * NO registrar en DatabaseSeeder.
 */
class MedicCredentialPermissionsSeeder extends Seeder
{
    /** El permiso que autoriza el ACTO DE AUTORIDAD: validar una cédula contra el registro. */
    private const PERMISSION = 'medic.credential.manage';

    /** Roles que validan cédulas. `medic` NO está aquí a propósito (ver docblock). */
    private const GRANT_TO = ['super-admin', 'line-producer', 'coordinator'];

    public function run()
    {
        // Cache de spatie: limpiar ANTES (leer el estado real) y DESPUÉS (publicar el grant).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => $guard]);

        foreach (self::GRANT_TO as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();

            if (!$role) {
                if ($this->command) {
                    $this->command->warn('MedicCredentialPermissionsSeeder: el rol `'.$roleName.'` no existe en esta BD; se omite.');
                }
                continue;
            }

            $role->givePermissionTo(self::PERMISSION); // aditivo
            if ($this->command) {
                $this->command->info('MedicCredentialPermissionsSeeder: '.$roleName.' ← '.self::PERMISSION);
            }
        }

        // Comprobación explícita de la invariante que sostiene el módulo. Si alguien
        // repartiera este permiso a `medic` desde la pantalla "Permisos por rol", el badge
        // dejaría de significar "alguien externo cotejó esta licencia" — y nadie lo notaría.
        $medic = Role::where('name', User::MEDIC_ROLE)->where('guard_name', $guard)->first();
        if ($medic && $medic->hasPermissionTo(self::PERMISSION) && $this->command) {
            $this->command->warn(
                'MedicCredentialPermissionsSeeder: ¡ATENCIÓN! el rol `'.User::MEDIC_ROLE.'` TIENE '.self::PERMISSION.'. '
                .'Este seeder no se lo dio. Revísalo: un médico que puede validar cédulas puede acreditarse a sí mismo.'
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
