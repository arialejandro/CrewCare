<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL INFOSHEET · FASE 3 — AUTORIZACIONES (paso 2). Cada fila es un acto de aprobación del trato por
 * un autorizador (Line Producer y/o los puestos que configure el módulo de firma), CONGELADO y
 * SELLADO con su firma autógrafa (`signature_image` entra al hash del sello → verificable e íntegra).
 * Distinta de la firma del sobre (esa formaliza los documentos). Al completarse todas, se DISPARA
 * la generación del contrato.
 */
class CreateInfosheetAuthorizationsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('infosheet_authorizations')) {
            return;
        }
        DB::statement(<<<'SQL'
CREATE TABLE `infosheet_authorizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payee_contract_id` bigint(20) unsigned NOT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `role` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_image` mediumtext COLLATE utf8mb4_unicode_ci,
  `accepted_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `infosheet_auth_contract_idx` (`payee_contract_id`),
  KEY `infosheet_auth_pos_idx` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('infosheet_authorizations');
    }
}
