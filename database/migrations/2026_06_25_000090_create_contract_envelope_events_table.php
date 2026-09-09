<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO C — BITÁCORA DE EVENTOS del sobre (append-only). Fase 1 de la alineación a
 * arquitectura tipo DocuSign: el estado del sobre deja de ser SOLO columnas booleanas y pasa a
 * DERIVARSE de una bitácora inmutable — la "única tabla verdaderamente crítica" de un servicio de
 * firma. Una fila por evento; nunca se actualiza ni se borra.
 *
 *  - `occurred_at` = reloj del SERVIDOR (nunca del cliente) + `display_timezone` registra la zona
 *    que se mostró (defensibilidad: "¿a qué hora, en qué huso?").
 *  - `prev_hash`/`hash` = CADENA: cada evento hashea (HMAC-SHA256, misma llave del sello) su
 *    contenido + el hash del anterior → alterar el evento N invalida del N en adelante.
 *  - ADITIVO: convive con las columnas de estado actuales (status/sent_at/...); no las reemplaza aún.
 *
 * FK-soft (sin FK real, como el resto del módulo). Idempotente (CREATE TABLE IF NOT EXISTS).
 */
class CreateContractEnvelopeEventsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `contract_envelope_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `envelope_id` bigint(20) unsigned NOT NULL,
  `recipient_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `actor_label` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occurred_at` datetime NOT NULL,
  `display_timezone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `prev_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cee_envelope_idx` (`envelope_id`, `id`),
  KEY `cee_event_idx` (`event`),
  KEY `cee_recipient_idx` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('contract_envelope_events');
    }
}
