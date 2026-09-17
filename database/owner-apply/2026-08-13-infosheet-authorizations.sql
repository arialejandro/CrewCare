-- ============================================================================
-- CrewCare — EL INFOSHEET · FASE 3: tabla infosheet_authorizations (autorización del paso 2).
-- (2026-08-13) — delta. Cada fila = un acto de aprobación del trato por un autorizador (Line
--   Producer y/o los puestos del módulo de firma), CONGELADO y SELLADO con su firma autógrafa
--   (signature_image entra al hash del sello). Distinta de la firma del sobre.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (CREATE TABLE IF NOT EXISTS):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-infosheet-authorizations.sql
-- SIN permiso/seeder/lang.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `infosheet_authorizations` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
