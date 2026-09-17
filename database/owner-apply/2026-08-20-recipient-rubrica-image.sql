-- ============================================================================
-- CrewCare — FIRMA vs RÚBRICA: marca de rúbrica aparte. 2026-08-20.
--
-- La firma completa vive en `signature_image`. La RÚBRICA es una marca DISTINTA que cada persona
-- elige (iniciales, una variante de su firma, o su firma misma); va en `rubrica_image`. Las etiquetas
-- de rúbrica estampan ESTA imagen, no la firma. NULLABLE y ADITIVO; queda FUERA del sello del sobre
-- (su integridad la respalda la firma sellada), así agregar la columna NO altera sobres ya sellados.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7+/8.0. Idempotente (guarda information_schema).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-20-recipient-rubrica-image.sql
--
-- REVERSIÓN:  ALTER TABLE `contract_envelope_recipients` DROP COLUMN `rubrica_image`;
-- ============================================================================

DROP PROCEDURE IF EXISTS cc_recipient_rubrica_image;
DELIMITER $$
CREATE PROCEDURE cc_recipient_rubrica_image()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelope_recipients'
                    AND COLUMN_NAME = 'rubrica_image') THEN
    ALTER TABLE `contract_envelope_recipients`
      ADD COLUMN `rubrica_image` MEDIUMTEXT NULL AFTER `signature_image`;
  END IF;
END$$
DELIMITER ;

CALL cc_recipient_rubrica_image();
DROP PROCEDURE IF EXISTS cc_recipient_rubrica_image;
