-- ============================================================================
-- CrewCare — CONTRACT BUILDER inc.3c-1 · rúbrica del contratado en cada página. 2026-08-14.
--
-- contract_templates += `initials_each_page` (0/1). ON = rúbrica pequeña (copia de la firma del
-- contratado) en cada hoja, como la "Inicial" del corpus.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guarda information_schema).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-14-contract-template-initials.sql
--
-- REVERSIÓN:  ALTER TABLE `contract_templates` DROP COLUMN `initials_each_page`;
-- ============================================================================

DROP PROCEDURE IF EXISTS cc_ct_initials;
DELIMITER $$
CREATE PROCEDURE cc_ct_initials()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'initials_each_page') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `initials_each_page` TINYINT(1) NOT NULL DEFAULT 0 AFTER `page_size`;
  END IF;
END$$
DELIMITER ;

CALL cc_ct_initials();
DROP PROCEDURE IF EXISTS cc_ct_initials;
