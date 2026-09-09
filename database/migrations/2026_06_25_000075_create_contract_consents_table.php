<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO C — CONSENTIMIENTO ELECTRÓNICO. Va APARTE del acto de firma: es la aceptación,
 * UNA VEZ por persona, de firmar electrónicamente (Cód. Comercio 89/89 Bis, CCF 1811). Se acepta
 * una vez y VALE para sobres posteriores (no se vuelve a pedir).
 *
 *  - Llave por persona: `consenter_type` (user|payee) + `consenter_id` (UNIQUE).
 *  - `identifier` = folio propio del consentimiento; `accepted_at` su propia fecha; + ip.
 *
 * NO se reusa `privacy_consents` (ese es el aviso de privacidad del expediente clínico, otro
 * propósito). FK-soft. Idempotente (CREATE TABLE IF NOT EXISTS).
 */
class CreateContractConsentsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `contract_consents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consenter_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `consenter_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identifier` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_consents_person_uq` (`consenter_type`, `consenter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('contract_consents');
    }
}
