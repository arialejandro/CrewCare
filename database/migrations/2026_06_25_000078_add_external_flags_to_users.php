<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL INFOSHEET · FASE 1 — la PERSONA no-crew como usuario ÚNICO sin credenciales. UNA sola tabla
 * `users` (no una segunda que divergiría, lección que ya costó tres veces): el no-crew NO recibe
 * contraseña y NO inicia sesión — entra por enlace firmado con `external_access_token`, un hash de
 * UN SOLO USO que no caduca si no se usa (`external_access_used_at` lo marca al consumirse). La
 * bandera `is_external` es de la PERSONA (la empresa proveedora sigue siendo un payee moral; quien
 * firma por ella lleva su bandera). `crewlist_visible` gatea el LISTADO (opcional: algunos
 * proveedores SÍ aparecen), no el acceso.
 *
 * TODO ADITIVO con DEFAULT seguro: las filas existentes quedan is_external=0 / crewlist_visible=1
 * → ningún flujo en uso (alta, listados, login) cambia. El token se asigna SÓLO en servidor.
 */
class AddExternalFlagsToUsers extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            DB::statement("ALTER TABLE `users` ADD COLUMN `is_external` TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (! Schema::hasColumn('users', 'crewlist_visible')) {
            DB::statement("ALTER TABLE `users` ADD COLUMN `crewlist_visible` TINYINT(1) NOT NULL DEFAULT 1");
        }
        if (! Schema::hasColumn('users', 'external_access_token')) {
            DB::statement("ALTER TABLE `users` ADD COLUMN `external_access_token` VARCHAR(64) NULL, ADD UNIQUE KEY `users_external_token_uk` (`external_access_token`)");
        }
        if (! Schema::hasColumn('users', 'external_access_used_at')) {
            DB::statement("ALTER TABLE `users` ADD COLUMN `external_access_used_at` DATETIME NULL");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'external_access_token')) {
            DB::statement("ALTER TABLE `users` DROP INDEX `users_external_token_uk`");
        }
        foreach (['is_external', 'crewlist_visible', 'external_access_token', 'external_access_used_at'] as $name) {
            if (Schema::hasColumn('users', $name)) {
                DB::statement("ALTER TABLE `users` DROP COLUMN `{$name}`");
            }
        }
    }
}
