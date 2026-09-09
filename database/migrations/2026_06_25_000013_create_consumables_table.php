<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateConsumablesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `consumables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `material_family` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `synonyms` json DEFAULT NULL,
  `cas_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hazards` text COLLATE utf8mb4_unicode_ci,
  `precautions` text COLLATE utf8mb4_unicode_ci,
  `signal_word` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ghs_pictograms` json DEFAULT NULL,
  `un_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sds_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sds_sections` json DEFAULT NULL,
  `sds_level` tinyint(4) DEFAULT NULL,
  `sds_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sds_source_note` text COLLATE utf8mb4_unicode_ci,
  `sds_source_date` date DEFAULT NULL,
  `sds_disclaimer` text COLLATE utf8mb4_unicode_ci,
  `source_verified` tinyint(1) DEFAULT NULL,
  `sds_url_verified` tinyint(1) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_consumables_code` (`code`),
  KEY `consumables_type_idx` (`type`),
  KEY `consumables_active_idx` (`is_active`),
  KEY `consumables_verified_idx` (`verified_at`),
  KEY `consumables_deleted_idx` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('consumables');
    }
}
