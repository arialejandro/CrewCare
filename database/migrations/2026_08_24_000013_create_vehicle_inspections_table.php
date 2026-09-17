<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 1 — ACTA sellada de verificación de vehículo.
 *
 * Hermana de {@see ambulance_inspections}/{@see tool_inspections}: congela lo que citó
 * (snapshots), veredicto DERIVADO del dato (fail-safe: punto aplicable sin contestar nunca
 * da favorable), sello HMAC-SHA256 sobre columnas propias, verificador público 'veh'.
 *
 * VEREDICTO GRADUADO (§5): `verdict` = apto | no_apto (cara pública). `level` = alto_riesgo |
 * pobre | normal | bien | excelente (INTERNO: solo transpo y safety lo ven). `n_critical/
 * n_major/n_minor` = conteo congelado que sostiene el nivel.
 *
 * REEVALUACIÓN (§8): `is_reevaluation` + `origin_inspection_id` encadenan la reevaluación al
 * acta que la originó. Superar la reevaluación marca la anterior con `superseded_by_id`.
 *
 * SELLO: TODO es contenido y entra al hash SALVO el ESTADO posterior (is_active + retiro), que
 * va HASH-EXCLUIDO → retirar/sustituir NO invalida el sello.
 */
class CreateVehicleInspectionsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `vehicle_inspections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `shoot_day` int(11) DEFAULT NULL,
  `vehicle_id` bigint(20) unsigned DEFAULT NULL,
  `vehicle_type_id` bigint(20) unsigned DEFAULT NULL,
  `type_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `make` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `color` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vin` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attributes_snapshot` json DEFAULT NULL,
  `owner_kind` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_user_id` bigint(20) unsigned DEFAULT NULL,
  `driver_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `km` int(11) DEFAULT NULL,
  `unit_photo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checklist_snapshot` json DEFAULT NULL,
  `verdict` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `n_critical` int(11) NOT NULL DEFAULT '0',
  `n_major` int(11) NOT NULL DEFAULT '0',
  `n_minor` int(11) NOT NULL DEFAULT '0',
  `observations` text COLLATE utf8mb4_unicode_ci,
  `is_reevaluation` tinyint(1) NOT NULL DEFAULT '0',
  `origin_inspection_id` bigint(20) unsigned DEFAULT NULL,
  `inspector_user_id` bigint(20) unsigned DEFAULT NULL,
  `inspector_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspector_role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspector_cedula` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `retired_at` datetime DEFAULT NULL,
  `retired_by_id` bigint(20) unsigned DEFAULT NULL,
  `retired_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `superseded_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_inspections_uuid_unique` (`uuid`),
  KEY `veh_insp_vehicle_idx` (`vehicle_id`),
  KEY `veh_insp_production_idx` (`production_id`,`shoot_day`),
  KEY `veh_insp_verdict_idx` (`verdict`),
  KEY `veh_insp_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('vehicle_inspections');
    }
}
