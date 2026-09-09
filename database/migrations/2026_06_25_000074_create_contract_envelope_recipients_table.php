<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO C — DESTINATARIOS DEL SOBRE (lo que registra el certificado). Es el ACTO DE
 * ACEPTACIÓN de cada persona (NO el sello de integridad del documento).
 *
 *  - TRES PAPELES: preparer (prepara y valida) · contracted (el contratado) · binder (obliga a la
 *    empresa). El puesto DEFINE la ruta en configuración; el sobre CONGELA a la persona al crearse.
 *  - CONGELADO al crear: `name`/`email`/`cargo`/`empresa` (la gente cambia de correo y de puesto;
 *    el certificado debe decir lo que era cierto entonces) + `user_id`/`payee_id` para resolver.
 *  - CUATRO marcas de tiempo distintas: `sent_at` (enviado), `resent_at` (reenviado), `viewed_at`
 *    (visto), `signed_at` (firmado). + `ip_address` + `sign_method`.
 *  - `sort_order` = orden en la ruta (configurable). `status`: pending | sent | viewed | signed.
 *
 * FK-soft. Idempotente (CREATE TABLE IF NOT EXISTS).
 */
class CreateContractEnvelopeRecipientsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `contract_envelope_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `envelope_id` bigint(20) unsigned NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `payee_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `resent_at` datetime DEFAULT NULL,
  `viewed_at` datetime DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sign_method` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cer_envelope_idx` (`envelope_id`, `sort_order`),
  KEY `cer_status_idx` (`status`),
  KEY `cer_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('contract_envelope_recipients');
    }
}
