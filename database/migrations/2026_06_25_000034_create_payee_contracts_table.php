<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BASE ÚNICA DE QUIEN COBRA — NIVEL 2 · CONTRATO / concepto de cobro. Varios por
 * identidad → así se logra el N:M "quién contrató" (cada contrato lleva su
 * contracted_by_user_id). REPSE aplica al CONTRATO, no a la persona.
 * Espejo de owner-apply/2026-08-13-payee-base.sql.
 */
class CreatePayeeContractsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `payee_contracts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payee_id` bigint(20) unsigned NOT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `concept` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'service',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contracted_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `payment_frequency` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_repse` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payee_contracts_payee_idx` (`payee_id`),
  KEY `payee_contracts_contractor_idx` (`contracted_by_user_id`),
  KEY `payee_contracts_prod_idx` (`production_id`),
  KEY `payee_contracts_active_idx` (`is_active`),
  KEY `payee_contracts_sort_idx` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('payee_contracts');
    }
}
