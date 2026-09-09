<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 2 (§2) — PADRÓN LIGERO de ocupantes NO-crew.
 *
 * CAST / AGENCIA / CLIENTE se capturan a mano en una corrida, pero se guardan una vez
 * dentro de la producción para que la SEGUNDA vez ya se sugieran (misma dinámica que
 * LitePatient: directorio re-encontrable, sin cuenta ni login). Referencias BLANDAS.
 */
class CreateTransportPartiesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `transport_parties` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `kind` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cast',
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transport_parties_prod_kind_idx` (`production_id`,`kind`),
  KEY `transport_parties_name_idx` (`name`),
  KEY `transport_parties_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('transport_parties');
    }
}
