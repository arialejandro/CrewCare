<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAddendumsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `addendums` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `injury_report_id` bigint(20) unsigned NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'diagnosis_change',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_treatment_level` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_is_recordable` tinyint(1) DEFAULT NULL,
  `new_days_away` int(11) DEFAULT NULL,
  `new_days_restricted` int(11) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addendums_injury_idx` (`injury_report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('addendums');
    }
}
