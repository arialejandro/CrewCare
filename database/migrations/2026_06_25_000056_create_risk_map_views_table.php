<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateRiskMapViewsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `risk_map_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `risk_map_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `view_type` enum('satelital','aerea','fachada_calle','acceso_circulacion','set','basecamp','detalle','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'otro',
  `label` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_original_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_enhanced_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_source` enum('scouting_photo','upload','satelital','dron') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upload',
  `narrative_what` varchar(280) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `narrative_decision` varchar(280) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `narrative_action` varchar(280) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risk_map_views_map_order_idx` (`risk_map_id`,`sort_order`),
  CONSTRAINT `risk_map_views_map_fk` FOREIGN KEY (`risk_map_id`) REFERENCES `risk_maps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('risk_map_views');
    }
}
