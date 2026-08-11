<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateHealthRecordAddendumsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `health_record_addendums` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formulario_id` bigint(20) unsigned NOT NULL,
  `reason` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'correccion',
  `changed_fields` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `medic_cedula` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medic_cedula_verified` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `health_record_addendums_uuid_unique` (`uuid`),
  KEY `health_record_addendums_formulario_idx` (`formulario_id`),
  KEY `health_record_addendums_medic_idx` (`created_by_id`),
  CONSTRAINT `health_record_addendums_formulario_fk` FOREIGN KEY (`formulario_id`) REFERENCES `formularios` (`id_formulario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('health_record_addendums');
    }
}
