<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCatalogPendingStandardsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `catalog_pending_standards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `raw_code` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `jurisdiction` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linkable_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `linkable_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_catalog_pending_standards` (`raw_code`,`linkable_type`,`linkable_id`),
  KEY `catalog_pending_standards_linkable_idx` (`linkable_type`,`linkable_id`),
  KEY `catalog_pending_standards_raw_idx` (`raw_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('catalog_pending_standards');
    }
}
