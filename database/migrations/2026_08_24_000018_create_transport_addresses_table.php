<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 2 (§3) — DIRECCIONES PRIVADAS (tabla propia).
 *
 * Las direcciones privadas (casas, hoteles, pick ups sensibles) las PRESETEA transpo y NO
 * viven en `call_places` (no toco la tabla del llamado). El PDF imprime `public_label`
 * ('CASA' por defecto); la calle real (`address`) sólo la ve el DRIVER asignado a la corrida
 * y los miembros de transpo en la allowlist (`transport_address_viewers`, señalamiento simple,
 * sin jerarquía). Una corrida apunta a un lugar del llamado (kind=call) O a una de estas
 * direcciones (kind=private). Referencias BLANDAS.
 */
class CreateTransportAddressesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `transport_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_label` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transport_addresses_prod_idx` (`production_id`),
  KEY `transport_addresses_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE `transport_address_viewers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transport_address_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transport_addr_viewer_unique` (`transport_address_id`,`user_id`),
  KEY `transport_addr_viewer_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('transport_address_viewers');
        Schema::dropIfExists('transport_addresses');
    }
}
