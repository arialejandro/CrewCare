<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePermitToolTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `permit_tool` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `permit_id` bigint(20) unsigned NOT NULL,
  `tool_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permit_tool` (`permit_id`,`tool_id`),
  KEY `permit_tool_permit_idx` (`permit_id`),
  KEY `permit_tool_tool_idx` (`tool_id`),
  CONSTRAINT `permit_tool_permit_fk` FOREIGN KEY (`permit_id`) REFERENCES `permits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permit_tool_tool_fk` FOREIGN KEY (`tool_id`) REFERENCES `tools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('permit_tool');
    }
}
