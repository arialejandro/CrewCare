-- ============================================================================
-- CrewCare — PLANTILLAS unificadas: categoría (contrato|anexo) + orden. 2026-08-16.
--
-- La plantilla deja de ser solo "el contrato": ahora una plantilla puede ser el CONTRATO principal o
-- un ANEXO (documento adicional). Con esto el PDF fillable encuentra su lugar natural (anexos: subir
-- el PDF y colocar tags) y el clausulado subido queda redundante (su cuerpo legal lo lleva la
-- plantilla-contrato). `sort_order` ordena los anexos cuando hay varios.
--
--   · category    'contrato' (default) | 'anexo'.
--   · sort_order  entero para ordenar (anexos), default 0.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7+/8.0. Idempotente (guarda information_schema).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-16-contract-template-category.sql
--
-- REVERSIÓN:  ALTER TABLE `contract_templates` DROP COLUMN `sort_order`, DROP COLUMN `category`;
-- ============================================================================

DROP PROCEDURE IF EXISTS cc_ct_category;
DELIMITER $$
CREATE PROCEDURE cc_ct_category()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'category') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `category` VARCHAR(10) NOT NULL DEFAULT 'contrato' AFTER `applies_to`;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'sort_order') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `category`;
  END IF;
END$$
DELIMITER ;

CALL cc_ct_category();
DROP PROCEDURE IF EXISTS cc_ct_category;
