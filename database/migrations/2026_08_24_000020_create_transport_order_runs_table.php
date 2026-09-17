<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 2 (§2) — LA CORRIDA (renglón de la orden).
 *
 * TIPO: normal | aeropuerto | aplicacion. `vehicle_id` obligatorio salvo run_type=aplicacion
 * (único caso con corrida SIN vehículo registrado). vehículo↔driver salen del catálogo del
 * Bloque 1. PICK UP guardado con el MISMO lenguaje del motor de horarios del llamado (OFFSET
 * desde general_call + literal + lugar); la orden es DUEÑA de su pick up y sólo lo compara
 * contra el back del llamado (no lo escribe). El lugar puede ser del llamado (kind=call →
 * call_places) o una dirección privada (kind=private → transport_addresses) o texto libre.
 * `equipment` = JSON de códigos del catálogo (íconos). Referencias BLANDAS.
 */
class CreateTransportOrderRunsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `transport_order_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transport_order_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `run_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `vehicle_id` bigint(20) unsigned DEFAULT NULL,
  `driver_user_id` bigint(20) unsigned DEFAULT NULL,
  `pickup_offset_minutes` int(11) DEFAULT NULL,
  `pickup_literal` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_place_kind` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_place_id` bigint(20) unsigned DEFAULT NULL,
  `pickup_place_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dest_place_kind` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dest_place_id` bigint(20) unsigned DEFAULT NULL,
  `dest_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transport_runs_order_idx` (`transport_order_id`),
  KEY `transport_runs_vehicle_idx` (`vehicle_id`),
  KEY `transport_runs_driver_idx` (`driver_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('transport_order_runs');
    }
}
