-- ============================================================================
-- CrewCare — CONTRACT BUILDER · FASE 1: plantilla de contrato con anclas de firma (DocuSign).
-- (2026-08-14) — delta. El contrato como DOCUMENTO GENERADO (no PDF subido): `{{campos}}` (se llenan
--   con el trato) + `[[firma:...]]` (una ancla por firmante). Al firmar, el ancla se rellena con la
--   autógrafa CONGELADA + su hash. Alternativa al clausulado subido. Espeja contract_clauses.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (CREATE TABLE IF NOT EXISTS):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-14-contract-templates.sql
-- SIN permiso/seeder/lang aparte (el permiso reusa settings.manage).
-- ============================================================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
