<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUnsafecondsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `unsafeconds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `production_name` varchar(255) DEFAULT NULL,
  `name_loc` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `gps_address` varchar(500) DEFAULT NULL,
  `date_observed` date DEFAULT NULL,
  `time_observed` time DEFAULT NULL,
  `unsafe_act_notify` tinyint(4) DEFAULT NULL,
  `date_notify_unsafe_act` date DEFAULT NULL,
  `location_unsafe_cond` varchar(255) DEFAULT NULL,
  `involved_department` varchar(255) DEFAULT NULL,
  `description_unsafe_cond` text,
  `action_taken` text,
  `corrective_action` text,
  `main_image_path` varchar(255) DEFAULT NULL,
  `additional_images_paths` text,
  `make_by` varchar(255) DEFAULT NULL,
  `make_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `regulation_badge` varchar(20) DEFAULT NULL,
  `regulation_code` varchar(50) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `risk_level` varchar(20) DEFAULT NULL,
  `likelihood` char(1) DEFAULT NULL,
  `consequence` tinyint(4) DEFAULT NULL,
  `action_status` varchar(20) DEFAULT NULL,
  `manual_location_justification` text,
  `uuid` char(36) DEFAULT NULL,
  `hazard_event_id` bigint(20) unsigned DEFAULT NULL,
  `override_risk_level` varchar(20) DEFAULT NULL,
  `pending_compliance` tinyint(1) NOT NULL DEFAULT '0',
  `scouting_report_id` bigint(20) unsigned DEFAULT NULL,
  `involved_user_id` bigint(20) unsigned DEFAULT NULL,
  `related_hazard_id` bigint(20) unsigned DEFAULT NULL,
  `is_recurrent` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unsafeconds_uuid_unique` (`uuid`),
  KEY `unsafeconds_event_idx` (`hazard_event_id`),
  KEY `unsafeconds_scouting_report_id_index` (`scouting_report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('unsafeconds');
    }
}
