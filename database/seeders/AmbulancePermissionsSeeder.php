<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * AmbulancePermissionsSeeder — permisos del módulo "verificación de ambulancias".
 *
 * DOS niveles sobre el MISMO módulo:
 *   - `ambulance.manage` = GESTIONAR (verificar, sellar actas, validar proveedor/documentos).
 *     TODO POR EL SAFETY: safety-officer y super-admin (este último además por Gate::before).
 *   - `ambulance.view`   = VER (hub, actas, proveedores) SIN gestionar. Se concede a la oficina
 *     de PRODUCCIÓN (line-producer, coordinator) y también al safety. Es la puerta de la
 *     VISIBILIDAD del Paso 4: ambulancias es EXCLUSIVO de producción y safety — nunca transpo
 *     (un HOD de transporte no tiene ninguno de los dos → 403 hasta por URL directa).
 *
 * Ambos permisos son "propios y APAGABLES" desde /permisoscrud (la matriz viva por rol): el
 * safety podría no necesitar ver, o el owner podría quitarle a producción. Las rutas de LECTURA
 * aceptan `ambulance.manage|ambulance.view`; las de ESCRITURA siguen exigiendo `ambulance.manage`.
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
        Permission::firstOrCreate(['name' => 'ambulance.view',   'guard_name' => $guard]);

        $matrix = [
            'safety-officer' => ['ambulance.manage', 'ambulance.view'],
            'super-admin'    => ['ambulance.manage', 'ambulance.view'],
            'line-producer'  => ['ambulance.view'],   // producción VE, no gestiona
            'coordinator'    => ['ambulance.view'],
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
        $this->command->info('AmbulancePermissionsSeeder: manage → safety/super-admin; view → +producción.');
    }
}
