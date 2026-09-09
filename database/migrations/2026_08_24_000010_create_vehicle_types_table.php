<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 1 — CATÁLOGO DE TIPOS de vehículo.
 *
 * EL TIPO PROPONE, LOS ATRIBUTOS CONFIRMAN: se elige el tipo y de ahí se derivan los
 * atributos (powertrain, caja, plazas, gas/sanitario, planta/calor, agua, remolque)
 * como condicionales precargados en `attr_profile` (JSON). Siguen siendo AJUSTABLES por
 * unidad (viven ya resueltos en `vehicles.attributes`) — misma cascada de los offsets
 * del llamado. `especial` no trae perfil (is_special=1): todo se declara a mano.
 *
 * EDITABLE por la producción (a diferencia del catálogo de ambulancias, de fondo).
 * `verified_at` NULL: el catálogo sale de oficio, no de una norma auditada.
 */
class CreateVehicleTypesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `vehicle_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_es` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_special` tinyint(1) NOT NULL DEFAULT '0',
  `attr_profile` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicle_types_code` (`code`),
  KEY `vehicle_types_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('vehicle_types');
    }
}
