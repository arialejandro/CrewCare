-- ============================================================================
-- CrewCare — Upgrades ESTRUCTURALES H&S (2026-07-09)
-- Cierra brechas de trazabilidad, validación y evaluación de riesgos detectadas
-- en la Auditoría de Contenido (Auditoria-Formularios-Seguridad-CrewCare.md).
--
-- Contenido:
--   1) TABLA  action_items      — Motor de Acciones Correctivas (PDCA, polimórfico).
--   2) TABLA  standardables     — Pivote polimórfico reporte ↔ safety_standards (N:M).
--   3) COLS   injury_reports    — Registrabilidad OSHA 300/301 + fatiga + RCA + EPP.
--   4) COLS   hazardnotifications / unsafeconds / injury_reports — matriz 5×5
--            (likelihood A–E + consequence 1–5; risk_level lo calcula el servidor).
--   5) COL    scouting_reports  — sb132_details (JSON) para el desglose SB-132.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), igual que el resto del
-- esquema. El CÓDIGO ya es DEFENSIVO (Schema::hasTable / hasColumn + $fillable):
-- las pantallas siguen funcionando aunque estas columnas/tablas aún no existan;
-- al aplicar este delta, las features se activan solas.
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`; para que RE-EJECUTARLO sea
-- SEGURO (idempotente) los ALTER se guardan con un check en information_schema
-- vía un procedimiento temporal. Las tablas usan `CREATE TABLE IF NOT EXISTS`.
--
-- Convención del repo: BIGINT UNSIGNED sin FK dura (igual que created_by_id);
-- la integridad referencial se cuida en la app (App\Models\SafetyStandard purga
-- standardables al borrar una norma). Así se evitan fallos de engine/charset.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) action_items — Motor de Acciones Correctivas (PDCA, polimórfico)
--    owner_id / verified_by_id son NULL: los formularios aún no capturan un
--    responsable nominal (brecha conocida); por defecto owner = autor del reporte.
--    source/source_field marcan los items AUTO-generados desde texto correctivo
--    libre → permiten actualizarlos idempotentemente sin duplicar.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `action_items` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actionable_type` VARCHAR(255) NOT NULL,
    `actionable_id`   BIGINT UNSIGNED NOT NULL,
    `description`     TEXT NOT NULL,
    `owner_id`        BIGINT UNSIGNED NULL,
    `due_date`        DATETIME NULL,
    `status`          ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
    `verified_by_id`  BIGINT UNSIGNED NULL,
    `closed_at`       DATETIME NULL,
    `source`          VARCHAR(50) NULL,       -- 'auto' | 'manual'
    `source_field`    VARCHAR(100) NULL,      -- p.ej. 'suggestions_corrective_action'
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `action_items_morph_idx`  (`actionable_type`, `actionable_id`),
    KEY `action_items_status_idx` (`status`),
    KEY `action_items_owner_idx`  (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2) standardables — pivote polimórfico N:M (reporte ↔ safety_standards)
--    Permite vincular un hallazgo a VARIAS normas (además del snapshot único
--    regulation_badge/code que ya existe). UNIQUE evita duplicados en sync().
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `standardables` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `safety_standard_id` BIGINT UNSIGNED NOT NULL,
    `standardable_type`  VARCHAR(255) NOT NULL,
    `standardable_id`    BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `standardables_unique` (`safety_standard_id`, `standardable_type`, `standardable_id`),
    KEY `standardables_morph_idx` (`standardable_type`, `standardable_id`),
    KEY `standardables_std_idx`   (`safety_standard_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3/4/5) Columnas nuevas (idempotentes vía procedimiento temporal)
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS crewcare_structural_2026_07_09;

DELIMITER //
CREATE PROCEDURE crewcare_structural_2026_07_09()
BEGIN
    -- ====================================================================
    -- injury_reports — Registrabilidad OSHA + Fatiga + RCA + EPP + matriz 5×5
    -- ====================================================================

    -- call_time: hora de llamado/inicio de turno del lesionado (para calcular fatiga).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='call_time') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `call_time` TIME NULL AFTER `time`;
    END IF;

    -- hours_worked_prior: horas trabajadas antes del incidente (time - call_time).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='hours_worked_prior') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `hours_worked_prior` DECIMAL(4,2) NULL AFTER `call_time`;
    END IF;

    -- employer_name: patrón/contratista del lesionado (aviso IMSS/STPS).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='employer_name') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `employer_name` VARCHAR(255) NULL AFTER `department`;
    END IF;

    -- treatment_level: nivel de atención (determina registrabilidad OSHA 300/301).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='treatment_level') THEN
        ALTER TABLE `injury_reports`
            ADD COLUMN `treatment_level` ENUM('first_aid','medical_treatment','hospitalization','fatality') NULL AFTER `hospital`;
    END IF;

    -- days_away_from_work / days_restricted_work: días perdidos / trabajo restringido.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='days_away_from_work') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `days_away_from_work` INT NOT NULL DEFAULT 0 AFTER `treatment_level`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='days_restricted_work') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `days_restricted_work` INT NOT NULL DEFAULT 0 AFTER `days_away_from_work`;
    END IF;

    -- is_recordable: se FUERZA server-side (Observer) según treatment_level / días.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='is_recordable') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `is_recordable` TINYINT(1) NOT NULL DEFAULT 0 AFTER `days_restricted_work`;
    END IF;

    -- root_cause_analysis (JSON): análisis de causa raíz estructurado.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='root_cause_analysis') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `root_cause_analysis` JSON NULL AFTER `what_caused`;
    END IF;

    -- ppe_details (JSON): EPP portado/requerido.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='ppe_details') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `ppe_details` JSON NULL AFTER `root_cause_analysis`;
    END IF;

    -- matriz 5×5: likelihood (A–E) + consequence (1–5) + risk_level (calculado).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='likelihood') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `likelihood` CHAR(1) NULL AFTER `frequency`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='consequence') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `consequence` TINYINT NULL AFTER `likelihood`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='risk_level') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `risk_level` VARCHAR(20) NULL AFTER `consequence`;
    END IF;

    -- ====================================================================
    -- hazardnotifications — matriz 5×5 (ya tiene risk_level; faltan ejes).
    -- ====================================================================
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='likelihood') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `likelihood` CHAR(1) NULL AFTER `risk_level`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='consequence') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `consequence` TINYINT NULL AFTER `likelihood`;
    END IF;

    -- ====================================================================
    -- unsafeconds — matriz 5×5 (ya tiene risk_level; faltan ejes).
    -- ====================================================================
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='likelihood') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `likelihood` CHAR(1) NULL AFTER `risk_level`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='consequence') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `consequence` TINYINT NULL AFTER `likelihood`;
    END IF;

    -- ====================================================================
    -- scouting_reports — sb132_details (JSON): desglose de actividades especiales
    -- (activity_type, scene_number, certified_personnel_required) exigido si
    -- special_activities = true (gatillo SB-132).
    -- ====================================================================
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='scouting_reports' AND COLUMN_NAME='sb132_details') THEN
        ALTER TABLE `scouting_reports` ADD COLUMN `sb132_details` JSON NULL AFTER `requires_specific_ra`;
    END IF;
END //
DELIMITER ;

CALL crewcare_structural_2026_07_09();
DROP PROCEDURE IF EXISTS crewcare_structural_2026_07_09;
