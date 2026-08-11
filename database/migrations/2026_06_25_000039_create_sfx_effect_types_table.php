<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSfxEffectTypesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `sfx_effect_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `family` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `definition` text COLLATE utf8mb4_unicode_ci,
  `variants` json DEFAULT NULL,
  `main_risk` text COLLATE utf8mb4_unicode_ci,
  `base_control` text COLLATE utf8mb4_unicode_ci,
  `required_ppe` json DEFAULT NULL,
  `certified_personnel` json DEFAULT NULL,
  `standards_snapshot` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sfx_effect_types_code` (`code`),
  KEY `sfx_effect_types_family_idx` (`family`),
  KEY `sfx_effect_types_active_idx` (`is_active`),
  KEY `sfx_effect_types_verified_idx` (`verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('sfx_effect_types');
    }
}
