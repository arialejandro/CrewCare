-- ============================================================================
-- CrewCare — CONTRACT BUILDER · tipografía base del contrato. 2026-08-15.
--
-- contract_templates += `font_family` (serif / sans / mono). Fuentes del SISTEMA (cero peso extra);
-- default 'mono' (neutra). Alimenta el body/@page del PDF, la hoja del editor y el medidor del salto.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guarda information_schema).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-15-contract-template-font-family.sql
--
-- REVERSIÓN:  ALTER TABLE `contract_templates` DROP COLUMN `font_family`;
-- ============================================================================

DROP PROCEDURE IF EXISTS cc_ct_fontfamily;
DELIMITER $$
CREATE PROCEDURE cc_ct_fontfamily()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'font_family') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `font_family` VARCHAR(16) NOT NULL DEFAULT 'mono' AFTER `page_size`;
  END IF;
END$$
DELIMITER ;

CALL cc_ct_fontfamily();
DROP PROCEDURE IF EXISTS cc_ct_fontfamily;
