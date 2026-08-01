<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ToolInspectionPermissionsSeeder — reparte el permiso de la vertical de inspección
 * de herramienta (delta #42) en una BD YA VIVA. 2026-07-26.
 *
 * POR QUÉ EXISTE: RolesAndPermissionsSeeder tiene guarda anti-sobreescritura ("si
 * algún rol ya tiene permisos → no reparte"), así que sobre una BD viva registra el
 * permiso nuevo pero NO lo reparte. Este seeder cubre ese hueco.
 *
 * ADITIVO E IDEMPOTENTE: givePermissionTo() (NUNCA syncPermissions()). Solo toca los
 * roles que existan. Re-correrlo no cambia nada.
 *
 * ⚠ super-admin pasa por Gate::before aunque no aparezca aquí; PROBAR el gate con un
 *   usuario NO super-admin.
 *
 * Correr:  php artisan db:seed --class=ToolInspectionPermissionsSeeder
 *          php artisan cache:clear   (el store de permisos es file, TTL 24h)
 */
class ToolInspectionPermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        // Un solo permiso cubre toda la vertical: buscar, ejecutar, sellar el acta y
        // desbloquear el paro. La lectura del acta interna va tras el mismo permiso;
        // el verificador PÚBLICO (QR) no usa permiso (ruta sin sesión).
        Permission::firstOrCreate(['name' => 'tools.inspect', 'guard_name' => $guard]);

        // safety-officer : el actor de campo que detiene la operación e inspecciona.
        // line-producer  : autoridad de producción (supervisa el enforcement).
        // super-admin    : explícito, además del Gate::before.
        $matrix = [
            'safety-officer' => ['tools.inspect'],
            'line-producer'  => ['tools.inspect'],
            'super-admin'    => ['tools.inspect'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                if ($this->command) {
                    $this->command->warn("ToolInspectionPermissionsSeeder: el rol `{$roleName}` no existe; se omite.");
                }
                continue;
            }
            $role->givePermissionTo($grants);
            if ($this->command) {
                $this->command->info("ToolInspectionPermissionsSeeder: {$roleName} ← ".implode(', ', $grants));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
