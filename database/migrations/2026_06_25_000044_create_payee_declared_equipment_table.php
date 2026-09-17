<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QUIEN COBRA · PASO 3 — equipo declarado para seguro. DECLARACIÓN con consecuencia
 * económica (lo no declarado no se cubre) → se firma con la capa simple (acceptor_*
 * congelado + accepted_at; el sello SHA vive en digital_signatures vía HasDigitalSignatures).
 * NO es el equipo RENTADO (eso va como contrato). Espejo de owner-apply.
 */
class CreatePayeeDeclaredEquipmentTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `payee_declared_equipment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payee_id` bigint(20) unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_holder` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declared_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `acceptor_user_id` bigint(20) unsigned DEFAULT NULL,
  `acceptor_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acceptor_role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payee_declared_equipment_payee_idx` (`payee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('payee_declared_equipment');
    }
}
