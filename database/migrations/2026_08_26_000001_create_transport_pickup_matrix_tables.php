<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Pick up derivado — FASE 1: PUNTOS DE PICKUP + MATRIZ DE TRASLADO.
 *
 * Puntos de pickup: catálogo de ORÍGENES estables (Churubusco, Oficina de Producción, Condesa),
 * capturados una vez CON coordenadas. Matriz: el tiempo es del PAR origen→destino (la locación del
 * día, que hereda lat/lng del `scouting_reports`). `CrewGeo.driveEta` (OSRM en el navegador) PROPONE;
 * transpo CORRIGE; lo corregido PERSISTE por par (unique). 100% aditivo: no toca la corrida ni el
 * pick up literal existentes (eso se rehace en la Fase 2). Referencias BLANDAS por id numérico
 * (scouting_id → scouting_reports.id): sin unión por texto → sin problema de collation con la tabla
 * vieja (scouting_reports es latin1).
 */
class CreateTransportPickupMatrixTables extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `transport_pickup_points` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transport_pickup_points_prod_idx` (`production_id`),
  KEY `transport_pickup_points_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE `transport_travel_times` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `pickup_point_id` bigint(20) unsigned NOT NULL,
  `scouting_id` bigint(20) unsigned NOT NULL,
  `minutes` int(11) NOT NULL,
  `source` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proposed',
  `corrected_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transport_travel_pair_unique` (`pickup_point_id`,`scouting_id`),
  KEY `transport_travel_prod_idx` (`production_id`),
  KEY `transport_travel_scouting_idx` (`scouting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('transport_travel_times');
        Schema::dropIfExists('transport_pickup_points');
    }
}
