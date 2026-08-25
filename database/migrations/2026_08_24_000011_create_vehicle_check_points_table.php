<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 1 — CATÁLOGO DE PUNTOS de verificación.
 *
 * VOCABULARIO DELIBERADO — la severidad es GRADUADA (`class` critical|major|minor), NO el
 * binario `es_compuerta`/`is_gate` de herramienta y ambulancia (esos emiten veredicto
 * binario; aquí el veredicto es graduado, así que tres clases). Documentado para que NO se
 * lea como divergencia accidental.
 *
 * `applies_when` = expresión sobre atributos del vehículo (p. ej. "powertrain != electric",
 * "seats > 8", "water_tank_liters >= 200", "has_cargo_box", "tows"). NULL/'' = aplica siempre.
 * La evalúa {@see \App\Support\VehicleChecklist} (gramática controlada, sin eval()).
 *
 * `norm_id` NULO y `verified_at` NULL en todos: SALEN DE OFICIO, no de una norma. No se
 * inventan normas ni URLs. `requires_photo` obliga foto por punto (fail-safe de captura).
 */
class CreateVehicleCheckPointsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `vehicle_check_points` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grupo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nucleo',
  `text_es` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_en` text COLLATE utf8mb4_unicode_ci,
  `class` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'minor',
  `requires_photo` tinyint(1) NOT NULL DEFAULT '0',
  `applies_when` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `norm_id` bigint(20) unsigned DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicle_points_code` (`code`),
  KEY `vehicle_points_module_idx` (`module`),
  KEY `vehicle_points_class_idx` (`class`),
  KEY `vehicle_points_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('vehicle_check_points');
    }
}
