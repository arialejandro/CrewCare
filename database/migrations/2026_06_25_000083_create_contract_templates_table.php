<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRACT BUILDER · FASE 1 — plantilla de contrato con ANCLAS de firma (estilo DocuSign).
 *
 * El contrato se vuelve un DOCUMENTO GENERADO (como la carátula), no un PDF subido: se arma una vez
 * por producción con `{{campos}}` (se llenan con el trato) y `[[firma:...]]` (anclas por firmante de
 * la ruta). Al firmar, cada ancla se rellena con la autógrafa CONGELADA de ese firmante + su propio
 * hash (per-firma, no un sello global que se rompa al re-renderizar). Es ALTERNATIVA al clausulado
 * subido (`contract_clauses`): la producción elige uno u otro. Espeja el diseño de clausulados
 * (applies_to por subtipo, versión, is_active). Idempotente (CREATE TABLE IF NOT EXISTS).
 */
class CreateContractTemplatesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `contract_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applies_to` json DEFAULT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci,
  `language` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'es',
  `version` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ctpl_prod_idx` (`production_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('contract_templates');
    }
}
