<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateWitnessesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `witnesses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `injury_report_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statement` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `witnesses_injury_report_idx` (`injury_report_id`),
  CONSTRAINT `witnesses_injury_report_id_fk` FOREIGN KEY (`injury_report_id`) REFERENCES `injury_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('witnesses');
    }
}
