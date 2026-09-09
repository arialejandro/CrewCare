-- ============================================================================
-- CrewCare — TRANSPORTACIÓN · Fase 3 · LA MARCA "lleva pick up hoy" (delta #115).
-- Gemelo de 2026_08_28_000001_create_transport_pickup_marks_table.
--
-- Singleton por producción+persona, SIN fecha (memoria del único día abierto, como
-- call_person_schedules — que NO se toca). Aditiva. Idempotente. MySQL 5.7/8.0.
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-28-transport-pickup-marks.sql
-- ============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `transport_pickup_marks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `is_marked` tinyint(1) NOT NULL DEFAULT '1',
  `marked_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transport_pickup_marks_unique` (`production_id`,`user_id`),
  KEY `transport_pickup_marks_prod_idx` (`production_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación:  SHOW TABLES LIKE 'transport_pickup_marks';
