<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO C — BIBLIOTECA DE ANEXOS. La productora sube sus anexos (NDA, política
 * anti-acoso, anticorrupción, código de conducta, aviso de privacidad, certificate of authorship…)
 * con NOMBRE LIBRE — NO se fijan en código, cada productora usa los suyos. Se conservan BYTE-INTACT
 * igual que el clausulado; se desactivan sin borrar. El sobre los agrupa junto a la carátula y el
 * clausulado y se firman como PAQUETE.
 *
 * FK-soft. Idempotente (CREATE TABLE IF NOT EXISTS).
 */
class CreateContractAnnexesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `contract_annexes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `uploaded_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_annexes_prod_idx` (`production_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('contract_annexes');
    }
}
