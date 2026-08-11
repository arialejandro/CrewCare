<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMedicationsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `medications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dosage` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `presentation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `beta_flagged` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medications_variant` (`name`,`dosage`,`presentation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('medications');
    }
}
