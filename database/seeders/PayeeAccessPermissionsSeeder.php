<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * PASO 4 · VISIBILIDAD — permiso del módulo "quien cobra" (payees.view).
 *
 * Puerta del MÓDULO (menú + listado + serve gateado); el SCOPE fino ("quien contrata es
 * quien ve") lo pone {@see \App\Models\Payee::scopeVisibleTo}. Se concede a la oficina de
 * producción (line-producer, coordinator → ven todo por crew.view.all-departments) y al HOD
 * (acotado a su departamento). El super-admin pasa por Gate::before.
 *
 * NO se concede por defecto a safety/medic/crew/auditor (no contratan). El owner puede
 * encenderlo/apagarlo por rol desde /permisoscrud (la matriz viva).
 *
 * DEBE correr DESPUÉS de RolesAndPermissionsSeeder: así el barrido `%.view` del auditor (que
 * corre antes) NO se lleva payees.view por accidente — aquí se decide explícito quién lo tiene.
 *
 * ADITIVO E IDEMPOTENTE: firstOrCreate + givePermissionTo (nunca syncPermissions).
 * A mano: php artisan db:seed --class=PayeeAccessPermissionsSeeder + cache:clear
 */
class PayeeAccessPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $guard = 'web';

        Permission::firstOrCreate(['name' => 'payees.view', 'guard_name' => $guard]);

        $matrix = [
            'line-producer' => ['payees.view'],
            'coordinator'   => ['payees.view'],
            'hod'           => ['payees.view'],
            'super-admin'   => ['payees.view'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                $this->command->warn("Rol '{$roleName}' no existe; se omite (no rompe).");
                continue;
            }
            $role->givePermissionTo($grants);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command->info('PayeeAccessPermissionsSeeder: payees.view → line-producer, coordinator, hod, super-admin.');
    }
}
