-- ============================================================================
-- CrewCare — EL CONTRATO · PASO A: FECHAS DE TRABAJO (tabla hija de payee_contracts).
-- (2026-08-13) — delta. Cada renglón = una FECHA + su FASE (soft_prep|prep|shoot|wrap).
--   Fechas NO CONTIGUAS (un day player trabaja los días 1, 37, 123). La fase importa
--   porque la tarifa de viáticos cambia. Diseñada para que agregar `unit_id` después sea
--   aditivo. FK-soft. Contrato activo + fecha aquí = LLAMADO; activo sin fecha = NO LLAMADO.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (CREATE TABLE IF NOT EXISTS):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-contract-work-dates.sql
-- Depende de: payee_contracts (referencia su id). SIN permiso/seeder/lang.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payee_contract_work_dates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payee_contract_id` bigint(20) unsigned NOT NULL,
  `work_date` date NOT NULL,
  `phase` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shoot',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pcwd_contract_date_uq` (`payee_contract_id`, `work_date`),
  KEY `pcwd_contract_idx` (`payee_contract_id`),
  KEY `pcwd_date_idx` (`work_date`),
  KEY `pcwd_phase_idx` (`phase`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
