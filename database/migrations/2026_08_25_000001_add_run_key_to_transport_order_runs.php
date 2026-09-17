<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 2 (§4) — IDENTIDAD ESTABLE de la corrida.
 *
 * `run_key` (UUID) se asigna al crear la corrida, PERSISTE al editarla y se COPIA al clonar la
 * orden para la versión siguiente. Así el diff contra la versión inmediata anterior empareja por
 * `run_key`: una corrida que sólo movió el pick up sale como MODIFICADA (no baja+alta). Aditiva.
 */
class AddRunKeyToTransportOrderRuns extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('transport_order_runs', 'run_key')) {
            DB::statement("ALTER TABLE `transport_order_runs` ADD COLUMN `run_key` CHAR(36) COLLATE utf8mb4_unicode_ci NULL AFTER `transport_order_id`");
            DB::statement("ALTER TABLE `transport_order_runs` ADD KEY `transport_runs_run_key_idx` (`run_key`)");
        }
        // Backfill: toda corrida existente estrena identidad (no debería haber en prod; sí en dev).
        DB::statement("UPDATE `transport_order_runs` SET `run_key` = (SELECT UUID()) WHERE `run_key` IS NULL OR `run_key` = ''");
    }

    public function down()
    {
        if (Schema::hasColumn('transport_order_runs', 'run_key')) {
            DB::statement("ALTER TABLE `transport_order_runs` DROP COLUMN `run_key`");
        }
    }
}
