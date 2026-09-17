<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO B — BIBLIOTECA DE CLAUSULADOS. La productora sube su clausulado (PDF)
 * y CrewCare NO lo transcribe ni lo convierte a vista: se conserva BYTE-INTACT.
 *
 *  - `name` LIBRE (la productora lo nombra: "Contrato Crew", "Vendor Agreement", ...). No hay
 *    tipos fijos en código.
 *  - `applies_to` = JSON con los SUBTIPOS a los que aplica (crew_work|equipment_rental|service);
 *    puede ser más de uno. Así el contrato ofrece solo los suyos.
 *  - `language` declarado AL SUBIR: es | en | bilingual (doble columna). El contrato lo hereda.
 *  - VERSIONADO: subir uno nuevo NO altera los contratos ya emitidos (ellos guardan el id EXACTO
 *    de la versión que usaron). `root_id` agrupa la familia; `version` incrementa por familia.
 *  - `is_active` = desactivar sin borrar; los contratos que lo usaron siguen apuntando a su fila.
 *  - `file_hash` (sha256) para probar el byte-intact.
 *
 * FK-soft. Idempotente (CREATE TABLE IF NOT EXISTS).
 */
class CreateContractClausesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `contract_clauses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applies_to` json NOT NULL,
  `language` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'es',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `root_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `uploaded_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_clauses_prod_idx` (`production_id`),
  KEY `contract_clauses_root_idx` (`root_id`),
  KEY `contract_clauses_active_idx` (`production_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('contract_clauses');
    }
}
