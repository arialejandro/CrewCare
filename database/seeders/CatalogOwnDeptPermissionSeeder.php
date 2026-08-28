<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ADITIVO (BD ya poblada): siembra el permiso `catalogs.manage.own-department` y lo otorga a
 * `hod` y `coordinator`. En fresh no hace falta invocarlo — RolesAndPermissionsSeeder ya lo declara
 * y lo concede. Idempotente. (F4 del catálogo, 2026-08-27.)
 *
 *   php artisan db:seed --class=CatalogOwnDeptPermissionSeeder --force
 */
class CatalogOwnDeptPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'catalogs.manage.own-department']);

        foreach (['hod', 'coordinator'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info('catalogs.manage.own-department → hod, coordinator.');
    }
}
