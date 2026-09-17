<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAmbulanceTypesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `ambulance_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_es` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rama` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'terrestre',
  `level` int(11) DEFAULT NULL,
  `apendice` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `personal_minimo` text COLLATE utf8mb4_unicode_ci,
  `dimensiones_minimas` text COLLATE utf8mb4_unicode_ci,
  `capacidad` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ambulance_types_code` (`code`),
  KEY `ambulance_types_rama_level_idx` (`rama`,`level`),
  KEY `ambulance_types_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('ambulance_types');
    }
}
