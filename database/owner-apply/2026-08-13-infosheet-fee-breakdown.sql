-- ============================================================================
-- CrewCare — EL INFOSHEET · FASE 1: importe POR FASE + desglose fiscal en payee_contracts.
-- (2026-08-13) — delta. El trato que faltaba: {semanas · tarifa · importe} × 4 fases
--   (soft_prep/prep/shoot/wrap; 3 capturas por fase porque la tarifa puede cambiar), IVA /
--   retención ISR / retención IVA, factura vs recibo, y administra caja chica. TODO NULLABLE y
--   ADITIVO — en equipment_rental/service quedan NULL (AmbulancePayeeLink no se rompe).
--   payee_contracts NO se sella → no toca hashes. El honorario TOTAL sigue en fee_amount (Paso A);
--   no se duplica. payment_document_type (factura|recibo) != issues_own_cfdi (quién emite CFDI).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guardado por
-- information_schema con columna centinela `fee_soft_prep_weeks`; el ALTER agrega todas juntas):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-infosheet-fee-breakdown.sql
-- Requiere `payee_contracts`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_infosheet_fee_breakdown_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_infosheet_fee_breakdown_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payee_contracts'
          AND COLUMN_NAME = 'fee_soft_prep_weeks') THEN
        ALTER TABLE `payee_contracts`
            ADD COLUMN `fee_soft_prep_weeks` DECIMAL(5,2) NULL,
            ADD COLUMN `fee_soft_prep_rate` DECIMAL(12,2) NULL,
            ADD COLUMN `fee_soft_prep_amount` DECIMAL(12,2) NULL,
            ADD COLUMN `fee_prep_weeks` DECIMAL(5,2) NULL,
            ADD COLUMN `fee_prep_rate` DECIMAL(12,2) NULL,
            ADD COLUMN `fee_prep_amount` DECIMAL(12,2) NULL,
            ADD COLUMN `fee_shoot_weeks` DECIMAL(5,2) NULL,
            ADD COLUMN `fee_shoot_rate` DECIMAL(12,2) NULL,
            ADD COLUMN `fee_shoot_amount` DECIMAL(12,2) NULL,
            ADD COLUMN `fee_wrap_weeks` DECIMAL(5,2) NULL,
            ADD COLUMN `fee_wrap_rate` DECIMAL(12,2) NULL,
            ADD COLUMN `fee_wrap_amount` DECIMAL(12,2) NULL,
            ADD COLUMN `tax_iva` DECIMAL(12,2) NULL,
            ADD COLUMN `tax_isr_retention` DECIMAL(12,2) NULL,
            ADD COLUMN `tax_iva_retention` DECIMAL(12,2) NULL,
            ADD COLUMN `payment_document_type` VARCHAR(20) NULL,
            ADD COLUMN `manages_petty_cash` TINYINT(1) NULL;
    END IF;
END //
DELIMITER ;
CALL crewcare_infosheet_fee_breakdown_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_infosheet_fee_breakdown_2026_08_13;
