<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCheckPointStandardTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `check_point_standard` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tool_check_point_id` bigint(20) unsigned NOT NULL,
  `safety_standard_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_check_point_standard` (`tool_check_point_id`,`safety_standard_id`),
  KEY `check_point_standard_cp_idx` (`tool_check_point_id`),
  KEY `check_point_standard_std_idx` (`safety_standard_id`),
  CONSTRAINT `check_point_standard_cp_fk` FOREIGN KEY (`tool_check_point_id`) REFERENCES `tool_check_points` (`id`) ON DELETE CASCADE,
  CONSTRAINT `check_point_standard_std_fk` FOREIGN KEY (`safety_standard_id`) REFERENCES `safety_standards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('check_point_standard');
    }
}
