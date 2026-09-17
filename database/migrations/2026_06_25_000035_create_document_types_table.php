<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BASE ÚNICA DE QUIEN COBRA — CATÁLOGO de tipos de documento con CLAVE.
 * `document_type` deja de ser texto libre ("32D" y "Opinión SAT" = el mismo doc).
 * Guarda la forma de vigencia y si exige estado positivo (32-D) como DATO.
 * Contenido: DocumentTypeSeeder (encadenado en DatabaseSeeder).
 * Espejo de owner-apply/2026-08-13-payee-base.sql.
 */
class CreateDocumentTypesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE `document_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `family` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'billing',
  `scope` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'identity',
  `legal_nature` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validity_shape` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validity_days` int(11) DEFAULT NULL,
  `requires_positive_status` tinyint(1) NOT NULL DEFAULT '0',
  `is_repse` tinyint(1) NOT NULL DEFAULT '0',
  `repse_phase` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_types_code_unique` (`code`),
  KEY `document_types_family_scope_idx` (`family`,`scope`),
  KEY `document_types_active_idx` (`is_active`),
  KEY `document_types_sort_idx` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('document_types');
    }
}
