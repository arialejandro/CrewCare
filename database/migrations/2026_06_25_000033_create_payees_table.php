<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BASE ÚNICA DE QUIEN COBRA — NIVEL 1 · IDENTIDAD (persona FÍSICA o MORAL).
 * Datos fiscales DE LA PERSONA + liga OPCIONAL a users (si quien cobra es crew).
 * Espejo de owner-apply/2026-08-13-payee-base.sql.
 */
class CreatePayeesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `payees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legal_nature` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `rfc` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_residence_country` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_branch` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_clabe` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payees_nature_idx` (`legal_nature`),
  KEY `payees_user_idx` (`user_id`),
  KEY `payees_active_idx` (`is_active`),
  KEY `payees_sort_idx` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('payees');
    }
}
