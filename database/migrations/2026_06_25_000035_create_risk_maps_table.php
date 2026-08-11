<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateRiskMapsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `risk_maps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scouting_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `location_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','sealed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `version` int(10) unsigned NOT NULL DEFAULT '1',
  `pin_scale` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'md',
  `folio` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sealed_at` datetime DEFAULT NULL,
  `seal_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sealed_by` bigint(20) unsigned DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `risk_maps_uuid_unique` (`uuid`),
  KEY `risk_maps_scouting_idx` (`scouting_id`),
  KEY `risk_maps_project_idx` (`project_id`),
  KEY `risk_maps_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('risk_maps');
    }
}
