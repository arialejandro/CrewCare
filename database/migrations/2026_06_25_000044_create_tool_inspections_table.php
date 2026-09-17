<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateToolInspectionsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `tool_inspections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `shoot_day` int(11) DEFAULT NULL,
  `tool_id` bigint(20) unsigned DEFAULT NULL,
  `tool_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tool_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tool_family_key` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tool_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tool_brand` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tool_serial` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tool_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tool_standards_snapshot` json DEFAULT NULL,
  `checklist_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'safety',
  `checklist_snapshot` json DEFAULT NULL,
  `verdict` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolution_path` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `department_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `owner_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspector_user_id` bigint(20) unsigned DEFAULT NULL,
  `inspector_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspector_role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspector_cedula` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unblocked_by_id` bigint(20) unsigned DEFAULT NULL,
  `unblocked_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unblocked_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `inspection_moment` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_id` bigint(20) unsigned DEFAULT NULL,
  `retired_at` datetime DEFAULT NULL,
  `retired_by_id` bigint(20) unsigned DEFAULT NULL,
  `retired_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `superseded_by_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tool_inspections_uuid_unique` (`uuid`),
  KEY `tool_inspections_tool_idx` (`tool_id`),
  KEY `tool_inspections_production_idx` (`production_id`),
  KEY `tool_inspections_verdict_idx` (`verdict`),
  KEY `tool_inspections_department_idx` (`department_id`),
  KEY `tool_inspections_active_idx` (`is_active`),
  KEY `tool_inspections_origin_idx` (`origin_type`,`origin_id`),
  KEY `tool_inspections_retired_idx` (`retired_at`),
  KEY `tool_inspections_superseded_idx` (`superseded_by_id`),
  KEY `tool_inspections_serial_idx` (`tool_serial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('tool_inspections');
    }
}
