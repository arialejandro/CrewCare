<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMedevacPostersTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `medevac_posters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `scouting_report_id` bigint(20) unsigned DEFAULT NULL,
  `revision` int(11) NOT NULL DEFAULT '1',
  `location_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `issued_by_id` bigint(20) unsigned DEFAULT NULL,
  `issued_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medevac_posters_uuid_unique` (`uuid`),
  KEY `medevac_posters_scouting_idx` (`scouting_report_id`),
  KEY `medevac_posters_production_idx` (`production_id`),
  KEY `medevac_posters_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('medevac_posters');
    }
}
