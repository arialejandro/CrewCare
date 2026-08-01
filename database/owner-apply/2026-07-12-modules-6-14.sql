-- ============================================================================
-- CrewCare — CAPA DE CIMIENTOS Módulos 6–14 (2026-07-12)
-- Firmas digitales / no-repudio, testigos del Injury, UUID público por reporte,
-- EPP transversal, clima extendido del DSR, inventario de emergencia del Scouting,
-- notificaciones a autoridad + justificación de ubicación manual, e i18n de catálogos.
--
-- Solo ESQUEMA (tablas + columnas). Los modelos/traits ya son DEFENSIVOS
-- (Schema::hasTable / hasColumn): las pantallas siguen funcionando aunque estas
-- columnas/tablas aún no existan; al aplicar este delta las features se activan
-- solas. La capa de controladores/vistas/FormRequests la hace otro equipo.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), igual que el resto del
-- esquema. Correr en un cliente MySQL (HeidiSQL / CLI):
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-12-modules-6-14.sql
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS` ni `ADD INDEX IF NOT EXISTS`;
-- para que RE-EJECUTARLO sea SEGURO (idempotente) los ALTER/ADD INDEX se guardan
-- con un check en information_schema vía un procedimiento temporal. Las tablas
-- usan `CREATE TABLE IF NOT EXISTS`.
--
-- Convención del repo: BIGINT UNSIGNED. `digital_signatures` es polimórfica y sin
-- FK dura (mismo criterio que action_items/standardables); la integridad se cuida
-- en la app. EXCEPCIÓN pedida en el spec: `witnesses` SÍ intenta FK dura a
-- injury_reports(id) ON DELETE CASCADE (tipos verificados: ambos BIGINT UNSIGNED,
-- InnoDB) — si el motor la rechazara, quitar la línea CONSTRAINT y dejar el índice.
--
-- UNIQUE en `uuid`: MySQL permite MÚLTIPLES NULL en un índice UNIQUE, así que las
-- filas legadas sin uuid no chocan; el backfill (UUID() se evalúa POR FILA en un
-- UPDATE — verificado) las rellena con valores distintos.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) digital_signatures — Firmas digitales / no-repudio (SHA-256 del payload
--    canónico del documento). Polimórfica (documentable_type/id) → cualquier
--    reporte de seguridad. Sin FK dura (patrón action_items/standardables).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `digital_signatures` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `documentable_type` VARCHAR(255) NOT NULL,
    `documentable_id`   BIGINT UNSIGNED NOT NULL,
    `user_id`           BIGINT UNSIGNED NULL,
    `role_at_signing`   VARCHAR(100) NULL,
    `ip_address`        VARCHAR(45) NULL,
    `user_agent`        VARCHAR(500) NULL,
    `document_hash`     CHAR(64) NOT NULL,
    `signed_at`         DATETIME NULL,
    `created_at`        DATETIME NULL DEFAULT NULL,
    `updated_at`        DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `digital_signatures_morph_idx` (`documentable_type`, `documentable_id`),
    KEY `digital_signatures_user_idx`  (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2) witnesses — Testigos del Accidente/Lesión (1:N a injury_reports).
--    FK dura ON DELETE CASCADE (tipos verificados). Si el motor la rechaza en
--    algún entorno, elimina la línea CONSTRAINT y conserva la KEY del índice.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `witnesses` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `injury_report_id` BIGINT UNSIGNED NOT NULL,
    `name`             VARCHAR(255) NOT NULL,
    `phone`            VARCHAR(50) NULL,
    `statement`        TEXT NULL,
    `created_at`       DATETIME NULL DEFAULT NULL,
    `updated_at`       DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `witnesses_injury_report_idx` (`injury_report_id`),
    CONSTRAINT `witnesses_injury_report_id_fk`
        FOREIGN KEY (`injury_report_id`) REFERENCES `injury_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3) Columnas nuevas + índices UNIQUE de uuid (idempotentes vía procedimiento).
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS crewcare_modules_6_14_2026_07_12;

DELIMITER //
CREATE PROCEDURE crewcare_modules_6_14_2026_07_12()
BEGIN
    -- ====================================================================
    -- daily_reports — EPP + clima extendido + factores de riesgo del día + uuid
    -- ====================================================================
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND COLUMN_NAME='required_ppe') THEN
        ALTER TABLE `daily_reports` ADD COLUMN `required_ppe` JSON NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND COLUMN_NAME='humidity') THEN
        ALTER TABLE `daily_reports` ADD COLUMN `humidity` INT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND COLUMN_NAME='wind_speed') THEN
        ALTER TABLE `daily_reports` ADD COLUMN `wind_speed` DECIMAL(5,2) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND COLUMN_NAME='heat_index') THEN
        ALTER TABLE `daily_reports` ADD COLUMN `heat_index` DECIMAL(5,2) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND COLUMN_NAME='day_risk_factors') THEN
        ALTER TABLE `daily_reports` ADD COLUMN `day_risk_factors` JSON NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND COLUMN_NAME='uuid') THEN
        ALTER TABLE `daily_reports` ADD COLUMN `uuid` CHAR(36) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND INDEX_NAME='daily_reports_uuid_unique') THEN
        ALTER TABLE `daily_reports` ADD UNIQUE INDEX `daily_reports_uuid_unique` (`uuid`);
    END IF;

    -- ====================================================================
    -- scouting_reports — EPP + headcount máx + inventario emergencia +
    --                     instalaciones logísticas + uuid
    -- ====================================================================
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='scouting_reports' AND COLUMN_NAME='required_ppe') THEN
        ALTER TABLE `scouting_reports` ADD COLUMN `required_ppe` JSON NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='scouting_reports' AND COLUMN_NAME='max_headcount') THEN
        ALTER TABLE `scouting_reports` ADD COLUMN `max_headcount` INT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='scouting_reports' AND COLUMN_NAME='emergency_equipment_inventory') THEN
        ALTER TABLE `scouting_reports` ADD COLUMN `emergency_equipment_inventory` JSON NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='scouting_reports' AND COLUMN_NAME='logistics_facilities') THEN
        ALTER TABLE `scouting_reports` ADD COLUMN `logistics_facilities` JSON NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='scouting_reports' AND COLUMN_NAME='uuid') THEN
        ALTER TABLE `scouting_reports` ADD COLUMN `uuid` CHAR(36) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='scouting_reports' AND INDEX_NAME='scouting_reports_uuid_unique') THEN
        ALTER TABLE `scouting_reports` ADD UNIQUE INDEX `scouting_reports_uuid_unique` (`uuid`);
    END IF;

    -- ====================================================================
    -- injury_reports — notificaciones a autoridad + justificación ubic. manual + uuid
    -- ====================================================================
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='authority_notifications') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `authority_notifications` JSON NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='manual_location_justification') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `manual_location_justification` TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='uuid') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `uuid` CHAR(36) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND INDEX_NAME='injury_reports_uuid_unique') THEN
        ALTER TABLE `injury_reports` ADD UNIQUE INDEX `injury_reports_uuid_unique` (`uuid`);
    END IF;

    -- ====================================================================
    -- hazardnotifications — justificación ubic. manual + uuid
    -- ====================================================================
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='manual_location_justification') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `manual_location_justification` TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='uuid') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `uuid` CHAR(36) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND INDEX_NAME='hazardnotifications_uuid_unique') THEN
        ALTER TABLE `hazardnotifications` ADD UNIQUE INDEX `hazardnotifications_uuid_unique` (`uuid`);
    END IF;

    -- ====================================================================
    -- unsafeconds — justificación ubic. manual + uuid
    -- ====================================================================
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='manual_location_justification') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `manual_location_justification` TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='uuid') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `uuid` CHAR(36) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND INDEX_NAME='unsafeconds_uuid_unique') THEN
        ALTER TABLE `unsafeconds` ADD UNIQUE INDEX `unsafeconds_uuid_unique` (`uuid`);
    END IF;

    -- ====================================================================
    -- i18n de catálogos (solo dejar la columna lista; otra fase la puebla).
    -- ====================================================================
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='safety_standards' AND COLUMN_NAME='category_name_en') THEN
        ALTER TABLE `safety_standards` ADD COLUMN `category_name_en` VARCHAR(255) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='departments' AND COLUMN_NAME='name_en') THEN
        ALTER TABLE `departments` ADD COLUMN `name_en` VARCHAR(255) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='positions' AND COLUMN_NAME='name_en') THEN
        ALTER TABLE `positions` ADD COLUMN `name_en` VARCHAR(255) NULL;
    END IF;
END //
DELIMITER ;

CALL crewcare_modules_6_14_2026_07_12();
DROP PROCEDURE IF EXISTS crewcare_modules_6_14_2026_07_12;

-- ----------------------------------------------------------------------------
-- 4) Backfill idempotente de uuid para filas legadas (solo donde es NULL).
--    UUID() se evalúa POR FILA en un UPDATE (verificado con SELECT UUID() UNION
--    SELECT UUID() → dos valores distintos), así que cada fila recibe un valor
--    ÚNICO y NO rompe el índice UNIQUE. Re-ejecutar es no-op (ya no hay NULLs).
--    Se corre DESPUÉS del CALL (las columnas ya existen).
-- ----------------------------------------------------------------------------
UPDATE `daily_reports`       SET `uuid` = UUID() WHERE `uuid` IS NULL;
UPDATE `scouting_reports`    SET `uuid` = UUID() WHERE `uuid` IS NULL;
UPDATE `injury_reports`      SET `uuid` = UUID() WHERE `uuid` IS NULL;
UPDATE `hazardnotifications` SET `uuid` = UUID() WHERE `uuid` IS NULL;
UPDATE `unsafeconds`         SET `uuid` = UUID() WHERE `uuid` IS NULL;
