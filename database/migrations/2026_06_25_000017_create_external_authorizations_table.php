<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateExternalAuthorizationsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `external_authorizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `holder_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `holder_id` bigint(20) unsigned NOT NULL,
  `level` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'empresa',
  `document_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `authority` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `folio` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `photo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normativo',
  `exigido_por` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_gate` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'presentado',
  `pending_commit_date` date DEFAULT NULL,
  `standard_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `standard_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validation_method` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `validated_by_id` bigint(20) unsigned DEFAULT NULL,
  `validated_snapshot` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `external_auth_holder_idx` (`holder_type`,`holder_id`),
  KEY `external_auth_validated_idx` (`validated_at`),
  KEY `external_auth_validator_idx` (`validated_by_id`),
  KEY `external_auth_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('external_authorizations');
    }
}
