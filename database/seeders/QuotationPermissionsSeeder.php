<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * QuotationPermissionsSeeder — reparte los permisos del módulo COTIZACIÓN en una BD YA VIVA.
 * 2026-08-17.
 *
 * POR QUÉ EXISTE: RolesAndPermissionsSeeder tiene guarda anti-sobreescritura (si algún rol ya
 * tiene permisos → NO re-reparte), así que sobre una BD viva registra los permisos nuevos pero
 * no los reparte. Este seeder cubre ese hueco (mismo patrón que el de permisos/inspección).
 *
 *   quotations.manage → capturar/versionar/ver cotizaciones (oficina de producción).
 *   quotations.accept → ACEPTAR (la aceptación la hace el Line Producer; sella la hoja).
 *
 * ADITIVO E IDEMPOTENTE: givePermissionTo() (NUNCA syncPermissions()). Solo toca roles que
 * existan. Re-correrlo no cambia nada. super-admin pasa por Gate::before (probar con NO super).
 *
 * Correr:  php artisan db:seed --class=QuotationPermissionsSeeder
 *          php artisan cache:clear
 */
class QuotationPermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';
        foreach (['quotations.manage', 'quotations.accept'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        $matrix = [
            'line-producer' => ['quotations.manage', 'quotations.accept'],
            'coordinator'   => ['quotations.manage'],
            'super-admin'   => ['quotations.manage', 'quotations.accept'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                if ($this->command) {
                    $this->command->warn("QuotationPermissionsSeeder: el rol `{$roleName}` no existe; se omite.");
                }
                continue;
            }
            $role->givePermissionTo($grants);
            if ($this->command) {
                $this->command->info("QuotationPermissionsSeeder: {$roleName} ← ".implode(', ', $grants));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
