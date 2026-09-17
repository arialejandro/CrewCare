<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAmbulanceInspectionsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `ambulance_inspections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `shoot_day` int(11) DEFAULT NULL,
  `trigger_scope` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unidad',
  `ambulance_type_id` bigint(20) unsigned DEFAULT NULL,
  `type_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rama` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_level` int(11) DEFAULT NULL,
  `capacity_level` int(11) DEFAULT NULL,
  `provider_id` bigint(20) unsigned DEFAULT NULL,
  `provider_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plates` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `economic_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `location_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_photo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evidence_photos` json DEFAULT NULL,
  `crew_snapshot` json DEFAULT NULL,
  `checklist_snapshot` json DEFAULT NULL,
  `verdict` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolution_path` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `day_risk_level` int(11) DEFAULT NULL,
  `correspondence_ok` tinyint(1) DEFAULT NULL,
  `inspector_user_id` bigint(20) unsigned DEFAULT NULL,
  `inspector_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspector_role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspector_cedula` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unblocked_by_id` bigint(20) unsigned DEFAULT NULL,
  `unblocked_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unblocked_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `retired_at` datetime DEFAULT NULL,
  `retired_by_id` bigint(20) unsigned DEFAULT NULL,
  `retired_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `superseded_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ambulance_inspections_uuid_unique` (`uuid`),
  KEY `amb_insp_type_idx` (`ambulance_type_id`),
  KEY `amb_insp_provider_idx` (`provider_id`),
  KEY `amb_insp_production_idx` (`production_id`,`shoot_day`),
  KEY `amb_insp_verdict_idx` (`verdict`),
  KEY `amb_insp_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('ambulance_inspections');
    }
}
