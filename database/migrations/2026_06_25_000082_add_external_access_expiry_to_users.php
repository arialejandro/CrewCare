<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL INFOSHEET · FASE 4 (endurecimiento) — CADUCIDAD del token de acceso externo. El hash de UN SOLO
 * USO ya muere al consumirse, pero uno minteado y NUNCA usado seguía válido para siempre: si el
 * enlace se reenvía o queda en un correo compartido, es una puerta abierta sin fecha. Esta columna le
 * pone vencimiento POR TIEMPO (7 días, ver ExternalParty::ACCESS_TTL_DAYS). Un token vencido responde
 * IGUAL que el ya usado (410, sin revelar de quién era). NULLABLE/ADITIVO: NULL = sin caducidad.
 */
class AddExternalAccessExpiryToUsers extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'external_access_expires_at')) {
            DB::statement("ALTER TABLE `users` ADD COLUMN `external_access_expires_at` DATETIME NULL AFTER `external_access_used_at`");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'external_access_expires_at')) {
            DB::statement("ALTER TABLE `users` DROP COLUMN `external_access_expires_at`");
        }
    }
}
