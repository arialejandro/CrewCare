<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePermitPointsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `permit_points` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permit_id` bigint(20) unsigned NOT NULL,
  `text_es` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_en` text COLLATE utf8mb4_unicode_ci,
  `executor` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_gate` tinyint(1) NOT NULL DEFAULT '1',
  `site_sensitive` tinyint(1) NOT NULL DEFAULT '0',
  `requires_contact` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permit_points_code` (`code`),
  KEY `permit_points_permit_idx` (`permit_id`),
  KEY `permit_points_site_idx` (`site_sensitive`),
  KEY `permit_points_active_idx` (`is_active`),
  CONSTRAINT `permit_points_permit_fk` FOREIGN KEY (`permit_id`) REFERENCES `permits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('permit_points');
    }
}
