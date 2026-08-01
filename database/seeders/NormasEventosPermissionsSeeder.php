<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * NormasEventosPermissionsSeeder — reparte los permisos del catálogo de Normas
 * (standards.*) y Eventos posibles (hazardevents.*) en una BD YA VIVA. 2026-07-18.
 *
 * POR QUÉ EXISTE: RolesAndPermissionsSeeder tiene una guarda anti-sobreescritura
 * ("si algún rol ya tiene permisos → return") que protege la matriz personalizada
 * en vivo desde la pantalla "Permisos por rol". Efecto colateral: sobre una BD viva
 * ese seeder REGISTRA los permisos nuevos del catálogo pero NO los REPARTE a los
 * roles. Este seeder cubre justo ese hueco para los 6 permisos de Normas/Eventos.
 *
 * OJO: standards.* y hazardevents.* son permisos NUEVOS del catálogo (Paso 3). NO
 * confundir con `hazards.*` (condiciones inseguras = REPORTES), que quedan intactos.
 *
 * ADITIVO E IDEMPOTENTE: usa givePermissionTo() (NUNCA syncPermissions(), que
 * borraría los grants ya existentes de cada rol). Re-correrlo no cambia nada.
 * Solo toca los roles que EXISTAN; los ausentes se omiten sin tronar.
 *
 * Correr:  php artisan db:seed --class=NormasEventosPermissionsSeeder
 */
class NormasEventosPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Cache de spatie: limpiar ANTES (para ver el estado real) y DESPUÉS (para publicar los grants).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        // ---- 1. Alta de los permisos (por si esta BD nunca corrió el seeder principal) ----
        $permissions = [
            'standards.view', 'standards.create', 'standards.manage',
            'hazardevents.view', 'hazardevents.create', 'hazardevents.manage',
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        // ---- 2. Grants por rol (aditivos) ----
        // view    : autoridad amplia de lectura del catálogo (producción, coordinación, HOD,
        //           seguridad, médico y auditoría lo consultan).
        // create  : quienes capturan normas/eventos en campo (producción y seguridad).
        // manage  : verificación/curaduría del catálogo (solo dirección de producción).
        // super-admin : los 6 (además del Gate::before que le da pase total).
        $matrix = [
            'super-admin'    => ['standards.view', 'standards.create', 'standards.manage', 'hazardevents.view', 'hazardevents.create', 'hazardevents.manage'],
            'line-producer'  => ['standards.view', 'standards.create', 'standards.manage', 'hazardevents.view', 'hazardevents.create', 'hazardevents.manage'],
            'safety-officer' => ['standards.view', 'standards.create', 'hazardevents.view', 'hazardevents.create'],
            'coordinator'    => ['standards.view', 'hazardevents.view'],
            'hod'            => ['standards.view', 'hazardevents.view'],
            'medic'          => ['standards.view', 'hazardevents.view'],
            'auditor'        => ['standards.view', 'hazardevents.view'],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if (!$role) {
                if ($this->command) {
                    $this->command->warn("NormasEventosPermissionsSeeder: el rol `{$roleName}` no existe en esta BD; se omite.");
                }
                continue;
            }

            $role->givePermissionTo($grants); // aditivo: respeta lo que el rol ya tenía
            if ($this->command) {
                $this->command->info("NormasEventosPermissionsSeeder: {$roleName} ← ".implode(', ', $grants));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
