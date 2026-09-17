-- ============================================================================
-- CrewCare — PARTE A: CALENDARIO DE RODAJE — columnas de duración planeada (2026-08-23).
-- Bloque owner "CALENDARIO DE RODAJE, MOTOR DE HORARIOS Y BACK". OWNER-APPLY, idempotente.
--
-- QUÉ HACE: agrega a `productions` dos columnas NULLABLE:
--   · shoot_weeks           INT      — duración del rodaje en SEMANAS.
--   · shoot_days_per_week   TINYINT  — 5 o 6 (días de rodaje por semana).
-- De start_date + shoot_weeks × shoot_days_per_week se derivan el TOTAL de días de rodaje
-- (la M de "Día N de M") y el wrap estimado. `end_date` (ya existente) sigue siendo el wrap
-- AJUSTABLE. `productions` NO se sella → no toca ningún hash.
--
-- IDEMPOTENTE: chequea INFORMATION_SCHEMA antes de cada ADD COLUMN (re-ejecutar es no-op).
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), en un cliente MySQL:
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-23-production-shoot-schedule.sql
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_prod_shoot_schedule_2026_08_23;

DELIMITER //
CREATE PROCEDURE crewcare_prod_shoot_schedule_2026_08_23()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productions' AND COLUMN_NAME = 'shoot_weeks'
    ) THEN
        ALTER TABLE `productions` ADD COLUMN `shoot_weeks` INT NULL AFTER `end_date`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productions' AND COLUMN_NAME = 'shoot_days_per_week'
    ) THEN
        ALTER TABLE `productions` ADD COLUMN `shoot_days_per_week` TINYINT NULL AFTER `shoot_weeks`;
    END IF;
END //
DELIMITER ;

CALL crewcare_prod_shoot_schedule_2026_08_23();
DROP PROCEDURE IF EXISTS crewcare_prod_shoot_schedule_2026_08_23;
