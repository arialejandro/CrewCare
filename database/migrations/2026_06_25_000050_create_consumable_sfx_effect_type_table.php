<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateConsumableSfxEffectTypeTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `consumable_sfx_effect_type` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sfx_effect_type_id` bigint(20) unsigned NOT NULL,
  `consumable_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_consumable_sfx_effect_type` (`sfx_effect_type_id`,`consumable_id`),
  KEY `consumable_sfx_effect_type_effect_idx` (`sfx_effect_type_id`),
  KEY `consumable_sfx_effect_type_consumable_idx` (`consumable_id`),
  CONSTRAINT `consumable_sfx_effect_type_consumable_fk` FOREIGN KEY (`consumable_id`) REFERENCES `consumables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consumable_sfx_effect_type_effect_fk` FOREIGN KEY (`sfx_effect_type_id`) REFERENCES `sfx_effect_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('consumable_sfx_effect_type');
    }
}
