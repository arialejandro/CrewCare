<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSfxEventsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `sfx_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumable_id` bigint(20) unsigned DEFAULT NULL,
  `daily_report_id` bigint(20) unsigned DEFAULT NULL,
  `production_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effect_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `safety_criteria` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `started_by_id` bigint(20) unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sfx_events_status_idx` (`status`),
  KEY `sfx_events_dsr_idx` (`daily_report_id`),
  KEY `sfx_events_consum_idx` (`consumable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('sfx_events');
    }
}
