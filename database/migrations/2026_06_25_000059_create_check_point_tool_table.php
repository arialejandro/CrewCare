<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCheckPointToolTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `check_point_tool` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tool_check_point_id` bigint(20) unsigned NOT NULL,
  `tool_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_check_point_tool` (`tool_check_point_id`,`tool_id`),
  KEY `check_point_tool_cp_idx` (`tool_check_point_id`),
  KEY `check_point_tool_tool_idx` (`tool_id`),
  CONSTRAINT `check_point_tool_cp_fk` FOREIGN KEY (`tool_check_point_id`) REFERENCES `tool_check_points` (`id`) ON DELETE CASCADE,
  CONSTRAINT `check_point_tool_tool_fk` FOREIGN KEY (`tool_id`) REFERENCES `tools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('check_point_tool');
    }
}
