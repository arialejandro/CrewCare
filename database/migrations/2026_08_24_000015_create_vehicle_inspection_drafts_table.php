<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 1 (ajuste §1) — BORRADOR del checklist (NO es un acta).
 *
 * Guardado parcial EN SERVIDOR (como el intake): un checklist de 40 puntos con fotos en un patio
 * con mala señal no puede perderse por una interrupción. Retomable desde CUALQUIER dispositivo.
 * Es DEL AUTOR: solo él lo ve y lo retoma (`created_by_id`). Al cerrar se sella el acta y el
 * borrador se BORRA — el acta sigue inmutable.
 *
 * Las fotos se guardan al vuelo (ImageCompressor::store → `point_photos` {code: ruta}); al sellar
 * se reusan esas rutas (no se re-suben). UNIQUE(vehicle_id, created_by_id): un borrador vivo por
 * vehículo y autor.
 */
class CreateVehicleInspectionDraftsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `vehicle_inspection_drafts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint(20) unsigned NOT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `is_reevaluation` tinyint(1) NOT NULL DEFAULT '0',
  `origin_inspection_id` bigint(20) unsigned DEFAULT NULL,
  `answers` json DEFAULT NULL,
  `point_photos` json DEFAULT NULL,
  `unit_photo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `km` int(11) DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_veh_draft_vehicle_author` (`vehicle_id`,`created_by_id`),
  KEY `veh_draft_author_idx` (`created_by_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('vehicle_inspection_drafts');
    }
}
