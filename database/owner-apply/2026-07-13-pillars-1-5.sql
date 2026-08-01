-- ============================================================================
-- CrewCare — PILARES 1–5 (2026-07-13): agilidad, DSR hub, SDS/SFX, addendums,
-- feature flags. Solo ESQUEMA (tablas + columnas). Modelos/controladores son
-- DEFENSIVOS (Schema::hasTable/hasColumn) → la app corre aunque esto no exista;
-- al aplicar el delta las features se activan solas.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). En un cliente MySQL:
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-13-pillars-1-5.sql
-- Después:  php artisan db:seed --class=ConsumableSeeder   (biblioteca SDS/SFX)
--
-- Convención del repo: BIGINT UNSIGNED, sin FK dura (integridad en la app).
-- Idempotente: CREATE TABLE IF NOT EXISTS + ALTERs vía procedimiento temporal con
-- checks en information_schema (MySQL 5.7 no soporta ADD COLUMN IF NOT EXISTS).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) feature_flags — conmutadores de módulos (Pilar 5). BD sobrescribe
--    config/features.php (ver App\Support\Features). PK por `key`.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feature_flags` (
    `key`         VARCHAR(100) NOT NULL,
    `enabled`     TINYINT(1)   NOT NULL DEFAULT 0,
    `label`       VARCHAR(255) NULL,
    `description` VARCHAR(500) NULL,
    `created_at`  TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2) consumables — Biblioteca VIVA de SDS/consumibles (Pilar 3). Químicos y
--    elementos de efectos especiales (humo, haze, fuego, salvas, combustibles…).
--    Expansible por CRUD; sembrada por ConsumableSeeder.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `consumables` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(255) NOT NULL,
    `type`         VARCHAR(40)  NOT NULL DEFAULT 'other',  -- smoke|haze|fire|blank|fuel|pyro|chemical|cryo|other
    `description`  VARCHAR(600) NULL,
    `hazards`      TEXT NULL,          -- peligros/riesgos clave (H&S)
    `precautions`  TEXT NULL,          -- EPP / medidas
    `signal_word`  VARCHAR(20)  NULL,  -- Peligro | Atención (GHS)
    `un_number`    VARCHAR(20)  NULL,  -- ONU (transporte) si aplica
    `sds_url`      VARCHAR(500) NULL,  -- enlace a la Hoja de Datos de Seguridad
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`   INT          NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`   TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `consumables_type_idx`   (`type`),
    KEY `consumables_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3) sfx_events — sesión de Efecto Especial (Pilar 3). El toggle Iniciar/Detener
--    crea/cierra una sesión; al INICIAR se inyecta un daily_log al DSR con la hora
--    y el "Criterio del Safety" (texto libre; sin checkboxes binarios).
--    daily_report_id = DSR donde se inyectó (nullable si aún no resuelto).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sfx_events` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `consumable_id`   BIGINT UNSIGNED NULL,
    `daily_report_id` BIGINT UNSIGNED NULL,
    `production_ref`  VARCHAR(255) NULL,   -- referencia de producción (no hay FK dura)
    `effect_label`    VARCHAR(255) NULL,   -- nombre del efecto (ej. "Humo en set A")
    `safety_criteria` TEXT NULL,           -- criterio del Safety al iniciar (texto libre)
    `status`          VARCHAR(20) NOT NULL DEFAULT 'active', -- active | ended
    `started_by_id`   BIGINT UNSIGNED NULL,
    `started_at`      DATETIME NULL,
    `ended_at`        DATETIME NULL,
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `sfx_events_status_idx`  (`status`),
    KEY `sfx_events_dsr_idx`     (`daily_report_id`),
    KEY `sfx_events_consum_idx`  (`consumable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4) addendums — Medical Addendum (Pilar 4). Cambios de diagnóstico POSTERIORES
--    a un injury_report. APPEND-ONLY: no altera el reporte original (ya firmado)
--    ni su PDF; las estadísticas "efectivas" se leen del último addendum.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `addendums` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `injury_report_id`       BIGINT UNSIGNED NOT NULL,
    `type`                   VARCHAR(40) NOT NULL DEFAULT 'diagnosis_change', -- diagnosis_change|treatment_update|note
    `body`                   TEXT NOT NULL,
    `new_treatment_level`    VARCHAR(40) NULL,   -- first_aid|medical_treatment|hospitalization|fatality
    `new_is_recordable`      TINYINT(1) NULL,
    `new_days_away`          INT NULL,
    `new_days_restricted`    INT NULL,
    `created_by_id`          BIGINT UNSIGNED NULL,
    `created_at`             TIMESTAMP NULL DEFAULT NULL,
    `updated_at`             TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `addendums_injury_idx` (`injury_report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5) Columnas nuevas (idempotentes vía procedimiento temporal).
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS crewcare_pillars_1_5_2026_07_13;

DELIMITER //
CREATE PROCEDURE crewcare_pillars_1_5_2026_07_13()
BEGIN
    -- ===== Pilar 2: inyección polimórfica al DSR =====
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_logs' AND COLUMN_NAME='sourceable_id') THEN
        ALTER TABLE `daily_logs` ADD COLUMN `sourceable_id` BIGINT UNSIGNED NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_logs' AND COLUMN_NAME='sourceable_type') THEN
        ALTER TABLE `daily_logs` ADD COLUMN `sourceable_type` VARCHAR(255) NULL;
        ALTER TABLE `daily_logs` ADD KEY `daily_logs_sourceable_idx` (`sourceable_type`, `sourceable_id`);
    END IF;

    -- daily_reports: production_id (scoping futuro; hoy se resuelve por report_date).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND COLUMN_NAME='production_id') THEN
        ALTER TABLE `daily_reports` ADD COLUMN `production_id` BIGINT UNSIGNED NULL;
        ALTER TABLE `daily_reports` ADD KEY `daily_reports_production_idx` (`production_id`);
    END IF;

    -- ===== Pilar 4 (override) + Pilar 1 (captura en 2 fases) en los 3 reportes 5×5 =====
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='override_risk_level') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `override_risk_level` VARCHAR(20) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='pending_compliance') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `pending_compliance` TINYINT(1) NOT NULL DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='override_risk_level') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `override_risk_level` VARCHAR(20) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='pending_compliance') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `pending_compliance` TINYINT(1) NOT NULL DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='override_risk_level') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `override_risk_level` VARCHAR(20) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='pending_compliance') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `pending_compliance` TINYINT(1) NOT NULL DEFAULT 0;
    END IF;

    -- ===== Pilar 1b: Magic Links (canal del responsable + foto de mitigación) =====
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='action_items' AND COLUMN_NAME='responsible_name') THEN
        ALTER TABLE `action_items` ADD COLUMN `responsible_name` VARCHAR(255) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='action_items' AND COLUMN_NAME='responsible_phone') THEN
        ALTER TABLE `action_items` ADD COLUMN `responsible_phone` VARCHAR(30) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='action_items' AND COLUMN_NAME='mitigation_image_path') THEN
        ALTER TABLE `action_items` ADD COLUMN `mitigation_image_path` VARCHAR(1000) NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='action_items' AND COLUMN_NAME='mitigation_note') THEN
        ALTER TABLE `action_items` ADD COLUMN `mitigation_note` TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='action_items' AND COLUMN_NAME='mitigation_uploaded_at') THEN
        ALTER TABLE `action_items` ADD COLUMN `mitigation_uploaded_at` DATETIME NULL;
    END IF;

    -- ===== Pilar 2 (filtro de ruido): liga OPCIONAL consulta médica → accidente =====
    -- La consulta médica común NO se inyecta al DSR; solo cruza si se liga a un
    -- accidente laboral (injury_report_id no nulo).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='injury_report_id') THEN
        ALTER TABLE `cmedic` ADD COLUMN `injury_report_id` BIGINT UNSIGNED NULL;
        ALTER TABLE `cmedic` ADD KEY `cmedic_injury_idx` (`injury_report_id`);
    END IF;
END //
DELIMITER ;

CALL crewcare_pillars_1_5_2026_07_13();
DROP PROCEDURE IF EXISTS crewcare_pillars_1_5_2026_07_13;
