-- ============================================================================
-- CrewCare — VENTANA DE RECEPCIÓN: el documento cuelga del PERIODO.
-- (2026-08-13) — delta hermano de payment-periods. Dos columnas ADITIVAS y
--   NULLABLE sobre el ledger `external_authorizations`:
--     `payment_period_id`      → periodo de pago contra el que se recibió.
--     `received_out_of_window` → recibido pero FUERA de ventana (marcado, no rechazado).
--
--   Estas filas NO se sellan (los sellos viven en las actas con firma) → agregar
--   columnas aquí NO altera ningún hash. Se estampan best-effort al capturar.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guardado por
-- information_schema dentro de un PROCEDURE):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-external-auth-period-link.sql
-- Depende de: payment-periods (la tabla destino del id). SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_ext_auth_period_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_ext_auth_period_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_authorizations'
          AND COLUMN_NAME = 'payment_period_id') THEN
        ALTER TABLE `external_authorizations`
            ADD COLUMN `payment_period_id` BIGINT UNSIGNED NULL AFTER `document_type_id`,
            ADD KEY `ext_auth_period_idx` (`payment_period_id`);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_authorizations'
          AND COLUMN_NAME = 'received_out_of_window') THEN
        ALTER TABLE `external_authorizations`
            ADD COLUMN `received_out_of_window` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_period_id`;
    END IF;
END //
DELIMITER ;
CALL crewcare_ext_auth_period_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_ext_auth_period_2026_08_13;
