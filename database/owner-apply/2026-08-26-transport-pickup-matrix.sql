-- =====================================================================
-- OWNER-APPLY · 2026-08-26 · transport-pickup-matrix  (delta #112)
-- Transportación · Pick up derivado — FASE 1: puntos de pickup + matriz de traslado.
-- 2 tablas NUEVAS, aditivo, CREATE TABLE IF NOT EXISTS. Sin dependencias nuevas
-- (scouting_id → scouting_reports.id, referencia BLANDA por id numérico).
-- Todo unicode_ci. NADA se sella.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `transport_pickup_points` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transport_pickup_points_prod_idx` (`production_id`),
  KEY `transport_pickup_points_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transport_travel_times` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `pickup_point_id` bigint(20) unsigned NOT NULL,
  `scouting_id` bigint(20) unsigned NOT NULL,
  `minutes` int(11) NOT NULL,
  `source` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proposed',
  `corrected_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transport_travel_pair_unique` (`pickup_point_id`,`scouting_id`),
  KEY `transport_travel_prod_idx` (`production_id`),
  KEY `transport_travel_scouting_idx` (`scouting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación:
--   SELECT COUNT(*) FROM information_schema.tables
--   WHERE table_schema = DATABASE()
--     AND table_name IN ('transport_pickup_points','transport_travel_times');   -- espera 2
