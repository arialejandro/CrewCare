<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAmbulanceDayResourcesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `ambulance_day_resources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `shoot_day` int(11) DEFAULT NULL,
  `resource_date` date DEFAULT NULL,
  `state` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `provider_id` bigint(20) unsigned DEFAULT NULL,
  `ambulance_inspection_id` bigint(20) unsigned DEFAULT NULL,
  `transport_means` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `call_service` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `call_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_time` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `declared_by_id` bigint(20) unsigned DEFAULT NULL,
  `declared_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declared_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amb_day_prod_day` (`production_id`,`shoot_day`),
  KEY `amb_day_state_idx` (`state`),
  KEY `amb_day_provider_idx` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('ambulance_day_resources');
    }
}
