<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * PaePermissionsSeeder — reparte el permiso de EMISIÓN del PAE (Plan de Atención a
 * Emergencias, 2026-08-06) en una BD YA VIVA. Mismo patrón que MedevacPermissionsSeeder.
 *
 * POR QUÉ EXISTE: RolesAndPermissionsSeeder tiene guarda anti-sobreescritura, así que
 * sobre una BD viva no repartiría un permiso nuevo. Este seeder standalone lo registra
 * y lo reparte (mismo patrón que medevac/inspección/permisos/epi).
 *
 * PERMISO PROPIO, SÓLO EL SAFETY (decisión del owner): el PAE es un documento de
 * seguridad. No se recicla ningún permiso existente y NO se da a line-producer: emitir
 * el plan de emergencias del día es acto del safety. El verificador PÚBLICO (QR) no usa
 * permiso (ruta sin sesión).
 *
 * ADITIVO E IDEMPOTENTE: givePermissionTo() (NUNCA syncPermissions()). Solo toca roles que
 * existan. Re-correrlo no cambia nada.
 *
 * ⚠ super-admin pasa por Gate::before aunque igual se le da explícito; PROBAR el gate con
 *   un usuario NO super-admin y NO safety (no debe poder emitir ni por URL directa).
 *
 * Correr:  php artisan db:seed --class=PaePermissionsSeeder
 *          php artisan cache:clear   (el store de permisos es file, TTL 24h)
 */
class PaePermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        Permission::firstOrCreate(['name' => 'pae.issue', 'guard_name' => $guard]);

        // SÓLO el safety (+ super-admin explícito, además del Gate::before). NADIE más.
        $matrix = [
            'safety-officer' => ['pae.issue'],
            'super-admin'    => ['pae.issue'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                if ($this->command) {
                    $this->command->warn("PaePermissionsSeeder: el rol `{$roleName}` no existe; se omite.");
                }
                continue;
            }
            $role->givePermissionTo($grants);
            if ($this->command) {
                $this->command->info("PaePermissionsSeeder: {$roleName} ← " . implode(', ', $grants));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
