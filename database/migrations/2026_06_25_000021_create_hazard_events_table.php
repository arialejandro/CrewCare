<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateHazardEventsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `hazard_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risk_icon` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_es` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_es` varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_en` varchar(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `control_measure_es` text COLLATE utf8mb4_unicode_ci COMMENT 'Medida de control pre-propuesta (ES). NULL = sin medida; el campo llega vacio.',
  `control_measure_en` text COLLATE utf8mb4_unicode_ci COMMENT 'Medida de control pre-propuesta (EN). NULL = sin medida.',
  `default_likelihood` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_consequence` tinyint(4) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `required_ppe` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hazard_events_code_unique` (`code`),
  KEY `hazard_events_context_idx` (`context`),
  KEY `hazard_events_category_idx` (`category`),
  KEY `hazard_events_active_idx` (`is_active`),
  KEY `hazard_events_verified_idx` (`verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('hazard_events');
    }
}
