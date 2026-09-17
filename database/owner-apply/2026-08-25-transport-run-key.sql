-- ============================================================================
-- CrewCare — TRANSPORTACIÓN (Bloque 2 §4): IDENTIDAD ESTABLE de la corrida.
-- (2026-08-25) — gemelo de add_run_key_to_transport_order_runs.
--
-- QUÉ ES:
--   Columna `transport_order_runs.run_key` (UUID, ALTER idempotente). Se asigna al crear la
--   corrida, PERSISTE al editarla y se COPIA al clonar la orden a la versión siguiente. El diff
--   contra la versión inmediata anterior empareja por `run_key` → una corrida que sólo movió el
--   pick up sale como MODIFICADA, no baja+alta.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7/8.0. Idempotente (information_schema):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-25-transport-run-key.sql
-- Backfill incluido: toda corrida existente estrena run_key (no debería haber en prod).
-- ============================================================================

DROP PROCEDURE IF EXISTS `cc_add_transport_run_key`;
DELIMITER //
CREATE PROCEDURE `cc_add_transport_run_key`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'transport_order_runs'
          AND COLUMN_NAME = 'run_key'
    ) THEN
        ALTER TABLE `transport_order_runs`
            ADD COLUMN `run_key` CHAR(36) COLLATE utf8mb4_unicode_ci NULL AFTER `transport_order_id`,
            ADD KEY `transport_runs_run_key_idx` (`run_key`);
    END IF;
END //
DELIMITER ;
CALL `cc_add_transport_run_key`();
DROP PROCEDURE IF EXISTS `cc_add_transport_run_key`;

UPDATE `transport_order_runs` SET `run_key` = (SELECT UUID()) WHERE `run_key` IS NULL OR `run_key` = '';
