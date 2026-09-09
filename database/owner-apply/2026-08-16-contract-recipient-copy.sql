-- ============================================================================
-- CrewCare — CONTRATO · PASO C · BLOQUE B1: DESTINATARIOS DE COPIA / ENTREGA-CERTIFICADA.
-- (2026-08-16) — delta. Suma al sobre gente que solo RECIBE (no firma): copia legal, contabilidad,
--   acuse al contratado. Reciben el contrato FIRMADO + el certificado de cierre al COMPLETARSE la
--   ruta; NO firman, NO bloquean el turno, NO entran a la ruta de firma.
--
--   delivery_mode  — 'sign' (o NULL = firmante, comportamiento histórico) | 'copy' (solo recibe).
--   delivered_at   — cuándo se le entregó la copia certificada (al completarse el sobre).
--
-- METADATO DE RUTA/ENTREGA: ambas en $signatureExcludes del modelo → agregar la columna NO convierte
--   en "alterados" los destinatarios YA firmados y sellados. NULLABLE/ADITIVO: los sobres viejos
--   siguen igual (sus destinatarios son firmantes por delivery_mode NULL).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela por columna):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-16-contract-recipient-copy.sql
-- Requiere `contract_envelope_recipients`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_recipient_copy_2026_08_16;
DELIMITER //
CREATE PROCEDURE crewcare_recipient_copy_2026_08_16()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelope_recipients'
          AND COLUMN_NAME = 'delivery_mode') THEN
        ALTER TABLE `contract_envelope_recipients`
            ADD COLUMN `delivery_mode` VARCHAR(10) NULL AFTER `sort_order`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelope_recipients'
          AND COLUMN_NAME = 'delivered_at') THEN
        ALTER TABLE `contract_envelope_recipients`
            ADD COLUMN `delivered_at` DATETIME NULL AFTER `signed_at`;
    END IF;
END //
DELIMITER ;
CALL crewcare_recipient_copy_2026_08_16();
DROP PROCEDURE IF EXISTS crewcare_recipient_copy_2026_08_16;
