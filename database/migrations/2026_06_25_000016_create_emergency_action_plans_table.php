<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateEmergencyActionPlansTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `emergency_action_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `shoot_day` int(11) DEFAULT NULL,
  `revision` int(11) NOT NULL DEFAULT '1',
  `supersedes_id` bigint(20) unsigned DEFAULT NULL,
  `root_id` bigint(20) unsigned DEFAULT NULL,
  `plan_date` date DEFAULT NULL,
  `unit_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `issued_by_id` bigint(20) unsigned DEFAULT NULL,
  `issued_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emergency_action_plans_uuid_unique` (`uuid`),
  KEY `emergency_action_plans_production_idx` (`production_id`),
  KEY `emergency_action_plans_active_idx` (`is_active`),
  KEY `eap_supersedes_idx` (`supersedes_id`),
  KEY `eap_root_idx` (`root_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('emergency_action_plans');
    }
}
