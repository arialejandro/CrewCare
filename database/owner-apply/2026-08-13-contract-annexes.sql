-- ============================================================================
-- CrewCare — EL CONTRATO · PASO C: BIBLIOTECA DE ANEXOS.
-- (2026-08-13) — delta. La productora sube sus anexos (NDA, anti-acoso, código,
--   aviso de privacidad, certificate of authorship…) con NOMBRE LIBRE (no fijos en
--   código), byte-intact, desactivables. El sobre los agrupa con carátula+clausulado.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (CREATE TABLE IF NOT EXISTS):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-contract-annexes.sql
-- Gestión gateada por settings.manage (SIN permiso nuevo).
-- ============================================================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
