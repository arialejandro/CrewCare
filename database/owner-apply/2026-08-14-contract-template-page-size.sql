-- ============================================================================
-- CrewCare — CONTRACT BUILDER · tamaño de página del documento. 2026-08-14.
--
-- contract_templates += `page_size` (carta / legal / a4). El corpus real es CARTA (salvo
-- Spectrum = Oficio-Legal), así que default 'carta'. Alimenta el @page del PDF y la hoja del editor.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guarda information_schema).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-14-contract-template-page-size.sql
--
-- REVERSIÓN:  ALTER TABLE `contract_templates` DROP COLUMN `page_size`;
-- ============================================================================

DROP PROCEDURE IF EXISTS cc_ct_pagesize;
DELIMITER $$
CREATE PROCEDURE cc_ct_pagesize()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'page_size') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `page_size` VARCHAR(16) NOT NULL DEFAULT 'carta' AFTER `bilingual`;
  END IF;
END$$
DELIMITER ;

CALL cc_ct_pagesize();
DROP PROCEDURE IF EXISTS cc_ct_pagesize;
