-- ============================================================================
-- CrewCare — CONTRACT BUILDER · tamaño de la letra base del contrato (pt). 2026-08-15.
--
-- contract_templates += `font_size` (pt: '10' / '11' / '12'). El corpus usa letra chica; 12pt se veía
-- enorme (sobre todo en monospace). Default '11'. Alimenta el body del PDF, la hoja del editor y el
-- medidor del salto.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guarda information_schema).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-15-contract-template-font-size.sql
--
-- REVERSIÓN:  ALTER TABLE `contract_templates` DROP COLUMN `font_size`;
-- ============================================================================

DROP PROCEDURE IF EXISTS cc_ct_fontsize;
DELIMITER $$
CREATE PROCEDURE cc_ct_fontsize()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'font_size') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `font_size` VARCHAR(8) NOT NULL DEFAULT '11' AFTER `font_family`;
  END IF;
END$$
DELIMITER ;

CALL cc_ct_fontsize();
DROP PROCEDURE IF EXISTS cc_ct_fontsize;
