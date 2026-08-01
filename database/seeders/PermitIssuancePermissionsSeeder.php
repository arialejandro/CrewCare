<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * PermitIssuancePermissionsSeeder — reparte el permiso de la vertical de EMISIÓN de
 * permisos de trabajo (delta #44) en una BD YA VIVA. 2026-07-30.
 *
 * POR QUÉ EXISTE: RolesAndPermissionsSeeder tiene guarda anti-sobreescritura ("si algún
 * rol ya tiene permisos → no reparte"), así que sobre una BD viva registra el permiso
 * nuevo pero NO lo reparte. Este seeder cubre ese hueco (igual que el de inspección).
 *
 * EMITIR UN PERMISO LEGAL ≠ INSPECCIONAR: por eso NO se recicla `tools.inspect`. Un solo
 * permiso `permits.issue` cubre toda la vertical: emitir, reverificar, cerrar y suspender.
 * El verificador PÚBLICO (QR) no usa permiso (ruta sin sesión).
 *
 * ADITIVO E IDEMPOTENTE: givePermissionTo() (NUNCA syncPermissions()). Solo toca los roles
 * que existan. Re-correrlo no cambia nada.
 *
 * ⚠ super-admin pasa por Gate::before aunque no aparezca aquí; PROBAR el gate con un
 *   usuario NO super-admin.
 *
 * Correr:  php artisan db:seed --class=PermitIssuancePermissionsSeeder
 *          php artisan cache:clear   (el store de permisos es file, TTL 24h)
 */
class PermitIssuancePermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        Permission::firstOrCreate(['name' => 'permits.issue', 'guard_name' => $guard]);

        // safety-officer : el actor de campo que emite el permiso y lo cierra.
        // line-producer  : autoridad de producción (supervisa el enforcement).
        // super-admin    : explícito, además del Gate::before.
        $matrix = [
            'safety-officer' => ['permits.issue'],
            'line-producer'  => ['permits.issue'],
            'super-admin'    => ['permits.issue'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                if ($this->command) {
                    $this->command->warn("PermitIssuancePermissionsSeeder: el rol `{$roleName}` no existe; se omite.");
                }
                continue;
            }
            $role->givePermissionTo($grants);
            if ($this->command) {
                $this->command->info("PermitIssuancePermissionsSeeder: {$roleName} ← ".implode(', ', $grants));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
