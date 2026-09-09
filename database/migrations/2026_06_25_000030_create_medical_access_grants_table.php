<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMedicalAccessGrantsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `medical_access_grants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `permission` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medical.view',
  `granted_by_id` bigint(20) unsigned DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT NULL,
  `revoked_by_id` bigint(20) unsigned DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medical_access_grants_user_idx` (`user_id`),
  KEY `medical_access_grants_active_idx` (`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('medical_access_grants');
    }
}
