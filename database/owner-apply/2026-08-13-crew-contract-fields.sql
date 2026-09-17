-- ============================================================================
-- CrewCare — EL CONTRATO · PASO A: campos de la CARÁTULA en payee_contracts (crew_work).
-- (2026-08-13) — delta. Extiende payee_contracts con los campos de las carátulas
--   reales; TODO NULLABLE y ADITIVO. En equipment_rental/service quedan NULL (los que
--   crea AmbulancePayeeLink no se rompen). payee_contracts NO se sella → no toca hashes.
--   La frecuencia de honorarios reusa payment_frequency (no se duplica). Congelados:
--   credit_name, beneficiary_*, contractor_* (se llenan al emitir/firmar).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guardado por
-- information_schema con columna centinela `crew_activity`; el ALTER agrega todas juntas):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-crew-contract-fields.sql
-- Requiere `payee_contracts` (quien-cobra). SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_crew_contract_fields_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_crew_contract_fields_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payee_contracts'
          AND COLUMN_NAME = 'crew_activity') THEN
        ALTER TABLE `payee_contracts`
            ADD COLUMN `crew_activity` VARCHAR(255) NULL AFTER `title`,
            ADD COLUMN `department_id` BIGINT UNSIGNED NULL AFTER `crew_activity`,
            ADD COLUMN `credit_name` VARCHAR(191) NULL AFTER `department_id`,
            ADD COLUMN `effective_date` DATE NULL,
            ADD COLUMN `estimated_end_date` DATE NULL,
            ADD COLUMN `definitive_end_date` DATE NULL,
            ADD COLUMN `fee_amount` DECIMAL(12,2) NULL,
            ADD COLUMN `fee_currency` VARCHAR(3) NULL,
            ADD COLUMN `issues_own_cfdi` TINYINT(1) NULL,
            ADD COLUMN `union_payroll` VARCHAR(160) NULL,
            ADD COLUMN `union_is_member` TINYINT(1) NULL,
            ADD COLUMN `union_retention_pct` DECIMAL(5,2) NULL,
            ADD COLUMN `perdiem_breakfast` DECIMAL(10,2) NULL,
            ADD COLUMN `perdiem_lunch` DECIMAL(10,2) NULL,
            ADD COLUMN `perdiem_dinner` DECIMAL(10,2) NULL,
            ADD COLUMN `perdiem_weekly_prep` DECIMAL(10,2) NULL,
            ADD COLUMN `perdiem_weekly_shoot` DECIMAL(10,2) NULL,
            ADD COLUMN `lodging_type` VARCHAR(20) NULL,
            ADD COLUMN `lodging_monthly_supplement` DECIMAL(10,2) NULL,
            ADD COLUMN `round_flights` SMALLINT UNSIGNED NULL,
            ADD COLUMN `budget_account` VARCHAR(80) NULL,
            ADD COLUMN `beneficiary_name` VARCHAR(160) NULL,
            ADD COLUMN `beneficiary_relationship` VARCHAR(80) NULL,
            ADD COLUMN `beneficiary_phone` VARCHAR(40) NULL,
            ADD COLUMN `contractor_legal_name` VARCHAR(191) NULL,
            ADD COLUMN `contractor_rfc` VARCHAR(20) NULL,
            ADD COLUMN `contractor_address` VARCHAR(255) NULL,
            ADD COLUMN `contractor_representative` VARCHAR(160) NULL,
            ADD COLUMN `contractor_email` VARCHAR(160) NULL,
            ADD KEY `payee_contracts_dept_idx` (`department_id`);
    END IF;
END //
DELIMITER ;
CALL crewcare_crew_contract_fields_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_crew_contract_fields_2026_08_13;
