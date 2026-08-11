<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateDailyReportsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `daily_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_date` date NOT NULL,
  `shoot_day` int(11) DEFAULT '1',
  `location_name` varchar(255) DEFAULT NULL,
  `slug_setting` varchar(50) DEFAULT 'INT.',
  `slug_time` varchar(50) DEFAULT 'DÍA',
  `call_time` time DEFAULT NULL,
  `weather_condition` varchar(50) DEFAULT 'sunny',
  `weather_min_temp` int(11) DEFAULT NULL,
  `weather_max_temp` int(11) DEFAULT NULL,
  `safety_meeting_time` time DEFAULT NULL,
  `safety_meeting_held` tinyint(1) DEFAULT NULL,
  `safety_meeting_topics` varchar(255) DEFAULT NULL,
  `safety_meeting_photo_path` varchar(255) DEFAULT NULL,
  `nearest_hospital` varchar(255) DEFAULT NULL,
  `ambulance_company` varchar(255) DEFAULT NULL,
  `medic_name` varchar(255) DEFAULT NULL,
  `crew_count` int(11) DEFAULT '0',
  `executive_summary` text,
  `hero_image_path` varchar(255) DEFAULT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `required_ppe` json DEFAULT NULL,
  `humidity` int(11) DEFAULT NULL,
  `wind_speed` decimal(5,2) DEFAULT NULL,
  `heat_index` decimal(5,2) DEFAULT NULL,
  `day_risk_factors` json DEFAULT NULL,
  `uuid` char(36) DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `scouting_report_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_reports_uuid_unique` (`uuid`),
  KEY `daily_reports_production_idx` (`production_id`),
  KEY `daily_reports_scouting_report_id_index` (`scouting_report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('daily_reports');
    }
}
