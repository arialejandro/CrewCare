<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateStandardablesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `standardables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `safety_standard_id` bigint(20) unsigned NOT NULL,
  `standardable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `standardable_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `standardables_unique` (`safety_standard_id`,`standardable_type`,`standardable_id`),
  KEY `standardables_morph_idx` (`standardable_type`,`standardable_id`),
  KEY `standardables_std_idx` (`safety_standard_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('standardables');
    }
}
