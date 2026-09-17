<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QUIEN COBRA · PASO 2 — QUIÉN PIDE QUÉ, por producción y editable. El eje antes/después
 * SE DERIVA del tipo (repse_phase), no se duplica. Contenido: DocumentRequirementSeeder.
 * Espejo de owner-apply/2026-08-13-payee-packages.sql.
 */
class CreateDocumentRequirementsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `document_requirements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned NOT NULL,
  `document_type_id` bigint(20) unsigned NOT NULL,
  `applies_to` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ambas',
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_requirements_prod_type_uq` (`production_id`,`document_type_id`),
  KEY `document_requirements_prod_idx` (`production_id`),
  KEY `document_requirements_type_idx` (`document_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('document_requirements');
    }
}
