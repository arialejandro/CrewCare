<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateIndicatorTermsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `indicator_terms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `match_kind` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `term` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_term` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_clinician_verified` tinyint(1) NOT NULL DEFAULT '0',
  `source_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `indicator_terms_group_idx` (`group_key`),
  KEY `indicator_terms_kind_idx` (`match_kind`),
  KEY `indicator_terms_term_idx` (`term`),
  KEY `indicator_terms_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('indicator_terms');
    }
}
