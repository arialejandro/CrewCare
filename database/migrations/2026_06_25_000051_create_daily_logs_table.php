<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateDailyLogsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `daily_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `daily_report_id` bigint(20) unsigned NOT NULL,
  `log_time` time NOT NULL,
  `description` text NOT NULL,
  `action_taken` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `required_ppe` json DEFAULT NULL,
  `regulation_badge` varchar(20) DEFAULT 'NA',
  `regulation_code` varchar(100) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `hazard_event_id` bigint(20) unsigned DEFAULT NULL,
  `sourceable_id` bigint(20) unsigned DEFAULT NULL,
  `sourceable_type` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `daily_report_id` (`daily_report_id`),
  KEY `daily_logs_event_idx` (`hazard_event_id`),
  KEY `daily_logs_sourceable_idx` (`sourceable_type`,`sourceable_id`),
  CONSTRAINT `daily_logs_ibfk_1` FOREIGN KEY (`daily_report_id`) REFERENCES `daily_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('daily_logs');
    }
}
