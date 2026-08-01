<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SdsPermissionsSeeder — reparte los permisos de SDS/consumibles SFX (Pilar 3,
 * flag 'sds_sfx') en una BD YA VIVA. 2026-07-16.
 *
 * POR QUÉ EXISTE: RolesAndPermissionsSeeder tiene una guarda anti-sobreescritura
 * ("si algún rol ya tiene permisos → return") que protege la matriz personalizada
 * en vivo desde la pantalla "Permisos por rol". Efecto colateral: sobre una BD viva
 * ese seeder REGISTRA los permisos nuevos del catálogo pero NO los REPARTE a los
 * roles. Este seeder cubre justo ese hueco para los 3 permisos de SDS.
 *
 * ADITIVO E IDEMPOTENTE: usa givePermissionTo() (NUNCA syncPermissions(), que
 * borraría los grants ya existentes de cada rol). Re-correrlo no cambia nada.
 * Solo toca los roles que EXISTAN; los ausentes se omiten sin tronar.
 *
 * Correr:  php artisan db:seed --class=SdsPermissionsSeeder
 */
class SdsPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Cache de spatie: limpiar ANTES (para ver el estado real) y DESPUÉS (para publicar los grants).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        // ---- 1. Alta de los permisos (por si esta BD nunca corrió el seeder principal) ----
        $permissions = ['sds.view', 'sds.create', 'sds.manage'];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        // ---- 2. Grants por rol (aditivos) ----
        // line-producer  : autoridad verificadora completa (ve, captura y valida).
        // safety-officer : captura fichas en campo; sus altas nacen PENDIENTES de validación.
        // auditor        : solo lectura (además ya lo recogería el `like '%.view'` del seeder principal).
        // super-admin    : los 3 (además del Gate::before que le da pase total).
        $matrix = [
            'line-producer'  => ['sds.view', 'sds.create', 'sds.manage'],
            'safety-officer' => ['sds.view', 'sds.create'],
            'auditor'        => ['sds.view'],
            'super-admin'    => ['sds.view', 'sds.create', 'sds.manage'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (!$role) {
                if ($this->command) {
                    $this->command->warn("SdsPermissionsSeeder: el rol `{$roleName}` no existe en esta BD; se omite.");
                }
                continue;
            }

            $role->givePermissionTo($grants); // aditivo: respeta lo que el rol ya tenía
            if ($this->command) {
                $this->command->info("SdsPermissionsSeeder: {$roleName} ← ".implode(', ', $grants));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
