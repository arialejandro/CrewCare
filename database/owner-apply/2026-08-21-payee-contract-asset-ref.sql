-- ============================================================================
-- CrewCare — Referencia mínima del ACTIVO en el contrato de RENTA. 2026-08-21.
--
-- `asset_ref` (JSON ligero: marca/modelo/placa/año del vehículo o equipo) es el GANCHO para el módulo
-- futuro de Transportación: se captura aquí y luego se promueve a la entidad "Vehículo" (auditorías +
-- órdenes de transportación). NULLABLE y ADITIVO; `payee_contracts` NO se sella → no toca ningún hash.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7+/8.0. Idempotente (guarda information_schema).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-21-payee-contract-asset-ref.sql
--
-- REVERSIÓN:  ALTER TABLE `payee_contracts` DROP COLUMN `asset_ref`;
-- ============================================================================

DROP PROCEDURE IF EXISTS cc_payee_contract_asset_ref;
DELIMITER $$
CREATE PROCEDURE cc_payee_contract_asset_ref()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payee_contracts'
                    AND COLUMN_NAME = 'asset_ref') THEN
    ALTER TABLE `payee_contracts`
      ADD COLUMN `asset_ref` TEXT NULL AFTER `title`;
  END IF;
END$$
DELIMITER ;

CALL cc_payee_contract_asset_ref();
DROP PROCEDURE IF EXISTS cc_payee_contract_asset_ref;
