-- ============================================================================
-- CrewCare — CONTRACT BUILDER · FASE 1c: ANCLA de firma por destinatario.
-- (2026-08-14) — delta. Enlaza cada destinatario del sobre con su `[[firma:CLAVE]]` de la plantilla
--   (contratado / dept_hod / puesto:ID). La clave se CONGELA al construir el sobre → el estampado de
--   la plantilla es inmune a cambios posteriores de la lista de firmantes.
--
-- NO entra al hash del sello del destinatario (está en $signatureExcludes del modelo): es metadato
--   de ruta, no el acto de firma → los sobres YA sellados NO se vuelven "alterados".
-- NULLABLE/ADITIVO: los sobres viejos siguen válidos sin ancla.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela `anchor_key`):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-14-recipient-anchor-key.sql
-- Requiere `contract_envelope_recipients`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_recipient_anchor_2026_08_14;
DELIMITER //
CREATE PROCEDURE crewcare_recipient_anchor_2026_08_14()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelope_recipients'
          AND COLUMN_NAME = 'anchor_key') THEN
        ALTER TABLE `contract_envelope_recipients`
            ADD COLUMN `anchor_key` VARCHAR(64) NULL AFTER `cargo`;
    END IF;
END //
DELIMITER ;
CALL crewcare_recipient_anchor_2026_08_14();
DROP PROCEDURE IF EXISTS crewcare_recipient_anchor_2026_08_14;
