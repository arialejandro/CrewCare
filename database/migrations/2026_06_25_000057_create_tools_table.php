<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateToolsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `tools` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tool_family_id` bigint(20) unsigned DEFAULT NULL,
  `is_wildcard` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `definition` text COLLATE utf8mb4_unicode_ci,
  `context` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quick_id` text COLLATE utf8mb4_unicode_ci,
  `main_risk` text COLLATE utf8mb4_unicode_ci,
  `requires_designated_operator` tinyint(1) NOT NULL DEFAULT '0',
  `is_accessory` tinyint(1) NOT NULL DEFAULT '0',
  `triggers_permit_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aliases` json DEFAULT NULL,
  `departments` json DEFAULT NULL,
  `stages` json DEFAULT NULL,
  `critical_parts` json DEFAULT NULL,
  `failure_modes` json DEFAULT NULL,
  `stop_checks` json DEFAULT NULL,
  `not_executable_checks` json DEFAULT NULL,
  `observation_checks` json DEFAULT NULL,
  `min_ppe` json DEFAULT NULL,
  `budget` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `inspection_regime` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'por_evento',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tools_code` (`code`),
  KEY `tools_family_idx` (`tool_family_id`),
  KEY `tools_active_idx` (`is_active`),
  KEY `tools_verified_idx` (`verified_at`),
  KEY `tools_accessory_idx` (`is_accessory`),
  KEY `tools_regime_idx` (`inspection_regime`),
  CONSTRAINT `tools_family_fk` FOREIGN KEY (`tool_family_id`) REFERENCES `tool_families` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('tools');
    }
}
