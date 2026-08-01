<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * EpiPermissionsSeeder — reparte el permiso del PANEL DE VIGILANCIA epidemiológica y del
 * estudio de brote (delta #45) en una BD YA VIVA. 2026-07-31.
 *
 * POR QUÉ UN PERMISO NUEVO: el panel lo ven SOLO el SAFETY y el MÉDICO, y no existe ningún
 * permiso que ambos compartan con esa semántica (`dsr.view`/`hazards.view` son solo del safety;
 * el `medic` no los tiene). Prestar un permiso genérico abriría el panel a quien más lo tenga.
 * Un permiso propio `epi.view` acota exacto.
 *
 * ADITIVO E IDEMPOTENTE: givePermissionTo() (NUNCA syncPermissions()). Solo toca los roles que
 * existan. Re-correrlo no cambia nada. ⚠ super-admin pasa por Gate::before; probar con NO super-admin.
 *
 * Correr:  php artisan db:seed --class=EpiPermissionsSeeder
 *          php artisan cache:clear
 */
class EpiPermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        // Un solo permiso cubre ver el panel Y emitir el estudio de brote (el estudio lo emite el
        // médico; el safety ve el panel y conversa la estrategia — ambos con epi.view).
        Permission::firstOrCreate(['name' => 'epi.view', 'guard_name' => $guard]);

        $matrix = [
            'safety-officer' => ['epi.view'],
            'medic'          => ['epi.view'],
            'super-admin'    => ['epi.view'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                if ($this->command) {
                    $this->command->warn("EpiPermissionsSeeder: el rol `{$roleName}` no existe; se omite.");
                }
                continue;
            }
            $role->givePermissionTo($grants);
            if ($this->command) {
                $this->command->info("EpiPermissionsSeeder: {$roleName} ← ".implode(', ', $grants));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
