-- ============================================================================
-- CrewCare — CONTRACT BUILDER inc.1 · el FORMATO como dato del template. 2026-08-14.
--
-- contract_templates += `architecture` (carátula tabla numerada / ficha etiqueta:valor /
-- declaraciones-primero) + `bilingual` (reservado para el 2-col del incremento 2).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guarda information_schema).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-14-contract-template-format.sql
--
-- REVERSIÓN:
--   ALTER TABLE `contract_templates` DROP COLUMN `bilingual`, DROP COLUMN `architecture`;
-- ============================================================================

DROP PROCEDURE IF EXISTS cc_ct_format;
DELIMITER $$
CREATE PROCEDURE cc_ct_format()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'architecture') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `architecture` VARCHAR(32) NOT NULL DEFAULT 'caratula_numbered' AFTER `language`;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'bilingual') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `bilingual` TINYINT(1) NOT NULL DEFAULT 0 AFTER `architecture`;
  END IF;
END$$
DELIMITER ;

CALL cc_ct_format();
DROP PROCEDURE IF EXISTS cc_ct_format;
