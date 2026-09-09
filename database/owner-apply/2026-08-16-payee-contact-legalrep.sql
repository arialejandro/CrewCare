-- ============================================================================
-- CrewCare — QUIEN COBRA · datos de contacto del contratado para el CONTRATO.
-- (2026-08-16) — delta. El DOCUMENTO del contrato (Contract Builder) tenía huecos [CONFIRMAR] de
--   DATO que ya se capturan pero no había dónde guardarlos en la ficha: TELÉFONO y CORREO propios del
--   payee (hoy solo existían si estaba ligado a un usuario) y, para persona MORAL, su REPRESENTANTE
--   LEGAL. Se agregan a `payees` para que el contrato los auto-llene (ContractTemplateRenderer).
--
--   phone                — teléfono del contratado (independiente del user ligado).
--   email                — correo del contratado (independiente del user ligado).
--   legal_representative — representante legal (SOLO aplica a persona moral; NULL en física).
--
-- ADITIVO/NULLABLE: no rompe intakes viejos ni sellos (Payee NO se sella). El intake los captura
--   (paso Identidad; el representante legal solo se pide cuando la naturaleza es moral).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela por columna):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-16-payee-contact-legalrep.sql
-- Requiere `payees`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_payee_contact_2026_08_16;
DELIMITER //
CREATE PROCEDURE crewcare_payee_contact_2026_08_16()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payees'
          AND COLUMN_NAME = 'phone') THEN
        ALTER TABLE `payees`
            ADD COLUMN `phone` VARCHAR(40) NULL AFTER `marital_status`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payees'
          AND COLUMN_NAME = 'email') THEN
        ALTER TABLE `payees`
            ADD COLUMN `email` VARCHAR(191) NULL AFTER `phone`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payees'
          AND COLUMN_NAME = 'legal_representative') THEN
        ALTER TABLE `payees`
            ADD COLUMN `legal_representative` VARCHAR(200) NULL AFTER `email`;
    END IF;
END //
DELIMITER ;
CALL crewcare_payee_contact_2026_08_16();
DROP PROCEDURE IF EXISTS crewcare_payee_contact_2026_08_16;
