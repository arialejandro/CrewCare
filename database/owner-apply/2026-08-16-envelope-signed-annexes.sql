-- ============================================================================
-- CrewCare — SOBRE multi-documento: anexos firmados. 2026-08-16.
--
-- El sobre congela hoy UN documento firmado (`signed_document` = el contrato principal). Con los
-- anexos como plantillas (que se estampan con datos + firmas por contrato), el sobre pasa a congelar
-- también un CONJUNTO de anexos firmados. `signed_annexes` = JSON lista de {path,hash,bytes,name,
-- template_id,engine}. Se excluye del sello del sobre igual que `signed_document` (su integridad va
-- en la bitácora, no en el hash del paquete).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7+/8.0. Idempotente (guarda information_schema).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-16-envelope-signed-annexes.sql
--
-- REVERSIÓN:  ALTER TABLE `contract_envelopes` DROP COLUMN `signed_annexes`;
-- ============================================================================

DROP PROCEDURE IF EXISTS cc_env_signed_annexes;
DELIMITER $$
CREATE PROCEDURE cc_env_signed_annexes()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelopes'
                    AND COLUMN_NAME = 'signed_annexes') THEN
    ALTER TABLE `contract_envelopes`
      ADD COLUMN `signed_annexes` JSON NULL DEFAULT NULL AFTER `signed_document`;
  END IF;
END$$
DELIMITER ;

CALL cc_env_signed_annexes();
DROP PROCEDURE IF EXISTS cc_env_signed_annexes;
