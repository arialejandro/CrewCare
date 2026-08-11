<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateIssuedPermitsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `issued_permits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `shoot_day` int(11) DEFAULT NULL,
  `permit_id` bigint(20) unsigned DEFAULT NULL,
  `permit_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permit_key` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permit_family` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permit_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permit_definition` text COLLATE utf8mb4_unicode_ci,
  `permit_site_scope` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points_snapshot` json DEFAULT NULL,
  `standards_snapshot` json DEFAULT NULL,
  `photos` json DEFAULT NULL,
  `activity_description` text COLLATE utf8mb4_unicode_ci,
  `site_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tool_id` bigint(20) unsigned DEFAULT NULL,
  `tool_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tool_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ext_auth_mandatory` tinyint(1) NOT NULL DEFAULT '0',
  `ext_auth_authority` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ext_auth_folio` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ext_auth_valid_until` date DEFAULT NULL,
  `ext_auth_declared_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ext_auth_note` text COLLATE utf8mb4_unicode_ci,
  `issuer_user_id` bigint(20) unsigned DEFAULT NULL,
  `issuer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issuer_role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issuer_cedula` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acceptor_user_id` bigint(20) unsigned DEFAULT NULL,
  `acceptor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acceptor_role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acceptor_id_label` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acceptor_id_value` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `requires_fire_watch` tinyint(1) NOT NULL DEFAULT '0',
  `fire_watch_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `reverifications` json DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by_id` bigint(20) unsigned DEFAULT NULL,
  `closed_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `close_notes` text COLLATE utf8mb4_unicode_ci,
  `suspended_at` datetime DEFAULT NULL,
  `suspended_by_id` bigint(20) unsigned DEFAULT NULL,
  `suspended_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `superseded_by_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `issued_permits_uuid_unique` (`uuid`),
  KEY `issued_permits_permit_idx` (`permit_id`),
  KEY `issued_permits_code_day_idx` (`permit_code`,`shoot_day`,`is_active`),
  KEY `issued_permits_production_idx` (`production_id`),
  KEY `issued_permits_tool_idx` (`tool_id`),
  KEY `issued_permits_closed_idx` (`closed_at`),
  KEY `issued_permits_suspended_idx` (`suspended_at`),
  KEY `issued_permits_superseded_idx` (`superseded_by_id`),
  KEY `issued_permits_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('issued_permits');
    }
}
