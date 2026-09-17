<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateToolStandardTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `tool_standard` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tool_id` bigint(20) unsigned NOT NULL,
  `safety_standard_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tool_standard` (`tool_id`,`safety_standard_id`),
  KEY `tool_standard_tool_idx` (`tool_id`),
  KEY `tool_standard_std_idx` (`safety_standard_id`),
  CONSTRAINT `tool_standard_std_fk` FOREIGN KEY (`safety_standard_id`) REFERENCES `safety_standards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tool_standard_tool_fk` FOREIGN KEY (`tool_id`) REFERENCES `tools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('tool_standard');
    }
}
