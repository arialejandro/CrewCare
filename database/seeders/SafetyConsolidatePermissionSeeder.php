<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SafetyConsolidatePermissionSeeder — reparte `safety.consolidate` en una BD YA VIVA (2026-08-21,
 * auditoría #1 · aislamiento por propiedad de los reportes de seguridad).
 *
 * POR QUÉ UN PERMISO NUEVO: la decisión del owner es aislar a los SAFETY ENTRE SÍ (un safety no ve
 * lo que hace otro safety, igual que los médicos entre sí) en DSR/Injury/Actos/Condiciones. El
 * `ReportVisibility` filtra por `created_by_id` salvo para quien tenga `safety.consolidate`. Ese
 * permiso NO puede ser uno que el safety-officer ya tenga (tiene los `*.manage` Y
 * `crew.view.all-departments`), así que se crea uno propio — espejo de `medical.consolidate`.
 *
 * Lo reciben la SUPERVISIÓN/compliance que sí debe ver el consolidado: line-producer, coordinator
 * y auditor. super-admin pasa por Gate::before (se otorga igual por claridad). El safety-officer
 * NO lo recibe → queda aislado a lo suyo.
 *
 * ADITIVO E IDEMPOTENTE: givePermissionTo() (NUNCA syncPermissions()). Solo toca roles existentes.
 * ⚠ super-admin pasa por Gate::before; probar el aislamiento con un safety-officer real.
 *
 * Correr:  php artisan db:seed --class=SafetyConsolidatePermissionSeeder
 *          php artisan cache:clear
 */
class SafetyConsolidatePermissionSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';
        Permission::firstOrCreate(['name' => 'safety.consolidate', 'guard_name' => $guard]);

        // Consolidación = supervisión de producción + compliance. El safety-officer queda FUERA
        // a propósito (es el sujeto del aislamiento).
        $roleNames = ['super-admin', 'line-producer', 'coordinator', 'auditor'];

        foreach ($roleNames as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                if ($this->command) {
                    $this->command->warn("SafetyConsolidatePermissionSeeder: el rol `{$roleName}` no existe; se omite.");
                }
                continue;
            }
            $role->givePermissionTo('safety.consolidate');
            if ($this->command) {
                $this->command->info("SafetyConsolidatePermissionSeeder: {$roleName} ← safety.consolidate");
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
