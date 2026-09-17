-- ============================================================================
-- CrewCare — CONTRACT BUILDER · PDF FILLABLE (subir PDF + colocar etiquetas). 2026-08-16.
--
-- Segundo MODO de autoría del contrato: en vez de REDACTAR el body HTML, la producción sube el PDF
-- que ya hizo su área legal y COLOCA las etiquetas (dónde firma cada parte y qué dato se auto-llena),
-- estilo DocuSign/Adobe Sign. CrewCare no redacta: solo coloca campos y estampa encima conservando
-- el texto (FPDI). Ver [[contract-builder-legal-boundary]].
--
-- contract_templates gana:
--   · source_kind        'html' (default, plantilla actual) | 'pdf' (documento subido con etiquetas).
--   · pdf_path           ruta del PDF original en el disco local (storage/app), solo cuando source='pdf'.
--   · pdf_original_name  nombre original del archivo subido, para mostrarlo en el editor.
--   · field_map          JSON = [{page, x_pct, y_pct, w_pct, type:'data'|'sign', key}] — las etiquetas
--                        colocadas sobre las páginas (coordenadas en % del tamaño de página).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7+/8.0. Idempotente (guarda information_schema).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-16-contract-template-pdf-source.sql
--
-- REVERSIÓN:
--   ALTER TABLE `contract_templates`
--     DROP COLUMN `field_map`, DROP COLUMN `pdf_original_name`,
--     DROP COLUMN `pdf_path`, DROP COLUMN `source_kind`;
-- ============================================================================

DROP PROCEDURE IF EXISTS cc_ct_pdf_source;
DELIMITER $$
CREATE PROCEDURE cc_ct_pdf_source()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'source_kind') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `source_kind` VARCHAR(4) NOT NULL DEFAULT 'html' AFTER `body`;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'pdf_path') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `pdf_path` VARCHAR(255) NULL DEFAULT NULL AFTER `source_kind`;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'pdf_original_name') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `pdf_original_name` VARCHAR(191) NULL DEFAULT NULL AFTER `pdf_path`;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
                    AND COLUMN_NAME = 'field_map') THEN
    ALTER TABLE `contract_templates`
      ADD COLUMN `field_map` JSON NULL DEFAULT NULL AFTER `pdf_original_name`;
  END IF;
END$$
DELIMITER ;

CALL cc_ct_pdf_source();
DROP PROCEDURE IF EXISTS cc_ct_pdf_source;
