-- ============================================================================
-- CrewCare — EL INFOSHEET · FASE 4 (endurecimiento): CADUCIDAD del token de acceso externo.
-- (2026-08-13) — delta. El hash de un solo uso ya muere al consumirse; esta columna le agrega
--   vencimiento POR TIEMPO (7 días). Un token vencido responde igual que el usado (410, sin fuga).
--   NULLABLE/ADITIVO: NULL = sin caducidad (tokens heredados).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela `external_access_expires_at`):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-external-access-expiry.sql
-- Requiere `users`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_ext_access_expiry_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_ext_access_expiry_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'external_access_expires_at') THEN
        ALTER TABLE `users`
            ADD COLUMN `external_access_expires_at` DATETIME NULL AFTER `external_access_used_at`;
    END IF;
END //
DELIMITER ;
CALL crewcare_ext_access_expiry_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_ext_access_expiry_2026_08_13;
