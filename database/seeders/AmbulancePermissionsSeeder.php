<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * AmbulancePermissionsSeeder — permiso del módulo "verificación de ambulancias".
 *
 * UN SOLO PUNTO, TODO POR EL SAFETY: el bloque manda que la verificación de la
 * ambulancia, la tripulación y los documentos del proveedor pasen por el safety,
 * sin repartir entre roles. Por eso hay UN permiso `ambulance.manage` (no varios).
 * Se asigna a safety-officer y super-admin (super-admin además pasa por Gate::before).
 *
 * ADITIVO E IDEMPOTENTE: firstOrCreate + givePermissionTo (nunca syncPermissions).
 * Correr a mano: php artisan db:seed --class=AmbulancePermissionsSeeder + cache:clear
 */
class AmbulancePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $guard = 'web';

        Permission::firstOrCreate(['name' => 'ambulance.manage', 'guard_name' => $guard]);

        $matrix = [
            'safety-officer' => ['ambulance.manage'],
            'super-admin'    => ['ambulance.manage'],
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
        $this->command->info('AmbulancePermissionsSeeder: ambulance.manage → safety-officer, super-admin.');
    }
}
