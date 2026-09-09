<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateEffectStandardTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `effect_standard` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sfx_effect_type_id` bigint(20) unsigned NOT NULL,
  `safety_standard_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_effect_standard` (`sfx_effect_type_id`,`safety_standard_id`),
  KEY `effect_standard_effect_idx` (`sfx_effect_type_id`),
  KEY `effect_standard_std_idx` (`safety_standard_id`),
  CONSTRAINT `effect_standard_effect_fk` FOREIGN KEY (`sfx_effect_type_id`) REFERENCES `sfx_effect_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `effect_standard_std_fk` FOREIGN KEY (`safety_standard_id`) REFERENCES `safety_standards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('effect_standard');
    }
}
