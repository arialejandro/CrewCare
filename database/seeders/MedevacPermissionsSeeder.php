<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * MedevacPermissionsSeeder — reparte el permiso de EMISIÓN del póster MEDEVAC
 * (delta #46) en una BD YA VIVA. 2026-07-31.
 *
 * POR QUÉ EXISTE: RolesAndPermissionsSeeder tiene guarda anti-sobreescritura, así que
 * sobre una BD viva no repartiría un permiso nuevo. Este seeder standalone lo registra
 * y lo reparte (mismo patrón que inspección/permisos/epi).
 *
 * PERMISO PROPIO, SÓLO EL SAFETY (decisión del owner): el póster MEDEVAC es un documento
 * de seguridad. No se recicla ningún permiso existente y NO se da a line-producer: emitir
 * el protocolo de emergencias de una locación es acto del safety. El verificador PÚBLICO
 * (QR) no usa permiso (ruta sin sesión).
 *
 * ADITIVO E IDEMPOTENTE: givePermissionTo() (NUNCA syncPermissions()). Solo toca roles que
 * existan. Re-correrlo no cambia nada.
 *
 * ⚠ super-admin pasa por Gate::before aunque igual se le da explícito; PROBAR el gate con
 *   un usuario NO super-admin y NO safety (no debe poder emitir ni por URL directa).
 *
 * Correr:  php artisan db:seed --class=MedevacPermissionsSeeder
 *          php artisan cache:clear   (el store de permisos es file, TTL 24h)
 */
class MedevacPermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        Permission::firstOrCreate(['name' => 'medevac.issue', 'guard_name' => $guard]);

        // SÓLO el safety (+ super-admin explícito, además del Gate::before). NADIE más.
        $matrix = [
            'safety-officer' => ['medevac.issue'],
            'super-admin'    => ['medevac.issue'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                if ($this->command) {
                    $this->command->warn("MedevacPermissionsSeeder: el rol `{$roleName}` no existe; se omite.");
                }
                continue;
            }
            $role->givePermissionTo($grants);
            if ($this->command) {
                $this->command->info("MedevacPermissionsSeeder: {$roleName} ← " . implode(', ', $grants));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
