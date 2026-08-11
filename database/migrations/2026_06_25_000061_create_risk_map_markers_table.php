<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateRiskMapMarkersTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `risk_map_markers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `view_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `kind` enum('resource','hazard','area') COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_type` enum('extintor','salida_emergencia','botiquin','punto_alarma','manguera_hidrante','tablero_electrico','punto_reunion','acceso_ambulancia') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `x_pct` decimal(6,3) NOT NULL,
  `y_pct` decimal(6,3) NOT NULL,
  `label_side` enum('left','right') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'right',
  `label_x_pct` decimal(6,3) DEFAULT NULL,
  `label_y_pct` decimal(6,3) DEFAULT NULL,
  `reference_text` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `polygon` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risk_map_markers_view_order_idx` (`view_id`,`sort_order`),
  KEY `risk_map_markers_event_idx` (`event_id`),
  CONSTRAINT `risk_map_markers_view_fk` FOREIGN KEY (`view_id`) REFERENCES `risk_map_views` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('risk_map_markers');
    }
}
