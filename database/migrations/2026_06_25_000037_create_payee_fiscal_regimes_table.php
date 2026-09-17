<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QUIEN COBRA · CIERRE PASO 1 — regímenes fiscales (N por identidad). El régimen lo
 * decide el SAT y en la CSF puede venir más de uno. Espejo de owner-apply/2026-08-13-payee-packages.sql.
 */
class CreatePayeeFiscalRegimesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `payee_fiscal_regimes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payee_id` bigint(20) unsigned NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payee_fiscal_regimes_payee_idx` (`payee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('payee_fiscal_regimes');
    }
}
