<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAmbulanceInspectionPointsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `ambulance_inspection_points` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_es` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `norm_ref` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rama` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'todas',
  `min_level` int(11) DEFAULT NULL,
  `max_level` int(11) DEFAULT NULL,
  `is_gate` tinyint(1) NOT NULL DEFAULT '0',
  `outcome_if_fail` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normativo',
  `exigido_por` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_scopes` json DEFAULT NULL,
  `requires_document` tinyint(1) NOT NULL DEFAULT '0',
  `nota` text COLLATE utf8mb4_unicode_ci,
  `norm_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ambulance_points_code` (`code`),
  KEY `ambulance_points_rama_level_idx` (`rama`,`min_level`),
  KEY `ambulance_points_gate_idx` (`is_gate`),
  KEY `ambulance_points_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('ambulance_inspection_points');
    }
}
