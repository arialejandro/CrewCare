-- ============================================================================
-- CrewCare — TRANSPORTACIÓN · Pick up derivado FASE 2 (delta #113).
-- Gemelo de 2026_08_26_000002_transport_derived_pickup.
--
-- Dos ejes (run_class + run_type ya existente), insumos del derivado, marca discreta, modo de la
-- orden, y config por producción (jefatura / lleva-pick-up-siempre / asignación fija por puesto).
-- Aditivo, NADA se sella. Idempotente (information_schema). MySQL 5.7/8.0.
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-26-transport-derived-pickup.sql
-- ============================================================================
SET NAMES utf8mb4;

-- ── transport_order_runs: columnas nuevas (guardadas una a una) ──────────────
DROP PROCEDURE IF EXISTS `cc_transport_f2_runs`;
DELIMITER //
CREATE PROCEDURE `cc_transport_f2_runs`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_order_runs' AND COLUMN_NAME='run_class') THEN
        ALTER TABLE `transport_order_runs` ADD COLUMN `run_class` VARCHAR(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fuera' AFTER `run_type`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_order_runs' AND COLUMN_NAME='pickup_point_id') THEN
        ALTER TABLE `transport_order_runs` ADD COLUMN `pickup_point_id` BIGINT(20) UNSIGNED NULL AFTER `run_class`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_order_runs' AND COLUMN_NAME='travel_minutes') THEN
        ALTER TABLE `transport_order_runs` ADD COLUMN `travel_minutes` INT(11) NULL AFTER `pickup_offset_minutes`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_order_runs' AND COLUMN_NAME='travel_adjust_minutes') THEN
        ALTER TABLE `transport_order_runs` ADD COLUMN `travel_adjust_minutes` INT(11) NOT NULL DEFAULT 0 AFTER `travel_minutes`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_order_runs' AND COLUMN_NAME='dest_location_ref') THEN
        ALTER TABLE `transport_order_runs` ADD COLUMN `dest_location_ref` BIGINT(20) UNSIGNED NULL AFTER `dest_text`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_order_runs' AND COLUMN_NAME='is_discreet') THEN
        ALTER TABLE `transport_order_runs` ADD COLUMN `is_discreet` TINYINT(1) NOT NULL DEFAULT 0 AFTER `dest_location_ref`;
    END IF;
END //
DELIMITER ;
CALL `cc_transport_f2_runs`();
DROP PROCEDURE IF EXISTS `cc_transport_f2_runs`;

-- ── transport_orders: pickup_mode ───────────────────────────────────────────
DROP PROCEDURE IF EXISTS `cc_transport_f2_orders`;
DELIMITER //
CREATE PROCEDURE `cc_transport_f2_orders`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_orders' AND COLUMN_NAME='pickup_mode') THEN
        ALTER TABLE `transport_orders` ADD COLUMN `pickup_mode` VARCHAR(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'masivo' AFTER `status`;
    END IF;
END //
DELIMITER ;
CALL `cc_transport_f2_orders`();
DROP PROCEDURE IF EXISTS `cc_transport_f2_orders`;

-- ── Config por producción ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transport_position_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `position_id` bigint(20) unsigned NOT NULL,
  `is_leadership` tinyint(1) NOT NULL DEFAULT '0',
  `always_pickup` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transport_poscfg_unique` (`production_id`,`position_id`),
  KEY `transport_poscfg_prod_idx` (`production_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transport_vehicle_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `vehicle_id` bigint(20) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transport_vassign_prod_idx` (`production_id`),
  KEY `transport_vassign_pos_idx` (`position_id`),
  KEY `transport_vassign_user_idx` (`user_id`),
  KEY `transport_vassign_veh_idx` (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación:
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
--     AND ((TABLE_NAME='transport_order_runs' AND COLUMN_NAME IN ('run_class','pickup_point_id','travel_minutes','travel_adjust_minutes','dest_location_ref','is_discreet'))
--       OR (TABLE_NAME='transport_orders' AND COLUMN_NAME='pickup_mode'));   -- espera 7
