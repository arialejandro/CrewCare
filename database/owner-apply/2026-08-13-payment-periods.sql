-- ============================================================================
-- CrewCare — VENTANA DE RECEPCIÓN POR PERIODO DE PAGO.
-- (2026-08-13) — delta. La app se abre cada semana por esto: centraliza la
--   recepción de facturas/32-D/CSF que hoy llega por correo. NO valida nada.
--
-- QUÉ ES: tabla `payment_periods`. La UNIDAD es el PERIODO (no el documento):
--   pertenece a una producción, declara frecuencia (weekly|biweekly|day_player) y
--   ventana de recepción (opens_on/closes_on). DAY PLAYER es otro TIPO de periodo:
--   no tiene semana, tiene `worked_on` (el día) y se captura A MANO para un `payee_id`.
--   La ventana ABRE/CIERRA pero NO RECHAZA (el fuera-de-ventana se marca en el delta
--   hermano external-auth-period-link). FK-soft.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (CREATE TABLE IF NOT EXISTS):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-payment-periods.sql
-- Permiso/seeder: PeriodPermissionsSeeder (periods.view + periods.manage). Aparte.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payment_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned NOT NULL,
  `frequency` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opens_on` date NOT NULL,
  `closes_on` date NOT NULL,
  `worked_on` date DEFAULT NULL,
  `payee_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `closed_by_id` bigint(20) unsigned DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopened_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pp_prod_idx` (`production_id`),
  KEY `pp_freq_idx` (`frequency`),
  KEY `pp_status_idx` (`status`),
  KEY `pp_payee_idx` (`payee_id`),
  KEY `pp_window_idx` (`opens_on`, `closes_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
