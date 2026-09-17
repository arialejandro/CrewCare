-- ============================================================================
-- CrewCare — EL CONTRATO · PASO B: BIBLIOTECA DE CLAUSULADOS.
-- (2026-08-13) — delta. La productora sube su clausulado (PDF) y se conserva
--   BYTE-INTACT (no se transcribe/convierte). `name` LIBRE, `applies_to` JSON de
--   subtipos, `language` (es|en|bilingual), VERSIONADO por `root_id`/`version`
--   (subir uno nuevo no altera los ya emitidos), `is_active` para desactivar sin
--   borrar, `file_hash` (sha256). FK-soft.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (CREATE TABLE IF NOT EXISTS):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-contract-clauses.sql
-- SIN permiso/seeder nuevos (gestión gateada por settings.manage).
-- ============================================================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
