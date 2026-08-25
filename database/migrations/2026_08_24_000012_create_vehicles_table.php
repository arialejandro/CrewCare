<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 1 — ENTIDAD VEHÍCULO (reutilizable dentro de la instancia).
 *
 * Promueve `payee_contracts.asset_ref` (JSON {make,model,plate,year}) a una entidad de
 * primera clase. PUENTE, no renumeración: el contrato liga con `payee_contracts.vehicle_id`.
 *
 * SIN ENTIDADES PARALELAS:
 *   - driver = CREW → `driver_user_id` liga a `users` (FK-soft). No hay tabla de conductores.
 *   - propietario PROVEEDOR → `owner_payee_id` liga a `payees` (ya existe con su paquete
 *     documental). El propietario PARTICULAR es una persona (`owner_user_id`) o un nombre
 *     suelto (`owner_name`), NO un proveedor nuevo. `owner_kind` = provider|person|other.
 *
 * NO ENTRAN aquí: picture cars (permiso `vehiculo_escena` + eventos propios) · ambulancias
 * (módulo propio) · grúas/montacargas/maquinaria (catálogo de herramientas).
 *
 * `attr_values` = perfil del tipo YA RESUELTO y ajustable por unidad (el tipo propone, aquí
 * se confirma). NO se nombra `attributes` (colisiona con la prop interna de Eloquent).
 * Referencias BLANDAS (sin FK dura) a users/payees/vehicle_types.
 */
class CreateVehiclesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `vehicles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_type_id` bigint(20) unsigned DEFAULT NULL,
  `type_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `make` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `color` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vin` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attr_values` json DEFAULT NULL,
  `owner_kind` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_payee_id` bigint(20) unsigned DEFAULT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `owner_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_user_id` bigint(20) unsigned DEFAULT NULL,
  `initial_km` int(11) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicles_type_idx` (`vehicle_type_id`),
  KEY `vehicles_owner_payee_idx` (`owner_payee_id`),
  KEY `vehicles_driver_idx` (`driver_user_id`),
  KEY `vehicles_plate_idx` (`plate`),
  KEY `vehicles_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('vehicles');
    }
}
