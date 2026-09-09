-- ============================================================================
-- CrewCare — EL CONTRATO · PASO B: lo que el contrato CONGELA AL EMITIR.
-- (2026-08-13) — delta. Columnas ADITIVAS nullable en payee_contracts:
--   clause_id (clausulado+versión exacta), language (idioma emitido),
--   caratula_path (PDF de la carátula generado, inmutable), emitted_at, emitted_by_id.
--   Los datos del contratante y credit_name/beneficiary_* ya son del Paso A; el EMIT
--   los llena y ahí quedan congelados. payee_contracts NO se sella → no toca hashes.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guardado por
-- information_schema con columna centinela `clause_id`):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-contract-emit-fields.sql
-- Requiere `payee_contracts`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_contract_emit_fields_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_contract_emit_fields_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payee_contracts'
          AND COLUMN_NAME = 'clause_id') THEN
        ALTER TABLE `payee_contracts`
            ADD COLUMN `clause_id` BIGINT UNSIGNED NULL,
            ADD COLUMN `language` VARCHAR(12) NULL,
            ADD COLUMN `caratula_path` VARCHAR(500) NULL,
            ADD COLUMN `emitted_at` DATETIME NULL,
            ADD COLUMN `emitted_by_id` BIGINT UNSIGNED NULL,
            ADD KEY `payee_contracts_clause_idx` (`clause_id`);
    END IF;
END //
DELIMITER ;
CALL crewcare_contract_emit_fields_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_contract_emit_fields_2026_08_13;
