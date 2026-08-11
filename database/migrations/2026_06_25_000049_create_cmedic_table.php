<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCmedicTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `cmedic` (
  `id_cmedic` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_user` bigint(20) unsigned DEFAULT NULL,
  `lite_patient_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `consultation_date` date DEFAULT NULL,
  `diagnosis` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `medication` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `medication_items` longtext COLLATE utf8mb4_unicode_ci,
  `observations` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `aditional` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `injury_report_id` bigint(20) unsigned DEFAULT NULL,
  `uuid` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medic_cedula` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medic_cedula_verified` tinyint(1) DEFAULT NULL,
  `management` text COLLATE utf8mb4_unicode_ci,
  `without_record` tinyint(1) DEFAULT NULL,
  `intake_formulario_id` bigint(20) unsigned DEFAULT NULL,
  `intake_addendum_id` bigint(20) unsigned DEFAULT NULL,
  `intake_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seal_version` tinyint(3) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_cmedic`),
  KEY `id_user` (`id_user`),
  KEY `cmedic_injury_idx` (`injury_report_id`),
  KEY `cmedic_uuid_index` (`uuid`),
  KEY `cmedic_lite_patient_idx` (`lite_patient_id`),
  CONSTRAINT `cmedic_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`),
  CONSTRAINT `cmedic_lite_patient_fk` FOREIGN KEY (`lite_patient_id`) REFERENCES `lite_patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('cmedic');
    }
}
