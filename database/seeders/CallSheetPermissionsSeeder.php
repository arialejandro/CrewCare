<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * CallSheetPermissionsSeeder — reparte `callsheet.manage` en una BD YA VIVA (2026-08-23, PARTES D/E/F).
 *
 * POR QUÉ UN PERMISO NUEVO: armar/editar el back del día es una tarea de OFICINA DE PRODUCCIÓN. El
 * gate NO puede ser `crew.view.all-departments` (lo tienen también medic/safety-officer/auditor, que
 * no arman llamados) ni algo que el HOD tenga (el HOD usa el roster de solo-lectura). Se crea uno
 * propio y se da a super-admin/line-producer/coordinator.
 *
 * ADITIVO E IDEMPOTENTE: givePermissionTo() (NUNCA syncPermissions()). Solo toca roles existentes.
 * En fresh install viaja en RolesAndPermissionsSeeder (cadena de fábrica).
 *
 * Correr:  php artisan db:seed --class=CallSheetPermissionsSeeder
 *          php artisan cache:clear
 */
class CallSheetPermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';
        Permission::firstOrCreate(['name' => 'callsheet.manage', 'guard_name' => $guard]);

        $roleNames = ['super-admin', 'line-producer', 'coordinator'];

        foreach ($roleNames as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                if ($this->command) {
                    $this->command->warn("CallSheetPermissionsSeeder: el rol `{$roleName}` no existe; se omite.");
                }
                continue;
            }
            $role->givePermissionTo('callsheet.manage');
            if ($this->command) {
                $this->command->info("CallSheetPermissionsSeeder: {$roleName} ← callsheet.manage");
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
