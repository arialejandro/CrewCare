<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePermitStandardTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `permit_standard` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `permit_id` bigint(20) unsigned NOT NULL,
  `safety_standard_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permit_standard` (`permit_id`,`safety_standard_id`),
  KEY `permit_standard_permit_idx` (`permit_id`),
  KEY `permit_standard_std_idx` (`safety_standard_id`),
  CONSTRAINT `permit_standard_permit_fk` FOREIGN KEY (`permit_id`) REFERENCES `permits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permit_standard_std_fk` FOREIGN KEY (`safety_standard_id`) REFERENCES `safety_standards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('permit_standard');
    }
}
