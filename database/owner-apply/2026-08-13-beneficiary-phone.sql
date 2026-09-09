-- ============================================================================
-- CrewCare — EL INFOSHEET · FASE 1: teléfono del BENEFICIARIO mortis causa.
-- (2026-08-13) — delta. Dato DISTINTO del contacto de emergencia (payees.emergency_contact_*);
--   cada uno en su tabla y no se confunden. Cierra el hueco: payee_contracts.beneficiary_phone se
--   congela al emitir pero el intake nunca capturaba el teléfono del beneficiario. NULLABLE/ADITIVO.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela `phone`):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-beneficiary-phone.sql
-- Requiere `payee_beneficiaries`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_beneficiary_phone_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_beneficiary_phone_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payee_beneficiaries'
          AND COLUMN_NAME = 'phone') THEN
        ALTER TABLE `payee_beneficiaries`
            ADD COLUMN `phone` VARCHAR(40) NULL AFTER `relationship`;
    END IF;
END //
DELIMITER ;
CALL crewcare_beneficiary_phone_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_beneficiary_phone_2026_08_13;
