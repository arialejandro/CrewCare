-- ============================================================================
-- CrewCare — FIRMA AUTÓGRAFA · la firma ADOPTADA del usuario (reutilizable, tipo DocuSign).
-- (2026-08-13) — delta. PNG base64. NULLABLE/ADITIVO. NO es el sello (digital_signatures): es la
--   plantilla visual reusable; la firma APLICADA se congela en la entidad firmada y el hash la cubre.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela `adopted_signature`):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-user-adopted-signature.sql
-- Requiere `users`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_user_adopted_signature_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_user_adopted_signature_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'adopted_signature') THEN
        ALTER TABLE `users` ADD COLUMN `adopted_signature` MEDIUMTEXT NULL;
    END IF;
END //
DELIMITER ;
CALL crewcare_user_adopted_signature_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_user_adopted_signature_2026_08_13;
