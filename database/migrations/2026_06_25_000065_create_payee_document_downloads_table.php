<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BITÁCORA DE DESCARGAS de documentos fiscales del payee (RFC/CLABE/domicilio de terceros).
 * Espeja la telemetría de la cédula: registra QUIÉN descargó QUÉ documento y CUÁNDO. Es un
 * ledger append-only; NUNCA bloquea el serve (si el insert falla, el documento igual se entrega).
 * FK-soft (documento histórico, sin constraint dura).
 */
class CreatePayeeDocumentDownloadsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `payee_document_downloads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payee_id` bigint(20) unsigned NOT NULL,
  `document_id` bigint(20) unsigned NOT NULL,
  `document_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `downloaded_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payee_dl_payee_idx` (`payee_id`),
  KEY `payee_dl_document_idx` (`document_id`),
  KEY `payee_dl_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('payee_document_downloads');
    }
}
