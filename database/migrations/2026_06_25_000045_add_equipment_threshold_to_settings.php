<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QUIEN COBRA · PASO 3 — umbral del equipo declarable, por producción (ref $6,000 MXN).
 * CONFIGURACIÓN, no constante en código. Espejo de owner-apply/2026-08-13-payee-intake.sql.
 */
class AddEquipmentThresholdToSettings extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
ALTER TABLE `production_document_settings`
  ADD COLUMN `equipment_threshold` decimal(12,2) NOT NULL DEFAULT '6000.00' AFTER `csf_cut_day`
SQL);
    }

    public function down()
    {
        Schema::table('production_document_settings', function ($t) {
            $t->dropColumn('equipment_threshold');
        });
    }
}
