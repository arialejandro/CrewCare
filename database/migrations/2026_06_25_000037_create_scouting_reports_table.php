<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateScoutingReportsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `scouting_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `production_name` varchar(255) DEFAULT NULL,
  `production_type` varchar(40) DEFAULT NULL,
  `manager_name` varchar(255) DEFAULT NULL,
  `safety_rep_name` varchar(255) DEFAULT NULL,
  `location_name` varchar(255) NOT NULL,
  `location_address` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `scene` varchar(255) DEFAULT NULL,
  `date_prep` date DEFAULT NULL,
  `date_shoot` date DEFAULT NULL,
  `date_wrap` date DEFAULT NULL,
  `loc_setting` varchar(30) DEFAULT NULL,
  `shoot_time` varchar(30) DEFAULT NULL,
  `complexity` varchar(20) DEFAULT NULL,
  `nearest_hospital` varchar(255) DEFAULT NULL,
  `hospital_address` varchar(500) DEFAULT NULL,
  `hospital_eta` varchar(50) DEFAULT NULL,
  `hospital_distance_km` decimal(6,2) DEFAULT NULL,
  `emergency_access` varchar(500) DEFAULT NULL,
  `assembly_point` varchar(255) DEFAULT NULL,
  `ambulance_company` varchar(255) DEFAULT NULL,
  `has_ambulance` tinyint(1) DEFAULT NULL,
  `emergency_phone` varchar(50) DEFAULT NULL,
  `risk_assessment` longtext,
  `requires_specific_ra` tinyint(1) NOT NULL DEFAULT '0',
  `sb132_details` json DEFAULT NULL,
  `exec_summary` text,
  `viability_checklist` longtext,
  `agreements` longtext,
  `operational_notes` text,
  `main_image_path` varchar(500) DEFAULT NULL,
  `additional_images_paths` longtext,
  `make_by` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `make_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `required_ppe` json DEFAULT NULL,
  `max_headcount` int(11) DEFAULT NULL,
  `emergency_equipment_inventory` json DEFAULT NULL,
  `logistics_facilities` json DEFAULT NULL,
  `uuid` char(36) DEFAULT NULL,
  `hospital_map` longtext COMMENT 'data-URI del mapa de la ruta al hospital (MEDEVAC); persiste entre emisiones',
  PRIMARY KEY (`id`),
  UNIQUE KEY `scouting_reports_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('scouting_reports');
    }
}
