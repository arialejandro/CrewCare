<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateHazardEventStandardTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `hazard_event_standard` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `hazard_event_id` bigint(20) unsigned NOT NULL,
  `safety_standard_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hazard_event_standard_unique` (`hazard_event_id`,`safety_standard_id`),
  KEY `hazard_event_standard_event_idx` (`hazard_event_id`),
  KEY `hazard_event_standard_std_idx` (`safety_standard_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('hazard_event_standard');
    }
}
