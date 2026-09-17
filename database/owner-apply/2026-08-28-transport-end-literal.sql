-- ============================================================================
-- CrewCare — TRANSPORTACIÓN · Fase 3 · HORA DE FIN de una corrida (delta #116).
-- Gemelo de 2026_08_28_000002_add_end_literal_to_transport_order_runs.
--
-- `end_literal` VARCHAR(16) NULL (texto libre HH:MM, como pickup_literal). SIRVE A TRES COSAS:
--   1. Hora de FIN del evento de vehículo (run_class='evento').
--   2. Fase 4: qué puede ADELANTARSE cuando se libera un vehículo (cuándo queda libre).
--   3. TURNAROUND del driver: el fin de su corrida es su wrap.
-- Aditiva. Idempotente (information_schema). MySQL 5.7/8.0.
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-28-transport-end-literal.sql
-- ============================================================================
SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `cc_transport_end_literal`;
DELIMITER //
CREATE PROCEDURE `cc_transport_end_literal`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transport_order_runs'
                     AND COLUMN_NAME='end_literal') THEN
        ALTER TABLE `transport_order_runs`
            ADD COLUMN `end_literal` VARCHAR(16) COLLATE utf8mb4_unicode_ci NULL AFTER `pickup_literal`;
    END IF;
END //
DELIMITER ;
CALL `cc_transport_end_literal`();
DROP PROCEDURE IF EXISTS `cc_transport_end_literal`;

-- Verificación:  SHOW COLUMNS FROM `transport_order_runs` LIKE 'end_literal';
