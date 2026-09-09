<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePermitsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `permits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permit_key` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `family` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `definition` text COLLATE utf8mb4_unicode_ci,
  `covers` json DEFAULT NULL,
  `issued_when` text COLLATE utf8mb4_unicode_ci,
  `issued_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accepted_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signatures` json DEFAULT NULL,
  `validity` text COLLATE utf8mb4_unicode_ci,
  `scope` text COLLATE utf8mb4_unicode_ci,
  `site_scope` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_scope_note` text COLLATE utf8mb4_unicode_ci,
  `reverify_on_move` json DEFAULT NULL,
  `ext_auth_authority` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ext_auth_what` text COLLATE utf8mb4_unicode_ci,
  `ext_auth_mandatory` tinyint(1) DEFAULT NULL,
  `budget` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permits_code` (`code`),
  KEY `permits_site_scope_idx` (`site_scope`),
  KEY `permits_active_idx` (`is_active`),
  KEY `permits_verified_idx` (`verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('permits');
    }
}
