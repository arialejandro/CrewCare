<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 2 (§2) — OCUPANTE de una corrida.
 *
 * OCUPANTE = persona + "carga" (`load_note`, descriptor libre) + puntero a departamento.
 *   - source=crew  → `user_id` (users), autobúsqueda contra base.
 *   - source=cast|agency|client → `party_id` (transport_parties, padrón ligero).
 *   - source=free  → sólo `name_snapshot` (texto libre como escape).
 * `name_snapshot` congela el nombre mostrado. `load_note` NO se llama `load` para no rozar
 * helpers de Eloquent. Referencias BLANDAS.
 */
class CreateTransportRunOccupantsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `transport_run_occupants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transport_order_run_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `source` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'crew',
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `name_snapshot` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `load_note` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transport_occupants_run_idx` (`transport_order_run_id`),
  KEY `transport_occupants_user_idx` (`user_id`),
  KEY `transport_occupants_party_idx` (`party_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('transport_run_occupants');
    }
}
