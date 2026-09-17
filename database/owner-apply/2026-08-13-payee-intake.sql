-- ============================================================================
-- CrewCare — QUIEN COBRA · PASO 3 (INTAKE AUTOSERVICIO + estado RECIBIDO).
-- (2026-08-13) — delta. Sigue de 2026-08-13-payee-packages.sql.
--
-- QUÉ ES:
--   · `payees` EXTENDIDO con lo que llena la persona en su intake: identidad
--     (nacionalidad, credencial de elector, domicilio, estado civil), contacto de
--     emergencia y logística (talla, vegetariano, donador) + marca de intake enviado.
--     ⚠ NO van tipo de sangre ni alergias: son dato CLÍNICO (expediente/médico).
--   · `payee_beneficiaries`     — beneficiarios en caso de fallecimiento. DATO DE
--     TERCEROS que no consienten (misma familia que heredo-familiares): SOLO nombre,
--     parentesco y porcentaje. Los porcentajes deben sumar 100% (se valida al guardar).
--   · `payee_declared_equipment`— equipo declarado para seguro. Es una DECLARACIÓN con
--     consecuencia económica (lo no declarado no se cubre) → se FIRMA con la capa simple
--     (aceptación + rastro + sello), calcando IssuedPermit: acceptor_* congelado +
--     accepted_at, y el sello vive en `digital_signatures` (HasDigitalSignatures).
--     ⚠ NO es el equipo RENTADO (eso se paga y va como contrato).
--   · `production_document_settings.equipment_threshold` — umbral del equipo declarable
--     (referencia $6,000 MXN). CONFIGURACIÓN por producción, no constante en código.
--
-- La captura (intake autoservicio + captura por quien contrata) escribe en ESTAS tablas
-- y en el ledger `external_authorizations` (estado RECIBIDO, nunca "validado"). Un solo
-- camino de escritura.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente.
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-payee-intake.sql
--   Luego: php artisan cache:clear
-- ============================================================================

-- payees: campos del intake (una sola sentencia, guardada por columna centinela). ----
DROP PROCEDURE IF EXISTS crewcare_payee_intake_fields_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_payee_intake_fields_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payees' AND COLUMN_NAME='nationality') THEN
        ALTER TABLE `payees`
            ADD COLUMN `nationality`            VARCHAR(20)  NULL AFTER `name`,          -- mexicana | extranjera
            ADD COLUMN `elector_credential`     VARCHAR(30)  NULL AFTER `nationality`,   -- número de credencial de elector
            ADD COLUMN `marital_status`         VARCHAR(30)  NULL AFTER `elector_credential`,
            ADD COLUMN `addr_street`            VARCHAR(160) NULL AFTER `bank_clabe`,
            ADD COLUMN `addr_ext_no`            VARCHAR(20)  NULL AFTER `addr_street`,
            ADD COLUMN `addr_int_no`            VARCHAR(20)  NULL AFTER `addr_ext_no`,
            ADD COLUMN `addr_colonia`           VARCHAR(120) NULL AFTER `addr_int_no`,
            ADD COLUMN `addr_municipio`         VARCHAR(120) NULL AFTER `addr_colonia`,  -- alcaldía o municipio
            ADD COLUMN `addr_cp`                VARCHAR(10)  NULL AFTER `addr_municipio`,
            ADD COLUMN `addr_city`              VARCHAR(120) NULL AFTER `addr_cp`,
            ADD COLUMN `addr_state`             VARCHAR(120) NULL AFTER `addr_city`,
            ADD COLUMN `emergency_contact_name` VARCHAR(160) NULL AFTER `addr_state`,
            ADD COLUMN `emergency_contact_phone` VARCHAR(40) NULL AFTER `emergency_contact_name`,
            ADD COLUMN `shirt_size`             VARCHAR(10)  NULL AFTER `emergency_contact_phone`,
            ADD COLUMN `is_vegetarian`          TINYINT(1)   NULL AFTER `shirt_size`,
            ADD COLUMN `is_donor`               TINYINT(1)   NULL AFTER `is_vegetarian`,
            ADD COLUMN `intake_submitted_at`    DATETIME     NULL AFTER `is_donor`;
    END IF;
END //
DELIMITER ;
CALL crewcare_payee_intake_fields_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_payee_intake_fields_2026_08_13;

-- BENEFICIARIOS — dato de terceros, MÍNIMO (nombre, parentesco, %). Suman 100%. -------
CREATE TABLE IF NOT EXISTS `payee_beneficiaries` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payee_id`     BIGINT UNSIGNED NOT NULL,           -- FK-soft a payees
    `full_name`    VARCHAR(200) NOT NULL,              -- igual que en identificación / acta
    `relationship` VARCHAR(60)  NULL,                  -- parentesco
    `percentage`   DECIMAL(5,2) NOT NULL DEFAULT 0,    -- % individual (el conjunto suma 100)
    `sort_order`   INT          NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`   TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `payee_beneficiaries_payee_idx` (`payee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EQUIPO DECLARADO PARA SEGURO — DECLARACIÓN firmada (capa simple, calca IssuedPermit).
CREATE TABLE IF NOT EXISTS `payee_declared_equipment` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payee_id`         BIGINT UNSIGNED NOT NULL,       -- FK-soft a payees
    `description`      VARCHAR(255) NOT NULL,          -- qué equipo
    `invoice_holder`   VARCHAR(200) NULL,              -- a nombre de quién está la factura
    `declared_value`   DECIMAL(12,2) NOT NULL DEFAULT 0, -- valor declarado (sobre el umbral)
    -- Aceptación CONGELADA (su firma es su identidad + aceptación de la consecuencia) --
    `acceptor_user_id` BIGINT UNSIGNED NULL,           -- FK-soft a users (puede no tener cuenta)
    `acceptor_name`    VARCHAR(200) NULL,
    `acceptor_role`    VARCHAR(100) NULL,
    `accepted_at`      DATETIME     NULL,              -- NULL = capturado, aún sin firmar
    `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `payee_declared_equipment_payee_idx` (`payee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- (el sello SHA vive en `digital_signatures`, polimórfico a este modelo; ya existe)

-- Umbral del equipo declarable, por producción (configuración, no constante). ---------
DROP PROCEDURE IF EXISTS crewcare_equipment_threshold_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_equipment_threshold_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='production_document_settings' AND COLUMN_NAME='equipment_threshold') THEN
        ALTER TABLE `production_document_settings`
            ADD COLUMN `equipment_threshold` DECIMAL(12,2) NOT NULL DEFAULT 6000.00 AFTER `csf_cut_day`;
    END IF;
END //
DELIMITER ;
CALL crewcare_equipment_threshold_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_equipment_threshold_2026_08_13;

-- REVERSIÓN (manual): DROP TABLE payee_beneficiaries, payee_declared_equipment;
--   ALTER TABLE payees DROP COLUMN nationality, ... (los 17);
--   ALTER TABLE production_document_settings DROP COLUMN equipment_threshold;
