<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ContractAuthorPermissionsSeeder — reparte el acceso al Contract Builder en una BD YA VIVA.
 * 2026-08-14.
 *
 * POR QUÉ EXISTE: RolesAndPermissionsSeeder tiene guarda anti-sobreescritura (si algún rol ya tiene
 * permisos → registra permiso/rol nuevos pero NO reparte grants). Este seeder cubre ese hueco (igual
 * que PermitIssuance / Epi / etc.).
 *
 * QUÉ SIEMBRA:
 *  · permiso `contracts.author` (redactar/ensamblar PLANTILLAS de contrato). DISTINTO de
 *    `settings.manage`: el contenido LEGAL es de la PRODUCTORA — CrewCare solo ensambla, numera y
 *    estampa firmas; NO redacta. Ver contract-builder-legal-boundary.
 *  · rol `representante-legal` (figura legal de la productora): FIRMA documentos y CREA/ensambla
 *    contratos. Se le da su set completo aquí porque en BD viva la matriz base no re-reparte.
 *
 * Grants: line-producer + super-admin → contracts.author. representante-legal → su set completo.
 *
 * ADITIVO E IDEMPOTENTE: firstOrCreate + givePermissionTo (NUNCA syncPermissions/syncRoles).
 *
 * ⚠ super-admin pasa por Gate::before aunque no aparezca aquí; PROBAR el gate con un NO super-admin.
 *
 * Correr:  php artisan db:seed --class=ContractAuthorPermissionsSeeder
 *          php artisan permission:cache-reset   (el store de permisos es file, TTL 24h)
 */
class ContractAuthorPermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        Permission::firstOrCreate(['name' => 'contracts.author', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'representante-legal', 'guard_name' => $guard]);

        // Rol → permisos (solo roles/permiso que existan; givePermissionTo es aditivo).
        $matrix = [
            'line-producer'       => ['contracts.author'],
            'super-admin'         => ['contracts.author'],
            'representante-legal' => ['contracts.author', 'documents.view', 'documents.sign', 'profile.update-own'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                if ($this->command) {
                    $this->command->warn("ContractAuthorPermissionsSeeder: el rol `{$roleName}` no existe; se omite.");
                }
                continue;
            }
            // Solo permisos ya registrados (evita PermissionDoesNotExist si el base aún no corrió).
            $existing = Permission::whereIn('name', $grants)->where('guard_name', $guard)->pluck('name')->all();
            $role->givePermissionTo($existing);
            if ($this->command) {
                $this->command->info("ContractAuthorPermissionsSeeder: {$roleName} ← ".implode(', ', $existing));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
