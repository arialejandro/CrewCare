<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CATÁLOGO DE CONCEPTOS DE PAGO (CONTABILIDAD) — aparte del calendario a propósito: un PREFIJO es un
 * TIPO DE CONCEPTO (honorario, car allowance, box rental…) y UNA factura puede llevar VARIOS, así que
 * NO cabe como campo de la semana. Vive en su propio catálogo, EDITABLE por producción (cada una usa
 * los suyos). `production_id` NULL = default GLOBAL sembrado (visible a todas); una producción puede
 * agregar/ocultar los suyos. El `code` es el prefijo corto (SEM, CA, BOX…). No valida nada: organiza.
 *
 * FK-soft. Idempotente (CREATE TABLE IF NOT EXISTS).
 */
class CreatePaymentConceptsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_concepts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pc_prod_idx` (`production_id`),
  KEY `pc_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('payment_concepts');
    }
}
