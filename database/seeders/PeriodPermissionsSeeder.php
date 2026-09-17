<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * VENTANA DE RECEPCIÓN POR PERIODO — permisos del módulo.
 *
 *  - `periods.view`   → VER el tablero de "quién falta". Oficina de producción (line-producer,
 *    coordinator → ven todo por crew.view.all-departments) + HOD (acotado a su depto por el
 *    scope "quien contrata es quien ve"). El super-admin pasa por Gate::before.
 *  - `periods.manage` → ABRIR/CERRAR/REABRIR periodos y ASIGNAR la frecuencia del contrato.
 *    Es la mesa de contabilidad → NO al HOD (que solo consulta su tablero).
 *
 * NO se concede a safety/medic/crew (no llevan la recepción fiscal). El auditor tampoco: es
 * dato fiscal y se sigue el precedente de payees.view. El owner lo enciende/apaga por rol desde
 * /permisoscrud.
 *
 * DEBE correr DESPUÉS de RolesAndPermissionsSeeder: el barrido `%.view` del auditor corre antes
 * → así NO se lleva periods.view por accidente. ADITIVO E IDEMPOTENTE (firstOrCreate + givePermissionTo).
 * A mano: php artisan db:seed --class=PeriodPermissionsSeeder + cache:clear
 */
class PeriodPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $guard = 'web';

        Permission::firstOrCreate(['name' => 'periods.view',   'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => 'periods.manage', 'guard_name' => $guard]);

        $matrix = [
            'line-producer' => ['periods.view', 'periods.manage'],
            'coordinator'   => ['periods.view', 'periods.manage'],
            'hod'           => ['periods.view'],                      // consulta su tablero, no administra
            'super-admin'   => ['periods.view', 'periods.manage'],
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
        $this->command->info('PeriodPermissionsSeeder: periods.view/manage → producción; hod solo view.');
    }
}
