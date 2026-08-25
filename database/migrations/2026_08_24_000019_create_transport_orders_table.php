<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 2 (§1) — LA ORDEN (por día).
 *
 * ⚠ NO SE SELLA Y NO SE FIRMA. Se CONGELA: `status`=frozen + `frozen_snapshot` (documento
 * completo al emitir). NO usa HasDigitalSignatures ni entra al verificador público. Identidad
 * = fecha + versión (sin folio/consecutivo) + un UUID DISCRETO en el pie (GeneratesUuidKey).
 * Cada emisión congela una versión y apunta a la INMEDIATA anterior (`prev_version_id`) para
 * resaltar cambios (§4). Referencias BLANDAS.
 */
class CreateTransportOrdersTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `transport_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `order_date` date NOT NULL,
  `version` int(11) NOT NULL DEFAULT '1',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes_general` text COLLATE utf8mb4_unicode_ci,
  `legend` json DEFAULT NULL,
  `prev_version_id` bigint(20) unsigned DEFAULT NULL,
  `frozen_at` datetime DEFAULT NULL,
  `frozen_by_id` bigint(20) unsigned DEFAULT NULL,
  `frozen_snapshot` json DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transport_orders_uuid_unique` (`uuid`),
  KEY `transport_orders_prod_date_ver_idx` (`production_id`,`order_date`,`version`),
  KEY `transport_orders_status_idx` (`status`),
  KEY `transport_orders_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('transport_orders');
    }
}
