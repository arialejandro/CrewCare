<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QUIEN COBRA · PASO 3 — campos que llena la persona en el intake (identidad, domicilio,
 * contacto de emergencia, logística) + marca de intake enviado. NO tipo de sangre ni
 * alergias (dato clínico). Espejo de owner-apply/2026-08-13-payee-intake.sql.
 */
class AddIntakeFieldsToPayees extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
ALTER TABLE `payees`
  ADD COLUMN `nationality` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `name`,
  ADD COLUMN `elector_credential` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `nationality`,
  ADD COLUMN `marital_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `elector_credential`,
  ADD COLUMN `addr_street` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `bank_clabe`,
  ADD COLUMN `addr_ext_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `addr_street`,
  ADD COLUMN `addr_int_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `addr_ext_no`,
  ADD COLUMN `addr_colonia` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `addr_int_no`,
  ADD COLUMN `addr_municipio` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `addr_colonia`,
  ADD COLUMN `addr_cp` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `addr_municipio`,
  ADD COLUMN `addr_city` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `addr_cp`,
  ADD COLUMN `addr_state` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `addr_city`,
  ADD COLUMN `emergency_contact_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `addr_state`,
  ADD COLUMN `emergency_contact_phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `emergency_contact_name`,
  ADD COLUMN `shirt_size` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `emergency_contact_phone`,
  ADD COLUMN `is_vegetarian` tinyint(1) DEFAULT NULL AFTER `shirt_size`,
  ADD COLUMN `is_donor` tinyint(1) DEFAULT NULL AFTER `is_vegetarian`,
  ADD COLUMN `intake_submitted_at` datetime DEFAULT NULL AFTER `is_donor`
SQL);
    }

    public function down()
    {
        Schema::table('payees', function ($t) {
            $t->dropColumn([
                'nationality', 'elector_credential', 'marital_status',
                'addr_street', 'addr_ext_no', 'addr_int_no', 'addr_colonia', 'addr_municipio',
                'addr_cp', 'addr_city', 'addr_state',
                'emergency_contact_name', 'emergency_contact_phone',
                'shirt_size', 'is_vegetarian', 'is_donor', 'intake_submitted_at',
            ]);
        });
    }
}
