<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO C — EL SOBRE. Se firma un PAQUETE, no un documento: agrupa la CARÁTULA
 * generada, el CLAUSULADO byte-intact y los ANEXOS. Un sobre pertenece a UN contrato.
 *
 *  - `documents` = SNAPSHOT JSON del paquete al crear el sobre (kind/name/path/hash, byte-intact).
 *  - `status`: draft | sent | completed | cancelled.
 *  - `current_recipient_id` = a quién le toca firmar (la ruta avanza sola).
 *  - Se SELLA con HasDigitalSignatures (integridad del paquete congelado). El sello es distinto
 *    del ACTO DE ACEPTACIÓN de cada persona (contract_envelope_recipients): uno prueba que el
 *    documento no cambió, el otro quién aceptó qué. No se fusionan.
 *  - Un sobre COMPLETADO no se edita ni se reabre.
 *
 * FK-soft. Idempotente (CREATE TABLE IF NOT EXISTS).
 */
class CreateContractEnvelopesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `contract_envelopes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payee_contract_id` bigint(20) unsigned NOT NULL,
  `production_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `documents` json DEFAULT NULL,
  `current_recipient_id` bigint(20) unsigned DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_envelopes_contract_idx` (`payee_contract_id`),
  KEY `contract_envelopes_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('contract_envelopes');
    }
}
