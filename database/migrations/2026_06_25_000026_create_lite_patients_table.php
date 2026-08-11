<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateLitePatientsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `lite_patients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dob` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `merged_into_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lite_patients_name_idx` (`full_name`),
  KEY `lite_patients_phone_idx` (`phone`),
  KEY `lite_patients_merged_idx` (`merged_into_id`),
  KEY `lite_patients_creator_idx` (`created_by_id`),
  CONSTRAINT `lite_patients_merged_fk` FOREIGN KEY (`merged_into_id`) REFERENCES `lite_patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('lite_patients');
    }
}
