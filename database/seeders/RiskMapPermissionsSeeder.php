<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permiso del módulo Mapeo de riesgos y recursos (delta #50).
 *
 * ADITIVO e idempotente (mismo patrón que MedevacPermissionsSeeder): crea
 * `riskmap.issue` y lo asigna a safety-officer + super-admin. NO usa
 * syncPermissions() (no pisa lo existente). super-admin además pasa todo por
 * Gate::before; se lista explícito para no depender solo de eso.
 *
 * Ejecutar FUERA de migrate:
 *   php artisan db:seed --class=RiskMapPermissionsSeeder
 *   php artisan cache:clear   (el store de permisos es de archivo, TTL 24 h)
 */
class RiskMapPermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $guard = 'web';

        Permission::firstOrCreate(['name' => 'riskmap.issue', 'guard_name' => $guard]);

        $matrix = [
            'safety-officer' => ['riskmap.issue'],
            'super-admin'    => ['riskmap.issue'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                $this->command->warn("Rol '{$roleName}' no existe; se omite (solo toca roles presentes).");
                continue;
            }
            $role->givePermissionTo($grants);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command->info('riskmap.issue creado y asignado (safety-officer, super-admin).');
    }
}
