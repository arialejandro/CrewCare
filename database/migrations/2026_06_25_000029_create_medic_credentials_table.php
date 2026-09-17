<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMedicCredentialsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `medic_credentials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `cedula` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profession` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialty` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registered_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_source` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_snapshot` json DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medic_credentials_user` (`user_id`),
  UNIQUE KEY `uq_medic_credentials_cedula` (`cedula`),
  KEY `medic_credentials_verified_idx` (`verified_at`),
  KEY `medic_credentials_verified_by_idx` (`verified_by_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('medic_credentials');
    }
}
