<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateInjuryReportsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `injury_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `production_title` varchar(255) DEFAULT NULL,
  `production_dates` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `employer_name` varchar(255) DEFAULT NULL,
  `incident_date` date DEFAULT NULL,
  `reported_date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `call_time` time DEFAULT NULL,
  `hours_worked_prior` decimal(4,2) DEFAULT NULL,
  `incident_location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `gps_address` varchar(500) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `other` varchar(255) DEFAULT NULL,
  `body_part` varchar(255) DEFAULT NULL,
  `injury_type` text,
  `treatment_type` varchar(255) DEFAULT NULL,
  `treatment_by` varchar(255) DEFAULT NULL,
  `hospital` varchar(255) DEFAULT NULL,
  `treatment_level` enum('first_aid','medical_treatment','hospitalization','fatality') DEFAULT NULL,
  `days_away_from_work` int(11) NOT NULL DEFAULT '0',
  `days_restricted_work` int(11) NOT NULL DEFAULT '0',
  `is_recordable` tinyint(1) NOT NULL DEFAULT '0',
  `treatment_comments` text,
  `what_happened` text,
  `what_caused` text,
  `root_cause_analysis` json DEFAULT NULL,
  `ppe_details` json DEFAULT NULL,
  `seriousness` varchar(255) DEFAULT NULL,
  `frequency` varchar(255) DEFAULT NULL,
  `likelihood` char(1) DEFAULT NULL,
  `consequence` tinyint(4) DEFAULT NULL,
  `risk_level` varchar(20) DEFAULT NULL,
  `notified_to_worksafe` tinyint(1) DEFAULT NULL,
  `date_notified` date DEFAULT NULL,
  `notified_by` varchar(255) DEFAULT NULL,
  `notified_comment` text,
  `preventions` text,
  `further_comments` text,
  `main_image_path` varchar(255) DEFAULT NULL,
  `additional_images_paths` text,
  `make_by` varchar(255) DEFAULT NULL,
  `make_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `regulation_badge` varchar(20) DEFAULT NULL,
  `regulation_code` varchar(50) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `authority_notifications` json DEFAULT NULL,
  `manual_location_justification` text,
  `uuid` char(36) DEFAULT NULL,
  `hazard_event_id` bigint(20) unsigned DEFAULT NULL,
  `override_risk_level` varchar(20) DEFAULT NULL,
  `pending_compliance` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `injury_reports_uuid_unique` (`uuid`),
  KEY `injury_reports_event_idx` (`hazard_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('injury_reports');
    }
}
