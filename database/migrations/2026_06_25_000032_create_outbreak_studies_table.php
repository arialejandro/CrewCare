<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateOutbreakStudiesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `outbreak_studies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `medic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medic_cedula` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medic_cedula_verified` tinyint(1) DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_key` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `case_definition` text COLLATE utf8mb4_unicode_ci,
  `time_description` text COLLATE utf8mb4_unicode_ci,
  `place_description` text COLLATE utf8mb4_unicode_ci,
  `person_description` text COLLATE utf8mb4_unicode_ci,
  `attack_rate_cases` int(11) DEFAULT NULL,
  `attack_rate_population` int(11) DEFAULT NULL,
  `attack_rate_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hypothesis` text COLLATE utf8mb4_unicode_ci,
  `control_measures` text COLLATE utf8mb4_unicode_ci,
  `counts_snapshot` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outbreak_studies_uuid_unique` (`uuid`),
  KEY `outbreak_studies_production_idx` (`production_id`),
  KEY `outbreak_studies_author_idx` (`created_by_id`),
  KEY `outbreak_studies_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('outbreak_studies');
    }
}
