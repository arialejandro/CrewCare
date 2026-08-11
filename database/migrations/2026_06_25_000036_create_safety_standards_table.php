<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSafetyStandardsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `safety_standards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(150) NOT NULL,
  `regulation_badge` varchar(20) NOT NULL,
  `regulation_code` varchar(100) NOT NULL,
  `reference_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `category_name_en` varchar(255) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_safety_standards_reg_code` (`regulation_code`),
  KEY `safety_standards_verified_idx` (`verified_at`),
  KEY `safety_standards_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('safety_standards');
    }
}
