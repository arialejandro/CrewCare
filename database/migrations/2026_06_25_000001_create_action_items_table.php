<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateActionItemsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `action_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actionable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actionable_id` bigint(20) unsigned NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` bigint(20) unsigned DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `status` enum('open','in_progress','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `responsible_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsible_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mitigation_image_path` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mitigation_note` text COLLATE utf8mb4_unicode_ci,
  `mitigation_uploaded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `action_items_morph_idx` (`actionable_type`,`actionable_id`),
  KEY `action_items_status_idx` (`status`),
  KEY `action_items_owner_idx` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('action_items');
    }
}
