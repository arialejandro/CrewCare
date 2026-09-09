-- ============================================================================
-- CrewCare — EL INFOSHEET · FASE 3.3: FIRMA AUTÓGRAFA en el sobre (DocuSign).
-- (2026-08-13) — delta. Cada destinatario firma dibujando/escribiendo; la imagen PNG (data URL
--   base64) vive en `contract_envelope_recipients.signature_image` (SU acto de aceptación). Al ser
--   columna del propio documento, el hash del sello (digital_signatures) la CUBRE → "verificada e
--   íntegra". NULLABLE/ADITIVO: los sobres viejos siguen válidos sin autógrafa.
--
-- MEDIUMTEXT (un PNG base64 rebasa TEXT ~64 KB), igual que users.adopted_signature.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela `signature_image`):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-recipient-signature-image.sql
-- Requiere `contract_envelope_recipients`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_recipient_sig_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_recipient_sig_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelope_recipients'
          AND COLUMN_NAME = 'signature_image') THEN
        ALTER TABLE `contract_envelope_recipients`
            ADD COLUMN `signature_image` MEDIUMTEXT NULL AFTER `sign_method`;
    END IF;
END //
DELIMITER ;
CALL crewcare_recipient_sig_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_recipient_sig_2026_08_13;
