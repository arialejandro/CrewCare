<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * TransportPermissionsSeeder — permisos del módulo "Transportación / verificación de vehículos".
 *
 * DOS niveles + un PUENTE por departamento (decisión del owner 2026-08-24, HÍBRIDO):
 *   - `transport.manage` = GESTIONAR (levantar checklist, sellar actas, flota, validar documentos).
 *     safety-officer y super-admin (este último además por Gate::before).
 *   - `transport.view`   = VER la VISTA LITE de producción (tarjeta, placas, licencia, conductor).
 *     Se concede a producción/compliance: line-producer, coordinator, auditor.
 *   - El DEPARTAMENTO de Transportación levanta el checklist por PERTENENCIA (pivote
 *     production_user), sin permiso — se resuelve en código ({@see \App\Support\TransportAccess}),
 *     no en esta matriz. Por eso aquí NO se le da manage a un rol "transpo" (no existe tal rol).
 *
 * ADITIVO E IDEMPOTENTE: firstOrCreate + givePermissionTo (nunca syncPermissions).
 * Correr a mano: php artisan db:seed --class=TransportPermissionsSeeder + cache:clear
 */
class TransportPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $guard = 'web';

        Permission::firstOrCreate(['name' => 'transport.manage', 'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => 'transport.view',   'guard_name' => $guard]);

        $matrix = [
            'safety-officer' => ['transport.manage', 'transport.view'],
            'super-admin'    => ['transport.manage', 'transport.view'],
            'line-producer'  => ['transport.view'],   // producción VE lite, no gestiona
            'coordinator'    => ['transport.view'],
            'auditor'        => ['transport.view'],    // compliance: creado después del barrido %.view del base
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
        $this->command->info('TransportPermissionsSeeder: manage → safety/super-admin; view → +producción/auditor. El depto de transpo levanta por pertenencia (TransportAccess).');
    }
}
